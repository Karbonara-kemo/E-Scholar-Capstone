<?php
include '../../../../connect.php';

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php");
    exit();
}

$userId = $_SESSION['user_id'];

$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "User";

$userId = $_SESSION['user_id'];
$sql = "SELECT * FROM user WHERE Id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "User not found.";
    exit();
}

$notificationSql = "SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationSql);
$notificationStmt->bind_param("i", $userId);
$notificationStmt->execute();
$notificationResult = $notificationStmt->get_result();
$notifications = $notificationResult->fetch_all(MYSQLI_ASSOC);

$countUnreadSql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$countUnreadStmt = $conn->prepare($countUnreadSql);
$countUnreadStmt->bind_param("i", $userId);
$countUnreadStmt->execute();
$countUnreadResult = $countUnreadStmt->get_result();
$unreadCount = $countUnreadResult->fetch_assoc()['unread_count'];

$scholarshipSql = "SELECT * FROM scholarships WHERE status = 'active'";
$scholarshipResult = $conn->query($scholarshipSql);
$scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);

$totalScholarships = count($scholarships);

if (isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insertSql = "INSERT INTO notifications (message, user_id, status, created_at) VALUES (?, ?, 'unread', NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("si", $message, $userId);
        $insertStmt->execute();
        header("Location: ".$_SERVER['PHP_SELF']."#communication-page");
        exit();
    }
}

