<?php
session_start();
require_once 'db_connection.php';

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, password, role FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if username exists, if yes then verify password
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $role);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_regenerate_id();

                            $_SESSION["loggedin"] = true;
                            // --- THIS IS THE FIX ---
                            // Changed from $_SESSION["id"] to $_SESSION["user_id"]
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;

                            // Redirect user based on role
                            switch ($role) {
                                case 'Admin':
                                    header("location: admin_dashboard.php");
                                    break;
                                case 'Procurement':
                                    header("location: procurement_dashboard.php");
                                    break;
                                case 'Warehouse':
                                    header("location: warehouse_dashboard.php");
                                    break;
                                case 'Pharmacist':
                                    header("location: pharmacist_dashboard.php");
                                    break;
                                default:
                                    header("location: admin_dashboard.php"); // Fallback redirect
                                    break;
                            }
                            exit(); // It's important to exit after a redirect
                        } else {
                            // Display an error message if password is not valid
                            $_SESSION["login_err"] = "Invalid username or password.";
                            header("location: login.php");
                            exit();
                        }
                    }
                } else {
                    // Display an error message if username doesn't exist
                    $_SESSION["login_err"] = "Invalid username or password.";
                    header("location: login.php");
                    exit();
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>
