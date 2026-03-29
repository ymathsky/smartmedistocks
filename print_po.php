<?php
// Filename: smart/print_po.php
require_once 'db_connection.php';
session_start();

// Security check: Only Admins and Procurement can print POs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Procurement'])) {
    header("Content-Type: text/plain");
    die("Access Denied.");
}

$po_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$po_id) {
    header("Content-Type: text/plain");
    die("Invalid Purchase Order ID.");
}

// 1. Fetch Global Settings (Hospital Name and Address)
$settings = [];
$settings_result = $conn->query("SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('hospital_name', 'hospital_address')");
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$hospital_name = htmlspecialchars($settings['hospital_name'] ?? 'SMART MEDI STOCKS');
$hospital_address = nl2br(htmlspecialchars($settings['hospital_address'] ?? 'Hospital Address, City, Postal Code')); // ADDED


// 2. Fetch all PO details along with related item, supplier, and user info
$sql = "
    SELECT 
        po.po_id, po.po_number, po.quantity_ordered, po.unit_cost_agreed, 
        po.expected_delivery_date, po.status, po.created_at, po.external_reference,
        i.name as item_name, i.item_code, i.unit_of_measure,
        s.name as supplier_name, s.contact_info as supplier_contact, s.address as supplier_address, 
        u.username as created_by
    FROM 
        purchase_orders po
    JOIN 
        items i ON po.item_id = i.item_id
    JOIN 
        suppliers s ON po.supplier_id = s.supplier_id
    JOIN 
        users u ON po.created_by_user_id = u.user_id
    WHERE
        po.po_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();
$po_data = $result->fetch_assoc();
$stmt->close();


if (!$po_data) {
    header("Content-Type: text/plain");
    die("Purchase Order not found.");
}