$messages = [];
$stmt = $conn->prepare("SELECT * FROM concerns WHERE user_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

if (isset($_POST['send_concern'])) {
    $message = trim($_POST['concern_message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO concerns (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $userId, $message);
        $stmt->execute();
        header("Location: ".$_SERVER['PHP_SELF']."#communication-page");
        exit();
    }
}

if (isset($_POST['delete_message'])) {
    $messageId = intval($_POST['message_id']);
    $stmt = $conn->prepare("DELETE FROM concerns WHERE id = ? AND user_id = ? AND sender = 'user'");
    $stmt->bind_param("ii", $messageId, $userId);
    $stmt->execute();
    header("Location: ".$_SERVER['PHP_SELF']."#communication-page");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PESO Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="../../../../assets/PESO Logo Assets.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<style>
body {
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: #f4f4f4;
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: linear-gradient(155deg, #090549 23.3%, #aa0505 50%,rgb(165, 137, 0) 50%);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    height: 50px;
}

.navbar .title {
    font-size: 20px;
    font-weight: bold;
    color: white;
    margin-left: 0;
}

.navbar a {
    color: white;
    text-decoration: none;
    margin: 0 15px;
    font-size: 14px;
}

.san-julian-logo {
    height: 58px;
    margin-right: 10px;
}

.container {
    display: flex;
    flex: 1;
    padding-top: 50px;
}

        .sidebar {
            background-color: #090549;
            color: white;
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 70px;
            left: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 900;
            transition: width 0.3s ease;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .nav-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
        }

        .nav-item:hover {
            background-color: #10087c;
        }


        .nav-item.active {
            background-color: #10087c;
            border-left: 4px solid #ffffff;
        }

        .nav-item.active .nav-icon {
            margin-left: -4px; 
        }

        .sidebar.collapsed .nav-item.active {
            background-color: #10087c;
            border-left: 4px solid #ffffff;
        }

        .sidebar.collapsed .nav-item.active .nav-icon {
            margin-left: -2px;
        }

        .nav-icon {
            margin-right: 10px;
            font-size: 14px;
            min-width: 20px;
            text-align: center;
        }

        .nav-text {
            color: white;
            transition: opacity 0.2s ease;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            display: none;
        }

        .toggle-sidebar {
            background-color: transparent;
            color: white;
            border: none;
            cursor: pointer;
            padding: 15px;
            text-align: left;
            font-size: 14px;
            display: flex;
            margin-left: 10px;
            justify-content: flex-start;
            align-items: center;
        }

        .toggle-sidebar:hover {
            background-color: #10087c;
        }

        .main-content.sidebar-collapsed {
            margin-left: 60px;
        }

.image-container {
    flex: 1;
    padding: 15px;
    display: flex;
    justify-content: center;
    position: relative;
    height: 400px;
    overflow: hidden;
}

.slideshow {
    width: 100%;
    height: 100%;
    position: relative;
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.slide.active {
    opacity: 1;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 7px;
}

.content-container {
    flex: 1;
    text-align: center;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.main-title {
    font-size: 30px;
    font-weight: bold;
    color: #333;
    margin-bottom: 15px;
}

.description {
    font-size: 12px;
    color: #555;
    margin-bottom: 30px;
}


.logo {
    height: 50px;
    margin-right: 10px;
}

.logo-container {
    display: flex;
    align-items: center;
    margin-left: 20px;
}

@media (max-width: 768px) {
    .container {
        flex-direction: column;
        margin: 50px auto;
    }
    
    .image-container {
        height: 300px;
    }
}

.main-content {
    padding: 30px;
    flex: 1;
    box-sizing: border-box;
    margin-left: 250px;
    transition: margin-left 0.3s ease;
}

.main-content.sidebar-collapsed {
    margin-left: 60px;
}

.user-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid black;
}

.menu-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-name {
    color: white;
    font-size: 12px;
}

.dropdown-menu {
    opacity: 0;
    transform: translateY(-10px);
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
    display: block;
    position: absolute;
    background-color: white;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 5px 0;
    right: 0px;
    top: 45px;
    z-index: 1000;
}

.dropdown-menu.show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.dropdown-menu a {
    display: block;
    text-decoration: none;
    color: #090549;
    padding: 8px 8px;
    font-size: 10px;
}

.dropdown-menu a:hover {
    background-color: #f4f4f4;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    width: 55%;
    font-size : 12px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.modal-header {
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 10px;
}

.modal-body {
    max-height: 200px;
    overflow-y: auto;
    word-wrap: break-word;
}

.modal-close {
    float: right;
    font-size: 25px;
    font-weight: bold;
    cursor: pointer;
}

.modal-close:hover {
    color: red;
}

.scholarship-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn {
    padding: 6px 13px;
    font-size: 10px;
    cursor: pointer;
    border: none;
    border-radius: 10px;
}

.btn-outline {
    background: #090549;
    color:rgb(255, 255, 255);
    border: 3px solid #090549;
    border-radius: 14px;
}

.btn-outline:hover {
    background:rgb(16, 9, 122);
    color: white;
}

.btn-primary {
    background:rgb(5, 73, 28);
    color: white;
    border-radius: 14px;
}

.btn-primary:hover {
    background:rgb(9, 114, 44);
}

.notification-bell {
    position: relative;
    color: white;
    font-size: 20px;
    cursor: pointer;
    margin-right: 15px;
}

.notification-badge {
    position: absolute;
    top: -5px;         /* Move higher above the bell */
    right: -5px;       /* Move closer to the right edge */
    min-width: 11px;
    height: 11px;
    background-color: red;
    border-radius: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 8px;
    color: black;
    font-weight: bold;
    padding: 0 2px;
    line-height: 1;
    z-index: 2;
}
.user-menu-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-dot {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 8px;
    height: 8px;
    background-color: red;
    border-radius: 50%;
    display: none;
}

.box {
    background-color: #fff;
    border-radius: 15px;
    padding: 20px;
    margin: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
    flex: 1;
}

.box-title {
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.box-value {
    font-size: 35px;
    font-weight: bold;
    color: #333;
}

.box-description {
    font-size: 12px;
    color: #555;
    margin-top: 10px;
}
.dashboard-boxes {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    padding: 0 20px;
}
.get-started {
    background-color: #090549;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 14px;
    cursor: pointer;
    font-size: 10px;
    margin-top: 10px;
}
.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 12px;
}
.history-h2 {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}
.history-p {
    font-size: 12px;
    color: #555;
    margin-bottom: 20px;
}
.scholarship-header {
    background-color:rgb(221, 221, 221);
    color: white;
    padding: 10px;
    border-radius: 8px 8px 0 0;
}
.scholarship-title {
    font-size: 14px;
    font-weight: bold;
    color: #333;
}
.scholarship-info {
    font-size: 12px;
    color: #555;
    margin-top: 10px;
}

#history-page {
    /* padding: 20px; */
    background-color: #f4f4f4;
    border-radius: 12px;
}

.page {
        max-width: 1200px;
        margin: auto;
    }

.submit-btn {
    background-color: #090549;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 14px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 20px;
}

.form-container-application {
    max-width: 600px;
    margin: 20px auto;
    padding: 30px;
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}


.input-field {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    margin-bottom: 20px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 10px;
    box-sizing: border-box;
    transition: all 0.3s ease;
}

.input-field:focus {
    border-color:rgb(0, 0, 0);
    box-shadow: 0 0 8px rgba(13, 59, 102, 0.2);
    outline: none;
}

.textarea-field {
    resize: vertical;
}

.select-field {
    appearance: none;
    background: #fff url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%230d3b66' d='M2 0L0 2h4z'/%3E%3C/svg%3E") no-repeat right 10px center;
    background-size: 8px 10px;
}

.file-field {
    padding: 8px;
    font-size: 10px;
}

.back-btn {
    background:#090549;
    color:rgb(255, 255, 255);
    border: 1px solid #090549;
    padding: 8px 14px;
    border-radius: 10px;
    text-align: center;
    font-size: 10px;
    cursor: pointer;
    margin-bottom: 20px;
    display: inline-block;
}

.back-btn:hover {
    background:rgb(9, 7, 122);
    color: white;
}

.submit-btn {
    background-color: #090549;
    color: white;
    border: none;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 10px;
    font-weight: bold;
    display: block;
    width: 100%;
    text-align: center;
    transition: background-color 0.3s ease;
}

.submit-btn:hover {
    background-color:rgb(18, 10, 136);
}

.label-application {
    font-size: 12px;
    color: black;
}

#application-form-title {
    font-size: 15px;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
}

.label-application {
    font-size: 13px;
    color: black;
    font-weight: bold;
    margin-top: 15px;
    margin-bottom: 5px;
    display: block;
}

form .label-application + div label,
form .label-application + div label input {
    font-size: 10px !important;
    font-weight: normal;
}

form .label-application + div label {
    margin-right: 18px;
}

.doc-req-section {
    font-size: 13px;
}
.doc-req-section label {
    font-size: 13px;
}

.title-description-p{
    font-size: 12px;
    color: black;
    margin-top: 20px;
    margin-bottom: 20px;
    text-align: center;
    margin-left: 70px;
    margin-right: 70px;
    font-weight: bold;
}

#spes-application-form-title {
    font-size: 17px;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
    text-decoration: underline;
}

.chat-container {
    max-width: 1200px;
    margin: 30px auto 0 auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    height: 500px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f7f7fa;
    border-radius: 10px 10px 0 0;
}

.message {
    max-width: 70%;
    padding: 10px 15px;
    border-radius: 15px;
    margin: 5px 0;
    word-break: break-word;
}

.user-message {
    align-self: flex-end;
    background-color: #090549;
    color: white;
}

.admin-message {
    align-self: flex-start;
    background-color: #e9ecef;
    color: #333;
}

.message-content {
    margin-bottom: 5px;
}

.message-timestamp {
    font-size: 0.7em;
    opacity: 0.7;
    text-align: right;
}

.chat-input {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #eee;
    background: #fff;
    border-radius: 0 0 10px 10px;
}

.chat-input textarea {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 20px;
    resize: none;
    height: 40px;
    font-family: inherit;
    font-size: 13px;
}

.chat-input button {
    background: #090549;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    transition: background 0.3s ease;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-input button:hover {
    background:rgb(21, 12, 158);
}

.message {
    position: relative;
}

.message-options {
    position: absolute;
    top: 5px;
    right: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}

.message:hover .message-options {
    opacity: 1;
}

.options-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    color: #666;
    font-size: 12px;
}

.options-btn:hover {
    background-color: rgba(0,0,0,0.1);
}

.options-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: none;
    min-width: 100px;
    z-index: 1000;
}

.options-menu.show {
    display: block;
}

.options-menu button {
    display: block;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    color: #dc3545;
    font-size: 12px;
}

.options-menu button:hover {
    background-color: #f8f9fa;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: 1px solid #dc3545;
}

.btn-danger:hover {
    background-color: #c82333;
}

.upload-btn {
    background: none;
    border: none;
    color: #090549;
    font-size: 20px;
    cursor: pointer;
    margin-right: 10px;
    padding: 0 8px;
    border-radius: 50%;
    transition: background 0.2s;
}
.upload-btn:hover {
    background: #e9ecef;
}
.upload-popup {
    display: none;
    position: absolute;
    left: 40px;
    top: -10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.12);
    padding: 15px 20px 15px 15px;
    z-index: 100;
    min-width: 180px;
    font-size: 13px;
}
.upload-popup .btn {
    border-radius: 6px !important;
    width: 100px;
    height: 40px;
    padding: 0;
    font-size: 13px;
    background: #090549;
    color: #fff;
    font-weight: bold;
    border: none;
    display: block;
    margin: 0 auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: background 0.2s;
}
.upload-popup .btn:hover {
    background:rgb(15, 7, 121);
}
.upload-popup span {
    display: block;
    margin-bottom: 10px;
}
.close-upload-popup {
    background: none;
    border: none;
    color: #888;
    font-size: 18px;
    position: absolute;
    top: 5px;
    right: 10px;
    cursor: pointer;
}


