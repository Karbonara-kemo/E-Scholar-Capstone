<?php
include '../../../../connect.php';
session_start();

$userId = $_SESSION['user_id'];

// Get the latest notification timestamp for the user
$latestNotificationSql = "SELECT MAX(created_at) AS latest_notification FROM notifications WHERE user_id IS NULL OR user_id = ?";
$stmt = $conn->prepare($latestNotificationSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$latestNotification = $result->fetch_assoc()['latest_notification'];

// Return the latest notification timestamp
echo json_encode(['status' => 'success', 'latest_notification' => $latestNotification]);
?>