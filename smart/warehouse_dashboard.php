<?php
// Filename: warehouse_dashboard.php
global $conn; // Use the global connection from index.php

// --- Fetch Data for Near-Expiry Alerts ---
$thirty_days_from_now = date('Y-m-d', strtotime('+30 days'));
$sixty_days_from_now = date('Y-m-d', strtotime('+60 days'));
$today = date('Y-m-d');

// Expiring in < 30 days (Critical)
$critical_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date
                 FROM item_batches b
                 JOIN items i ON b.item_id = i.item_id
                 WHERE b.expiry_date IS NOT NULL AND b.expiry_date <= ? AND b.quantity > 0
                 ORDER BY b.expiry_date ASC";
$critical_stmt = $conn->prepare($critical_sql);
$critical_stmt->bind_param("s", $thirty_days_from_now);
$critical_stmt->execute();
$critical_result = $critical_stmt->get_result();

// Expiring in 30-60 days (Warning)
$warning_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date
                FROM item_batches b
                JOIN items i ON b.item_id = i.item_id
                WHERE b.expiry_date > ? AND b.expiry_date <= ? AND b.quantity > 0
                ORDER BY b.expiry_date ASC";
$warning_stmt = $conn->prepare($warning_sql);
$warning_stmt->bind_param("ss", $thirty_days_from_now, $sixty_days_from_now);
$warning_stmt->execute();
$warning_result = $warning_stmt->get_result();

// --- Fetch All Inventory Batches for Detailed View ---
$all_batches_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, b.received_date, b.purchase_order_id
                    FROM item_batches b
                    JOIN items i ON b.item_id = i.item_id
                    WHERE b.quantity > 0
                    ORDER BY i.name, b.expiry_date ASC";
$all_batches_result = $conn->query($all_batches_sql);
?>
<div class="p-6">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Warehouse Dashboard</h1>

        <!-- Expiry Alerts Section -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Expiry Alerts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Critical Alerts -->
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                    <h3 class="font-bold text-lg">Expiring in less than 30 days</h3>
                    <?php if ($critical_result->num_rows > 0): ?>
                        <ul class="list-disc list-inside mt-2 text-sm">
                            <?php while($row = $critical_result->fetch_assoc()): ?>
                                <li><strong><?php echo $row['quantity']; ?>x</strong> <?php echo $row['name']; ?> (Expires: <?php echo date("M j, Y", strtotime($row['expiry_date'])); ?>)</li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-2 text-sm">No items are critically close to expiry.</p>
                    <?php endif; ?>
                </div>
                <!-- Warning Alerts -->
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">
                    <h3 class="font-bold text-lg">Expiring in 30-60 days</h3>
                    <?php if ($warning_result->num_rows > 0): ?>
                        <ul class="list-disc list-inside mt-2 text-sm">
                            <?php while($row = $warning_result->fetch_assoc()): ?>
                                <li><strong><?php echo $row['quantity']; ?>x</strong> <?php echo $row['name']; ?> (Expires: <?php echo date("M j, Y", strtotime($row['expiry_date'])); ?>)</li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-2 text-sm">No items are expiring in the next 30-60 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Inventory View -->
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Detailed Stock Batches</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-2 px-4 text-left">Item Name</th>
                        <th class="py-2 px-4 text-left">Item Code</th>
                        <th class="py-2 px-4 text-right">Quantity</th>
                        <th class="py-2 px-4 text-left">Expiry Date</th>
                        <th class="py-2 px-4 text-left">Received Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($all_batches_result->num_rows > 0): ?>
                        <?php while($row = $all_batches_result->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-100">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="py-2 px-4 font-mono"><?php echo htmlspecialchars($row['item_code']); ?></td>
                                <td class="py-2 px-4 text-right"><?php echo $row['quantity']; ?></td>
                                <td class="py-2 px-4 <?php echo (strtotime($row['expiry_date']) < strtotime('+30 days')) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo $row['expiry_date'] ? date("M j, Y", strtotime($row['expiry_date'])) : 'N/A'; ?>
                                </td>
                                <td class="py-2 px-4"><?php echo date("M j, Y", strtotime($row['received_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No stock batches found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$critical_stmt->close();
$warning_stmt->close();
?>