@media (max-width: 768px) {
    @media (max-width: 768px) {
    .navbar {
        height: auto;
        padding: 15px 10px;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
    }
    .logo-container {
        margin-left: 0;
        margin-bottom: 0;
        flex-direction: row;
        align-items: center;
        gap: 5px;
    }
    .logo, .san-julian-logo {
        height: 30px;
    }
    .navbar .title {
        font-size: 11px !important;
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .right-nav {
        width: auto;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }
    .menu-container {
        width: auto;
        justify-content: flex-end;
        padding: 0;
        gap: 10px;
    }
    .user-name {
        display: none;
    }
    .dropdown-menu {
        top: 40px;
        right: 5px;
    }
}

    .sidebar {
        width: 100%;
        height: auto;
        position: fixed;
        bottom: 0;
        top: auto;
        flex-direction: row;
        z-index: 1000;
        padding: 5px 0;
    }
    
    .sidebar.collapsed {
        width: 100%;
    }
    
    .nav-item {
        margin: 0;
        display: flex;
        padding: 10px 5px;
        justify-content: space-between;
        flex-direction: column;
        text-align: center;
        min-width: 78px;
        min-height: 40px;
    }

    .nav-item.active {
        border-left: none;
        border-bottom: 4px solid #ffffff;
        background-color: #10087c;
        border-radius: 0;
    }
    
    .nav-icon {
        margin-right: 0;
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .nav-text {
        font-size: 10px;
        display: block !important;
        opacity: 1 !important;
    }
    
    .toggle-sidebar {
        display: none;
    }

    .container {
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
    .main-content {
        margin: 0 !important;
        padding: 10px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .welcome-screen {
        margin-top: 40px !important;
        text-align: center;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .dashboard-boxes {
        flex-direction: column;
        align-items: center;
        width: 100%;
        padding: 0;
    }
    .box {
        width: 90%;
        margin: 10px 0;
    }
}

    .dashboard-boxes {
        flex-direction: column;
    }
    
    .box {
        margin: 5px 0;
    }

    .form-container-application {
        padding: 15px;
    }
    
    .input-field, .textarea-field, .select-field {
        font-size: 14px;
        padding: 8px;
    }

    .chat-container {
        height: calc(100vh - 180px);
        margin: 10px auto;
    }
    
    .chat-input textarea {
        height: 35px;
    }

    .history-table {
        font-size: 10px;
    }
    
    .history-table th, 
    .history-table td {
        padding: 5px;
    }

    .description {
        display: none;
    }
    
    #spes-application-form-page .form-container-application {
        padding: 15px;
    }
    
    #spes-application-form-page img {
        display: none;
    }
    
    .title-description-p {
        margin-left: 0;
        margin-right: 0;
        font-size: 11px;
    }

    .modal-content {
        width: 90%;
        margin: 30% auto;
    }
    
    #application-form-page .form-container-application {
        padding: 15px;
    }
}

@media (max-width: 480px) {
    .nav-item {
        padding: 8px 3px;
    }
    
    .nav-text {
        font-size: 8px;
    }
    
    .main-title {
        font-size: 20px;
    }
    
    .box-title {
        font-size: 14px;
    }
    
    .box-value {
        font-size: 25px;
    }
    
    .history-h2 {
        font-size: 18px;
    }
}

@media screen and (max-width: 768px) {
    input, select, textarea {
        font-size: 16px !important;
    }
}

@media (max-width: 768px) {
    .dropdown-menu {
        position: fixed;
        top: 50px;
        right: 5px;
        width: 150px;
    }
    
    .options-menu {
        position: fixed;
        top: auto;
        bottom: 60px;
        right: 10px;
    }
}

@media (max-width: 768px) {
    #spes-page .dashboard-boxes {
        flex-direction: column;
    }
    
    #spes-page .box {
        margin-bottom: 10px;
    }
}

