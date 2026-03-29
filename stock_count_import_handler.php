<?php
// Filename: stock_count_import_handler.php
// Applies bulk stock adjustments from a physical count import.
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: stock_count_import.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security error: Invalid request token.";
    header("Location: stock_count_import.php");
    exit();
}

$variances = $_SESSION['sc_variances'] ?? [];
if (empty($variances)) {
    $_SESSION['error'] = "No variance data found. Please upload a count CSV first.";
    header("Location: stock_count_import.php");
    exit();
}

$reason = trim($_POST['reason'] ?? '');
if (empty($reason)) {
    $_SESSION['error'] = "A reason for the adjustment is required.";
    header("Location: stock_count_import.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn->begin_transaction();
$applied = 0;
$errors  = [];

try {
    foreach ($variances as $v) {
        $item_id  = (int)$v['item_id'];
        $type     = $v['type'];
        $abs_qty  = abs((int)$v['variance']);
        $item_name = htmlspecialchars($v['name']);

        if ($type === 'Increase') {
            // Insert a new batch with the positive variance quantity
            $stmt = $conn->prepare("INSERT INTO item_batches (item_id, quantity, received_date, purchase_order_id, expiry_date) VALUES (?, ?, CURDATE(), 'SC-ADJ', NULL)");
            $stmt->bind_param("ii", $item_id, $abs_qty);
            $stmt->execute();
            $stmt->close();
        } elseif ($type === 'Decrease') {
            // FEFO deduction across batches
            $remaining = $abs_qty;
            $batches_stmt = $conn->prepare("SELECT batch_id, quantity FROM item_batches WHERE item_id = ? AND quantity > 0 ORDER BY ISNULL(expiry_date) ASC, expiry_date ASC, batch_id ASC");
            $batches_stmt->bind_param("i", $item_id);
            $batches_stmt->execute();
            $batches = $batches_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $batches_stmt->close();

            foreach ($batches as $batch) {
                if ($remaining <= 0) break;
                $deduct = min($remaining, (int)$batch['quantity']);
                $new_qty = (int)$batch['quantity'] - $deduct;
                $upd = $conn->prepare("UPDATE item_batches SET quantity = ? WHERE batch_id = ?");
                $upd->bind_param("ii", $new_qty, $batch['batch_id']);
                $upd->execute();
                $upd->close();
                $remaining -= $deduct;
            }
            // If remaining > 0 the stock was insufficient; log it but don't throw
            if ($remaining > 0) {
                $errors[] = "Warning: Insufficient stock for '{$item_name}'; adjustment reduced by {$remaining} unit(s).";
            }
        }

        // Log to decision_log
        $log_details = "Stock count adjustment ({$type}): {$abs_qty} unit(s) for '{$item_name}' (ID:{$item_id}). Reason: {$reason}";
        $log_stmt = $conn->prepare("INSERT INTO decision_log (action_type, details, user_id, username) VALUES ('Stock Count', ?, ?, ?)");
        $log_stmt->bind_param("sis", $log_details, $user_id, $username);
        $log_stmt->execute();
        $log_stmt->close();

        $applied++;
    }

    $conn->commit();
    unset($_SESSION['sc_variances']);

    $msg = "Successfully applied {$applied} stock adjustment(s) from physical count.";
    if (!empty($errors)) {
        $msg .= " Note: " . implode('; ', $errors);
    }
    $_SESSION['message'] = $msg;
    header("Location: stock_count_import.php");

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "An error occurred while applying adjustments: " . $e->getMessage();
    header("Location: stock_count_import.php");
}

$conn->close();
exit();
