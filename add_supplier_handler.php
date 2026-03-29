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
    // --- CSRF Protection Check (NEW) ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']); // Clear the token for security
        header("Location: index.php");
        exit();
    }
    // --- End CSRF Check ---

    $name = trim($_POST['supplier_name']);
    $contact = trim($_POST['contact_info']);
    $email = trim($_POST['supplier_email'] ?? '');
    $address = trim($_POST['address']); // NEW
    $lead_time = filter_input(INPUT_POST, 'lead_time', FILTER_VALIDATE_INT);

    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address provided.";
        header("Location: add_supplier.php");
        exit();
    }

    if (empty($name) || empty($address) || $lead_time === false || $lead_time < 1) {
        $_SESSION['error'] = "Invalid input. Please provide a valid name, address, and lead time.";
        header("Location: add_supplier.php");
        exit();
    }

    // UPDATED SQL: Insert into the new 'address' and 'email' columns
    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_info, email, address, average_lead_time_days) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $contact, $email, $address, $lead_time);

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
