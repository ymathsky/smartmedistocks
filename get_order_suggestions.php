<?php
// Filename: smart/get_order_suggestions.php
// Purpose: Calculates ROP and EOQ for all critical items and returns the suggestions as JSON.

// --- Error Handling & JSON Header ---
error_reporting(0); // Suppress direct error output to prevent breaking JSON
ini_set('display_errors', 0);
header('Content-Type: application/json'); // SET HEADER FIRST!

require_once 'db_connection.php';
session_start();

// Security check: Only Admins and Procurement should typically initiate orders.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    // Output JSON error and exit
    echo json_encode(['error' => 'Unauthorized access.', 'data' => []]);
    exit();
}

// --- Fetch Global Settings for Calculation ---
$settings = [];
try {
    // Added check if query was successful
    $settings_result = $conn->query("SELECT setting_name, setting_value FROM settings");
    if ($settings_result) {
        while($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    } else {
        // Log error if settings can't be fetched, but continue with defaults
        error_log("DB Error fetching settings: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Exception fetching settings: " . $e->getMessage());
    // Continue with defaults even if fetching fails
}


// Use null coalescing operator ?? for safer defaults
$ordering_cost = isset($settings['ordering_cost']) ? (float)$settings['ordering_cost'] : 50.0; // S
$holding_cost_rate = isset($settings['holding_cost_rate']) ? (float)$settings['holding_cost_rate'] : 25.0; // i
$service_level = isset($settings['service_level']) ? (float)$settings['service_level'] : 95.0; // Z

$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
// Find closest Z-score if not exact or use default
$closest_level = array_reduce(array_keys($z_scores), function ($a, $b) use ($service_level) {
    return abs($service_level - $a) < abs($service_level - $b) ? $a : $b;
});
$z_score = $z_scores[$closest_level] ?? 1.65; // Default to 95% Z-score

$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
$suggestions = [];

// --- OPTIMIZED QUERY TO GET ALL NECESSARY DATA ---
$items_sql = "
    SELECT
        i.item_id, i.name, i.item_code, i.unit_cost, i.unit_of_measure,
        s.name AS supplier_name, s.supplier_id, /* Added supplier_id */
        s.average_lead_time_days,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND quantity > 0 AND status = 'Active'), 0) as current_stock, /* Ensure only positive stock */
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
    /* Removed HAVING clause to consider all items, filtering happens in PHP */
    ORDER BY
        i.name ASC
";

$stmt = $conn->prepare($items_sql);
if (!$stmt) {
    error_log("DB Prepare Error: " . $conn->error);
    echo json_encode(['error' => 'Database query preparation failed.', 'data' => []]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

while ($item = $items_result->fetch_assoc()) {
    $current_stock_check = (int)$item['current_stock'];
    $has_sufficient_data = $item['total_usage_90_days'] > 0 && $item['transaction_days_90'] >= 1;

    // Always include completely out-of-stock items that have a supplier, even without demand history
    $is_zero_stock = $current_stock_check === 0 && $item['supplier_id'];

    if (!$has_sufficient_data && !$is_zero_stock) {
        continue; // Skip items without enough data and not critically out of stock
    }

    $current_stock = (int)$item['current_stock'];
    $unit_cost = max(0.01, (float)($item['unit_cost'] ?? 0.01));
    $lead_time_days = $item['average_lead_time_days'] ? (int)$item['average_lead_time_days'] : 7;

    // For zero-stock items with no usage history, use a minimal demand to force inclusion
    $usage_90 = $item['total_usage_90_days'] > 0 ? $item['total_usage_90_days'] : 1;
    $avg_daily_demand = $usage_90 / 90.0;
    $annual_demand = $avg_daily_demand * 365.0;

    // Ensure holding cost rate is positive before calculating per unit cost
    $safe_holding_cost_rate = max(0.1, $holding_cost_rate); // Use at least 0.1% to avoid zero
    $holding_cost_per_unit = $unit_cost * ($safe_holding_cost_rate / 100.0);

    // Calculate EOQ (check holding_cost_per_unit again for safety)
    $eoq = ($holding_cost_per_unit > 0) ? sqrt((2.0 * $annual_demand * $ordering_cost) / $holding_cost_per_unit) : 0;
    // Use ceil to round up, ensuring we order enough if EOQ is fractional and ROP is met
    $suggested_order_qty = max(1, (int)ceil($eoq));

    // Calculate ROP
    // Use sqrt($avg_daily_demand) as a proxy for std dev if actual std dev is not calculated
    $std_dev_daily_demand = ($avg_daily_demand > 0) ? sqrt($avg_daily_demand) : 0;
    $safety_stock = $z_score * $std_dev_daily_demand * sqrt((float)$lead_time_days);
    $demand_during_lead_time = $avg_daily_demand * (float)$lead_time_days;
    $reorder_point = $demand_during_lead_time + $safety_stock;
    $rop_rounded = round($reorder_point);

    // Check if item is below Reorder Point
    if ($current_stock <= $rop_rounded) {
        $suggestions[] = [
            'item_id' => $item['item_id'],
            'item_code' => htmlspecialchars($item['item_code'] ?? 'N/A'),
            'item_name' => htmlspecialchars($item['name'] ?? 'Unknown Item'),
            'current_stock' => $current_stock,
            'reorder_point' => $rop_rounded,
            'supplier_name' => htmlspecialchars($item['supplier_name'] ?? 'N/A'),
            'supplier_id' => $item['supplier_id'], // Include supplier ID
            'suggested_order_qty' => $suggested_order_qty,
            'order_cost' => number_format($suggested_order_qty * $unit_cost, 2),
            'unit_of_measure' => htmlspecialchars($item['unit_of_measure'] ?? 'unit'),
        ];
    }
}
$stmt->close();
$conn->close();

// Always output valid JSON, even if suggestions is empty
echo json_encode(['data' => $suggestions]);
?>
