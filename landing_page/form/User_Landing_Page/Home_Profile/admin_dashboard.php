<?php
define('BASE_URL', 'http://localhost/form_prac/');
include '../../../../connect.php';

session_start();

// Look for 'admin_id' in the session, not 'user_id'
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../signin.php");
    exit();
}

// Use the correct session variable to get the admin's ID
$admin_id = $_SESSION['admin_id'];

$sql = "SELECT * FROM admin WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $admin_name = $admin['fname'] . " " . $admin['lname'];
    // Corrected path separator for web compatibility
    $profile_pic = $admin['profile_pic'] ?? 'images/admin-default.png';
} else {
    // If no admin is found, the session is invalid.
    // Destroy the session and redirect to the login page to be safe.
    session_unset();
    session_destroy();
    header("Location: ../../signin.php");
    exit();
}

if (isset($_POST['send_message'])) {
    $message = $_POST['message'];
    $deadline = isset($_POST['deadline']) && !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    $insertMessageSql = "INSERT INTO notifications (message, deadline, user_id, status) VALUES (?, ?, NULL, 'unread')";
    $insertMessageStmt = $conn->prepare($insertMessageSql);

    if ($insertMessageStmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    $insertMessageStmt->bind_param("ss", $message, $deadline);

    if ($insertMessageStmt->execute() === false) {
        die("Error executing statement: " . $insertMessageStmt->error);
    }

    $_SESSION['message_sent'] = true;

    header("Location: admin_dashboard.php#send-updates-page");
    exit();
}

// --- START: MODIFIED APPROVE/REJECT LOGIC WITH EMAIL NOTIFICATION ---
if (isset($_POST['approve_application']) || isset($_POST['reject_application'])) {
    $applicationId = $_POST['application_id'];
    $newStatus = isset($_POST['approve_application']) ? 'approved' : 'rejected';

    // 1. Fetch user and scholarship info for the email
    $infoSql = "SELECT u.Email, u.Fname, u.Lname, s.title 
                FROM applications a
                JOIN user u ON a.user_id = u.user_id
                JOIN scholarships s ON a.scholarship_id = s.scholarship_id
                WHERE a.application_id = ?";
    $infoStmt = $conn->prepare($infoSql);
    $infoStmt->bind_param("i", $applicationId);
    $infoStmt->execute();
    $infoResult = $infoStmt->get_result()->fetch_assoc();

    if ($infoResult) {
        // 2. Update the application status in the database
        $updateSql = "UPDATE applications SET status = ? WHERE application_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $newStatus, $applicationId);
        $stmt->execute();

        // 3. Prepare and send the email
        $to = $infoResult['Email'];
        $name = $infoResult['Fname'] . ' ' . $infoResult['Lname'];
        $scholarshipTitle = $infoResult['title'];

        if ($newStatus == 'approved') {
            $subject = "Your Scholarship Application has been Approved";
            $body = "Hello {$name},\n\nCongratulations! Your application for the '{$scholarshipTitle}' scholarship has been approved.\n\nPlease log in to your account for more details.\n\nThank you,\nPESO San Julian MIS";
        } else { // rejected
            $subject = "Update on your Scholarship Application";
            $body = "Hello {$name},\n\nWe regret to inform you that your application for the '{$scholarshipTitle}' scholarship has been rejected at this time.\n\nThank you for your interest.\n\nSincerely,\nPESO San Julian MIS";
        }
        
        require '../../../../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'edlexus59@gmail.com';
            $mail->Password   = 'nfsv eqpj sfur sjsw';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('edlexus59@gmail.com', 'PESO San Julian MIS');
            $mail->addAddress($to, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            // Optional: handle mail error, e.g., log it
        }
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
// --- END: MODIFIED APPROVE/REJECT LOGIC ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_scholarship'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $requirements = $_POST['requirements'];
        $benefits = $_POST['benefits'];
        $eligibility = $_POST['eligibility'];
        $slots = $_POST['number_of_slots'];
        
        $insertSql = "INSERT INTO scholarships (title, description, requirements, benefits, eligibility, number_of_slots, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sssssi", $title, $description, $requirements, $benefits, $eligibility, $slots); 
        if ($insertStmt->execute()) {
            $_SESSION['scholarship_added'] = true;
        }
        
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['delete_scholarship'])) {
        $id = $_POST['id'];
        $deleteSql = "DELETE FROM scholarships WHERE scholarship_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $_SESSION['scholarship_deleted'] = true;
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['publish_scholarship'])) {
        $id = $_POST['id'];
        $uploadSql = "UPDATE scholarships SET status = 'active' WHERE scholarship_id = ?";
        $uploadStmt = $conn->prepare($uploadSql);
        $uploadStmt->bind_param("i", $id);
        $uploadStmt->execute();
        $_SESSION['scholarship_published'] = true;
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['update_slots'])) { 
        $id = $_POST['scholarship_id'];
        $newSlots = intval($_POST['new_slots']);
        $updateSql = "UPDATE scholarships SET number_of_slots = ? WHERE scholarship_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $newSlots, $id);
        $updateStmt->execute();
        $_SESSION['slots_updated'] = true;
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    }
}

if (isset($_POST['delete_message'])) {
    $messageId = $_POST['message_id'];

    $deleteMessageSql = "DELETE FROM notifications WHERE notification_id = ?";
    $deleteMessageStmt = $conn->prepare($deleteMessageSql);
    $deleteMessageStmt->bind_param("i", $messageId);
    $deleteMessageStmt->execute();

    $_SESSION['message_deleted'] = true;

    header("Location: admin_dashboard.php#send-updates-page");
    exit();
}


// --- START: ORIGINAL CODE FOR CHATS ---
// Fetch scholarship groups that have AT LEAST ONE 'approved' applicant to act as group chats.
$scholarshipsSql = "
    SELECT DISTINCT s.scholarship_id, s.title 
    FROM scholarships s
    JOIN applications a ON s.scholarship_id = a.scholarship_id
    WHERE a.status = 'approved'
";
$scholarshipResult = $conn->query($scholarshipsSql);
$scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);
// --- END: ORIGINAL CODE FOR CHATS ---

// --- START: FIX FOR HOME PAGE SCHOLARSHIP COUNT ---
// This new query accurately counts ALL scholarships for the home page dashboard.
$totalListedScholarshipsSql = "SELECT COUNT(scholarship_id) as total FROM scholarships";
$totalListedScholarshipsResult = $conn->query($totalListedScholarshipsSql);
$totalListedScholarshipsCount = $totalListedScholarshipsResult->fetch_assoc()['total'] ?? 0;
// --- END: FIX FOR HOME PAGE SCHOLARSHIP COUNT ---


$messagesSql = "SELECT * FROM notifications ORDER BY created_at DESC";
$messagesResult = $conn->query($messagesSql);
$messages = $messagesResult->fetch_all(MYSQLI_ASSOC);

// Fetch individual users with concerns (who are not part of a group chat context)
$usersWithConcerns = [];
$userQuery = $conn->query("SELECT DISTINCT u.user_id, u.Fname, u.Lname, u.profile_pic FROM user u JOIN concerns c ON u.user_id = c.user_id WHERE c.scholarship_id IS NULL");
while ($row = $userQuery->fetch_assoc()) {
    $usersWithConcerns[] = $row;
}

// Handle chat selection (Group or User)
$selectedGroupId = isset($_GET['chat_group']) ? intval($_GET['chat_group']) : null;
$selectedUserId = isset($_GET['chat_user']) ? intval($_GET['chat_user']) : null;
$chatMessages = [];
$chatTitle = 'Select a conversation';

