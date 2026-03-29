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
    header("Location: user_management.php");
    exit();
}

$userId = $_GET['id'];
$user = null;

// Fetch the user's current data from the database
$stmt = $conn->prepare("SELECT user_id, username, role, fullname, contact_number, address FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: user_management.php");
    exit();
}
$stmt->close();

// Define available roles
$roles = ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'];
?>

<div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto mt-10">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Edit User</h2>

    <form action="edit_user_handler.php" method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div>
                <label for="fullname" class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="contact_number" class="block text-gray-700 text-sm font-bold mb-2">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role</label>
                <select id="role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo ($user['role'] === $role) ? 'selected' : ''; ?>>
                            <?php echo $role; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Address</label>
            <textarea id="address" name="address" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($user['address']); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-4">
            <a href="user_management.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
require_once 'footer.php';
?>
