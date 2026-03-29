<?php
// Filename: smart/create_purchase_order_handler.php
session_start();
require_once 'db_connection.php';

// Security check: Only Admins and Procurement can create POs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']);
        header("Location: po_management.php"); // Updated fallback redirect
        exit();
    }

    // 2. Sanitize and Validate Inputs
    $po_number = trim($_POST['po_number']);
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $unit_cost_final = filter_input(INPUT_POST, 'unit_cost_final', FILTER_VALIDATE_FLOAT);
    $expected_delivery_date = trim($_POST['expected_delivery_date']);
    $reference = trim($_POST['reference']) ?: null;
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Basic validation
    if (empty($po_number) || !$item_id || !$supplier_id || $quantity <= 0 || $unit_cost_final === false || $unit_cost_final <= 0 || empty($expected_delivery_date)) {
        $_SESSION['error'] = "Invalid input: Please check PO details, quantity, and cost.";
        header("Location: po_management.php"); // Updated fallback redirect
        exit();
    }

    // 3. Database Transaction
    $conn->begin_transaction();
    $po_created = false;

    try {
        // A. Insert the Purchase Order
        $stmt_po = $conn->prepare("INSERT INTO purchase_orders (po_number, item_id, supplier_id, quantity_ordered, unit_cost_agreed, expected_delivery_date, external_reference, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_po->bind_param("siiidssi", $po_number, $item_id, $supplier_id, $quantity, $unit_cost_final, $expected_delivery_date, $reference, $user_id);
        $stmt_po->execute();
        $stmt_po->close();
        $po_created = true;

        // B. Get item name for logging
        $item_name_stmt = $conn->prepare("SELECT name FROM items WHERE item_id = ?");
        $item_name_stmt->bind_param("i", $item_id);
        $item_name_stmt->execute();
        $item_name = $item_name_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown Item';
        $item_name_stmt->close();

        // C. Log the Decision/Action
        $log_stmt = $conn->prepare("INSERT INTO decision_log (user_id, username, action_type, details) VALUES (?, ?, ?, ?)");
        $action_type = "Created Purchase Order";
        $details = "PO #$po_number created by $username for $quantity unit(s) of '$item_name' (ID: $item_id). Est. cost: ₱" . number_format($quantity * $unit_cost_final, 2) . ".";
        $log_stmt->bind_param("isss", $user_id, $username, $action_type, $details);
        $log_stmt->execute();
        $log_stmt->close();

        // 4. Commit and Success
        $conn->commit();
        $_SESSION['message'] = "Purchase Order **#$po_number** for $quantity units of **$item_name** has been successfully placed!";

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // Check for unique PO number constraint violation (Error code 1062)
        if ($e->getCode() == 1062) {
            $_SESSION['error'] = "Failed to create PO: Purchase Order number '$po_number' already exists. Please try again.";
        } else {
            $_SESSION['error'] = "Database error during PO creation: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error during PO creation: " . $e->getMessage();
    }

    $conn->close();
    // FINAL REDIRECT: Go to the PO Management page regardless of success/failure
    header("Location: po_management.php");
    exit();

} else {
    header("Location: po_management.php"); // Final fallback redirect
    exit();
}
?>
