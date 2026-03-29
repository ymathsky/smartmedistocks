<?php
// Filename: export_handler.php
session_start();
require_once 'db_connection.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['export_error'] = "You do not have permission to perform this action.";
    header("Location: data_hub.php");
    exit();
}

$export_type = $_GET['type'] ?? '';

// --- Data Fetching and CSV Generation ---
$filename = "export_" . $export_type . "_" . date('Y-m-d') . ".csv";
$sql = '';
$headers = [];

switch ($export_type) {
    case 'items':
        // Select only the requested columns in the specified order
        $sql = "SELECT name, item_code, description, category, brand_name, unit_of_measure, unit_cost, shelf_life_days, supplier_id FROM items ORDER BY name ASC"; // Order by name for consistency
        // Update headers to match the requested columns and order
        $headers = ['name', 'item_code', 'description', 'category', 'brand_name', 'unit_of_measure', 'unit_cost', 'shelf_life_days', 'supplier_id'];
        break;

    case 'suppliers':
        // Select only the requested columns in the specified order
        $sql = "SELECT name, contact_info, address, average_lead_time_days FROM suppliers ORDER BY name ASC"; // Order by name
        // Update headers to match the requested columns and order
        $headers = ['name', 'contact_info', 'address', 'average_lead_time_days'];
        break;

    case 'transactions':
        // FIX: Select only the requested columns (date format is already YYYY-MM-DD)
        $sql = "SELECT item_id, quantity_used, transaction_date FROM transactions ORDER BY transaction_id ASC";
        // FIX: Update headers to match the requested columns and order
        $headers = ['item_id', 'quantity_used', 'transaction_date'];
        break;

    default:
        $_SESSION['export_error'] = "Invalid export type specified.";
        header("Location: data_hub.php");
        exit();
}

// --- Execute Query and Generate CSV ---
$result = $conn->query($sql);

if (!$result) {
    // Log the actual error for debugging
    error_log("Database error fetching data for export ($export_type): " . $conn->error);
    $_SESSION['export_error'] = "Database error fetching data for export. Please check server logs.";
    header("Location: data_hub.php");
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, $headers);

// Write data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Ensure the order matches the headers
        $ordered_row = [];
        foreach ($headers as $header) {
            // Use null coalescing operator to handle potential missing keys gracefully
            $ordered_row[] = $row[$header] ?? '';
        }
        fputcsv($output, $ordered_row);
    }
} else {
    // Optional: Write a row indicating no data if the table is empty
    // fputcsv($output, ['No data found for this export type.']);
}

fclose($output);
$conn->close();
exit(); // Important to stop script execution after generating the file

?>

