<?php
// Filename: delete_user_handler.php

session_start();
require_once 'db_connection.php';

// Security check: Ensure an admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: user_management.php"); // Corrected redirect
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF Protection Check (NEW) ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']); // Clear the token for security
        header("Location: index.php");
        exit();
    }
    // --- End CSRF Check ---

    // Check if the user ID to delete is provided.
    if (isset($_POST['user_id_to_delete'])) {
        $userIdToDelete = $_POST['user_id_to_delete'];
        $currentUserId = $_SESSION['user_id'];

        // --- Prevent Admin from deleting themselves ---
        if ($userIdToDelete == $currentUserId) {
            $_SESSION['error'] = "Error: You cannot delete your own account.";
            header("Location: user_management.php"); // Corrected redirect
            exit();
        }

        // Prepare and execute the DELETE statement to prevent SQL injection.
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userIdToDelete);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User has been successfully deleted.";
        } else {
            $_SESSION['error'] = "Error: Could not delete user. " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid request. No user specified for deletion.";
    }
}

// Redirect back to the user management page.
header("Location: user_management.php"); // Corrected redirect
exit();
