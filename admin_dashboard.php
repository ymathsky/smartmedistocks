<?php
// Filename: admin_dashboard.php

// This dashboard provides a high-level overview for administrators.
require_once 'db_connection.php';

// --- KPI Calculations ---

// 1. Total Inventory Value
$value_sql = "
    SELECT SUM(b.quantity * i.unit_cost) as total_value
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
";
$value_result = $conn->query($value_sql);
$total_inventory_value = $value_result->fetch_assoc()['total_value'] ?? 0;

// 2. Count of Items Below Reorder Point (Reusing logic from pharmacist dashboard)
$rop_items_count = 0;
// Fetch settings for calculation
$settings_sql = "SELECT setting_name, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
$service_level = $settings['service_level'] ?? 95;
$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = $z_scores[$service_level] ?? 1.65;
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

$rop_sql = "
    SELECT 
        i.item_id, 
        COALESCE(SUM(b.quantity), 0) as current_stock,
        s.average_lead_time_days,
        COALESCE(td.total_usage, 0) as total_usage_90_days
    FROM items i
    LEFT JOIN item_batches b ON i.item_id = b.item_id
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) as total_usage
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) as td ON i.item_id = td.item_id
    GROUP BY i.item_id, s.average_lead_time_days, td.total_usage
";
$stmt_rop = $conn->prepare($rop_sql);
$stmt_rop->bind_param("s", $ninety_days_ago);
$stmt_rop->execute();
$items_result = $stmt_rop->get_result();
while ($item = $items_result->fetch_assoc()) {
    if ($item['total_usage_90_days'] > 0) {
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $lead_time_days = $item['average_lead_time_days'] ?? 7;
        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;
        if ($item['current_stock'] <= $reorder_point) {
            $rop_items_count++;
        }
    }
}
$stmt_rop->close();


// 3. Count of Slow-Moving Items
$slow_moving_sql = "
    SELECT COUNT(*) as slow_count
    FROM (
        SELECT i.item_id
        FROM items i
        LEFT JOIN (
            SELECT item_id, SUM(quantity_used) as total_usage
            FROM transactions
            WHERE transaction_date >= ?
            GROUP BY item_id
        ) as t ON i.item_id = t.item_id
        WHERE COALESCE(t.total_usage, 0) < 10
    ) as slow_items
";
$stmt_slow = $conn->prepare($slow_moving_sql);
$stmt_slow->bind_param("s", $ninety_days_ago);
$stmt_slow->execute();
$slow_moving_count = $stmt_slow->get_result()->fetch_assoc()['slow_count'] ?? 0;
$stmt_slow->close();


// 4. Fetch Recent Transactions
$recent_transactions_sql = "
    SELECT t.transaction_date, i.name, t.quantity_used
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    ORDER BY t.transaction_id DESC
    LIMIT 5
";
$recent_transactions_result = $conn->query($recent_transactions_sql);

// --- Data for Charts ---

// 1. Inventory Value by Category (for Pie Chart)
$category_value_sql = "
    SELECT i.category, SUM(b.quantity * i.unit_cost) as category_value
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
    GROUP BY i.category
    HAVING category_value > 0
    ORDER BY category_value DESC
";
$category_value_result = $conn->query($category_value_sql);
$category_labels = [];
$category_values = [];
if ($category_value_result) {
    while ($row = $category_value_result->fetch_assoc()) {
        $category_labels[] = $row['category'];
        $category_values[] = $row['category_value'];
    }
}
$category_chart_data = [
    'labels' => $category_labels,
    'values' => $category_values,
];

// 2. Daily Usage Trend (Last 14 days)
$usage_trend_sql = "
    SELECT DATE(transaction_date) as date, SUM(quantity_used) as total_usage
    FROM transactions
    WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(transaction_date)
    ORDER BY date ASC
";
$usage_trend_result = $conn->query($usage_trend_sql);
$usage_labels = [];
$usage_values = [];
// Create a date range to ensure all days are represented, even with 0 usage
$date = new DateTime();
$date->modify('-14 days');
for ($i = 0; $i < 14; $i++) {
    $usage_labels[] = $date->format('M j');
    $usage_values[$date->format('Y-m-d')] = 0;
    $date->modify('+1 day');
}
if ($usage_trend_result) {
    while ($row = $usage_trend_result->fetch_assoc()) {
        $usage_values[$row['date']] = (int)$row['total_usage'];
    }
}
$usage_chart_data = [
    'labels' => $usage_labels,
    'values' => array_values($usage_values),
];

?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Administrator Dashboard</h1>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500">
            <h2 class="text-gray-600 text-sm font-bold">Total Inventory Value</h2>
            <p class="text-3xl font-bold text-gray-800 mt-2">₱<?php echo number_format($total_inventory_value, 2); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-red-500">
            <h2 class="text-gray-600 text-sm font-bold">Items Below Reorder Point</h2>
            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $rop_items_count; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-yellow-500">
            <h2 class="text-gray-600 text-sm font-bold">Slow-Moving Items</h2>
            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $slow_moving_count; ?></p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Inventory Value by Category</h2>
            <canvas id="categoryValueChart"></canvas>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daily Item Usage (Last 14 Days)</h2>
            <canvas id="usageTrendChart"></canvas>
        </div>
    </div>

    <!-- Quick Management Links & Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
        <!-- Quick Management Links -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Management Panels</h2>
            <div class="space-y-3">
                <a href="user_management.php" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="font-semibold text-gray-700">User Management</span>
                </a>
                <a href="item_management.php" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="font-semibold text-gray-700">Item Management</span>
                </a>
                <a href="supplier_management.php" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="font-semibold text-gray-700">Supplier Management</span>
                </a>
                <a href="global_settings.php" class="flex items-center p-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="font-semibold text-gray-700">Global Settings</span>
                </a>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Recent Item Usage</h2>
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
                    <?php if ($recent_transactions_result && $recent_transactions_result->num_rows > 0): ?>
                        <?php while($row = $recent_transactions_result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo date("M j, Y", strtotime($row['transaction_date'])); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-600 text-right font-mono"><?php echo htmlspecialchars($row['quantity_used']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-gray-500">No recent transactions found.</td>
                        </tr>
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
        // Data from PHP
        const categoryData = <?php echo json_encode($category_chart_data); ?>;
        const usageData = <?php echo json_encode($usage_chart_data); ?>;

        // Chart 1: Inventory Value by Category
        if (categoryData.labels.length > 0) {
            const ctxCategory = document.getElementById('categoryValueChart').getContext('2d');
            new Chart(ctxCategory, {
                type: 'pie',
                data: {
                    labels: categoryData.labels,
                    datasets: [{
                        label: 'Inventory Value',
                        data: categoryData.values,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.raw !== null) {
                                        label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' }).format(context.raw);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Chart 2: Daily Usage Trend
        if (usageData.values.length > 0) {
            const ctxUsage = document.getElementById('usageTrendChart').getContext('2d');
            new Chart(ctxUsage, {
                type: 'line',
                data: {
                    labels: usageData.labels,
                    datasets: [{
                        label: 'Units Used',
                        data: usageData.values,
                        fill: true,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    });
</script>
