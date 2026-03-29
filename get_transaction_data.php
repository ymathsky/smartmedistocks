<?php
// Filename: get_transaction_data.php
// Purpose: Fetch transaction data for DataTables AJAX request with date filtering.

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized access.', 'data' => []]);
    exit();
}

$range = $_GET['range'] ?? 'all'; // Default to 'all' if no range is specified
$startDate = '';
$endDate = date('Y-m-d'); // Today

// Determine date range based on the 'range' parameter
switch ($range) {
    case 'today':
        $startDate = date('Y-m-d');
        break;
    case 'week':
        // Assuming week starts on Monday
        $startDate = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        break;
    case '3months':
        $startDate = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'all':
    default:
        $startDate = null; // No start date filter needed for 'all'
        break;
}

// Base SQL Query
$sql = "SELECT t.transaction_id, t.quantity_used, t.transaction_date,
               COALESCE(t.transaction_type, 'Usage') AS transaction_type,
               t.notes, i.name as item_name, i.item_code
        FROM transactions t
        JOIN items i ON t.item_id = i.item_id";

// Add WHERE clause if a date range is specified
$params = [];
$types = '';
if ($startDate !== null) {
    $sql .= " WHERE t.transaction_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types = 'ss'; // Two string parameters (dates)
}

$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);

// Bind parameters if needed
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Format data for DataTables
        $type = htmlspecialchars($row['transaction_type']);
        $data[] = [
            'transaction_id'   => $row['transaction_id'],
            'date'             => htmlspecialchars(date('M j, Y', strtotime($row['transaction_date']))),
            'item_code'        => htmlspecialchars($row['item_code']),
            'item_name'        => htmlspecialchars($row['item_name']),
            'quantity_used'    => htmlspecialchars($row['quantity_used']),
            'transaction_type' => $type,
            'notes'            => htmlspecialchars($row['notes'] ?? ''),
            'actions'          => ''
        ];
    }
} else {
    echo json_encode(['error' => 'Database query failed: ' . $conn->error, 'data' => []]);
    $conn->close();
    exit();
}

$stmt->close();
$conn->close();

// Return data in DataTables expected JSON format
echo json_encode(['data' => $data]);
?>
