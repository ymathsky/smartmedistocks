<?php
// Filename: notifications_helper.php
// Purpose: Utility functions for inserting real-time in-app notifications.
// Requires $conn (MySQLi) to be available in the calling scope.

/**
 * Insert a notification for every user matching the given roles.
 *
 * @param mysqli $conn     Active DB connection.
 * @param string $message  Notification text (plain text or basic HTML).
 * @param array  $roles    Roles that should receive the notification.
 * @return void
 */
function notify_by_role(mysqli $conn, string $message, array $roles): void
{
    if (empty($roles) || empty($message)) return;

    // Build IN clause safely — roles are all internal string literals
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types        = str_repeat('s', count($roles));

    $users_stmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ($placeholders)");
    $users_stmt->bind_param($types, ...$roles);
    $users_stmt->execute();
    $users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();

    if (empty($users)) return;

    $insert_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    foreach ($users as $user) {
        $uid = (int)$user['user_id'];
        $insert_stmt->bind_param("is", $uid, $message);
        $insert_stmt->execute();
    }
    $insert_stmt->close();
}

/**
 * Insert a notification for a specific user ID.
 *
 * @param mysqli $conn     Active DB connection.
 * @param int    $user_id  Target user.
 * @param string $message  Notification text.
 * @return void
 */
function notify_user(mysqli $conn, int $user_id, string $message): void
{
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
}
