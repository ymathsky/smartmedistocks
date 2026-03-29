<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $fullname = trim($_POST['fullname']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email'] ?? '');

    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Please enter a valid email address.";
        header("location: register.php");
        exit;
    }

    // --- Validation ---
    if (empty($username) || empty($password) || empty($role) || empty($fullname)) {
        $_SESSION['register_error'] = "Please fill out all required fields.";
        header("location: register.php");
        exit;
    }
    // Check if username is already taken
    $sql = "SELECT user_id FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['register_error'] = "This username is already taken.";
            header("location: register.php");
            exit;
        }
        $stmt->close();
    }
    // --- End Validation ---


    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password, role, fullname, contact_number, address) VALUES (?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $role, $fullname, $contact_number, $address);

        if ($stmt->execute()) {
            // Redirect to login page after successful registration
            header("location: login.php");
        } else {
            $_SESSION['register_error'] = "Something went wrong. Please try again.";
            header("location: register.php");
        }
        $stmt->close();
    }
    $conn->close();
}
?>
