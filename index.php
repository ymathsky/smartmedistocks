<?php
// Filename: index.php
// This file is the main router for the application. It establishes the database
// connection first to ensure it is available to all included dashboard views.

// 1. ESTABLISH DATABASE CONNECTION
// This is the most critical step. It creates the $conn variable.
require_once 'db_connection.php';

// 2. INCLUDE THE HEADER
// The header handles session checks and the opening HTML structure.
// NOTE: The db_connection.php include has been REMOVED from header.php
require_once 'header.php';

// Check for any success or error messages passed through the session
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Clear the error after displaying it
}

// 3. ROLE-BASED DASHBOARD ROUTER
// Get the user's role from the session.
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Include the specific dashboard view based on the user's role.
// Because the connection was made in Step 1, the $conn variable is now
// automatically available to any of these included files.
switch ($userRole) {
    case 'Pharmacist':
        include 'pharmacist_dashboard.php';
        break;
    case 'Procurement':
        include 'procurement_dashboard.php';
        break;
    case 'Warehouse':
        include 'warehouse_dashboard.php';
        break;
    case 'Admin':
        include 'admin_dashboard.php';
        break;
    default:
        // Fallback for an unrecognized or missing role.
        echo '<div class="flex-1 p-6 bg-gray-100">';
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
        echo '<strong class="font-bold">Access Error!</strong>';
        echo '<span class="block sm:inline"> Your user role is not configured correctly. Please contact an administrator.</span>';
        echo '</div>';
        echo '</div>';
        break;
}

// 4. INCLUDE THE FOOTER
// The footer closes the HTML structure and can also close the database connection.
require_once 'footer.php';
?>

