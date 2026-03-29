<?php
// Filename: edit_user_handler.php

session_start();
require_once 'db_connection.php';

// Security check: Ensure an admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: user_management.php");
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']);
        header("Location: index.php");
        exit();
    }

    // Basic validation
    if (isset($_POST['user_id'], $_POST['username'], $_POST['role'], $_POST['fullname']) && is_numeric($_POST['user_id'])) {

        $userId = $_POST['user_id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $fullname = trim($_POST['fullname']);
        $contact_number = trim($_POST['contact_number']);
        $address = trim($_POST['address']);


        // Further validation
        if (empty($username) || empty($fullname)) {
            $_SESSION['error'] = "Username and Full Name cannot be empty.";
            header("Location: edit_user.php?id=" . $userId);
            exit();
        }

        // Check if another user already has the new username
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Username already taken by another user.";
            header("Location: edit_user.php?id=" . $userId);
            exit();
        }
        $stmt->close();

        // Prepare and execute the UPDATE statement
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, fullname = ?, contact_number = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $username, $role, $fullname, $contact_number, $address, $userId);

        if ($stmt->execute()) {
            $_SESSION['message'] = "User details updated successfully.";
            header("Location: user_management.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating user: " . $stmt->error;
            header("Location: edit_user.php?id=" . $userId);
            exit();
        }
        $stmt->close();

    } else {
        $_SESSION['error'] = "Invalid data submitted.";
        header("Location: user_management.php");
        exit();
    }
} else {
    // Redirect if accessed directly without POST method
    header("Location: user_management.php");
    exit();
}
