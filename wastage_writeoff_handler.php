<?php
// Filename: wastage_writeoff_handler.php
ob_start();

session_start();
require_once 'db_connection.php';

// Only Warehouse or Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    ob_end_clean();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header("Location: wastage_writeoff.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security error: Invalid request token.";
    ob_end_clean();
    header("Location: wastage_writeoff.php");
    exit();
}

$batch_ids = $_POST['batch_ids'] ?? [];
$reason    = trim($_POST['reason'] ?? '');

if (empty($batch_ids)) {
    $_SESSION['error'] = "Please select at least one batch to write off.";
    ob_end_clean();
    header("Location: wastage_writeoff.php");
    exit();
}

if (empty($reason)) {
    $_SESSION['error'] = "A reason for the write-off is required.";
    ob_end_clean();
    header("Location: wastage_writeoff.php");
    exit();
}

// Sanitize batch IDs — must all be positive integers
$batch_ids = array_filter(array_map('intval', $batch_ids), fn($id) => $id > 0);
if (empty($batch_ids)) {
    $_SESSION['error'] = "Invalid batch selection.";
    ob_end_clean();
    header("Location: wastage_writeoff.php");
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$today    = date('Y-m-d');

$conn->begin_transaction();

try {
    $total_quantity  = 0;
    $total_value     = 0.0;
    $items_written   = [];

    // Build placeholders for IN clause — safe because $batch_ids are all integers
    $placeholders = implode(',', array_fill(0, count($batch_ids), '?'));
    $types        = str_repeat('i', count($batch_ids));

    // Fetch the batches we are writing off (lock rows)
    $fetch_stmt = $conn->prepare(
        "SELECT b.batch_id, b.item_id, b.quantity, b.expiry_date,
                i.name AS item_name, i.item_code, COALESCE(i.unit_cost, 0) AS unit_cost
         FROM item_batches b
         JOIN items i ON b.item_id = i.item_id
         WHERE b.batch_id IN ($placeholders) AND b.quantity > 0
         FOR UPDATE"
    );
    $fetch_stmt->bind_param($types, ...$batch_ids);
    $fetch_stmt->execute();
    $batches = $fetch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fetch_stmt->close();

    if (empty($batches)) {
        throw new Exception("None of the selected batches have stock available to write off.");
    }

    // Process each batch
    $zero_stmt = $conn->prepare("UPDATE item_batches SET quantity = 0 WHERE batch_id = ?");
    $txn_stmt  = $conn->prepare(
        "INSERT INTO transactions (item_id, quantity_used, transaction_date, transaction_type, notes)
         VALUES (?, ?, ?, 'Wastage Write-off', ?)"
    );

    foreach ($batches as $batch) {
        $bid      = (int)$batch['batch_id'];
        $item_id  = (int)$batch['item_id'];
        $qty      = (int)$batch['quantity'];
        $val      = $qty * (float)$batch['unit_cost'];
        $note_txt = "Wastage write-off — Batch #{$bid}, Expiry: {$batch['expiry_date']}. Reason: {$reason}";

        // Zero the batch
        $zero_stmt->bind_param("i", $bid);
        $zero_stmt->execute();

        // Record in transactions
        $txn_stmt->bind_param("iisss", $item_id, $qty, $today, $note_txt);
        $txn_stmt->execute();

        $total_quantity += $qty;
        $total_value    += $val;
        $items_written[] = "{$batch['item_name']} ({$batch['item_code']}) — {$qty} unit(s), batch #{$bid}";
    }

    $zero_stmt->close();
    $txn_stmt->close();

    // Decision log entry
    $log_details = sprintf(
        "Wastage Write-off: %d batch(es), %d total unit(s), estimated financial impact ₱%s. Items: %s. Reason: %s",
        count($batches),
        $total_quantity,
        number_format($total_value, 2),
        implode('; ', $items_written),
        $reason
    );
    $log_stmt = $conn->prepare(
        "INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, 'Wastage Write-off', ?)"
    );
    $log_stmt->bind_param("iss", $user_id, $username, $log_details);
    $log_stmt->execute();
    $log_stmt->close();

    $conn->commit();

    $_SESSION['message'] = sprintf(
        "Write-off completed successfully.\n%d batch(es) written off — %d unit(s) — estimated value ₱%s.",
        count($batches),
        $total_quantity,
        number_format($total_value, 2)
    );

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Write-off failed: " . htmlspecialchars($e->getMessage());
}

$conn->close();
ob_end_clean();
header("Location: wastage_writeoff.php");
exit();
