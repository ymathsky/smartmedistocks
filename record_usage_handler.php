<?php
// Filename: record_usage_handler.php
ob_start(); // Buffer output so any stray echo from included scripts never breaks the redirect

session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $quantity_used = filter_input(INPUT_POST, 'quantity_used', FILTER_VALIDATE_INT);
    $transaction_date = $_POST['transaction_date'];

    // Validate date format (Y-m-d)
    $d = DateTime::createFromFormat('Y-m-d', $transaction_date);
    if (!$d || $d->format('Y-m-d') !== $transaction_date) {
        $_SESSION['error'] = "Invalid date format. Please use YYYY-MM-DD.";
        header("Location: record_usage.php");
        exit();
    }

    if (!$item_id || !$quantity_used || $quantity_used <= 0) {
        $_SESSION['error'] = "Invalid input. Please select an item and enter a valid quantity.";
        header("Location: record_usage.php");
        exit();
    }

    // --- Fetch item name for logging details ---
    $item_name_stmt = $conn->prepare("SELECT name FROM items WHERE item_id = ?");
    $item_name_stmt->bind_param("i", $item_id);
    $item_name_stmt->execute();
    $item_name_result = $item_name_stmt->get_result();
    $item_name = ($item_name_result->num_rows > 0) ? $item_name_result->fetch_assoc()['name'] : 'Unknown Item';
    $item_name_stmt->close();


    // --- Database Interaction within a Transaction ---
    $conn->begin_transaction();

    try {
        // 1. Deduct from oldest batches first (FEFO)
        $remaining_quantity = $quantity_used;
        $batches_stmt = $conn->prepare("SELECT batch_id, quantity FROM item_batches WHERE item_id = ? AND quantity > 0 ORDER BY expiry_date ASC, received_date ASC");
        $batches_stmt->bind_param("i", $item_id);
        $batches_stmt->execute();
        $batches_result = $batches_stmt->get_result();

        if ($batches_result->num_rows == 0) {
            throw new Exception("No stock available for the selected item.");
        }

        // Check total stock before proceeding
        $total_stock = 0;
        $batches_for_deduction = [];
        while($batch = $batches_result->fetch_assoc()) {
            $total_stock += $batch['quantity'];
            $batches_for_deduction[] = $batch;
        }

        if ($quantity_used > $total_stock) {
            throw new Exception("Not enough stock available to fulfill the request. Only " . $total_stock . " units were available.");
        }


        foreach ($batches_for_deduction as $batch) {
            if ($remaining_quantity <= 0) break;

            $deduct_amount = min($remaining_quantity, $batch['quantity']);

            $update_batch_stmt = $conn->prepare("UPDATE item_batches SET quantity = quantity - ? WHERE batch_id = ?");
            $update_batch_stmt->bind_param("ii", $deduct_amount, $batch['batch_id']);
            $update_batch_stmt->execute();
            $update_batch_stmt->close();

            $remaining_quantity -= $deduct_amount;
        }
        $batches_stmt->close();

        if ($remaining_quantity > 0) {
            // This case should be caught by the initial stock check, but it's a good safeguard.
            throw new Exception("Stock deduction error. Could not deduct the full quantity requested.");
        }

        // 2. Insert the new transaction record
        $stmt1 = $conn->prepare("INSERT INTO transactions (item_id, quantity_used, transaction_date) VALUES (?, ?, ?)");
        $stmt1->bind_param("iis", $item_id, $quantity_used, $transaction_date);
        $stmt1->execute();
        $stmt1->close();

        // 3. LOG THE DECISION
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];
        $action_type = "Recorded Item Usage";
        $details = "Recorded usage of $quantity_used unit(s) for item '$item_name' (ID: $item_id).";
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        // If everything succeeds, commit the transaction
        $conn->commit();
        $_SESSION['message'] = "Transaction recorded and logged successfully. Stock levels updated.";

        // --- AUTO-TRIGGER ALERT CHECK ---
        // The included script should not close the connection.
        @include 'check_alerts_cron.php';

    } catch (Exception $e) {
        // If any query fails, roll back the changes
        $conn->rollback();
        $_SESSION['error'] = "Error recording transaction: " . $e->getMessage();
    }

    // --- Final cleanup of the main connection ---
    // This is the single point where the connection is closed for this script.
    if ($conn) {
        $conn->close();
    }

    // FINAL REDIRECT
    ob_end_clean(); // Discard any stray output before redirecting
    header("Location: record_usage.php");
    exit();

} else {
    ob_end_clean();
    header("Location: record_usage.php");
    exit();
}
