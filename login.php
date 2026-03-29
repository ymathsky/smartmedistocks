<?php
session_start();

// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Check for login errors from the login handler
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear the error after displaying it
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Medi Stocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensure body takes full viewport height */
        }
        .main-content {
            flex-grow: 1; /* Allow content to grow and push footer down */
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex items-center justify-center min-h-screen main-content">
    <div class="flex w-full max-w-4xl bg-white rounded-lg shadow-2xl overflow-hidden my-auto"> <!-- Added my-auto for vertical centering if needed -->
        <!-- Image Section -->
        <div class="hidden md:block md:w-1/2">
            <img src="image.png" alt="Medical Background" class="object-cover h-full w-full">
        </div>

        <!-- Form Section -->
        <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center">
            <div class="text-center mb-8">
                <img src="logo.png" alt="Smart Medi Stocks Logo" class="mx-auto h-30 w-36 mb-4" height="50" width="50">
                <h1 class="text-2xl font-bold text-gray-800">Welcome Back</h1>
                <p class="text-sm text-gray-500">Please sign in to access your account.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login_handler.php" method="post" class="space-y-6">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <input type="text" name="username" id="username" required
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm transition duration-150 ease-in-out">
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm transition duration-150 ease-in-out">
                </div>

                <div class="pt-4">
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300 ease-in-out">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer Enhanced -->
<footer class="text-center text-sm text-gray-600 py-6 mt-auto border-t border-gray-200 bg-gray-50"> <!-- Added padding, border, bg -->
    &copy; <?php echo date("Y"); ?> Smart Medi Stocks. All rights reserved. |
    <a href="terms.php" class="text-blue-600 hover:underline">Terms of Use</a> |
    <a href="privacy.php" class="text-blue-600 hover:underline">Privacy Policy</a>
</footer>

</body>
</html>

