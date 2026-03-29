<?php
// Filename: smart/check_alerts_cron.php
// This script is designed to be run periodically (e.g., daily) by a server cron job.

// Load dependencies
require_once 'db_connection.php';
require_once 'send_email.php';

// --- CONFIGURATION ---
// This is now fetched from the database below.
// $admin_email = defined('ADMIN_ALERT_EMAIL') ? ADMIN_ALERT_EMAIL : 'procurement@hospital.com';

// --- 1. Fetch Global Settings for ROP Calculation & Email Recipient ---
$settings_sql = "SELECT setting_name, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
$settings = [];
while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
$admin_email = $settings['alert_recipient_email'] ?? 'procurement@hospital.com'; // Use DB value
$service_level = $settings['service_level'] ?? 95;
$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = $z_scores[$service_level] ?? 1.65;
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));

// Combined array for all alerts (ROP/SS, Expiry, and PO)
$all_alerts_found = [];
$rop_ss_alerts_found = [];
$expiry_alerts_found = [];
$po_alerts_found = [];

// --- 2. ROP/Safety Stock Check (Ordering Alerts) ---
$items_sql = "
    SELECT
        i.item_id, i.name, i.item_code,
        COALESCE((SELECT SUM(quantity) FROM item_batches WHERE item_id = i.item_id), 0) as current_stock,
        s.average_lead_time_days,
        COALESCE(td.total_usage, 0) as total_usage_90_days,
        COALESCE(td.transaction_days, 0) as transaction_days_90
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_used) as total_usage, COUNT(DISTINCT transaction_date) as transaction_days
        FROM transactions
        WHERE transaction_date >= ?
        GROUP BY item_id
    ) as td ON i.item_id = td.item_id
    HAVING total_usage_90_days > 0
";

$stmt = $conn->prepare($items_sql);
$stmt->bind_param("s", $ninety_days_ago);
$stmt->execute();
$items_result = $stmt->get_result();

while ($item = $items_result->fetch_assoc()) {
    if ($item['total_usage_90_days'] > 0) {
        $avg_daily_demand = $item['total_usage_90_days'] / 90;
        $lead_time_days = $item['average_lead_time_days'] ?? 7;

        $std_dev_daily_demand = sqrt($avg_daily_demand);
        $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);
        $demand_during_lead_time = $avg_daily_demand * $lead_time_days;
        $reorder_point = $demand_during_lead_time + $safety_stock;

        $rop_rounded = round($reorder_point);
        $ss_rounded = round($safety_stock);
        $current_stock = (int)$item['current_stock'];

        if ($current_stock <= $rop_rounded || $current_stock <= $ss_rounded) {
            $rop_ss_alerts_found[] = [
                'id' => $item['item_id'],
                'type' => 'ROP_SS',
                'name' => $item['name'],
                'code' => $item['item_code'],
                'stock' => $current_stock,
                'rop' => $rop_rounded,
                'safety_stock' => $ss_rounded
            ];
        }
    }
}
$stmt->close();


// --- 3. Near-Expiry Stock Check (Warehouse/Pharmacist Alerts) ---
$sixty_days_from_now = date('Y-m-d', strtotime('+60 days'));
$expiry_sql = "
    SELECT
        i.name, i.item_code, SUM(b.quantity) as expiring_qty, MIN(b.expiry_date) as earliest_expiry
    FROM item_batches b
    JOIN items i ON b.item_id = i.item_id
    WHERE
        b.expiry_date IS NOT NULL
        AND b.expiry_date BETWEEN CURDATE() AND ?
        AND b.quantity > 0
    GROUP BY i.item_id
    ORDER BY earliest_expiry ASC
";

$expiry_stmt = $conn->prepare($expiry_sql);
$expiry_stmt->bind_param("s", $sixty_days_from_now);
$expiry_stmt->execute();
$expiry_result = $expiry_stmt->get_result();

