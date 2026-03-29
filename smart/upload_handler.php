<?php
// Filename: upload_handler.php
session_start();
require_once 'db_connection.php';

// Security check: Ensure an Admin is logged in.
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['upload_error'] = "You do not have permission to perform this action.";
    header("Location: data_hub.php");
    exit();
}

// --- Main Upload Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"]) && isset($_POST["upload_type"])) {

    // Check for upload errors
    if ($_FILES["csv_file"]["error"] > 0) {
        $_SESSION['upload_error'] = "Error during file upload: " . $_FILES["csv_file"]["error"];
        header("Location: data_hub.php");
        exit();
    }

    $fileName = $_FILES["csv_file"]["tmp_name"];
    $uploadType = $_POST["upload_type"];

    $file = fopen($fileName, "r");
    if ($file === FALSE) {
        $_SESSION['upload_error'] = "Could not open the uploaded file.";
        header("Location: data_hub.php");
        exit();
    }

    $successCount = 0;
    $errorCount = 0;
    $isFirstRow = true; // Skip header

    // --- Transaction Wrapper ---
    $conn->begin_transaction();

    try {
        // --- Process based on upload type ---
        switch ($uploadType) {
            case 'items':
                $stmt = $conn->prepare("INSERT INTO items (name, item_code, description, category, brand_name, unit_of_measure, unit_cost, shelf_life_days, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), category=VALUES(category), brand_name=VALUES(brand_name), unit_of_measure=VALUES(unit_of_measure), unit_cost=VALUES(unit_cost), shelf_life_days=VALUES(shelf_life_days), supplier_id=VALUES(supplier_id)");
                while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
                    if ($isFirstRow) { $isFirstRow = false; continue; }
                    if (count($column) < 9) { $errorCount++; continue; }

                    $stmt->bind_param("ssssssdii", $column[0], $column[1], $column[2], $column[3], $column[4], $column[5], $column[6], $column[7], $column[8]);
                    if ($stmt->execute()) $successCount++; else $errorCount++;
                }
                break;

            case 'suppliers':
                $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_info, average_lead_time_days) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE contact_info=VALUES(contact_info), average_lead_time_days=VALUES(average_lead_time_days)");
                while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
                    if ($isFirstRow) { $isFirstRow = false; continue; }
                    if (count($column) < 3) { $errorCount++; continue; }

                    $stmt->bind_param("ssi", $column[0], $column[1], $column[2]);
                    if ($stmt->execute()) $successCount++; else $errorCount++;
                }
                break;

            case 'transactions':
                $stmt = $conn->prepare("INSERT INTO transactions (item_id, quantity_used, transaction_date) VALUES (?, ?, ?)");
                while (($column = fgetcsv($file, 1000, ",")) !== FALSE) {
                    if ($isFirstRow) { $isFirstRow = false; continue; }
                    if (count($column) < 3) { $errorCount++; continue; }

                    $transactionDate = date('Y-m-d', strtotime($column[2]));
                    $stmt->bind_param("iis", $column[0], $column[1], $transactionDate);
                    if ($stmt->execute()) $successCount++; else $errorCount++;
                }
                break;

            default:
                $_SESSION['upload_error'] = "Invalid upload type specified.";
                header("Location: data_hub.php");
                exit();
        }

        $conn->commit();
        $_SESSION['upload_message'] = "Upload for '$uploadType' complete.\nSuccessfully imported: $successCount rows.\nFailed or skipped: $errorCount rows.";

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['upload_error'] = "A database error occurred during the import: " . $exception->getMessage();
    }

    $stmt->close();
    fclose($file);

} else {
    $_SESSION['upload_error'] = "No file was uploaded or an unknown error occurred.";
}

$conn->close();
header("Location: data_hub.php");
exit();
?>
