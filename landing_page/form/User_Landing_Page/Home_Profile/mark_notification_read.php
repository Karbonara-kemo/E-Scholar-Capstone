<?php
include '../../../../connect.php';
session_start();

$userId = $_SESSION['user_id'];
$sql = "UPDATE notifications SET status = 'read' WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark notifications as read']);
}
?>