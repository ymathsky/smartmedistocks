<?php
// Filename: receive_stock.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure Warehouse staff or Admin is logged in.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all items to populate the dropdown
$items_sql = "SELECT item_id, name, item_code FROM items ORDER BY name ASC";
$items_result = $conn->query($items_sql);
?>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Receive Incoming Stock</h1>
        <p class="mb-6 text-gray-600">Use this form to record the arrival of new inventory. This will create a new batch for the selected item.</p>

        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <form action="receive_stock_handler.php" method="POST">
            <div class="mb-4">
                <label for="purchase_order_id" class="block text-gray-700 text-sm font-bold mb-2">Purchase Order ID (Optional):</label>
                <input type="text" id="purchase_order_id" name="purchase_order_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="item_id" class="block text-gray-700 text-sm font-bold mb-2">Select Item:</label>
                <select id="item_id" name="item_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Choose an item --</option>
                    <?php while($item = $items_result->fetch_assoc()): ?>
                        <option value="<?php echo $item['item_id']; ?>">
                            <?php echo htmlspecialchars($item['name']) . " (" . htmlspecialchars($item['item_code']) . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="quantity_received" class="block text-gray-700 text-sm font-bold mb-2">Quantity Received:</label>
                <input type="number" id="quantity_received" name="quantity_received" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="expected_delivery_date" class="block text-gray-700 text-sm font-bold mb-2">Expected Delivery Date (Optional):</label>
                <input type="date" id="expected_delivery_date" name="expected_delivery_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="expiry_date" class="block text-gray-700 text-sm font-bold mb-2">Expiry Date (Optional):</label>
                <input type="date" id="expiry_date" name="expiry_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p class="text-gray-600 text-xs italic mt-2">Leave blank for items without an expiry date.</p>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Stock Batch
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
