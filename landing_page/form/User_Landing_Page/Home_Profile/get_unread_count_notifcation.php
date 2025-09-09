<?php
include '../../../../connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

// Count unread global notifications for this user
$sqlGlobal = "SELECT COUNT(*) AS cnt FROM notifications n
              WHERE n.user_id IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM notification_reads r
                  WHERE r.notification_id = n.notification_id AND r.user_id = ?
              )";

// Count unread personal notifications
$sqlPersonal = "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND status = 'unread'";

// Prepare and execute global count
$stmtGlobal = $conn->prepare($sqlGlobal);
$stmtGlobal->bind_param("i", $userId);
$stmtGlobal->execute();
$stmtGlobal->bind_result($unreadGlobal);
$stmtGlobal->fetch();
$stmtGlobal->close();

// Prepare and execute personal count
$stmtPersonal = $conn->prepare($sqlPersonal);
$stmtPersonal->bind_param("i", $userId);
$stmtPersonal->execute();
$stmtPersonal->bind_result($unreadPersonal);
$stmtPersonal->fetch();
$stmtPersonal->close();

$totalUnread = $unreadGlobal + $unreadPersonal;

echo json_encode(['status' => 'success', 'unread_count' => $totalUnread]);
?>