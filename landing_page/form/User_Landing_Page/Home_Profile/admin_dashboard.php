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

// --- START: MODIFIED APPROVE/REJECT LOGIC ---
if (isset($_POST['approve_application']) || isset($_POST['reject_application'])) {
    $applicationId = $_POST['application_id'];
    $newStatus = isset($_POST['approve_application']) ? 'approved' : 'rejected';

    // 1. Fetch user ID, email, and scholarship info
    $infoSql = "SELECT u.user_id, u.Email, u.Fname, u.Lname, s.title 
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

        // 3. Prepare email content
        $userId = $infoResult['user_id'];
        $to = $infoResult['Email'];
        $name = $infoResult['Fname'] . ' ' . $infoResult['Lname'];
        $scholarshipTitle = $infoResult['title'];

        if ($newStatus == 'approved') {
            // **NEW**: Once approved, delete all other PENDING applications for this user.
            $deletePendingSql = "DELETE FROM applications WHERE user_id = ? AND status = 'pending'";
            $deleteStmt = $conn->prepare($deletePendingSql);
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();

            $subject = "Your Scholarship Application has been Approved";
            // **MODIFIED**: Email body updated to inform the user.
            $body = "Hello {$name},\n\nCongratulations! Your application for the '{$scholarshipTitle}' scholarship has been approved.\n\nAs a result, any other pending scholarship applications you had have been withdrawn. Please log in to your account for more details.\n\nThank you,\nPESO San Julian MIS";
        } else { // rejected
            $subject = "Update on your Scholarship Application";
            $body = "Hello {$name},\n\nWe regret to inform you that your application for the '{$scholarshipTitle}' scholarship has been rejected at this time.\n\nThank you for your interest.\n\nSincerely,\nPESO San Julian MIS";
        }
        
        // 4. Send the email
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
    // --- START: SPES APPROVE/REJECT LOGIC ---
    } elseif (isset($_POST['approve_spes_application']) || isset($_POST['reject_spes_application'])) {
        $spesApplicationId = $_POST['spes_application_id'];
        $newStatus = isset($_POST['approve_spes_application']) ? 'approved' : 'rejected';

        $updateSql = "UPDATE spes_applications SET status = ? WHERE spes_application_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $newStatus, $spesApplicationId);
        $stmt->execute();

        // Optionally, you can add an email notification here as well.
        
        $_SESSION['spes_status_updated'] = true; // For a toast message
        header("Location: admin_dashboard.php#total-applicants-spes");
        exit();
    }
    // --- END: SPES APPROVE/REJECT LOGIC ---
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

// --- START: FETCH SPES APPLICANT DATA ---
// Count total SPES applicants
$totalSpesApplicantsSql = "SELECT COUNT(spes_application_id) as total FROM spes_applications";
$totalSpesApplicantsResult = $conn->query($totalSpesApplicantsSql);
$totalSpesApplicantsCount = $totalSpesApplicantsResult->fetch_assoc()['total'] ?? 0;
// --- END: FETCH SPES APPLICANT DATA ---


