<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // --- Validation ---
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['register_error'] = "Please fill out all fields.";
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

    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sss", $username, $hashed_password, $role);

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
