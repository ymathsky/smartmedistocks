<?php
// Filename: smart/stock_adjustment.php
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

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Physical Stock Adjustment</h1>
        <p class="mb-6 text-gray-600">Use this form to correct inventory levels due to physical counts, loss, or spoilage. All adjustments are logged.</p>

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

        <form action="stock_adjustment_handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                <label for="adjustment_type" class="block text-gray-700 text-sm font-bold mb-2">Adjustment Type:</label>
                <select id="adjustment_type" name="adjustment_type" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="Increase">Increase Stock (Found during count)</option>
                    <option value="Decrease">Decrease Stock (Lost, spoiled, or miscounted)</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="quantity_adjusted" class="block text-gray-700 text-sm font-bold mb-2">Quantity to Adjust:</label>
                <input type="number" id="quantity_adjusted" name="quantity_adjusted" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-6">
                <label for="reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for Adjustment:</label>
                <textarea id="reason" name="reason" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required placeholder="e.g., Physical count discrepancy, damage/spoilage, system error correction."></textarea>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Perform Adjustment
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