// --- START: FETCH APPROVED APPLICANT DATA (SCHOLARSHIP & SPES) ---
// Count approved SPES applicants for the dashboard box
$approvedSpesSql = "SELECT COUNT(spes_application_id) as total FROM spes_applications WHERE status = 'approved'";
$approvedSpesResult = $conn->query($approvedSpesSql);
$approvedSpesCount = $approvedSpesResult->fetch_assoc()['total'] ?? 0;
// --- END: FETCH APPROVED APPLICANT DATA ---


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
    $stmt = $conn->prepare("SELECT c.*, u.Fname, u.Lname FROM concerns c LEFT JOIN user u ON c.user_id = u.user_id WHERE c.scholarship_id = ? ORDER BY c.created_at ASC");
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
    $attachmentPath = null;

    // ADDED: Handle file upload logic
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $target_dir = "../../../../uploads/chat_attachments/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = basename($_FILES['attachment']['name']);
        $target_file = $target_dir . uniqid() . "_" . $file_name;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachmentPath = $target_file;
        }
    }
    // END ADDED

    // MODIFIED: Check for a message OR an attachment
    if (!empty($reply) || $attachmentPath) {
        if (isset($_POST['chat_group_id']) && !empty($_POST['chat_group_id'])) {
            // It's a group message for a scholarship
            $chat_group_id = intval($_POST['chat_group_id']);
            
            // MODIFIED: SQL query and bind_param
            $stmt = $conn->prepare("INSERT INTO concerns (admin_id, scholarship_id, sender, message, user_id, attachment_path) VALUES (?, ?, 'admin', ?, NULL, ?)");
            $stmt->bind_param("iiss", $admin_id, $chat_group_id, $reply, $attachmentPath);
            $stmt->execute();
            header("Location: admin_dashboard.php?chat_group=$chat_group_id#user-concerns-page");
            exit();

        } elseif (isset($_POST['chat_user_id']) && !empty($_POST['chat_user_id'])) {
            // It's a 1-on-1 message to a specific user
            $chat_user_id = intval($_POST['chat_user_id']);
            
            // MODIFIED: SQL query and bind_param
            $stmt = $conn->prepare("INSERT INTO concerns (user_id, admin_id, sender, message, attachment_path) VALUES (?, ?, 'admin', ?, ?)");
            $stmt->bind_param("iiss", $chat_user_id, $admin_id, $reply, $attachmentPath);
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

// --- START: NEW LOGIC FOR SAVING CHAT ATTACHMENTS TO SCHOLARSHIP OR SPES ---
function saveAttachmentToDocuments($conn, $messageId, $userId, $type) {
    $attachmentPath = '';

    // 1. Get the attachment path from the message
    $pathStmt = $conn->prepare("SELECT attachment_path FROM concerns WHERE id = ? AND user_id = ?");
    $pathStmt->bind_param("ii", $messageId, $userId);
    $pathStmt->execute();
    $pathResult = $pathStmt->get_result();
    if ($pathRow = $pathResult->fetch_assoc()) {
        $attachmentPath = $pathRow['attachment_path'];
    }

    if (empty($attachmentPath)) {
        $_SESSION['document_saved_error'] = "Could not find the attachment.";
        return;
    }

    if ($type === 'scholarship') {
        // 2. Find the user's latest APPROVED scholarship application
        $appStmt = $conn->prepare("SELECT application_id, documents FROM applications WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
        $appStmt->bind_param("i", $userId);
        $appStmt->execute();
        $appResult = $appStmt->get_result();

        if ($appRow = $appResult->fetch_assoc()) {
            $applicationId = $appRow['application_id'];
            $currentDocs = json_decode($appRow['documents'], true) ?: [];
            if (!in_array($attachmentPath, $currentDocs)) {
                $currentDocs[] = $attachmentPath;
            }
            $newDocsJson = json_encode($currentDocs);
            $updateStmt = $conn->prepare("UPDATE applications SET documents = ? WHERE application_id = ?");
            $updateStmt->bind_param("si", $newDocsJson, $applicationId);
            $updateStmt->execute();
            $_SESSION['document_saved_success'] = "File saved to user's Scholarship documents.";
        } else {
            $_SESSION['document_saved_error'] = "Failed: User is not an approved Scholarship applicant.";
        }
    } elseif ($type === 'spes') {
        // 2. Find the user's latest APPROVED SPES application
        $appStmt = $conn->prepare("SELECT spes_application_id, spes_documents_path FROM spes_applications WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
        $appStmt->bind_param("i", $userId);
        $appStmt->execute();
        $appResult = $appStmt->get_result();

        if ($appRow = $appResult->fetch_assoc()) {
            $applicationId = $appRow['spes_application_id'];
            $currentDocsPath = $appRow['spes_documents_path'];
            $currentDocs = json_decode($currentDocsPath, true);

            // If it's not a JSON array, it might be the initial single file path
            if (!is_array($currentDocs)) {
                $currentDocs = !empty($currentDocsPath) ? [$currentDocsPath] : [];
            }
            
            if (!in_array($attachmentPath, $currentDocs)) {
                $currentDocs[] = $attachmentPath;
            }
            $newDocsJson = json_encode($currentDocs);
            $updateStmt = $conn->prepare("UPDATE spes_applications SET spes_documents_path = ? WHERE spes_application_id = ?");
            $updateStmt->bind_param("si", $newDocsJson, $applicationId);
            $updateStmt->execute();
            $_SESSION['document_saved_success'] = "File saved to user's SPES documents.";
        } else {
            $_SESSION['document_saved_error'] = "Failed: User is not an approved SPES applicant.";
        }
    }
}


if (isset($_POST['save_to_documents_scholarship'])) {
    saveAttachmentToDocuments($conn, intval($_POST['message_id']), intval($_POST['user_id']), 'scholarship');
    header("Location: admin_dashboard.php?chat_user=" . intval($_POST['user_id']) . "#user-concerns-page");
    exit();
}

if (isset($_POST['save_to_documents_spes'])) {
    saveAttachmentToDocuments($conn, intval($_POST['message_id']), intval($_POST['user_id']), 'spes');
    header("Location: admin_dashboard.php?chat_user=" . intval($_POST['user_id']) . "#user-concerns-page");
    exit();
}
// --- END: NEW LOGIC FOR SAVING CHAT ATTACHMENTS ---

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

// --- START: PHP FOR REPORTS PAGE ---
// Scholarship Documents Search
$scholarshipDocsSearch = isset($_GET['search_scholarship_docs']) ? trim($_GET['search_scholarship_docs']) : '';
$scholarshipDocsSql = "SELECT u.user_id, u.Fname, u.Lname, a.documents FROM user u JOIN applications a ON u.user_id = a.user_id WHERE a.status = 'approved'";
if (!empty($scholarshipDocsSearch)) {
    $scholarshipDocsSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
}
$scholarshipDocsSql .= " ORDER BY a.created_at ASC"; // Oldest first, newest last

$scholarship_docs_users = [];
if (!empty($scholarshipDocsSearch)) {
    $stmt = $conn->prepare($scholarshipDocsSql);
    $search = "%$scholarshipDocsSearch%";
    $stmt->bind_param("s", $search); // Correctly binds the variable by reference
    $stmt->execute();
    $result = $stmt->get_result();
    $scholarship_docs_users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $scholarshipDocsResult = $conn->query($scholarshipDocsSql);
    $scholarship_docs_users = $scholarshipDocsResult->fetch_all(MYSQLI_ASSOC);
}

// SPES Documents Search
$spesDocsSearch = isset($_GET['search_spes_docs']) ? trim($_GET['search_spes_docs']) : '';
$spesDocsSql = "SELECT u.user_id, u.Fname, u.Lname, sa.id_image_paths, sa.spes_documents_path FROM user u JOIN spes_applications sa ON u.user_id = sa.user_id WHERE sa.status = 'approved'";
if (!empty($spesDocsSearch)) {
    $spesDocsSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
}
$spesDocsSql .= " ORDER BY sa.created_at ASC"; // Oldest first, newest last

$spes_docs_users = [];
if (!empty($spesDocsSearch)) {
    $stmt = $conn->prepare($spesDocsSql);
    $search = "%$spesDocsSearch%";
    $stmt->bind_param("s", $search); // Correctly binds the variable by reference
    $stmt->execute();
    $result = $stmt->get_result();
    $spes_docs_users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $spesDocsResult = $conn->query($spesDocsSql);
    $spes_docs_users = $spesDocsResult->fetch_all(MYSQLI_ASSOC);
}
// --- END: PHP FOR REPORTS PAGE ---
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

/* --- NEW: Icon styling for dashboard boxes --- */
.box-icon {
    font-size: 28px;
    color: #090549;
    margin-bottom: 10px;
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

/* --- NEW: General button transition styles --- */
.view-details, .btn-publish, .btn-delete-scholarship, .btn-edit-slots, 
.btn-approve, .btn-reject, .btn-primary, .back-btn, .btn-outline,
.scholarship-form button, .send-updates-form button {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.view-details:hover, .btn-publish:hover, .btn-delete-scholarship:hover, 
.btn-edit-slots:hover, .btn-approve:hover, .btn-reject:hover, .btn-primary:hover, 
.back-btn:hover, .btn-outline:hover, .scholarship-form button:hover, .send-updates-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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
}

.btn-delete-message:hover {
    background-color:#d32f2f;
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
}

.btn-publish {
    background-color: #28A745;
    border-radius: 14px !important;
    font-size: 10px !important;
}

.btn-primary {
    background-color: #090549;
    color: white;
}

.btn-danger {
    background-color: #f44336;
}

.btn-success {
    background-color: #4CAF50;
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
    position: relative;
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
    margin-bottom: 20px;
    display: inline-block;
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

.message-attachment {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 10px;
}
.message-attachment a {
    text-decoration: none;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.message-attachment a:hover {
    text-decoration: underline;
}
.concern-message.user .message-attachment {
    background-color: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
}
.concern-message.user .message-attachment a {
    color: #333;
}
/* --- STYLES CONVERTED FROM INLINE --- */
#chevronIcon {
    color: white;
    cursor: pointer;
}
#toast-icon {
    margin-left: 10px;
    font-size: 16px;
    vertical-align: middle;
}
#searchForm, #searchSpesForm, #searchApprovedForm, #searchSpesTotalForm {
    margin-bottom: 20px;
}
.search-input {
    padding: 8px;
    width: 300px;
    border-radius: 5px;
    border: 1px solid #ccc;
}
.search-button {
    padding: 8px 12px;
    border-radius: 5px;
    border: none;
    background-color: #090549;
    color: white;
    cursor: pointer;
}
#scholarship-applicants-page .back-btn,
#approved-spes-list-page .back-btn,
#approved-applicants-list-page .back-btn {
    margin-top: 0;
    margin-bottom: 20px;
}
#approved-scholarship-programs-page .back-btn {
    margin-bottom: 20px;
}
.inline-form {
    display: inline-block;
    margin-right: 5px;
}
.status-badge {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 10px;
    display: inline-block;
}
.modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 22px;
    cursor: pointer;
}
.modal-header {
    margin-top: 30px;
}
#appFormModal .modal-body, #spesAppModal .modal-body {
    max-height: 400px;
    overflow-y: auto;
}
.scholarship-list ul {
    list-style: none;
    padding: 0;
}
.scholarship-list li {
    margin-bottom: 18px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    padding: 18px;
}
.scholarship-list-description {
    font-size: 12px;
    color: #555;
    margin-top: 5px;
}
.scholarship-list-footer {
    margin-top: 10px;
}
.scholarship-list-footer p {
    font-size: 12px;
    margin: 5px 0;
}
.text-center {
    text-align: center;
}
.icon-check {
    color: green;
    font-size: 16px;
}
.icon-times {
    color: red;
    font-size: 16px;
}
#userList {
    list-style: none;
    padding: 0;
}
#userList li {
    cursor: pointer;
    padding: 12px 20px;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}
