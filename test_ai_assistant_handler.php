<?php
// test_ai_assistant_handler.php - Debug script for AI assistant handler
session_start();

// Simulate login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION["loggedin"] = true;
    $_SESSION["user_id"] = 1; // Assume user ID 1 for testing
}

echo "<h1>Testing AI Assistant Handler</h1>";

// Test data
$test_data = [
    'action' => 'send_message',
    'message' => 'What is the total stock value?'
];

$json_data = json_encode($test_data);

echo "<p>Sending: " . htmlspecialchars($json_data) . "</p>";

// Use cURL to call the handler
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/smartmedistocks/ai_assistant_handler.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if ($curl_error) {
    echo "<p>cURL Error: $curl_error</p>";
} else {
    echo "<p>Response: <pre>" . htmlspecialchars($response) . "</pre></p>";
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p>JSON Decode Error: " . json_last_error_msg() . "</p>";
    } else {
        echo "<p>Decoded Response: <pre>" . print_r($decoded, true) . "</pre></p>";
    }
}
?>