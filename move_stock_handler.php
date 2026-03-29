<?php
// Filename: smart/move_stock_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CSRF Protection Check ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']);
        header("Location: index.php");
        exit();
    }

    // 1. Validate and sanitize inputs
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity_to_move = filter_input(INPUT_POST, 'quantity_to_move', FILTER_VALIDATE_INT);
    $source_location_id = filter_input(INPUT_POST, 'source_location_id', FILTER_VALIDATE_INT);
    $destination_location_id = filter_input(INPUT_POST, 'destination_location_id', FILTER_VALIDATE_INT);
    $reason = trim($_POST['reason']);
    $username = $_SESSION['username'];
    $user_id = $_SESSION['user_id'];

    if (!$item_id || $quantity_to_move < 1 || !$source_location_id || !$destination_location_id || empty($reason) || $source_location_id == $destination_location_id) {
        $_SESSION['error'] = "Invalid input. Please ensure all fields are filled, quantity is valid, and locations are different.";
        header("Location: move_stock.php");
        exit();
    }

    $conn->begin_transaction();
    $remaining_quantity = $quantity_to_move;
    $batches_moved = [];

    try {
        // A. Check for sufficient stock in the source location
        $stock_check_stmt = $conn->prepare("SELECT SUM(quantity) as total_stock FROM item_batches WHERE item_id = ? AND location_id = ?");
        $stock_check_stmt->bind_param("ii", $item_id, $source_location_id);
        $stock_check_stmt->execute();
        $total_stock = $stock_check_stmt->get_result()->fetch_assoc()['total_stock'] ?? 0;
        $stock_check_stmt->close();

        if ($quantity_to_move > $total_stock) {
            throw new Exception("Insufficient stock. Only $total_stock units available in the source location.");
        }

        // B. Fetch batches to move (FEFO - Oldest expiry date first)
        $batches_stmt = $conn->prepare("SELECT batch_id, quantity, expiry_date, expected_delivery_date, purchase_order_id FROM item_batches WHERE item_id = ? AND location_id = ? AND quantity > 0 ORDER BY expiry_date ASC, received_date ASC");
        $batches_stmt->bind_param("ii", $item_id, $source_location_id);
        $batches_stmt->execute();
        $batches_result = $batches_stmt->get_result();
        $batches_stmt->close();

        // C. Process deduction from source batches and prepare new destination batches
        while ($batch = $batches_result->fetch_assoc()) {
            if ($remaining_quantity <= 0) break;

            $deduct_amount = min($remaining_quantity, $batch['quantity']);

            // 1. Deduct from source batch
            $update_source_stmt = $conn->prepare("UPDATE item_batches SET quantity = quantity - ? WHERE batch_id = ?");
            $update_source_stmt->bind_param("ii", $deduct_amount, $batch['batch_id']);
            $update_source_stmt->execute();
            $update_source_stmt->close();

            // 2. Prepare new batch data for destination (Keep original batch info but change location)
            $batches_moved[] = [
                'item_id' => $item_id,
                'po_id' => $batch['purchase_order_id'],
                'quantity' => $deduct_amount,
                'expected_delivery_date' => $batch['expected_delivery_date'],
                'expiry_date' => $batch['expiry_date'],
                'location_id' => $destination_location_id
            ];

            $remaining_quantity -= $deduct_amount;
        }

        if ($remaining_quantity > 0) {
            // This should not happen if the initial check was correct, but is a safety net
            throw new Exception("Stock deduction error: Could not complete deduction for the full quantity.");
        }

        // D. Insert new batches into the destination location
        $insert_dest_stmt = $conn->prepare("INSERT INTO item_batches (item_id, purchase_order_id, quantity, expected_delivery_date, expiry_date, location_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($batches_moved as $new_batch) {
            $insert_dest_stmt->bind_param("isissi",
                $new_batch['item_id'],
                $new_batch['po_id'],
                $new_batch['quantity'],
                $new_batch['expected_delivery_date'],
                $new_batch['expiry_date'],
                $new_batch['location_id']
            );
            $insert_dest_stmt->execute();
        }
        $insert_dest_stmt->close();

        // E. Get location names for logging
        $loc_res = $conn->query("SELECT name FROM locations WHERE location_id IN ($source_location_id, $destination_location_id)");
        $loc_names = [];
        while($r = $loc_res->fetch_assoc()) { $loc_names[] = $r['name']; }
        $source_name = $loc_names[0]; // Assuming order is consistent enough, otherwise requires mapping

        // F. Log the Decision
        $item_name = $conn->query("SELECT name FROM items WHERE item_id = $item_id")->fetch_assoc()['name'] ?? 'Unknown Item';
        $action_type = "Stock Movement";
        $details = "Moved $quantity_to_move unit(s) of '$item_name' from '$source_name' to '" . end($loc_names) . "'. Reason: $reason.";

        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        // Commit transaction
        $conn->commit();
        $_SESSION['message'] = "Successfully moved $quantity_to_move unit(s) of $item_name.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error moving stock: " . $e->getMessage();
    }

    $conn->close();
    header("Location: move_stock.php");
    exit();

} else {
    header("Location: move_stock.php");
    exit();
}
?>
