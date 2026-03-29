<?php
// Filename: smart/edit_transaction_handler.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF and Input Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        header("Location: transaction_history.php");
        exit();
    }

    $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
    $new_quantity = filter_input(INPUT_POST, 'quantity_used', FILTER_VALIDATE_INT);
    $new_date = $_POST['transaction_date'];

    if (!$transaction_id || $new_quantity < 1 || empty($new_date)) {
        $_SESSION['error'] = "Invalid input provided.";
        header("Location: edit_transaction.php?id=$transaction_id");
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1. Get original transaction data
        $stmt = $conn->prepare("SELECT item_id, quantity_used FROM transactions WHERE transaction_id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $original_transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$original_transaction) {
            throw new Exception("Original transaction not found.");
        }
        $item_id = $original_transaction['item_id'];
        $old_quantity = $original_transaction['quantity_used'];

        // 2. Calculate the difference
        $quantity_diff = $new_quantity - $old_quantity;

        // 3. Adjust stock levels based on the difference
        if ($quantity_diff > 0) { // More items used than originally recorded, deduct more
            $remaining_to_deduct = $quantity_diff;
            $batches_stmt = $conn->prepare("SELECT batch_id, quantity FROM item_batches WHERE item_id = ? AND quantity > 0 AND status = 'Active' ORDER BY expiry_date ASC, received_date ASC");
            $batches_stmt->bind_param("i", $item_id);
            $batches_stmt->execute();
            $batches_result = $batches_stmt->get_result();
            while ($batch = $batches_result->fetch_assoc()) {
                if ($remaining_to_deduct <= 0) break;
                $deduct = min($remaining_to_deduct, $batch['quantity']);

                $update_stmt = $conn->prepare("UPDATE item_batches SET quantity = quantity - ? WHERE batch_id = ?");
                $update_stmt->bind_param("ii", $deduct, $batch['batch_id']);
                $update_stmt->execute();
                $update_stmt->close();
                $remaining_to_deduct -= $deduct;
            }
            if ($remaining_to_deduct > 0) {
                throw new Exception("Not enough stock to cover the edited quantity.");
            }
        } elseif ($quantity_diff < 0) { // Fewer items used, add stock back
            $quantity_to_add = abs($quantity_diff);
            $stmt_add = $conn->prepare("INSERT INTO item_batches (item_id, quantity, received_date, purchase_order_id) VALUES (?, ?, CURDATE(), 'ADJ-EDIT')");
            $stmt_add->bind_param("ii", $item_id, $quantity_to_add);
            $stmt_add->execute();
            $stmt_add->close();
        }

        // 4. Update the transaction record itself
        $stmt_update_trans = $conn->prepare("UPDATE transactions SET quantity_used = ?, transaction_date = ? WHERE transaction_id = ?");
        $stmt_update_trans->bind_param("isi", $new_quantity, $new_date, $transaction_id);
        $stmt_update_trans->execute();
        $stmt_update_trans->close();

        // 5. Log the action
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $action_type = "Edited Transaction";
        $details = "Edited transaction #$transaction_id. Changed quantity from $old_quantity to $new_quantity. Stock adjusted by " . ($quantity_diff * -1) . " units.";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();
        $_SESSION['message'] = "Transaction #$transaction_id updated successfully. Stock levels have been adjusted.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating transaction: " . $e->getMessage();
    }

    header("Location: transaction_history.php");
    exit();
}
