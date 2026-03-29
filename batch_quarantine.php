<?php
// Filename: batch_quarantine.php
require_once 'header.php';
require_once 'db_connection.php';

// Warehouse and Admin only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Warehouse', 'Admin'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

$filter = $_GET['status'] ?? 'Active';
if (!in_array($filter, ['Active', 'Quarantined', 'Written-Off'])) $filter = 'Active';

$batches_sql = "SELECT b.batch_id, b.item_id, b.quantity, b.status, b.expiry_date,
                       b.received_date, b.purchase_order_id, b.location_id,
                       i.name AS item_name, i.item_code,
                       l.name AS location_name
                FROM item_batches b
                JOIN items i ON b.item_id = i.item_id
                LEFT JOIN locations l ON b.location_id = l.location_id
                WHERE b.status = ?
                ORDER BY b.expiry_date ASC, i.name ASC";
$stmt = $conn->prepare($batches_sql);
$stmt->bind_param("s", $filter);
$stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count per status for tabs
$counts = ['Active' => 0, 'Quarantined' => 0, 'Written-Off' => 0];
$count_result = $conn->query("SELECT status, COUNT(*) AS cnt FROM item_batches GROUP BY status");
if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['cnt'];
    }
}
?>

<link rel="stylesheet" href="dashboard.css">

<div class="p-6">
    <div class="max-w-6xl mx-auto">

        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Batch Quarantine &amp; Inspection</h1>
            <p class="text-gray-500 text-sm mt-1">Flag batches for inspection, release them back to stock, or escalate to write-off. Quarantined batches are excluded from all stock counts.</p>
        </div>

        <!-- Feedback -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-5 rounded" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-5 rounded" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Status Tabs -->
        <div class="flex gap-2 mb-6">
            <?php foreach (['Active' => 'bg-green-100 text-green-700', 'Quarantined' => 'bg-yellow-100 text-yellow-700', 'Written-Off' => 'bg-red-100 text-red-700'] as $s => $cls): ?>
                <a href="?status=<?php echo urlencode($s); ?>"
                   class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors
                          <?php echo $filter === $s ? str_replace('100', '200', $cls) . ' ring-2 ring-offset-1 ring-current' : $cls; ?>">
                    <?php echo htmlspecialchars($s); ?>
                    <span class="ml-1 bg-white bg-opacity-60 rounded-full px-1.5 py-0.5 text-xs"><?php echo $counts[$s]; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Batch Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-700"><?php echo htmlspecialchars($filter); ?> Batches</h2>
                <span class="text-sm text-gray-400"><?php echo count($batches); ?> batch<?php echo count($batches) !== 1 ? 'es' : ''; ?></span>
            </div>

            <?php if (empty($batches)): ?>
                <div class="ds-empty text-gray-400 text-sm py-10">No <?php echo htmlspecialchars(strtolower($filter)); ?> batches found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" id="quarantineTable">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="py-3 px-4 text-left">Item</th>
                                <th class="py-3 px-4 text-left">Batch</th>
                                <th class="py-3 px-4 text-left">Location</th>
                                <th class="py-3 px-4 text-right">Qty</th>
                                <th class="py-3 px-4 text-center">Expiry</th>
                                <th class="py-3 px-4 text-center">Received</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($batches as $b):
                                $isExpired = $b['expiry_date'] && strtotime($b['expiry_date']) < time();
                                $expiryClass = $isExpired ? 'text-red-600 font-semibold' : '';
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($b['item_name']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($b['item_code']); ?></div>
                                </td>
                                <td class="py-3 px-4 text-gray-600">
                                    #<?php echo (int)$b['batch_id']; ?>
                                    <?php if ($b['purchase_order_id']): ?>
                                        <span class="text-xs text-gray-400">(<?php echo htmlspecialchars($b['purchase_order_id']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-gray-500"><?php echo htmlspecialchars($b['location_name'] ?? '—'); ?></td>
                                <td class="py-3 px-4 text-right font-mono font-semibold text-gray-800"><?php echo number_format($b['quantity']); ?></td>
                                <td class="py-3 px-4 text-center <?php echo $expiryClass; ?>">
                                    <?php echo $b['expiry_date'] ? date('M j, Y', strtotime($b['expiry_date'])) : '—'; ?>
                                    <?php if ($isExpired): ?><span class="ml-1 text-xs bg-red-100 text-red-600 px-1 py-0.5 rounded">Expired</span><?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center text-gray-500">
                                    <?php echo $b['received_date'] ? date('M j, Y', strtotime($b['received_date'])) : '—'; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex justify-center gap-2 flex-wrap">
                                        <?php if ($filter === 'Active'): ?>
                                            <form action="batch_quarantine_handler.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="batch_id" value="<?php echo (int)$b['batch_id']; ?>">
                                                <input type="hidden" name="action" value="quarantine">
                                                <button type="submit"
                                                        onclick="return confirm('Quarantine batch #<?php echo (int)$b['batch_id']; ?>? It will be excluded from stock counts until released.');"
                                                        class="text-xs bg-yellow-100 hover:bg-yellow-200 text-yellow-700 font-semibold px-3 py-1 rounded-full transition-colors">
                                                    Quarantine
                                                </button>
                                            </form>
                                        <?php elseif ($filter === 'Quarantined'): ?>
                                            <form action="batch_quarantine_handler.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="batch_id" value="<?php echo (int)$b['batch_id']; ?>">
                                                <input type="hidden" name="action" value="release">
                                                <button type="submit"
                                                        class="text-xs bg-green-100 hover:bg-green-200 text-green-700 font-semibold px-3 py-1 rounded-full transition-colors">
                                                    Release
                                                </button>
                                            </form>
                                            <form action="batch_quarantine_handler.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="batch_id" value="<?php echo (int)$b['batch_id']; ?>">
                                                <input type="hidden" name="action" value="writeoff">
                                                <button type="submit"
                                                        onclick="return confirm('Write off batch #<?php echo (int)$b['batch_id']; ?>? Stock will be zeroed and logged.');"
                                                        class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1 rounded-full transition-colors">
                                                    Write-off
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">No actions</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
$conn->close();
require_once 'footer.php';
