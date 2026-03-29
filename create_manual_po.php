<?php
// Filename: smart/create_manual_po.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Procurement can access this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all items with their primary supplier details
$sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost, i.unit_of_measure,
        s.name AS supplier_name, s.supplier_id
    FROM 
        items i
    LEFT JOIN 
        suppliers s ON i.supplier_id = s.supplier_id
    ORDER BY 
        i.name ASC
";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manual Purchase Order Creation</h1>
        <a href="po_management.php" class="text-blue-600 hover:underline">&larr; Back to PO Management</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <p class="text-gray-600 mb-6">Select an item below to manually create a new Purchase Order. You must specify the quantity later. Only items with an assigned supplier are available.</p>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border" id="itemSelectionTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name (Code)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Primary Supplier</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Unit Cost (₱)</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Action</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4">
                                <?php echo htmlspecialchars($row['name']); ?>
                                <span class="font-mono text-xs text-gray-500">(<?php echo htmlspecialchars($row['item_code']); ?>)</span>
                            </td>
                            <td class="py-3 px-4">
                                <?php if (empty($row['supplier_name'])): ?>
                                    <span class="text-red-500 font-semibold">NO SUPPLIER ASSIGNED</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($row['supplier_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">₱<?php echo number_format($row['unit_cost'], 2); ?></td>
                            <td class="py-3 px-4 text-center">
                                <?php if (!empty($row['supplier_id'])): ?>
                                    <!-- Link to the PO creation form with default quantity=1 -->
                                    <a href="create_purchase_order.php?item_id=<?php echo htmlspecialchars($row['item_id']); ?>&quantity=1"
                                       class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-1.5 px-3 rounded-md transition duration-150">
                                        Create PO
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500">Assign Supplier First</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">No items found in the master list.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