if ($selectedGroupId) {
    // Fetch group messages from 'concerns' table
    $stmt = $conn->prepare("SELECT * FROM concerns WHERE scholarship_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $selectedGroupId);
    $stmt->execute();
    $chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get scholarship title for the header
    $schTitleStmt = $conn->prepare("SELECT title FROM scholarships WHERE scholarship_id = ?");
    $schTitleStmt->bind_param("i", $selectedGroupId);
    $schTitleStmt->execute();
    $schResult = $schTitleStmt->get_result();
    if($schRow = $schResult->fetch_assoc()){
        $chatTitle = htmlspecialchars($schRow['title']) . " (Group)";
    }

} elseif ($selectedUserId) {
    // Fetch 1-on-1 messages from 'concerns' table
    $stmt = $conn->prepare("SELECT * FROM concerns WHERE user_id = ? AND scholarship_id IS NULL ORDER BY created_at ASC");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get user name for the header
    $userQuery = $conn->prepare("SELECT Fname, Lname FROM user WHERE user_id = ?");
    $userQuery->bind_param("i", $selectedUserId);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    if($user = $userResult->fetch_assoc()){
        $chatTitle = "Chat with " . htmlspecialchars($user['Fname'] . ' ' . $user['Lname']);
    }
}


if (isset($_POST['send_admin_reply'])) {
    $reply = trim($_POST['admin_reply']);
    // Use the `$admin_id` variable that was already validated at the top of the script.

    if (!empty($reply)) {
        if (isset($_POST['chat_group_id']) && !empty($_POST['chat_group_id'])) {
            // It's a group message for a scholarship
            $chat_group_id = intval($_POST['chat_group_id']);
            // The `user_id` should be NULL because this message is from an admin to a group.
            $stmt = $conn->prepare("INSERT INTO concerns (admin_id, scholarship_id, sender, message, user_id) VALUES (?, ?, 'admin', ?, NULL)");
            $stmt->bind_param("iis", $admin_id, $chat_group_id, $reply);
            $stmt->execute();
            header("Location: admin_dashboard.php?chat_group=$chat_group_id#user-concerns-page");
            exit();

        } elseif (isset($_POST['chat_user_id']) && !empty($_POST['chat_user_id'])) {
            // It's a 1-on-1 message to a specific user
            $chat_user_id = intval($_POST['chat_user_id']);
            $stmt = $conn->prepare("INSERT INTO concerns (user_id, admin_id, sender, message) VALUES (?, ?, 'admin', ?)");
            $stmt->bind_param("iis", $chat_user_id, $admin_id, $reply);
            $stmt->execute();
            header("Location: admin_dashboard.php?chat_user=$chat_user_id#user-concerns-page");
            exit();
        }
    }
}


if (isset($_POST['delete_admin_message']) && isset($_POST['message_id'])) {
    $message_id = intval($_POST['message_id']);
    
    // Determine redirect URL
    $redirect_url = "admin_dashboard.php#user-concerns-page";
    if (isset($_POST['chat_user_id']) && !empty($_POST['chat_user_id'])) {
        $redirect_url = "admin_dashboard.php?chat_user=" . intval($_POST['chat_user_id']) . "#user-concerns-page";
    } elseif (isset($_POST['chat_group_id']) && !empty($_POST['chat_group_id'])) {
         $redirect_url = "admin_dashboard.php?chat_group=" . intval($_POST['chat_group_id']) . "#user-concerns-page";
    }

    $stmt = $conn->prepare("DELETE FROM concerns WHERE id = ? AND sender = 'admin'");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: " . $redirect_url);
    exit();
}


if (isset($_POST['approve_user'])) {
    $uid = intval($_POST['user_id']);

    $result = $conn->query("SELECT Email, Fname, Mname, Lname, status FROM user WHERE user_id=$uid");
    $user = $result->fetch_assoc();

    if (!$user || $user['status'] === 'approved') {
        header("Location: admin_dashboard.php#user-request-page");
        exit();
    }

    $to = $user['Email'];

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Error: The user\\'s email address is in an invalid format.'); window.location.href='admin_dashboard.php#user-request-page';</script>";
        exit();
    }
    
    $domain = substr($to, strpos($to, '@') + 1);
    if (!checkdnsrr($domain, "MX")) {
        echo "<script>alert('Error: The user\\'s email domain does not have a valid mail server. Approval failed.'); window.location.href='admin_dashboard.php#user-request-page';</script>";
        exit();
    }

    $conn->query("UPDATE user SET status='approved' WHERE user_id=$uid");

    $result = $conn->query("SELECT Email, Fname, Mname, Lname FROM user WHERE user_id=$uid");
    $user = $result->fetch_assoc();
    $to = $user['Email'];
    $name = $user['Fname'] . ' ' . $user['Lname'];

    require '../../../../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'edlexus59@gmail.com';
    $mail->Password = 'nfsv eqpj sfur sjsw';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('edlexus59@gmail.com', 'PESO San Julian MIS');
    $mail->addAddress($to, $name);
    $mail->Subject = 'Account Approved';
    $mail->Body = "Hello {$user['Fname']} {$user['Lname']},\n\nYour account has been approved by the admin. You may now log in and use the system.\n\nThank you!";
    $mail->send();

    header("Location: admin_dashboard.php#user-request-page");
    exit();
}