@media (max-width: 768px) {
    .modal-content {
        margin: 50% auto;
        width: 85%;
        font-size: 9px;
    }
}

.chevron-icon {
    transition: transform 0.3s cubic-bezier(.4,0,.2,1);
    transform: rotate(-90deg);
}
.chevron-icon.open {
    transform: rotate(0deg);
}
</style>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../../../images/final-logo-san-julian.png" alt="E-Scholar Logo" class="san-julian-logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
        <div class="right-nav">
            <div class="menu-container">
                <div class="notification-bell" onclick="openNotificationModal()">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-badge" style="display: none;"></span>
                </div>
                
                <div class="user-menu-container">
                    <img src="../../../../<?php echo htmlspecialchars($user['profile_pic'] ?? 'images/default-user.png'); ?>" alt="User Icon" class="user-icon">
                    <span class="user-name"><?php echo htmlspecialchars($user['Fname'] . " " . $user['Lname']); ?></span>
                    <i class="fas fa-chevron-down chevron-icon" style="color: white; cursor: pointer;" onclick="toggleMenu()" id="chevronIcon"></i>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="user_profile.php"><i class="fas fa-user"></i>  Profile</a>
                        <a href="../../signin.php"><i class="fas fa-sign-out-alt"></i>  Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar" id="sidebar">
            <button class="toggle-sidebar" id="toggleSidebar">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            <div class="nav-item" id="home-nav" onclick="showPage('home-page')">
                <div class="nav-icon"><i class="fas fa-home"></i></div>
                <div class="nav-text">Home</div>
            </div>
            <div class="nav-item" id="history-nav" onclick="showPage('history-page')">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                <div class="nav-text">Application History</div>
            </div>
            <div class="nav-item" id="scholarships-nav" onclick="showPage('scholarships-page')">
                <div class="nav-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="nav-text">Scholarships</div>
            </div>
            <div class="nav-item" id="spes-nav" onclick="showPage('spes-page')">
                <div class="nav-icon"><i class="fas fa-briefcase"></i></div>
                <div class="nav-text">SPESOS</div>
            </div>
            <div class="nav-item" id="communication-nav" onclick="showPage('communication-page')">
                <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                <div class="nav-text">Concern</div>
            </div>
        </div>

        <div class="main-content">
            <div id="home-page" class="page active">
                <div class="welcome-screen" style="margin-top: 100px;">
                    <h1 class="main-title">Welcome to PESO MIS SAN JULIAN</h1>
                    <p class="description">
                        Connecting Students and Out-of-School Youth in San Julian to Life-Changing Opportunities. Supporting Education,  <br>Building Careers, and Shaping Tomorrow Through Scholarships and SPES Programs.</p>
                    <!-- <button class="get-started" onclick="showPage('scholarships-page')">Browse Scholarships</button> -->
                </div>

                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Applications History</div>
                        <div class="box-value">4</div>
                        <div class="box-description">You have 2 approved scholarship applications</div>
                        <button class="get-started" style="margin-top: 20px;" onclick="showPage('history-page')">Browse</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Total Scholarships</div>
                        <div class="box-value"><?php echo $totalScholarships; ?></div>
                        <div class="box-description"><?php echo $totalScholarships; ?> scholarship programs available for application</div>
                        <button class="get-started" style="margin-top: 20px;" onclick="showPage('scholarships-page')">Browse</button>
                    </div>
                </div>
            </div>

            <div id="spes-page" class="page">
                <h3>SPECIAL PROGRAM FOR EMPLOYMENT OF STUDENTS AND OUT-OF-SCHOOL YOUTH (SPESOS)</h3>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Employment Contract Form</div>
                        <div class="box-description">Download or fill out your employment contract for SPES.</div>
                        <button class="get-started" onclick="showPage('spes-employment-contract-page')">Open Form</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Application Form</div>
                        <div class="box-description">Apply for the SPES program here.</div>
                        <button class="get-started" onclick="showPage('spes-application-form-page')">Open Form</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Oath of Undertaking Form</div>
                        <div class="box-description">Complete your Oath of Undertaking for SPES.</div>
                        <button class="get-started" onclick="showPage('spes-oath-of-undertaking-page')">Open Form</button>
                    </div>
                </div>
            </div>

            <div id="spes-employment-contract-page" class="page" style="display:none;">
                <div class="form-container-application" style="position:relative; z-index:1; text-align:center;">
                    <h2 style="margin-bottom:20px;">Employment Contract Form</h2>
                    <img src="../../../../images/Employment-contract.jpg" alt="Employment contract image" class="image" style="max-width:100%; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.15); margin-bottom:30px;">
                    <br>
                    <a href="../../../../download_assets/SPES-FORM-4-EMPLOYMENT-CONTRACT-1-1.docx" download class="submit-btn" style="width:auto; display:inline-block; margin-top:20px; text-decoration: none;">
                        Download Employment Contract
                    </a>
                    <br>
                    <button class="back-btn" style="margin-top:20px;" onclick="showPage('spes-page')">Back to SPESOS</button>
                </div>
            </div>

           <div id="spes-application-form-page" class="page" style="display:none; position:relative;">
            <div class="form-container-application" style="position:relative; z-index:1;">
                <img src="../../../../images/Peso_logo1.gif"  alt="SPES_Logo1" style="width: 80px; position:absolute; top: 130px; left:7%; opacity:1; pointer-events: none;">
                <img src="../../../../images/PESO_Logo.png"  alt="PESO_Logo" style="width: 80px; position:absolute; top: 135px; right:7%; opacity:1; pointer-events: none;">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" style="width: 550px; position:absolute; top:10%; left:50px; opacity:0.1; pointer-events: none;">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" style="width: 550px; position:absolute; top:43%; left:50px; opacity:0.1; pointer-events: none;">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" style="width: 550px; position:absolute; top:75%; left:50px; opacity:0.1; pointer-events: none;">
                <button class="back-btn" onclick="showPage('spes-page')">Back to SPESOS</button>
                <a href="../../../../download_assets/SPES-FORM-2-APPLICATION-FORM-1-1.docx" download class="submit-btn" style="padding: 8px 20px;width:auto; display:inline-block; margin-bottom:10px; right: 20px; position: absolute; top: 10px; text-align: center; text-decoration: none;">
                    Download SPES Application Form
                </a>
                <p class="title-description-p">REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF LABOR AND EMPLOYMENT<br>Regional Office No. VIII<br>PUBLIC EMPLOYMENT SERVICE OFFICE<br>SAN JULIAN, EASTERN SAMAR<br>City/Municipality/Province<br>SPECIAL PROGRAM FOR EMPLOYMENT OF STUDENTS (SPES)<br>(RA 7323, as amended by RAs 9547 and 10917)
