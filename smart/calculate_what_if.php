<?php
// Filename: calculate_what_if.php

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
// --- Get inputs from the AJAX request ---
$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$service_level = filter_input(INPUT_GET, 'service_level', FILTER_VALIDATE_FLOAT);
$holding_cost_rate = filter_input(INPUT_GET, 'holding_cost', FILTER_VALIDATE_FLOAT);
$ordering_cost = filter_input(INPUT_GET, 'ordering_cost', FILTER_VALIDATE_FLOAT);
$lead_time_days = filter_input(INPUT_GET, 'lead_time', FILTER_VALIDATE_INT);

if (!$item_id || $service_level === false || $holding_cost_rate === false || $ordering_cost === false || $lead_time_days === false) {
    echo json_encode(['error' => 'Invalid parameters provided.']);
    exit();
}

// Z-score mapping for common service levels
$z_scores = [
    80 => 0.84, 85 => 1.04, 90 => 1.28, 91 => 1.34, 92 => 1.41, 93 => 1.48, 94 => 1.56,
    95 => 1.65, 96 => 1.75, 97 => 1.88, 98 => 2.05, 99 => 2.33
];
// Find the closest Z-score if not exact
$closest_level = array_reduce(array_keys($z_scores), function ($a, $b) use ($service_level) {
    return abs($service_level - $a) < abs($service_level - $b) ? $a : $b;
});
$z_score = $z_scores[$closest_level];

// --- Fetch Item and Transaction Data ---
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
$item_sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost,
        s.average_lead_time_days as default_lead_time,
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
         WHERE transaction_date >= ? AND item_id = ?
         GROUP BY item_id
        ) as td ON i.item_id = td.item_id
    WHERE i.item_id = ?
";

$stmt = $conn->prepare($item_sql);
$stmt->bind_param("sii", $ninety_days_ago, $item_id, $item_id);
$stmt->execute();
$item_result = $stmt->get_result();
$item = $item_result->fetch_assoc();
$stmt->close();

if (!$item || $item['total_usage_90_days'] == 0 || $item['transaction_days_90'] < 7) {
    echo json_encode(['error' => 'Not enough transaction data for this item to run a simulation.']);
    exit();
}

// --- CALCULATIONS ---
// 1. Demand Calculation
$avg_daily_demand = $item['total_usage_90_days'] / 90;
$annual_demand = $avg_daily_demand * 365; // D

// 2. EOQ (Economic Order Quantity)
$unit_cost = (float)$item['unit_cost'];
$holding_cost_per_unit = $unit_cost * ($holding_cost_rate / 100);

if ($holding_cost_per_unit <= 0) {
    echo json_encode(['error' => 'Holding cost cannot be zero or less.']);
    exit();
}

$eoq = sqrt((2 * $annual_demand * $ordering_cost) / $holding_cost_per_unit);

// 3. Safety Stock
$std_dev_daily_demand = sqrt($avg_daily_demand);
$safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);

// 4. Reorder Point (ROP)
$demand_during_lead_time = $avg_daily_demand * $lead_time_days;
$reorder_point = $demand_during_lead_time + $safety_stock;

// --- Send JSON Response ---
echo json_encode([
    'item_name' => htmlspecialchars($item['name']),
    'item_code' => htmlspecialchars($item['item_code']),
    'avg_daily_demand' => round($avg_daily_demand, 2),
    'default_lead_time' => $item['default_lead_time'] ? (int)$item['default_lead_time'] : 7,
    'simulated_policy' => [
        'safety_stock' => round($safety_stock),
        'reorder_point' => round($reorder_point),
        'eoq' => round($eoq)
    ]
]);

$conn->close();
?>

