<?php
// Filename: edit_supplier_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['supplier_name']);
    $contact = trim($_POST['contact_info']);
    $lead_time = filter_input(INPUT_POST, 'lead_time', FILTER_VALIDATE_INT);

    if (!$supplier_id || empty($name) || $lead_time === false || $lead_time < 1) {
        $_SESSION['error'] = "Invalid input provided.";
        header("Location: edit_supplier.php?id=" . $supplier_id);
        exit();
    }

    $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_info = ?, average_lead_time_days = ? WHERE supplier_id = ?");
    $stmt->bind_param("ssii", $name, $contact, $lead_time, $supplier_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Supplier '" . htmlspecialchars($name) . "' updated successfully.";
        header("Location: supplier_management.php");
    } else {
        if ($conn->errno == 1062) {
            $_SESSION['error'] = "Error: A supplier with this name already exists.";
        } else {
            $_SESSION['error'] = "Error updating supplier: " . $stmt->error;
        }
        header("Location: edit_supplier.php?id=" . $supplier_id);
    }
    $stmt->close();
    $conn->close();
} else {
    header("Location: supplier_management.php");
    exit();
}
?>
