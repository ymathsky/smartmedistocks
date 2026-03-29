<?php
// Filename: pharmacist_dashboard.php

// DEFINITIVE FIX: Explicitly bring the global database connection variable
// into this script's scope to resolve the persistent "null" error.
global $conn;

// Security check: Only Admins and Pharmacists can access this dashboard
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: index.php");
    exit();
}

// --- 1. Fetch Global Settings for ROP Calculation ---
$settings_sql = "SELECT setting_name, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
$ordering_cost = $settings['ordering_cost'] ?? 50;
$holding_cost_rate = $settings['holding_cost_rate'] ?? 25;
$service_level = $settings['service_level'] ?? 95;
$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = $z_scores[$service_level] ?? 1.65;
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));


// --- 2. Fetch Data for Critical Stock Alerts (Below ROP) ---
$items_sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id), 0) as current_stock,
        s.average_lead_time_days,
        COALESCE(td.total_usage, 0) as total_usage_90_days,
        COALESCE(td.transaction_days, 0) as transaction_days_90
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) as total_usage, COUNT(DISTINCT transaction_date) as transaction_days
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) as td ON i.item_id = td.item_id
";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

$rop_alerts = [];
while ($item = $items_result->fetch_assoc()) {
    $has_sufficient_data = $item['total_usage_90_days'] > 0 && $item['transaction_days_90'] >= 7;

    if ($has_sufficient_data) {
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $lead_time_days = $item['average_lead_time_days'] ?? 7;

        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;

        $current_stock = (int)$item['current_stock'];

        if ($current_stock <= round($reorder_point)) {
            $rop_alerts[] = [
                'item_code' => htmlspecialchars($item['item_code']),
                'name' => htmlspecialchars($item['name']),
                'current_stock' => $current_stock,
                'reorder_point' => round($reorder_point)
            ];
        }
    }
}
$stmt->close();


// --- 3. Fetch Near-Expiry Alerts (< 60 days) ---
$sixty_days_from_now = date('Y-m-d', strtotime('+60 days'));
$expiry_sql = "
    SELECT i.name, i.item_code, SUM(b.quantity) as total_expiring_qty, MIN(b.expiry_date) as earliest_expiry
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
    WHERE b.expiry_date IS NOT NULL AND b.expiry_date <= ? AND b.quantity > 0
    GROUP BY i.item_id
    ORDER BY earliest_expiry ASC
    LIMIT 5
";
$expiry_stmt = $conn->prepare($expiry_sql);
$expiry_stmt->bind_param("s", $sixty_days_from_now);
$expiry_stmt->execute();
$expiry_result = $expiry_stmt->get_result();
$expiry_alerts = [];
while ($row = $expiry_result->fetch_assoc()) {
    $expiry_alerts[] = $row;
}
$expiry_stmt->close();


// --- 4. Fetch Recent Item Usage (Last 10 Transactions) ---
$recent_usage_sql = "
    SELECT t.transaction_date, i.name, t.quantity_used
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    ORDER BY t.transaction_id DESC
    LIMIT 10
";
$recent_usage_result = $conn->query($recent_usage_sql);


// --- 5. Overall Stock Count KPI ---
$total_stock_sql = "SELECT SUM(quantity) as total_stock_count FROM item_batches";
$total_stock_result = $conn->query($total_stock_sql);
$total_stock_count = $total_stock_result->fetch_assoc()['total_stock_count'] ?? 0;

// --- CHART DATA: Top 5 Used Items (Last 30 days) ---
$top_items_sql = "
    SELECT i.name, SUM(t.quantity_used) as total_usage
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    WHERE t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY t.item_id, i.name
    ORDER BY total_usage DESC
    LIMIT 5
";
$top_items_result = $conn->query($top_items_sql);
$top_items_labels = [];
$top_items_values = [];
if ($top_items_result) {
    while ($row = $top_items_result->fetch_assoc()) {
        $top_items_labels[] = $row['name'];
        $top_items_values[] = $row['total_usage'];
    }
}
$top_items_chart_data = ['labels' => $top_items_labels, 'values' => $top_items_values];

?>

