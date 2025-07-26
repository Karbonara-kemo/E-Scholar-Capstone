<?php
include '../../../../connect.php';
session_start();

$userId = $_SESSION['user_id'];

// Mark global notifications as read for this user
$sqlGlobal = "SELECT id FROM notifications WHERE user_id IS NULL";
$resultGlobal = $conn->query($sqlGlobal);
while ($row = $resultGlobal->fetch_assoc()) {
    $notifId = $row['id'];
    $conn->query("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES ($notifId, $userId)");
}

// Mark personal notifications as read
$sqlPersonal = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
$stmtPersonal = $conn->prepare($sqlPersonal);
$stmtPersonal->bind_param("i", $userId);
$stmtPersonal->execute();

echo json_encode(['status' => 'success']);
?>