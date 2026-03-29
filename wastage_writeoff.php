<?php
// Filename: wastage_writeoff.php
require_once 'header.php';
require_once 'db_connection.php';

// Warehouse and Admin only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Fetch expired batches (expiry_date < today, quantity > 0)
$expired_sql = "SELECT b.batch_id, b.item_id, b.quantity, b.expiry_date, b.purchase_order_id,
                       i.name AS item_name, i.item_code, COALESCE(i.unit_cost, 0) AS unit_cost
                FROM item_batches b
                JOIN items i ON b.item_id = i.item_id
                WHERE b.expiry_date < CURDATE() AND b.quantity > 0
                  AND b.status IN ('Active', 'Quarantined')
                ORDER BY b.expiry_date ASC";
$expired_result = $conn->query($expired_sql);

// Fetch near-expiry batches (expiry_date within next 30 days, quantity > 0)
$near_sql = "SELECT b.batch_id, b.item_id, b.quantity, b.expiry_date, b.purchase_order_id,
                    i.name AS item_name, i.item_code, COALESCE(i.unit_cost, 0) AS unit_cost
             FROM item_batches b
             JOIN items i ON b.item_id = i.item_id
             WHERE b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND b.quantity > 0
               AND b.status = 'Active'
             ORDER BY b.expiry_date ASC";
$near_result = $conn->query($near_sql);
?>

<link rel="stylesheet" href="dashboard.css">

