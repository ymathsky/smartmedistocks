<?php
// Filename: reset_password_handler.php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['rp_error'] = "Security error: Invalid request token.";
    $token = htmlspecialchars($_POST['token'] ?? '');
    header("Location: reset_password.php?token=" . $token);
    exit();
}

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

// Validate token format
if (empty($token) || strlen($token) !== 128 || !ctype_xdigit($token)) {
    header("Location: forgot_password.php");
    exit();
}

if (strlen($password) < 8) {
    $_SESSION['rp_error'] = "Password must be at least 8 characters.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
}

if ($password !== $confirm) {
    $_SESSION['rp_error'] = "Passwords do not match.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
}

// Fetch and validate token
$stmt = $conn->prepare("SELECT user_id, expires_at, used FROM password_reset_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['used'] || strtotime($row['expires_at']) < time()) {
    $_SESSION['fp_error'] = "This reset link is invalid, expired, or has already been used.";
    header("Location: forgot_password.php");
    exit();
}

$user_id = (int)$row['user_id'];
$hashed  = password_hash($password, PASSWORD_DEFAULT);

$conn->begin_transaction();
try {
    // Update password
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $upd->bind_param("si", $hashed, $user_id);
    $upd->execute();
    $upd->close();

    // Mark token as used
    $mark = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $mark->bind_param("s", $token);
    $mark->execute();
    $mark->close();

    $conn->commit();
    $conn->close();

    $_SESSION['login_message'] = "Your password has been reset successfully. Please log in with your new password.";
    header("Location: login.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    $_SESSION['rp_error'] = "An error occurred. Please try again.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
}
