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
    $hospitalName = trim($_POST['hospital_name']);
    $hospitalAddress = trim($_POST['hospital_address']);
    $alertRecipientEmail = filter_input(INPUT_POST, 'alert_recipient_email', FILTER_VALIDATE_EMAIL);
    $serviceLevel = filter_input(INPUT_POST, 'service_level', FILTER_VALIDATE_FLOAT);
    $holdingCostRate = filter_input(INPUT_POST, 'holding_cost_rate', FILTER_VALIDATE_FLOAT);
    $orderingCost = filter_input(INPUT_POST, 'ordering_cost', FILTER_VALIDATE_FLOAT);
    $slowMovingDays = filter_input(INPUT_POST, 'slow_moving_days', FILTER_VALIDATE_INT);
    $slowMovingThreshold = filter_input(INPUT_POST, 'slow_moving_threshold', FILTER_VALIDATE_INT);

    // Validate the inputs
    if (empty($hospitalName) || empty($hospitalAddress) || !$alertRecipientEmail
        || $serviceLevel === false || $holdingCostRate === false || $orderingCost === false
        || $serviceLevel < 0 || $holdingCostRate < 0 || $orderingCost < 0
        || $slowMovingDays === false || $slowMovingDays < 1
        || $slowMovingThreshold === false || $slowMovingThreshold < 1) {
        $_SESSION['error'] = "Invalid input. Please ensure all required fields are provided and numeric values are positive numbers.";
        header("Location: global_settings.php");
        exit();
    }

    // Prepare a statement for updating/inserting settings
    $stmt = $conn->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $hn_name  = 'hospital_name';
    $ha_name  = 'hospital_address';
    $are_name = 'alert_recipient_email';
    $sl_name  = 'service_level';
    $hcr_name = 'holding_cost_rate';
    $oc_name  = 'ordering_cost';
    $smd_name = 'slow_moving_days';
    $smt_name = 'slow_moving_threshold';

    $stmt->bind_param("ssssssssssssssss",
        $hn_name,  $hospitalName,
        $ha_name,  $hospitalAddress,
        $are_name, $alertRecipientEmail,
        $sl_name,  $serviceLevel,
        $hcr_name, $holdingCostRate,
        $oc_name,  $orderingCost,
        $smd_name, $slowMovingDays,
        $smt_name, $slowMovingThreshold
    );

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
