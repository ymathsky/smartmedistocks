<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare a select statement
    $sql = "SELECT user_id, username, password, role FROM users WHERE username = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;

        if ($stmt->execute()) {
            $stmt->store_result();

            // Check if username exists, if so then verify password
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username, $hashed_password, $role);
                if ($stmt->fetch()) {
                    if (password_verify($password, $hashed_password)) {
                        // Password is correct, so start a new session
                        session_start();

                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["role"] = $role;

                        // Redirect user to dashboard page
                        header("location: index.php");
                    } else {
                        // Password is not valid
                        $_SESSION['login_error'] = 'Invalid username or password.';
                        header("location: login.php");
                    }
                }
            } else {
                // Username doesn't exist
                $_SESSION['login_error'] = 'Invalid username or password.';
                header("location: login.php");
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
