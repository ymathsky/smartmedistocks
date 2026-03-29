<?php
// Filename: smart/create_purchase_order.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Procurement should create POs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// 1. Get parameters from URL
$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$suggested_quantity = filter_input(INPUT_GET, 'quantity', FILTER_VALIDATE_INT);

if (!$item_id || !$suggested_quantity || $suggested_quantity <= 0) {
    $_SESSION['error'] = "Invalid item or quantity specified for PO creation.";
    header("Location: order_suggestion.php");
    exit();
}

// 2. Fetch item and supplier details
$stmt = $conn->prepare("
    SELECT 
        i.name, i.item_code, i.unit_cost, i.unit_of_measure, i.shelf_life_days, 
        s.supplier_id, s.name AS supplier_name, s.contact_info, s.average_lead_time_days
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    WHERE i.item_id = ?
");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Item not found.";
    header("Location: order_suggestion.php");
    exit();
}
$item_data = $result->fetch_assoc();
$stmt->close();

$po_number = "PO-" . time(); // Simple unique PO number generator
$supplier_id = $item_data['supplier_id'];
$supplier_name = htmlspecialchars($item_data['supplier_name'] ?? 'N/A');
$lead_time = $item_data['average_lead_time_days'] ?? 7;
$expected_delivery_date = date('Y-m-d', strtotime("+$lead_time days"));

// Calculate the expected total cost
$unit_cost = (float)$item_data['unit_cost'];
$total_cost = number_format($unit_cost * $suggested_quantity, 2);

// Check if supplier is available
if (!$supplier_id) {
    $_SESSION['error'] = "Cannot create PO: Item **" . htmlspecialchars($item_data['name']) . "** does not have an assigned supplier. Please assign one in Item Management.";
    header("Location: order_suggestion.php");
    exit();
}
?>

<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Finalize Purchase Order</h1>

        <!-- Order Summary -->
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 mb-6">
            <h2 class="text-xl font-semibold text-blue-800 mb-2">Order Summary:</h2>
            <p class="text-sm">PO Number: <span class="font-mono font-bold text-gray-700"><?php echo $po_number; ?></span></p>
            <p class="text-sm">Item: <span class="font-bold"><?php echo htmlspecialchars($item_data['name']); ?></span> (<span class="font-mono"><?php echo htmlspecialchars($item_data['item_code']); ?></span>)</p>
            <p class="text-sm">Suggested Quantity: <span class="font-bold text-green-700"><?php echo $suggested_quantity; ?></span> <?php echo htmlspecialchars($item_data['unit_of_measure']); ?></p>
            <p class="text-sm">Estimated Total Cost: <span class="font-bold text-lg">₱<?php echo $total_cost; ?></span></p>
        </div>

        <!-- UPDATED: Form now posts to the dedicated handler -->
        <form action="create_purchase_order_handler.php" method="POST">
            <!-- Hidden input for CSRF protection -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <!-- Hidden inputs for essential data -->
            <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($po_number); ?>">
            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item_id); ?>">
            <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Supplier and Item Details -->
                <div>
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Supplier & Item Details</h3>

                    <div class="mb-4">
                        <label for="supplier_display" class="block text-gray-700 font-bold mb-2">Supplier</label>
                        <select id="supplier_display" name="supplier_display" class="shadow border rounded w-full py-2 px-3 bg-gray-100 text-gray-700" disabled>
                            <option selected><?php echo $supplier_name; ?></option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="item_display" class="block text-gray-700 font-bold mb-2">Item</label>
                        <input type="text" id="item_display" value="<?php echo htmlspecialchars($item_data['name']); ?>" class="shadow border rounded w-full py-2 px-3 bg-gray-100 text-gray-700" disabled>
                    </div>

                    <div class="mb-4">
                        <label for="reference" class="block text-gray-700 font-bold mb-2">External Reference / Quote #</label>
                        <input type="text" id="reference" name="reference" placeholder="Optional reference number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                </div>

                <!-- Order Parameters -->
                <div>
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Order Parameters</h3>

                    <div class="mb-4">
                        <label for="quantity" class="block text-gray-700 font-bold mb-2">Order Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="<?php echo htmlspecialchars($suggested_quantity); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <p class="text-xs text-gray-500 mt-1">This is the suggested EOQ quantity.</p>
                    </div>

                    <div class="mb-4">
                        <label for="unit_cost_final" class="block text-gray-700 font-bold mb-2">Agreed Unit Cost (₱)</label>
                        <input type="number" step="0.01" id="unit_cost_final" name="unit_cost_final" value="<?php echo htmlspecialchars($unit_cost); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>

                    <div class="mb-4">
                        <label for="expected_delivery_date" class="block text-gray-700 font-bold mb-2">Expected Delivery Date</label>
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?php echo htmlspecialchars($expected_delivery_date); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <p class="text-xs text-gray-500 mt-1">Calculated based on <?php echo $lead_time; ?>-day lead time.</p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end mt-8 border-t pt-6">
                <a href="order_suggestion.php" class="inline-block align-baseline font-bold text-sm text-gray-600 hover:text-gray-800 mr-4">
                    Cancel & Back to Suggestions
                </a>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md focus:outline-none focus:shadow-outline">
                    Create & Place Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
