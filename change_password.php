<?php
// Filename: smart/change_password.php
require_once 'header.php';
require_once 'db_connection.php';

// Security: User must be logged in.
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Change Password</h1>
        <p class="mb-6 text-gray-600">Update your password for the account: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>

        <!-- User Feedback -->
        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['message']) . '</p></div>';
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['error']) . '</p></div>';
            unset($_SESSION['error']);
        }
        ?>

        <form action="change_password_handler.php" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password:</label>
                <input type="password" id="current_password" name="current_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                <input type="password" id="new_password" name="new_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Password
                </button>
                <a href="index.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
