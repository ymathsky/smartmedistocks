<?php
// Filename: add_supplier_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['supplier_name']);
    $contact = trim($_POST['contact_info']);
    $lead_time = filter_input(INPUT_POST, 'lead_time', FILTER_VALIDATE_INT);

    if (empty($name) || $lead_time === false || $lead_time < 1) {
        $_SESSION['error'] = "Invalid input. Please provide a valid name and lead time.";
        header("Location: add_supplier.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_info, average_lead_time_days) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $contact, $lead_time);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Supplier '" . htmlspecialchars($name) . "' added successfully.";
        header("Location: supplier_management.php");
    } else {
        if ($conn->errno == 1062) { // Duplicate entry
            $_SESSION['error'] = "Error: A supplier with this name already exists.";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        header("Location: add_supplier.php");
    }
    $stmt->close();
    $conn->close();
} else {
    header("Location: add_supplier.php");
    exit();
}
?>
