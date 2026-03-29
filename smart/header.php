<?php
// This file should be included at the top of every protected page.

// Start the session if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Security Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Medi Stocks</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="flex h-screen bg-gray-100">
    <?php include_once 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg text-white">
            <div class="container mx-auto px-8 py-5 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">SMART MEDI STOCKS</h1>
                    <p class="text-sm text-blue-200">Role: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION["role"]); ?></span></p>
                </div>
                <div class="flex items-center">
                    <span class="text-blue-100 mr-6">Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?>!</strong></span>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-5 rounded-full focus:outline-none focus:shadow-outline transition duration-300 transform hover:scale-105">
                        Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Start -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
            <div class="container mx-auto px-6 py-8">