</p>
                <h2 id="spes-application-form-title">Application Form</h2>
                <form enctype="multipart/form-data">
                    <h4>Personal Information</h4>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label class="label-application">Surname</label>
                            <input type="text" name="surname" class="input-field" required>
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">First Name</label>
                            <input type="text" name="firstname" class="input-field" required>
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Middle Name</label>
                            <input type="text" name="middlename" class="input-field">
                        </div>
                    </div>
                    <label class="label-application">GSIS Beneficiary/Relationship</label>
                    <input type="text" name="gsis_beneficiary" class="input-field">
                    <label class="label-application">Upload ID (.png/.jpeg)</label>
                    <input type="file" name="id_image" class="input-field file-field" accept="image/*">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label class="label-application">Date of Birth</label>
                            <input type="date" name="dob" class="input-field" required>
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Place of Birth</label>
                            <input type="text" name="place_of_birth" class="input-field">
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Citizenship</label>
                            <input type="text" name="citizenship" class="input-field">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label class="label-application">Contact Details/Cellphone No.</label>
                            <input type="text" name="contact" class="input-field">
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Email Address</label>
                            <input type="email" name="email" class="input-field">
                        </div>
                    </div>
                    <label class="label-application">Social Media Account (Facebook, Twitter, Instagram, etc.)</label>
                    <input type="text" name="social_media" class="input-field">

                    <label class="label-application">Status</label>
                    <div>
                        <label><input type="radio" name="status" value="Single" required> Single</label>
                        <label><input type="radio" name="status" value="Married"> Married</label>
                        <label><input type="radio" name="status" value="Widow/er"> Widow/er</label>
                        <label><input type="radio" name="status" value="Separated"> Separated</label>
                    </div>

                    <label class="label-application">Sex</label>
                    <div>
                        <label><input type="radio" name="sex" value="Male" required> Male</label>
                        <label><input type="radio" name="sex" value="Female"> Female</label>
                    </div>

                    <label class="label-application">Student Type</label>
                    <div>
                        <label><input type="radio" name="student_type" value="Student" required> Student</label>
                        <label><input type="radio" name="student_type" value="ALS Student"> ALS Student</label>
                        <label><input type="radio" name="student_type" value="Out-of-school (OSY)"> Out-of-school (OSY)</label>
                    </div>

                    <label class="label-application">Current Status of Parents (choose applicable)</label>
                    <div>
                        <label><input type="radio" name="parent_status" value="Living together" required> Living together</label>
                        <label><input type="radio" name="parent_status" value="Solo Parent"> Solo Parent</label>
                        <label><input type="radio" name="parent_status" value="Separated"> Separated</label>
                        <label><input type="radio" name="parent_status" value="Person With Disability"> Person With Disability</label>
                        <label><input type="radio" name="parent_status" value="Senior Citizen"> Senior Citizen</label>
                        <label><input type="radio" name="parent_status" value="Sugar Plantation Worker"> Sugar Plantation Worker</label>
                        <label><input type="radio" name="parent_status" value="Indigenous People"> Indigenous People</label>
                        <label><input type="radio" name="parent_status" value="Displaced Worker (Local)"> Displaced Worker (Local)</label>
                        <label><input type="radio" name="parent_status" value="Displaced Worker (OFW)"> Displaced Worker (OFW)</label>
                    </div>

                    <label class="label-application">Present Address</label>
                    <input type="text" name="present_address" class="input-field">
                    <label class="label-application">Permanent Address</label>
                    <input type="text" name="permanent_address" class="input-field">

                    <h4>Parental Information</h4>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label class="label-application">Father’s Name / Contact No.</label>
                            <input type="text" name="father_name_contact" class="input-field">
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Mother’s Maiden Name / Contact No.</label>
                            <input type="text" name="mother_name_contact" class="input-field">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div style="flex:1;">
                            <label class="label-application">Father’s Occupation</label>
                            <input type="text" name="father_occupation" class="input-field">
                        </div>
                        <div style="flex:1;">
                            <label class="label-application">Mother’s Occupation</label>
                            <input type="text" name="mother_occupation" class="input-field">
                        </div>
                    </div>

                        <h4>Educational Background</h4>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Name of School</th>
                                    <th>Degree Earned / Course</th>
                                    <th>Year/Level</th>
                                    <th>Date of Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Elementary</td>
                                    <td><input type="text" name="elem_school" class="input-field"></td>
                                    <td><input type="text" name="elem_degree" class="input-field"></td>
                                    <td><input type="text" name="elem_year" class="input-field"></td>
                                    <td><input type="text" name="elem_attendance" class="input-field"></td>
                                </tr>
                                <tr>
                                    <td>Secondary</td>
                                    <td><input type="text" name="sec_school" class="input-field"></td>
                                    <td><input type="text" name="sec_degree" class="input-field"></td>
                                    <td><input type="text" name="sec_year" class="input-field"></td>
                                    <td><input type="text" name="sec_attendance" class="input-field"></td>
                                </tr>
                                <tr>
                                    <td>Tertiary</td>
                                    <td><input type="text" name="ter_school" class="input-field"></td>
                                    <td><input type="text" name="ter_degree" class="input-field"></td>
                                    <td><input type="text" name="ter_year" class="input-field"></td>
                                    <td><input type="text" name="ter_attendance" class="input-field"></td>
                                </tr>
                                <tr>
                                    <td>Tech-Voc</td>
                                    <td><input type="text" name="tech_school" class="input-field"></td>
                                    <td><input type="text" name="tech_degree" class="input-field"></td>
                                    <td><input type="text" name="tech_year" class="input-field"></td>
                                    <td><input type="text" name="tech_attendance" class="input-field"></td>
                                </tr>
                            </tbody>
                        </table>

                        <h4>Documentary Requirements</h4>
                            <div class="doc-req-section">
                                <label><input type="checkbox" name="doc_birth_cert"> Photocopy of Birth Certificate or any document indicating date of birth or age (age must be 15-30)</label><br>
                                <label><input type="checkbox" name="doc_itr"> Photocopy of the latest Income Tax Return (ITR) of parents/legal guardian OR certification issued by BIR that the Parents/guardians are exempted from payment of tax OR original Certificate of Indigence OR original Certificate of Low Income issued by the Barangay/DSWD or CSWD where the applicant resides</label><br>
                                <label><input type="checkbox" name="doc_student"> For students, any of the following, in addition to requirements no. 1 and 2:</label>
                                <div style="margin-left:20px;">
                                    <label><input type="checkbox" name="doc_class_card"> a) Photocopy of proof of average passing grade such as (1) class card or (2) Form 138 of the previous semester or year immediately preceding the application</label><br>
                                    <label><input type="checkbox" name="doc_registrar_cert"> b) Original copy of Certification by the School Registrar as to passing grade immediately preceding semester/year if grades are not yet available</label>
                                </div>
                                <label><input type="checkbox" name="doc_osy"> For Out of School Youth (OSY), original copy of Certification as OSY issued by DSWD/CSWD or the authorized Barangay Official where the OSY resides, in addition to requirements no. 1 and 2.</label>
                            </div>

                        <h4>Special Skills</h4>
                        <input type="text" name="special_skills" class="input-field">

                        <h4>History of SPES Availment/Name of Establishment</h4>
                        <label><input type="checkbox" name="availment_1"> 1st Availment</label>
                        <label><input type="checkbox" name="availment_2"> 2nd Availment</label>
                        <label><input type="checkbox" name="availment_3"> 3rd Availment</label>
                        <label><input type="checkbox" name="availment_4"> 4th Availment</label>

                        <h4>Year</h4>
                        <label><input type="checkbox" name="year_1"> 1st Availment</label>
                        <label><input type="checkbox" name="year_2"> 2nd Availment</label>
                        <label><input type="checkbox" name="year_3"> 3rd Availment</label>
                        <label><input type="checkbox" name="year_4"> 4th Availment</label>

                        <h4>SPES ID No. (if applicable)</h4>
                        <label><input type="checkbox" name="spesid_1"> 1st Availment</label>
                        <label><input type="checkbox" name="spesid_2"> 2nd Availment</label>
                        <label><input type="checkbox" name="spesid_3"> 3rd Availment</label>
                        <label><input type="checkbox" name="spesid_4"> 4th Availment</label>

                        <button type="submit" class="submit-btn">Submit Application</button>
                    </form>
                </div>
            </div>

            <div id="spes-oath-of-undertaking-page" class="page" style="display:none;">
                <div class="form-container-application" style="position:relative; z-index:1; text-align:center;">
                    <h2 style="margin-bottom:20px;">Employment Contract Form</h2>
                    <img src="../../../../images/spesos-oath-of-undertaking.jpg" alt="Employment contract image" class="image" style="max-width:100%; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.15); margin-bottom:30px;">
                    <br>
                    <a href="../../../../download_assets/SPES-FORM-2-A-OATH-OF-UNDERTAKING-.docx" download class="submit-btn" style="width:auto; display:inline-block; margin-top:20px; text-decoration: none;">
                        Download Oath-of-Undertaking Form
                    </a>
                    <br>
                    <button class="back-btn" style="margin-top:20px;" onclick="showPage('spes-page')">Back to SPESOS</button>
                </div>
            </div>

            <div id="history-page" class="page">
                <div class="application-history">
                    <h2 class="history-h2">Application History</h2>
                    <p class="history-p">Review your previous scholarship applications</p>
                    
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Application ID</th>
                                <th>Scholarship</th>
                                <th>Date Applied</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>APP-2023-001</td>
                                <td>TDP Scholarship</td>
                                <td>Jan 15, 2023</td>
                                <td class="status-approved">Approved</td>
                                <td>
                                    <button class="btn btn-outline" onclick="viewDetails('APP-2023-001')">View Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP-2023-003</td>
                                <td>TES Scholarship</td>
                                <td>Mar 10, 2023</td>
                                <td class="status-pending">Pending</td>
                                <td>
                                    <button class="btn btn-outline" onclick="viewDetails('APP-2023-003')">View Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td>APP-2022-001</td>
                                <td>DOST Scholarship</td>
                                <td>Aug 05, 2022</td>
                                <td class="status-rejected">Rejected</td>
                                <td>
                                    <button class="btn btn-outline" onclick="viewDetails('APP-2022-001')">View Details</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