<div class="flex-1 p-6 bg-gray-100 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Wastage Write-off</h1>
            <p class="text-gray-500 text-sm mt-1">Select expired or near-expiry batches to formally write off. All write-offs are logged with financial impact.</p>
        </div>

        <!-- Feedback -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-5 rounded" role="alert">
                <?php echo nl2br(htmlspecialchars($_SESSION['message'])); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-5 rounded" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="wastage_writeoff_handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- ── EXPIRED BATCHES ─────────────────────────── -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-red-50">
                    <span class="ds-dot ds-dot--red"></span>
                    <h2 class="text-base font-semibold text-red-700">Expired Batches</h2>
                    <span class="ml-auto text-xs text-red-500 font-medium">Expiry date has passed</span>
                </div>

                <?php if ($expired_result->num_rows === 0): ?>
                    <div class="ds-empty text-gray-400 text-sm py-8">No expired batches with remaining stock.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th class="py-3 px-4 text-left w-10">
                                        <input type="checkbox" id="selectAllExpired" title="Select all expired" class="rounded">
                                    </th>
                                    <th class="py-3 px-4 text-left">Item</th>
                                    <th class="py-3 px-4 text-left">Batch / PO</th>
                                    <th class="py-3 px-4 text-right">Qty</th>
                                    <th class="py-3 px-4 text-right">Unit Cost</th>
                                    <th class="py-3 px-4 text-right">Write-off Value</th>
                                    <th class="py-3 px-4 text-center">Expiry</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php while ($row = $expired_result->fetch_assoc()):
                                    $writeoffVal = $row['quantity'] * $row['unit_cost'];
                                ?>
                                <tr class="hover:bg-red-50 transition-colors batch-row" data-type="expired">
                                    <td class="py-3 px-4">
                                        <input type="checkbox" name="batch_ids[]"
                                               value="<?php echo (int)$row['batch_id']; ?>"
                                               class="batch-checkbox rounded"
                                               data-value="<?php echo number_format($writeoffVal, 2); ?>">
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['item_code']); ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500">
                                        Batch #<?php echo (int)$row['batch_id']; ?>
                                        <?php if ($row['purchase_order_id']): ?>
                                            <span class="ml-1 text-xs text-gray-400">(<?php echo htmlspecialchars($row['purchase_order_id']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-semibold text-gray-800"><?php echo number_format($row['quantity']); ?></td>
                                    <td class="py-3 px-4 text-right text-gray-500">₱<?php echo number_format($row['unit_cost'], 2); ?></td>
                                    <td class="py-3 px-4 text-right font-semibold text-red-600">₱<?php echo number_format($writeoffVal, 2); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded-full">
                                            <?php echo date('M j, Y', strtotime($row['expiry_date'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── NEAR-EXPIRY BATCHES ─────────────────────── -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-yellow-50">
                    <span class="ds-dot ds-dot--amber"></span>
                    <h2 class="text-base font-semibold text-yellow-700">Near-Expiry Batches</h2>
                    <span class="ml-auto text-xs text-yellow-600 font-medium">Expiring within 30 days</span>
                </div>

                <?php if ($near_result->num_rows === 0): ?>
                    <div class="ds-empty text-gray-400 text-sm py-8">No near-expiry batches found.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                                <tr>
                                    <th class="py-3 px-4 text-left w-10">
                                        <input type="checkbox" id="selectAllNear" title="Select all near-expiry" class="rounded">
                                    </th>
                                    <th class="py-3 px-4 text-left">Item</th>
                                    <th class="py-3 px-4 text-left">Batch / PO</th>
                                    <th class="py-3 px-4 text-right">Qty</th>
                                    <th class="py-3 px-4 text-right">Unit Cost</th>
                                    <th class="py-3 px-4 text-right">Write-off Value</th>
                                    <th class="py-3 px-4 text-center">Expiry</th>
                                    <th class="py-3 px-4 text-center">Days Left</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php while ($row = $near_result->fetch_assoc()):
                                    $daysLeft = (int)ceil((strtotime($row['expiry_date']) - time()) / 86400);
                                    $writeoffVal = $row['quantity'] * $row['unit_cost'];
                                    $daysClass = $daysLeft <= 7 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700';
                                ?>
                                <tr class="hover:bg-yellow-50 transition-colors">
                                    <td class="py-3 px-4">
                                        <input type="checkbox" name="batch_ids[]"
                                               value="<?php echo (int)$row['batch_id']; ?>"
                                               class="batch-checkbox rounded"
                                               data-value="<?php echo number_format($writeoffVal, 2); ?>">
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['item_code']); ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500">
                                        Batch #<?php echo (int)$row['batch_id']; ?>
                                        <?php if ($row['purchase_order_id']): ?>
                                            <span class="ml-1 text-xs text-gray-400">(<?php echo htmlspecialchars($row['purchase_order_id']); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-semibold text-gray-800"><?php echo number_format($row['quantity']); ?></td>
                                    <td class="py-3 px-4 text-right text-gray-500">₱<?php echo number_format($row['unit_cost'], 2); ?></td>
                                    <td class="py-3 px-4 text-right font-semibold text-yellow-600">₱<?php echo number_format($writeoffVal, 2); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-block bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-1 rounded-full">
                                            <?php echo date('M j, Y', strtotime($row['expiry_date'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-block <?php echo $daysClass; ?> text-xs font-semibold px-2 py-1 rounded-full">
                                            <?php echo $daysLeft; ?> day<?php echo $daysLeft !== 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── WRITE-OFF FORM ──────────────────────────── -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-700 mb-4">Write-off Details</h3>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Write-off <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                              placeholder="e.g., Expired stock during quarterly audit, supplier delivery issue..."></textarea>
                </div>

                <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-3 mb-5">
                    <span class="text-sm text-gray-600">Estimated total write-off value:</span>
                    <span id="totalWriteoff" class="text-xl font-bold text-red-600">₱0.00</span>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            onclick="return confirm('Are you sure you want to write off the selected batches? This cannot be undone.');"
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-lg text-sm transition-colors">
                        Confirm Write-off
                    </button>
                    <a href="index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-6 rounded-lg text-sm transition-colors">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Select all / deselect helpers
document.getElementById('selectAllExpired')?.addEventListener('change', function () {
    document.querySelectorAll('[data-type="expired"] .batch-checkbox').forEach(cb => cb.checked = this.checked);
    updateTotal();
});
document.getElementById('selectAllNear')?.addEventListener('change', function () {
    document.querySelectorAll('tr:not([data-type="expired"]) .batch-checkbox').forEach(cb => cb.checked = this.checked);
    updateTotal();
});

// Live total
document.querySelectorAll('.batch-checkbox').forEach(cb => cb.addEventListener('change', updateTotal));

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.batch-checkbox:checked').forEach(cb => {
        total += parseFloat(cb.dataset.value.replace(/,/g, '')) || 0;
    });
    document.getElementById('totalWriteoff').textContent =
        '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php
$conn->close();
require_once 'footer.php';
