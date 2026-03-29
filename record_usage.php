<?php
// Filename: record_usage.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Pharmacists can record usage
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all items to populate the dropdown
$items_result = $conn->query("SELECT item_id, name, item_code FROM items ORDER BY name ASC");
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Record Item Usage (Transaction)</h1>
        <a href="transaction_history.php" class="text-blue-600 hover:underline">View Transaction History &rarr;</a>
    </div>

    <!-- User Feedback -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['message']) . '</p></div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['error']) . '</p></div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="bg-white p-8 rounded-lg shadow-lg max-w-2xl mx-auto">
        <form action="record_usage_handler.php" method="POST">
            <div class="mb-6">
                <label for="item_id" class="block text-gray-700 font-bold mb-2">Select Item</label>
                <!-- Search box -->
                <input type="text" id="item_search" placeholder="Search by name or item code..." 
                    class="shadow border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mb-2"
                    autocomplete="off">
                <select id="item_id" name="item_id" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required size="6">
                    <option value="">-- Search above to filter items --</option>
                    <?php
                    if ($items_result && $items_result->num_rows > 0) {
                        while ($item = $items_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($item['item_id']) . '" data-label="' . strtolower(htmlspecialchars($item['name'])) . ' ' . strtolower(htmlspecialchars($item['item_code'])) . '">' . htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['item_code']) . ')</option>';
                        }
                    }
                    ?>
                </select>
                <p class="text-xs text-gray-400 mt-1" id="item_match_count"></p>
            </div>

            <div class="mb-6">
                <label for="quantity_used" class="block text-gray-700 font-bold mb-2">Quantity Used</label>
                <input type="number" id="quantity_used" name="quantity_used" min="1" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-8">
                <label for="transaction_date" class="block text-gray-700 font-bold mb-2">Date of Usage</label>
                <input type="date" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300 ease-in-out">
                    Record Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var searchInput = document.getElementById('item_search');
    var select      = document.getElementById('item_id');
    var countLabel  = document.getElementById('item_match_count');
    var allOptions  = Array.prototype.slice.call(select.querySelectorAll('option'));

    // Remove the placeholder once user starts typing
    var placeholder = select.querySelector('option[value=""]');

    searchInput.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        var visible = 0;

        allOptions.forEach(function(opt) {
            if (!opt.value) { // placeholder
                opt.style.display = query ? 'none' : '';
                return;
            }
            var label = opt.getAttribute('data-label') || '';
            var match = !query || label.indexOf(query) !== -1;
            opt.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        countLabel.textContent = query ? visible + ' item(s) found' : '';

        // Auto-select if only one match
        if (visible === 1) {
            allOptions.forEach(function(opt) {
                if (opt.value && opt.style.display !== 'none') {
                    select.value = opt.value;
                }
            });
        }
    });

    // Collapse size back to 1 once the user selects something
    select.addEventListener('change', function() {
        if (this.value) {
            var chosen = select.options[select.selectedIndex];
            searchInput.value = chosen.text;
        }
    });
})();
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
