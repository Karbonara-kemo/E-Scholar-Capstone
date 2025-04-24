<?php
include '../../../../connect.php';
session_start();

$userId = $_SESSION['user_id'];
$countUnreadSql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$countUnreadStmt = $conn->prepare($countUnreadSql);
$countUnreadStmt->bind_param("i", $userId);
$countUnreadStmt->execute();
$countUnreadResult = $countUnreadStmt->get_result();
$unreadCount = $countUnreadResult->fetch_assoc()['unread_count'];

echo json_encode(['status' => 'success', 'unread_count' => $unreadCount]);
?>