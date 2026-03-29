<?php
// Filename: delete_item_handler.php

session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist')) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: item_management.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

    if (!$item_id) {
        $_SESSION['error'] = "Invalid item ID provided.";
        header("Location: item_management.php");
        exit();
    }

    // --- Database Interaction ---
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("i", $item_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Item has been deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting item: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
    $conn->close();

    header("Location: item_management.php");
    exit();
} else {
    header("Location: item_management.php");
    exit();
}
?>
