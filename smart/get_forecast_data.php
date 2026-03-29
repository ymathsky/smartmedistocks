<?php
// Filename: get_forecast_data.php

// Set the content type to JSON for the response
header('Content-Type: application/json');

// 1. ESTABLISH DATABASE CONNECTION
require_once 'db_connection.php';
session_start();

// 2. SECURITY AND INPUT VALIDATION
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
if (!$item_id) {
    echo json_encode(['error' => 'Invalid item ID provided.']);
    exit();
}

// 3. FETCH TRANSACTION DATA
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
$sql = "
    SELECT transaction_date, SUM(quantity_used) as total_quantity
    FROM transactions
    WHERE item_id = ? AND transaction_date >= ?
    GROUP BY transaction_date
    ORDER BY transaction_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $item_id, $ninety_days_ago);
$stmt->execute();
$result = $stmt->get_result();

$transactions_by_date = [];
while ($row = $result->fetch_assoc()) {
    $transactions_by_date[$row['transaction_date']] = (int)$row['total_quantity'];
}

if (count($transactions_by_date) < 10) {
    echo json_encode(['error' => 'Not enough historical data for a trend forecast (minimum 10 days of transactions required).']);
    exit();
}

// 4. PREPARE DATA FOR THE CHART
$labels = [];
$historical_data = [];
$today = new DateTime();
$period = new DatePeriod(
    new DateTime($ninety_days_ago),
    new DateInterval('P1D'),
    $today->modify('+1 day') // Include today
);

foreach ($period as $date) {
    $date_string = $date->format('Y-m-d');
    $labels[] = $date->format('Y-m-d');
    $historical_data[] = $transactions_by_date[$date_string] ?? 0;
}

// 5. CALCULATE FORECAST (Holt's Damped Trend Method)
$alpha = 0.3; // Smoothing factor for the level
$beta = 0.2;  // Smoothing factor for the trend
$phi = 0.9;   // Damping factor for the trend (0 < phi < 1)
$forecast_data = [];
$level = [];
$trend = [];

// Initialization
$level[0] = $historical_data[0];
$trend[0] = $historical_data[1] - $historical_data[0];

// Calculate smoothed level and trend for historical data
for ($i = 1; $i < count($historical_data); $i++) {
    $last_level = $level[$i - 1];
    $last_trend = $trend[$i - 1];

    $level[$i] = $alpha * $historical_data[$i] + (1 - $alpha) * ($last_level + ($phi * $last_trend));
    $trend[$i] = $beta * ($level[$i] - $last_level) + (1 - $beta) * ($phi * $last_trend);
}

// Forecast for the next 30 days
$last_level_val = end($level);
$last_trend_val = end($trend);

for ($k = 1; $k <= 30; $k++) {
    // This formula correctly calculates the damped trend forecast for k steps ahead
    $damped_trend_component = 0;
    for ($j = 1; $j <= $k; $j++) {
        $damped_trend_component += pow($phi, $j);
    }

    $forecast = $last_level_val + ($damped_trend_component * $last_trend_val);
    $forecast_data[] = max(0, round($forecast, 2)); // Ensure forecast is not negative

    // Add the corresponding future date label
    $future_date = new DateTime();
    $future_date->modify('+' . $k . ' day');
    $labels[] = $future_date->format('Y-m-d');
}

// Align data for Chart.js
$historical_data_aligned = array_pad($historical_data, count($historical_data) + 30, null);
$forecast_data_aligned = array_fill(0, count($historical_data), null);
$forecast_data_aligned = array_merge($forecast_data_aligned, $forecast_data);

// 6. SEND JSON RESPONSE
echo json_encode([
    'labels' => $labels,
    'historical' => $historical_data_aligned,
    'forecast' => $forecast_data_aligned
]);

$stmt->close();
$conn->close();
?>

