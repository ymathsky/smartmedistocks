<?php
// Filename: smart/inventory_report_data.php
// Purpose: Calculates key inventory performance metrics (Stock Age and Turnover)
// and returns the results as JSON.

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Security check: Admins, Procurement, and Warehouse staff can view this report
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

$conn->begin_transaction();
$report_data = [];
$period_days = 90; // Calculate turnover based on the last 90 days
$ninety_days_ago = date('Y-m-d', strtotime("-$period_days days"));

try {
    // Fetch all necessary data: Item details, total current stock, total cost, and total usage (COGU)
    $sql = "
        SELECT 
            i.item_id, i.name, i.item_code, i.unit_cost, i.unit_of_measure,
            
            -- Current Stock Value
            COALESCE(SUM(b.quantity * i.unit_cost), 0) AS current_inventory_value,

            -- Current Total Quantity
            COALESCE(SUM(b.quantity), 0) AS current_stock_qty,

            -- Total Cost of Goods Used (COGU) in the last 90 days
            COALESCE(SUM(t.quantity_used * i.unit_cost), 0) AS total_cogu_90_days
        FROM 
            items i
        LEFT JOIN 
            item_batches b ON i.item_id = b.item_id AND b.quantity > 0
        LEFT JOIN 
            transactions t ON i.item_id = t.item_id AND t.transaction_date >= ?
        GROUP BY 
            i.item_id
        ORDER BY 
            i.name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ninety_days_ago);
    $stmt->execute();
    $result = $stmt->get_result();

    $items_without_stock = [];

    while ($item = $result->fetch_assoc()) {
        $item_id = $item['item_id'];
        $current_stock_qty = (int)$item['current_stock_qty'];
        $cogu_90_days = (float)$item['total_cogu_90_days'];
        $current_inventory_value = (float)$item['current_inventory_value'];
        $annualized_cogu = ($cogu_90_days / $period_days) * 365;

        // Skip items with zero stock and zero usage, they clutter the report
        if ($current_stock_qty == 0 && $cogu_90_days == 0) continue;

        // --- 1. Inventory Turnover Calculation ---
        // Turnover = Annualized COGU / Current Inventory Value (proxy for Avg. Inventory)
        if ($current_inventory_value > 0 && $annualized_cogu > 0) {
            $turnover = $annualized_cogu / $current_inventory_value;
            $days_in_inventory = round(365 / $turnover);
        } else {
            $turnover = 0;
            $days_in_inventory = "N/A";
        }

        // --- 2. Average Stock Age Calculation ---
        // This requires a separate subquery as it deals with weighted averages of batch dates.
        $age_sql = "
            SELECT 
                SUM(b.quantity * DATEDIFF(CURDATE(), b.received_date)) / SUM(b.quantity) AS weighted_avg_age
            FROM 
                item_batches b
            WHERE 
                b.item_id = ? AND b.quantity > 0
        ";
        $age_stmt = $conn->prepare($age_sql);
        $age_stmt->bind_param("i", $item_id);
        $age_stmt->execute();
        $age_result = $age_stmt->get_result()->fetch_assoc();
        $age_stmt->close();

        $avg_stock_age = $age_result['weighted_avg_age'] ? round($age_result['weighted_avg_age']) : 0;
        if ($current_stock_qty == 0) $avg_stock_age = 'N/A';

        // --- Compile Report Data ---
        $report_data[] = [
            'item_code' => htmlspecialchars($item['item_code']),
            'item_name' => htmlspecialchars($item['name']),
            'current_stock_qty' => number_format($current_stock_qty),
            'avg_stock_age_days' => $avg_stock_age,
            'turnover_rate' => number_format($turnover, 2),
            'days_in_inventory' => $days_in_inventory
        ];
    }

    echo json_encode(['data' => $report_data]);
    $conn->commit(); // Commit transaction (read-only queries, but good practice)

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>
