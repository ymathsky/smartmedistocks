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
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <h1 class="text-3xl font-bold mb-2 text-gray-800">Pharmacist Dashboard</h1>
        <p class="mb-6 text-gray-600">Focus on daily operations: inventory availability, usage tracking, and expiry management.</p>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
                <h2 class="text-gray-600 text-sm font-bold">Total Stocked Units</h2>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_stock_count); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500">
                <h2 class="text-gray-600 text-sm font-bold">Items Below Reorder Point</h2>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo count($rop_alerts); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
                <h2 class="text-gray-600 text-sm font-bold">Items Expiring in <60 Days</h2>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo count($expiry_alerts); ?></p>
            </div>
        </div>

        <!-- Chart: Top 5 Most Used Items -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Top 5 Most Used Items (Last 30 Days)</h2>
            <canvas id="topItemsChart"></canvas>
        </div>

        <!-- Alerts and Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Critical Stock Alerts Section -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Critical Stock Alerts (Below ROP)</h2>
                <?php if (!empty($rop_alerts)): ?>
                    <div class="space-y-4">
                        <?php foreach($rop_alerts as $alert): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow">
                                <div class="font-bold text-lg"><?php echo $alert['name']; ?> (<?php echo $alert['item_code']; ?>)</div>
                                <p class="text-sm">Current Stock: **<?php echo $alert['current_stock']; ?>** | ROP: **<?php echo $alert['reorder_point']; ?>**</p>
                                <div class="mt-2 text-sm">
                                    <a href="item_management.php" class="text-red-600 hover:underline font-semibold">Manage Item Details &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow" role="alert">
                        <p class="font-bold">All items are currently above their reorder points.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Near-Expiry Alerts Section -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Near-Expiry Alerts (Next 60 Days)</h2>
                <?php if (!empty($expiry_alerts)): ?>
                    <div class="space-y-4">
                        <?php foreach($expiry_alerts as $alert): ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow">
                                <div class="font-bold text-lg"><?php echo htmlspecialchars($alert['name']); ?></div>
                                <p class="text-sm">
                                    **<?php echo number_format($alert['total_expiring_qty']); ?> units** expiring earliest on **<?php echo date("M j, Y", strtotime($alert['earliest_expiry'])); ?>**.
                                </p>
                                <div class="mt-2 text-sm">
                                    <a href="item_management.php" class="text-yellow-600 hover:underline font-semibold">Prioritize Usage / Manage &rarr;</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow" role="alert">
                        <p class="font-bold">No items are set to expire in the next 60 days.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Recent Usage Section -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Recent Item Usage</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 uppercase">Quantity Used</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    <?php if ($recent_usage_result && $recent_usage_result->num_rows > 0): ?>
                        <?php while($row = $recent_usage_result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo date("M j, Y", strtotime($row['transaction_date'])); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-600 text-right font-mono"><?php echo htmlspecialchars($row['quantity_used']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-gray-500">No recent usage transactions found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end mt-4">
                <a href="record_usage.php" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-700">Record New Usage</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const topItemsData = <?php echo json_encode($top_items_chart_data); ?>;

        if (topItemsData.labels.length > 0) {
            const ctxTopItems = document.getElementById('topItemsChart').getContext('2d');
            new Chart(ctxTopItems, {
                type: 'bar',
                data: {
                    labels: topItemsData.labels,
                    datasets: [{
                        label: 'Total Units Used',
                        data: topItemsData.values,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: { display: true, text: 'Units Used' }
                        }
                    }
                }
            });
        }
    });
</script>
