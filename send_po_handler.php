<?php
// Filename: send_po_handler.php
// Sends a formatted Purchase Order to the supplier's email address.
session_start();
require_once 'db_connection.php';
require_once 'send_email.php';

// Only Admins and Procurement staff can send POs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    $_SESSION['error'] = "You do not have permission to perform this action.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: po_management.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security error: Invalid request token.";
    header("Location: po_management.php");
    exit();
}

$po_id = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
if (!$po_id) {
    $_SESSION['error'] = "Invalid Purchase Order ID.";
    header("Location: po_management.php");
    exit();
}

// Fetch PO data along with supplier email
$sql = "
    SELECT
        po.po_id, po.po_number, po.quantity_ordered, po.unit_cost_agreed,
        po.expected_delivery_date, po.status, po.created_at, po.external_reference,
        i.name AS item_name, i.item_code, i.unit_of_measure,
        s.name AS supplier_name, s.email AS supplier_email, s.contact_info AS supplier_contact,
        u.username AS created_by
    FROM purchase_orders po
    JOIN items i ON po.item_id = i.item_id
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN users u ON po.created_by_user_id = u.user_id
    WHERE po.po_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$po) {
    $_SESSION['error'] = "Purchase Order not found.";
    header("Location: po_management.php");
    exit();
}

if (empty($po['supplier_email'])) {
    $_SESSION['error'] = "This supplier does not have an email address on file. Please edit the supplier record to add one.";
    header("Location: print_po.php?id=" . $po_id);
    exit();
}

// Fetch hospital name for the email header
$settings_res = $conn->query("SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('hospital_name', 'hospital_address')");
$settings = [];
if ($settings_res) {
    while ($row = $settings_res->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$hospital_name = htmlspecialchars($settings['hospital_name'] ?? 'SMART MEDI STOCKS');
$hospital_address = htmlspecialchars($settings['hospital_address'] ?? '');
$conn->close();

$total_cost = $po['quantity_ordered'] * $po['unit_cost_agreed'];
$po_number  = htmlspecialchars($po['po_number']);
$delivery   = date("F j, Y", strtotime($po['expected_delivery_date']));
$issued     = date("F j, Y", strtotime($po['created_at']));

// Build the HTML email body
$email_body = "
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><style>
    body { font-family: Arial, sans-serif; color: #333; font-size: 14px; }
    .header { background-color: #2563eb; color: #fff; padding: 20px 30px; }
    .header h1 { margin: 0; font-size: 26px; }
    .header p  { margin: 4px 0 0; font-size: 14px; opacity: 0.85; }
    .section   { padding: 20px 30px; }
    .grid      { display: table; width: 100%; border-collapse: collapse; }
    .col       { display: table-cell; width: 50%; vertical-align: top; padding: 0 10px 0 0; }
    h3         { border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 10px; font-size: 13px; text-transform: uppercase; color: #6b7280; }
    table      { width: 100%; border-collapse: collapse; }
    th         { background: #1f2937; color: #fff; padding: 10px; text-align: left; font-size: 13px; }
    td         { padding: 10px; border: 1px solid #e5e7eb; font-size: 13px; }
    .total-row { background: #2563eb; color: #fff; font-weight: bold; }
    .footer    { padding: 20px 30px; background: #f9fafb; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    .badge     { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: bold; background: #dbeafe; color: #1d4ed8; }
</style></head>
<body>
<div class='header'>
    <h1>PURCHASE ORDER: {$po_number}</h1>
    <p>{$hospital_name} &mdash; Procurement Department</p>
</div>
<div class='section'>
    <div class='grid'>
        <div class='col'>
            <h3>To (Supplier)</h3>
            <p><strong>" . htmlspecialchars($po['supplier_name']) . "</strong></p>
            <p>" . htmlspecialchars($po['supplier_contact']) . "</p>
        </div>
        <div class='col'>
            <h3>Ship To</h3>
            <p><strong>{$hospital_name}</strong></p>
            <p>" . nl2br(htmlspecialchars($hospital_address)) . "</p>
            <p>Attn: Receiving Dock</p>
        </div>
    </div>

    <br>
    <table style='margin-bottom:20px;border:1px solid #e5e7eb;'>
        <tr>
            <td><strong>PO Number:</strong></td><td><span class='badge'>{$po_number}</span></td>
            <td><strong>Date Issued:</strong></td><td>{$issued}</td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td><td>" . htmlspecialchars($po['status']) . "</td>
            <td><strong>Expected Delivery:</strong></td><td>{$delivery}</td>
        </tr>
        <tr>
            <td><strong>Ref:</strong></td><td colspan='3'>" . htmlspecialchars($po['external_reference'] ?? 'N/A') . "</td>
        </tr>
    </table>

    <h3>Order Details</h3>
    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Description</th>
                <th>Unit</th>
                <th style='text-align:right;'>Qty</th>
                <th style='text-align:right;'>Unit Cost (&#8369;)</th>
                <th style='text-align:right;'>Line Total (&#8369;)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style='font-family:monospace;'>" . htmlspecialchars($po['item_code']) . "</td>
                <td>" . htmlspecialchars($po['item_name']) . "</td>
                <td>" . htmlspecialchars($po['unit_of_measure']) . "</td>
                <td style='text-align:right;font-weight:bold;'>" . number_format($po['quantity_ordered']) . "</td>
                <td style='text-align:right;'>" . number_format($po['unit_cost_agreed'], 2) . "</td>
                <td style='text-align:right;font-weight:bold;'>&#8369;" . number_format($total_cost, 2) . "</td>
            </tr>
            <tr class='total-row'>
                <td colspan='5' style='text-align:right;'>TOTAL ORDER VALUE</td>
                <td style='text-align:right;'>&#8369;" . number_format($total_cost, 2) . "</td>
            </tr>
        </tbody>
    </table>
</div>
<div class='footer'>
    <p>This Purchase Order was issued by <strong>" . htmlspecialchars($po['created_by']) . "</strong> on {$issued}.</p>
    <p>Please send your invoice and packing slip referencing PO Number <strong>{$po_number}</strong> to {$hospital_name}.</p>
    <p>If you have questions, please reply to this email or contact the Procurement Department.</p>
</div>
</body></html>
";

$subject = "Purchase Order {$po_number} from {$hospital_name}";
$sent    = send_alert_email($po['supplier_email'], $subject, $email_body);

if ($sent) {
    $_SESSION['message'] = "Purchase Order {$po_number} has been sent successfully to " . htmlspecialchars($po['supplier_email']) . ".";
} else {
    $_SESSION['error'] = "Failed to send the email. Please check server mail settings and try again.";
}

header("Location: print_po.php?id=" . $po_id);
exit();
