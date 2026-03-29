<?php
// Filename: batch_quarantine_handler.php
ob_start();

session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    ob_end_clean();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header("Location: batch_quarantine.php");
    exit();
}

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security error: Invalid request token.";
    ob_end_clean();
    header("Location: batch_quarantine.php");
    exit();
}

$batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$action   = trim($_POST['action'] ?? '');
$allowed  = ['quarantine', 'release', 'writeoff'];

if (!$batch_id || !in_array($action, $allowed)) {
    $_SESSION['error'] = "Invalid request.";
    ob_end_clean();
    header("Location: batch_quarantine.php");
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch the batch row first
$fetch = $conn->prepare("SELECT b.batch_id, b.item_id, b.quantity, b.status, b.expiry_date, i.name AS item_name, i.item_code FROM item_batches b JOIN items i ON b.item_id = i.item_id WHERE b.batch_id = ?");
$fetch->bind_param("i", $batch_id);
$fetch->execute();
$batch = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$batch) {
    $_SESSION['error'] = "Batch not found.";
    ob_end_clean();
    header("Location: batch_quarantine.php");
    exit();
}

$conn->begin_transaction();
try {
    switch ($action) {
        case 'quarantine':
            if ($batch['status'] !== 'Active') throw new Exception("Only Active batches can be quarantined.");
            $conn->prepare("UPDATE item_batches SET status = 'Quarantined' WHERE batch_id = ?")->execute([$batch_id]) ||
            ($s = $conn->prepare("UPDATE item_batches SET status = 'Quarantined' WHERE batch_id = ?")) && $s->bind_param("i", $batch_id) && $s->execute();
            $new_status = 'Quarantined';
            $log = "Quarantined batch #{$batch_id} — {$batch['item_name']} ({$batch['item_code']}), {$batch['quantity']} unit(s). Pending inspection.";
            $_SESSION['message'] = "Batch #{$batch_id} quarantined. It is now excluded from stock counts.";
            break;

        case 'release':
            if ($batch['status'] !== 'Quarantined') throw new Exception("Only Quarantined batches can be released.");
            $new_status = 'Active';
            $log = "Released batch #{$batch_id} from quarantine — {$batch['item_name']} ({$batch['item_code']}), {$batch['quantity']} unit(s). Returned to active stock.";
            $_SESSION['message'] = "Batch #{$batch_id} released. Stock is back in active inventory.";
            break;

        case 'writeoff':
            if (!in_array($batch['status'], ['Active', 'Quarantined'])) throw new Exception("Batch cannot be written off.");
            $new_status = 'Written-Off';
            $log = "Wrote off batch #{$batch_id} — {$batch['item_name']} ({$batch['item_code']}), {$batch['quantity']} unit(s). Stock zeroed.";
            $_SESSION['message'] = "Batch #{$batch_id} written off and zeroed.";
            break;

        default:
            throw new Exception("Unknown action.");
    }

    // Update batch status
    $upd = $conn->prepare("UPDATE item_batches SET status = ? WHERE batch_id = ?");
    $upd->bind_param("si", $new_status, $batch_id);
    $upd->execute();
    $upd->close();

    // If write-off, also zero the quantity and record a transaction
    if ($action === 'writeoff' && $batch['quantity'] > 0) {
        $zero = $conn->prepare("UPDATE item_batches SET quantity = 0 WHERE batch_id = ?");
        $zero->bind_param("i", $batch_id);
        $zero->execute();
        $zero->close();

        $today    = date('Y-m-d');
        $note_txt = "Batch quarantine write-off — Batch #{$batch_id}, Expiry: {$batch['expiry_date']}.";
        $txn = $conn->prepare("INSERT INTO transactions (item_id, quantity_used, transaction_date, transaction_type, notes) VALUES (?, ?, ?, 'Wastage Write-off', ?)");
        $txn->bind_param("iiss", $batch['item_id'], $batch['quantity'], $today, $note_txt);
        $txn->execute();
        $txn->close();
    }

    // Decision log
    $dl = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, 'Batch Quarantine', ?)");
    $dl->bind_param("iss", $user_id, $username, $log);
    $dl->execute();
    $dl->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Action failed: " . htmlspecialchars($e->getMessage());
}

$conn->close();
ob_end_clean();
$redirect_status = $action === 'release' ? 'Quarantined' : ($action === 'quarantine' ? 'Active' : 'Quarantined');
header("Location: batch_quarantine.php?status=" . urlencode($redirect_status));
exit();
