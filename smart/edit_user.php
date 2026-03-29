<?php
// Filename: edit_user.php
require_once 'header.php'; // Includes session_start() and security checks
require_once 'db_connection.php'; // Make the database connection available

// Security check: Only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Check if user ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid user ID specified.";
    header("Location: index.php");
    exit();
}

$userId = $_GET['id'];
$user = null;

// Fetch the user's current data from the database
$stmt = $conn->prepare("SELECT user_id, username, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}
$stmt->close();

// Define available roles
$roles = ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'];
?>

<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mx-auto mt-10">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Edit User</h2>

    <form action="edit_user_handler.php" method="POST">
        <!-- Hidden input for the user ID -->
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

        <div class="mb-4">
            <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>

        <div class="mb-6">
            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role</label>
            <select id="role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role; ?>" <?php echo ($user['role'] === $role) ? 'selected' : ''; ?>>
                        <?php echo $role; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Save Changes
            </button>
            <a href="index.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php
require_once 'footer.php';
?>

