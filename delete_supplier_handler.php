<?php
// Filename: delete_supplier_handler.php
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

    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);

    if (!$supplier_id) {
        $_SESSION['error'] = "Invalid supplier ID.";
        header("Location: supplier_management.php");
        exit();
    }

    // Note: Deleting a supplier will set the supplier_id for associated items to NULL
    // due to the ON DELETE SET NULL constraint in the database schema.
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Supplier deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting supplier: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    header("Location: supplier_management.php");
    exit();
} else {
    header("Location: supplier_management.php");
    exit();
}
