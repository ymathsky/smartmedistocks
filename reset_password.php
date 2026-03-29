<?php
// Filename: reset_password.php
session_start();
require_once 'db_connection.php';

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: index.php");
    exit;
}

$token = trim($_GET['token'] ?? '');
$error = '';
$valid = false;
$username = '';

if (empty($token) || strlen($token) !== 128 || !ctype_xdigit($token)) {
    $error = "Invalid or missing reset token.";
} else {
    // Validate token
    $stmt = $conn->prepare("SELECT t.token, t.user_id, t.expires_at, t.used, u.username
        FROM password_reset_tokens t JOIN users u ON t.user_id = u.user_id
        WHERE t.token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $error = "This reset link is invalid or has already been used.";
    } elseif ($row['used']) {
        $error = "This reset link has already been used. Please request a new one.";
    } elseif (strtotime($row['expires_at']) < time()) {
        $error = "This reset link has expired. Please request a new one.";
    } else {
        $valid    = true;
        $username = $row['username'];
    }
}

$conn->close();

if (isset($_SESSION['rp_error'])) { $error = $_SESSION['rp_error']; unset($_SESSION['rp_error']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Medi Stocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-md">
    <div class="text-center mb-8">
        <img src="logo.png" alt="Smart Medi Stocks" class="mx-auto h-16 w-16 mb-3">
        <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
    <p class="text-center text-sm"><a href="forgot_password.php" class="text-blue-600 hover:underline">Request a new reset link</a></p>
    <?php elseif ($valid): ?>
    <p class="text-sm text-gray-600 mb-5">Setting a new password for <strong><?php echo htmlspecialchars($username); ?></strong>.</p>
    <form action="reset_password_handler.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php
            if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
            echo htmlspecialchars($_SESSION['csrf_token']);
        ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-4">
            <label class="block text-sm font-bold text-gray-700 mb-2">New Password:</label>
            <input type="password" name="password" required minlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-400"
                placeholder="At least 8 characters">
        </div>
        <div class="mb-5">
            <label class="block text-sm font-bold text-gray-700 mb-2">Confirm New Password:</label>
            <input type="password" name="password_confirm" required minlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-400"
                placeholder="Repeat your new password">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none transition">
            Set New Password
        </button>
    </form>
    <?php endif; ?>

    <p class="mt-5 text-center text-sm text-gray-500">
        <a href="login.php" class="text-blue-600 hover:underline">&larr; Back to Login</a>
    </p>
</div>
</body>
</html>
