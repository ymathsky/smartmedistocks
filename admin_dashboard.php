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
$sm_days      = max(1, (int)($settings['slow_moving_days']      ?? 90));
$sm_threshold = max(1, (int)($settings['slow_moving_threshold'] ?? 10));
$sm_days_ago  = date('Y-m-d', strtotime("-{$sm_days} days"));
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
        WHERE COALESCE(t.total_usage, 0) < {$sm_threshold}
    ) as slow_items
";
$stmt_slow = $conn->prepare($slow_moving_sql);
$stmt_slow->bind_param("s", $sm_days_ago);
$stmt_slow->execute();
$slow_moving_count = $stmt_slow->get_result()->fetch_assoc()['slow_count'] ?? 0;
$stmt_slow->close();


// 4. Warehouse: Stock by Location
$location_stock_sql = "
    SELECT l.name, SUM(b.quantity) as total_quantity
    FROM locations l
    JOIN item_batches b ON l.location_id = b.location_id
    WHERE b.quantity > 0
    GROUP BY l.location_id
    ORDER BY total_quantity DESC
";
$location_stock_result = $conn->query($location_stock_sql);
$warehouse_locations = [];
if ($location_stock_result) {
    while ($row = $location_stock_result->fetch_assoc()) {
        $warehouse_locations[] = $row;
    }
}

// 5. Warehouse: Near-Expiry Alerts
$thirty_days_from_now = date('Y-m-d', strtotime('+30 days'));
$sixty_days_from_now  = date('Y-m-d', strtotime('+60 days'));
$expiry_critical_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, l.name as location_name
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
    LEFT JOIN locations l ON b.location_id = l.location_id
    WHERE b.expiry_date IS NOT NULL AND b.expiry_date <= ? AND b.quantity > 0
    ORDER BY b.expiry_date ASC LIMIT 10";
$stmt_exp_c = $conn->prepare($expiry_critical_sql);
$stmt_exp_c->bind_param("s", $thirty_days_from_now);
$stmt_exp_c->execute();
$expiry_critical_result = $stmt_exp_c->get_result();
$stmt_exp_c->close();

$expiry_warning_sql = "SELECT i.name, i.item_code, b.quantity, b.expiry_date, l.name as location_name
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
    LEFT JOIN locations l ON b.location_id = l.location_id
    WHERE b.expiry_date > ? AND b.expiry_date <= ? AND b.quantity > 0
    ORDER BY b.expiry_date ASC LIMIT 10";
$stmt_exp_w = $conn->prepare($expiry_warning_sql);
$stmt_exp_w->bind_param("ss", $thirty_days_from_now, $sixty_days_from_now);
$stmt_exp_w->execute();
$expiry_warning_result = $stmt_exp_w->get_result();
$stmt_exp_w->close();

// 6. Fetch Recent Transactions
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

// 3. Monthly Inventory Turnover Trend (Last 6 months)
// Turnover Rate = Monthly COGS / (Current Inventory Value / 12) — an annualised index
$turnover_sql = "
    SELECT
        DATE_FORMAT(t.transaction_date, '%Y-%m') AS month_key,
        DATE_FORMAT(t.transaction_date, '%b %Y') AS month_label,
        SUM(t.quantity_used * i.unit_cost) AS monthly_cogs
    FROM transactions t
    JOIN items i ON t.item_id = i.item_id
    WHERE t.transaction_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
";
$turnover_result = $conn->query($turnover_sql);
$turnover_kv_labels = [];
$turnover_cogs_map  = [];
$turnover_rates_map = [];
$monthly_avg_inv    = $total_inventory_value > 0 ? ($total_inventory_value / 12.0) : 1;
for ($i = 5; $i >= 0; $i--) {
    $m   = new DateTime();
    $m->modify("-{$i} months");
    $key = $m->format('Y-m');
    $turnover_kv_labels[$key] = $m->format('M Y');
    $turnover_cogs_map[$key]  = 0;
    $turnover_rates_map[$key] = 0;
}
if ($turnover_result) {
    while ($row = $turnover_result->fetch_assoc()) {
        $k = $row['month_key'];
        if (isset($turnover_cogs_map[$k])) {
            $cogs = (float)$row['monthly_cogs'];
            $turnover_cogs_map[$k]  = $cogs;
            $turnover_rates_map[$k] = round($cogs / $monthly_avg_inv, 2);
        }
    }
}
$turnover_chart_data = [
    'labels' => array_values($turnover_kv_labels),
    'cogs'   => array_values($turnover_cogs_map),
    'rates'  => array_values($turnover_rates_map),
];