<div id="communication-page" class="page">
    <h2>Chat with Admin</h2>
    <div class="chat-container">
        <div class="chat-messages" id="chatMessages">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['sender'] === 'user' ? 'user-message' : 'admin-message'; ?>">
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            <?php if ($message['sender'] === 'user'): ?>
                                <div class="message-options">
                                    <button class="options-btn" onclick="toggleMessageOptions(<?php echo $message['id']; ?>)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="options-menu" id="options-<?php echo $message['id']; ?>">
                                        <button onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="message-timestamp">
                            <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-messages">
                    <p>No messages yet. Start a conversation with the admin!</p>
                </div>
            <?php endif; ?>
        </div>
        <form class="chat-input" method="POST" enctype="multipart/form-data" autocomplete="off">
            <div class="upload-container" style="position: relative;">
                <button type="button" class="upload-btn" onclick="showUploadPopup()" title="Upload">
                    <i class="fas fa-paperclip"></i>
                </button>
                <input type="file" id="chatUpload" name="chat_upload[]" style="display:none;" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                <div id="uploadPopup" class="upload-popup">
                    <span style="display:block;text-align:center;margin-bottom:10px;">Upload documents or images</span>
                    <button type="button" class="btn" style="display:block;margin:0 auto;" onclick="triggerFileInput()">Choose File</button>
                </div>
            </div>
            <textarea name="concern_message" placeholder="Type your message to admin..." rows="1"></textarea>
            <button type="submit" name="send_concern">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<div id="deleteMessageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Delete Message</div>
        <div class="modal-body">
            <p>Are you sure you want to delete this message? This action cannot be undone.</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

            <div id="scholarships-page" class="page">
                <div class="scholarship-list">
                    <h2>Available Scholarships</h2>
                    <p>Browse and apply for available scholarship programs</p>
                    
                    <?php foreach ($scholarships as $scholarship): ?>
                        <div class="scholarship-card">
                            <div class="scholarship-header">
                                <div class="scholarship-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
                            </div>
                            <div class="scholarship-body">
                                <div class="scholarship-info">
                                    <p><?php echo htmlspecialchars($scholarship['description']); ?></p>
                                </div>
                            </div>
                            <div class="scholarship-actions">
                                <button class="btn btn-outline" 
                                    onclick='showDetails(
                                        <?php echo json_encode($scholarship["title"]); ?>, 
                                        <?php echo json_encode(nl2br($scholarship["requirements"])); ?>, 
                                        <?php echo json_encode(nl2br($scholarship["benefits"])); ?>, 
                                        <?php echo json_encode(nl2br($scholarship["eligibility"])); ?>
                                    )'>View Details</button>
                                <button class="btn btn-primary" onclick="showApplicationForm('<?php echo htmlspecialchars($scholarship['title']); ?>')">Apply Now</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="application-form-page" class="page">
                <div class="form-container-application">
                    <button class="back-btn" onclick="showScholarshipsPage()">Back to Scholarships</button>
                    <h2 id="application-form-title">Scholarship Application Form</h2>
                    <form>
                        <label class="label-application" for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" class="input-field" placeholder="Enter your full name" required />

                        <label class="label-application" for="birthdate">Date of Birth</label>
                        <input type="date" id="birthdate" name="birthdate" class="input-field" required />

                        <label class="label-application" for="address">Complete Address</label>
                        <textarea id="address" name="address" class="input-field textarea-field" rows="3" placeholder="Enter your complete address" required></textarea>

                        <label class="label-application" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="input-field" placeholder="Enter your email" required />

                        <label class="label-application" for="contact">Contact Number</label>
                        <input type="tel" id="contact" name="contact" class="input-field" placeholder="Enter your contact number" required />

                        <label class="label-application" for="school">Current School</label>
                        <input type="text" id="school" name="school" class="input-field" placeholder="Enter your current school" required />

                        <label class="label-application" for="course">Course / Program</label>
                        <input type="text" id="course" name="course" class="input-field" placeholder="Enter your course or program" required />

                        <label class="label-application" for="year">Year Level</label>
                        <select id="year" name="year" class="input-field select-field" required>
                            <option value="">--Select Year Level--</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>

                        <label class="label-application" for="income">Family Monthly Income (PHP)</label>
                        <input type="number" id="income" name="income" class="input-field" placeholder="Enter family monthly income" required />

                        <label class="label-application" for="documents">Upload Requirements (PDF/JPEG)</label>
                        <input type="file" id="documents" name="documents" class="input-field file-field" accept=".pdf,.jpg,.jpeg,.png" multiple required />

                        <button type="submit" class="submit-btn">Submit Application</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <div class="modal-header" id="modalTitle"></div>
            <div class="modal-body">
                <h3>Requirements:</h3>
                <p id="modalRequirements"></p>
                <h3>Benefits:</h3>
                <p id="modalBenefits"></p>
                <h3>Eligibility Criteria:</h3>
                <p id="modalEligibility"></p>
            </div>
        </div>
    </div>

    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeNotificationModal()">&times;</span>
            <div class="modal-header">Notifications</div>
            <div class="modal-body">
                <?php if (!empty($notifications)): ?>
                    <?php 
                    $currentDate = null;
                    foreach ($notifications as $notification): 
                        $notificationDate = date('F j, Y', strtotime($notification['created_at'])); 
                    ?>
                        <?php if ($currentDate !== $notificationDate): ?>
                            <?php if ($currentDate !== null): ?>
                                <hr style="border: 1px solid #ccc;" />
                            <?php endif; ?>
                            <h4><?php echo $notificationDate; ?></h4>
                            <?php $currentDate = $notificationDate;?>
                        <?php endif; ?>
                        
                        <li>
                            <strong><?php echo date('h:i A', strtotime($notification['created_at'])); ?>:</strong>
                            <div style="white-space: pre-wrap;">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <?php if (!empty($notification['deadline'])): ?>
                                <div style="margin-top: 5px; color: red;">
                                    <strong>Deadline:</strong> <?php echo date('F j, Y h:i A', strtotime($notification['deadline'])); ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No notifications available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function toggleMenu() {
        var menu = document.getElementById("dropdownMenu");
        var chevron = document.getElementById("chevronIcon");
        const isOpen = menu.classList.toggle("show");
        if (isOpen) {
            chevron.classList.add("open");
        } else {
            chevron.classList.remove("open");
        }
    }
    window.onclick = function(event) {
        if (!event.target.matches('.user-icon') && !event.target.matches('.fa-chevron-down')) {
            var dropdowns = document.getElementsByClassName("dropdown-menu");
            var chevron = document.getElementById("chevronIcon");
            for (var i = 0; i < dropdowns.length; i++) {
                dropdowns[i].classList.remove("show");
            }
            if (chevron) {
                chevron.classList.remove("open");
            }
        }
    }

    function showDetails(title, requirements, benefits, eligibility) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalRequirements').innerHTML = requirements;
        document.getElementById('modalBenefits').innerHTML = benefits;
        document.getElementById('modalEligibility').innerHTML = eligibility;
        document.getElementById('detailsModal').style.display = "block";
    }

    function closeModal() {
        document.getElementById('detailsModal').style.display = "none";
    }

