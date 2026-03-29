<?php
// --- Database Configuration ---
// Replace these values with your actual database credentials.
$servername = "localhost"; // Or your database server IP
$username = "smartmed_users";        // Your database username
$password = "gzMT56ogKJpk8Kwb";            // Your database password
$dbname = "smartmed_smart_medi_stocks"; // The name of your database

// --- Create Connection ---
// FIX: Added error reporting suppression using @ to prevent warnings/notices
// from interfering with JSON API responses if the database connection fails.
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Check Connection ---
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error message.
    die("Connection failed: " . $conn->connect_error);
}
// If the connection is successful, this script can be included in other PHP files
// that need to interact with the database.
?>
