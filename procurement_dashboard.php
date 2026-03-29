<?php
// Filename: procurement_dashboard.php
global $conn;

// --- 1. Fetch Global Settings for Inventory Policy Calculations ---
$settings_sql = "SELECT setting_name, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

$ordering_cost = isset($settings['ordering_cost']) ? (float)$settings['ordering_cost'] : 50; // S
$holding_cost_rate = isset($settings['holding_cost_rate']) ? (float)$settings['holding_cost_rate'] : 25; // i
$service_level = isset($settings['service_level']) ? (float)$settings['service_level'] : 95; // Z

$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = isset($z_scores[$service_level]) ? $z_scores[$service_level] : 1.65; // Default to 95% if service level is unusual or missing

// --- 2. Fetch data for Items Requiring Reorder (ROP Check) ---
$reorder_alerts = [];
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

$items_sql = "
    SELECT 
        i.item_id, i.name as item_name, i.item_code, i.unit_cost,
        s.average_lead_time_days,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id), 0) as current_stock,
        COALESCE(td.total_usage, 0) as total_usage_90_days,
        COALESCE(td.transaction_days, 0) as transaction_days_90
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT 
            item_id, 
            SUM(quantity_used) as total_usage,
            COUNT(DISTINCT transaction_date) as transaction_days
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) as td ON i.item_id = td.item_id
";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

while ($item = $items_result->fetch_assoc()) {
    $current_stock = (int)$item['current_stock'];
    $has_sufficient_data = $item['total_usage_90_days'] > 0 && $item['transaction_days_90'] >= 7;

    if ($has_sufficient_data) {
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $lead_time_days = $item['average_lead_time_days'] ?? 7;

        // Safety Stock Calculation
        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);

        // Reorder Point Calculation
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;

        // Check if current stock is at or below ROP
        if ($current_stock <= round($reorder_point)) {
            $reorder_alerts[] = [
                'item_name' => htmlspecialchars($item['item_name']),
                'item_code' => htmlspecialchars($item['item_code']),
                'current_stock' => $current_stock,
                'reorder_point' => round($reorder_point)
            ];
        }
    }
}
$stmt->close();

// --- 3. Fetch data for Slow-Moving Items (used less than 10 units in last 90 days) ---
$slow_moving_sql = "
    SELECT i.name, i.item_code, COALESCE(SUM(t.quantity_used), 0) AS usage_90_days
    FROM items i
    LEFT JOIN transactions t ON i.item_id = t.item_id AND t.transaction_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY i.item_id
    HAVING usage_90_days < 10 AND (SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id) > 0
    ORDER BY usage_90_days ASC
    LIMIT 10;
";
$slow_moving_result = $conn->query($slow_moving_sql);

// --- 4. Fetch data for Supplier Lead Time Performance ---
$supplier_performance_sql = "
    SELECT 
        s.name,
        AVG(DATEDIFF(ib.received_date, ib.expected_delivery_date)) as avg_delivery_variance,
        COUNT(ib.batch_id) as total_deliveries
    FROM suppliers s
    JOIN items i ON s.supplier_id = i.supplier_id
    JOIN item_batches ib ON i.item_id = ib.item_id
    WHERE ib.expected_delivery_date IS NOT NULL AND ib.received_date IS NOT NULL
    GROUP BY s.supplier_id
    ORDER BY avg_delivery_variance DESC;
";
$supplier_performance_result = $conn->query($supplier_performance_sql);

// --- CHART DATA: PO Status Breakdown ---
$po_status_sql = "SELECT status, COUNT(po_id) as count FROM purchase_orders GROUP BY status";
$po_status_result = $conn->query($po_status_sql);
$po_status_labels = [];
$po_status_values = [];
if ($po_status_result) {
    while ($row = $po_status_result->fetch_assoc()) {
        $po_status_labels[] = $row['status'];
        $po_status_values[] = $row['count'];
    }
}
$po_status_chart_data = ['labels' => $po_status_labels, 'values' => $po_status_values];
?>

<!-- Main Content -->
<style>
.dash-card { transition: transform 0.18s, box-shadow 0.18s; }
.dash-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,0.08); }
.section-title { font-size: 0.9375rem; font-weight: 700; color: #111827; }
</style>
<div class="p-5 max-w-screen-2xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-7">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Procurement Dashboard</h1>
            <p class="text-xs text-gray-400 mt-0.5"><?php echo date("l, F j, Y"); ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="create_purchase_order.php" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New PO
            </a>
            <a href="po_management.php" class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition">
                View All POs
            </a>
            <a href="order_suggestion.php" class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition">
                Order Suggestions
            </a>
        </div>
    </div>

    <!-- PO Status chart + Reorder Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-7">
        <!-- PO Status Doughnut -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="section-title mb-4">Purchase Order Status</p>
            <div class="flex justify-center">
                <canvas id="poStatusChart" style="max-width:260px;max-height:260px;"></canvas>
            </div>
        </div>

        <!-- Reorder Alerts -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="section-title">Items Below Reorder Point</p>
                <span class="text-xs font-semibold bg-red-100 text-red-600 px-2 py-0.5 rounded-full"><?php echo count($reorder_alerts); ?> items</span>
            </div>
            <?php if (!empty($reorder_alerts)): ?>
                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    <?php foreach($reorder_alerts as $a): ?>
                        <div class="flex items-center justify-between bg-red-50 rounded-xl px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($a['item_name']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($a['item_code']); ?></p>
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
                    <p class="text-xs text-gray-400">No items below reorder point</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Supplier Performance + Slow-Moving Items -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Supplier Performance -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
                <p class="section-title">Supplier Performance</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                            <th class="pb-3 text-left font-semibold tracking-wide">Supplier</th>
                            <th class="pb-3 text-center font-semibold tracking-wide">Delivery Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    <?php if ($supplier_performance_result && $supplier_performance_result->num_rows > 0): ?>
                        <?php while($row = $supplier_performance_result->fetch_assoc()):
                            $variance = round($row['avg_delivery_variance']);
                            $badge_class = $variance > 2 ? 'bg-red-100 text-red-600' : ($variance < -2 ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-600');
                            $text = $variance > 0 ? "+$variance days late" : ($variance < 0 ? abs($variance)." days early" : "On time");
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3 font-medium text-gray-800"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="py-3 text-center">
                                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full <?php echo $badge_class; ?>"><?php echo $text; ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="py-6 text-center text-xs text-gray-400">No delivery data available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Slow-Moving Items -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 bg-amber-500 rounded-full"></div>
                <p class="section-title">Slow-Moving Items</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                            <th class="pb-3 text-left font-semibold tracking-wide">Item</th>
                            <th class="pb-3 text-center font-semibold tracking-wide">Used (90 Days)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    <?php if ($slow_moving_result && $slow_moving_result->num_rows > 0): ?>
                        <?php while($row = $slow_moving_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($row['name']); ?></p>
                                    <p class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($row['item_code']); ?></p>
                                </td>
                                <td class="py-3 text-center font-bold text-amber-600"><?php echo (int)$row['usage_90_days']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="py-6 text-center text-xs text-gray-400">No slow-moving items found.</td></tr>
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
    const poStatusData = <?php echo json_encode($po_status_chart_data); ?>;
    if (poStatusData.labels.length > 0) {
        const palette = ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6'];
        new Chart(document.getElementById('poStatusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: poStatusData.labels,
                datasets: [{ data: poStatusData.values, backgroundColor: palette, borderWidth: 3, borderColor: '#fff' }]
            },
            options: {
                responsive: true, cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 14 } }
                }
            }
        });
    }
});
</script>

