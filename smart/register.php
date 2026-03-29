<?php
session_start();

// Check for registration errors
$error = '';
if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart Medi Stocks</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

<div class="w-full max-w-md bg-white p-8 rounded-lg shadow-lg">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Create Account</h1>
        <p class="text-sm text-gray-600">Join SMART MEDI STOCKS</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <form action="register_handler.php" method="post">
        <div class="mb-4">
            <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
            <input type="text" name="username" id="username" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
            <input type="password" name="password" id="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-6">
            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
            <select name="role" id="role" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="Pharmacist">Pharmacist</option>
                <option value="Procurement">Procurement</option>
                <option value="Warehouse">Warehouse</option>
                <option value="Admin">Admin</option>
            </select>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Register
            </button>
            <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Already have an account?
            </a>
        </div>
    </form>
</div>

</body>
</html>
