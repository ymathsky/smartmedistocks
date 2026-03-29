<?php
// Filename: get_usage_trend_data.php
// Returns daily usage aggregation for the usage trend chart (AJAX endpoint).
header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
if (!$days || $days < 1 || $days > 365) {
    $days = 14;
}

$sql = "
    SELECT DATE(transaction_date) AS date, SUM(quantity_used) AS total_usage
    FROM transactions
    WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(transaction_date)
    ORDER BY date ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $days);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Build a full date range so every day appears (even zeros)
$labels = [];
$values_map = [];
$d = new DateTime();
$d->modify("-{$days} days");
for ($i = 0; $i < $days; $i++) {
    $labels[]                    = $d->format('M j');
    $values_map[$d->format('Y-m-d')] = 0;
    $d->modify('+1 day');
}

while ($row = $result->fetch_assoc()) {
    if (isset($values_map[$row['date']])) {
        $values_map[$row['date']] = (int)$row['total_usage'];
    }
}

$conn->close();
echo json_encode(['labels' => $labels, 'values' => array_values($values_map)]);
