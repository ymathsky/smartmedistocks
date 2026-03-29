<?php
// Filename: smart/ai_assistant_handler.php

header('Content-Type: application/json');
require_once 'db_connection.php'; // Ensure connection is established first
require_once 'config.php';         // API keys — gitignored, never committed
session_start();

/**
 * Calls the Gemini API using PHP cURL with Google Search grounding.
 * @param string $prompt The user's query.
 * @return array The decoded JSON response from the API.
 */
function call_gemini_api($prompt) {
    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'tools' => [['google_search' => new stdClass()]], // Enable search grounding
        'systemInstruction' => [
            'parts' => [['text' => 'You are an expert inventory management and pharmacy supply chain consultant. Keep all answers short and direct — 1 to 3 sentences maximum unless a list is truly needed. Format responses in basic HTML using <strong>, <ul>, <li>, and <br> only. Never use asterisks (*) or markdown. Never give long explanations. No preamble, no filler sentences. Just the answer.']]
        ],
    ];

    $ch = curl_init();
    $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code != 200 || $curl_error) {
        error_log("Gemini API Error: HTTP $http_code. cURL Error: $curl_error. Response: $response");
        return ['error' => true, 'message' => "Error contacting the AI service (HTTP $http_code)."];
    }

    return json_decode($response, true);
}

/**
 * Logs a chat message to the database.
 * @param mysqli $conn Database connection object.
 * @param int $userId The ID of the logged-in user.
 * @param string $sender 'user' or 'ai'.
 * @param string $message The message content.
 * @return bool True on success, false on failure.
 */
function log_chat_message($conn, $userId, $sender, $message) {
    $log_sql = "INSERT INTO chat_log (user_id, sender, message) VALUES (?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $log_stmt->bind_param("iss", $userId, $sender, $message);
        $success = $log_stmt->execute();
        $log_stmt->close();
        if (!$success) {
            error_log("Failed to log chat message: " . $conn->error); // Log DB error
        }
        return $success;
    } else {
        error_log("Failed to prepare chat log statement: " . $conn->error); // Log DB error
        return false;
    }
}

/**
 * Fetches the chat history for a user from the database.
 * @param mysqli $conn Database connection object.
 * @param int $userId The ID of the logged-in user.
 * @return array An array of chat messages.
 */
function get_chat_history($conn, $userId) {
    $history = [];
    $sql = "SELECT sender, message, created_at FROM chat_log WHERE user_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Sanitize output for security
            $row['sender'] = htmlspecialchars($row['sender']);
            $row['message'] = $row['message']; // Allow HTML for AI responses
            if ($row['sender'] == 'user') {
                $row['message'] = htmlspecialchars($row['message']);
            }
            $history[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare chat history statement: " . $conn->error);
    }
    return $history;
}


// Basic security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Error: You must be logged in to use the assistant.']);
    exit;
}
$user_id = $_SESSION["user_id"]; // Get user ID from session

// Get the request data and determine the action
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'send_message'; // Default to sending a message if no action is specified

// --- ROUTING BASED ON ACTION ---

