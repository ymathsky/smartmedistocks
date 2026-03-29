<?php
// Filename: public_chat_handler.php
// Public endpoint - NO login required.
// ONLY answers medicine availability questions. Does NOT expose internal data.

ob_start();
header('Content-Type: application/json');
require_once 'db_connection.php';

// --- Basic rate limiting via session (1 session = max 30 queries) ---
session_start();
if (!isset($_SESSION['public_chat_count'])) {
    $_SESSION['public_chat_count'] = 0;
}
if ($_SESSION['public_chat_count'] >= 30) {
    ob_end_clean();
    echo json_encode(['reply' => 'You have reached the maximum number of queries for this session. Please try again later.']);
    exit();
}
$_SESSION['public_chat_count']++;

// --- Input validation ---
$input = json_decode(file_get_contents('php://input'), true);
$query = isset($input['query']) ? trim($input['query']) : '';

if (strlen($query) < 2 || strlen($query) > 200) {
    ob_end_clean();
    echo json_encode(['reply' => 'Please enter a valid medicine name (2–200 characters).']);
    exit();
}

// Sanitize: strip HTML/script tags
$query = strip_tags($query);

// --- Search items by name or item_code (partial match, case-insensitive) ---
$search = '%' . $conn->real_escape_string($query) . '%';

$sql = "
    SELECT
        i.item_id,
        i.name,
        i.item_code,
        i.category,
        COALESCE(SUM(b.quantity), 0) AS current_stock
    FROM items i
    LEFT JOIN item_batches b ON i.item_id = b.item_id
    WHERE i.name LIKE ? OR i.item_code LIKE ?
    GROUP BY i.item_id, i.name, i.item_code, i.category
    ORDER BY i.name ASC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$matches = [];
while ($row = $result->fetch_assoc()) {
    $matches[] = $row;
}
$stmt->close();
$conn->close();

// --- Build response ---
if (empty($matches)) {
    $reply = "Sorry, we could not find any medicine matching <strong>" . htmlspecialchars($query) . "</strong> in our inventory. Please check the spelling or try a different name.";
} elseif (count($matches) === 1) {
    $item  = $matches[0];
    $stock = (int)$item['current_stock'];
    $name  = htmlspecialchars($item['name']);
    $code  = htmlspecialchars($item['item_code']);
    if ($stock > 10) {
        $status = "<span style='color:#16a34a;font-weight:600;'>&#10003; Available</span>";
        $detail = "We currently have this medicine in stock.";
    } elseif ($stock > 0) {
        $status = "<span style='color:#ca8a04;font-weight:600;'>&#9888; Limited Stock</span>";
        $detail = "This medicine is available but in limited quantity. Please visit soon.";
    } else {
        $status = "<span style='color:#dc2626;font-weight:600;'>&#10007; Out of Stock</span>";
        $detail = "This medicine is currently unavailable. Please check back later or ask our staff for alternatives.";
    }
    $reply = "<strong>{$name}</strong> ({$code}) &mdash; {$status}<br><small style='color:#6b7280;'>{$detail}</small>";
} else {
    // Multiple matches
    $reply = "Found " . count($matches) . " medicines matching <strong>" . htmlspecialchars($query) . "</strong>:<br><br>";
    foreach ($matches as $item) {
        $stock = (int)$item['current_stock'];
        $name  = htmlspecialchars($item['name']);
        $code  = htmlspecialchars($item['item_code']);
        if ($stock > 10) {
            $dot = "<span style='color:#16a34a;'>&#9679;</span> Available";
        } elseif ($stock > 0) {
            $dot = "<span style='color:#ca8a04;'>&#9679;</span> Limited";
        } else {
            $dot = "<span style='color:#dc2626;'>&#9679;</span> Out of stock";
        }
        $reply .= "<div style='margin:4px 0;'><strong>{$name}</strong> <small style='color:#9ca3af;'>({$code})</small> &mdash; {$dot}</div>";
    }
}

ob_end_clean();
echo json_encode(['reply' => $reply]);
