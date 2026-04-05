<?php
// Filename: public_chat_handler.php
// Public endpoint - NO login required.
// ONLY answers medicine availability questions. Does NOT expose internal data.

ob_start();
header('Content-Type: application/json');

set_exception_handler(function($e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['reply' => 'Error: ' . $e->getMessage()]);
    exit();
});

require_once 'db_connection.php';
require_once 'fuzzy_search_helper.php';

if (!isset($conn) || $conn === null) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode(['reply' => 'Error: Unable to connect to the inventory database.']);
    exit();
}

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

// --- Use fuzzy search helper ---
try {
    $search_results = fuzzy_search_items($conn, $query, 10, 3);
    $matches = $search_results['exact'];
    $suggestions = $search_results['suggestions'];
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['reply' => 'Error searching inventory: ' . $e->getMessage()]);
    $conn->close();
    exit();
}

$conn->close();

// --- Build response ---
if (empty($matches)) {
    if (!empty($suggestions)) {
        // Show suggestions with fuzzy-matched alternatives
        $reply = "We couldn't find <strong>" . htmlspecialchars($query) . "</strong>, but did you mean one of these?<br><br>";
        foreach ($suggestions as $item) {
            $formatted = format_item_for_display($item);
            $reply .= generate_suggestion_html($formatted);
        }
    } else {
        $reply = "Sorry, we could not find any medicine matching <strong>" . htmlspecialchars($query) . "</strong> in our inventory. Please check the spelling or try a different name.";
    }
} elseif (count($matches) === 1) {
    $formatted = format_item_for_display($matches[0]);
    $reply = generate_exact_match_html($formatted);
} else {
    // Multiple exact matches
    $reply = "Found " . count($matches) . " medicines matching <strong>" . htmlspecialchars($query) . "</strong>:<br><br>";
    foreach ($matches as $item) {
        $formatted = format_item_for_display($item);
        $reply .= "<div style='margin:4px 0;'><strong>{$formatted['name']}</strong> " .
                  "<small style='color:#9ca3af;'>({$formatted['code']})</small> &mdash; " .
                  "<span style='color:{$formatted['status_color']};'>●</span> {$formatted['status']}</div>";
    }
}

ob_end_clean();
echo json_encode(['reply' => $reply]);
