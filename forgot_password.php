<?php
// Filename: forgot_password.php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
if (isset($_SESSION['fp_message'])) { $message = $_SESSION['fp_message']; unset($_SESSION['fp_message']); }
if (isset($_SESSION['fp_error']))   { $error   = $_SESSION['fp_error'];   unset($_SESSION['fp_error']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Smart Medi Stocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white rounded-2xl shadow-xl p-10 w-full max-w-md">
    <div class="text-center mb-8">
        <img src="logo.png" alt="Smart Medi Stocks" class="mx-auto h-16 w-16 mb-3">
        <h1 class="text-2xl font-bold text-gray-800">Forgot Password</h1>
        <p class="text-sm text-gray-500 mt-1">Enter your registered email address and we'll send you a reset link.</p>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="forgot_password_handler.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php
            if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
            echo htmlspecialchars($_SESSION['csrf_token']);
        ?>">
        <div class="mb-5">
            <label for="email" class="block text-sm font-bold text-gray-700 mb-2">Email Address:</label>
            <input type="email" id="email" name="email" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-400"
                placeholder="you@example.com">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none transition">
            Send Reset Link
        </button>
    </form>
    <p class="mt-5 text-center text-sm text-gray-500">
        <a href="login.php" class="text-blue-600 hover:underline">&larr; Back to Login</a>
    </p>
</div>
</body>
</html>