$total_cost = $po_data['quantity_ordered'] * $po_data['unit_cost_agreed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order: <?php echo htmlspecialchars($po_data['po_number']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Print-specific styles */
        @media print {
            body {
                margin: 0;
                color: #000;
                background: #fff;
            }
            .no-print {
                display: none !important;
            }
            .po-document {
                box-shadow: none !important;
                border: none !important;
            }
            /* Force table full width and prevent page breaks inside rows */
            .po-document table {
                width: 100% !important;
                border-collapse: collapse;
            }
            .po-document th, .po-document td {
                border: 1px solid #000 !important;
                padding: 8px;
            }
            .po-document h1, .po-document h2 {
                color: #000 !important;
            }
            .bg-blue-600 {
                background-color: #3b82f6 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .text-white {
                color: #fff !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-8">

<div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-xl po-document">

    <!-- Header & PO Number -->
    <div class="flex justify-between items-start border-b-4 border-blue-600 pb-4 mb-6">
        <div>
            <h1 class="text-4xl font-extrabold text-gray-800">PURCHASE ORDER</h1>
            <p class="text-sm text-gray-500 mt-1"><?php echo $hospital_name; ?> - Procurement Department</p>
        </div>
        <div class="text-right">
            <h2 class="text-3xl font-mono font-bold text-blue-600"><?php echo htmlspecialchars($po_data['po_number']); ?></h2>
            <p class="text-sm text-gray-600">Date Issued: <?php echo date("F j, Y", strtotime($po_data['created_at'])); ?></p>
        </div>
    </div>

    <!-- Supplier & Ship To Info -->
    <div class="grid grid-cols-2 gap-8 mb-8">
        <div>
            <h3 class="text-lg font-bold text-gray-700 mb-2 border-b">SUPPLIER (SELL TO)</h3>
            <p class="font-semibold text-xl"><?php echo htmlspecialchars($po_data['supplier_name']); ?></p>
            <!-- UPDATED: Display actual supplier address -->
            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($po_data['supplier_address'] ?? 'No Address Provided')); ?></p>
            <p class="text-sm text-gray-600 mt-1">Contact: <?php echo htmlspecialchars($po_data['supplier_contact']); ?></p>
        </div>
        <div>
            <h3 class="text-lg font-bold text-gray-700 mb-2 border-b">SHIP TO</h3>
            <p class="font-semibold text-xl"><?php echo $hospital_name; ?></p>
            <!-- Hospital Address is ADDED and formatted -->
            <p class="text-sm text-gray-600 mt-1"><?php echo $hospital_address; ?></p>
            <p class="text-sm text-gray-600 mt-1">Attn: Receiving Dock</p>
        </div>
    </div>

    <!-- Key Dates & Status -->
    <div class="bg-gray-50 p-4 rounded-lg border mb-8">
        <div class="grid grid-cols-3 text-center">
            <div>
                <p class="text-sm font-semibold text-gray-600">STATUS</p>
                <p class="text-xl font-bold text-blue-800"><?php echo htmlspecialchars($po_data['status']); ?></p>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-600">EXPECTED DELIVERY</p>
                <p class="text-xl font-bold text-green-700"><?php echo date("F j, Y", strtotime($po_data['expected_delivery_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-600">REFERENCE</p>
                <p class="text-xl font-bold text-gray-700"><?php echo htmlspecialchars($po_data['external_reference'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Order Line Items Table -->
    <h3 class="text-lg font-bold text-gray-700 mb-2 border-b">ORDER DETAILS</h3>
    <table class="min-w-full bg-white border border-gray-300">
        <thead class="bg-gray-200">
        <tr>
            <th class="py-2 px-4 border border-gray-300 text-left text-sm font-bold">Item Code</th>
            <th class="py-2 px-4 border border-gray-300 text-left text-sm font-bold">Item Name</th>
            <th class="py-2 px-4 border border-gray-300 text-right text-sm font-bold">UoM</th>
            <th class="py-2 px-4 border border-gray-300 text-right text-sm font-bold">Quantity</th>
            <th class="py-2 px-4 border border-gray-300 text-right text-sm font-bold">Unit Cost (₱)</th>
            <th class="py-2 px-4 border border-gray-300 text-right text-sm font-bold">Line Total (₱)</th>
        </tr>
        </thead>
        <tbody>
        <!-- Only one item per PO in this structure, so hardcode the row -->
        <tr>
            <td class="py-2 px-4 border border-gray-300 font-mono"><?php echo htmlspecialchars($po_data['item_code']); ?></td>
            <td class="py-2 px-4 border border-gray-300"><?php echo htmlspecialchars($po_data['item_name']); ?></td>
            <td class="py-2 px-4 border border-gray-300 text-right"><?php echo htmlspecialchars($po_data['unit_of_measure']); ?></td>
            <td class="py-2 px-4 border border-gray-300 text-right font-bold"><?php echo number_format($po_data['quantity_ordered']); ?></td>
            <td class="py-2 px-4 border border-gray-300 text-right"><?php echo number_format($po_data['unit_cost_agreed'], 2); ?></td>
            <td class="py-2 px-4 border border-gray-300 text-right font-bold">₱<?php echo number_format($total_cost, 2); ?></td>
        </tr>
        <!-- Totals Row -->
        <tr class="bg-blue-600 text-white">
            <td colspan="5" class="py-2 px-4 border border-gray-300 text-right font-bold text-lg">TOTAL ORDER VALUE</td>
            <td class="py-2 px-4 border border-gray-300 text-right font-bold text-lg">₱<?php echo number_format($total_cost, 2); ?></td>
        </tr>
        </tbody>
    </table>

    <!-- Footer / Authorization -->
    <div class="mt-12 pt-6 border-t">
        <div class="grid grid-cols-2 gap-12 text-sm">
            <div>
                <p class="font-semibold text-gray-700 mb-1">Prepared By:</p>
                <p class="font-bold text-lg"><?php echo htmlspecialchars($po_data['created_by']); ?></p>
                <p class="text-gray-500">Procurement Officer</p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-gray-700 mb-1">Authorized By:</p>
                <p class="font-bold text-gray-800">_________________________</p>
                <p class="text-gray-500">Inventory Manager Signature</p>
            </div>
        </div>
        <p class="mt-8 text-xs text-gray-500 text-center">Please send invoice and packing slip referencing the PO Number above to the Ship To address.</p>
    </div>

    <!-- Print Button (Hidden on actual print) -->
    <div class="text-center mt-6 no-print">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-lg">
            Print this Document
        </button>
    </div>
</div>

</body>
</html>
