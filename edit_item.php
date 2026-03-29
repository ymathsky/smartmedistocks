<?php
// Filename: edit_item.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Get Item ID from URL and validate
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$item_id) {
    $_SESSION['error'] = "Invalid item ID.";
    header("Location: item_management.php");
    exit();
}

// Fetch the item's current data
$stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Item not found.";
    header("Location: item_management.php");
    exit();
}
$item = $result->fetch_assoc();
$stmt->close();

// Fetch suppliers for the dropdown
$suppliers_result = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name ASC");
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Edit Medical Item</h1>
        <a href="item_management.php" class="text-blue-600 hover:underline">&larr; Back to Item List</a>
    </div>

    <div class="bg-white p-8 rounded-lg shadow-lg max-w-4xl mx-auto">
        <form action="edit_item_handler.php" method="POST">
            <!-- CSRF Token (NEW) -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id']); ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div>
                    <div class="mb-4">
                        <label for="item_name" class="block text-gray-700 font-bold mb-2">Item Name</label>
                        <input type="text" id="item_name" name="item_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="item_code" class="block text-gray-700 font-bold mb-2">Item Code</label>
                        <input type="text" id="item_code" name="item_code" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 font-mono" value="<?php echo htmlspecialchars($item['item_code']); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="category" class="block text-gray-700 font-bold mb-2">Category</label>
                        <select id="category" name="category" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                            <?php $categories = ['Hospital Supplies', 'Medicines', 'Laboratory', 'Office Supplies', 'Equipment', 'Reagents']; ?>
                            <?php foreach ($categories as $cat): ?>
                                <option <?php echo ($item['category'] == $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Right Column -->
                <div>
                    <div class="mb-4">
                        <label for="brand_name" class="block text-gray-700 font-bold mb-2">Brand Name</label>
                        <input type="text" id="brand_name" name="brand_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" value="<?php echo htmlspecialchars($item['brand_name']); ?>">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="unit_cost" class="block text-gray-700 font-bold mb-2">Unit Cost (₱)</label>
                            <input type="number" step="0.01" id="unit_cost" name="unit_cost" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" value="<?php echo htmlspecialchars($item['unit_cost']); ?>" required>
                        </div>
                        <div>
                            <label for="unit_of_measure" class="block text-gray-700 font-bold mb-2">Unit of Measure</label>
                            <select id="unit_of_measure" name="unit_of_measure" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                                <?php $uoms = ['Pieces', 'Box', 'Pack', 'Bottle', 'Kit']; ?>
                                <?php foreach ($uoms as $uom): ?>
                                    <option <?php echo ($item['unit_of_measure'] == $uom) ? 'selected' : ''; ?>><?php echo $uom; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="shelf_life" class="block text-gray-700 font-bold mb-2">Shelf Life (Days)</label>
                        <input type="number" id="shelf_life" name="shelf_life" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" value="<?php echo htmlspecialchars($item['shelf_life_days']); ?>">
                    </div>
                    <div class="mb-6">
                        <label for="supplier_id" class="block text-gray-700 font-bold mb-2">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                            <option value="">None</option>
                            <?php
                            if ($suppliers_result && $suppliers_result->num_rows > 0) {
                                while ($supplier = $suppliers_result->fetch_assoc()) {
                                    $selected = ($item['supplier_id'] == $supplier['supplier_id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($supplier['supplier_id']) . '" ' . $selected . '>' . htmlspecialchars($supplier['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <!-- Form Actions -->
            <div class="flex items-center justify-end mt-6">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
                    Update Item
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
