<?php
// Filename: smart/items_inventory.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Warehouse can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Warehouse'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all items with their current summed stock quantity
$sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_of_measure,
        COALESCE(SUM(b.quantity), 0) AS current_stock
    FROM 
        items i
    LEFT JOIN 
        item_batches b ON i.item_id = b.item_id
    GROUP BY
        i.item_id, i.name, i.item_code, i.unit_of_measure
    ORDER BY 
        i.name ASC
";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Current Warehouse Stock Overview</h1>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <p class="text-gray-600 mb-6">This list provides the summed, current on-hand quantity for every item in the system across all locations.</p>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border" id="inventoryTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Current Stock</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">UoM</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Stock Status</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()):
                        $stock = (int)$row['current_stock'];
                        $status_class = $stock > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        $status_text = $stock > 0 ? 'In Stock' : 'Out of Stock';
                        ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['item_code']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-3 px-4 text-right font-bold"><?php echo number_format($stock); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['unit_of_measure']); ?></td>
                            <td class="py-3 px-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">No items found in the item master list.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Initialize DataTables
    $(document).ready(function() {
        $('#inventoryTable').DataTable({
            "pagingType": "full_numbers",
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ]
        });
    });
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
