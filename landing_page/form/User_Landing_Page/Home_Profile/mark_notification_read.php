<?php
include '../../../../connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

// Mark global notifications as read for this user by adding an entry to notification_reads
$sqlGlobal = "SELECT notification_id FROM notifications WHERE user_id IS NULL";
$resultGlobal = $conn->query($sqlGlobal);
if ($resultGlobal) {
    while ($row = $resultGlobal->fetch_assoc()) {
        $notifId = $row['notification_id'];
        // Use INSERT IGNORE to prevent errors if the entry already exists
        $conn->query("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES ($notifId, $userId)");
    }
}

// Mark personal notifications as read
$sqlPersonal = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
$stmtPersonal = $conn->prepare($sqlPersonal);
if ($stmtPersonal) {
    $stmtPersonal->bind_param("i", $userId);
    $stmtPersonal->execute();
    $stmtPersonal->close();
}

echo json_encode(['status' => 'success']);
?>