if ($action === 'get_history') {
    // --- ACTION: FETCH CHAT HISTORY ---
    $history = get_chat_history($conn, $user_id);
    echo json_encode(['history' => $history]);

} elseif ($action === 'send_message') {
    // --- ACTION: SEND A NEW MESSAGE ---
    $user_message_raw = trim($data['message'] ?? '');
    if (empty($user_message_raw)) {
        echo json_encode(['answer' => 'I did not receive a message.']);
        exit;
    }

    // Log the incoming user message
    log_chat_message($conn, $user_id, 'user', $user_message_raw);

    $user_message = strtolower($user_message_raw);

    // --- CONFIGURATION & CALCULATION FUNCTIONS (for internal queries) ---
    $settings_sql = "SELECT setting_name, setting_value FROM settings";
    $settings_result = $conn->query($settings_sql);
    $settings = [];
    while($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    $ordering_cost = isset($settings['ordering_cost']) ? (float)$settings['ordering_cost'] : 50;
    $holding_cost_rate = isset($settings['holding_cost_rate']) ? (float)$settings['holding_cost_rate'] : 25;
    $service_level = $settings['service_level'] ?? 95;
    $z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
    $z_score = $z_scores[$service_level] ?? 1.65;
    $ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

    function calculate_inventory_metrics($conn, $itemId, $zScore, $orderingCost, $holdingCostRate, $ninetyDaysAgo) {
        $item_sql = "
            SELECT
                i.name, i.item_code, i.unit_cost,
                COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND status = 'Active'), 0) as current_stock,
                s.average_lead_time_days,
                COALESCE(td.total_usage, 0) as total_usage_90_days,
                COALESCE(td.transaction_days, 0) as transaction_days_90
            FROM items i
            LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
            LEFT JOIN (
                SELECT item_id, SUM(quantity_used) as total_usage, COUNT(DISTINCT transaction_date) as transaction_days
                FROM transactions
                WHERE transaction_date >= ? AND item_id = ?
                GROUP BY item_id
            ) as td ON i.item_id = td.item_id
            WHERE i.item_id = ?
        ";
        $stmt = $conn->prepare($item_sql);
        $stmt->bind_param("sii", $ninetyDaysAgo, $itemId, $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item || $item['total_usage_90_days'] == 0 || $item['transaction_days_90'] < 7) {
            return ['error' => 'Insufficient data for analysis.'];
        }

        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $annual_demand = $avg_daily_demand * 365;
        $lead_time_days = $item['average_lead_time_days'] ? (int)$item['average_lead_time_days'] : 7;
        $unit_cost = (float)$item['unit_cost'];
        $holding_cost_per_unit = $unit_cost * ($holdingCostRate / 100);
        $eoq = ($holding_cost_per_unit > 0) ? sqrt((2 * $annual_demand * $orderingCost) / $holding_cost_per_unit) : 0;
        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $zScore * $std_dev_daily_demand * sqrt($lead_time_days);
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;

        return [
            'name' => htmlspecialchars($item['name']),
            'current_stock' => (int)$item['current_stock'],
            'reorder_point' => round($reorder_point),
            'eoq' => round($eoq),
            'avg_daily_demand' => $avg_daily_demand,
            'days_of_stock' => $avg_daily_demand > 0 ? (int)floor($item['current_stock'] / $avg_daily_demand) : 999
        ];
    }

    // --- KEYWORD AND DATA-DRIVEN RESPONSE LOGIC ---
    $response = '';
    $is_internal_query = false;

    if (strpos($user_message, 'total stock value') !== false || strpos($user_message, 'total inventory value') !== false) {
        $is_internal_query = true;
        $value_sql = "SELECT SUM(b.quantity * i.unit_cost) as total_value FROM item_batches b JOIN items i ON b.item_id = i.item_id";
        $value_result = $conn->query($value_sql);
        $total_inventory_value = $value_result->fetch_assoc()['total_value'] ?? 0;
        $response = "The total current inventory value across all items is <strong>₱" . number_format($total_inventory_value, 2) . "</strong>.";

    } elseif (strpos($user_message, 'inventory costs') !== false || strpos($user_message, 'cost breakdown') !== false || strpos($user_message, 'cost analysis') !== false) {
        $is_internal_query = true;
        $cost_sql = "SELECT i.category, SUM(b.quantity * i.unit_cost) as category_value FROM item_batches b JOIN items i ON b.item_id = i.item_id GROUP BY i.category ORDER BY category_value DESC";
        $cost_result = $conn->query($cost_sql);
        if ($cost_result->num_rows > 0) {
            $response = "Here is the inventory value breakdown by category:<br><br><ul>";
            while ($row = $cost_result->fetch_assoc()) {
                $response .= "<li><strong>" . htmlspecialchars($row['category']) . ":</strong> ₱" . number_format($row['category_value'], 2) . "</li>";
            }
            $response .= "</ul>";
        } else {
            $response = "I couldn't find any inventory data to generate a cost breakdown report.";
        }
    } elseif (strpos($user_message, 'calculate the eoq for') !== false || strpos($user_message, 'eoq for') !== false) {
        $is_internal_query = true;
        $prompt_parts = explode(' ', $user_message);
        $item_name_guess = '';
        $key_index = array_search('for', $prompt_parts);
        if ($key_index !== false && isset($prompt_parts[$key_index + 1])) {
            $item_name_guess = $prompt_parts[$key_index + 1];
        }
        if (empty($item_name_guess)) {
            $response = "Please specify the item name or item code you want the EOQ for (e.g., 'EOQ for Paracetamol').";
        } else {
            $item_search_sql = "SELECT item_id, name FROM items WHERE LOWER(name) LIKE ? OR LOWER(item_code) LIKE ? LIMIT 1";
            $item_search_term = "%" . $item_name_guess . "%";
            $stmt_item = $conn->prepare($item_search_sql);
            $stmt_item->bind_param("ss", $item_search_term, $item_name_guess);
            $stmt_item->execute();
            $item_result = $stmt_item->get_result();
            if ($item_result->num_rows > 0) {
                $item_row = $item_result->fetch_assoc();
                $metrics = calculate_inventory_metrics($conn, $item_row['item_id'], $z_score, $ordering_cost, $holding_cost_rate, $ninety_days_ago);
                if (isset($metrics['error'])) {
                    $response = "I found " . htmlspecialchars($item_row['name']) . ", but there is " . $metrics['error'];
                } else {
                    $response = "For <strong>" . $metrics['name'] . "</strong>, the Economic Order Quantity (EOQ) is <strong>" . $metrics['eoq'] . " units</strong>. This is the optimal quantity to order to minimize costs, based on an average daily demand of " . round($metrics['avg_daily_demand'], 2) . " units. The recommended reorder point is <strong>" . $metrics['reorder_point'] . " units</strong>.";
                }
            } else {
                $response = "I could not find an item matching '" . htmlspecialchars($item_name_guess) . "'.";
            }
            $stmt_item->close();
        }
    } elseif (strpos($user_message, 'expiring') !== false) {
        $is_internal_query = true;
        $three_months_from_now = date('Y-m-d', strtotime('+3 months'));
        $expiry_sql = "SELECT i.name, i.item_code, SUM(b.quantity) as total_expiring_qty, MIN(b.expiry_date) as earliest_expiry FROM item_batches b JOIN items i ON b.item_id = i.item_id WHERE b.expiry_date IS NOT NULL AND b.expiry_date <= ? AND b.quantity > 0 GROUP BY i.item_id, i.name, i.item_code ORDER BY earliest_expiry ASC LIMIT 10";
        $stmt = $conn->prepare($expiry_sql);
        $stmt->bind_param("s", $three_months_from_now);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = "Here are the items with stock expiring in the next 3 months. You should prioritize these for use or sale:<br><br><ul>";
            while ($row = $result->fetch_assoc()) {
                $response .= "<li><strong>" . htmlspecialchars($row['name']) . "</strong> (" . htmlspecialchars($row['item_code']) . ") has " . $row['total_expiring_qty'] . " units expiring, with the earliest batch expiring on <strong>" . date("F j, Y", strtotime($row['earliest_expiry'])) . "</strong>.</li>";
            }
            $response .= "</ul>";
        } else {
            $response = "Good news! No stock batches are currently set to expire within the next three months.";
        }
        $stmt->close();
    } elseif (strpos($user_message, 'slow-moving') !== false || strpos($user_message, 'not been used') !== false) {
        $is_internal_query = true;
        $slow_moving_sql = "SELECT i.name, i.item_code, COALESCE(SUM(t.quantity_used), 0) AS usage_90_days, COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id AND status = 'Active'), 0) as current_stock FROM items i LEFT JOIN transactions t ON i.item_id = t.item_id AND t.transaction_date >= ? GROUP BY i.item_id, i.name, i.item_code HAVING usage_90_days < 10 AND current_stock > 0 ORDER BY usage_90_days ASC LIMIT 10;";
        $stmt = $conn->prepare($slow_moving_sql);
        $stmt->bind_param("s", $ninety_days_ago);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = "The following items are considered slow-moving because less than 10 units were used in the past 90 days:<br><br><ul>";
            while ($row = $result->fetch_assoc()) {
                $response .= "<li><strong>" . htmlspecialchars($row['name']) . "</strong> (" . htmlspecialchars($row['item_code']) . ") has " . $row['current_stock'] . " units in stock, but only " . $row['usage_90_days'] . " have been used recently.</li>";
            }
            $response .= "</ul><br>You might want to consider clearance or redistribution for these items.";
        } else {
            $response = "I did not find any items that are classified as slow-moving and still have stock.";
        }
        $stmt->close();
    } elseif (strpos($user_message, 'reorder point') !== false || strpos($user_message, 'rop') !== false || strpos($user_message, 'below rop') !== false) {
        $is_internal_query = true;
        $all_items_result = $conn->query("SELECT item_id, name, item_code FROM items");
        $rop_alerts = [];
        while ($item = $all_items_result->fetch_assoc()) {
            $metrics = calculate_inventory_metrics($conn, $item['item_id'], $z_score, $ordering_cost, $holding_cost_rate, $ninety_days_ago);
            if (!isset($metrics['error']) && $metrics['current_stock'] <= $metrics['reorder_point']) {
                $rop_alerts[] = ['name' => htmlspecialchars($item['name']), 'code' => htmlspecialchars($item['item_code']), 'stock' => $metrics['current_stock'], 'rop' => $metrics['reorder_point']];
            }
        }
        if (!empty($rop_alerts)) {
            $response = "There are <strong>" . count($rop_alerts) . " items</strong> that are at or below their reorder point and should be ordered soon:<br><br><ul>";
            foreach ($rop_alerts as $alert) {
                $response .= "<li><strong>" . $alert['name'] . "</strong> (" . $alert['code'] . ") needs to be reordered. Its current stock is " . $alert['stock'] . ", while the reorder point is " . $alert['rop'] . ".</li>";
            }
            $response .= "</ul>";
        } else {
            $response = "Currently, no items are below their calculated Reorder Point (ROP). Stock levels appear to be sufficient.";
        }
    } elseif (strpos($user_message, 'stockout risk') !== false || strpos($user_message, 'expected to run out') !== false || strpos($user_message, 'predict stockout') !== false) {
        $is_internal_query = true;
        $all_items_result = $conn->query("SELECT item_id, name, item_code FROM items");
        $risk_alerts = [];
        while ($item = $all_items_result->fetch_assoc()) {
            $metrics = calculate_inventory_metrics($conn, $item['item_id'], $z_score, $ordering_cost, $holding_cost_rate, $ninety_days_ago);
            if (!isset($metrics['error']) && $metrics['days_of_stock'] <= 7 && $metrics['current_stock'] > 0) {
                $risk_alerts[] = ['name' => htmlspecialchars($item['name']), 'code' => htmlspecialchars($item['item_code']), 'stock' => $metrics['current_stock'], 'days' => $metrics['days_of_stock']];
            }
        }
        if (!empty($risk_alerts)) {
            $response = "I've identified <strong>" . count($risk_alerts) . " items</strong> that are at a high risk of stocking out within a week based on recent usage:<br><br><ul>";
            foreach ($risk_alerts as $alert) {
                $response .= "<li><strong>" . $alert['name'] . "</strong> (" . $alert['code'] . ") has a high risk of stocking out. The current inventory of " . $alert['stock'] . " units is forecasted to last only about <strong>" . $alert['days'] . " more days</strong>.</li>";
            }
            $response .= "</ul>";
        } else {
            $response = "Based on current demand and stock levels, no items are predicted to run out in the next 7 days.";
        }
    } elseif (strpos($user_message, 'highest demand') !== false || strpos($user_message, 'top items') !== false || strpos($user_message, 'most used') !== false) {
        $is_internal_query = true;
        $sql = "SELECT i.name, i.item_code, SUM(t.quantity_used) as total_usage FROM transactions t JOIN items i ON t.item_id = i.item_id WHERE t.transaction_date >= ? GROUP BY t.item_id, i.name, i.item_code ORDER BY total_usage DESC LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $thirty_days_ago);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = "The items with the highest demand over the last 30 days are:<br><br><ol style='list-style-type: decimal; padding-left: 20px;'>";
            while ($row = $result->fetch_assoc()) {
                $response .= "<li><strong>" . htmlspecialchars($row['name']) . "</strong> (" . htmlspecialchars($row['item_code']) . ") with " . $row['total_usage'] . " units used.</li>";
            }
            $response .= "</ol>";
        } else {
            $response = "I couldn't find any transaction data from the last 30 days to determine the top items.";
        }
        $stmt->close();
    } elseif (strpos($user_message, 'eoq') !== false && !$is_internal_query) {
        $is_internal_query = true;
        $response = "<strong>Economic Order Quantity (EOQ)</strong> is a formula used to determine the optimal quantity of inventory to order. It balances the cost of holding stock against the cost of ordering it, helping to minimize total inventory costs.";
    } elseif (strpos($user_message, 'safety stock') !== false && !$is_internal_query) {
        $is_internal_query = true;
        $response = "<strong>Safety Stock</strong> is an extra quantity of an item held in inventory to reduce the risk of a stockout. It's used to cover uncertainties in demand and lead time from suppliers.";
    } elseif (strpos($user_message, 'reorder point') !== false && !$is_internal_query) {
        $is_internal_query = true;
        $response = "The <strong>Reorder Point (ROP)</strong> is the inventory level at which a new order should be placed. It is calculated as (Average Daily Demand × Lead Time) + Safety Stock. When stock hits this level, it's time to reorder.";
    }

    // --- FALLBACK: CALL GEMINI API ---
    if (!$is_internal_query) {
        $api_response = call_gemini_api($user_message_raw);
        if (isset($api_response['error']) && $api_response['error']) {
            $response = "I'm sorry, I couldn't reach the external AI service right now. Please try again later. (Error: " . htmlspecialchars($api_response['message']) . ")";
        } else {
            $raw_text = $api_response['candidates'][0]['content']['parts'][0]['text'] ?? "I received a response from the AI but the content was empty.";
            // Strip any asterisks that may appear despite instructions
            $response = str_replace('*', '', $raw_text);
        }
    } elseif (empty($response)) {
        $response = "I processed your request, but the data was empty. Please ensure your database contains the necessary information.";
    }

    // Log the final AI response
    if (!empty($response)) {
        log_chat_message($conn, $user_id, 'ai', $response);
    } else {
        $error_response = "Sorry, I couldn't generate a response.";
        log_chat_message($conn, $user_id, 'ai', $error_response);
        echo json_encode(['answer' => $error_response]);
        $conn->close();
        exit;
    }

    // Send the final response back to the client
    echo json_encode(['answer' => $response]);

} else {
    // Handle invalid action
    echo json_encode(['error' => 'Invalid action specified.']);
}

$conn->close();
?>
