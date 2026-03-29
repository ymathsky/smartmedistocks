<?php
// Filename: ai_assistant_handler.php

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

// Basic security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['answer' => 'Error: You must be logged in to use the assistant.']);
    exit;
}

// Get the user's message from the POST request
$data = json_decode(file_get_contents('php://input'), true);
$user_message = isset($data['message']) ? strtolower(trim($data['message'])) : '';

if (empty($user_message)) {
    echo json_encode(['answer' => 'I did not receive a message.']);
    exit;
}

// --- Keyword-Based and Data-Driven Response Logic ---

$response = "I'm not sure how to answer that. I can explain terms like 'EOQ', 'Safety Stock', and 'Reorder Point'. I can also identify 'top items' by demand.";

if (strpos($user_message, 'eoq') !== false) {
    $response = "Economic Order Quantity (EOQ) is a formula used to determine the optimal quantity of inventory to order. It balances the cost of holding stock against the cost of ordering it, helping to minimize total inventory costs.";
} elseif (strpos($user_message, 'safety stock') !== false) {
    $response = "Safety Stock is an extra quantity of an item held in inventory to reduce the risk of a stockout. It's used to cover uncertainties in demand and lead time from suppliers.";
} elseif (strpos($user_message, 'reorder point') !== false || strpos($user_message, 'rop') !== false) {
    $response = "The Reorder Point (ROP) is the inventory level at which a new order should be placed. It is calculated as (Average Daily Demand × Lead Time) + Safety Stock. When stock hits this level, it's time to reorder.";
} elseif (strpos($user_message, 'slow-moving') !== false) {
    $response = "Slow-Moving Items are products that have not been sold or used in a significant amount of time (in our case, less than 10 units in the last 90 days). Identifying them helps prevent waste and frees up capital.";
} elseif (strpos($user_message, 'supplier performance') !== false) {
    $response = "Supplier Performance is measured by tracking the variance between the expected delivery date and the actual received date. Consistently late suppliers increase the risk of stockouts, while early suppliers can increase holding costs.";
} elseif (strpos($user_message, 'highest demand') !== false || strpos($user_message, 'top items') !== false || strpos($user_message, 'most used') !== false) {
    // This is a data-driven query
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    $sql = "
        SELECT i.name, SUM(t.quantity_used) as total_usage
        FROM transactions t
        JOIN items i ON t.item_id = i.item_id
        WHERE t.transaction_date >= ?
        GROUP BY t.item_id, i.name
        ORDER BY total_usage DESC
        LIMIT 3
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $thirty_days_ago);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $response = "Based on the last 30 days, the items with the highest demand are:\n";
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $response .= "\n" . $rank . ". " . htmlspecialchars($row['name']) . " (" . $row['total_usage'] . " units used)";
            $rank++;
        }
    } else {
        $response = "I couldn't find any transaction data from the last 30 days to determine the top items.";
    }
    $stmt->close();
}

// Send the response back as JSON
echo json_encode(['answer' => $response]);

$conn->close();
?>

