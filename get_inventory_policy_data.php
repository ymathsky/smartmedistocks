<?php
// Filename: get_inventory_policy_data.php

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// --- Fetch Global Settings ---
$settings_sql = "SELECT setting_name, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

$ordering_cost = isset($settings['ordering_cost']) ? (float)$settings['ordering_cost'] : 50; // S
$holding_cost_rate = isset($settings['holding_cost_rate']) ? (float)$settings['holding_cost_rate'] : 25; // i
$service_level = isset($settings['service_level']) ? (float)$settings['service_level'] : 95; // Z

// Z-score mapping for common service levels
$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = isset($z_scores[$service_level]) ? $z_scores[$service_level] : 1.65; // Default to 95%

// --- OPTIMIZED AND CORRECTED QUERY ---
// The filtering condition is REMOVED from the HAVING clause to include ALL items.
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
$items_sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND quantity > 0), 0) as current_stock,
        s.average_lead_time_days,
        COALESCE(td.total_usage, 0) as total_usage_90_days,
        COALESCE(td.transaction_days, 0) as transaction_days_90
    FROM 
        items i
    LEFT JOIN 
        suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN 
        (SELECT 
            item_id, 
            SUM(quantity_used) as total_usage, 
            COUNT(DISTINCT transaction_date) as transaction_days
         FROM transactions
         WHERE transaction_date >= ?
         GROUP BY item_id
        ) as td ON i.item_id = td.item_id
";

$stmt = $conn->prepare($items_sql);
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();
$policy_data = [];

while ($item = $items_result->fetch_assoc()) {
    // UPDATED CHECK: Lowered requirement for demo/testing to 1 day and usage > 0.
    $has_sufficient_data = $item['total_usage_90_days'] > 0 && $item['transaction_days_90'] >= 1;

    $safety_stock = 0;
    $reorder_point = 0;
    $eoq = 0;

    if ($has_sufficient_data) {
        // --- CALCULATIONS (ONLY if data is sufficient) ---
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $annual_demand = $avg_daily_demand * 365; // D

        $lead_time_days = $item['average_lead_time_days'] ? (int)$item['average_lead_time_days'] : 7; // L (default to 7 days if not set)
        $unit_cost = (float)$item['unit_cost'];
        $holding_cost_per_unit = $unit_cost * ($holding_cost_rate / 100);

        if ($holding_cost_per_unit > 0) {
            // EOQ Formula: sqrt((2 * D * S) / (i * C))
            $eoq = sqrt((2 * $annual_demand * $ordering_cost) / $holding_cost_per_unit);
        }

        // Safety Stock Formula: Z * StdDev * sqrt(L)
        // Since we don't calculate true StdDev of daily demand, we use sqrt(AvgDemand) as a proxy (a common simplification)
        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);

        // ROP Formula: DdL + SS
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;
    }

    // --- Add to results, using calculated or placeholder values ---
    $policy_data[] = [
        'item_id' => $item['item_id'],
        'item_code' => htmlspecialchars($item['item_code']),
        'item_name' => htmlspecialchars($item['name']),
        'current_stock' => (int)$item['current_stock'],
        // Output N/A if insufficient data
        'safety_stock' => $has_sufficient_data ? round($safety_stock) : 'N/A',
        'reorder_point' => $has_sufficient_data ? round($reorder_point) : 'N/A',
        'eoq' => $has_sufficient_data ? round($eoq) : 'N/A'
    ];
}
$stmt->close();

echo json_encode($policy_data);
$conn->close();
?>
