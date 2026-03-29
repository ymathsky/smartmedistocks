<?php
// Filename: edit_supplier.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

$supplier_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$supplier_id) {
    $_SESSION['error'] = "Invalid supplier ID.";
    header("Location: supplier_management.php");
    exit();
}

// UPDATED SQL: Select the new 'address' and 'email' columns
$stmt = $conn->prepare("SELECT name, contact_info, email, address, average_lead_time_days FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Supplier not found.";
    header("Location: supplier_management.php");
    exit();
}
$supplier = $result->fetch_assoc();
$stmt->close();
?>

<!-- Main Content -->
<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Edit Supplier</h1>

        <form action="edit_supplier_handler.php" method="POST">
            <!-- CSRF Token (NEW) -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
            <div class="mb-4">
                <label for="supplier_name" class="block text-gray-700 text-sm font-bold mb-2">Supplier Name:</label>
                <input type="text" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($supplier['name']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="contact_info" class="block text-gray-700 text-sm font-bold mb-2">Contact Info (Phone/Other):</label>
                <input type="text" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($supplier['contact_info']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="supplier_email" class="block text-gray-700 text-sm font-bold mb-2">Supplier Email:</label>
                <input type="email" id="supplier_email" name="supplier_email" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>" placeholder="orders@supplier.com" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p class="text-gray-600 text-xs italic mt-1">Used to send Purchase Orders directly to this supplier.</p>
            </div>
            <!-- NEW: Supplier Address Field -->
            <div class="mb-4">
                <label for="address" class="block text-gray-700 text-sm font-bold mb-2">Supplier Address:</label>
                <textarea id="address" name="address" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo htmlspecialchars($supplier['address']); ?></textarea>
            </div>
            <div class="mb-6">
                <label for="lead_time" class="block text-gray-700 text-sm font-bold mb-2">Average Lead Time (days):</label>
                <input type="number" id="lead_time" name="lead_time" min="1" value="<?php echo htmlspecialchars($supplier['average_lead_time_days']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Supplier
                </button>
                <a href="supplier_management.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
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
