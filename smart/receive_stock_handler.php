<?php
// Filename: receive_stock_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity_received = filter_input(INPUT_POST, 'quantity_received', FILTER_VALIDATE_INT);
    $purchase_order_id = !empty($_POST['purchase_order_id']) ? trim($_POST['purchase_order_id']) : null;
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if (!$item_id || $quantity_received === false || $quantity_received < 1) {
        $_SESSION['error'] = "Invalid input. Please select an item and enter a valid quantity.";
        header("Location: receive_stock.php");
        exit();
    }

    // Insert a new batch into the item_batches table
    $stmt = $conn->prepare("INSERT INTO item_batches (item_id, purchase_order_id, quantity, expected_delivery_date, expiry_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $item_id, $purchase_order_id, $quantity_received, $expected_delivery_date, $expiry_date);

    if ($stmt->execute()) {
        $_SESSION['message'] = "New stock batch has been added successfully.";
    } else {
        $_SESSION['error'] = "Error adding new stock batch: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    header("Location: receive_stock.php");
    exit();

} else {
    header("Location: receive_stock.php");
    exit();
}
?>
