<?php
// --- Database Configuration ---
// Replace these values with your actual database credentials.
$servername = "localhost"; // Or your database server IP
$username = "root";        // Your database username
$password = "";            // Your database password
$dbname = "smart_medi_stocks"; // The name of your database

// --- Create Connection ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Check Connection ---
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error message.
    die("Connection failed: " . $conn->connect_error);
}
// If the connection is successful, this script can be included in other PHP files
// that need to interact with the database.
?>
