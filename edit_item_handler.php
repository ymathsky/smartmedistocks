<?php
// Filename: edit_item_handler.php

session_start();
require_once 'db_connection.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Pharmacist' && $_SESSION['role'] != 'Warehouse')) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: item_management.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CSRF Protection Check (NEW) ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security error: Invalid request token.";
        unset($_SESSION['csrf_token']); // Clear the token for security
        header("Location: index.php");
        exit();
    }
    // --- End CSRF Check ---

    // Sanitize and validate inputs
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $item_name = trim($_POST['item_name']);
    $item_code = trim($_POST['item_code']) ?: null;
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $brand_name = trim($_POST['brand_name']);
    $unit_of_measure = trim($_POST['unit_of_measure']);

    $unit_cost = filter_input(INPUT_POST, 'unit_cost', FILTER_VALIDATE_FLOAT);
    $shelf_life = filter_input(INPUT_POST, 'shelf_life', FILTER_VALIDATE_INT);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);

    if (!$item_id || empty($item_name) || $unit_cost === false || $unit_cost < 0) {
        $_SESSION['error'] = "Invalid input. Please check the item ID, name, and cost values.";
        header("Location: edit_item.php?id=" . $item_id);
        exit();
    }

    $shelf_life = ($shelf_life === false || $shelf_life < 0) ? null : $shelf_life;
    $supplier_id = ($supplier_id === false) ? null : $supplier_id;
    $is_controlled = isset($_POST['is_controlled']) ? 1 : 0;

    // --- Database Interaction ---
    $stmt = $conn->prepare("UPDATE items SET name = ?, item_code = ?, description = ?, category = ?, brand_name = ?, unit_of_measure = ?, unit_cost = ?, shelf_life_days = ?, supplier_id = ?, is_controlled = ? WHERE item_id = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("ssssssdiiii", $item_name, $item_code, $description, $category, $brand_name, $unit_of_measure, $unit_cost, $shelf_life, $supplier_id, $is_controlled, $item_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Item '" . htmlspecialchars($item_name) . "' has been updated successfully.";
        header("Location: item_management.php");
    } else {
        if ($conn->errno == 1062) {
            $_SESSION['error'] = "Error: Another item with the same name or item code already exists.";
        } else {
            $_SESSION['error'] = "Error updating item: " . htmlspecialchars($stmt->error);
        }
        header("Location: edit_item.php?id=" . $item_id);
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: item_management.php");
    exit();
}