function showPage(pageId) {
        document.querySelectorAll('.page').forEach(page => {
            page.style.display = 'none';
            page.classList.remove('active');
        });

        window.location.hash = pageId;
        document.getElementById(pageId).style.display = 'block';
        document.getElementById(pageId).classList.add('active');

        switch (pageId) {
            case 'home-page':
                highlightActiveNav('home-nav');
                break;
            case 'history-page':
                highlightActiveNav('history-nav');
                break;
            case 'scholarships-page':
                highlightActiveNav('scholarships-nav');
                break;
            case 'communication-page':
                highlightActiveNav('communication-nav');
                setTimeout(() => {
                    var chatMessages = document.getElementById('chatMessages');
                    if (chatMessages) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }, 100);
                break;
            case 'spes-page':
                highlightActiveNav('spes-nav');
                break;
        }
    }
        function openNotificationModal() {
        document.getElementById('notificationModal').style.display = "block";
        fetch('mark_notification_read.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('notificationBadge').style.display = 'none';
                    updateNotificationDot();
                }
            });
    }




    function closeNotificationModal() {
        document.getElementById('notificationModal').style.display = "none";

        document.getElementById('notificationBadge').style.display = 'none';
    }

    function updateNotificationDot() {
        fetch('get_unread_count_notifcation.php')
            .then(response => response.json())
            .then(data => {
                const notificationBadge = document.getElementById('notificationBadge');
                if (data.status === 'success' && data.unread_count > 0) {
                    notificationBadge.style.display = 'flex';
                    notificationBadge.textContent = data.unread_count; // Show the count
                } else {
                    notificationBadge.style.display = 'none';
                    notificationBadge.textContent = '';
                }
            })
            .catch(() => {
                document.getElementById('notificationBadge').style.display = 'none';
                document.getElementById('notificationBadge').textContent = '';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateNotificationDot();
        setInterval(updateNotificationDot, 10000);
    });

    document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });

    document.getElementById('home-page').style.display = 'block';
    highlightActiveNav('home-nav');

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    window.history.pushState(null, '', window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, '', window.location.href);
    };

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleBtn = document.getElementById('toggleSidebar');
    const toggleIcon = document.getElementById('toggleIcon');
    
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
        
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    });
});


