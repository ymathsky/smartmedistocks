<?php
require_once 'header.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Check for registration errors
$error = '';
if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Add New User</h1>
            <a href="user_management.php" class="text-blue-600 hover:underline">&larr; Back to User Management</a>
        </div>


        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <form action="register_handler.php" method="post" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                    <input type="text" name="username" id="username" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="fullname" class="block text-gray-700 text-sm font-bold mb-2">Full Name:</label>
                    <input type="text" name="fullname" id="fullname" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address:</label>
                <input type="email" name="email" id="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Required for password reset">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                    <input type="password" name="password" id="password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="contact_number" class="block text-gray-700 text-sm font-bold mb-2">Contact Number:</label>
                    <input type="text" name="contact_number" id="contact_number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div>
                <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Address:</label>
                <textarea name="address" id="address" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div>
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                <select name="role" id="role" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="Pharmacist">Pharmacist</option>
                    <option value="Procurement">Procurement</option>
                    <option value="Warehouse">Warehouse</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <div class="flex items-center justify-end pt-4">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>