<!-- Main Content -->
<link rel="stylesheet" href="dashboard.css">
<div class="p-5 max-w-screen-2xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-7">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Pharmacist Dashboard</h1>
            <p class="text-xs text-gray-400 mt-0.5"><?php echo date("l, F j, Y"); ?></p>
        </div>
        <a href="record_usage.php" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Record Usage
        </a>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full">Current</span>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_stock_count); ?></p>
            <p class="text-xs text-gray-400 mt-1">Total Stocked Units</p>
        </div>
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <?php echo count($rop_alerts) > 0 ? '<span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-0.5 rounded-full">Reorder</span>' : '<span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-0.5 rounded-full">&#10003; OK</span>'; ?>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo count($rop_alerts); ?></p>
            <p class="text-xs text-gray-400 mt-1">Items Below Reorder Point</p>
        </div>
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <?php echo count($expiry_alerts) > 0 ? '<span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2.5 py-0.5 rounded-full">Monitor</span>' : '<span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-0.5 rounded-full">&#10003; OK</span>'; ?>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo count($expiry_alerts); ?></p>
            <p class="text-xs text-gray-400 mt-1">Items Expiring &lt;60 Days</p>
        </div>
    </div>

    <!-- Chart + Alerts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-7">
        <!-- Top 5 Used Items chart -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="section-title mb-4">Top 5 Most Used Items &mdash; Last 30 Days</p>
            <canvas id="topItemsChart" height="200"></canvas>
        </div>

        <!-- ROP Alerts -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="section-title">Critical Stock Alerts</p>
                <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Below ROP</span>
            </div>
            <?php if (!empty($rop_alerts)): ?>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    <?php foreach($rop_alerts as $a): ?>
                        <div class="flex items-center justify-between bg-red-50 rounded-xl px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate"><?php echo $a['name']; ?></p>
                                <p class="text-xs text-gray-400"><?php echo $a['item_code']; ?></p>
                            </div>
                            <div class="text-right flex-shrink-0 ml-3">
                                <p class="text-xs font-bold text-red-600"><?php echo $a['current_stock']; ?> units</p>
                                <p class="text-xs text-gray-400">ROP: <?php echo $a['reorder_point']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="order_suggestion.php" class="inline-block mt-3 text-xs text-blue-600 hover:underline font-medium">View order suggestions &rarr;</a>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-10">
                    <svg class="w-10 h-10 text-green-200 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-gray-400 text-center">All items above reorder point</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expiry Alerts + Recent Usage -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Near-Expiry -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="section-title">Near-Expiry Alerts</p>
                <span class="text-xs font-semibold bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">Next 60 Days</span>
            </div>
            <?php if (!empty($expiry_alerts)): ?>
                <div class="space-y-2">
                    <?php foreach($expiry_alerts as $a): ?>
                        <div class="flex items-start justify-between gap-2 pb-2 border-b border-gray-50 last:border-0">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($a['name']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo number_format($a['total_expiring_qty']); ?> units expiring</p>
                            </div>
                            <p class="text-xs font-bold text-amber-600 flex-shrink-0"><?php echo date("M j, Y", strtotime($a['earliest_expiry'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-10">
                    <svg class="w-10 h-10 text-green-200 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-gray-400">No expiry alerts in next 60 days</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Usage Table -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-5">
                <p class="section-title">Recent Item Usage</p>
                <a href="transaction_history.php" class="text-xs text-blue-600 hover:underline font-medium">View all &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                            <th class="pb-3 text-left font-semibold tracking-wide">Date</th>
                            <th class="pb-3 text-left font-semibold tracking-wide">Item</th>
                            <th class="pb-3 text-right font-semibold tracking-wide">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    <?php if ($recent_usage_result && $recent_usage_result->num_rows > 0): ?>
                        <?php while($row = $recent_usage_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-2.5 text-xs text-gray-400"><?php echo date("M j, Y", strtotime($row['transaction_date'])); ?></td>
                                <td class="py-2.5 text-sm font-medium text-gray-800"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="py-2.5 text-right text-sm font-bold text-gray-700"><?php echo $row['quantity_used']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="py-6 text-center text-xs text-gray-400">No usage transactions found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const topItemsData = <?php echo json_encode($top_items_chart_data); ?>;
    if (topItemsData.labels.length > 0) {
        new Chart(document.getElementById('topItemsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: topItemsData.labels,
                datasets: [{ data: topItemsData.values, backgroundColor: '#3b82f6', borderRadius: 6, barThickness: 16 }]
            },
            options: {
                indexAxis: 'y', responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }
});
</script>

