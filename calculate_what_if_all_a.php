<?php
// Filename: calculate_what_if_all_a.php
// Purpose: Runs what-if scenarios for all A-class items across multiple service levels.
header('Content-Type: application/json');
error_reporting(0);
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$holding_cost_rate = filter_input(INPUT_GET, 'holding_cost', FILTER_VALIDATE_FLOAT) ?: 25.0;
$ordering_cost     = filter_input(INPUT_GET, 'ordering_cost', FILTER_VALIDATE_FLOAT) ?: 50.0;

// Z-scores for three representative service levels
$scenarios = [
    ['label' => '90%', 'z' => 1.28],
    ['label' => '95%', 'z' => 1.65],
    ['label' => '99%', 'z' => 2.33],
];

// --- 1. Identify A-class items (top ~80% cumulative ACV) ---
$one_year_ago = date('Y-m-d', strtotime('-365 days'));
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

$abc_sql = "
    SELECT i.item_id, i.name, i.item_code, i.unit_cost,
           COALESCE(SUM(t.quantity_used), 0) AS total_usage_year
    FROM items i
    LEFT JOIN transactions t ON i.item_id = t.item_id AND t.transaction_date >= ?
    GROUP BY i.item_id, i.name, i.item_code, i.unit_cost
    HAVING i.unit_cost > 0.00 AND total_usage_year > 0
    ORDER BY (total_usage_year * i.unit_cost) DESC
";
$stmt = $conn->prepare($abc_sql);
$stmt->bind_param("s", $one_year_ago);
$stmt->execute();
$abc_result = $stmt->get_result();
$stmt->close();

$all_items = [];
$total_acv = 0;
while ($row = $abc_result->fetch_assoc()) {
    $acv = (float)$row['unit_cost'] * (int)$row['total_usage_year'];
    $all_items[] = $row + ['acv' => $acv];
    $total_acv += $acv;
}

if (empty($all_items)) {
    echo json_encode(['error' => 'No items with annual consumption value found.']);
    exit();
}

// Select A-class = cumulative ACV ≤ 80%
$a_items = [];
$cumulative = 0;
foreach ($all_items as $item) {
    $cumulative += $item['acv'];
    $a_items[]   = $item;
    if ($total_acv > 0 && ($cumulative / $total_acv) >= 0.80) break;
}

if (empty($a_items)) {
    echo json_encode(['error' => 'No A-class items found.']);
    exit();
}

// --- 2. Fetch demand stats for A-class items ---
$item_ids = array_column($a_items, 'item_id');
$placeholders = implode(',', array_fill(0, count($item_ids), '?'));
$types = str_repeat('i', count($item_ids));

$demand_sql = "
    SELECT i.item_id,
           COALESCE(s.average_lead_time_days, 7) AS lead_time,
           COALESCE(td.total_usage_90, 0)        AS usage_90,
           COALESCE(td.tx_days, 0)               AS tx_days
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) AS total_usage_90,
               COUNT(DISTINCT transaction_date) AS tx_days
        FROM transactions
        WHERE transaction_date >= ? AND item_id IN ($placeholders)
        GROUP BY item_id
    ) td ON i.item_id = td.item_id
    WHERE i.item_id IN ($placeholders)
";

$params = array_merge([$ninety_days_ago], $item_ids, $item_ids);
$param_types = 's' . $types . $types;
$stmt2 = $conn->prepare($demand_sql);
$stmt2->bind_param($param_types, ...$params);
$stmt2->execute();
$demand_rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$demand_map = [];
foreach ($demand_rows as $dr) {
    $demand_map[$dr['item_id']] = $dr;
}

// --- 3. Build results ---
$rows = [];
$scenario_totals = array_fill(0, count($scenarios), ['total_safety_cost' => 0, 'total_annual_holding' => 0]);

foreach ($a_items as $item) {
    $iid  = $item['item_id'];
    $dmnd = $demand_map[$iid] ?? ['usage_90' => 1, 'tx_days' => 7, 'lead_time' => 7];

    $unit_cost    = max(0.01, (float)$item['unit_cost']);
    $lead_time    = max(1, (int)$dmnd['lead_time']);
    $usage_90     = $dmnd['usage_90'] > 0 ? (float)$dmnd['usage_90'] : 1;
    $avg_daily    = $usage_90 / 90.0;
    $annual_d     = $avg_daily * 365.0;

    $hcpu  = $unit_cost * ($holding_cost_rate / 100.0);
    $eoq   = $hcpu > 0 ? (int)ceil(sqrt((2 * $annual_d * $ordering_cost) / $hcpu)) : 0;
    $std   = sqrt($avg_daily);

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

// Round scenario totals
foreach ($scenario_totals as &$st) {
    $st['total_safety_cost']   = number_format($st['total_safety_cost'], 2);
    $st['total_annual_holding'] = number_format($st['total_annual_holding'], 2);
}

echo json_encode([
    'item_count'     => count($rows),
    'scenario_labels'=> array_column($scenarios, 'label'),
    'scenario_totals'=> $scenario_totals,
    'rows'           => $rows,
]);
$conn->close();
?>
