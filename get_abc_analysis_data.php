<?php
// Filename: smart/get_abc_analysis_data.php
// Purpose: Calculates Annual Consumption Value (ACV) for all items and performs ABC classification.

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Security check: Admins, Procurement, and Warehouse staff can view this report
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// Define the period for annual usage (365 days)
$period_days = 365;
$one_year_ago = date('Y-m-d', strtotime("-$period_days days"));

// Fetch item data including historical usage
$sql = "
    SELECT 
        i.item_id, i.name, i.item_code, i.unit_cost,
        COALESCE(SUM(t.quantity_used), 0) AS total_usage_year
    FROM 
        items i
    LEFT JOIN 
        transactions t ON i.item_id = t.item_id AND t.transaction_date >= ?
    GROUP BY 
        i.item_id
    HAVING
        i.unit_cost > 0.00
    ORDER BY 
        i.item_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $one_year_ago);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$total_acv = 0;

// 1. Calculate ACV and Total ACV
while ($row = $result->fetch_assoc()) {
    $unit_cost = (float)$row['unit_cost'];
    $usage = (int)$row['total_usage_year'];

    // Annual Consumption Value (ACV) = Annual Usage * Unit Cost
    $acv = $usage * $unit_cost;

    // Only include items with positive ACV for classification
    if ($acv > 0) {
        $items[] = [
            'item_id' => $row['item_id'],
            'item_code' => htmlspecialchars($row['item_code']),
            'item_name' => htmlspecialchars($row['name']),
            'unit_cost' => number_format($unit_cost, 2),
            'usage' => $usage,
            'acv' => $acv,
            'percentage_items' => 0,
            'cumulative_acv_percent' => 0,
            'abc_class' => ''
        ];
        $total_acv += $acv;
    }
}
$stmt->close();

if (empty($items)) {
    echo json_encode(['error' => 'No items with positive consumption value found over the last year.']);
    exit();
}

// 2. Sort items by ACV (Highest to Lowest)
usort($items, function($a, $b) {
    return $b['acv'] <=> $a['acv'];
});

// 3. Perform ABC Classification
$cumulative_acv = 0;
$total_items = count($items);
$a_cutoff = 0.80; // 80% of total ACV is Class A
$b_cutoff = 0.95; // 95% of total ACV is Class A + B
$item_count = 0;

foreach ($items as $key => &$item) {
    $cumulative_acv += $item['acv'];
    $cumulative_acv_percent = ($cumulative_acv / $total_acv);

    $item['cumulative_acv_percent'] = round($cumulative_acv_percent * 100, 2);
    $item['percentage_items'] = round((($key + 1) / $total_items) * 100, 2);

    // Classification Logic
    if ($cumulative_acv_percent <= $a_cutoff) {
        $item['abc_class'] = 'A';
    } elseif ($cumulative_acv_percent <= $b_cutoff) {
        $item['abc_class'] = 'B';
    } else {
        $item['abc_class'] = 'C';
    }
}
unset($item); // Break reference

echo json_encode([
    'data' => $items,
    'total_acv' => number_format($total_acv, 2)
]);

$conn->close();
?>
