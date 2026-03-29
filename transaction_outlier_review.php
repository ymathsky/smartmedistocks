<?php
// Filename: transaction_outlier_review.php
// Purpose: Identify statistically suspicious transactions (likely data-entry errors)
//          so Admins can review and correct them before they skew demand forecasts.

require_once 'header.php';
require_once 'db_connection.php';

// Admin-only — corrections must be authorised
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "Only Admins can access the Data Correction Review tool.";
    header("Location: index.php");
    exit();
}

// -----------------------------------------------------------------------
// Pull every transaction with its item stats in one pass.
// We flag a row when its quantity_used is more than 2 standard deviations
// away from that item's mean — a standard statistical outlier test.
// Items with fewer than 3 transactions are excluded (too little data to judge).
// -----------------------------------------------------------------------
$sql = "
    SELECT
        t.transaction_id,
        t.item_id,
        t.quantity_used,
        t.transaction_date,
        i.name      AS item_name,
        i.item_code,
        stats.avg_qty,
        stats.std_qty,
        stats.tx_count
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    JOIN (
        SELECT
            item_id,
            COUNT(*)            AS tx_count,
            AVG(quantity_used)  AS avg_qty,
            STDDEV(quantity_used) AS std_qty
        FROM transactions
        GROUP BY item_id
        HAVING COUNT(*) >= 3
    ) stats ON t.item_id = stats.item_id
    WHERE stats.std_qty > 0
      AND ABS(t.quantity_used - stats.avg_qty) > (2 * stats.std_qty)
    ORDER BY ABS(t.quantity_used - stats.avg_qty) / stats.std_qty DESC
";

$result = $conn->query($sql);
$outliers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['z_score'] = ($row['std_qty'] > 0)
            ? round(abs($row['quantity_used'] - $row['avg_qty']) / $row['std_qty'], 1)
            : 0;
        $row['normal_min'] = max(1, round($row['avg_qty'] - 2 * $row['std_qty']));
        $row['normal_max'] = round($row['avg_qty'] + 2 * $row['std_qty']);
        $outliers[] = $row;
    }
}
?>

<div class="p-6 max-w-6xl mx-auto">

    <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transaction Data Correction Review</h1>
            <p class="text-sm text-gray-500 mt-1">
                Transactions flagged as statistical outliers (>2 standard deviations from the item's mean).
                These are <strong>likely data-entry errors</strong> that inflate MAPE. Edit only genuine mistakes.
            </p>
        </div>
        <a href="transaction_history.php" class="text-sm text-blue-600 hover:underline">&larr; Back to Transaction History</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-lg p-4 mb-5">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-800 rounded-lg p-4 mb-5">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- How this works -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-900">
        <p class="font-bold mb-1">📊 How outliers are detected</p>
        <p>For each item, the system calculates the average and standard deviation of all recorded quantities.
           Any single transaction more than <strong>2 standard deviations (z-score &gt; 2)</strong> from the average is flagged.
           A high z-score means the quantity was unusually large or small compared to all other records for that item —
           common signs of a typo (e.g. entering <em>1000</em> instead of <em>100</em>).</p>
        <p class="mt-2 text-blue-700 font-medium">⚠ Only correct genuine data-entry mistakes. All edits are audit-logged and require a written reason.</p>
    </div>

    <?php if (empty($outliers)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 mx-auto mb-3 text-green-400"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-lg font-semibold text-gray-700">No outliers found</p>
            <p class="text-sm text-gray-400 mt-1">
                All transaction quantities are within normal range. No corrections needed.
            </p>
        </div>
    <?php else: ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <span class="font-semibold text-gray-700">
                    <?php echo count($outliers); ?> flagged transaction<?php echo count($outliers) !== 1 ? 's' : ''; ?>
                </span>
                <span class="text-xs text-gray-400">Sorted by highest z-score first</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" id="outlierTable">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Item</th>
                            <th class="px-4 py-3 text-right">Recorded Qty</th>
                            <th class="px-4 py-3 text-right">Item Average</th>
                            <th class="px-4 py-3 text-right">Normal Range</th>
                            <th class="px-4 py-3 text-center">Z-Score</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($outliers as $row): ?>
                            <?php
                                $z = $row['z_score'];
                                if ($z >= 5)      { $zbg = 'bg-red-100 text-red-700'; $severity = 'Very High'; }
                                elseif ($z >= 3)  { $zbg = 'bg-orange-100 text-orange-700'; $severity = 'High'; }
                                else              { $zbg = 'bg-yellow-100 text-yellow-700'; $severity = 'Moderate'; }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    <?php echo htmlspecialchars($row['transaction_date']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['item_code']); ?> &bull; <?php echo (int)$row['tx_count']; ?> total records</div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-red-600 text-base">
                                    <?php echo number_format($row['quantity_used']); ?>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">
                                    <?php echo number_format($row['avg_qty'], 1); ?>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 whitespace-nowrap">
                                    <?php echo $row['normal_min']; ?> – <?php echo number_format($row['normal_max'], 0); ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold <?php echo $zbg; ?>">
                                        <?php echo $z; ?>σ &nbsp;<?php echo $severity; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="edit_transaction.php?id=<?php echo (int)$row['transaction_id']; ?>"
                                       class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Correct
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