while ($row = $expiry_result->fetch_assoc()) {
    $expiry_date = new DateTime($row['earliest_expiry']);
    $diff = $expiry_date->diff(new DateTime());
    $days_to_expiry = $diff->days;

    $alert_level = 'Warning';
    // CRITICAL is still <= 30 days
    if ($days_to_expiry <= 30) {
        $alert_level = 'CRITICAL';
    }

    $expiry_alerts_found[] = [
        'type' => 'EXPIRY',
        'level' => $alert_level,
        'name' => $row['name'],
        'code' => $row['item_code'],
        'qty' => (int)$row['expiring_qty'],
        'date' => $row['earliest_expiry'],
        'days' => $days_to_expiry
    ];
}
$expiry_stmt->close();

// --- 4. NEW: PO Lead Time Performance Check (Procurement Alerts) ---
$three_days_from_now = date('Y-m-d', strtotime('+3 days'));
$po_sql = "
    SELECT
        po.po_number, i.name as item_name, po.quantity_ordered, po.expected_delivery_date, po.status, s.name as supplier_name
    FROM purchase_orders po
    JOIN items i ON po.item_id = i.item_id
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE
        po.status IN ('Placed', 'Shipped')
        AND po.expected_delivery_date <= ?
    ORDER BY po.expected_delivery_date ASC
";

$po_stmt = $conn->prepare($po_sql);
$po_stmt->bind_param("s", $three_days_from_now);
$po_stmt->execute();
$po_result = $po_stmt->get_result();

while ($row = $po_result->fetch_assoc()) {
    $expected_date = new DateTime($row['expected_delivery_date']);
    $today = new DateTime(date('Y-m-d'));
    $days_diff = $today->diff($expected_date)->days;
    $is_overdue = $expected_date < $today;

    $alert_level = 'Due Soon';
    if ($is_overdue) {
        $alert_level = 'OVERDUE';
    }

    // FIX: Removed duplicate array assignment lines that caused the parse error.
    $po_alerts_found[] = [
        'type' => 'PO',
        'level' => $alert_level,
        'po_number' => $row['po_number'],
        'item' => $row['item_name'],
        'qty' => $row['quantity_ordered'],
        'supplier' => $row['supplier_name'],
        'date' => $row['expected_delivery_date'],
        'days_diff' => $is_overdue ? $days_diff : (3 - $days_diff) // Days late or days remaining (up to 3)
    ];
}
$po_stmt->close();


// --- 5. COMBINE ALERTS AND DETERMINE NOTIFICATION USERS ---
$users_for_rop_ss = $conn->query("SELECT user_id, role FROM users WHERE role IN ('Admin', 'Procurement')")->fetch_all(MYSQLI_ASSOC);
$users_for_expiry = $conn->query("SELECT user_id, role FROM users WHERE role IN ('Admin', 'Pharmacist', 'Warehouse')")->fetch_all(MYSQLI_ASSOC);
$users_for_po = $conn->query("SELECT user_id, role FROM users WHERE role IN ('Admin', 'Procurement')")->fetch_all(MYSQLI_ASSOC); // PO alerts go to procurement/admin

// Combine all users and remove duplicates
$notification_users = array_merge($users_for_rop_ss, $users_for_expiry, $users_for_po);
$notification_users = array_unique($notification_users, SORT_REGULAR);

$all_alerts_found = array_merge($rop_ss_alerts_found, $expiry_alerts_found, $po_alerts_found);


