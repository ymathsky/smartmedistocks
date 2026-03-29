<?php
// Filename: user_management.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all users from the database to display in the table.
$users = [];
$sql = "SELECT user_id, username, role, created_at FROM users";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
            <a href="register.php" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-700 inline-flex items-center">
                <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add New User
            </a>
        </div>
        <p class="text-gray-600 mb-8">This table displays all registered users in the system. You can edit or remove users.</p>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Registered</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?php
                                switch($user['role']) {
                                    case 'Admin': echo 'bg-red-100 text-red-800'; break;
                                    case 'Pharmacist': echo 'bg-green-100 text-green-800'; break;
                                    case 'Procurement': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'Warehouse': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-4">
                                <!-- Edit Link -->
                                <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>

                                <!-- Delete Form -->
                                <form action="delete_user_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id_to_delete" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No users found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