function showApplicationForm(scholarshipTitle) {
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    document.getElementById('application-form-page').style.display = 'block';
    document.getElementById('application-form-title').textContent = `Apply for ${scholarshipTitle}`;
}

function showScholarshipsPage() {
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    document.getElementById('scholarships-page').style.display = 'block';
    highlightActiveNav('scholarships-nav');
}

function highlightActiveNav(navId) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.getElementById(navId).classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    var chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    function scrollChatToBottom() {
        var chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    if (document.getElementById('communication-page').classList.contains('active')) {
        scrollChatToBottom();
    }

    window.showPage = (function(origShowPage) {
        return function(pageId) {
            origShowPage(pageId);
            if (pageId === 'communication-page') {
                setTimeout(scrollChatToBottom, 100);
            }
        };
    })(window.showPage || function(){});
});

let messageToDelete = null;

function toggleMessageOptions(messageId) {
    document.querySelectorAll('.options-menu').forEach(menu => {
        if (menu.id !== `options-${messageId}`) {
            menu.classList.remove('show');
        }
    });

    const menu = document.getElementById(`options-${messageId}`);
    menu.classList.toggle('show');
}

function deleteMessage(messageId) {
    messageToDelete = messageId;
    document.getElementById('deleteMessageModal').style.display = "block";
    
    document.getElementById(`options-${messageId}`).classList.remove('show');
}

function closeDeleteModal() {
    document.getElementById('deleteMessageModal').style.display = "none";
    messageToDelete = null;
}

function confirmDelete() {
    if (messageToDelete) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const messageIdInput = document.createElement('input');
        messageIdInput.type = 'hidden';
        messageIdInput.name = 'message_id';
        messageIdInput.value = messageToDelete;
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_message';
        deleteInput.value = '1';
        
        form.appendChild(messageIdInput);
        form.appendChild(deleteInput);
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.message-options')) {
        document.querySelectorAll('.options-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});


function showUploadPopup() {
    document.getElementById('uploadPopup').style.display = 'block';
}
function closeUploadPopup() {
    document.getElementById('uploadPopup').style.display = 'none';
}
function triggerFileInput() {
    document.getElementById('chatUpload').click();
    closeUploadPopup();
}
document.addEventListener('click', function(event) {
    const popup = document.getElementById('uploadPopup');
    const btn = document.querySelector('.upload-btn');
    if (popup && !popup.contains(event.target) && !btn.contains(event.target)) {
        popup.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const pageId = window.location.hash.substring(1);
        if (document.getElementById(pageId)) {
            showPage(pageId);
        }
    }
});
</script>
</body>
</html>