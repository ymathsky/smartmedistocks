<?php
// Filename: pharmacist_dashboard.php

// DEFINITIVE FIX: Explicitly bring the global database connection variable
// into this script's scope to resolve the persistent "null" error.
global $conn;

// --- Fetch data for Critical Stock Alerts ---
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
$items_sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost, i.current_stock,
        s.average_lead_time_days,
        COALESCE(td.total_usage, 0) as total_usage_90_days
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) as total_usage
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) as td ON i.item_id = td.item_id
";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

$alerts = [];
while ($item = $items_result->fetch_assoc()) {
    if ($item['total_usage_90_days'] > 0) {
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $lead_time_days = $item['average_lead_time_days'] ?? 7;

        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;

        if ($item['current_stock'] <= $reorder_point) {
            $alerts[] = [
                'item_code' => htmlspecialchars($item['item_code']),
                'name' => htmlspecialchars($item['name']),
                'current_stock' => (int)$item['current_stock'],
                'reorder_point' => round($reorder_point)
            ];
        }
    }
}
$stmt->close();
?>

<!-- Main Content -->
<div class="flex-1 p-6 bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full">
        <h1 class="text-3xl font-bold mb-2 text-gray-800">Pharmacist Dashboard</h1>
        <p class="mb-6 text-gray-600">Welcome! Here is a summary of the current inventory status.</p>

        <!-- Critical Stock Alerts Section -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Critical Stock Alerts</h2>
            <?php if (!empty($alerts)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($alerts as $alert): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow">
                            <div class="font-bold text-lg"><?php echo $alert['name']; ?> (<?php echo $alert['item_code']; ?>)</div>
                            <p>Action required: Stock is at or below reorder point.</p>
                            <div class="mt-2 text-sm">
                                <span>Current Stock: <strong class="font-mono"><?php echo $alert['current_stock']; ?></strong></span> |
                                <span>Reorder Point: <strong class="font-mono"><?php echo $alert['reorder_point']; ?></strong></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow" role="alert">
                    <p class="font-bold">All stock levels are currently sufficient.</p>
                    <p>No items are at or below their reorder points at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Placeholder for other dashboard components -->
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Quick Actions</h2>
            <div class="flex space-x-4">
                <a href="record_usage.php" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-700">Record Item Usage</a>
                <a href="item_management.php" class="bg-gray-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-gray-700">Manage Items</a>
            </div>
        </div>
    </div>
</div>