if (isset($_POST['reject_user'])) {
    $uid = intval($_POST['user_id']);

    // 1. Get user info before updating status
    $userResult = $conn->query("SELECT Email, Fname, Lname FROM user WHERE user_id=$uid");
    $user = $userResult->fetch_assoc();

    if ($user) {
        // 2. Update status to 'rejected'
        $conn->query("UPDATE user SET status='rejected' WHERE user_id=$uid");

        // 3. Send the REJECTION email
        $to = $user['Email'];
        $name = $user['Fname'] . ' ' . $user['Lname'];
        $subject = "Update on Your Account Registration";
        $body = "Hello {$name},\n\nWe regret to inform you that your account registration has been rejected at this time.\n\nIf you believe this is a mistake, please contact our support.\n\nSincerely,\nPESO San Julian MIS";

        require '../../../../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'edlexus59@gmail.com';
            $mail->Password   = 'nfsv eqpj sfur sjsw';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('edlexus59@gmail.com', 'PESO San Julian MIS');
            $mail->addAddress($to, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            // Optional: Handle email sending errors
        }
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// --- START: MODIFIED REJECT WITH MESSAGE LOGIC WITH EMAIL NOTIFICATION ---
if (isset($_POST['reject_application_with_message'])) {
    $applicationId = $_POST['application_id'];
    $rejectionMessage = $_POST['rejection_message'];

    // 1. Fetch user and scholarship info
    $infoSql = "SELECT u.Email, u.Fname, u.Lname, s.title 
                FROM applications a
                JOIN user u ON a.user_id = u.user_id
                JOIN scholarships s ON a.scholarship_id = s.scholarship_id
                WHERE a.application_id = ?";
    $infoStmt = $conn->prepare($infoSql);
    $infoStmt->bind_param("i", $applicationId);
    $infoStmt->execute();
    $infoResult = $infoStmt->get_result()->fetch_assoc();

    if ($infoResult) {
        // 2. Update DB
        $updateSql = "UPDATE applications SET status = 'rejected', rejection_message = ? WHERE application_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $rejectionMessage, $applicationId);
        $stmt->execute();

        // 3. Prepare and send email
        $to = $infoResult['Email'];
        $name = $infoResult['Fname'] . ' ' . $infoResult['Lname'];
        $scholarshipTitle = $infoResult['title'];

        $subject = "Update on your Scholarship Application";
        $body = "Hello {$name},\n\nWe regret to inform you that your application for the '{$scholarshipTitle}' scholarship has been rejected.\n\nReason provided: {$rejectionMessage}\n\nThank you for your interest.\n\nSincerely,\nPESO San Julian MIS";
        
        require '../../../../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'edlexus59@gmail.com';
            $mail->Password   = 'nfsv eqpj sfur sjsw';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('edlexus59@gmail.com', 'PESO San Julian MIS');
            $mail->addAddress($to, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            // Optional: handle mail error
        }
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
// --- END: MODIFIED REJECT WITH MESSAGE LOGIC ---

$viewApprovedScholarshipId = isset($_GET['view_approved']) ? intval($_GET['view_approved']) : null;
$approved_applicants = [];
$approved_scholarship_title = '';

if ($viewApprovedScholarshipId) {
    // Fetch scholarship title
    $schTitleSql = "SELECT title FROM scholarships WHERE scholarship_id = ?";
    $schStmt = $conn->prepare($schTitleSql);
    $schStmt->bind_param("i", $viewApprovedScholarshipId);
    $schStmt->execute();
    $schResult = $schStmt->get_result();
    if ($schRow = $schResult->fetch_assoc()) {
        $approved_scholarship_title = $schRow['title'];
    }

    // --- START: SEARCH LOGIC FOR APPROVED APPLICANTS ---
    $searchApprovedTerm = isset($_GET['search_approved_name']) ? trim($_GET['search_approved_name']) : '';
    $searchApprovedSql = '%' . $searchApprovedTerm . '%';

    $approvedSql = "SELECT u.*, a.application_id 
                    FROM user u 
                    JOIN applications a ON u.user_id = a.user_id 
                    WHERE a.scholarship_id = ? AND a.status = 'approved'";

    if (!empty($searchApprovedTerm)) {
        $approvedSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
    }

    $apprStmt = $conn->prepare($approvedSql);

    if (!empty($searchApprovedTerm)) {
        $apprStmt->bind_param("is", $viewApprovedScholarshipId, $searchApprovedSql);
    } else {
        $apprStmt->bind_param("i", $viewApprovedScholarshipId);
    }
    // --- END: SEARCH LOGIC FOR APPROVED APPLICANTS ---

    $apprStmt->execute();
    $result = $apprStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $approved_applicants[] = $row;
    }
}

// Corrected query to get all applications for a specific scholarship
$viewScholarshipId = isset($_GET['view_scholarship']) ? intval($_GET['view_scholarship']) : null;
$applicants = [];
if ($viewScholarshipId) {
    $applicantsSql = "SELECT a.*, u.Fname, u.Lname FROM applications a JOIN user u ON a.user_id = u.user_id WHERE a.scholarship_id = ?";
    $stmt = $conn->prepare($applicantsSql);
    $stmt->bind_param("i", $viewScholarshipId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $applicants[] = $row;
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
    <link rel="icon" type="image/x-icon" href="../../../../assets/PESO Logo Assets.png"  />
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

.logo-container {
    display: flex;
    align-items: center;
    margin-left: 20px;
}

.logo {
    height: 50px;
    margin-right: 10px;
}

.san-julian-logo {
    height: 58px;
    margin-right: 10px;
}

.title {
    font-size: 20px !important;
    font-weight: bold;
    color: white;
}

.right-nav {
    display: flex;
    align-items: center;
    margin-right: 25px;
}

.user-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: transparent;
}

.user-name {
    color: white;
    font-size: 12px;
    margin-right: 10px;
}

.menu-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dropdown-menu {
    display: block;
    opacity: 0;
    transform: translateY(-10px);
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
    position: absolute;
    background-color: white;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 15px 30px;
    top: 60px;
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
    padding: 8px 8;
    font-size: 10px;
}

.dropdown-menu a:hover {
    background-color: #f4f4f4;
}

.container {
    display: flex;
    flex: 1;
    padding-top: 50px;
}

.sidebar {
            background: #090549;
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

.main-content {
    margin-left: 250px;
    padding: 30px;
    flex: 1;
    box-sizing: border-box;
    transition: margin-left 0.3s ease;
}

.dashboard-boxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
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

.view-details {
    background-color: #090549;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 15px;
    cursor: pointer;
    font-size: 10px;
    margin-top: 10px;
}

.view-details:hover {
    background:rgb(12, 5, 105);
}

    .scholarship-form {
        margin: 20px 0;
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    .scholarship-form h3 {
        color: #090549;
        margin-top: 0;
        font-size: 20px;
    }
    .scholarship-form input,
    .scholarship-form textarea,
    .scholarship-form button {
        display: block;
        width: 98%;
        border-radius: 10px;
        margin-bottom: 10px;
        padding: 14px;
        font-size: 10px;
    }
    .scholarship-form button {
        background-color: #090549;
        color: white;
        border: none;
        width: 100%;
        cursor: pointer;
    }
    .scholarship-form button:hover {
        background-color: #10087c;
    }
    .scholarship-list {
        margin: 20px 0;
    }
    .scholarship-item {
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 10px;
    }
    .scholarship-actions button {
        margin-right: 5px;
    }


.send-updates-form textarea {
    width: 98%;
    font-size: 12px;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #ccc;
}

.send-updates-form input[type="datetime-local"] {
    margin-top: 10px;
    width: 100%;
    font-size: 12px;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.send-updates-form button {
    margin-top: 10px;
    width: 100%;
    background-color: #090549;
    color: white;
    font-size: 10px;
    padding: 12px;
    border: none;
    border-radius: 14px;
    cursor: pointer;
}

.send-updates-form button:hover {
    background-color: #10087c;
}

.main-title-send-updates {
    color: black;
    margin-top: 0;
    font-size: 25px;
    margin-bottom: 20px;
    margin-top: 20px;
}

.send-updates-h3 {
    color:black;
    margin-top: 0;
    font-size: 15px;
}

.sent-messages {
    margin-top: 20px;
    font-size: 10px;
}

.message-sent-h3 {
    color: #090549;
    margin-top: 30;
    font-size: 15px;
}

.message-card h3 {
    color: #090549;
    margin-top: 0;
    font-size: 15px;
}

.message-item {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-bottom: 10px;
    background-color: #f9f9f9;
}

.btn-delete-message {
    background-color:#f44336; 
    color: white;
    font-size: 10px !important;
    padding: 8px 14px !important;
    border: none;
    border-radius: 14px !important;
    cursor: pointer;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.btn-delete-message:hover {
    background-color:#d32f2f;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.scholarship-card {
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.scholarship-card h3 {
    color: #090549;
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 15px;
}

.scholarship-details {
    margin-bottom: 15px;
}

.scholarship-details p {
    margin: 5px 0;
    font-size: 10px;
    align-items: center;
}

.scholarship-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 10px;
}

.status-pending {
    background-color: #FFF3CD;
    color: #856404;
}

.status-active {
    background-color: #D4EDDA;
    color: #155724;
}

.scholarship-footer {
    margin-top: 20px;
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    flex-wrap: wrap;
}

.scholarship-footer button {
    padding: 8px 15px;
    font-size: 14px;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    transition: background 0.3s ease;
}

.btn-publish {
    background-color: #28A745;
    border-radius: 14px !important;
    font-size: 10px !important;
}

.btn-publish:hover {
    background-color: #218838;
}

.btn-primary {
    background-color: #090549;
    color: white;
}

.btn-primary:hover {
    background-color: #10087c;
}

.btn-danger {
    background-color: #f44336;
}

.btn-danger:hover {
    background-color: #d32f2f;
}

.btn-success {
    background-color: #4CAF50;
}

.btn-success:hover {
    background-color: #388E3C;
}

.page {
    display: none;
}

.page.active {
    display: block;
}

.message-card {
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.message-card h3 {
    color: #090549;
    margin-top: 0;
    font-size: 18px;
}

.message-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    background-color: #D4EDDA;
    color: #155724;
    margin-bottom: 10px;
}

.message-footer {
    margin-top: 10px;
}

.message-footer form {
    display: inline;
}

.btn-delete-scholarship {
    background-color: #f44336;
    color: white;
    font-size: 10px !important;
    padding: 8px 14px !important;
    border: none;
    border-radius: 14px !important;
    cursor: pointer;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.btn-delete-scholarship:hover {
    background-color: #d32f2f;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.main-title-scholar {
    color: black;
    margin-top: 20px;
    font-size: 25px;
    margin-bottom: 20px;
}

.h1-home-welcome {
    color: black;
    margin-top: 20px;
    margin-left : 10px;
    font-size: 25px;
    margin-bottom: 20px;
}

.h1-title-appManagement {
    color: black;
    margin-top: 20px;
    font-size: 25px;
    margin-bottom: 20px;
}

.p-description-appM {
    color: black;
    margin-top: 0;
    font-size: 14px;
    margin-bottom: 20px;
}

.scholarship-form h3 {
    color:rgb(0, 0, 0);
    margin-top: 0;
    font-size: 15px;
}


.scholarship-form textarea {
    height: 40px;
    resize: none;
    border-radius: 10px;
    font-size : 10px;
}
.scholarship-form input[type="text"] {
    height: 15px;
    border-radius: 10px;
    font-size: 10px;
}

#toast-message {
    display: block;
    position: fixed;
    top: 0px;
    left: 50%;
    transform: translateX(-50%);
    background: rgb(13, 160, 8);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 2000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s, top 0.5s;
}
#toast-message.show {
    opacity: 1;
    top: 20px;
    pointer-events: auto;
}

.concerns-chat-container {
    display: flex;
    max-width: 100%;
    /* margin: 30px auto; */
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    height: 500px;
    overflow: hidden;
}
.concerns-chat-list {
    width: 200px;
    background: #f4f4f4;
    border-right: 1px solid #eee;
    padding: 20px 0;
    overflow-y: auto;
}
.concerns-chat-list h3 {
    text-align: center;
    font-size: 16px;
    margin-bottom: 10px;
}
.concerns-chat-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.concerns-chat-list li {
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}
.concerns-chat-list li:hover, .concerns-chat-list li.active {
    background: #e0e7ff;
    font-weight: bold;
}
.concerns-chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.concerns-chat-header {
    padding: 15px 20px;
    background: #f7f7fa;
    border-bottom: 1px solid #eee;
    font-weight: bold;
    font-size: 16px;
}
.concerns-chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f7f7fa;
}
.concern-message {
    max-width: 70%;
    padding: 10px 15px;
    border-radius: 15px;
    margin: 5px 0;
    word-break: break-word;
}
.concern-message.user {
    align-self: flex-start;
    background-color: #e9ecef;
    color: #333;
}
.concern-message.admin {
    align-self: flex-end;
    background-color: #090549;
    color: white;
}
.concern-message-content {
    margin-bottom: 5px;
}
.concern-message-timestamp {
    font-size: 0.7em;
    opacity: 0.7;
    text-align: right;
}
.concerns-chat-input {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #eee;
    background: #fff;
}
.concerns-chat-input textarea {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 20px;
    resize: none;
    height: 40px;
    font-family: inherit;
    font-size: 13px;
}
.concerns-chat-input button {
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
.concerns-chat-input button:hover {
    background:rgb(12, 7, 90);
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
    background:rgb(9, 18, 136);
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

.chevron-icon {
    transition: transform 0.3s cubic-bezier(.4,0,.2,1);
    transform: rotate(-90deg);
}
.chevron-icon.open {
    transform: rotate(0deg);
}

.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 10000; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
    justify-content: center; /* Center horizontally */
    align-items: center; /* Center vertically */
}

.modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Could be more specific based on design */
    max-width: 600px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    position: relative;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from {opacity: 0;}
    to {opacity: 1;}
}

.btn-edit-slots {
    background-color: #090549;
    color: white;
    font-size: 10px !important;
    border: none;
    border-radius: 14px !important;
    cursor: pointer;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.btn-edit-slots:hover {
    background-color: #100a66ff;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}


.applicants-container {
    padding: 20px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.applicants-h2 {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}
.applicants-p {
    font-size: 12px;
    color: #555;
    margin-bottom: 25px;
}
.applicants-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.applicants-table thead tr {
    background-color: #f8f9fa;
    color: #333;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}
.applicants-table th, .applicants-table td {
    padding: 12px 15px;
    vertical-align: middle;
}
.applicants-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
}
.applicants-table tbody tr:last-of-type {
    border-bottom: none;
}
.btn-outline {
    background-color: transparent;
    color:#090549;
    border: 1px solid #090549;
    padding: 6px 13px;
    font-size: 10px;
    cursor: pointer;
    border-radius: 14px;
    transition: all 0.3s ease;
}
.btn-outline:hover {
    background-color: #090549;
    color: white;
}
.back-btn {
    background:#6c757d;
    color:white;
    border: none;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 10px;
    cursor: pointer;
    margin-top: 20px;
    display: inline-block;
    transition: background 0.3s ease;
}
.back-btn:hover {
    background: #5a6268;
}
.status-approved {
    background-color: #D4EDDA;
    color: #155724;
}
.status-rejected {
    background-color: #F8D7DA;
    color: #721C24;
}

/* New Button Styles for Approve/Reject */
.btn-approve, .btn-reject {
    padding: 6px 13px;
    font-size: 10px;
    cursor: pointer;
    border-radius: 14px;
    border: 1px solid transparent;
    transition: all 0.3s ease;
    font-weight: bold;
    background-color: transparent;
}

.btn-approve {
    border-color: #28a745;
    color: #28a745;
}
.btn-approve:hover {
    background-color: #28a745;
    color: white;
}

.btn-reject {
    border-color: #dc3545;
    color: #dc3545;
}
.btn-reject:hover {
    background-color: #dc3545;
    color: white;
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
            <img src="../../../../<?php echo htmlspecialchars($profile_pic); ?>" alt="Admin Icon" class="user-icon">
            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <i class="fas fa-chevron-down chevron-icon" id="chevronIcon" style="color: white; cursor: pointer;" onclick="toggleMenu()"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="../../signin.php"><i class="fas fa-sign-out-alt"></i>  Logout</a>  
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
            <div class="nav-item" id="history-nav" onclick="showPage('application-page')">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                <div class="nav-text">Approved Applicants</div>
            </div>
            <div class="nav-item" id="scholarships-nav" onclick="showPage('scholarship-page')">
                <div class="nav-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="nav-text">Scholarship</div>
            </div>
            <div class="nav-item" id="communication-nav" onclick="showPage('send-updates-page')">
                <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                <div class="nav-text">Send Updates</div>
            </div>
            <div class="nav-item" id="total-applicants-nav" onclick="showPage('total-applicants-page')">
                <div class="nav-icon"><i class="fas fa-users"></i></div>
                <div class="nav-text">Total Applicants</div>
            </div>
            <div class="nav-item" id="reports-nav" onclick="showPage('reports-page')">
                <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="nav-text">Reports</div>
            </div>
            <div class="nav-item" id="user-concerns-nav" onclick="showPage('user-concerns-page')">
                <div class="nav-icon"><i class="fas fa-comments"></i></div>
                <div class="nav-text">User Concerns</div>
            </div>
            <div class="nav-item" id="user-request-nav" onclick="showPage('user-request-page')">
                <div class="nav-icon"><i class="fas fa-id-card"></i></div>
                <div class="nav-text">User Request</div>
            </div>
        </div>


        <?php if (isset($_SESSION['scholarship_added'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Scholarship added successfully';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_added']); endif; ?>

        <?php if (isset($_SESSION['scholarship_deleted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Scholarship deleted successfully';
            toastIcon.className = 'fas fa-trash-alt';
            toast.style.background = '#B22222';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_deleted']); endif; ?>


        <?php if (isset($_SESSION['scholarship_published'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Scholarship published successfully';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#191970';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_published']); endif; ?>

        <?php if (isset($_SESSION['slots_updated'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Scholarship slots updated successfully';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#191970';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['slots_updated']); endif; ?>


        <div id="toast-message">
            <span id="toast-text"></span>
            <i class="fas fa-check-circle" id="toast-icon" style="margin-left:10px; font-size:16px; vertical-align:middle;"></i>
        </div>


        <?php if (isset($_SESSION['message_deleted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Message deleted successfully';
            toastIcon.className = 'fas fa-trash-alt';
            toast.style.background = '#B22222';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['message_deleted']); endif; ?>
        
        <?php
            // Count Total Applicants (All Statuses)
            $totalApplicantsSql = "SELECT COUNT(application_id) as total FROM applications";
            $totalApplicantsResult = $conn->query($totalApplicantsSql);
            $totalApplicantsCount = $totalApplicantsResult->fetch_assoc()['total'] ?? 0;

            // Count Rejected Applicants
            $rejectedApplicantsSql = "SELECT COUNT(application_id) as total FROM applications WHERE status = 'rejected'";
            $rejectedApplicantsResult = $conn->query($rejectedApplicantsSql);
            $rejectedApplicantsCount = $rejectedApplicantsResult->fetch_assoc()['total'] ?? 0;

            // Count Approved Applicants
            $approvedApplicantsSql = "SELECT COUNT(application_id) as total FROM applications WHERE status = 'approved'";
            $approvedApplicantsResult = $conn->query($approvedApplicantsSql);
            $approvedApplicantsCount = $approvedApplicantsResult->fetch_assoc()['total'] ?? 0;
            ?>

        <?php if (isset($_SESSION['message_sent'])): ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = 'Message sent successfully';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['message_sent']); endif; ?>

        <div class="main-content">

            <div id="home-page" class="page active">
            <h1 class="h1-home-welcome">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h1>
            <div class="dashboard-boxes">
                <div class="box">
                    <div class="box-title">Total Applicants</div>
                    <div class="box-value"><?php echo $totalApplicantsCount; ?></div>
                    <div class="box-description">All applicants in the system</div>
                    <button class="view-details" onclick="showPage('total-applicants-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-title">Rejected Applicants</div>
                    <div class="box-value"><?php echo $rejectedApplicantsCount; ?></div>
                    <div class="box-description">Applicants not meeting criteria</div>
                    <button class="view-details" onclick="showPage('total-applicants-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-title">Approved Applicants</div>
                    <div class="box-value"><?php echo $approvedApplicantsCount; ?></div>
                    <div class="box-description">Applicants who got approved</div>
                    <button class="view-details" onclick="showPage('application-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-title">Listed Scholarships</div>
                    <div class="box-value"><?php echo $totalListedScholarshipsCount; ?></div>
                    <div class="box-description"><?php echo $totalListedScholarshipsCount; ?> scholarships have been listed in the system</div>
                    <button class="view-details" onclick="showPage('scholarship-page')">View Details</button>
                </div>
            </div>
        </div>
            

<div id="total-applicants-page" class="page">
    <h1 class="h1-title-appManagement">Total Applicants</h1>
    <p class="p-description-appM">This section displays the total number of applicants in the system, grouped by Scholarship and SPES.</p>
    <div class="dashboard-boxes">
        <?php
        // New query to get the TOTAL number of all applicants (pending, approved, and rejected)
        $totalAllApplicantsSql = "SELECT COUNT(application_id) as total_all_applicants FROM applications";
        $totalAllApplicantsResult = $conn->query($totalAllApplicantsSql);
        $totalAllApplicants = $totalAllApplicantsResult->fetch_assoc()['total_all_applicants'];
        ?>
        <div class="box">
            <div class="box-title">Scholarship Applicants</div>
            <div class="box-value"><?php echo htmlspecialchars($totalAllApplicants); ?></div>
            <div class="box-description">View all scholarship programs and their applicants.</div>
            <button class="view-details" onclick="showPage('total-applicants-scholarship')">View Details</button>
        </div>
        <div class="box">
            <div class="box-title">SPES Applicants</div>
            <div class="box-value">0</div>
            <div class="box-description">Special Program for Employment of Students (SPES)</div>
            <button class="view-details" onclick="showPage('total-applicants-spes')">View Details</button>
        </div>
    </div>
</div>

            <?php
            // Handle view details for a specific scholarship
            $viewScholarshipId = isset($_GET['view_scholarship']) ? intval($_GET['view_scholarship']) : null;
                $applicants = [];
                if ($viewScholarshipId) {
                    // Check for a search query and prepare it for the SQL statement
                    $searchQuery = isset($_GET['search']) && !empty($_GET['search']) ? $_GET['search'] : '%';
                    
                    // If it's a number, allow partial matches from the start. Otherwise, search for anything.
                    if (is_numeric($searchQuery)) {
                        $searchQuery .= '%';
                    } else {
                        $searchQuery = '%' . $searchQuery . '%';
                    }

                    $applicantsSql = "SELECT a.*, u.Fname, u.Lname 
                                    FROM applications a 
                                    JOIN user u ON a.user_id = u.user_id 
                                    WHERE a.scholarship_id = ? 
                                    AND a.application_id LIKE ? 
                                    ORDER BY a.created_at DESC";
                    $stmt = $conn->prepare($applicantsSql);
                    $stmt->bind_param("is", $viewScholarshipId, $searchQuery);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $applicants[] = $row;
                    }
                }
            ?>

            <div id="total-applicants-scholarship" class="page" style="display:none;">
                <h2>Scholarship Programs</h2>
                <p>List of all scholarship programs available in the system:</p>
                <?php
                // Corrected SQL query to count all applicants for each scholarship
                $totalApplicantsSql = "SELECT s.*, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id GROUP BY s.scholarship_id";
                $totalApplicantsResult = $conn->query($totalApplicantsSql);
                $totalApplicantsScholarships = $totalApplicantsResult->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if (count($totalApplicantsScholarships) > 0): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($totalApplicantsScholarships as $scholarship): ?>
                            <li style="margin-bottom: 18px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 18px;">
                                <strong><?php echo htmlspecialchars($scholarship['title']); ?></strong>
                                <div style="font-size:12px; color:#555; margin-top:5px;">
                                    <?php echo nl2br(htmlspecialchars($scholarship['description'])); ?>
                                </div>
                                <div style="margin-top:10px;">
                                    <span class="scholarship-status <?php echo $scholarship['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                        Status: <?php echo ucfirst($scholarship['status']); ?>
                                    </span>
                                    <p style="font-size: 12px; margin: 5px 0;">
                                        <strong>Total Applicants:</strong> <?php echo htmlspecialchars($scholarship['total_applicants']); ?>
                                    </p>
                                </div>
                                <button class="view-details" onclick="window.location.href='admin_dashboard.php?view_scholarship=<?php echo $scholarship['scholarship_id']; ?>#scholarship-applicants-page'">View Details</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No scholarship programs found.</p>
                <?php endif; ?>
                <button class="back-btn" onclick="showPage('total-applicants-page')" style="margin-top:20px;">Back</button>
            </div>

<?php if ($viewScholarshipId): ?>
<div id="scholarship-applicants-page" class="page active">
    <div class="applicants-container">
        <h2 class="applicants-h2">Applicants for 
            <?php
            // Fetch the scholarship title
            $schTitle = '';
            $schQuery = $conn->prepare("SELECT title FROM scholarships WHERE scholarship_id = ?");
            $schQuery->bind_param("i", $viewScholarshipId);
            $schQuery->execute();
            $schResult = $schQuery->get_result();
            if($schRow = $schResult->fetch_assoc()) {
                $schTitle = $schRow['title'];
            }
            echo htmlspecialchars($schTitle);
            ?>
        </h2>
        <p class="applicants-p">Review, approve, or reject applications for this program.</p>
        
        <form id="searchForm" method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="view_scholarship" value="<?php echo htmlspecialchars($viewScholarshipId); ?>">
            <input type="text" name="search" placeholder="Search by Application ID..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding: 8px; width: 300px; border-radius: 5px; border: 1px solid #ccc;">
            <button type="submit" style="padding: 8px 12px; border-radius: 5px; border: none; background-color: #090549; color: white; cursor: pointer;">Search</button>
        </form>
        <script>
            // This script ensures the page remains on the correct tab after a search
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = e.target;
                const search = form.querySelector('input[name="search"]').value;
                const scholarshipId = form.querySelector('input[name="view_scholarship"]').value;
                window.location.href = `admin_dashboard.php?view_scholarship=${scholarshipId}&search=${search}#scholarship-applicants-page`;
            });
        </script>

        <table class="applicants-table">
            <thead>
                <tr>
                    <th>Application ID</th>
                    <th>Name</th>
                    <th>Application Form</th>
                    <th>Documents</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($applicants) > 0): ?>
                <?php foreach ($applicants as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['application_id']); ?></td>
                        <td><?php echo htmlspecialchars($app['Fname'] . ' ' . $app['Lname']); ?></td>
                        <td>
                            <button class="btn-outline" onclick='showAppFormModal(<?php echo json_encode($app); ?>)'>View Form</button>
                        </td>
                        <td>
                            <button class="btn-outline" onclick='showDocsModal(<?php echo json_encode($app["documents"]); ?>)'>View Documents</button>
                        </td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?>
                                <form method="POST" style="display:inline-block; margin-right: 5px;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <button type="submit" name="approve_application" class="btn-approve">Approve</button>
                                </form>
                                <button type="button" class="btn-reject" onclick="showRejectionModal('<?php echo $app['application_id']; ?>')">Reject</button>
                            <?php else: 
                                $statusClass = 'status-' . htmlspecialchars($app['status']);
                            ?>
                                <span style="font-weight:bold; padding: 5px 10px; border-radius: 10px; display: inline-block;" class="<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding: 20px;">No applicants found for this scholarship or search query.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <button class="back-btn" onclick="window.location.href='admin_dashboard.php#total-applicants-scholarship'">Back</button>
    </div>
</div>
<?php endif; ?>

            <div id="appFormModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeAppFormModal()" style="position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;">&times;</span>
                    <div class="modal-header" style="margin-top:30px;">Application Form Details</div>
                    <div class="modal-body" id="appFormModalBody"></div>
                </div>
            </div>

            <div id="docsModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeDocsModal()" style="position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;">&times;</span>
                    <div class="modal-header" style="margin-top:30px;">Applicant Documents</div>
                    <div class="modal-body" id="docsModalBody"></div>
                </div>
            </div>

            <div id="editSlotsModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeEditSlotsModal()" style="position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;">&times;</span>
                    <div class="modal-header" style="margin-top:30px;">Edit Scholarship Slots</div>
                    <form method="POST">
                        <input type="hidden" name="scholarship_id" id="editSlotsScholarshipId">
                        <p style="font-size:14px;">Current Slots: <span id="currentSlotsText"></span></p>
                        <p style="font-size:14px;">Remaining Slots: <span id="remainingSlotsText"></span></p>
                        <p style="font-size:14px;">Total Applicants: <span id="totalApplicantsText"></span></p>
                        <label for="new_slots" style="font-size:14px;">Set New Total Slots:</label>
                        <input type="number" id="new_slots" name="new_slots" required style="width:100%;padding:8px;margin-top:10px;box-sizing:border-box;">
                        <button type="submit" name="update_slots" style="margin-top:15px;width:100%;padding:10px;background:#090549;color:white;border:none;border-radius:10px;cursor:pointer;">Update Slots</button>
                    </form>
                </div>
            </div>

            <script>

            </script>

            <div id="total-applicants-spes" class="page" style="display:none;">
                <h2>SPES Applicants</h2>
                <p>No SPES applicants yet.</p>
                <button class="back-btn" onclick="showPage('total-applicants-page')" style="margin-top:20px;">Back</button>
            </div>

            <div id="application-page" class="page">
                <h1 class="h1-title-appManagement">Approved Applicants</h1>
                <p class="p-description-appM">This section displays the details of approved scholarships and their applicants.</p>
                <div class="dashboard-boxes">
                    <?php foreach ($scholarships as $scholarship): ?>
                        <div class="box">
                            <div class="box-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
                            <button class="view-details" onclick="window.location.href='admin_dashboard.php?view_approved=<?php echo $scholarship['scholarship_id']; ?>#approved-applicants-list-page'">View Applicants</button>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($scholarships) === 0): ?>
                        <p>No scholarships found. Add scholarships to display them here.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php if ($viewApprovedScholarshipId): ?>
        <div id="approved-applicants-list-page" class="page active">
            <div class="applicants-container">
                <h2 class="applicants-h2">Approved Applicants for: <?php echo htmlspecialchars($approved_scholarship_title); ?></h2>
                <p class="applicants-p">Below is the list of users approved for this scholarship.</p>

                <form id="searchApprovedForm" method="GET" style="margin-bottom: 20px;">
                    <input type="hidden" name="view_approved" value="<?php echo htmlspecialchars($viewApprovedScholarshipId); ?>">
                    <input type="text" name="search_approved_name" placeholder="Search by applicant name..." value="<?php echo isset($_GET['search_approved_name']) ? htmlspecialchars($_GET['search_approved_name']) : ''; ?>" style="padding: 8px; width: 300px; border-radius: 5px; border: 1px solid #ccc;">
                    <button type="submit" style="padding: 8px 12px; border-radius: 5px; border: none; background-color: #090549; color: white; cursor: pointer;">Search</button>
                </form>
                <button class="back-btn" onclick="window.location.href='admin_dashboard.php#application-page'" style="margin-top: 0; margin-bottom: 20px;">Back to Programs</button>
                <table class="applicants-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Other Details</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($approved_applicants) > 0): ?>
                        <?php foreach ($approved_applicants as $applicant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($applicant['Fname'] . ' ' . $applicant['Lname']); ?></td>
                                <td>
                                    <button class="btn-outline" onclick='showUserDetailsModal(<?php echo htmlspecialchars(json_encode($applicant)); ?>)'>View Info</button>
                                </td>
                                <td>
                                    <button class="btn-primary" onclick="window.location.href='admin_dashboard.php?chat_user=<?php echo $applicant['user_id']; ?>#user-concerns-page'">
                                        <i class="fas fa-envelope"></i> Message
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center; padding: 20px;">No approved applicants found for this scholarship or search query.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>            

            <div id="reports-page" class="page">
                <h2>Reports</h2>
                <p>Access and view system reports here.</p>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Open Reports</div>
                        <div class="box-description">View and download available reports.</div>
                        <button class="view-details" onclick="alert('Reports feature coming soon!')">Open Reports</button>
                    </div>
                </div>
            </div>

<div id="user-request-page" class="page" style="display:none;">
    <div class="applicants-container">
        <h2 class="applicants-h2">User Account Requests</h2>
        <p class="applicants-p">Review and manage user registration requests, including uploaded IDs for verification.</p>
        
        <table class="applicants-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Valid ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $pendingUsers = $conn->query("SELECT * FROM user WHERE status='pending'");
            if ($pendingUsers->num_rows > 0):
                while ($user = $pendingUsers->fetch_assoc()):
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?></td>
                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                    <td>
                        <?php
                        $validIdFiles = json_decode($user['valid_id'], true);
                        if (is_array($validIdFiles) && !empty($validIdFiles[0])) {
                            foreach ($validIdFiles as $idx => $file) {
                                // --- START: FINAL PATCH FOR INCORRECT PATHS ---
                                $filePath = ltrim($file, './'); // Clean any leading "./"

                                // Check for and remove the duplicated "form_prac/" if it exists
                                if (strpos($filePath, 'form_prac/') === 0) {
                                    $filePath = substr($filePath, strlen('form_prac/'));
                                }

                                echo '<a href="' . BASE_URL . htmlspecialchars($filePath) . '" target="_blank" style="color:#090549;text-decoration:underline;display:inline-block;margin-bottom:4px;">View ' . ($idx == 0 ? 'Front' : 'Back') . ' of ID</a><br>';
                                // --- END: FINAL PATCH FOR INCORRECT PATHS ---
                            }
                        } else {
                            echo '<span style="color:#B22222;">No ID uploaded.</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline-block; margin-right: 5px;">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <button type="submit" name="approve_user" class="btn-approve">Approve</button>
                        </form>
                        <form method="POST" style="display:inline-block; margin-right: 5px;">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <button type="submit" name="reject_user" class="btn-reject" onclick="return confirm('Are you sure you want to reject this user?');">Reject</button>
                        </form>
                        <button type="button" class="btn-outline" onclick="showUserDetailsModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">Other Info</button>
                    </td>
                </tr>
            <?php 
                endwhile;
            else:
            ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding: 20px;">No pending user requests found.</td>
                </tr>
            <?php
            endif; 
            ?>
            </tbody>
        </table>
    </div>
</div>

<div id="user-concerns-page" class="page" style="display:none;">
    <h2>User Concerns Chat</h2>
    <div class="concerns-chat-container" style="display: flex;">
        <div class="concerns-chat-list" style="width: 220px; border-right: 1px solid #eee; padding-right: 10px;">
            <h3>Conversations</h3>
            <ul id="userList" style="list-style: none; padding: 0;">
                
                <?php foreach ($scholarships as $group): 
                    $isGroupActive = ($selectedGroupId == $group['scholarship_id']) ? 'background:#e0e7ff; font-weight:bold;' : '';
                ?>
                    <li onclick="window.location.href='admin_dashboard.php?chat_group=<?php echo $group['scholarship_id']; ?>#user-concerns-page'"
                        style="cursor:pointer; padding: 12px 20px; border-bottom: 1px solid #eee; transition: background 0.2s; <?php echo $isGroupActive; ?>">
                        <i class="fas fa-users" style="width:25px; height:25px; border-radius:50%; vertical-align:middle; margin-right:8px; text-align:center; line-height:25px; background-color: #ddd; color: #555;"></i>
                        <span style="font-weight: bold;"><?php echo htmlspecialchars($group['title']); ?></span>
                    </li>
                <?php endforeach; ?>

                <?php if(count($scholarships) > 0 && count($usersWithConcerns) > 0): ?>
                    <hr style="margin: 5px 0; border-color: #ccc;">
                <?php endif; ?>

                <?php foreach ($usersWithConcerns as $user): 
                    $isUserActive = ($selectedUserId == $user['user_id']) ? 'background:#e0e7ff; font-weight:bold;' : '';
                ?>
                    <li onclick="window.location.href='admin_dashboard.php?chat_user=<?php echo $user['user_id']; ?>#user-concerns-page'"
                        style="cursor:pointer; padding: 12px 20px; border-bottom: 1px solid #eee; transition: background 0.2s; <?php echo $isUserActive; ?>">
                        <img src="../../../../<?php echo htmlspecialchars(!empty($user['profile_pic']) ? $user['profile_pic'] : 'images/user.png'); ?>" style="width:25px;height:25px;border-radius:50%;vertical-align:middle;margin-right:8px;">
                        <?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="concerns-chat-main" style="flex:1; display:flex; flex-direction:column;">
            <div class="concerns-chat-header" id="concernChatHeader" style="padding:15px 20px; background:#f7f7fa; border-bottom:1px solid #eee; font-weight:bold; font-size:16px;">
                <?php echo $chatTitle; ?>
            </div>
            <div class="concerns-chat-messages" id="concernChatMessages" style="flex:1; overflow-y:auto; padding:20px; background:#f7f7fa;">
                <?php if ($selectedGroupId || $selectedUserId): ?>
                    <?php foreach ($chatMessages as $msg): ?>
                        <div class="concern-message <?php echo $msg['sender'] === 'admin' ? 'admin' : 'user'; ?>"
                             style="max-width:70%;padding:10px 15px;border-radius:15px;margin:5px 0;word-break:break-word;position:relative;
                             <?php echo $msg['sender'] === 'admin' ? 'align-self:flex-end;background:#090549;color:white;margin-left:auto;' : 'align-self:flex-start;background:#e9ecef;color:#333;'; ?>">
                            
                            <?php if ($msg['sender'] === 'admin'): ?>
                                <div class="message-options" style="position:absolute;top:5px;right:5px;">
                                    <span class="three-dots" onclick="toggleMessageMenu(<?php echo $msg['id']; ?>)" style="cursor:pointer;color:rgba(255,255,255,0.7);font-size:16px;padding:2px 5px;">⋮</span>
                                    <div class="message-menu" id="menu-<?php echo $msg['id']; ?>" style="display:none;position:absolute;top:20px;right:0;background:white;border:1px solid #ddd;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);z-index:1000;min-width:100px;">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                             <?php if ($selectedUserId): ?><input type="hidden" name="chat_user_id" value="<?php echo $selectedUserId; ?>"><?php endif; ?>
                                             <?php if ($selectedGroupId): ?><input type="hidden" name="chat_group_id" value="<?php echo $selectedGroupId; ?>"><?php endif; ?>
                                            <button type="submit" name="delete_admin_message" onclick="return confirm('Are you sure you want to delete this message?')" style="width:100%;padding:8px 12px;border:none;background:none;color:#dc3545;cursor:pointer;text-align:left;font-size:12px;">
                                                <i class="fas fa-trash-alt" style="margin-right:5px;"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="concern-message-content" style="<?php echo $msg['sender'] === 'admin' ? 'margin-right:15px;' : ''; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                            <div class="concern-message-timestamp" style="font-size:0.7em;opacity:0.7;text-align:right;margin-top:5px;">
                                <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:#888; text-align: center; margin-top: 20px;">Please select a conversation to start chatting.</div>
                <?php endif; ?>
            </div>
            <?php if ($selectedGroupId || $selectedUserId): ?>
            <form class="concerns-chat-input" method="POST" enctype="multipart/form-data" autocomplete="off" style="display:flex;gap:10px;padding:15px;border-top:1px solid #eee;background:#fff;">
                <textarea name="admin_reply" id="concernMessageInput" placeholder="Type your reply..." required style="flex:1;padding:10px;border:1px solid #ddd;border-radius:20px;resize:none;height:40px;font-family:inherit;font-size:13px;"></textarea>
                <?php if ($selectedGroupId): ?>
                    <input type="hidden" name="chat_group_id" value="<?php echo $selectedGroupId; ?>">
                <?php elseif ($selectedUserId): ?>
                    <input type="hidden" name="chat_user_id" value="<?php echo $selectedUserId; ?>">
                <?php endif; ?>
                <button type="submit" name="send_admin_reply" style="background:#090549;color:white;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;transition:background 0.3s;font-size:16px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

            <div id="scholarship-page" class="page">
                <h1 class="main-title-scholar">Manage Scholarships</h1>
                <form class="scholarship-form" method="POST">
                    <h3>Add New Scholarship</h3>
                    <input type="text" name="title" placeholder="Scholarship Title" required>
                    <textarea name="description" placeholder="Scholarship Description" required></textarea>
                    <textarea name="requirements" placeholder="Requirements" required></textarea>
                    <textarea name="benefits" placeholder="Benefits" required></textarea>
                    <textarea name="eligibility" placeholder="Eligibility Criteria" required></textarea>
                    <input type="number" name="number_of_slots" placeholder="Number of Slots" min="1" required>
                    <button type="submit" name="add_scholarship">Add Scholarship</button>
                </form>

                <h3>Scholarship List</h3>
                <?php 
                $scholarshipsSql = "SELECT s.*, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id GROUP BY s.scholarship_id";
                $scholarshipResult = $conn->query($scholarshipsSql);
                $scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);
                
                foreach ($scholarships as $scholarship): 
                    $remainingSlots = $scholarship['number_of_slots'] - $scholarship['total_applicants'];
                ?>
                    <div class="scholarship-card">
                        <h3 style="font-size: 15px;"><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                        <div class="scholarship-details">
                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($scholarship['description'])); ?></p>
                            <p><strong>Requirements:</strong> <?php echo nl2br(htmlspecialchars($scholarship['requirements'])); ?></p>
                            <p><strong>Benefits:</strong> <?php echo nl2br(htmlspecialchars($scholarship['benefits'])); ?></p>
                            <p><strong>Eligibility:</strong> <?php echo nl2br(htmlspecialchars($scholarship['eligibility'])); ?></p>
                        </div>
                        <div style="margin-top:10px;">
                            <span class="scholarship-status <?php echo $scholarship['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                Status: <?php echo ucfirst($scholarship['status']); ?>
                            </span>
                            <br>
                            <p style="font-size: 12px; margin: 5px 0;"><strong>Slots:</strong> 
                                <?php echo htmlspecialchars($remainingSlots); ?> of <?php echo htmlspecialchars($scholarship['number_of_slots']); ?> remaining
                            </p>
                        </div>
                        <div class="scholarship-footer">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $scholarship['scholarship_id']; ?>">
                                <?php if ($scholarship['status'] === 'pending'): ?>
                                    <button type="submit" name="publish_scholarship" class="btn-publish">Publish</button>
                                <?php endif; ?>
                                <button type="submit" name="delete_scholarship" class="btn-delete-scholarship" onclick="return confirm('Are you sure you want to delete this scholarship?');">Delete</button>
                            </form>
                            <button type="button" class="btn-edit-slots" onclick="showEditSlotsModal(
                                '<?php echo $scholarship['scholarship_id']; ?>', 
                                '<?php echo $scholarship['number_of_slots']; ?>', 
                                '<?php echo $scholarship['total_applicants']; ?>'
                            )">Edit Slots</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($scholarships) === 0): ?>
                <p>No scholarships found. Add a new scholarship using the form above.</p>
                <?php endif; ?>
            </div>

            <div id="rejectionModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeRejectionModal()">&times;</span>
                    <div class="modal-header">Reject Application</div>
                    <form method="POST">
                        <input type="hidden" name="application_id" id="rejectionAppId">
                        <label for="rejectionMessage" style="font-size:14px;">Reason for rejection:</label>
                        <textarea id="rejectionMessage" name="rejection_message" rows="5" required style="width:100%;padding:10px;box-sizing:border-box;margin-top:5px;"></textarea>
                        <button type="submit" name="reject_application_with_message" class="btn-danger" style="margin-top:10px;">Submit Rejection</button>
                    </form>
                </div>
            </div>

            
            <div id="send-updates-page" class="page">
                <h1 class="main-title-send-updates">Send Updates</h1>
                <form class="send-updates-form" method="POST">
                    <h3 class="send-updates-h3">Send a Updates to Users</h3>
                    <textarea name="message" placeholder="Title/Subject:                 
Greeting/Opening:
Body/Message Content:
Closing/Signature:" rows="5" required></textarea>
                    <button type="submit" name="send_message">Send Message</button>
                </form>

                <h3 class="message-sent-h3">Messages Sent</h3>
                <div class="sent-messages">
                <?php foreach ($messages as $message): ?>
                    <div class="message-card">
                        <div class="message-status">Sent</div>
                        <h3>Message:</h3>
                        <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                        <p><strong>Deadline:</strong> <?php echo $message['deadline'] ?: 'No deadline'; ?></p>
                        <div class="message-footer">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="message_id" value="<?php echo $message['notification_id']; ?>">
                                <button type="submit" name="delete_message" class="btn-delete-message" title="Delete" onclick="return confirm('Are you sure you want to delete this message?')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                    
                    <?php if (count($messages) === 0): ?>
                        <p>No messages found. Use the form above to send a message.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="userDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:30px; border-radius:10px; max-width:500px; width:90%; margin:auto; position:relative;">
                    <span style="position:absolute; top:10px; right:15px; font-size:22px; cursor:pointer;" onclick="closeUserDetailsModal()">&times;</span>
                    <h2 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px;">User Details</h2>
                    <div id="userDetailsContent"></div>
                </div>
            </div>

        </div> </div>
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
            case 'application-page':
            case 'approved-applicants-list-page': 
                highlightActiveNav('history-nav');
                break;
            case 'scholarship-page':
                highlightActiveNav('scholarships-nav');
                break;
            case 'send-updates-page':
                highlightActiveNav('communication-nav');
                break;
            case 'total-applicants-page':
            case 'total-applicants-scholarship': 
            case 'scholarship-applicants-page': 
            case 'total-applicants-spes': 
                highlightActiveNav('total-applicants-nav');
                break;
            case 'reports-page':
                highlightActiveNav('reports-nav');
                break;
            case 'user-concerns-page':
                highlightActiveNav('user-concerns-nav');
                break;
            case 'user-request-page':
                highlightActiveNav('user-request-nav');
                break;
        }
    }

    function highlightActiveNav(navId) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });

        document.getElementById(navId).classList.add('active');
    }

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    let hash = window.location.hash.substr(1);

    if (urlParams.has('view_scholarship') && hash === 'scholarship-applicants-page') {
        showPage('scholarship-applicants-page');
    } else if (urlParams.has('view_approved') && hash === 'approved-applicants-list-page') {
        showPage('approved-applicants-list-page');
    } else if (hash && document.getElementById(hash)) {
        showPage(hash);
    } else {
        showPage('home-page');
    }

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

    // Add this event listener to handle clicks on back buttons or hash changes
    window.addEventListener('hashchange', function() {
        let newHash = window.location.hash.substr(1);
        if (newHash && document.getElementById(newHash)) {
            // Prevent re-showing the same page if it's already active due to URL params
            if (!document.getElementById(newHash).classList.contains('active')) {
                 showPage(newHash);
            }
        } else {
            showPage('home-page');
        }
    });

    // --- START: JAVASCRIPT FOR APPROVED APPLICANT SEARCH ---
    const searchApprovedForm = document.getElementById('searchApprovedForm');
    if(searchApprovedForm) {
        searchApprovedForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const searchTerm = form.querySelector('input[name="search_approved_name"]').value;
            const scholarshipId = form.querySelector('input[name="view_approved"]').value;
            window.location.href = `admin_dashboard.php?view_approved=${scholarshipId}&search_approved_name=${encodeURIComponent(searchTerm)}#approved-applicants-list-page`;
        });
    }
    // --- END: JAVASCRIPT FOR APPROVED APPLICANT SEARCH ---
});

    function showAppFormModal(applicantJson) {
        let app = typeof applicantJson === 'string' ? JSON.parse(applicantJson) : applicantJson;
        let html = '';

        if (!app) {
            document.getElementById('appFormModalBody').innerHTML = "<p>No application data found.</p>";
            document.getElementById('appFormModal').style.display = 'flex';
            return;
        }

        html += '<h3 style="text-align:center; margin-bottom:20px;">Application Details</h3>';

        html += '<hr>';
        html += '<h4 style="margin-bottom: 10px;">1. Personal Information</h4>';
        html += '<p><strong>Full Name:</strong> ' + (app.fullname || 'N/A') + '</p>';
        html += '<p><strong>Date of Birth:</strong> ' + (app.birthdate || 'N/A') + '</p>';
        html += '<p><strong>Place of Birth:</strong> ' + (app.place_of_birth || 'N/A') + '</p>';
        html += '<p><strong>Address:</strong> ' + (app.address || 'N/A') + '</p>';
        html += '<p><strong>Email:</strong> ' + (app.email || 'N/A') + '</p>';
        html += '<p><strong>Contact Number:</strong> ' + (app.contact || 'N/A') + '</p>';
        html += '<p><strong>Facebook Account:</strong> ' + (app.facebook || 'N/A') + '</p>';
        html += '<p><strong>Civil Status:</strong> ' + (app.civil_status || 'N/A') + '</p>';
        html += '<p><strong>Gender:</strong> ' + (app.gender || 'N/A') + '</p>';

        html += '<hr>';
        html += '<h4 style="margin-top:20px; margin-bottom: 10px;">2. Family Background</h4>';
        html += '<p><strong>Mother\'s Name:</strong> ' + (app.mother_name || 'N/A') + '</p>';
        html += '<p><strong>Mother\'s Occupation:</strong> ' + (app.mother_occupation || 'N/A') + '</p>';
        html += '<p><strong>Father\'s Name:</strong> ' + (app.father_name || 'N/A') + '</p>';
        html += '<p><strong>Father\'s Occupation:</strong> ' + (app.father_occupation || 'N/A') + '</p>';
        html += '<p><strong>Monthly Family Income:</strong> ' + (app.family_income || 'N/A') + '</p>';
        html += '<p><strong>Number of Dependents:</strong> ' + (app.dependents || 'N/A') + '</p>';
        
        html += '<hr>';
        html += '<h4 style="margin-top:20px; margin-bottom: 10px;">3. Educational Background</h4>';
        html += '<p><strong>Elementary School:</strong> ' + (app.elem_school || 'N/A') + '</p>';
        html += '<p><strong>Honors Received:</strong> ' + (app.elem_honors || 'N/A') + '</p>';
        html += '<p><strong>Date Graduated/Current Level:</strong> ' + (app.elem_grad || 'N/A') + '</p>';
        
        html += '<p><strong>High School:</strong> ' + (app.hs_school || 'N/A') + '</p>';
        html += '<p><strong>Honors Received:</strong> ' + (app.hs_honors || 'N/A') + '</p>';
        html += '<p><strong>Date Graduated/Current Level:</strong> ' + (app.hs_grad || 'N/A') + '</p>';
        html += '<p><strong>Vocational School:</strong> ' + (app.voc_school || 'N/A') + '</p>';
        html += '<p><strong>Honors Received:</strong> ' + (app.voc_honors || 'N/A') + '</p>';
        html += '<p><strong>Date Graduated/Current Level:</strong> ' + (app.voc_grad || 'N/A') + '</p>';
        
        html += '<hr>';
        html += '<h4 style="margin-top:20px; margin-bottom: 10px;">3-A. College Background</h4>';
        html += '<p><strong>College School:</strong> ' + (app.college_school || 'N/A') + '</p>';
        html += '<p><strong>Course & Year:</strong> ' + (app.college_course || 'N/A') + '</p>';
        html += '<p><strong>Average from Previous Semester:</strong> ' + (app.college_average || 'N/A') + '</p>';
        html += '<p><strong>Awards and Recognitions:</strong> ' + (app.college_awards || 'N/A') + '</p>';

        document.getElementById('appFormModalBody').innerHTML = html;
        document.getElementById('appFormModal').style.display = 'flex';
    }

    function closeAppFormModal() {
        document.getElementById('appFormModal').style.display = 'none';
        document.getElementById('appFormModalBody').innerHTML = '';
    }

function showDocsModal(documentsJson) {
                    let html = '';
                    try {
                        const docs = JSON.parse(documentsJson);
                        if (Array.isArray(docs) && docs.length > 0) {
                            docs.forEach(doc => {
                                html += `<a href="${doc}" target="_blank">View Document</a><br>`;
                            });
                        } else {
                            html = 'No documents uploaded.';
                        }
                    } catch {
                        html = 'No documents uploaded.';
                    }
                    document.getElementById('docsModalBody').innerHTML = html;
                    document.getElementById('docsModal').style.display = 'flex'; 
                }


    function closeDocsModal() {
        document.getElementById('docsModal').style.display = 'none';
    }

    function showEditSlotsModal(scholarshipId, totalSlots, totalApplicants) {
        document.getElementById('editSlotsScholarshipId').value = scholarshipId;
        document.getElementById('currentSlotsText').textContent = totalSlots;
        document.getElementById('remainingSlotsText').textContent = totalSlots - totalApplicants;
        document.getElementById('totalApplicantsText').textContent = totalApplicants;
        document.getElementById('editSlotsModal').style.display = 'flex';
    }

    function closeEditSlotsModal() {
        document.getElementById('editSlotsModal').style.display = 'none';
    }

    function showRejectionModal(applicationId) {
        document.getElementById('rejectionAppId').value = applicationId;
        document.getElementById('rejectionModal').style.display = "flex";
    }

    function closeRejectionModal() {
        document.getElementById('rejectionModal').style.display = "none";
    }

    function showUserDetailsModal(user) {
        var modal = document.getElementById('userDetailsModal');
        var content = document.getElementById('userDetailsContent');

        
        const fullName = `${user.Fname || ''} ${user.Mname || ''} ${user.Lname || ''}`.replace(/\s+/g, ' ').trim();

        content.innerHTML = `
            <style>
                .modal-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-family: 'Roboto', sans-serif;}
                .modal-info-item { display: flex; flex-direction: column; }
                .modal-info-label { font-weight: bold; color: #333; margin-bottom: 5px; font-size: 14px; }
                .modal-info-value { color: #666; font-size: 12px; }
            </style>
            <div class="modal-info-grid">
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                    <span class="modal-info-label">Full Name</span>
                    <span class="modal-info-value">${fullName}</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Age</span>
                    <span class="modal-info-value">${user.Age || 'N/A'}</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Gender</span>
                    <span class="modal-info-value">${user.Gender || 'N/A'}</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Birthdate</span>
                    <span class="modal-info-value">${user.Birthdate || 'N/A'}</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-info-label">Contact Number</span>
                    <span class="modal-info-value">${user.contact_number || 'N/A'}</span>
                </div>
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                     <span class="modal-info-label">Address</span>
                    <span class="modal-info-value">${user.Address || 'N/A'}</span>
                </div>
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                    <span class="modal-info-label">Email</span>
                    <span class="modal-info-value">${user.Email || 'N/A'}</span>
                </div>
            </div>
        `;
        modal.style.display = 'flex';
    }

    function closeUserDetailsModal() {
        document.getElementById('userDetailsModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        let appModal = document.getElementById('appFormModal');
        let docsModal = document.getElementById('docsModal');
        let editSlotsModal = document.getElementById('editSlotsModal');
        let rejectionModal = document.getElementById('rejectionModal');
        let userDetailsModal = document.getElementById('userDetailsModal');
        if (event.target === appModal) closeAppFormModal();
        if (event.target === docsModal) closeDocsModal();
        if (event.target === editSlotsModal) closeEditSlotsModal();
        if (event.target === rejectionModal) closeRejectionModal();
        if (event.target === userDetailsModal) closeUserDetailsModal();
    };

    function scrollAdminChatToBottom() {
        var chatMessages = document.getElementById('concernChatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const chatMessages = document.getElementById('concernChatMessages');
        if (chatMessages && document.getElementById('user-concerns-page').classList.contains('active')) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    });

    window.showPage = (function(origShowPage) {
        return function(pageId) {
            origShowPage(pageId);
            if (pageId === 'user-concerns-page') {
                setTimeout(scrollAdminChatToBottom, 100);
            }
        };
    })(window.showPage || function() {});

    function toggleMessageMenu(messageId) {
        document.querySelectorAll('.message-menu').forEach(menu => {
            if (menu.id !== 'menu-' + messageId) {
                menu.style.display = 'none';
            }
        });
        
        const menu = document.getElementById('menu-' + messageId);
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
    
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.message-options')) {
            document.querySelectorAll('.message-menu').forEach(menu => {
                menu.style.display = 'none';
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
</script>
</body>
</html>