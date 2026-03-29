<?php
// Filename: warehouse_dashboard.php

// DEFINITIVE FIX: Explicitly bring the global database connection variable
// into this script's scope to resolve the persistent "null" error.
global $conn;

// Security check: Only Admins and Warehouse can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// --- Fetch Data for Near-Expiry Alerts ---
$thirty_days_from_now = date('Y-m-d', strtotime('+30 days'));
$sixty_days_from_now = date('Y-m-d', strtotime('+60 days'));
$today = date('Y-m-d');

// Expiring in < 30 days (Critical)
$critical_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, l.name as location_name
                 FROM item_batches b
                 JOIN items i ON b.item_id = i.item_id
                 LEFT JOIN locations l ON b.location_id = l.location_id
                 WHERE b.expiry_date IS NOT NULL AND b.expiry_date <= ? AND b.quantity > 0
                 ORDER BY b.expiry_date ASC";
$critical_stmt = $conn->prepare($critical_sql);
$critical_stmt->bind_param("s", $thirty_days_from_now);
$critical_stmt->execute();
$critical_result = $critical_stmt->get_result();

// Expiring in 30-60 days (Warning)
$warning_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, l.name as location_name
                FROM item_batches b
                JOIN items i ON b.item_id = i.item_id
                LEFT JOIN locations l ON b.location_id = l.location_id
                WHERE b.expiry_date > ? AND b.expiry_date <= ? AND b.quantity > 0
                ORDER BY b.expiry_date ASC";
$warning_stmt = $conn->prepare($warning_sql);
$warning_stmt->bind_param("ss", $thirty_days_from_now, $sixty_days_from_now);
$warning_stmt->execute();
$warning_result = $warning_stmt->get_result();

// --- Fetch All Inventory Batches for Detailed View ---
$all_batches_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, b.received_date, b.purchase_order_id, l.name as location_name
                    FROM item_batches b
                    JOIN items i ON b.item_id = i.item_id
                    LEFT JOIN locations l ON b.location_id = l.location_id
                    WHERE b.quantity > 0
                    ORDER BY i.name, b.expiry_date ASC";
$all_batches_result = $conn->query($all_batches_sql);

// --- CHART DATA: Stock by Location ---
$location_stock_sql = "
    SELECT l.name, SUM(b.quantity) as total_quantity
    FROM locations l
    JOIN item_batches b ON l.location_id = b.location_id
    WHERE b.quantity > 0
    GROUP BY l.location_id
    ORDER BY total_quantity DESC
";
$location_stock_result = $conn->query($location_stock_sql);
$location_labels = [];
$location_values = [];
if ($location_stock_result) {
    while ($row = $location_stock_result->fetch_assoc()) {
        $location_labels[] = $row['name'];
        $location_values[] = $row['total_quantity'];
    }
}
$location_chart_data = ['labels' => $location_labels, 'values' => $location_values];

?>
<link rel="stylesheet" href="dashboard.css">
<div class="p-5 max-w-screen-2xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-7">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Warehouse Dashboard</h1>
            <p class="text-xs text-gray-400 mt-0.5"><?php echo date("l, F j, Y"); ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="receive_stock.php" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Receive Stock
            </a>
            <a href="move_stock.php" class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition">
                Move Stock
            </a>
        </div>
    </div>

    <!-- Chart + Expiry Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-7">
        <!-- Stock by Location -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="section-title mb-4">Stock Quantity by Location</p>
            <canvas id="locationStockChart" height="195"></canvas>
        </div>

        <!-- Expiry Alerts -->
        <div class="grid grid-rows-2 gap-4">
            <!-- Critical -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="section-title">Expiring &lt;30 Days</p>
                    <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Critical</span>
                </div>
                <?php if ($critical_result->num_rows > 0): ?>
                    <div class="space-y-1.5 max-h-28 overflow-y-auto">
                        <?php $critical_result->data_seek(0); while($row = $critical_result->fetch_assoc()): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-800 truncate mr-2"><?php echo htmlspecialchars($row['name']); ?> <span class="text-gray-400 font-mono"><?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?></span></span>
                                <div class="flex-shrink-0 text-right">
                                    <span class="font-bold text-red-600"><?php echo date("M j", strtotime($row['expiry_date'])); ?></span>
                                    <span class="text-gray-400 ml-1"><?php echo $row['quantity']; ?>u</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-400">No critical expiry batches.</p>
                <?php endif; ?>
            </div>
            <!-- Warning -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="section-title">Expiring 30&ndash;60 Days</p>
                    <span class="text-xs font-semibold bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">Warning</span>
                </div>
                <?php if ($warning_result->num_rows > 0): ?>
                    <div class="space-y-1.5 max-h-28 overflow-y-auto">
                        <?php $warning_result->data_seek(0); while($row = $warning_result->fetch_assoc()): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-800 truncate mr-2"><?php echo htmlspecialchars($row['name']); ?> <span class="text-gray-400 font-mono"><?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?></span></span>
                                <div class="flex-shrink-0 text-right">
                                    <span class="font-bold text-amber-600"><?php echo date("M j", strtotime($row['expiry_date'])); ?></span>
                                    <span class="text-gray-400 ml-1"><?php echo $row['quantity']; ?>u</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-400">No warning expiry batches.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detailed Stock Batches (DataTable) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-2 mb-5">
            <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
            <p class="section-title">Detailed Stock Batches</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="batchesTable">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                        <th class="pb-3 text-left font-semibold tracking-wide">Item Name</th>
                        <th class="pb-3 text-left font-semibold tracking-wide">Code</th>
                        <th class="pb-3 text-left font-semibold tracking-wide">Location</th>
                        <th class="pb-3 text-right font-semibold tracking-wide">Qty</th>
                        <th class="pb-3 text-left font-semibold tracking-wide">Expiry</th>
                        <th class="pb-3 text-left font-semibold tracking-wide">Received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if ($all_batches_result->num_rows > 0): ?>
                    <?php while($row = $all_batches_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-2.5 font-medium text-gray-800"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-2.5 font-mono text-xs text-gray-500"><?php echo htmlspecialchars($row['item_code']); ?></td>
                            <td class="py-2.5 text-xs font-semibold text-gray-700"><?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?></td>
                            <td class="py-2.5 text-right font-bold text-gray-900"><?php echo $row['quantity']; ?></td>
                            <td class="py-2.5 text-xs <?php echo ($row['expiry_date'] && strtotime($row['expiry_date']) < strtotime('+30 days')) ? 'text-red-600 font-bold' : 'text-gray-500'; ?>">
                                <?php echo $row['expiry_date'] ? date("M j, Y", strtotime($row['expiry_date'])) : 'N/A'; ?>
                            </td>
                            <td class="py-2.5 text-xs text-gray-400"><?php echo date("M j, Y", strtotime($row['received_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="py-6 text-center text-xs text-gray-400">No stock batches found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$critical_stmt->close();
$warning_stmt->close();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="dashboard.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#batchesTable').DataTable({ pageLength: 15, order: [[4, 'asc']] });

    const locationData = <?php echo json_encode($location_chart_data); ?>;
    if (locationData.labels.length > 0) {
        new Chart(document.getElementById('locationStockChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: locationData.labels,
                datasets: [{ data: locationData.values, backgroundColor: '#3b82f6', borderRadius: 6, barThickness: 22 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }
});
</script>
