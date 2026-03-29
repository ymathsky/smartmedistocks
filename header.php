<?php
// This file should be included at the top of every protected page.

// Start the session if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF Token Generation (NEW) ---
if (empty($_SESSION['csrf_token'])) {
    // Generate a new 32-byte token and convert to hexadecimal string
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS for management tables (NEW) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Style for notification badge */
        #notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 20px;
            height: 20px;
            font-size: 10px;
            line-height: 20px;
            border-radius: 50%;
            background-color: red;
            color: white;
            text-align: center;
            padding: 0 4px;
        }
        /* Style adjustments for DataTables */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-bottom: 1rem; /* Add some space around controls */
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.25rem 0.75rem;
            margin: 0 0.1rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #3b82f6; /* Tailwind blue-600 */
            color: white;
            border-color: #3b82f6;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ddd;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            margin-left: 0.5rem;
        }
        table.dataTable thead th {
            cursor: pointer;
        }


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
                <div class="flex items-center space-x-6">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notification-bubble" class="text-blue-200 hover:text-white focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span id="notification-badge" class="hidden"></span> <!-- Badge hidden by default -->
                        </button>
                        <!-- Notification Dropdown -->
                        <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl overflow-hidden z-20 hidden">
                            <div class="py-2 px-4 bg-gray-100 border-b">
                                <h3 class="font-semibold text-gray-700">Notifications</h3>
                            </div>
                            <ul id="notification-list" class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                                <!-- Notifications will be loaded here by JavaScript -->
                                <li class="p-4 text-center text-sm text-gray-500">Loading alerts...</li>
                            </ul>
                            <div class="px-4 py-2 border-t text-center">
                                <a href="#" id="mark-all-read" class="text-sm text-blue-600 hover:underline">Mark all as read</a>
                            </div>
                        </div>
                    </div>
                    <!-- User Info & Logout -->
                    <span class="text-blue-100">Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?>!</strong></span>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-5 rounded-full focus:outline-none focus:shadow-outline transition duration-300 transform hover:scale-105">
                        Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Start -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
            <div class="container mx-auto px-6 py-8">
