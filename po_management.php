<?php
// Filename: smart/po_management.php
require_once 'header.php';
require_once 'db_connection.php';

// Security check: Only Admins and Procurement can access this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch all POs with associated item and supplier data
$sql = "
    SELECT 
        po.po_id, po.po_number, po.quantity_ordered, po.unit_cost_agreed, po.expected_delivery_date,
        po.actual_delivery_date,
        po.status, po.created_at, po.external_reference,
        i.name as item_name, i.item_code,
        s.name as supplier_name,
        u.username as created_by
    FROM 
        purchase_orders po
    JOIN 
        items i ON po.item_id = i.item_id
    JOIN 
        suppliers s ON po.supplier_id = s.supplier_id
    JOIN 
        users u ON po.created_by_user_id = u.user_id
    ORDER BY 
        po.created_at DESC
";
$result = $conn->query($sql);

// Function to determine badge style based on PO status
function get_status_badge($status) {
    switch ($status) {
        case 'Placed':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Placed</span>';
        case 'Shipped':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Shipped</span>';
        case 'Received':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Received</span>';
        case 'Cancelled':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>';
        case 'Draft':
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Draft</span>';
        default:
            return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-300 text-gray-600">Unknown</span>';
    }
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Purchase Order Management</h1>
        <div class="flex space-x-3">
            <a href="create_manual_po.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
                + Manual PO (Select Item)
            </a>
            <a href="order_suggestion.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
                + Create New PO from Suggestion
            </a>
        </div>
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
        <p class="text-gray-600 mb-6">Track the status of all past and pending purchase orders. Warehouse staff will mark orders as Received via the 'Receive Stock' page.</p>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border" id="poTable">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">PO Number</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Status</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item / Qty</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Supplier</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Total Cost</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Expected Delivery</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Actual / Variance</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()):
                        $total_cost = $row['quantity_ordered'] * $row['unit_cost_agreed'];
                        ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4 font-mono font-semibold text-blue-600"><?php echo htmlspecialchars($row['po_number']); ?></td>
                            <td class="py-3 px-4"><?php echo get_status_badge($row['status']); ?></td>
                            <td class="py-3 px-4 text-sm"><?php echo htmlspecialchars($row['item_name']); ?> (<?php echo $row['quantity_ordered']; ?>)</td>
                            <td class="py-3 px-4 text-sm"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td class="py-3 px-4 text-right font-bold">₱<?php echo number_format($total_cost, 2); ?></td>
                            <td class="py-3 px-4 text-sm font-semibold"><?php echo htmlspecialchars(date('M j, Y', strtotime($row['expected_delivery_date']))); ?></td>
                            <td class="py-3 px-4 text-sm">
                                <?php if ($row['actual_delivery_date']): ?>
                                    <?php
                                        $variance = (int)((strtotime($row['actual_delivery_date']) - strtotime($row['expected_delivery_date'])) / 86400);
                                        $vSign = $variance > 0 ? '+' : '';
                                        $vClass = $variance > 3 ? 'text-red-600' : ($variance > 0 ? 'text-yellow-600' : 'text-green-600');
                                    ?>
                                    <div class="font-semibold"><?php echo date('M j, Y', strtotime($row['actual_delivery_date'])); ?></div>
                                    <div class="text-xs <?php echo $vClass; ?> font-semibold">
                                        <?php echo $vSign . $variance; ?> day<?php echo abs($variance) !== 1 ? 's' : ''; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">

                                <!-- NEW: Print PO Button -->
                                <a href="print_po.php?id=<?php echo htmlspecialchars($row['po_id']); ?>" target="_blank"
                                   class="bg-gray-500 hover:bg-gray-600 text-white text-xs font-bold py-1 px-3 rounded-md transition duration-150 mr-2 inline-block">
                                    Print
                                </a>

                                <?php if ($row['status'] == 'Placed'): ?>
                                    <button onclick="updatePoStatus('<?php echo htmlspecialchars($row['po_id']); ?>', 'Shipped')"
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-bold py-1 px-3 rounded-md transition duration-150">
                                        Mark Shipped
                                    </button>
                                <?php elseif ($row['status'] == 'Shipped'): ?>
                                    <span class="text-xs font-bold text-green-700 bg-green-50 px-2 py-1 rounded">
                                        Awaiting Stock Receipt
                                    </span>
                                <?php elseif ($row['status'] == 'Draft'): ?>
                                    <button onclick="updatePoStatus('<?php echo htmlspecialchars($row['po_id']); ?>', 'Placed')"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1 px-3 rounded-md transition duration-150">
                                        Place Order
                                    </button>
                                <?php elseif ($row['status'] == 'Received'): ?>
                                    <span class="text-green-600 text-xs font-bold">Closed - Received</span>
                                <?php endif; ?>

                                <?php if ($row['status'] == 'Placed' || $row['status'] == 'Draft' || $row['status'] == 'Shipped'): ?>
                                    <button onclick="updatePoStatus('<?php echo htmlspecialchars($row['po_id']); ?>', 'Cancelled', true)"
                                            class="text-red-600 hover:text-red-800 text-xs font-bold py-1 px-3 rounded-md ml-2">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-gray-500">No Purchase Orders found.</td>
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
        $('#poTable').DataTable({
            "pagingType": "full_numbers",
            "order": [[5, "desc"]], // Order by Expected Delivery Date descending
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ]
        });
    });

    // Helper function to handle status updates
    function updatePoStatus(poId, newStatus, confirmAction = false) {
        if (confirmAction && !confirm(`Are you sure you want to change PO ID ${poId} status to ${newStatus}? This action cannot be undone for cancellation.`)) {
            return;
        }

        // Use a simple, non-alert mechanism for user feedback (placeholder for a custom modal)
        const displayMessage = (msg, isError = false) => {
            const container = document.querySelector('.p-6');
            let div = document.createElement('div');
            div.className = `bg-${isError ? 'red' : 'green'}-100 border-l-4 border-${isError ? 'red' : 'green'}-500 text-${isError ? 'red' : 'green'}-700 p-4 mb-4 fixed top-4 right-4 z-50 rounded shadow-lg transition-opacity duration-300`;
            div.innerHTML = `<p>${msg}</p>`;
            container.appendChild(div);
            setTimeout(() => div.style.opacity = 0, 3000);
            setTimeout(() => div.remove(), 3500);
        };

        const formData = new FormData();
        formData.append('po_id', poId);
        formData.append('new_status', newStatus);
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>');

        fetch('po_update_status_handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMessage(data.message);
                    // Reload the page after a short delay to allow the message to show
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    displayMessage('Error: ' + (data.message || 'Failed to update status.'), true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayMessage('An unexpected network error occurred.', true);
            });
    }
</script>

<?php
$conn->close();
require_once 'footer.php';
?>
