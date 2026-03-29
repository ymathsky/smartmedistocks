<?php
// Filename: smart/delete_transaction_handler.php
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
    if (!$transaction_id) {
        $_SESSION['error'] = "Invalid transaction ID.";
        header("Location: transaction_history.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1. Get original transaction data
        $stmt = $conn->prepare("SELECT item_id, quantity_used FROM transactions WHERE transaction_id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$transaction) {
            throw new Exception("Transaction not found.");
        }
        $item_id = $transaction['item_id'];
        $quantity_to_add = $transaction['quantity_used'];

        // 2. Add the stock back by creating a new adjustment batch
        $stmt_add = $conn->prepare("INSERT INTO item_batches (item_id, quantity, received_date, purchase_order_id) VALUES (?, ?, CURDATE(), 'ADJ-DEL')");
        $stmt_add->bind_param("ii", $item_id, $quantity_to_add);
        $stmt_add->execute();
        $stmt_add->close();

        // 3. Delete the transaction record
        $stmt_delete = $conn->prepare("DELETE FROM transactions WHERE transaction_id = ?");
        $stmt_delete->bind_param("i", $transaction_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        // 4. Log the action
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $action_type = "Deleted Transaction";
        $details = "Deleted transaction #$transaction_id. Credited $quantity_to_add units back to stock for item ID $item_id.";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();
        $_SESSION['message'] = "Transaction #$transaction_id deleted successfully. Stock levels have been adjusted.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting transaction: " . $e->getMessage();
    }

    header("Location: transaction_history.php");
    exit();
}
