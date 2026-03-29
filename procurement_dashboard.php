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
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <h1 class="text-3xl font-bold mb-2 text-gray-800">Procurement Dashboard</h1>
        <p class="mb-8 text-gray-600">Analytics on inventory status, supplier performance, and items requiring action.</p>

        <!-- Chart: PO Status Breakdown -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Purchase Order Status Overview</h2>
            <div class="max-w-md mx-auto">
                <canvas id="poStatusChart"></canvas>
            </div>
        </div>

        <!-- Section 1: Items Requiring Reorder -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Items Requiring Reorder (Below ROP)</h2>
            <?php if (!empty($reorder_alerts)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($reorder_alerts as $alert): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 rounded-lg shadow">
                            <div class="font-bold text-lg"><?php echo $alert['item_name']; ?> (<?php echo $alert['item_code']; ?>)</div>
                            <p>Current Stock is **<?php echo $alert['current_stock']; ?>** vs ROP of **<?php echo $alert['reorder_point']; ?>**.</p>
                            <div class="mt-2 text-sm">
                                <a href="order_suggestion.php" class="text-red-600 hover:underline font-semibold">View Order Suggestion &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow"><p>No items are currently below their reorder point.</p></div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Section 2: Supplier Lead Time Performance -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Supplier Performance</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Supplier</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">Avg. Delivery Variance</th>
                        </tr>
                        </thead>
                        <tbody class="text-gray-700">
                        <?php if ($supplier_performance_result && $supplier_performance_result->num_rows > 0): ?>
                            <?php while($row = $supplier_performance_result->fetch_assoc()):
                                $variance = round($row['avg_delivery_variance']);
                                $color_class = $variance > 2 ? 'text-red-600' : ($variance < -2 ? 'text-green-600' : '');
                                $text = $variance > 0 ? "$variance days late" : abs($variance)." days early";
                                if ($variance == 0) $text = "On time";
                                ?>
                                <tr>
                                    <td class="text-left py-3 px-4"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="text-center py-3 px-4 font-bold <?php echo $color_class; ?>"><?php echo $text; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center py-4">No supplier delivery data available.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: Slow-Moving Items -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Slow-Moving Items</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Item</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">Units Used (90 Days)</th>
                        </tr>
                        </thead>
                        <tbody class="text-gray-700">
                        <?php if ($slow_moving_result && $slow_moving_result->num_rows > 0): ?>
                            <?php while($row = $slow_moving_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-left py-3 px-4"><?php echo htmlspecialchars($row['name']); ?> <span class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($row['item_code']); ?></span></td>
                                    <td class="text-center py-3 px-4 font-bold"><?php echo (int)$row['usage_90_days']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center py-4">No slow-moving items found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const poStatusData = <?php echo json_encode($po_status_chart_data); ?>;

        if (poStatusData.labels.length > 0) {
            const ctxPOStatus = document.getElementById('poStatusChart').getContext('2d');
            new Chart(ctxPOStatus, {
                type: 'doughnut',
                data: {
                    labels: poStatusData.labels,
                    datasets: [{
                        label: 'PO Status',
                        data: poStatusData.values,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)', // Placed
                            'rgba(255, 206, 86, 0.8)', // Shipped
                            'rgba(75, 192, 192, 0.8)', // Received
                            'rgba(255, 99, 132, 0.8)', // Cancelled
                            'rgba(153, 102, 255, 0.8)',// Draft
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }
    });
</script>
