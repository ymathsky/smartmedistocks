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

// Fetch active locations for the dropdown
$locations_sql = "SELECT location_id, name FROM locations WHERE is_active = 1 ORDER BY name ASC";
$locations_result = $conn->query($locations_sql);

// NEW: Fetch pending Purchase Orders (Placed or Shipped)
$po_sql = "
    SELECT 
        po.po_id, po.po_number, po.item_id, po.quantity_ordered, po.expected_delivery_date, i.name as item_name
    FROM 
        purchase_orders po
    JOIN
        items i ON po.item_id = i.item_id
    WHERE 
        po.status IN ('Placed', 'Shipped')
    ORDER BY 
        po.expected_delivery_date ASC
";
$po_result = $conn->query($po_sql);

$pending_pos = [];
if ($po_result) {
    while ($row = $po_result->fetch_assoc()) {
        $pending_pos[] = $row;
    }
}

// NEW: Fetch Receipt History for the current user
$current_user_id = $_SESSION['user_id'];
$history_sql = "
    SELECT 
        dl.details, dl.created_at
    FROM 
        decision_log dl
    WHERE 
        dl.user_id = ? AND dl.action_type = 'Received Stock'
    ORDER BY 
        dl.created_at DESC
    LIMIT 10
";

$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $current_user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$receipt_history = [];
while ($row = $history_result->fetch_assoc()) {
    $receipt_history[] = $row;
}
$history_stmt->close();
?>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Receive Incoming Stock</h1>
        <p class="mb-6 text-gray-600">Use this form to record the arrival of new inventory. Receiving stock from a **Purchase Order** will automatically update its status to 'Received'.</p>

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

        <form action="receive_stock_handler.php" method="POST" id="receive_stock_form">
            <!-- CSRF Token (NEW) -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- UPDATED: Purchase Order Select -->
            <div class="mb-4">
                <label for="purchase_order_id" class="block text-gray-700 text-sm font-bold mb-2">Purchase Order (Optional):</label>
                <select id="purchase_order_id" name="purchase_order_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Manual Entry or Select PO --</option>
                    <?php foreach($pending_pos as $po): ?>
                        <option
                                value="<?php echo htmlspecialchars($po['po_number']); ?>"
                                data-item-id="<?php echo htmlspecialchars($po['item_id']); ?>"
                                data-quantity="<?php echo htmlspecialchars($po['quantity_ordered']); ?>"
                                data-delivery-date="<?php echo htmlspecialchars($po['expected_delivery_date']); ?>"
                        >
                            <?php echo htmlspecialchars($po['po_number']) . " - " . htmlspecialchars($po['item_name']) . " (" . $po['quantity_ordered'] . " units)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Selecting a PO will auto-fill the Item, Quantity, and Expected Delivery Date.</p>
            </div>
            <!-- END UPDATED -->

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

            <!-- Location Selection -->
            <div class="mb-4">
                <label for="location_id" class="block text-gray-700 text-sm font-bold mb-2">Assign Location:</label>
                <select id="location_id" name="location_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">-- Choose a location --</option>
                    <?php
                    // Rewind the location result set since it was consumed in the outer loop
                    if ($locations_result) $locations_result->data_seek(0);
                    while($location = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $location['location_id']; ?>">
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <!-- END NEW -->

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

    <!-- NEW: Receipt History Section -->
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg mx-auto mt-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Your Recent Receipts</h2>

        <?php if (!empty($receipt_history)): ?>
            <ul class="divide-y divide-gray-200">
                <?php foreach ($receipt_history as $log): ?>
                    <li class="py-3">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['details']); ?></p>
                        <p class="text-xs text-gray-500">
                            Received on: <?php echo date("F j, Y, g:i a", strtotime($log['created_at'])); ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-gray-500 text-sm">No recent stock receipts logged by your account.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const poSelect = document.getElementById('purchase_order_id');
        const itemIdSelect = document.getElementById('item_id');
        const quantityInput = document.getElementById('quantity_received');
        const deliveryDateInput = document.getElementById('expected_delivery_date');

        // Store all available item options for later use in logic
        const originalItemOptions = Array.from(itemIdSelect.options).filter(opt => opt.value !== '');

        poSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption.value === "") {
                // Manual entry selected or reset
                itemIdSelect.value = "";
                quantityInput.value = "";
                deliveryDateInput.value = "";
                // Re-enable all inputs if necessary, though they are already enabled by default.
                itemIdSelect.disabled = false;
                quantityInput.disabled = false;
            } else {
                // PO selected: retrieve data attributes
                const itemId = selectedOption.getAttribute('data-item-id');
                const quantity = selectedOption.getAttribute('data-quantity');
                const deliveryDate = selectedOption.getAttribute('data-delivery-date');

                // 1. Set Item ID
                itemIdSelect.value = itemId;

                // 2. Set Quantity
                quantityInput.value = quantity;
                quantityInput.min = quantity; // Prevent accidental lower entry

                // 3. Set Expected Delivery Date
                deliveryDateInput.value = deliveryDate;

                // 4. Disable relevant fields to force use of PO data
                // While disabling them is good, setting them to read-only might be better if the warehouse needs to adjust quantity
                itemIdSelect.disabled = true; // Item must match the PO

                // Keep quantity and date enabled for slight adjustments, but warn the user if changed
            }
        });

        // Add a handler to reset fields if the PO is unselected, and re-enable inputs
        poSelect.addEventListener('change', function() {
            if (this.value === "") {
                itemIdSelect.value = "";
                quantityInput.value = "";
                deliveryDateInput.value = "";
                quantityInput.min = 1;

                // Re-enable inputs
                itemIdSelect.disabled = false;
            }
        });

        // Add an event listener to the form submission to re-enable disabled fields
        // so their values are sent to the handler.
        document.getElementById('receive_stock_form').addEventListener('submit', function(e) {
            itemIdSelect.disabled = false;
            // The handler relies on item_id, so it must be sent even if disabled for the user
        });
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