?>
<link rel="stylesheet" href="dashboard.css">

<div class="p-5 max-w-screen-2xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center mb-7 gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Admin Dashboard</h1>
            <p class="text-sm text-gray-400 mt-0.5"><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="user_management.php" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Users
            </a>
            <a href="supplier_management.php" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Suppliers
            </a>
            <a href="item_management.php" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7"/></svg>
                Manage Items
            </a>
            <a href="global_settings.php" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-full">Assets</span>
            </div>
            <p class="text-2xl font-bold text-gray-900">&#8369;<?php echo number_format($total_inventory_value, 2); ?></p>
            <p class="text-xs text-gray-400 mt-1">Total Inventory Value</p>
        </div>
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <?php echo $rop_items_count > 0 ? '<span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-0.5 rounded-full">Action Needed</span>' : '<span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-0.5 rounded-full">&#10003; OK</span>'; ?>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo $rop_items_count; ?></p>
            <p class="text-xs text-gray-400 mt-1">Items Below Reorder Point</p>
        </div>
        <div class="dash-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2.5 py-0.5 rounded-full">Monitor</span>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo $slow_moving_count; ?></p>
            <p class="text-xs text-gray-400 mt-1">Slow-Moving Items</p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-7">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="section-title mb-4">Inventory Value by Category</p>
            <canvas id="categoryValueChart" height="190"></canvas>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="section-title mb-4">Daily Item Usage &mdash; Last 14 Days</p>
            <canvas id="usageTrendChart" height="190"></canvas>
        </div>
    </div>

    <!-- Monthly Inventory Turnover Trend -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-7">
        <p class="section-title mb-1">Monthly Inventory Turnover &mdash; Last 6 Months</p>
        <p class="text-xs text-gray-400 mb-4">Bars = monthly cost of goods dispensed &nbsp;|&nbsp; Line = turnover rate (monthly COGS &divide; avg. monthly inventory value)</p>
        <canvas id="turnoverTrendChart" height="90"></canvas>
    </div>

    <!-- Warehouse Overview -->
    <div class="mb-7">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
            <h2 class="section-title">Warehouse Overview</h2>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Stock by Location -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <p class="section-title mb-4">Stock by Location</p>
                <?php if (!empty($warehouse_locations)): ?>
                    <?php $max_loc = max(array_column($warehouse_locations, 'total_quantity')); ?>
                    <div class="space-y-3">
                        <?php foreach ($warehouse_locations as $loc): ?>
                            <?php $pct = $max_loc > 0 ? round(($loc['total_quantity'] / $max_loc) * 100) : 0; ?>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs font-medium text-gray-700 truncate max-w-[140px]"><?php echo htmlspecialchars($loc['name']); ?></span>
                                    <span class="text-xs font-bold text-blue-700"><?php echo number_format($loc['total_quantity']); ?></span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-1.5 bg-blue-400 rounded-full" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-gray-400">No location data.</p>
                <?php endif; ?>
            </div>

            <!-- Critical Expiry -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="section-title">Expiring &lt;30 Days</p>
                    <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Critical</span>
                </div>
                <?php $exp_c_rows = []; if ($expiry_critical_result) { while ($r = $expiry_critical_result->fetch_assoc()) $exp_c_rows[] = $r; } ?>
                <?php if (!empty($exp_c_rows)): ?>
                    <div class="space-y-2">
                        <?php foreach ($exp_c_rows as $r): ?>
                            <div class="flex items-start justify-between gap-2 pb-2 border-b border-gray-50 last:border-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($r['name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($r['item_code']); ?> &mdash; <?php echo htmlspecialchars($r['location_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-bold text-red-600"><?php echo date("M j", strtotime($r['expiry_date'])); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $r['quantity']; ?> units</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-5 text-center">
                        <svg class="w-8 h-8 text-green-200 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-gray-400">No critical expiry alerts</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Warning Expiry -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="section-title">Expiring 30&ndash;60 Days</p>
                    <span class="text-xs font-semibold bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">Warning</span>
                </div>
                <?php $exp_w_rows = []; if ($expiry_warning_result) { while ($r = $expiry_warning_result->fetch_assoc()) $exp_w_rows[] = $r; } ?>
                <?php if (!empty($exp_w_rows)): ?>
                    <div class="space-y-2">
                        <?php foreach ($exp_w_rows as $r): ?>
                            <div class="flex items-start justify-between gap-2 pb-2 border-b border-gray-50 last:border-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($r['name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($r['item_code']); ?> &mdash; <?php echo htmlspecialchars($r['location_name'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-bold text-amber-600"><?php echo date("M j", strtotime($r['expiry_date'])); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $r['quantity']; ?> units</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-5 text-center">
                        <svg class="w-8 h-8 text-green-200 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-gray-400">No expiry warnings</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
                <p class="section-title">Recent Item Usage</p>
            </div>
            <a href="transaction_history.php" class="text-xs text-blue-600 hover:underline font-medium">View all &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                        <th class="pb-3 text-left font-semibold tracking-wide">Date</th>
                        <th class="pb-3 text-left font-semibold tracking-wide">Item</th>
                        <th class="pb-3 text-right font-semibold tracking-wide">Qty Used</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if ($recent_transactions_result && $recent_transactions_result->num_rows > 0): ?>
                    <?php while($row = $recent_transactions_result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-3 text-xs text-gray-400"><?php echo date("M j, Y", strtotime($row['transaction_date'])); ?></td>
                            <td class="py-3 text-sm font-medium text-gray-800"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="py-3 text-right text-sm font-bold text-gray-700 font-mono"><?php echo $row['quantity_used']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="py-6 text-center text-xs text-gray-400">No recent transactions.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="dashboard.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryData = <?php echo json_encode($category_chart_data); ?>;
    const usageData    = <?php echo json_encode($usage_chart_data); ?>;
    const palette = ['#3b82f6','#06b6d4','#8b5cf6','#f59e0b','#10b981','#ef4444','#f97316','#ec4899'];

    if (categoryData.labels.length > 0) {
        new Chart(document.getElementById('categoryValueChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{ data: categoryData.values, backgroundColor: palette, borderWidth: 3, borderColor: '#fff' }]
            },
            options: {
                responsive: true, cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11, family: 'Inter' }, padding: 14 } },
                    tooltip: { callbacks: { label: function(c) { return ' ₱' + Number(c.raw).toLocaleString('en-PH', {minimumFractionDigits:2}); } } }
                }
            }
        });
    }
    if (usageData.labels.length > 0) {
        new Chart(document.getElementById('usageTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: usageData.labels,
                datasets: [{ label: 'Units Used', data: usageData.values, fill: true, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.07)', tension: 0.4, pointRadius: 3, pointBackgroundColor: '#3b82f6' }]
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

    const turnoverData = <?php echo json_encode($turnover_chart_data); ?>;
    if (turnoverData.labels.length > 0) {
        new Chart(document.getElementById('turnoverTrendChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: turnoverData.labels,
                datasets: [
                    {
                        label: 'Monthly COGS (\u20b1)',
                        data: turnoverData.cogs,
                        backgroundColor: 'rgba(59,130,246,0.15)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        yAxisID: 'y',
                        order: 2,
                    },
                    {
                        label: 'Turnover Rate (x)',
                        data: turnoverData.rates,
                        type: 'line',
                        borderColor: '#10b981',
                        backgroundColor: 'transparent',
                        borderWidth: 2.5,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#10b981',
                        yAxisID: 'y1',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11, family: 'Inter' }, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                if (c.datasetIndex === 0) return ' COGS: \u20b1' + Number(c.raw).toLocaleString('en-PH', {minimumFractionDigits:2});
                                return ' Turnover Rate: ' + c.raw + 'x';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 }, callback: v => '\u20b1' + Number(v).toLocaleString('en-PH', {minimumFractionDigits:0}) }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 11 }, callback: v => v + 'x' }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }
});
</script>