#userList li.active {
    background: #e0e7ff;
    font-weight: bold;
}
.chat-list-icon {
    width: 25px;
    height: 25px;
    border-radius: 50%;
    vertical-align: middle;
    margin-right: 8px;
}
.chat-list-icon.icon-bg {
    text-align: center;
    line-height: 25px;
    background-color: #ddd;
    color: #555;
}
#userDetailsModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.4);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#userDetailsModal > div {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    max-width: 500px;
    width: 90%;
    margin: auto;
    position: relative;
}
#validIdModal .modal-body, #viewDocumentsModal .modal-body {
    max-height: 450px;
    overflow-y: auto;
    text-align: center;
}
#validIdModal .modal-body img {
    max-width: 100%;
    height: auto;
    border-radius: 5px;
    margin-bottom: 15px;
}
.concern-message .message-options {
    position: absolute;
    top: 5px;
    right: 5px;
}
.concern-message.admin .message-options {
    color: rgba(255,255,255,0.7);
}
.concern-message.user .message-options {
    color: #333;
}
.message-options .three-dots {
    cursor: pointer;
    font-size: 16px;
    padding: 2px 5px;
}
.message-menu {
    display: none;
    position: absolute;
    top: 20px;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
    min-width: 100px;
}
.message-menu button {
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    font-size: 12px;
}
.message-menu form {
    margin: 0;
}
.message-menu button[name="save_to_documents_scholarship"] {
    color: #090549;
    border-bottom: 1px solid #f0f0f0;
}
.message-menu button[name="save_to_documents_spes"] {
    color: #090549;
}
.message-menu button[name="delete_admin_message"] {
    color: #dc3545;
}
.message-menu i {
    margin-right: 5px;
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
                <i class="fas fa-chevron-down chevron-icon" id="chevronIcon" onclick="toggleMenu()"></i>
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
                <div class="nav-icon"><i class="fas fa-user-check"></i></div>
                <div class="nav-text">Approved Applicants</div>
            </div>
            <div class="nav-item" id="scholarships-nav" onclick="showPage('scholarship-page')">
                <div class="nav-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="nav-text">Scholarship</div>
            </div>
            <div class="nav-item" id="communication-nav" onclick="showPage('send-updates-page')">
                <div class="nav-icon"><i class="fas fa-bullhorn"></i></div>
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
                <div class="nav-icon"><i class="fas fa-user-plus"></i></div>
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
            <i class="fas fa-check-circle" id="toast-icon"></i>
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

        <?php if (isset($_SESSION['document_saved_success'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = '<?php echo $_SESSION['document_saved_success']; ?>';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        });
        </script>
        <?php unset($_SESSION['document_saved_success']); endif; ?>

        <?php if (isset($_SESSION['document_saved_error'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            var toastText = document.getElementById('toast-text');
            var toastIcon = document.getElementById('toast-icon');
            toastText.textContent = '<?php echo $_SESSION['document_saved_error']; ?>';
            toastIcon.className = 'fas fa-exclamation-triangle';
            toast.style.background = '#dc3545'; // Red color for error
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3500);
        });
        </script>
        <?php unset($_SESSION['document_saved_error']); endif; ?>
        
        <?php
            // --- START: MODIFIED APPLICANT COUNT QUERIES (SCHOLARSHIP + SPES) ---
            // Count Total Applicants (All Statuses from both tables)
            $totalApplicantsSql = "
                SELECT SUM(total) as grand_total FROM (
                    (SELECT COUNT(application_id) as total FROM applications)
                    UNION ALL
                    (SELECT COUNT(spes_application_id) as total FROM spes_applications)
                ) as combined_counts
            ";
            $totalApplicantsResult = $conn->query($totalApplicantsSql);
            $totalApplicantsCount = $totalApplicantsResult->fetch_assoc()['grand_total'] ?? 0;

            // Count Rejected Applicants (from both tables)
            $rejectedApplicantsSql = "
                SELECT SUM(total) as grand_total FROM (
                    (SELECT COUNT(application_id) as total FROM applications WHERE status = 'rejected')
                    UNION ALL
                    (SELECT COUNT(spes_application_id) as total FROM spes_applications WHERE status = 'rejected')
                ) as combined_counts
            ";
            $rejectedApplicantsResult = $conn->query($rejectedApplicantsSql);
            $rejectedApplicantsCount = $rejectedApplicantsResult->fetch_assoc()['grand_total'] ?? 0;

            // Count Approved Applicants (from both tables - already done for SPES, just need scholarship)
            $approvedApplicantsSql = "SELECT COUNT(application_id) as total FROM applications WHERE status = 'approved'";
            $approvedApplicantsResult = $conn->query($approvedApplicantsSql);
            $approvedApplicantsCount = $approvedApplicantsResult->fetch_assoc()['total'] ?? 0;
            // Note: $approvedSpesCount is already calculated further up.
            // --- END: MODIFIED APPLICANT COUNT QUERIES ---
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
                    <div class="box-icon"><i class="fas fa-users"></i></div>
                    <div class="box-title">Total Applicants</div>
                    <div class="box-value" data-target="<?php echo $totalApplicantsCount; ?>">0</div>
                    <div class="box-description">All applicants in the system</div>
                    <button class="view-details" onclick="showPage('total-applicants-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-icon"><i class="fas fa-user-times"></i></div>
                    <div class="box-title">Rejected Applicants</div>
                    <div class="box-value" data-target="<?php echo $rejectedApplicantsCount; ?>">0</div>
                    <div class="box-description">Applicants not meeting criteria</div>
                    <button class="view-details" onclick="showPage('total-applicants-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-icon"><i class="fas fa-user-check"></i></div>
                    <div class="box-title">Approved Applicants</div>
                    <div class="box-value" data-target="<?php echo $approvedApplicantsCount + $approvedSpesCount; ?>">0</div>
                    <div class="box-description">Applicants who got approved</div>
                    <button class="view-details" onclick="showPage('application-page')">View Details</button>
                </div>
                <div class="box">
                    <div class="box-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="box-title">Listed Scholarships</div>
                    <div class="box-value" data-target="<?php echo $totalListedScholarshipsCount; ?>">0</div>
                    <div class="box-description"><?php echo $totalListedScholarshipsCount; ?> scholarships listed</div>
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
            <div class="box-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="box-title">Scholarship Applicants</div>
            <div class="box-value" data-target="<?php echo htmlspecialchars($totalAllApplicants); ?>">0</div>
            <div class="box-description">View all scholarship programs and their applicants.</div>
            <button class="view-details" onclick="showPage('total-applicants-scholarship')">View Details</button>
        </div>
        <div class="box">
            <div class="box-icon"><i class="fas fa-briefcase"></i></div>
            <div class="box-title">SPES Applicants</div>
            <div class="box-value" data-target="<?php echo $totalSpesApplicantsCount; ?>">0</div>
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

            <div id="total-applicants-scholarship" class="page">
                <h2>Scholarship Programs</h2>
                <p>List of all scholarship programs available in the system:</p>
                <button class="back-btn" onclick="showPage('total-applicants-page')">Back to Total Applicants</button>
                <?php
                // Corrected SQL query to count all applicants for each scholarship
                $totalApplicantsSql = "SELECT s.*, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id GROUP BY s.scholarship_id";
                $totalApplicantsResult = $conn->query($totalApplicantsSql);
                $totalApplicantsScholarships = $totalApplicantsResult->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if (count($totalApplicantsScholarships) > 0): ?>
                    <ul class="scholarship-list">
                        <?php foreach ($totalApplicantsScholarships as $scholarship): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($scholarship['title']); ?></strong>
                                <div class="scholarship-list-description">
                                    <?php echo nl2br(htmlspecialchars($scholarship['description'])); ?>
                                </div>
                                <div class="scholarship-list-footer">
                                    <span class="scholarship-status <?php echo $scholarship['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                        Status: <?php echo ucfirst($scholarship['status']); ?>
                                    </span>
                                    <p>
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
        
        <form id="searchForm" method="GET">
            <input type="hidden" name="view_scholarship" value="<?php echo htmlspecialchars($viewScholarshipId); ?>">
            <input type="text" name="search" placeholder="Search by Application ID..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" class="search-input">
            <button type="submit" class="search-button">Search</button>
        </form>
        <button class="back-btn" onclick="window.location.href='admin_dashboard.php#total-applicants-scholarship'">Back to program</button>

        <table class="applicants-table">
            <thead>
                <tr>
                    <th>Application ID</th>
                    <th>Name</th>
                    <th>Application Details</th>
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
                            <button class="btn-outline" onclick='showAppFormModal(<?php echo json_encode($app); ?>)'>View Details</button>
                        </td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <button type="submit" name="approve_application" class="btn-approve">Approve</button>
                                </form>
                                <button type="button" class="btn-reject" onclick="showRejectionModal('<?php echo $app['application_id']; ?>')">Reject</button>
                            <?php else: 
                                $statusClass = 'status-' . htmlspecialchars($app['status']);
                            ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center" style="padding: 20px;">No applicants found for this scholarship or search query.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
</div>
<?php endif; ?>

            <div id="appFormModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeAppFormModal()">&times;</span>
                    <div class="modal-header">Scholarship Application Details</div>
                    <div class="modal-body" id="appFormModalBody"></div>
                </div>
            </div>

            <div id="editSlotsModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeEditSlotsModal()">&times;</span>
                    <div class="modal-header">Edit Scholarship Slots</div>
                    <form method="POST">
                        <input type="hidden" name="scholarship_id" id="editSlotsScholarshipId">
                        <p>Current Slots: <span id="currentSlotsText"></span></p>
                        <p>Remaining Slots: <span id="remainingSlotsText"></span></p>
                        <p>Total Applicants: <span id="totalApplicantsText"></span></p>
                        <label for="new_slots">Set New Total Slots:</label>
                        <input type="number" id="new_slots" name="new_slots" required class="search-input" style="width:100%; box-sizing: border-box; margin-top:10px;">
                        <button type="submit" name="update_slots" class="search-button" style="margin-top:15px; width:100%;">Update Slots</button>
                    </form>
                </div>
            </div>
            
            <div id="total-applicants-spes" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">SPES Applicants</h2>
                    <p class="applicants-p">Review, approve, or reject applications for the SPES program. Search by Application ID.</p>
                    
                    <?php
                    // --- PHP Logic to handle SPES search by ID ---
                    $searchSpesIdTerm = isset($_GET['search_spes_id']) ? trim($_GET['search_spes_id']) : '';
                    
                    $spesListSql = "
                        SELECT sa.*, u.Fname, u.Lname 
                        FROM spes_applications sa
                        JOIN user u ON sa.user_id = u.user_id
                    ";

                    if (!empty($searchSpesIdTerm)) {
                        $spesListSql .= " WHERE sa.spes_application_id LIKE ?";
                        $searchSpesIdSql = '%' . $searchSpesIdTerm . '%';
                    }
                    $spesListSql .= " ORDER BY sa.created_at DESC";
                    
                    $spesStmt = $conn->prepare($spesListSql);

                    if (!empty($searchSpesIdTerm)) {
                        $spesStmt->bind_param("s", $searchSpesIdSql);
                    }
                    $spesStmt->execute();
                    $spesResult = $spesStmt->get_result();
                    $spesApplicantsList = $spesResult->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <form id="searchSpesTotalForm" method="GET">
                        <input type="text" name="search_spes_id" placeholder="Search by Application ID..." value="<?php echo htmlspecialchars($searchSpesIdTerm); ?>" class="search-input">
                        <button type="submit" class="search-button">Search</button>
                    </form>
                    <button class="back-btn" onclick="showPage('total-applicants-page')">Back</button>

                    <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>Application ID</th>
                                <th>Name</th>
                                <th>Date Applied</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($spesApplicantsList) > 0): ?>
                            <?php foreach ($spesApplicantsList as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['spes_application_id']); ?></td>
                                    <td><?php echo htmlspecialchars($app['Fname'] . ' ' . $app['Lname']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            $statusClass = 'status-' . htmlspecialchars($app['status']);
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-outline" onclick='showSpesAppModal(<?php echo json_encode($app); ?>)'>View Details</button>
                                        <?php if ($app['status'] == 'pending'): ?>
                                            <form method="POST" class="inline-form" style="margin-left:5px;">
                                                <input type="hidden" name="spes_application_id" value="<?php echo $app['spes_application_id']; ?>">
                                                <button type="submit" name="approve_spes_application" class="btn-approve">Approve</button>
                                                <button type="submit" name="reject_spes_application" class="btn-reject">Reject</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center" style="padding: 20px;">No SPES applicants found for this search.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="application-page" class="page">
                <h1 class="h1-title-appManagement">Approved Applicants</h1>
                <p class="p-description-appM">This section displays the total number of approved applicants, categorized by program type.</p>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="box-title">Scholarship Awardees</div>
                        <div class="box-value" data-target="<?php echo htmlspecialchars($approvedApplicantsCount); ?>">0</div>
                        <div class="box-description">View all approved applicants for various scholarship programs.</div>
                        <button class="view-details" onclick="showPage('approved-scholarship-programs-page')">View Details</button>
                    </div>
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-id-badge"></i></div>
                        <div class="box-title">SPES Awardees</div>
                        <div class="box-value" data-target="<?php echo $approvedSpesCount; ?>">0</div>
                        <div class="box-description">View all approved applicants for the SPES program.</div>
                        <button class="view-details" onclick="showPage('approved-spes-list-page')">View Details</button>
                    </div>
                </div>
            </div>
            <div id="approved-scholarship-programs-page" class="page">
                <h1 class="h1-title-appManagement">Approved Scholarship Programs</h1>
                <p class="p-description-appM">Select a scholarship program to view its list of approved applicants.</p>
                <button class="back-btn" onclick="showPage('application-page')">Back</button>
                <div class="dashboard-boxes">
                    <?php foreach ($scholarships as $scholarship): ?>
                        <div class="box">
                            <div class="box-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
                            <button class="view-details" onclick="window.location.href='admin_dashboard.php?view_approved=<?php echo $scholarship['scholarship_id']; ?>#approved-applicants-list-page'">View Applicants</button>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($scholarships) === 0): ?>
                        <p>No scholarships with approved applicants found.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="approved-spes-list-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">Approved SPES Applicants</h2>
                    <p class="applicants-p">Below is the list of users approved for the SPES program.</p>
                    
                    <?php
                    // --- PHP Logic to handle SPES search ---
                    $searchSpesTerm = isset($_GET['search_spes_name']) ? trim($_GET['search_spes_name']) : '';
                    $searchSpesSql = '%' . $searchSpesTerm . '%';

                    $approvedSpesListSql = "
                        SELECT sa.*, u.Fname, u.Lname 
                        FROM spes_applications sa
                        JOIN user u ON sa.user_id = u.user_id
                        WHERE sa.status = 'approved'";

                    if (!empty($searchSpesTerm)) {
                        $approvedSpesListSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
                    }
                    $approvedSpesListSql .= " ORDER BY sa.created_at DESC";
                    
                    $apprSpesStmt = $conn->prepare($approvedSpesListSql);

                    if (!empty($searchSpesTerm)) {
                        $apprSpesStmt->bind_param("s", $searchSpesSql);
                    }
                    $apprSpesStmt->execute();
                    $approvedSpesListResult = $apprSpesStmt->get_result();
                    $approvedSpesList = $approvedSpesListResult->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <form id="searchSpesForm" method="GET">
                        <input type="text" name="search_spes_name" placeholder="Search by applicant name..." value="<?php echo htmlspecialchars($searchSpesTerm); ?>" class="search-input">
                        <button type="submit" class="search-button">Search</button>
                    </form>
                    <button class="back-btn" onclick="showPage('application-page')">Back</button>

                    <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date Approved</th>
                                <th>Application Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($approvedSpesList) > 0): ?>
                            <?php foreach ($approvedSpesList as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['Fname'] . ' ' . $app['Lname']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td><strong>SPES</strong></td>
                                    <td>
                                        <button class="btn-outline" onclick='showSpesAppModal(<?php echo json_encode($app); ?>)'>View Details</button>
                                         <button class="btn-primary" onclick="window.location.href='admin_dashboard.php?chat_user=<?php echo $app['user_id']; ?>#user-concerns-page'">
                                            <i class="fas fa-envelope"></i> Message
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center" style="padding: 20px;">No approved SPES applicants found for this search.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($viewApprovedScholarshipId): ?>
        <div id="approved-applicants-list-page" class="page active">
            <div class="applicants-container">
                <h2 class="applicants-h2">Approved Applicants for: <?php echo htmlspecialchars($approved_scholarship_title); ?></h2>
                <p class="applicants-p">Below is the list of users approved for this scholarship.</p>

                <form id="searchApprovedForm" method="GET">
                    <input type="hidden" name="view_approved" value="<?php echo htmlspecialchars($viewApprovedScholarshipId); ?>">
                    <input type="text" name="search_approved_name" placeholder="Search by applicant name..." value="<?php echo isset($_GET['search_approved_name']) ? htmlspecialchars($_GET['search_approved_name']) : ''; ?>" class="search-input">
                    <button type="submit" class="search-button">Search</button>
                </form>
                <button class="back-btn" onclick="showPage('approved-scholarship-programs-page')">Back to Programs</button>
                <table class="applicants-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Application Type</th>
                            <th>Other Details</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($approved_applicants) > 0): ?>
                        <?php foreach ($approved_applicants as $applicant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($applicant['Fname'] . ' ' . $applicant['Lname']); ?></td>
                                <td><strong>Scholarship</strong></td>
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
                            <td colspan="4" class="text-center" style="padding: 20px;">No approved applicants found for this scholarship or search query.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>            

            <div id="reports-page" class="page">
                <h2>Reports</h2>
                <p>Access and view document reports here.</p>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="box-title">Scholarship Document</div>
                        <div class="box-description">View documents of approved scholarship awardees.</div>
                        <button class="view-details" onclick="showPage('scholarship-document-page')">View Details</button>
                    </div>
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-file-contract"></i></div>
                        <div class="box-title">SPES Documents</div>
                        <div class="box-description">View documents of approved SPES awardees.</div>
                        <button class="view-details" onclick="showPage('spes-document-page')">View Details</button>
                    </div>
                </div>
            </div>

            <div id="scholarship-document-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">Scholarship Applicant Documents</h2>
                    <p class="applicants-p">Review the submitted documents for all approved scholarship awardees.</p>
                    <form method="GET">
                        <input type="text" name="search_scholarship_docs" placeholder="Search by applicant name..." value="<?php echo htmlspecialchars($scholarshipDocsSearch ?? ''); ?>" class="search-input">
                        <button type="submit" class="search-button">Search</button>
                        <input type="hidden" name="search_spes_docs" value="<?php echo htmlspecialchars($spesDocsSearch ?? ''); ?>">
                    </form>
                    <button class="back-btn" onclick="showPage('reports-page')">Back to Reports</button>
                    <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>View Documents</th>
                                <th>Applicant Name</th>
                                <th>Application Form</th>
                                <th>Documents Requirements</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scholarship_docs_users as $user): ?>
                                <?php
                                    $docs_array = json_decode($user['documents'], true);
                                    $has_docs = is_array($docs_array) && !empty($docs_array);
                                    $status = $has_docs ? 'Complete' : 'INC';
                                ?>
                                <tr>
                                    <td>
                                        <button class="btn-outline" <?php if(!$has_docs) echo 'disabled'; ?> onclick='viewUserDocuments(<?php echo json_encode($user['documents']); ?>, "<?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?>")'>View Documents</button>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?></td>
                                    <td class="text-center"><i class="fas fa-check-circle icon-check"></i></td>
                                    <td class="text-center">
                                        <?php if ($has_docs): ?>
                                            <i class="fas fa-check-circle icon-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle icon-times"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status === 'Complete' ? 'status-approved' : 'status-rejected'; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($scholarship_docs_users) === 0): ?>
                                <tr><td colspan="5" class="text-center" style="padding: 20px;">No approved scholarship applicants found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="spes-document-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">SPES Applicant Documents</h2>
                    <p class="applicants-p">Review the submitted documents for all approved SPES awardees.</p>
                    <form method="GET">
                        <input type="text" name="search_spes_docs" placeholder="Search by applicant name..." value="<?php echo htmlspecialchars($spesDocsSearch ?? ''); ?>" class="search-input">
                        <button type="submit" class="search-button">Search</button>
                        <input type="hidden" name="search_scholarship_docs" value="<?php echo htmlspecialchars($scholarshipDocsSearch ?? ''); ?>">
                    </form>
                    <button class="back-btn" onclick="showPage('reports-page')">Back to Reports</button>
                    <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>View Documents</th>
                                <th>Applicant Name</th>
                                <th>Application Form</th>
                                <th>Documents Requirements</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spes_docs_users as $user): ?>
                                <?php
                                    $id_paths_array = json_decode($user['id_image_paths'], true);
                                    $reqs_paths_array = json_decode($user['spes_documents_path'], true);

                                    $has_id = is_array($id_paths_array) && !empty($id_paths_array);
                                    
                                    $has_reqs = !empty($user['spes_documents_path']);
                                    // A more robust check if we are saving multiple reqs as JSON
                                    if (is_string($user['spes_documents_path'])) {
                                        $reqs_temp = json_decode($user['spes_documents_path'], true);
                                        $has_reqs = is_array($reqs_temp) && !empty($reqs_temp);
                                    }


                                    $status = $has_id && $has_reqs ? 'Complete' : 'INC';
                                ?>
                                <tr>
                                    <td>
                                        <button class="btn-outline" <?php if(!$has_id && !$has_reqs) echo 'disabled'; ?> onclick='viewSpesUserDocuments(<?php echo json_encode(["ids" => $user["id_image_paths"], "reqs" => $user["spes_documents_path"]]); ?>, "<?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?>")'>View Documents</button>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?></td>
                                    <td class="text-center"><i class="fas fa-check-circle icon-check"></i></td>
                                    <td class="text-center">
                                        <?php if ($has_reqs): ?>
                                            <i class="fas fa-check-circle icon-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle icon-times"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status === 'Complete' ? 'status-approved' : 'status-rejected'; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                             <?php if (count($spes_docs_users) === 0): ?>
                                <tr><td colspan="5" class="text-center" style="padding: 20px;">No approved SPES applicants found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<div id="user-request-page" class="page">
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
                        // Decode the JSON and check if it's valid and not empty
                        $validIdFiles = json_decode($user['valid_id'], true);
                        if (is_array($validIdFiles) && !empty($validIdFiles[0])):
                        ?>
                            <button class="btn-outline" onclick='showValidIdModal(<?php echo json_encode($user['valid_id']); ?>)'>View ID</button>
                        <?php else: ?>
                            <span style="color:#B22222;">No ID uploaded.</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <button type="submit" name="approve_user" class="btn-approve">Approve</button>
                        </form>
                        <form method="POST" class="inline-form">
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
                    <td colspan="4" class="text-center" style="padding: 20px;">No pending user requests found.</td>
                </tr>
            <?php
            endif; 
            ?>
            </tbody>
        </table>
    </div>
