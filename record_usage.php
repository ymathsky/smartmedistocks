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

// Fetch all items with current stock to populate the dropdown
$items_result = $conn->query("
    SELECT i.item_id, i.name, i.item_code,
           COALESCE(SUM(b.quantity), 0) AS current_stock
    FROM items i
    LEFT JOIN item_batches b ON i.item_id = b.item_id
    GROUP BY i.item_id, i.name, i.item_code
    ORDER BY i.name ASC
");
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
                            $stock = (int)$item['current_stock'];
                            echo '<option value="' . htmlspecialchars($item['item_id']) . '"'
                               . ' data-label="' . strtolower(htmlspecialchars($item['name'])) . ' ' . strtolower(htmlspecialchars($item['item_code'])) . '"'
                               . ' data-stock="' . $stock . '"'
                               . '>' . htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['item_code']) . ')</option>';
                        }
                    }
                    ?>
                </select>
                <p class="text-xs text-gray-400 mt-1" id="item_match_count"></p>
            </div>

            <!-- Real-time stock badge -->
            <div id="stock_display" class="hidden mb-6 p-4 rounded-lg border flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-600">Available Stock:</span>
                <span id="stock_value" class="text-2xl font-bold"></span>
                <span id="stock_unit" class="text-sm text-gray-500">units</span>
                <span id="stock_warning" class="hidden ml-auto text-xs font-semibold text-red-600 bg-red-50 border border-red-200 px-2 py-1 rounded">⚠ Quantity exceeds available stock</span>
                <span id="stock_zero" class="hidden ml-auto text-xs font-semibold text-orange-600 bg-orange-50 border border-orange-200 px-2 py-1 rounded">⚠ No stock available for this item</span>
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

    var qtyInput    = document.getElementById('quantity_used');
    var stockBox    = document.getElementById('stock_display');
    var stockVal    = document.getElementById('stock_value');
    var stockWarn   = document.getElementById('stock_warning');
    var stockZero   = document.getElementById('stock_zero');
    var currentStock = 0;

    function showStock(stock) {
        currentStock = parseInt(stock, 10);
        stockBox.classList.remove('hidden');
        stockVal.textContent = currentStock;
        // Colour the badge based on level
        stockBox.className = stockBox.className.replace(/border-\S+|bg-\S+/g, '');
        if (currentStock === 0) {
            stockBox.classList.add('border-orange-300', 'bg-orange-50');
            stockVal.className = 'text-2xl font-bold text-orange-600';
            stockZero.classList.remove('hidden');
            stockWarn.classList.add('hidden');
        } else if (currentStock <= 10) {
            stockBox.classList.add('border-yellow-300', 'bg-yellow-50');
            stockVal.className = 'text-2xl font-bold text-yellow-600';
            stockZero.classList.add('hidden');
        } else {
            stockBox.classList.add('border-green-300', 'bg-green-50');
            stockVal.className = 'text-2xl font-bold text-green-600';
            stockZero.classList.add('hidden');
        }
        checkQty();
    }

    function checkQty() {
        var qty = parseInt(qtyInput.value, 10);
        if (!isNaN(qty) && qty > currentStock && currentStock > 0) {
            stockWarn.classList.remove('hidden');
        } else {
            stockWarn.classList.add('hidden');
        }
    }

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
                    showStock(opt.getAttribute('data-stock') || 0);
                    searchInput.value = opt.text;
                }
            });
        }
    });

    // Show stock when user selects an item
    select.addEventListener('change', function() {
        var chosen = select.options[select.selectedIndex];
        if (chosen && chosen.value) {
            searchInput.value = chosen.text;
            showStock(chosen.getAttribute('data-stock') || 0);
        } else {
            stockBox.classList.add('hidden');
        }
    });

    // Re-validate quantity when user types
    qtyInput.addEventListener('input', checkQty);
})();
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
