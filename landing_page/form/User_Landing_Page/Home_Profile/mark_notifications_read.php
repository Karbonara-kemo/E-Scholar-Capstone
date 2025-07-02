<?php
include '../../../../connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$userId = $_SESSION['user_id'];

$sql = "UPDATE notifications SET status = 'read' WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

http_response_code(200);
?>