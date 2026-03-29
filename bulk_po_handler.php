<?php
// Filename: smart/bulk_po_handler.php
// Purpose: Auto-generates Purchase Orders for all items currently below ROP that have a supplier set.
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: order_suggestion.php");
    exit();
}

// CSRF validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security error: Invalid request token.";
    header("Location: order_suggestion.php");
    exit();
}

// --- Re-run ROP/EOQ calculation server-side (never trust client data) ---
$settings = [];
$settings_result = $conn->query("SELECT setting_name, setting_value FROM settings");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}

$ordering_cost      = (float)($settings['ordering_cost']      ?? 50.0);
$holding_cost_rate  = (float)($settings['holding_cost_rate']  ?? 25.0);
$service_level      = (float)($settings['service_level']      ?? 95.0);

$z_scores      = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$closest_level = array_reduce(array_keys($z_scores), function ($a, $b) use ($service_level) {
    return abs($service_level - $a) < abs($service_level - $b) ? $a : $b;
});
$z_score = $z_scores[$closest_level] ?? 1.65;

$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

$items_sql = "
    SELECT
        i.item_id, i.name, i.item_code, i.unit_cost, i.unit_of_measure,
        s.supplier_id, s.average_lead_time_days,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND quantity > 0 AND status = 'Active'), 0) AS current_stock,
        COALESCE(td.total_usage, 0)     AS total_usage_90_days,
        COALESCE(td.transaction_days, 0) AS transaction_days_90
    FROM items i
    INNER JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) AS total_usage, COUNT(DISTINCT transaction_date) AS transaction_days
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) AS td ON i.item_id = td.item_id
    ORDER BY i.name ASC
";

$stmt = $conn->prepare($items_sql);
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: order_suggestion.php");
    exit();
}
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

$suggestions = [];
while ($item = $items_result->fetch_assoc()) {
    $current_stock_check = (int)$item['current_stock'];
    $has_sufficient_data = $item['total_usage_90_days'] > 0 && $item['transaction_days_90'] >= 1;
    $is_zero_stock       = $current_stock_check === 0;

    if (!$has_sufficient_data && !$is_zero_stock) {
        continue;
    }

    $unit_cost       = max(0.01, (float)($item['unit_cost'] ?? 0.01));
    $lead_time_days  = $item['average_lead_time_days'] ? (int)$item['average_lead_time_days'] : 7;

    $usage_90           = $item['total_usage_90_days'] > 0 ? $item['total_usage_90_days'] : 1;
    $avg_daily_demand   = $usage_90 / 90.0;
    $annual_demand      = $avg_daily_demand * 365.0;

    $safe_hcr            = max(0.1, $holding_cost_rate);
    $holding_cost_per_u  = $unit_cost * ($safe_hcr / 100.0);
    $eoq                 = ($holding_cost_per_u > 0) ? sqrt((2.0 * $annual_demand * $ordering_cost) / $holding_cost_per_u) : 0;
    $suggested_qty       = max(1, (int)ceil($eoq));

    $std_dev       = ($avg_daily_demand > 0) ? sqrt($avg_daily_demand) : 0;
    $safety_stock  = $z_score * $std_dev * sqrt((float)$lead_time_days);
    $rop           = round($avg_daily_demand * $lead_time_days + $safety_stock);

    if ($current_stock_check <= $rop) {
        $suggestions[] = [
            'item_id'      => (int)$item['item_id'],
            'item_name'    => $item['name'],
            'supplier_id'  => (int)$item['supplier_id'],
            'quantity'     => $suggested_qty,
            'unit_cost'    => $unit_cost,
            'lead_time'    => $lead_time_days,
        ];
    }
}
$stmt->close();

if (empty($suggestions)) {
    $_SESSION['message'] = "No items currently require reordering. No POs were created.";
    header("Location: order_suggestion.php");
    exit();
}

// --- Create POs in a single transaction ---
$user_id   = (int)$_SESSION['user_id'];
$username  = $_SESSION['username'];
$batch_key = date('Ymd') . '-' . date('His'); // e.g. 20250610-143022

$conn->begin_transaction();
$created_count = 0;

try {
    $stmt_po = $conn->prepare(
        "INSERT INTO purchase_orders
            (po_number, item_id, supplier_id, quantity_ordered, unit_cost_agreed, expected_delivery_date, created_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_log = $conn->prepare(
        "INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)"
    );

    foreach ($suggestions as $idx => $s) {
        $seq       = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
        $po_number = "BULK-{$batch_key}-{$seq}";
        $exp_delivery = date('Y-m-d', strtotime("+{$s['lead_time']} days"));

        $stmt_po->bind_param("siiidsi",
            $po_number, $s['item_id'], $s['supplier_id'],
            $s['quantity'], $s['unit_cost'], $exp_delivery, $user_id
        );
        $stmt_po->execute();

        $action  = "Bulk PO Creation";
        $details = "PO #{$po_number} auto-generated by {$username} for {$s['quantity']} unit(s) of '{$s['item_name']}'. Est. cost: ₱"
                   . number_format($s['quantity'] * $s['unit_cost'], 2) . ".";
        $stmt_log->bind_param("isss", $user_id, $username, $action, $details);
        $stmt_log->execute();

        $created_count++;
    }

    $stmt_po->close();
    $stmt_log->close();
    $conn->commit();

    $_SESSION['message'] = "{$created_count} Purchase Order(s) auto-generated successfully. Review them in PO Management.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error during bulk PO creation: " . $e->getMessage();
}

$conn->close();
header("Location: po_management.php");
exit();
?>
