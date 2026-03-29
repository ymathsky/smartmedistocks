<?php
// Filename: receive_stock_handler.php
session_start();
require_once 'db_connection.php';
require_once 'notifications_helper.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity_received = filter_input(INPUT_POST, 'quantity_received', FILTER_VALIDATE_INT);
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT); // NEW: Location ID
    $purchase_order_id = !empty($_POST['purchase_order_id']) ? trim($_POST['purchase_order_id']) : null;
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF Protection Check
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        header("Location: receive_stock.php");
        exit();
    }

    if (!$item_id || $quantity_received === false || $quantity_received < 1 || !$location_id) {
        $_SESSION['error'] = "Invalid input. Please select an item, a valid quantity, and a location.";
        header("Location: receive_stock.php");
        exit();
    }

    // --- TRANSACTION START ---
    $conn->begin_transaction();
    $username = $_SESSION['username'];
    $user_id = $_SESSION['user_id'];

    try {
        // 1. Insert a new batch into the item_batches table (UPDATED to include location_id)
        $stmt_batch = $conn->prepare("INSERT INTO item_batches (item_id, purchase_order_id, quantity, expected_delivery_date, expiry_date, location_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_batch->bind_param("isissi", $item_id, $purchase_order_id, $quantity_received, $expected_delivery_date, $expiry_date, $location_id);

        if (!$stmt_batch->execute()) {
            throw new Exception("Error adding new stock batch: " . $stmt_batch->error);
        }
        $stmt_batch->close();

        $log_details = "Received $quantity_received units for item ID $item_id at location ID $location_id. PO: " . ($purchase_order_id ?? 'N/A');

        // 2. If a PO number is provided, update its status to 'Received' and record actual delivery date
        if ($purchase_order_id) {
            $stmt_po_update = $conn->prepare("UPDATE purchase_orders SET status = 'Received', actual_delivery_date = CURDATE() WHERE po_number = ?");
            $stmt_po_update->bind_param("s", $purchase_order_id);

            if (!$stmt_po_update->execute()) {
                error_log("Failed to update PO status for PO #$purchase_order_id: " . $stmt_po_update->error);
            } else {
                $log_details .= " (PO status updated to Received)";
            }
            $stmt_po_update->close();
        }

        // 3. Get item name and location name for logging
        $item_name = $conn->query("SELECT name FROM items WHERE item_id = $item_id")->fetch_assoc()['name'] ?? 'Unknown Item';
        $location_name = $conn->query("SELECT name FROM locations WHERE location_id = $location_id")->fetch_assoc()['name'] ?? 'Unknown Location';

        // 4. Log the action (UPDATED details)
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $action_type = "Received Stock";
        $details = "Recorded receipt of $quantity_received unit(s) for item '$item_name' at location '$location_name'. PO: " . ($purchase_order_id ?? 'N/A');
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();
        $_SESSION['message'] = "New stock batch has been added successfully to **$location_name**.";

        // Real-time notification: inform Procurement and Admin
        $notif_msg = "Stock Received: {$quantity_received} unit(s) of '{$item_name}' have been received and added to '{$location_name}'.";
        if ($purchase_order_id) $notif_msg .= " (PO: {$purchase_order_id})";
        notify_by_role($conn, $notif_msg, ['Admin', 'Procurement']);

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Transaction failed: " . $e->getMessage();
    }
    // --- TRANSACTION END ---

    $conn->close();
    header("Location: receive_stock.php");
    exit();

} else {
    header("Location: receive_stock.php");
    exit();
}
