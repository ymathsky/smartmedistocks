<?php
// Filename: forgot_password_handler.php
session_start();
require_once 'db_connection.php';
require_once 'send_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: forgot_password.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['fp_error'] = "Security error: Invalid request token.";
    header("Location: forgot_password.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['fp_error'] = "Please enter a valid email address.";
    header("Location: forgot_password.php");
    exit();
}

// Look up user by email — always show the same generic message to prevent email enumeration
$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    // Delete any existing unused tokens for this user
    $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0");
    $del->bind_param("i", $user['user_id']);
    $del->execute();
    $del->close();

    // Generate a secure 64-byte (128 hex char) token
    $token   = bin2hex(random_bytes(64));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $ins = $conn->prepare("INSERT INTO password_reset_tokens (token, user_id, expires_at, used) VALUES (?, ?, ?, 0)");
    $ins->bind_param("sis", $token, $user['user_id'], $expires);
    $ins->execute();
    $ins->close();

    // Build the reset URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $reset_url = "{$protocol}://{$host}{$dir}/reset_password.php?token={$token}";

    $subject = "Password Reset Request - Smart Medi Stocks";
    $body    = "
    <div style='font-family:Inter,Arial,sans-serif;color:#333;max-width:480px;margin:0 auto;padding:24px;'>
        <h2 style='color:#1d4ed8;'>Password Reset Request</h2>
        <p>Hi <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
        <p>We received a request to reset your password. Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
        <div style='text-align:center;margin:28px 0;'>
            <a href='" . htmlspecialchars($reset_url) . "'
               style='background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px;'>
                Reset My Password
            </a>
        </div>
        <p style='font-size:12px;color:#9ca3af;'>If you didn't request this, you can safely ignore this email. Your password won't change.</p>
        <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
        <p style='font-size:12px;color:#9ca3af;'>Or copy and paste this URL into your browser:<br>" . htmlspecialchars($reset_url) . "</p>
    </div>";

    send_alert_email($email, $subject, $body);
}

$conn->close();
// Always show the same message to prevent email enumeration
$_SESSION['fp_message'] = "If that email is registered, a reset link has been sent. Please check your inbox (and spam folder).";
header("Location: forgot_password.php");
exit();
