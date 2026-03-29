<?php
// Filename: smart/po_update_status_handler.php

session_start();
header('Content-Type: application/json');
require_once 'db_connection.php';

// Security check: Only Admins and Procurement can update PO status manually
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Security Check & Input Validation
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// CSRF Protection Check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security error: Invalid request token.']);
    exit();
}

$po_id = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

// Define allowed statuses for manual update (excluding 'Received')
$allowed_statuses = ['Placed', 'Shipped', 'Cancelled', 'Draft'];

if (!$po_id || empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID or status provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn->begin_transaction();
$response = ['success' => false, 'message' => ''];

try {
    // 1. Update the status in the purchase_orders table
    $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE po_id = ?");
    $stmt->bind_param("si", $new_status, $po_id);

    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $stmt->error);
    }
    $stmt->close();

    // 2. Fetch PO number and item name for logging
    $po_details_stmt = $conn->prepare("
        SELECT po_number, i.name as item_name, po.quantity_ordered
        FROM purchase_orders po
        JOIN items i ON po.item_id = i.item_id
        WHERE po.po_id = ?
    ");
    $po_details_stmt->bind_param("i", $po_id);
    $po_details_stmt->execute();
    $po_data = $po_details_stmt->get_result()->fetch_assoc();
    $po_details_stmt->close();

    if (!$po_data) {
        throw new Exception("PO details not found for logging.");
    }

    // 3. Log the decision/action
    $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
    $action_type = "Updated PO Status";
    $details = "Status of PO #{$po_data['po_number']} for {$po_data['quantity_ordered']} units of '{$po_data['item_name']}' changed to '{$new_status}' by $username.";
    $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
    $log_stmt->execute();
    $log_stmt->close();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = "PO #{$po_data['po_number']} status updated to '{$new_status}' successfully.";

} catch (Exception $e) {
    $conn->rollback();
    error_log("PO Status Update Error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