</div>
<div id="user-concerns-page" class="page">
    <h2>User Concerns Chat</h2>
    <div class="concerns-chat-container">
        <div class="concerns-chat-list">
            <h3>Conversations</h3>
            <ul id="userList">
                
                <?php foreach ($scholarships as $group): 
                    $isGroupActive = ($selectedGroupId == $group['scholarship_id']) ? 'active' : '';
                ?>
                    <li class="<?php echo $isGroupActive; ?>" onclick="window.location.href='admin_dashboard.php?chat_group=<?php echo $group['scholarship_id']; ?>#user-concerns-page'">
                        <i class="fas fa-users chat-list-icon icon-bg"></i>
                        <strong><?php echo htmlspecialchars($group['title']); ?></strong>
                    </li>
                <?php endforeach; ?>

                <?php if(count($scholarships) > 0 && count($usersWithConcerns) > 0): ?>
                    <hr style="margin: 5px 0; border-color: #ccc;">
                <?php endif; ?>

                <?php foreach ($usersWithConcerns as $user): 
                    $isUserActive = ($selectedUserId == $user['user_id']) ? 'active' : '';
                ?>
                    <li class="<?php echo $isUserActive; ?>" onclick="window.location.href='admin_dashboard.php?chat_user=<?php echo $user['user_id']; ?>#user-concerns-page'">
                        <img src="../../../../<?php echo htmlspecialchars(!empty($user['profile_pic']) ? $user['profile_pic'] : 'images/user.png'); ?>" class="chat-list-icon">
                        <?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="concerns-chat-main">
            <div class="concerns-chat-header" id="concernChatHeader">
                <?php echo $chatTitle; ?>
            </div>
            <div class="concerns-chat-messages" id="concernChatMessages">
                <?php if ($selectedGroupId || $selectedUserId): ?>
                    <?php
                        // --- START: MODIFIED - PRE-FETCH APPROVAL STATUSES ---
                        $userIdsInChat = array_unique(array_column($chatMessages, 'user_id'));
                        $approvedScholarUsers = [];
                        $approvedSpesUsers = [];

                        if (!empty($userIdsInChat)) {
                            // Sanitize to prevent issues, although we control the user_id values
                            $userIdsInChat = array_filter($userIdsInChat, 'is_numeric');
                            if(!empty($userIdsInChat)){
                                $userIdsPlaceholder = implode(',', array_fill(0, count($userIdsInChat), '?'));
                                $types = str_repeat('i', count($userIdsInChat));
                                
                                $stmt = $conn->prepare("SELECT DISTINCT user_id FROM applications WHERE user_id IN ($userIdsPlaceholder) AND status = 'approved'");
                                $stmt->bind_param($types, ...$userIdsInChat);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) { $approvedScholarUsers[] = $row['user_id']; }

                                $stmt = $conn->prepare("SELECT DISTINCT user_id FROM spes_applications WHERE user_id IN ($userIdsPlaceholder) AND status = 'approved'");
                                $stmt->bind_param($types, ...$userIdsInChat);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) { $approvedSpesUsers[] = $row['user_id']; }
                            }
                        }
                        // --- END: MODIFIED - PRE-FETCH APPROVAL STATUSES ---
                    ?>

                    <?php foreach ($chatMessages as $msg): ?>
                        <div class="concern-message <?php echo $msg['sender'] === 'admin' ? 'admin' : 'user'; ?>">
                            
                            <?php 
                            // --- START: MODIFIED - CONDITIONAL THREE-DOTS MENU ---
                            if ($msg['sender'] === 'user' && !empty($msg['attachment_path'])) {
                                $isApprovedScholar = in_array($msg['user_id'], $approvedScholarUsers);
                                $isApprovedSpes = in_array($msg['user_id'], $approvedSpesUsers);
                                if ($isApprovedScholar || $isApprovedSpes) {
                            ?>
                                <div class="message-options">
                                    <span class="three-dots" onclick="toggleMessageMenu(<?php echo $msg['id']; ?>)">⋮</span>
                                    <div class="message-menu" id="menu-<?php echo $msg['id']; ?>">
                                        <?php if ($isApprovedScholar): ?>
                                        <form method="POST">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $msg['user_id']; ?>">
                                            <button type="submit" name="save_to_documents_scholarship">
                                                <i class="fas fa-graduation-cap"></i>Save to Scholarship
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($isApprovedSpes): ?>
                                        <form method="POST">
                                             <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $msg['user_id']; ?>">
                                            <button type="submit" name="save_to_documents_spes">
                                                <i class="fas fa-briefcase"></i>Save to SPES
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                } // end if is approved
                            } elseif ($msg['sender'] === 'admin') { 
                            ?>
                                <div class="message-options">
                                    <span class="three-dots" onclick="toggleMessageMenu(<?php echo $msg['id']; ?>)">⋮</span>
                                    <div class="message-menu" id="menu-<?php echo $msg['id']; ?>">
                                        <form method="POST">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                             <?php if ($selectedUserId): ?><input type="hidden" name="chat_user_id" value="<?php echo $selectedUserId; ?>"><?php endif; ?>
                                             <?php if ($selectedGroupId): ?><input type="hidden" name="chat_group_id" value="<?php echo $selectedGroupId; ?>"><?php endif; ?>
                                            <button type="submit" name="delete_admin_message" onclick="return confirm('Are you sure you want to delete this message?')">
                                                <i class="fas fa-trash-alt"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php 
                            } // end if sender
                            // --- END: MODIFIED - CONDITIONAL THREE-DOTS MENU ---
                            ?>
                            
                            <div class="concern-message-content" style="<?php echo $msg['sender'] === 'admin' ? 'margin-right:15px;' : ''; ?>">
                                <?php if($msg['sender'] === 'user' && $selectedGroupId) echo "<strong>" . htmlspecialchars($msg['Fname'] . ' ' . $msg['Lname']) . ":</strong><br>"; ?>
                                <?php if (!empty($msg['message'])) echo nl2br(htmlspecialchars($msg['message'])); ?>
                                
                                <?php if (!empty($msg['attachment_path'])): ?>
                                    <div class="message-attachment">
                                        <a href="<?php echo htmlspecialchars($msg['attachment_path']); ?>" target="_blank" download>
                                            <i class="fas fa-file-download"></i>
                                            <span><?php echo htmlspecialchars(preg_replace('/^[a-f0-9]+_/', '', basename($msg['attachment_path']))); ?></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="concern-message-timestamp">
                                <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="color:#888; margin-top: 20px;">Please select a conversation to start chatting.</div>
                <?php endif; ?>
            </div>
            <?php if ($selectedGroupId || $selectedUserId): ?>
                <form class="concerns-chat-input" method="POST" enctype="multipart/form-data" autocomplete="off">
                    
                    <input type="file" name="attachment" id="adminChatAttachment" style="display: none;">
                    <button type="button" class="upload-btn" onclick="document.getElementById('adminChatAttachment').click();" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <textarea name="admin_reply" id="concernMessageInput" placeholder="Type your reply..."></textarea>
                    
                    <?php if ($selectedGroupId): ?>
                        <input type="hidden" name="chat_group_id" value="<?php echo $selectedGroupId; ?>">
                    <?php elseif ($selectedUserId): ?>
                        <input type="hidden" name="chat_user_id" value="<?php echo $selectedUserId; ?>">
                    <?php endif; ?>

                    <button type="submit" name="send_admin_reply">
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
                $scholarshipsSql_list = "SELECT s.*, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id GROUP BY s.scholarship_id";
                $scholarshipResult_list = $conn->query($scholarshipsSql_list);
                $scholarships_list = $scholarshipResult_list->fetch_all(MYSQLI_ASSOC);
                
                foreach ($scholarships_list as $scholarship): 
                    $remainingSlots = $scholarship['number_of_slots'] - $scholarship['total_applicants'];
                ?>
                    <div class="scholarship-card">
                        <h3><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                        <div class="scholarship-details">
                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($scholarship['description'])); ?></p>
                            <p><strong>Requirements:</strong> <?php echo nl2br(htmlspecialchars($scholarship['requirements'])); ?></p>
                            <p><strong>Benefits:</strong> <?php echo nl2br(htmlspecialchars($scholarship['benefits'])); ?></p>
                            <p><strong>Eligibility:</strong> <?php echo nl2br(htmlspecialchars($scholarship['eligibility'])); ?></p>
                        </div>
                        <div>
                            <span class="scholarship-status <?php echo $scholarship['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                Status: <?php echo ucfirst($scholarship['status']); ?>
                            </span>
                            <br>
                            <p style="font-size: 12px; margin: 5px 0;"><strong>Slots:</strong> 
                                <?php echo htmlspecialchars($remainingSlots); ?> of <?php echo htmlspecialchars($scholarship['number_of_slots']); ?> remaining
                            </p>
                        </div>
                        <div class="scholarship-footer">
                            <form method="POST" class="inline-form">
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
                
                <?php if (count($scholarships_list) === 0): ?>
                <p>No scholarships found. Add a new scholarship using the form above.</p>
                <?php endif; ?>
            </div>

            <div id="rejectionModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeRejectionModal()">&times;</span>
                    <div class="modal-header">Reject Application</div>
                    <form method="POST">
                        <input type="hidden" name="application_id" id="rejectionAppId">
                        <label for="rejectionMessage">Reason for rejection:</label>
                        <textarea id="rejectionMessage" name="rejection_message" rows="5" required style="width:100%;padding:10px;box-sizing:border-box;margin-top:5px;"></textarea>
                        <button type="submit" name="reject_application_with_message" class="btn-danger" style="margin-top:10px;">Submit Rejection</button>
                    </form>
                </div>
            </div>
            
            <div id="validIdModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeValidIdModal()">&times;</span>
                    <div class="modal-header">User Submitted ID</div>
                    <div class="modal-body" id="validIdModalBody"></div>
                </div>
            </div>
            <div id="spesAppModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeSpesAppModal()">&times;</span>
                    <div class="modal-header">SPES Application Details</div>
                    <div class="modal-body" id="spesAppModalBody"></div>
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
                            <form method="POST" class="inline-form">
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

            <div id="userDetailsModal">
                <div>
                    <span onclick="closeUserDetailsModal()">&times;</span>
                    <h2>User Details</h2>
                    <div id="userDetailsContent"></div>
                </div>
            </div>

             <div id="viewDocumentsModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeViewDocumentsModal()">&times;</span>
                    <div class="modal-header" id="viewDocumentsModalHeader">Applicant's Documents</div>
                    <div class="modal-body" id="viewDocumentsModalBody"></div>
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

    function showPage(pageId) {
        document.querySelectorAll('.page').forEach(page => {
            page.classList.remove('active');
        });
        
        const url = new URL(window.location);
        url.hash = pageId;
        // Clean up search params when navigating to a new main page
        if (['home-page', 'application-page', 'scholarship-page', 'send-updates-page', 'total-applicants-page', 'reports-page', 'user-concerns-page', 'user-request-page'].includes(pageId)) {
            url.search = '';
        }
        history.pushState({}, '', url);

        document.getElementById(pageId).classList.add('active');

        switch (pageId) {
            case 'home-page':
                highlightActiveNav('home-nav');
                break;
            case 'application-page':
            case 'approved-scholarship-programs-page':
            case 'approved-applicants-list-page':
            case 'approved-spes-list-page':
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
            case 'scholarship-document-page':
            case 'spes-document-page':
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
    
    // Prioritize search params to determine the active page
    if (urlParams.has('view_scholarship')) {
        hash = 'scholarship-applicants-page';
    } else if (urlParams.has('view_approved')) {
        hash = 'approved-applicants-list-page';
    } else if (urlParams.has('search_spes_name')) {
        hash = 'approved-spes-list-page';
    } else if (urlParams.has('search_spes_id')) {
        hash = 'total-applicants-spes';
    }

    if (hash && document.getElementById(hash)) {
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

    // --- NEW: Number Animation Logic ---
    const animateValue = (obj, start, end, duration) => {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            obj.innerHTML = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const targetValue = parseInt(el.getAttribute('data-target'), 10);
                animateValue(el, 0, targetValue, 1500); // Animate over 1.5 seconds
                observer.unobserve(el); // Stop observing after animation
            }
        });
    }, { threshold: 0.5 }); // Trigger when 50% of the element is visible

    document.querySelectorAll('.box-value').forEach(el => {
        observer.observe(el);
    });
    // --- END: Number Animation Logic ---

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
    
    // --- START: JAVASCRIPT FOR APPROVED SPES SEARCH ---
    const searchSpesForm = document.getElementById('searchSpesForm');
    if(searchSpesForm) {
        searchSpesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const searchTerm = form.querySelector('input[name="search_spes_name"]').value;
            window.location.href = `admin_dashboard.php?search_spes_name=${encodeURIComponent(searchTerm)}#approved-spes-list-page`;
        });
    }
    // --- END: JAVASCRIPT FOR APPROVED SPES SEARCH ---
    
    // --- START: JAVASCRIPT FOR TOTAL SPES APPLICANTS SEARCH ---
    const searchSpesTotalForm = document.getElementById('searchSpesTotalForm');
    if(searchSpesTotalForm) {
        searchSpesTotalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const searchTerm = form.querySelector('input[name="search_spes_id"]').value;
            window.location.href = `admin_dashboard.php?search_spes_id=${encodeURIComponent(searchTerm)}#total-applicants-spes`;
        });
    }
    // --- END: JAVASCRIPT FOR TOTAL SPES APPLICANTS SEARCH ---
});

    function showAppFormModal(applicantJson) {
        let app = typeof applicantJson === 'string' ? JSON.parse(applicantJson) : applicantJson;
        let html = '<div style="font-size: 12px;">';

        if (!app) {
            document.getElementById('appFormModalBody').innerHTML = "<p>No application data found.</p>";
            document.getElementById('appFormModal').style.display = 'flex';
            return;
        }
        
        // Personal Info
        html += '<h4>Personal Information</h4>';
        html += `<p><strong>Full Name:</strong> ${app.fullname || 'N/A'}</p>`;
        html += `<p><strong>Date of Birth:</strong> ${app.birthdate || 'N/A'}</p>`;
        html += `<p><strong>Place of Birth:</strong> ${app.place_of_birth || 'N/A'}</p>`;
        html += `<p><strong>Gender:</strong> ${app.gender || 'N/A'}</p>`;
        html += `<p><strong>Civil Status:</strong> ${app.civil_status || 'N/A'}</p>`;
        html += `<p><strong>Address:</strong> ${app.address || 'N/A'}</p>`;
        html += `<p><strong>Contact Number:</strong> ${app.contact || 'N/A'}</p>`;
        html += `<p><strong>Facebook Account:</strong> ${app.facebook || 'N/A'}</p>`;

        // Family Background
        html += '<hr style="margin: 15px 0;"><h4>Family Background</h4>';
        html += `<p><strong>Father\'s Name:</strong> ${app.father_name || 'N/A'} - <i>${app.father_occupation || 'N/A'}</i></p>`;
        html += `<p><strong>Mother\'s Name:</strong> ${app.mother_name || 'N/A'} - <i>${app.mother_occupation || 'N/A'}</i></p>`;
        html += `<p><strong>Monthly Family Income:</strong> ${app.family_income || 'N/A'}</p>`;
        html += `<p><strong>Number of Dependents:</strong> ${app.dependents || 'N/A'}</p>`;

        // Educational Background
        html += '<hr style="margin: 15px 0;"><h4>Educational Background</h4>';
        html += `<table class="applicants-table" style="font-size: 11px;">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>School</th>
                            <th>Honors</th>
                            <th>Graduation/Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Elementary</td>
                            <td>${app.elem_school || 'N/A'}</td>
                            <td>${app.elem_honors || 'N/A'}</td>
                            <td>${app.elem_grad || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td>High School</td>
                            <td>${app.hs_school || 'N/A'}</td>
                            <td>${app.hs_honors || 'N/A'}</td>
                            <td>${app.hs_grad || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td>Vocational</td>
                            <td>${app.voc_school || 'N/A'}</td>
                            <td>${app.voc_honors || 'N/A'}</td>
                            <td>${app.voc_grad || 'N/A'}</td>
                        </tr>
                    </tbody>
                </table>`;
        
        // College Background
        html += '<hr style="margin: 15px 0;"><h4>College Background</h4>';
        html += `<p><strong>School:</strong> ${app.college_school || 'N/A'}</p>`;
        html += `<p><strong>Course & Year:</strong> ${app.college_course || 'N/A'}</p>`;
        html += `<p><strong>Previous Semester Average:</strong> ${app.college_average || 'N/A'}</p>`;
        html += `<p><strong>Awards / Recognitions:</strong> ${app.college_awards || 'N/A'}</p>`;

        // Documents
        html += '<hr style="margin: 15px 0;"><h4>Uploaded Documents</h4>';
        try {
            const docs = JSON.parse(app.documents);
            if (Array.isArray(docs) && docs.length > 0) {
                docs.forEach(docPath => {
                    const fullPath = `<?php echo BASE_URL; ?>${docPath.replace('../../../../', '')}`;
                    // Extract the original filename after the unique ID and underscore
                    const fileNameWithId = docPath.split('/').pop();
                    const fileName = fileNameWithId.substring(fileNameWithId.indexOf('_') + 1);

                    // Create a download link for each document. The 'download' attribute prompts the browser to download.
                    html += `<p style="margin-bottom: 8px;">
                                <i class="fas fa-file-download" style="margin-right: 8px; color: #090549;"></i>
                                <a href="${fullPath}" download="${fileName}" style="color:#090549; text-decoration: none; font-weight: bold;">
                                    ${fileName}
                                </a>
                             </p>`;
                });
            } else {
                html += '<p>No documents were uploaded.</p>';
            }
        } catch (e) {
            html += '<p>No documents were uploaded or there was an error reading them.</p>';
        }

        html += '</div>';
        document.getElementById('appFormModalBody').innerHTML = html;
        document.getElementById('appFormModal').style.display = 'flex';
    }

    function closeAppFormModal() {
        document.getElementById('appFormModal').style.display = 'none';
        document.getElementById('appFormModalBody').innerHTML = '';
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
    
    function showSpesAppModal(spesAppJson) {
        let app = typeof spesAppJson === 'string' ? JSON.parse(spesAppJson) : spesAppJson;
        let html = '<div style="font-size: 12px;">';

        if (!app) {
            document.getElementById('spesAppModalBody').innerHTML = "<p>No application data found.</p>";
            document.getElementById('spesAppModal').style.display = 'flex';
            return;
        }

        const fullName = `${app.firstname || ''} ${app.middlename || ''} ${app.surname || ''}`.trim();
        
        // --- START: NEW CODE - Define path for SPES documents ---
        const docPath = `<?php echo BASE_URL; ?>${app.spes_documents_path ? app.spes_documents_path.replace('../../../../', '') : ''}`;
        // --- END: NEW CODE ---


        // --- Personal Information Section ---
        html += '<h4>Personal Information</h4>';
        html += `<p><strong>Full Name:</strong> ${fullName}</p>`;
        html += `<p><strong>Date of Birth:</strong> ${app.dob || 'N/A'}</p>`;
        html += `<p><strong>Place of Birth:</strong> ${app.place_of_birth || 'N/A'}</p>`;
        html += `<p><strong>Citizenship:</strong> ${app.citizenship || 'N/A'}</p>`;
        html += `<p><strong>Sex:</strong> ${app.sex || 'N/A'}</p>`;
        html += `<p><strong>Civil Status:</strong> ${app.civil_status || 'N/A'}</p>`;
        html += `<p><strong>Contact:</strong> ${app.contact || 'N/A'}</p>`;
        html += `<p><strong>Email:</strong> ${app.email || 'N/A'}</p>`;
        html += `<p><strong>Social Media:</strong> ${app.social_media || 'N/A'}</p>`;
        html += `<p><strong>Present Address:</strong> ${app.present_address || 'N/A'}</p>`;
        html += `<p><strong>Permanent Address:</strong> ${app.permanent_address || 'N/A'}</p>`;
        
        // --- MODIFIED UPLOADED ID DISPLAY ---
        html += `<p><strong>Uploaded ID:</strong></p>`;
        try {
            const idImagePaths = JSON.parse(app.id_image_paths);
            if (Array.isArray(idImagePaths) && idImagePaths.length > 0) {
                idImagePaths.forEach(path => {
                    if(path) {
                        const fullPath = `<?php echo BASE_URL; ?>${path.replace('../../../../', '')}`;
                        html += `<a href="${fullPath}" target="_blank" style="display:inline-block; margin-right: 10px; margin-bottom: 10px;">
                                     <img src="${fullPath}" alt="Applicant ID" style="max-width: 200px; height: auto; border-radius: 5px; cursor: pointer; border: 1px solid #ddd;">
                                 </a>`;
                    }
                });
            } else {
                html += `<p>No ID images were uploaded.</p>`;
            }
        } catch (e) {
            html += `<p>No ID images were uploaded or there was an error reading them.</p>`;
        }

        // --- Parental & Status Section ---
        html += '<hr style="margin: 15px 0;"><h4>Parental & Status Information</h4>';
        html += `<p><strong>GSIS Beneficiary:</strong> ${app.gsis_beneficiary || 'N/A'}</p>`;
        html += `<p><strong>Student Type:</strong> ${app.student_type || 'N/A'}</p>`;
        html += `<p><strong>Parent Status:</strong> ${app.parent_status || 'N/A'}</p>`;
        html += `<p><strong>Father:</strong> ${app.father_name_contact || 'N/A'} - <i>${app.father_occupation || 'N/A'}</i></p>`;
        html += `<p><strong>Mother:</strong> ${app.mother_name_contact || 'N/A'} - <i>${app.mother_occupation || 'N/A'}</i></p>`;

        // --- Educational Background Section ---
        html += '<hr style="margin: 15px 0;"><h4>Educational Background</h4>';
        html += `<table class="applicants-table" style="font-size: 11px;">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>School</th>
                            <th>Degree/Course</th>
                            <th>Year/Level</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Elementary</td>
                            <td>${app.elem_school || 'N/A'}</td>
                            <td>${app.elem_degree || 'N/A'}</td>
                            <td>${app.elem_year || 'N/A'}</td>
                            <td>${app.elem_attendance || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td>Secondary</td>
                            <td>${app.sec_school || 'N/A'}</td>
                            <td>${app.sec_degree || 'N/A'}</td>
                            <td>${app.sec_year || 'N/A'}</td>
                            <td>${app.sec_attendance || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td>Tertiary</td>
                            <td>${app.ter_school || 'N/A'}</td>
                            <td>${app.ter_degree || 'N/A'}</td>
                            <td>${app.ter_year || 'N/A'}</td>
                            <td>${app.ter_attendance || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td>Tech-Voc</td>
                            <td>${app.tech_school || 'N/A'}</td>
                            <td>${app.tech_degree || 'N/A'}</td>
                            <td>${app.tech_year || 'N/A'}</td>
                            <td>${app.tech_attendance || 'N/A'}</td>
                        </tr>
                    </tbody>
                </table>`;
        
        // --- Skills & SPES History Section ---
        html += '<hr style="margin: 15px 0;"><h4>Skills & SPES History</h4>';
        html += `<p><strong>Special Skills:</strong> ${app.special_skills || 'None'}</p>`;
        html += `<p><strong>Availment History:</strong> ${app.availment_history || 'None'}</p>`;
        html += `<p><strong>Year History:</strong> ${app.year_history || 'N/A'}</p>`;
        html += `<p><strong>SPES ID History:</strong> ${app.spes_id_history || 'N/A'}</p>`;
        
        // --- START: NEW SECTION FOR SPES DOCUMENTS ---
        html += '<hr style="margin: 15px 0;"><h4>Uploaded Requirement Documents</h4>';
        if (app.spes_documents_path) {
            const fileNameWithId = app.spes_documents_path.split('/').pop();
            const fileName = fileNameWithId.substring(fileNameWithId.indexOf('_') + 1);
            html += `<p style="margin-bottom: 8px;">
                        <i class="fas fa-file-download" style="margin-right: 8px; color: #090549;"></i>
                        <a href="${docPath}" download="${fileName}" style="color:#090549; text-decoration: none; font-weight: bold;">
                            ${fileName}
                        </a>
                     </p>`;
        } else {
            html += '<p>No requirement documents were uploaded.</p>';
        }
        // --- END: NEW SECTION FOR SPES DOCUMENTS ---


        html += '</div>';

        document.getElementById('spesAppModalBody').innerHTML = html;
        document.getElementById('spesAppModal').style.display = 'flex';
    }

    function closeSpesAppModal() {
        document.getElementById('spesAppModal').style.display = 'none';
        document.getElementById('spesAppModalBody').innerHTML = '';
    }

    // --- START: NEW JAVASCRIPT FOR VALID ID MODAL ---
    // --- START: RESTORED WORKING JAVASCRIPT FOR VALID ID MODAL ---
    function showValidIdModal(validIdJson) {
        let idFiles;
        try {
            idFiles = JSON.parse(validIdJson);
        } catch(e) {
            idFiles = [];
        }

        let html = '';
        if (Array.isArray(idFiles) && idFiles.length > 0) {
            // Display Front ID
            if(idFiles[0]) {
                const frontPath = `<?php echo BASE_URL; ?>${idFiles[0].replace('../../../../', '').replace('form_prac/', '')}`;
                html += `<h4>Front of ID</h4>
                        <a href="${frontPath}" target="_blank">
                            <img src="${frontPath}" alt="Front of ID" style="max-width: 100%; height: auto; border-radius: 5px; margin-bottom: 15px;">
                        </a>`;
            } else {
                html += `<h4>Front of ID</h4><p>Not provided.</p>`;
            }

            // Display Back ID
            if(idFiles[1]) {
                const backPath = `<?php echo BASE_URL; ?>${idFiles[1].replace('../../../../', '').replace('form_prac/', '')}`;
                html += `<h4>Back of ID</h4>
                        <a href="${backPath}" target="_blank">
                            <img src="${backPath}" alt="Back of ID" style="max-width: 100%; height: auto; border-radius: 5px;">
                        </a>`;
            } else {
                html += `<h4>Back of ID</h4><p>Not provided.</p>`;
            }
        } else {
            html = '<p>No ID images were uploaded by the user.</p>';
        }

        document.getElementById('validIdModalBody').innerHTML = html;
        document.getElementById('validIdModal').style.display = 'flex';
    }

    function closeValidIdModal() {
        document.getElementById('validIdModal').style.display = 'none';
    }
    // --- END: NEW JAVASCRIPT FOR VALID ID MODAL ---

    // --- START: JAVASCRIPT FOR VIEW DOCUMENTS MODAL ---
    function viewUserDocuments(documents, userName) {
        document.getElementById('viewDocumentsModalHeader').textContent = `Documents for ${userName}`;
        let docs;
        try {
            docs = JSON.parse(documents);
        } catch (e) {
            docs = documents ? [documents] : [];
        }

        let html = '';
        if (Array.isArray(docs) && docs.length > 0 && docs[0] !== null) {
            docs.forEach(docPath => {
                if (docPath) { 
                    const fullPath = `<?php echo BASE_URL; ?>${docPath.replace('../../../../', '')}`;
                    const isImage = /\.(jpg|jpeg|png|gif)$/i.test(docPath);
                    const fileName = docPath.substring(docPath.indexOf('_') + 1);
                    
                    html += `<p><a href="${fullPath}" target="_blank" download="${fileName}" class="btn-outline">
                    <i class="fas fa-download"></i> ${fileName}</a></p>`;
                }
            });
        }
        
        if (html === '') {
             html = '<p>No documents were uploaded by this applicant.</p>';
        }

        document.getElementById('viewDocumentsModalBody').innerHTML = html;
        document.getElementById('viewDocumentsModal').style.display = 'flex';
    }

    function closeViewDocumentsModal() {
        document.getElementById('viewDocumentsModal').style.display = 'none';
        document.getElementById('viewDocumentsModalBody').innerHTML = '';
    }

    function viewSpesUserDocuments(spesDocs, userName) {
        document.getElementById('viewDocumentsModalHeader').textContent = `SPES Documents for ${userName}`;
        let html = '';
        
        // Handle ID images
        html += '<h4>Uploaded IDs:</h4>';
        try {
            const idPaths = JSON.parse(spesDocs.ids);
            if (Array.isArray(idPaths) && idPaths.length > 0) {
                 idPaths.forEach(path => {
                    if (path) {
                        const fullPath = `<?php echo BASE_URL; ?>${path.replace('../../../../', '')}`;
                        const fileName = path.substring(path.indexOf('_') + 1);
                        html += `<p><a href="${fullPath}" target="_blank" download="${fileName}" class="btn-outline">
                        <i class="fas fa-id-card"></i> ${fileName}</a></p>`;
                    }
                });
            } else {
                 html += '<p>No ID documents were uploaded.</p>';
            }
        } catch(e) {
            html += '<p>No ID documents were uploaded.</p>';
        }

        // Handle requirement documents
        html += '<hr style="margin: 20px 0;"><h4>Requirement Documents:</h4>';
        try {
             const reqsPaths = JSON.parse(spesDocs.reqs);
             if(Array.isArray(reqsPaths) && reqsPaths.length > 0) {
                 reqsPaths.forEach(path => {
                    if (path) {
                        const fullPath = `<?php echo BASE_URL; ?>${path.replace('../../../../', '')}`;
                        const fileName = path.substring(path.indexOf('_') + 1);
                        html += `<p><a href="${fullPath}" target="_blank" download="${fileName}" class="btn-outline">
                        <i class="fas fa-file-alt"></i> ${fileName}</a></p>`;
                    }
                });
             } else {
                 html += '<p>No requirement documents were uploaded.</p>';
             }
        } catch(e) {
             html += '<p>No requirement documents were uploaded.</p>';
        }

        document.getElementById('viewDocumentsModalBody').innerHTML = html;
        document.getElementById('viewDocumentsModal').style.display = 'flex';
    }

    window.onclick = function(event) {
        let appModal = document.getElementById('appFormModal');
        let editSlotsModal = document.getElementById('editSlotsModal');
        let rejectionModal = document.getElementById('rejectionModal');
        let userDetailsModal = document.getElementById('userDetailsModal');
        let spesAppModal = document.getElementById('spesAppModal');
        let validIdModal = document.getElementById('validIdModal');
        let viewDocumentsModal = document.getElementById('viewDocumentsModal');
        
        if (event.target === appModal) closeAppFormModal();
        if (event.target === editSlotsModal) closeEditSlotsModal();
        if (event.target === rejectionModal) closeRejectionModal();
        if (event.target === userDetailsModal) closeUserDetailsModal();
        if (event.target === spesAppModal) closeSpesAppModal();
        if (event.target === validIdModal) closeValidIdModal();
        if (event.target === viewDocumentsModal) closeViewDocumentsModal();
    };
    // --- END: JAVASCRIPT FOR VIEW DOCUMENTS MODAL ---

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