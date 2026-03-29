<?php
// Filename: item_management.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch items, supplier name, and current stock, ordered by item_id
$sql = "
    SELECT
        i.*,
        s.name as supplier_name,
        COALESCE(SUM(b.quantity), 0) as current_stock
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN item_batches b ON i.item_id = b.item_id AND b.quantity > 0 /* Only count batches with stock */
    GROUP BY i.item_id
    ORDER BY i.item_id ASC /* Order by ID for default view */
";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Item Master Management</h1>
        <a href="add_item.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add New Item
        </a>
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

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="overflow-x-auto">
            <!-- Table with ID for DataTables -->
            <table class="min-w-full bg-white display" id="itemTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">ID</th> <!-- Added ID Header -->
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Category</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Primary Supplier</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Brand</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Unit Cost (₱)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right bg-blue-700">Current Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">UoM</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <!-- All dynamic outputs are now protected by htmlspecialchars() -->
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['item_id']); ?></td> <!-- Added ID Data -->
                            <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['item_code'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4">
                                <?php
                                $supplier_name = htmlspecialchars($row['supplier_name'] ?? 'N/A');
                                if ($supplier_name == 'N/A') {
                                    echo '<span class="text-red-500">N/A</span>';
                                } else {
                                    echo $supplier_name;
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['brand_name'] ?? 'N/A'); ?></td>
                            <td class="py-3 px-4 text-right">₱<?php echo htmlspecialchars(number_format($row['unit_cost'], 2)); ?></td>
                            <td class="py-3 px-4 text-right font-bold bg-blue-50">
                                <?php echo htmlspecialchars(number_format($row['current_stock'])); ?>
                            </td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['unit_of_measure']); ?></td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <a href="edit_item.php?id=<?php echo htmlspecialchars($row['item_id']); ?>" class="text-blue-500 hover:text-blue-700 font-semibold mr-2">Edit</a>

                                <button type="button"
                                    onclick="showBarcode(<?php echo json_encode($row['item_code'] ?? $row['item_id']); ?>, <?php echo json_encode($row['name']); ?>)"
                                    class="text-green-600 hover:text-green-800 font-semibold mr-2">Barcode</button>

                                <!-- CSRF Token added to delete form -->
                                <form action="delete_item_handler.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($row['item_id']); ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 font-semibold">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-gray-500">No items found.</td> <!-- Updated colspan -->
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- REMOVED DATATABLES INIT SCRIPT FROM HERE -->

<?php
$conn->close();
require_once 'footer.php'; // Footer now includes jQuery and DataTables JS
?>

<!-- Barcode Modal -->
<div id="barcodeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center">
        <h2 class="text-xl font-bold text-gray-800 mb-1" id="barcodeItemName"></h2>
        <p class="text-sm text-gray-500 mb-4 font-mono" id="barcodeItemCode"></p>
        <svg id="barcodeSvg" class="mx-auto"></svg>
        <div class="mt-6 flex justify-center gap-3">
            <button onclick="printBarcode()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Print</button>
            <button onclick="closeBarcodeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
function showBarcode(itemCode, itemName) {
    document.getElementById('barcodeItemName').textContent = itemName;
    document.getElementById('barcodeItemCode').textContent = itemCode;
    try {
        JsBarcode('#barcodeSvg', itemCode, {
            format: 'CODE128',
            width: 2,
            height: 80,
            displayValue: true,
            fontSize: 14,
            margin: 10
        });
    } catch(e) {
        document.getElementById('barcodeSvg').innerHTML = '<text y="40" fill="red">Cannot generate barcode for this code</text>';
    }
    document.getElementById('barcodeModal').classList.remove('hidden');
}

function closeBarcodeModal() {
    document.getElementById('barcodeModal').classList.add('hidden');
}

function printBarcode() {
    const name = document.getElementById('barcodeItemName').textContent;
    const code = document.getElementById('barcodeItemCode').textContent;
    const svgEl = document.getElementById('barcodeSvg');
    const svgData = new XMLSerializer().serializeToString(svgEl);
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>Barcode - ${code}</title><style>body{display:flex;align-items:center;justify-content:center;flex-direction:column;font-family:sans-serif;padding:20px;}h3{margin:0 0 4px;}p{margin:0 0 12px;font-size:13px;color:#555;font-family:monospace;}@media print{button{display:none;}}</style></head><body><h3>${name}</h3><p>${code}</p>${svgData}<br><button onclick="window.print()">Print</button></body></html>`);
    win.document.close();
}

// Close modal when clicking backdrop
document.getElementById('barcodeModal').addEventListener('click', function(e) {
    if (e.target === this) closeBarcodeModal();
});
</script>

