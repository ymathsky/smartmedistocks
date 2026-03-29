<?php
// Filename: smart/stock_adjustment_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CSRF Protection Check (NEW) ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']); // Clear the token for security
        header("Location: index.php");
        exit();
    }
    // --- End CSRF Check ---

    // 1. Validate and sanitize inputs
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity_adjusted = filter_input(INPUT_POST, 'quantity_adjusted', FILTER_VALIDATE_INT);
    $adjustment_type = trim($_POST['adjustment_type']);
    $reason = trim($_POST['reason']);
    $username = $_SESSION['username'];
    $user_id = $_SESSION['user_id'];

    if (!$item_id || $quantity_adjusted === false || $quantity_adjusted < 1 || empty($reason)) {
        $_SESSION['error'] = "Invalid input. Please fill all fields with valid data.";
        header("Location: stock_adjustment.php");
        exit();
    }

    // --- Fetch item name for logging details ---
    $item_name_stmt = $conn->prepare("SELECT name FROM items WHERE item_id = ?");
    $item_name_stmt->bind_param("i", $item_id);
    $item_name_stmt->execute();
    $item_name_result = $item_name_stmt->get_result();
    $item_name = ($item_name_result->num_rows > 0) ? $item_name_result->fetch_assoc()['name'] : 'Unknown Item';
    $item_name_stmt->close();

    // 2. Database Interaction within a Transaction
    $conn->begin_transaction();
    $log_details = "";

    try {
        if ($adjustment_type === 'Increase') {
            // Simplification: Insert a new batch with the positive adjustment.
            $log_details = "Increased stock by $quantity_adjusted unit(s) for item '$item_name' (ID: $item_id). Reason: $reason";

            $stmt = $conn->prepare("INSERT INTO item_batches (item_id, quantity, received_date, purchase_order_id, expiry_date) VALUES (?, ?, CURDATE(), 'ADJ-IN', NULL)");
            $stmt->bind_param("ii", $item_id, $quantity_adjusted);
            $stmt->execute();
            $stmt->close();

        } elseif ($adjustment_type === 'Decrease') {
            // --- FEFO-COMPLIANT DEDUCTION LOGIC (UPDATED) ---

            $log_details = "Decreased stock by $quantity_adjusted unit(s) for item '$item_name' (ID: $item_id). Reason: $reason";
            $remaining_quantity = $quantity_adjusted;

            // 1. Check current total stock first
            $stock_check = $conn->query("SELECT SUM(quantity) as total_stock FROM item_batches WHERE item_id = $item_id")->fetch_assoc();
            $total_stock = $stock_check['total_stock'] ?? 0;

            if ($quantity_adjusted > $total_stock) {
                throw new Exception("Cannot decrease stock. The requested adjustment of $quantity_adjusted exceeds the current total stock of $total_stock units.");
            }

            // 2. Fetch batches, ordered by Expiry Date (FEFO)
            $batches_stmt = $conn->prepare("SELECT batch_id, quantity FROM item_batches WHERE item_id = ? AND quantity > 0 ORDER BY expiry_date ASC, received_date ASC");
            $batches_stmt->bind_param("i", $item_id);
            $batches_stmt->execute();
            $batches_result = $batches_stmt->get_result();
            $batches_stmt->close();

            $batches_to_update = [];

            while ($batch = $batches_result->fetch_assoc()) {
                if ($remaining_quantity <= 0) break;

                $deduct_amount = min($remaining_quantity, $batch['quantity']);

                // Store the batch update details
                $batches_to_update[] = [
                    'id' => $batch['batch_id'],
                    'deduct' => $deduct_amount
                ];

                $remaining_quantity -= $deduct_amount;
            }

            // 3. Perform the batch updates outside the fetch loop
            foreach ($batches_to_update as $batch_update) {
                $stmt_update = $conn->prepare("UPDATE item_batches SET quantity = quantity - ? WHERE batch_id = ?");
                $stmt_update->bind_param("ii", $batch_update['deduct'], $batch_update['id']);
                $stmt_update->execute();
                $stmt_update->close();
            }

            // --- END UPDATED LOGIC ---

        } else {
            throw new Exception("Invalid adjustment type.");
        }

        // 3. LOG THE DECISION
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $action_type = "Stock Adjustment: " . $adjustment_type;
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $log_details);
        $log_stmt->execute();
        $log_stmt->close();

        // If everything succeeds, commit the transaction
        $conn->commit();
        $_SESSION['message'] = "Stock adjustment successful. Inventory updated: " . $log_details;

    } catch (Exception $e) {
        // If any query fails, roll back the changes
        $conn->rollback();
        $_SESSION['error'] = "Error performing adjustment: " . $e->getMessage();
    }

    $conn->close();
    header("Location: stock_adjustment.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}
?>
