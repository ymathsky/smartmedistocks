<?php
// Filename: smart/change_password_handler.php
session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. CSRF Protection Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']);
        header("Location: change_password.php");
        exit();
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 2. Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['error'] = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = "New password and confirmation password do not match.";
    } elseif (strlen($newPassword) < 6) { // Basic length check
        $_SESSION['error'] = "New password must be at least 6 characters long.";
    } else {
        // 3. Verify Current Password
        $stmt_select = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt_select->bind_param("i", $userId);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($currentPassword, $row['password'])) {
                // 4. Update Password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt_update->bind_param("si", $hashedPassword, $userId);

                if ($stmt_update->execute()) {
                    $_SESSION['message'] = "Password updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating password. Please try again.";
                }
                $stmt_update->close();
            } else {
                $_SESSION['error'] = "The current password you entered is incorrect.";
            }
        } else {
            $_SESSION['error'] = "User account not found.";
        }
        $stmt_select->close();
    }

    $conn->close();
    header("Location: change_password.php");
    exit();
} else {
    header("Location: change_password.php");
    exit();
}
?>
