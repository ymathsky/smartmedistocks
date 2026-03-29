<?php
// Filename: transaction_history.php

require_once 'header.php';
require_once 'db_connection.php';

// Security check: Admins and Pharmacists can view history
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch transaction data with item details
$sql = "SELECT t.transaction_id, t.quantity_used, t.transaction_date, i.name as item_name, i.item_code
        FROM transactions t
        JOIN items i ON t.item_id = i.item_id
        ORDER BY t.transaction_date DESC, t.transaction_id DESC";
$result = $conn->query($sql);
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Transaction History</h1>
        <a href="record_usage.php" class="text-blue-600 hover:underline">&larr; Back to Record Usage</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Date</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Code</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-left">Item Name</th>
                    <th class="py-3 px-4 uppercase font-semibold text-sm text-right">Quantity Used</th>
                </tr>
                </thead>
                <tbody class="text-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-100">
                            <td class="py-3 px-4"><?php echo htmlspecialchars(date("F j, Y", strtotime($row['transaction_date']))); ?></td>
                            <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['item_code']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td class="py-3 px-4 text-right"><?php echo htmlspecialchars($row['quantity_used']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">No transactions found.</td>
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
