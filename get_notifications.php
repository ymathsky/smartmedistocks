<?php
// Filename: smart/get_notifications.php
// Purpose: API endpoint to fetch and manage in-app notifications for the logged-in user.

header('Content-Type: application/json');
require_once 'db_connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'fetch';

$conn->begin_transaction();

try {
    if ($action === 'mark_read') {
        // Handle marking individual notification as read
        $notification_id = filter_input(INPUT_GET, 'notification_id', FILTER_VALIDATE_INT);
        if ($notification_id) {
            // Only update the specific notification for the current user
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'mark_all_read') {
        // Handle marking ALL notifications for the user as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        // Fall-through to fetch updated data
    }

    // Fetch unread count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $unread_count = $count_stmt->get_result()->fetch_assoc()['unread_count'];
    $count_stmt->close();

    // Fetch latest 10 notifications (unread first)
    $notifications_stmt = $conn->prepare("
        SELECT notification_id, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT 10
    ");
    $notifications_stmt->bind_param("i", $user_id);
    $notifications_stmt->execute();
    $result = $notifications_stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['notification_id'],
            'message' => htmlspecialchars($row['message']),
            'is_read' => (bool)$row['is_read'],
            'time' => date('M j, g:i a', strtotime($row['created_at']))
        ];
    }
    $notifications_stmt->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'count' => (int)$unread_count,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Notification Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database Error: Could not fetch alerts.']);
}

$conn->close();
?>
