<?php
// Filename: calculate_what_if_all_a.php
// Purpose: Runs what-if scenarios for all A-class items across multiple service levels.
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Catch fatal errors and return them as JSON (fatal errors bypass set_error_handler)
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fatal: '.$err['message'].' in '.basename($err['file']).' line '.$err['line']]);
    }
});

// Catch uncaught exceptions (e.g. mysqli_sql_exception) as JSON
set_exception_handler(function($e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => get_class($e).': '.$e->getMessage().' on line '.$e->getLine()]);
    exit(1);
});

// Catch non-fatal errors as JSON too
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => "Error [$errno]: $errstr on line $errline"]);
    exit(1);
});

ob_start();

require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'])) {
    ob_end_flush();
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$holding_cost_rate = filter_input(INPUT_GET, 'holding_cost', FILTER_VALIDATE_FLOAT) ?: 25.0;
$ordering_cost     = filter_input(INPUT_GET, 'ordering_cost', FILTER_VALIDATE_FLOAT) ?: 50.0;

$scenarios = [
    ['label' => '90%', 'z' => 1.28],
    ['label' => '95%', 'z' => 1.65],
    ['label' => '99%', 'z' => 2.33],
];

$one_year_ago    = date('Y-m-d', strtotime('-365 days'));
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

$abc_sql = "
    SELECT i.item_id, i.name, i.item_code, i.unit_cost,
           COALESCE(SUM(t.quantity_used), 0) AS total_usage_year
    FROM items i
    LEFT JOIN transactions t ON i.item_id = t.item_id AND t.transaction_date >= ?
    GROUP BY i.item_id, i.name, i.item_code, i.unit_cost
    HAVING i.unit_cost > 0.00 AND COALESCE(SUM(t.quantity_used), 0) > 0
    ORDER BY (COALESCE(SUM(t.quantity_used), 0) * i.unit_cost) DESC
";

$stmt = $conn->prepare($abc_sql);
if (!$stmt) {
    ob_end_clean();
    echo json_encode(['error' => 'DB prepare failed (abc_sql): '.$conn->error]);
    exit();
}
$stmt->bind_param('s', $one_year_ago);
$stmt->execute();
$abc_result = $stmt->get_result();
if (!$abc_result) {
    ob_end_clean();
    echo json_encode(['error' => 'DB get_result failed (abc_sql): '.$stmt->error]);
    exit();
}
$stmt->close();

$all_items = [];
$total_acv  = 0;
while ($row = $abc_result->fetch_assoc()) {
    $acv         = (float)$row['unit_cost'] * (float)$row['total_usage_year'];
    $all_items[] = $row + ['acv' => $acv];
    $total_acv  += $acv;
}

if (empty($all_items)) {
    ob_end_clean();
    echo json_encode(['error' => 'No items with annual consumption value found.']);
    exit();
}

$a_items   = [];
$cumulative = 0;
foreach ($all_items as $item) {
    $cumulative += $item['acv'];
    $a_items[]   = $item;
    if ($total_acv > 0 && ($cumulative / $total_acv) >= 0.80) break;
}

if (empty($a_items)) {
    ob_end_clean();
    echo json_encode(['error' => 'No A-class items found.']);
    exit();
}

$item_ids     = array_map('intval', array_column($a_items, 'item_id'));
$placeholders = implode(',', array_fill(0, count($item_ids), '?'));
$types        = str_repeat('i', count($item_ids));

$demand_sql = "
    SELECT i.item_id,
           COALESCE(s.average_lead_time_days, 7) AS lead_time,
           COALESCE(td.total_usage_90, 0)        AS usage_90,
           COALESCE(td.tx_days, 0)               AS tx_days
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id,
               SUM(quantity_used)              AS total_usage_90,
               COUNT(DISTINCT transaction_date) AS tx_days
        FROM transactions
        WHERE transaction_date >= ?
          AND item_id IN ($placeholders)
        GROUP BY item_id
    ) td ON i.item_id = td.item_id
    WHERE i.item_id IN ($placeholders)
";

$params      = array_merge([$ninety_days_ago], $item_ids, $item_ids);
$param_types = 's'.$types.$types;
$stmt2 = $conn->prepare($demand_sql);
if (!$stmt2) {
    ob_end_clean();
    echo json_encode(['error' => 'DB prepare failed (demand_sql): '.$conn->error]);
    exit();
}
$stmt2->bind_param($param_types, ...$params);
$stmt2->execute();
$demand_result = $stmt2->get_result();
if (!$demand_result) {
    ob_end_clean();
    echo json_encode(['error' => 'DB get_result failed (demand_sql): '.$stmt2->error]);
    exit();
}
$demand_rows = [];
while ($dr = $demand_result->fetch_assoc()) {
    $demand_rows[] = $dr;
}
$stmt2->close();

$demand_map = [];
foreach ($demand_rows as $dr) {
    $demand_map[$dr['item_id']] = $dr;
}

$rows = [];
$scenario_totals = array_fill(0, count($scenarios), ['total_safety_cost' => 0, 'total_annual_holding' => 0]);

foreach ($a_items as $item) {
    $iid  = $item['item_id'];
    $dmnd = $demand_map[$iid] ?? ['usage_90' => 1, 'tx_days' => 7, 'lead_time' => 7];

    $unit_cost = max(0.01, (float)$item['unit_cost']);
    $lead_time = max(1, (int)$dmnd['lead_time']);
    $usage_90  = (float)$dmnd['usage_90'] > 0 ? (float)$dmnd['usage_90'] : 1;
    $avg_daily = $usage_90 / 90.0;
    $annual_d  = $avg_daily * 365.0;

    $hcpu = $unit_cost * ($holding_cost_rate / 100.0);
    $eoq  = $hcpu > 0 ? (int)ceil(sqrt((2 * $annual_d * $ordering_cost) / $hcpu)) : 0;
    $std  = sqrt($avg_daily);

    $item_scenarios = [];
    foreach ($scenarios as $idx => $sc) {
        $ss   = (int)round($sc['z'] * $std * sqrt($lead_time));
        $rop  = (int)round($avg_daily * $lead_time + $ss);
        $hold = round($ss * $hcpu, 2);

        $item_scenarios[] = [
            'label'        => $sc['label'],
            'safety_stock' => $ss,
            'rop'          => $rop,
            'holding_cost' => $hold,
        ];
        $scenario_totals[$idx]['total_safety_cost']   += $ss * $unit_cost;
        $scenario_totals[$idx]['total_annual_holding'] += $hold;
    }

    $rows[] = [
        'item_id'   => $iid,
        'item_name' => htmlspecialchars($item['name']),
        'item_code' => htmlspecialchars($item['item_code']),
        'unit_cost' => number_format($unit_cost, 2),
        'eoq'       => $eoq,
        'avg_daily' => round($avg_daily, 3),
        'scenarios' => $item_scenarios,
    ];
}

foreach ($scenario_totals as &$st) {
    $st['total_safety_cost']    = number_format($st['total_safety_cost'], 2);
    $st['total_annual_holding'] = number_format($st['total_annual_holding'], 2);
}

ob_end_clean();
echo json_encode([
    'item_count'      => count($rows),
    'scenario_labels' => array_column($scenarios, 'label'),
    'scenario_totals' => $scenario_totals,
    'rows'            => $rows,
]);
$conn->close();
?>

