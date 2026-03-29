<?php
// Filename: update_settings_handler.php
session_start();
require_once 'db_connection.php';

// Security: Ensure only Admins can perform this action
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize the input values
    $serviceLevel = filter_input(INPUT_POST, 'service_level', FILTER_VALIDATE_FLOAT);
    $holdingCostRate = filter_input(INPUT_POST, 'holding_cost_rate', FILTER_VALIDATE_FLOAT);
    $orderingCost = filter_input(INPUT_POST, 'ordering_cost', FILTER_VALIDATE_FLOAT);

    // Validate the inputs
    if ($serviceLevel === false || $holdingCostRate === false || $orderingCost === false || $serviceLevel < 0 || $holdingCostRate < 0 || $orderingCost < 0) {
        $_SESSION['error'] = "Invalid input. Please ensure all values are positive numbers.";
        header("Location: global_settings.php");
        exit();
    }

    // Prepare a statement for updating/inserting settings
    // Using INSERT ... ON DUPLICATE KEY UPDATE is efficient for this task
    $stmt = $conn->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?), (?, ?), (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $sl_name = 'service_level';
    $hcr_name = 'holding_cost_rate';
    $oc_name = 'ordering_cost';

    $stmt->bind_param("ssssss", $sl_name, $serviceLevel, $hcr_name, $holdingCostRate, $oc_name, $orderingCost);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Global settings have been updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update settings: " . $stmt->error;
    }

    $stmt->close();
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: global_settings.php");
exit();

?>