if (!empty($all_alerts_found)) {
    $notification_insert_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $conn->begin_transaction();
    $notification_success = true;
    $count = count($all_alerts_found);

    // --- Prepare HTML Email Message ---
    $email_subject = "DAILY ALERT: " . $count . " Inventory and PO Warnings Issued";
    $email_message = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; line-height: 1.5; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
            h1 { color: #2a6496; border-bottom: 2px solid #2a6496; padding-bottom: 10px; }
            h2 { background-color: #333; color: white; padding: 10px; font-size: 1.1em; margin-top: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .critical { color: #c7254e; font-weight: bold; }
            .warning { color: #f89406; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Smart Medi Stocks - Daily Alert Summary</h1>
            <p>The Smart Medi Stocks system detected the following alerts as of ' . date('Y-m-d H:i:s') . ':</p>';

    // A. Add ROP/SS Alerts to email & in-app notifications
    if (!empty($rop_ss_alerts_found)) {
        $email_message .= "<h2>CRITICAL ORDERING ALERTS (ROP/SS)</h2>";
        $email_message .= "<table><thead><tr><th>Item Code</th><th>Item Name</th><th>Current Stock</th><th>Reorder Point</th><th>Safety Stock</th></tr></thead><tbody>";
        foreach ($rop_ss_alerts_found as $alert) {
            $email_message .= "<tr>
                <td>" . htmlspecialchars($alert['code']) . "</td>
                <td>" . htmlspecialchars($alert['name']) . "</td>
                <td class='critical'>" . $alert['stock'] . "</td>
                <td>" . $alert['rop'] . "</td>
                <td>" . $alert['safety_stock'] . "</td>
            </tr>";

            // UPDATED: More conversational message
            $alert_type = ($alert['stock'] <= $alert['safety_stock']) ? 'safety stock level' : 'reorder point';
            $in_app_message = "Attention: Stock for **" . htmlspecialchars($alert['name']) . "** (" . htmlspecialchars($alert['code']) . ") has fallen to **" . $alert['stock'] . " units**, which is below the " . $alert_type . " of **" . $alert['rop'] . " units**. Please consider placing an order soon.";


            foreach ($users_for_rop_ss as $user) {
                if (in_array($user['role'], ['Admin', 'Procurement'])) {
                    $notification_insert_stmt->bind_param("is", $user['user_id'], $in_app_message);
                    $notification_insert_stmt->execute();
                }
            }
        }
        $email_message .= "</tbody></table>";
    }

    // B. Add Expiry Alerts to email & in-app notifications
    if (!empty($expiry_alerts_found)) {
        $email_message .= "<h2>NEAR-EXPIRY ALERTS (NEXT 60 DAYS)</h2>";
        $email_message .= "<table><thead><tr><th>Item Code</th><th>Item Name</th><th>Expiring Qty</th><th>Expires On</th><th>Days Left</th></tr></thead><tbody>";
        foreach ($expiry_alerts_found as $alert) {
            $style_class = $alert['level'] === 'CRITICAL' ? 'critical' : 'warning';
            $email_message .= "<tr>
                <td>" . htmlspecialchars($alert['code']) . "</td>
                <td>" . htmlspecialchars($alert['name']) . "</td>
                <td>" . $alert['qty'] . "</td>
                <td class='{$style_class}'>" . date("M j, Y", strtotime($alert['date'])) . "</td>
                <td class='{$style_class}'>" . $alert['days'] . "</td>
            </tr>";

            // UPDATED: More conversational message
            $urgency = $alert['level'] === 'CRITICAL' ? "very soon" : "soon";
            $in_app_message = "Expiry " . ($alert['level'] === 'CRITICAL' ? "Critical" : "Warning") . ": **" . $alert['qty'] . " units** of **" . htmlspecialchars($alert['name']) . "** (" . htmlspecialchars($alert['code']) . ") are expiring " . $urgency . " on **" . date("M j, Y", strtotime($alert['date'])) . "** (in **" . $alert['days'] . "** days). Please prioritize use.";


            foreach ($users_for_expiry as $user) {
                if (in_array($user['role'], ['Admin', 'Pharmacist', 'Warehouse'])) {
                    $notification_insert_stmt->bind_param("is", $user['user_id'], $in_app_message);
                    $notification_insert_stmt->execute();
                }
            }
        }
        $email_message .= "</tbody></table>";
    }

    // C. Add PO Alerts to email & in-app notifications
    if (!empty($po_alerts_found)) {
        $email_message .= "<h2>PO LEAD TIME ALERTS</h2>";
        $email_message .= "<table><thead><tr><th>PO #</th><th>Item</th><th>Supplier</th><th>Status</th></tr></thead><tbody>";
        foreach ($po_alerts_found as $alert) {
            $status_text = $alert['level'] . " (Expected: " . date("M j, Y", strtotime($alert['date'])) . ")";
            $style_class = $alert['level'] === 'OVERDUE' ? 'critical' : 'warning';
            $email_message .= "<tr>
                <td>" . htmlspecialchars($alert['po_number']) . "</td>
                <td>" . htmlspecialchars($alert['item']) . "</td>
                <td>" . htmlspecialchars($alert['supplier']) . "</td>
                <td class='{$style_class}'>" . $status_text . "</td>
            </tr>";

            // UPDATED: More conversational message
            $status_detail = $alert['level'] === 'OVERDUE' ? "is **overdue by " . $alert['days_diff'] . " days**" : "is **due soon** (expected on " . date("M j, Y", strtotime($alert['date'])) . ")";
            $in_app_message = "PO Alert: Purchase Order **" . htmlspecialchars($alert['po_number']) . "** for **" . htmlspecialchars($alert['item']) . "** " . $status_detail . ". Please follow up with the supplier, **" . htmlspecialchars($alert['supplier']) . "**.";


            foreach ($users_for_po as $user) {
                if (in_array($user['role'], ['Admin', 'Procurement'])) {
                    $notification_insert_stmt->bind_param("is", $user['user_id'], $in_app_message);
                    $notification_insert_stmt->execute();
                }
            }
        }
        $email_message .= "</tbody></table>";
    }

    $email_message .= '</div></body></html>';

    // --- Final Commit and Status ---
    try {
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("CRON ERROR: Failed to insert all notifications. " . $e->getMessage());
        $notification_success = false;
    }

    $notification_insert_stmt->close();

    // Attempt to send the email
    if (send_alert_email($admin_email, $email_subject, $email_message)) {
        error_log("CRON: Successfully sent $count alerts to $admin_email.");
        if ($notification_success) {
            error_log("CRON: Successfully created " . count($all_alerts_found) . " in-app notifications.");
        } else {
            error_log("CRON WARNING: Email sent, but failed to create all in-app notifications (DB ROLLBACK occurred).");
        }
    } else {
        error_log("CRON: Failed to send email alerts.");
    }

} else {
    error_log("CRON: No critical stock or expiry alerts found.");
}

// --- CHAT LOG CLEANUP ---
// 1. Delete entries older than 90 days (absolute age limit)
$chat_cleanup_age = $conn->query(
    "DELETE FROM chat_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
);
if ($chat_cleanup_age) {
    $del_age = $conn->affected_rows;
    if ($del_age > 0) error_log("CRON: Removed $del_age chat_log entries older than 90 days.");
}

// 2. Per-user cap: keep most recent 200 messages per user, delete the rest
$users_with_log = $conn->query("SELECT DISTINCT user_id FROM chat_log");
if ($users_with_log) {
    $cap_stmt = $conn->prepare(
        "DELETE FROM chat_log
         WHERE user_id = ?
           AND log_id NOT IN (
               SELECT log_id FROM (
                   SELECT log_id FROM chat_log WHERE user_id = ?
                   ORDER BY created_at DESC LIMIT 200
               ) AS keep_rows
           )"
    );
    while ($u = $users_with_log->fetch_assoc()) {
        $uid = (int)$u['user_id'];
        $cap_stmt->bind_param("ii", $uid, $uid);
        $cap_stmt->execute();
        if ($cap_stmt->affected_rows > 0) {
            error_log("CRON: Trimmed " . $cap_stmt->affected_rows . " excess chat_log rows for user #$uid.");
        }
    }
    $cap_stmt->close();
}

// Do not close the connection if this script is included by another
// $conn->close();
