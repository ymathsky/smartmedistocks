<?php
// Filename: record_usage_handler.php
ob_start(); // Buffer output so any stray echo from included scripts never breaks the redirect

session_start();
require_once 'db_connection.php';
require_once 'notifications_helper.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
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
    // --- End CSRF Check ---

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

    // --- Check if this is a Controlled Substance ---
    $ctrl_stmt = $conn->prepare("SELECT is_controlled FROM items WHERE item_id = ?");
    $ctrl_stmt->bind_param("i", $item_id);
    $ctrl_stmt->execute();
    $ctrl_row = $ctrl_stmt->get_result()->fetch_assoc();
    $ctrl_stmt->close();
    $is_controlled = $ctrl_row ? (int)$ctrl_row['is_controlled'] : 0;

    $authorizer_user_id = null;

    if ($is_controlled) {
        $auth_username = trim($_POST['auth_username'] ?? '');
        $auth_password = $_POST['auth_password'] ?? '';
        if (empty($auth_username) || empty($auth_password)) {
            $_SESSION['error'] = "This is a controlled substance. A second authorizer's credentials are required.";
            header("Location: record_usage.php");
            exit();
        }
        // Authorizer must be a valid, different user from the current session
        $current_user_id = (int)$_SESSION['user_id'];
        $auth_stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ? AND user_id != ?");
        $auth_stmt->bind_param("si", $auth_username, $current_user_id);
        $auth_stmt->execute();
        $auth_row = $auth_stmt->get_result()->fetch_assoc();
        $auth_stmt->close();
        if (!$auth_row || !password_verify($auth_password, $auth_row['password'])) {
            $_SESSION['error'] = "Controlled substance authorization failed. The authorizer credentials provided are invalid.";
            header("Location: record_usage.php");
            exit();
        }
        $authorizer_user_id = (int)$auth_row['user_id'];
    }

    // --- Database Interaction within a Transaction ---
    $conn->begin_transaction();

    try {
        // 1. Deduct from oldest batches first (FEFO)
        $remaining_quantity = $quantity_used;
        $batches_stmt = $conn->prepare("SELECT batch_id, quantity FROM item_batches WHERE item_id = ? AND quantity > 0 AND status = 'Active' ORDER BY expiry_date ASC, received_date ASC");
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
        $stmt1 = $conn->prepare("INSERT INTO transactions (item_id, quantity_used, transaction_date, authorizer_user_id) VALUES (?, ?, ?, ?)");
        $stmt1->bind_param("iisi", $item_id, $quantity_used, $transaction_date, $authorizer_user_id);
        $stmt1->execute();
        $stmt1->close();

        // 3. LOG THE DECISION
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];
        $action_type = "Recorded Item Usage";
        $details = $is_controlled
            ? "Recorded usage of $quantity_used unit(s) for CONTROLLED SUBSTANCE '$item_name' (ID: $item_id). Dual-authorized by user ID: $authorizer_user_id."
            : "Recorded usage of $quantity_used unit(s) for item '$item_name' (ID: $item_id).";
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        // If everything succeeds, commit the transaction
        $conn->commit();
        $_SESSION['message'] = "Transaction recorded and logged successfully. Stock levels updated.";

        // --- LIGHTWEIGHT ROP NOTIFICATION CHECK (single item only) ---
        $stock_check = $conn->prepare(
            "SELECT i.name, i.item_code, s.average_lead_time_days,
                    COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND status = 'Active'), 0) as current_stock,
                    COALESCE((SELECT SUM(quantity_used) / 90 FROM transactions WHERE item_id = i.item_id AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)), 0) as avg_daily
             FROM items i LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
             WHERE i.item_id = ?"
        );
        $stock_check->bind_param("i", $item_id);
        $stock_check->execute();
        $item_data = $stock_check->get_result()->fetch_assoc();
        $stock_check->close();

        if ($item_data && $item_data['avg_daily'] > 0) {
            $lead = $item_data['average_lead_time_days'] ?? 7;
            $rop  = round($item_data['avg_daily'] * $lead + 1.65 * sqrt($item_data['avg_daily']) * sqrt($lead));
            if ((int)$item_data['current_stock'] <= $rop) {
                $msg = "Stock Alert: '{$item_data['item_name']}' ({$item_data['item_code']}) stock is now {$item_data['current_stock']} unit(s) — at or below Reorder Point ({$rop} units). Consider placing an order.";
                notify_by_role($conn, $msg, ['Admin', 'Procurement']);
            }
        }

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
