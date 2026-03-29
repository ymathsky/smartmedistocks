<?php
// Filename: smart/move_stock.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Ensure Warehouse staff or Admin is logged in.
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all active items
$items_sql = "SELECT item_id, name, item_code FROM items ORDER BY name ASC";
$items_result = $conn->query($items_sql);

// Fetch all active locations
$locations_sql = "SELECT location_id, name FROM locations WHERE is_active = 1 ORDER BY name ASC";
$locations_result = $conn->query($locations_sql);
?>

<!-- Main Content -->
<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Move Stock Between Locations</h1>
        <p class="mb-6 text-gray-600">Transfer inventory batches from a source location to a destination location. This action will update inventory records but does not affect total stock count.</p>

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

        <form action="move_stock_handler.php" method="POST">
            <!-- CSRF Token -->
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
                <label for="source_location_id" class="block text-gray-700 text-sm font-bold mb-2">Source Location (From):</label>
                <select id="source_location_id" name="source_location_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Choose source location --</option>
                    <?php
                    // Reset pointer for re-use
                    $locations_result->data_seek(0);
                    while($location = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $location['location_id']; ?>">
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="destination_location_id" class="block text-gray-700 text-sm font-bold mb-2">Destination Location (To):</label>
                <select id="destination_location_id" name="destination_location_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Choose destination location --</option>
                    <?php
                    // Reset pointer for re-use
                    $locations_result->data_seek(0);
                    while($location = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $location['location_id']; ?>">
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-xs text-red-500 mt-1" id="location_error" style="display:none;">Source and Destination locations cannot be the same.</p>
            </div>

            <div class="mb-4">
                <label for="quantity_to_move" class="block text-gray-700 text-sm font-bold mb-2">Quantity to Move:</label>
                <input type="number" id="quantity_to_move" name="quantity_to_move" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-6">
                <label for="reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for Movement:</label>
                <textarea id="reason" name="reason" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required placeholder="e.g., Transfer to primary picking area, temporary overflow, etc."></textarea>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" id="move_button" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Perform Stock Move
                </button>
                <a href="index.php" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sourceSelect = document.getElementById('source_location_id');
        const destinationSelect = document.getElementById('destination_location_id');
        const locationError = document.getElementById('location_error');
        const form = document.querySelector('form');

        // Client-side validation to ensure source and destination are different
        form.addEventListener('submit', function(e) {
            if (sourceSelect.value && destinationSelect.value && sourceSelect.value === destinationSelect.value) {
                e.preventDefault();
                locationError.style.display = 'block';
            } else {
                locationError.style.display = 'none';
            }
        });

        const checkLocations = () => {
            if (sourceSelect.value && destinationSelect.value && sourceSelect.value === destinationSelect.value) {
                locationError.style.display = 'block';
            } else {
                locationError.style.display = 'none';
            }
        };

        sourceSelect.addEventListener('change', checkLocations);
        destinationSelect.addEventListener('change', checkLocations);
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
