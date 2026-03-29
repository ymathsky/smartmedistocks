<?php
// Filename: add_supplier.php
require_once 'header.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}
?>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Add New Supplier</h1>

        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <form action="add_supplier_handler.php" method="POST">
            <!-- CSRF Token (NEW) -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-4">
                <label for="supplier_name" class="block text-gray-700 text-sm font-bold mb-2">Supplier Name:</label>
                <input type="text" id="supplier_name" name="supplier_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="contact_info" class="block text-gray-700 text-sm font-bold mb-2">Contact Info (Phone/Other):</label>
                <input type="text" id="contact_info" name="contact_info" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="supplier_email" class="block text-gray-700 text-sm font-bold mb-2">Supplier Email:</label>
                <input type="email" id="supplier_email" name="supplier_email" placeholder="orders@supplier.com" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p class="text-gray-600 text-xs italic mt-1">Used to send Purchase Orders directly to this supplier.</p>
            </div>
            <!-- NEW: Supplier Address Field -->
            <div class="mb-4">
                <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Supplier Address:</label>
                <textarea id="address" name="address" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
            </div>
            <div class="mb-6">
                <label for="lead_time" class="block text-gray-700 text-sm font-bold mb-2">Average Lead Time (in days):</label>
                <input type="number" id="lead_time" name="lead_time" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <p class="text-gray-600 text-xs italic mt-2">The average number of days it takes for an order from this supplier to arrive.</p>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Supplier
                </button>
                <a href="supplier_management.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
