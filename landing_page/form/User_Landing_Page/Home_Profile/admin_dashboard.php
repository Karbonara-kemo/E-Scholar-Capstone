<?php
define('BASE_URL', 'http://localhost/form_prac/');
include '../../../../connect.php';

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../signin.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

$sql = "SELECT * FROM admin WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $admin_name = $admin['fname'] . " " . $admin['lname'];
    $profile_pic = $admin['profile_pic'] ?? 'images/admin-default.png';
} else {
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

if (isset($_POST['approve_application']) || isset($_POST['reject_application'])) {
    $applicationId = $_POST['application_id'];
    $newStatus = isset($_POST['approve_application']) ? 'approved' : 'rejected';

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
        $updateSql = "UPDATE applications SET status = ? WHERE application_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $newStatus, $applicationId);
        $stmt->execute();

        $userId = $infoResult['user_id'];
        $to = $infoResult['Email'];
        $name = $infoResult['Fname'] . ' ' . $infoResult['Lname'];
        $scholarshipTitle = $infoResult['title'];

        if ($newStatus == 'approved') {
            $deletePendingSql = "DELETE FROM applications WHERE user_id = ? AND status = 'pending'";
            $deleteStmt = $conn->prepare($deletePendingSql);
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();

            $subject = "Your Scholarship Application has been Approved";
            $body = "Hello {$name},\n\nCongratulations! Your application for the '{$scholarshipTitle}' scholarship has been approved.\n\nAs a result, any other pending scholarship applications you had have been withdrawn. Please log in to your account for more details.\n\nThank you,\nPESO San Julian MIS";
        } else {
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
        }
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

    // --- START: FETCH SPES FORM IMAGES ---
$spes_forms_data = [
    'employment_contract' => ['file_path' => '../../../../images/default-placeholder.png', 'doc_file_path' => null],
    'oath_undertaking' => ['file_path' => '../../../../images/default-placeholder.png', 'doc_file_path' => null]
];
$spesFilesSql = "SELECT doc_type, file_path, doc_file_path FROM spes_files WHERE doc_type IN ('employment_contract', 'oath_undertaking')";
$spesFilesResult = $conn->query($spesFilesSql);
if ($spesFilesResult) {
    while($row = $spesFilesResult->fetch_assoc()) {
        $spes_forms_data[$row['doc_type']] = [
            'file_path' => $row['file_path'] ?? $spes_forms_data[$row['doc_type']]['file_path'],
            'doc_file_path' => $row['doc_file_path']
        ];
    }
}
// --- END: FETCH SPES FORM IMAGES ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_POST['upload_spes_doc_file'])) {
    $doc_type = $_POST['doc_type'];
    $allowed_types = ['employment_contract', 'oath_undertaking'];
    $allowed_extensions = ['pdf', 'doc', 'docx'];

    if (in_array($doc_type, $allowed_types) && isset($_FILES['spes_doc_source_file']) && $_FILES['spes_doc_source_file']['error'] == 0) {
        $target_dir = "../../../../uploads/spes_source_files/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_name = basename($_FILES['spes_doc_source_file']['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_extension, $allowed_extensions)) {
            // Delete any old source file for this doc_type
            $old_files = glob($target_dir . $doc_type . '.*');
            foreach($old_files as $old_file){
                if(is_file($old_file)){
                    unlink($old_file);
                }
            }

            // Create the new filename
            $target_file = $target_dir . $doc_type . '.' . $file_extension;

            if (move_uploaded_file($_FILES['spes_doc_source_file']['tmp_name'], $target_file)) {
                // Update the database with the new path
                $sql = "INSERT INTO spes_files (doc_type, doc_file_path) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE doc_file_path = VALUES(doc_file_path)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $doc_type, $target_file);
                if ($stmt->execute()) {
                    $_SESSION['spes_form_upload_success'] = ucfirst(str_replace('_', ' ', $doc_type)) . " source file has been updated.";
                    
                    // Update the in-memory array to reflect the change
                    $spes_forms_data[$doc_type]['doc_file_path'] = $target_file;
                } else {
                    $_SESSION['spes_form_upload_error'] = "Database error while updating the source file.";
                }
            } else {
                $_SESSION['spes_form_upload_error'] = "Sorry, there was an error uploading your file.";
            }
        } else {
            $_SESSION['spes_form_upload_error'] = "Invalid file type. Only PDF, DOC, and DOCX are allowed.";
        }
    } else {
        $_SESSION['spes_form_upload_error'] = "Invalid request or no file uploaded.";
    }
    header("Location: admin_dashboard.php#scholarship-page");
    exit();
}
    
if (isset($_POST['add_spes_batch'])) {
    $batch_name = $_POST['batch_name'];
    // MODIFICATION START: Get both date and time from the form
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $start_datetime = $start_date . ' ' . $start_time; // Combine them into a single DATETIME string
    // MODIFICATION END

    $checkActiveSql = "SELECT batch_id FROM spes_batches WHERE status = 'active'";
    $activeResult = $conn->query($checkActiveSql);
    if ($activeResult->num_rows > 0) {
        $_SESSION['spes_batch_error'] = "An active SPES batch already exists. Please end it before starting a new one.";
    } else {
        // MODIFICATION: Use the new combined $start_datetime variable
        $insertBatchSql = "INSERT INTO spes_batches (batch_name, start_date, status) VALUES (?, ?, 'active')";
        $batchStmt = $conn->prepare($insertBatchSql);
        $batchStmt->bind_param("ss", $batch_name, $start_datetime); // Bind the new variable
        if ($batchStmt->execute()) {
            $_SESSION['spes_batch_success'] = "SPES Batch '{$batch_name}' has been added and is now active.";
        }
    }
    header("Location: admin_dashboard.php#scholarship-page");
    exit();
}

    if (isset($_POST['end_spes_batch'])) {
        $batch_id = $_POST['batch_id'];
        // Accept both date and time for end_date
        $end_date = $_POST['end_date'];
        $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
        // If time is provided, append it to the end_date, else default to 23:59:59
        if ($end_time) {
            $end_datetime = $end_date . ' ' . $end_time . ':00';
        } else {
            $end_datetime = $end_date . ' 23:59:59';
        }

        $endBatchSql = "UPDATE spes_batches SET status = 'ended', end_date = ? WHERE batch_id = ?";
        $endStmt = $conn->prepare($endBatchSql);
        $endStmt->bind_param("si", $end_datetime, $batch_id);
        if ($endStmt->execute()) {
            $_SESSION['spes_batch_success'] = "SPES Batch has been successfully ended.";
        }
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    }

    if (isset($_POST['upload_spes_doc'])) {
            $doc_type = $_POST['doc_type'];
            $allowed_types = ['employment_contract', 'oath_undertaking'];

            if (in_array($doc_type, $allowed_types) && isset($_FILES['spes_doc_image']) && $_FILES['spes_doc_image']['error'] == 0) {
                $target_dir = "../../../../uploads/spes_forms/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $file_name = basename($_FILES['spes_doc_image']['name']);
                $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                // Create a more predictable filename based on type
                $target_file = $target_dir . $doc_type . '.' . $imageFileType;
                $check = getimagesize($_FILES['spes_doc_image']['tmp_name']);

                if ($check !== false) {
                    // Before uploading, delete the old file if it exists with a different extension
                    $old_files = glob($target_dir . $doc_type . '.*');
                    foreach($old_files as $old_file){
                        if(is_file($old_file)){
                            unlink($old_file);
                        }
                    }

                    if (move_uploaded_file($_FILES['spes_doc_image']['tmp_name'], $target_file)) {
                        // Use REPLACE INTO to either insert a new row or update an existing one
                        $sql = "REPLACE INTO spes_files (doc_type, file_path) VALUES (?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ss", $doc_type, $target_file);
                        if ($stmt->execute()) {
                            $_SESSION['spes_form_upload_success'] = ucfirst(str_replace('_', ' ', $doc_type)) . " image has been updated.";
                        } else {
                            $_SESSION['spes_form_upload_error'] = "Database error while updating the form image.";
                        }
                    } else {
                        $_SESSION['spes_form_upload_error'] = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $_SESSION['spes_form_upload_error'] = "File is not a valid image.";
                }
            } else {
                $_SESSION['spes_form_upload_error'] = "Invalid request or no file uploaded.";
            }

            // Redirect back to the SPES management tab
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    }

    if (isset($_POST['add_scholarship'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $requirements = $_POST['requirements'];
        $benefits = $_POST['benefits'];
        $eligibility = $_POST['eligibility'];
        $slots = $_POST['number_of_slots'];
        
        $insertSql = "INSERT INTO scholarships (title, description, requirements, benefits, eligibility, number_of_slots, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sssssi", $title, $description, $requirements, $benefits, $eligibility, $slots); 
        if ($insertStmt->execute()) {
            $_SESSION['scholarship_added'] = true;
        }
        
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['delete_scholarship'])) {
        $id = $_POST['id'];
        $endScholarshipSql = "UPDATE scholarships SET status = 'ended', ended_at = NOW() WHERE scholarship_id = ?";
        $endScholarshipStmt = $conn->prepare($endScholarshipSql);
        $endScholarshipStmt->bind_param("i", $id);
        $endScholarshipStmt->execute();
        $_SESSION['scholarship_ended'] = true;
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
} elseif (isset($_POST['approve_spes_application']) || isset($_POST['reject_spes_application'])) {
        $spesApplicationId = $_POST['spes_application_id'];
        $newStatus = isset($_POST['approve_spes_application']) ? 'approved' : 'rejected';

        // --- START: MODIFICATION - ADD EMAIL NOTIFICATION ---
        // 1. Get user information for the email
        $infoSql = "SELECT u.Email, u.Fname, u.Lname 
                    FROM spes_applications sa
                    JOIN user u ON sa.user_id = u.user_id
                    WHERE sa.spes_application_id = ?";
        $infoStmt = $conn->prepare($infoSql);
        $infoStmt->bind_param("i", $spesApplicationId);
        $infoStmt->execute();
        $infoResult = $infoStmt->get_result()->fetch_assoc();

        if ($infoResult) {
            // 2. Update the application status in the database
            $updateSql = "UPDATE spes_applications SET status = ? WHERE spes_application_id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("si", $newStatus, $spesApplicationId);
            $stmt->execute();

            // 3. Prepare email content
            $to = $infoResult['Email'];
            $name = $infoResult['Fname'] . ' ' . $infoResult['Lname'];

            if ($newStatus == 'approved') {
                $subject = "Your SPES Application has been Approved";
                $body = "Hello {$name},\n\nCongratulations! Your application for the SPES Program has been approved.\n\nPlease log in to your account for more details.\n\nThank you,\nPESO San Julian MIS";
            } else { // 'rejected'
                $subject = "Update on your SPES Application";
                $body = "Hello {$name},\n\nWe regret to inform you that your application for the SPES Program has been rejected at this time.\n\nThank you for your interest.\n\nSincerely,\nPESO San Julian MIS";
            }
            
            // 4. Send the email using PHPMailer
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
                // Email sending failed, but the application status is still updated.
                // You could add logging here if needed.
            }
        }
        // --- END: MODIFICATION ---
        
        $_SESSION['spes_status_updated'] = true;
        header("Location: admin_dashboard.php#total-applicants-spes");
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

$scholarshipsSql = "
    SELECT DISTINCT s.scholarship_id, s.title 
    FROM scholarships s
    JOIN applications a ON s.scholarship_id = a.scholarship_id
    WHERE a.status = 'approved' AND s.status = 'active' -- This ensures only active scholarships are shown
";
$scholarshipResult = $conn->query($scholarshipsSql);
$scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);

$totalListedScholarshipsSql = "SELECT COUNT(scholarship_id) as total FROM scholarships WHERE status != 'ended'";
$totalListedScholarshipsResult = $conn->query($totalListedScholarshipsSql);
$totalListedScholarshipsCount = $totalListedScholarshipsResult->fetch_assoc()['total'] ?? 0;

$totalSpesApplicantsSql = "SELECT COUNT(spes_application_id) as total FROM spes_applications";
$totalSpesApplicantsResult = $conn->query($totalSpesApplicantsSql);
$totalSpesApplicantsCount = $totalSpesApplicantsResult->fetch_assoc()['total'] ?? 0;

$approvedSpesSql = "SELECT COUNT(spes_application_id) as total FROM spes_applications WHERE status = 'approved'";
$approvedSpesResult = $conn->query($approvedSpesSql);
$approvedSpesCount = $approvedSpesResult->fetch_assoc()['total'] ?? 0;

$messagesSql = "SELECT * FROM notifications ORDER BY created_at DESC";
$messagesResult = $conn->query($messagesSql);
$messages = $messagesResult->fetch_all(MYSQLI_ASSOC);

$usersWithConcerns = [];
$userQuery = $conn->query("SELECT DISTINCT u.user_id, u.Fname, u.Lname, u.profile_pic FROM user u JOIN concerns c ON u.user_id = c.user_id WHERE c.scholarship_id IS NULL");
while ($row = $userQuery->fetch_assoc()) {
    $usersWithConcerns[] = $row;
}

$selectedGroupId = isset($_GET['chat_group']) ? intval($_GET['chat_group']) : null;
$selectedUserId = isset($_GET['chat_user']) ? intval($_GET['chat_user']) : null;
$chatMessages = [];
$chatTitle = 'Select a conversation';

if ($selectedGroupId) {
    $stmt = $conn->prepare("SELECT c.*, u.Fname, u.Lname FROM concerns c LEFT JOIN user u ON c.user_id = u.user_id WHERE c.scholarship_id = ? ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $selectedGroupId);
    $stmt->execute();
    $chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $schTitleStmt = $conn->prepare("SELECT title FROM scholarships WHERE scholarship_id = ?");
    $schTitleStmt->bind_param("i", $selectedGroupId);
    $schTitleStmt->execute();
    $schResult = $schTitleStmt->get_result();
    if($schRow = $schResult->fetch_assoc()){
        $chatTitle = htmlspecialchars($schRow['title']) . " (Group)";
    }

} elseif ($selectedUserId) {
    $stmt = $conn->prepare("SELECT * FROM concerns WHERE user_id = ? AND scholarship_id IS NULL ORDER BY created_at ASC");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $chatMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

    if (!empty($reply) || $attachmentPath) {
        if (isset($_POST['chat_group_id']) && !empty($_POST['chat_group_id'])) {
            $chat_group_id = intval($_POST['chat_group_id']);
            
            $stmt = $conn->prepare("INSERT INTO concerns (admin_id, scholarship_id, sender, message, user_id, attachment_path) VALUES (?, ?, 'admin', ?, NULL, ?)");
            $stmt->bind_param("iiss", $admin_id, $chat_group_id, $reply, $attachmentPath);
            $stmt->execute();
            header("Location: admin_dashboard.php?chat_group=$chat_group_id#user-concerns-page");
            exit();

        } elseif (isset($_POST['chat_user_id']) && !empty($_POST['chat_user_id'])) {
            $chat_user_id = intval($_POST['chat_user_id']);
            
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

    // CORRECTED: Normalize the new attachment's path to use forward slashes
    $normalizedAttachmentPath = str_replace('\\', '/', $attachmentPath);

    if ($type === 'scholarship') {
        // 2. Find the user's latest APPROVED scholarship application
        $appStmt = $conn->prepare("SELECT application_id, documents FROM applications WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
        $appStmt->bind_param("i", $userId);
        $appStmt->execute();
        $appResult = $appStmt->get_result();

        if ($appRow = $appResult->fetch_assoc()) {
            $applicationId = $appRow['application_id'];
            $currentDocsRaw = json_decode($appRow['documents'], true) ?: [];
            
            // CORRECTED: Normalize all existing paths to prevent duplicates and formatting issues
            $currentDocs = array_map(function($path) {
                return str_replace('\\', '/', $path);
            }, $currentDocsRaw);

            if (!in_array($normalizedAttachmentPath, $currentDocs)) {
                $currentDocs[] = $normalizedAttachmentPath;
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
            
            // CORRECTED: This block now robustly handles both single paths and existing JSON arrays
            $currentDocsRaw = json_decode($currentDocsPath, true);
            if (!is_array($currentDocsRaw)) {
                $currentDocsRaw = !empty($currentDocsPath) ? [$currentDocsPath] : [];
            }
            
            $currentDocs = array_map(function($path) {
                return str_replace('\\', '/', $path);
            }, $currentDocsRaw);

            if (!in_array($normalizedAttachmentPath, $currentDocs)) {
                $currentDocs[] = $normalizedAttachmentPath;
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

    $userResult = $conn->query("SELECT Email, Fname, Lname FROM user WHERE user_id=$uid");
    $user = $userResult->fetch_assoc();

    if ($user) {
        $conn->query("UPDATE user SET status='rejected' WHERE user_id=$uid");

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
        }
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

if (isset($_POST['reject_application_with_message'])) {
    $applicationId = $_POST['application_id'];
    $rejectionMessage = $_POST['rejection_message'];

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
        $updateSql = "UPDATE applications SET status = 'rejected', rejection_message = ? WHERE application_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $rejectionMessage, $applicationId);
        $stmt->execute();

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
        }
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

$viewApprovedScholarshipId = isset($_GET['view_approved']) ? intval($_GET['view_approved']) : null;
$approved_applicants = [];
$approved_scholarship_title = '';

if ($viewApprovedScholarshipId) {
    $schTitleSql = "SELECT title FROM scholarships WHERE scholarship_id = ?";
    $schStmt = $conn->prepare($schTitleSql);
    $schStmt->bind_param("i", $viewApprovedScholarshipId);
    $schStmt->execute();
    $schResult = $schStmt->get_result();
    if ($schRow = $schResult->fetch_assoc()) {
        $approved_scholarship_title = $schRow['title'];
    }

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

    $apprStmt->execute();
    $result = $apprStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $approved_applicants[] = $row;
    }
}

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

$scholarshipDocsSearch = isset($_GET['search_scholarship_docs']) ? trim($_GET['search_scholarship_docs']) : '';
// --- START: MODIFICATION - Only show reports for ACTIVE scholarships ---
$scholarshipDocsSql = "
    SELECT u.user_id, u.Fname, u.Lname, u.valid_id, a.documents 
    FROM user u 
    JOIN applications a ON u.user_id = a.user_id 
    JOIN scholarships s ON a.scholarship_id = s.scholarship_id 
    WHERE a.status = 'approved' AND s.status = 'active'
";
// --- END: MODIFICATION ---
if (!empty($scholarshipDocsSearch)) {
    $scholarshipDocsSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
}
$scholarshipDocsSql .= " ORDER BY a.created_at ASC";

$scholarship_docs_users = [];
if (!empty($scholarshipDocsSearch)) {
    $stmt = $conn->prepare($scholarshipDocsSql);
    $search = "%$scholarshipDocsSearch%";
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $scholarship_docs_users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $scholarshipDocsResult = $conn->query($scholarshipDocsSql);
    $scholarship_docs_users = $scholarshipDocsResult->fetch_all(MYSQLI_ASSOC);
}

// --- START: MODIFICATION - Only show reports for ACTIVE SPES batch ---
// 1. Find the start date of the currently active SPES batch
$activeBatchStartDate = null;
$activeBatchSql = "SELECT start_date FROM spes_batches WHERE status = 'active' LIMIT 1";
$activeBatchResult = $conn->query($activeBatchSql);
if ($activeBatchResult && $activeBatchRow = $activeBatchResult->fetch_assoc()) {
    $activeBatchStartDate = $activeBatchRow['start_date'];
}

// 2. Build the query based on the active batch
$spesDocsSearch = isset($_GET['search_spes_docs']) ? trim($_GET['search_spes_docs']) : '';
$spesDocsSql = "
    SELECT u.user_id, u.Fname, u.Lname, sa.id_image_paths, sa.spes_documents_path 
    FROM user u JOIN spes_applications sa ON u.user_id = sa.user_id 
    WHERE sa.status = 'approved'
";

// Only include applicants from the currently active batch
if ($activeBatchStartDate) {
    // Note the use of quotes around the date string for proper SQL syntax
    $spesDocsSql .= " AND sa.created_at >= '" . $conn->real_escape_string($activeBatchStartDate) . "'";
} else {
    // If no batch is active, the report should be empty. This condition ensures that.
    $spesDocsSql .= " AND 1=0"; 
}
// --- END: MODIFICATION ---

if (!empty($spesDocsSearch)) {
    $spesDocsSql .= " AND CONCAT(u.Fname, ' ', u.Lname) LIKE ?";
}
$spesDocsSql .= " ORDER BY sa.created_at ASC";

$spes_docs_users = [];
if (!empty($spesDocsSearch)) {
    $stmt = $conn->prepare($spesDocsSql);
    $search = "%$spesDocsSearch%";
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $spes_docs_users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $spesDocsResult = $conn->query($spesDocsSql);
    $spes_docs_users = $spesDocsResult->fetch_all(MYSQLI_ASSOC);
}

$activeScholarshipsSql = "SELECT * FROM scholarships WHERE status = 'active' ORDER BY created_at DESC";
$activeScholarshipsResult = $conn->query($activeScholarshipsSql);
$activeScholarships = $activeScholarshipsResult->fetch_all(MYSQLI_ASSOC);

$endedScholarshipsSql = "SELECT * FROM scholarships WHERE status = 'ended' ORDER BY ended_at DESC";
$endedScholarshipsResult = $conn->query($endedScholarshipsSql);
$endedScholarships = $endedScholarshipsResult->fetch_all(MYSQLI_ASSOC);

// MODIFICATION: Sort by active status first, then by the most recent start date
$spesHistorySql = "SELECT * FROM spes_batches ORDER BY status = 'active' DESC, start_date DESC";
$spesHistoryResult = $conn->query($spesHistorySql);
$spesHistory = $spesHistoryResult->fetch_all(MYSQLI_ASSOC);

$viewRecentMembersId = isset($_GET['view_recent_members']) ? intval($_GET['view_recent_members']) : null;
$recentMembers = [];
$recentScholarshipTitle = '';
if($viewRecentMembersId) {
    $schTitleSql = "SELECT title FROM scholarships WHERE scholarship_id = ?";
    $stmt = $conn->prepare($schTitleSql);
    $stmt->bind_param("i", $viewRecentMembersId);
    $stmt->execute();
    $recentScholarshipTitle = $stmt->get_result()->fetch_assoc()['title'];

    $membersSql = "SELECT u.Fname, u.Lname, a.created_at as application_date FROM applications a JOIN user u ON a.user_id = u.user_id WHERE a.scholarship_id = ? AND a.status = 'approved' ORDER BY u.Lname, u.Fname";
    $stmt = $conn->prepare($membersSql);
    $stmt->bind_param("i", $viewRecentMembersId);
    $stmt->execute();
    $recentMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$viewEndedMembersId = isset($_GET['view_ended_members']) ? intval($_GET['view_ended_members']) : null;
$endedMembers = [];
$endedScholarshipTitle = '';
if($viewEndedMembersId) {
    $schTitleSql = "SELECT title FROM scholarships WHERE scholarship_id = ?";
    $stmt = $conn->prepare($schTitleSql);
    $stmt->bind_param("i", $viewEndedMembersId);
    $stmt->execute();
    $endedScholarshipTitle = $stmt->get_result()->fetch_assoc()['title'];

    $membersSql = "SELECT u.Fname, u.Lname, a.created_at as application_date FROM applications a JOIN user u ON a.user_id = u.user_id WHERE a.scholarship_id = ? ORDER BY u.Lname, u.Fname";
    $stmt = $conn->prepare($membersSql);
    $stmt->bind_param("i", $viewEndedMembersId);
    $stmt->execute();
    $endedMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- START: MODIFIED SPES MEMBER FETCHING LOGIC ---
$viewSpesMembersId = isset($_GET['view_spes_members']) ? intval($_GET['view_spes_members']) : null;
$spesMembers = [];
$spesBatchName = '';
if ($viewSpesMembersId) {
    // 1. Fetch the selected batch's information (name, start date, end date)
    $batchInfoSql = "SELECT batch_name, start_date, end_date FROM spes_batches WHERE batch_id = ?";
    $stmt = $conn->prepare($batchInfoSql);
    $stmt->bind_param("i", $viewSpesMembersId);
    $stmt->execute();
    $batchInfo = $stmt->get_result()->fetch_assoc();

    if ($batchInfo) {
        $spesBatchName = $batchInfo['batch_name'];
        $startDate = $batchInfo['start_date'];
        $endDate = $batchInfo['end_date']; // This will be NULL if the batch is still active

        // 2. Build the query to find members whose application date falls within the batch's active period
        $membersSql = "SELECT u.Fname, u.Lname, sa.created_at as application_date
                       FROM spes_applications sa
                       JOIN user u ON sa.user_id = u.user_id
                       WHERE sa.status = 'approved' AND sa.created_at >= ?";
        
        $params = [$startDate];
        $types = "s";

        // 3. If the batch has an end date, it means it's an "ended" batch. We add the end date to the
        //    query to create a specific time window for that batch.
        if ($endDate) {
            $membersSql .= " AND sa.created_at <= ?";
            // We append the time to include the entire day of the end date
            $params[] = $endDate . ' 23:59:59';
            $types .= "s";
        }
        
        // For active batches, the end date is NULL, so the query correctly fetches all members
        // from the start date up to the present moment.

        $membersSql .= " ORDER BY u.Lname, u.Fname";

        // 4. Execute the dynamically built query to get the correct members for the selected batch
        $stmt = $conn->prepare($membersSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $spesMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
// --- END: MODIFIED SPES MEMBER FETCHING LOGIC ---

$spesBatchesSql = "SELECT * FROM spes_batches ORDER BY start_date DESC";
$spesBatchesResult = $conn->query($spesBatchesSql);
$spesBatches = $spesBatchesResult->fetch_all(MYSQLI_ASSOC);
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
    font-size: 14px;
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
    font-size: 20px;
}

.scholarship-details {
    margin-bottom: 15px;
}

.scholarship-details p {
    margin: 5px 0;
    font-size: 14px;
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
    font-size: 25px;
}

.message-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 15px;
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
    font-size: 18px;
}

.scholarship-form input[name="number_of_slots"]::placeholder {
    font-size: 14px;
}

.send-updates-form textarea::placeholder {
    font-size: 14px;
}

.scholarship-form textarea {
    height: 40px;
    resize: none;
    border-radius: 10px;
    font-size : 14px;
}
.scholarship-form input[type="text"], .scholarship-form input[type="number"], .scholarship-form input[type="date"] {
    height: 15px;
    border-radius: 10px;
    font-size: 14px;
}

.scholarship-form button[name="add_scholarship"] {
    font-size: 12px !important;
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
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
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
    font-size: 15px;
    color: #555;
    margin-top: 5px;
}
.scholarship-list-footer {
    margin-top: 10px;
}
.scholarship-list-footer p {
    font-size: 13px;
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

.tabs {
    overflow: hidden;
    border-bottom: 1px solid #ccc;
    margin-bottom: 20px;
}
.tab-link {
    background-color: #f1f1f1;
    float: left;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 14px 16px;
    transition: 0.3s;
    font-size: 14px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    margin-right: 2px;
}
.tab-link:hover {
    background-color: #ddd;
}
.tab-link.active {
    background-color: #fff;
    border: 1px solid #ccc;
    border-bottom: 1px solid #fff;
    font-weight: bold;
    color: #090549;
}
.tab-content {
    display: none;
    padding: 6px 12px;
    border-top: none;
    animation: fadeIn 0.5s;
}

.user-details-modal-container {
    display: flex;
    flex-direction: column;
}
.user-details-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.user-details-pic {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #090549;
    margin-right: 20px;
}
.user-details-name {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}
.user-details-section-header {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}
.user-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.user-details-item {
    font-size: 14px;
}
.user-details-label {
    font-weight: bold;
    color: #555;
    display: block;
    margin-bottom: 3px;
}
.user-details-value {
    color: #666;
    font-size: 12px;
}

.spes-form-upload-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}
.spes-form-section {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}
.spes-form-section h4 {
    margin-top: 0;
    font-size: 14px;
    color: #333;
}
.spes-form-preview {
    max-width: 100%;
    height: 200px;
    object-fit: contain;
    border-radius: 5px;
    border: 1px solid #eee;
    background-color: #f9f9f9;
    margin-bottom: 10px;
}
.input-field.file-field {
    padding: 8px;
    font-size: 10px;
    width: 100%;
    box-sizing: border-box;
}

.form-divider {
    margin: 20px 0;
    border: 0;
    border-top: 1px solid #eee;
}
.form-section-label {
    font-size: 12px;
    font-weight: bold;
    color: #555;
    margin-bottom: 10px;
}
.current-file {
    font-size: 11px;
    padding: 8px;
    background-color: #f0f0f0;
    border-radius: 5px;
    margin-bottom: 10px;
    word-wrap: break-word;
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
                <div class="nav-text">Manage Scholarship/SPES</div>
            </div>
            <div class="nav-item" id="scholarship-history-nav" onclick="showPage('scholarship-history-page')">
                <div class="nav-icon"><i class="fas fa-history"></i></div>
                <div class="nav-text">Scholarship/SPES History</div>
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
            showToast('Scholarship added successfully', 'success');
        });
        </script>
        <?php unset($_SESSION['scholarship_added']); endif; ?>

        <?php if (isset($_SESSION['scholarship_ended'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Scholarship has been ended and moved to history.', 'info');
        });
        </script>
        <?php unset($_SESSION['scholarship_ended']); endif; ?>

        <?php if (isset($_SESSION['scholarship_published'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Scholarship published successfully', 'success');
        });
        </script>
        <?php unset($_SESSION['scholarship_published']); endif; ?>

        <?php if (isset($_SESSION['slots_updated'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Scholarship slots updated successfully', 'success');
        });
        </script>
        <?php unset($_SESSION['slots_updated']); endif; ?>

        <?php if (isset($_SESSION['message_deleted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Message deleted successfully', 'error');
        });
        </script>
        <?php unset($_SESSION['message_deleted']); endif; ?>

        <?php if (isset($_SESSION['document_saved_success'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['document_saved_success']; ?>', 'success');
        });
        </script>
        <?php unset($_SESSION['document_saved_success']); endif; ?>

        <?php if (isset($_SESSION['document_saved_error'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['document_saved_error']; ?>', 'error');
        });
        </script>
        <?php unset($_SESSION['document_saved_error']); endif; ?>

        <?php if (isset($_SESSION['spes_batch_success'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['spes_batch_success']; ?>', 'success');
        });
        </script>
        <?php unset($_SESSION['spes_batch_success']); endif; ?>
        
        <?php if (isset($_SESSION['spes_batch_error'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['spes_batch_error']; ?>', 'error');
        });
        </script>
        <?php unset($_SESSION['spes_batch_error']); endif; ?>

        <?php if (isset($_SESSION['spes_form_upload_success'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['spes_form_upload_success']; ?>', 'success');
        });
        </script>
        <?php unset($_SESSION['spes_form_upload_success']); endif; ?>

        <?php if (isset($_SESSION['spes_form_upload_error'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $_SESSION['spes_form_upload_error']; ?>', 'error');
        });
        </script>
        <?php unset($_SESSION['spes_form_upload_error']); endif; ?>


        <div id="toast-message">
            <span id="toast-text"></span>
            <i class="fas fa-check-circle" id="toast-icon"></i>
        </div>


        <?php
            // FIX: Modified the scholarship part of the query to only count applicants
            // from 'active' scholarships for a more accurate total count on the home page.
            $totalApplicantsSql = "
                SELECT SUM(total) as grand_total FROM (
                    (SELECT COUNT(a.application_id) as total 
                     FROM applications a
                     JOIN scholarships s ON a.scholarship_id = s.scholarship_id
                     WHERE s.status = 'active')
                    UNION ALL
                    (SELECT COUNT(spes_application_id) as total FROM spes_applications)
                ) as combined_counts
            ";
            $totalApplicantsResult = $conn->query($totalApplicantsSql);
            $totalApplicantsCount = $totalApplicantsResult->fetch_assoc()['grand_total'] ?? 0;

            $rejectedApplicantsSql = "
                SELECT SUM(total) as grand_total FROM (
                    (SELECT COUNT(application_id) as total FROM applications WHERE status = 'rejected')
                    UNION ALL
                    (SELECT COUNT(spes_application_id) as total FROM spes_applications WHERE status = 'rejected')
                ) as combined_counts
            ";
            $rejectedApplicantsResult = $conn->query($rejectedApplicantsSql);
            $rejectedApplicantsCount = $rejectedApplicantsResult->fetch_assoc()['grand_total'] ?? 0;

            $approvedApplicantsSql = "
                SELECT COUNT(a.application_id) as total 
                FROM applications a
                JOIN scholarships s ON a.scholarship_id = s.scholarship_id
                WHERE a.status = 'approved' AND s.status = 'active' -- This now only counts awardees of active scholarships
            ";
            $approvedApplicantsResult = $conn->query($approvedApplicantsSql);
            $approvedApplicantsCount = $approvedApplicantsResult->fetch_assoc()['total'] ?? 0;
        ?>

        <?php if (isset($_SESSION['message_sent'])): ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Message sent successfully', 'success');
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
        // FIX: This query now joins with the scholarships table to count only applicants
        // from scholarships that are currently 'active'.
        $totalAllApplicantsSql = "
            SELECT COUNT(a.application_id) as total_all_applicants 
            FROM applications a
            JOIN scholarships s ON a.scholarship_id = s.scholarship_id
            WHERE s.status = 'active'
        ";
        $totalAllApplicantsResult = $conn->query($totalAllApplicantsSql);
        $totalAllApplicants = $totalAllApplicantsResult->fetch_assoc()['total_all_applicants'] ?? 0;
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
            $viewScholarshipId = isset($_GET['view_scholarship']) ? intval($_GET['view_scholarship']) : null;
                $applicants = [];
                if ($viewScholarshipId) {
                    $searchQuery = isset($_GET['search']) && !empty($_GET['search']) ? $_GET['search'] : '%';
                    
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
                $totalApplicantsSql = "SELECT s.scholarship_id, s.title, s.description, s.status, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id WHERE s.status != 'ended' GROUP BY s.scholarship_id";
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
                    $searchSpesTerm = isset($_GET['search_spes_name']) ? trim($_GET['search_spes_name']) : '';
                    $searchSpesSql = '%' . $searchSpesTerm . '%';

                    // FIX: Changed "u.Fname, u.Lname" to "u.*" to fetch all user details
                    // so the 'View User Info' modal can be fully populated.
                    $approvedSpesListSql = "
                        SELECT sa.*, u.* FROM spes_applications sa
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
                                        <button class="btn-outline" onclick='showSpesAppModal(<?php echo json_encode($app); ?>)'>View App Details</button>
                                        <button class="btn-outline" onclick='showUserDetailsModal(<?php echo json_encode($app); ?>)'>View User Info</button>
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
                        // --- START: FIX - Align status with visible table columns ---
                        // Determine if documents are complete
                        $docs_array = json_decode($user['documents'], true);
                        $has_docs = is_array($docs_array) && !empty($docs_array);
                        
                        // The status should be 'Complete' if the requirement documents are present,
                        // as the application form is implicitly present for all listed users.
                        $status = $has_docs ? 'Complete' : 'INC';
                        // --- END: FIX ---
                    ?>
                    <tr>
                        <td>
                            <button class="btn-outline" <?php if(!$has_docs && !$has_id) echo 'disabled'; ?> 
                                onclick='viewUserDocuments(<?php echo json_encode(["ids" => $user["valid_id"], "docs" => $user["documents"]]); ?>, "<?php echo htmlspecialchars($user['Fname'] . ' ' . $user['Lname']); ?>")'>
                                View Documents
                            </button>
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
                                    // --- START: FIX - Align status with visible table columns ---
                                    // The presence of an application form is implied. We only need to check for requirement documents.
                                    $has_reqs = !empty($user['spes_documents_path']);

                                    // The status is now based solely on whether requirement documents were uploaded.
                                    $status = $has_reqs ? 'Complete' : 'INC';
                                    // --- END: FIX ---
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
                        // Pre-fetch approval statuses for all users in the current chat view
                        $userIdsInChat = array_unique(array_column($chatMessages, 'user_id'));
                        $approvedScholarUsers = [];
                        $approvedSpesUsers = [];

                        if (!empty($userIdsInChat)) {
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
                    ?>

                    <?php foreach ($chatMessages as $msg): ?>
                        <div class="concern-message <?php echo $msg['sender'] === 'admin' ? 'admin' : 'user'; ?>">
                            
                            <?php 
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
                                }
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
                            }
                            ?>
                            
                            <div class="concern-message-content" style="<?php echo $msg['sender'] === 'admin' ? 'margin-right:15px;' : ''; ?>">
                                <?php if($msg['sender'] === 'user' && $selectedGroupId) echo "<strong>" . htmlspecialchars($msg['Fname'] . ' ' . $msg['Lname']) . ":</strong><br>"; ?>
                                <?php if (!empty($msg['message'])) echo nl2br(htmlspecialchars($msg['message'])); ?>
                                
                                <?php if (!empty($msg['attachment_path'])): ?>
                                    <div class="message-attachment">
                                        <a href="<?php echo htmlspecialchars(str_replace('../../../../', BASE_URL, $msg['attachment_path'])); ?>" target="_blank" download>
                                            <i class="fas fa-file-download"></i>
                                            <span><?php echo htmlspecialchars(preg_replace('/^[a-f0_.-9]+_/', '', basename($msg['attachment_path']))); ?></span>
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

                <div class="tabs">
                    <button class="tab-link active" onclick="openScholarshipMgmtTab(event, 'ScholarshipMgmt')">Scholarship Management</button>
                    <button class="tab-link" onclick="openScholarshipMgmtTab(event, 'SpesMgmt')">SPES Management</button>
                </div>

                <div id="ScholarshipMgmt" class="tab-content" style="display: block;">
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
                    $scholarshipsSql_list = "SELECT s.*, COUNT(a.application_id) as total_applicants FROM scholarships s LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id WHERE s.status != 'ended' GROUP BY s.scholarship_id";
                    $scholarshipResult_list = $conn->query($scholarshipsSql_list);
                    $scholarships_list = $scholarshipResult_list->fetch_all(MYSQLI_ASSOC);
                    
                    foreach ($scholarships_list as $scholarship): 
                        $remainingSlots = $scholarship['number_of_slots'] - $scholarship['total_applicants'];
                    ?>
                        <div class="scholarship-card">
                            <h3><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                            <div class="scholarship-details">
                                <p><strong>Description:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['description'])); ?></p>
                                
                                <p><strong>Requirements:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['requirements'])); ?></p>

                                <p><strong>Benefits:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['benefits'])); ?></p>

                                <p><strong>Eligibility:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['eligibility'])); ?></p>
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
                                    <button type="submit" name="delete_scholarship" class="btn-delete-scholarship" onclick="return confirm('Are you sure you want to end this scholarship? It will be moved to history.');">End Scholarship</button>
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
                    <p>No active or pending scholarships found. Add a new scholarship using the form above.</p>
                    <?php endif; ?>
                </div>

                <div id="SpesMgmt" class="tab-content">
                    <form class="scholarship-form" method="POST">
                        <h3>Add New SPES Batch</h3>
                        <input type="text" name="batch_name" placeholder="Batch Name (e.g., SPES Batch 1.0)" required>
                        <label for="start_date" style="font-size: 14px; margin-top: 10px; display:block;">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required>
                        
                        <label for="start_time" style="font-size: 14px; margin-top: 10px; display:block;">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                        <button type="submit" name="add_spes_batch">Add and Start Batch</button>
                    </form>
                    <div class="scholarship-card">
                        <h3>SPES Form Management</h3>
                        <p style="font-size:12px; color: #555; margin-bottom: 20px;">Upload the preview images and the downloadable source files (.pdf, .docx, .doc) for the SPES forms.</p>
                        
                        <div class="spes-form-upload-container">
                            <div class="spes-form-section">
                                <h4>Employment Contract Form</h4>
                                <p class="form-section-label">Preview Image</p>
                                <img src="<?php echo htmlspecialchars(str_replace('../../../../', BASE_URL, $spes_forms_data['employment_contract']['file_path'])); ?>" alt="Contract Preview" class="spes-form-preview" onerror="this.src='<?php echo BASE_URL; ?>images/default-placeholder.png'">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="doc_type" value="employment_contract">
                                    <input type="file" name="spes_doc_image" accept="image/*" class="input-field file-field" required>
                                    <button type="submit" name="upload_spes_doc" class="btn-publish" style="width:100%;">
                                        <i class="fas fa-upload"></i> Upload Preview Image
                                    </button>
                                </form>
                                <hr class="form-divider">
                                <p class="form-section-label">Downloadable Source File</p>
                                <div class="current-file" id="employment-contract-current" data-original="Current File: <strong><?php echo htmlspecialchars(basename($spes_forms_data['employment_contract']['doc_file_path'] ?? 'None')); ?></strong>">
                                    Current File: <strong><?php echo htmlspecialchars(basename($spes_forms_data['employment_contract']['doc_file_path'] ?? 'None')); ?></strong>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="doc_type" value="employment_contract">
                                    <input type="file" name="spes_doc_source_file" accept=".pdf,.doc,.docx" class="input-field file-field" required onchange="updateCurrentFileName(this, 'employment-contract-current')">
                                    <button type="submit" name="upload_spes_doc_file" class="btn-primary" style="width:100%; border-radius:14px; font-size:10px;">
                                        <i class="fas fa-file-upload"></i> Upload Source File
                                    </button>
                                </form>
                            </div>

                            <div class="spes-form-section">
                                <h4>Oath of Undertaking Form</h4>
                                <p class="form-section-label">Preview Image</p>
                                <img src="<?php echo htmlspecialchars(str_replace('../../../../', BASE_URL, $spes_forms_data['oath_undertaking']['file_path'])); ?>" alt="Oath Preview" class="spes-form-preview" onerror="this.src='<?php echo BASE_URL; ?>images/default-placeholder.png'">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="doc_type" value="oath_undertaking">
                                    <input type="file" name="spes_doc_image" accept="image/*" class="input-field file-field" required>
                                    <button type="submit" name="upload_spes_doc" class="btn-publish" style="width:100%;">
                                        <i class="fas fa-upload"></i> Upload Preview Image
                                    </button>
                                </form>
                                <hr class="form-divider">
                                <p class="form-section-label">Downloadable Source File</p>
                                <div class="current-file" id="oath-undertaking-current" data-original="Current File: <strong><?php echo htmlspecialchars(basename($spes_forms_data['oath_undertaking']['doc_file_path'] ?? 'None')); ?></strong>">
                                    Current File: <strong><?php echo htmlspecialchars(basename($spes_forms_data['oath_undertaking']['doc_file_path'] ?? 'None')); ?></strong>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="doc_type" value="oath_undertaking">
                                    <input type="file" name="spes_doc_source_file" accept=".pdf,.doc,.docx" class="input-field file-field" required onchange="updateCurrentFileName(this, 'oath-undertaking-current')">
                                    <button type="submit" name="upload_spes_doc_file" class="btn-primary" style="width:100%; border-radius:14px; font-size:10px;">
                                        <i class="fas fa-file-upload"></i> Upload Source File
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <h3>SPES Batch List</h3>
                     <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>Batch Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($spesBatches) > 0): ?>
                            <?php foreach ($spesBatches as $batch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                <td><?php echo date('M d, Y, g:i A', strtotime($batch['start_date'])); ?></td>
                                <td><?php echo $batch['end_date'] ? date('M d, Y, g:i A', strtotime($batch['end_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $batch['status'] === 'active' ? 'status-active' : 'status-rejected'; ?>">
                                        <?php echo ucfirst($batch['status']); ?>
                                    </span>
                                </td>
                                <td>
                                <?php if ($batch['status'] === 'active'): ?>
                                    <button type="button" class="btn-delete-scholarship" onclick="showEndSpesModal('<?php echo $batch['batch_id']; ?>')">End Batch</button>
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center" style="padding: 20px;">No SPES batches found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                     </table>
                </div>

            </div>

            <div id="scholarship-history-page" class="page">
                <h1 class="main-title-scholar">Scholarship History</h1>
                <p>View active, ended, and SPES scholarship programs.</p>

                <div class="tabs">
                    <button class="tab-link active" onclick="openHistoryTab(event, 'RecentScholarshipsTab')">Recent Scholarship (Current Ongoing)</button>
                    <button class="tab-link" onclick="openHistoryTab(event, 'EndedScholarshipsTab')">Ended Scholarships</button>
                    <button class="tab-link" onclick="openHistoryTab(event, 'SpesHistoryTab')">SPES History</button>
                </div>

                <div id="RecentScholarshipsTab" class="tab-content" style="display: block;">
                    <h3>Recent Scholarships</h3>
                     <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>Scholarship Start/End Year</th>
                                <th>Scholarship Program</th>
                                <th>Application Date Start</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($activeScholarships) > 0): ?>
                            <?php foreach ($activeScholarships as $scholarship): ?>
                                <tr>
                                    <td><?php echo date('Y', strtotime($scholarship['created_at'])); ?> - Present</td>
                                    <td><?php echo htmlspecialchars($scholarship['title']); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($scholarship['created_at'])); ?></td>
                                    <td>
                                        <a href="?view_recent_members=<?php echo $scholarship['scholarship_id']; ?>#view-recent-members-page" class="view-details">View Members</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr><td colspan="4" class="text-center" style="padding: 20px;">No recent (active) scholarships found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                     </table>
                </div>

                <div id="EndedScholarshipsTab" class="tab-content">
                    <h3>Ended Scholarships</h3>
                     <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>Scholarship Start/End Year</th>
                                <th>Scholarship Program</th>
                                <th>Application Date Start</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($endedScholarships) > 0): ?>
                            <?php foreach ($endedScholarships as $scholarship): ?>
                                <tr>
                                    <td><?php echo date('Y', strtotime($scholarship['created_at'])); ?> - <?php echo date('Y', strtotime($scholarship['ended_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($scholarship['title']); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($scholarship['created_at'])); ?></td>
                                    <td>
                                        <a href="?view_ended_members=<?php echo $scholarship['scholarship_id']; ?>#view-ended-members-page" class="view-details">View Members</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr><td colspan="4" class="text-center" style="padding: 20px;">No ended scholarships found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                     </table>
                </div>

            <div id="SpesHistoryTab" class="tab-content">
                    <h3>SPES History</h3>
                    <table class="applicants-table">
                        <thead>
                            <tr>
                                <th>SPES</th>
                                <th>Program</th>
                                <th>Application Date</th>
                                <th>Status</th> <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($spesHistory) > 0): ?>
                            <?php foreach ($spesHistory as $batch): ?>
                            <tr>
                                <td><?php echo date('Y', strtotime($batch['start_date'])); ?></td>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                <td><?php echo date('F d, Y', strtotime($batch['start_date'])); ?></td>
                                
                                <td>
                                    <span class="status-badge <?php echo $batch['status'] === 'active' ? 'status-active' : 'status-rejected'; ?>">
                                        <?php echo ucfirst($batch['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?view_spes_members=<?php echo $batch['batch_id']; ?>#view-spes-members-page" class="view-details">View Members</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center" style="padding: 20px;">No SPES History found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="view-recent-members-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">Members for: <?php echo htmlspecialchars($recentScholarshipTitle); ?> (Current Ongoing)</h2>
                    <button class="back-btn" onclick="showPage('scholarship-history-page')">Back to History</button>
                    <table class="applicants-table">
                        <thead>
                            <tr><th>Name</th><th>Program</th><th>Scholarship Application Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentMembers) > 0): ?>
                                <?php foreach($recentMembers as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['Fname'] . ' ' . $member['Lname']); ?></td>
                                    <td><?php echo htmlspecialchars($recentScholarshipTitle); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($member['application_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No members found for this scholarship.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="view-ended-members-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">Members for: <?php echo htmlspecialchars($endedScholarshipTitle); ?> (Ended)</h2>
                    <button class="back-btn" onclick="showPage('scholarship-history-page')">Back to History</button>
                    <table class="applicants-table">
                         <thead>
                            <tr><th>Name</th><th>Program</th><th>Scholarship Application Date</th></tr>
                        </thead>
                        <tbody>
                             <?php if (count($endedMembers) > 0): ?>
                                <?php foreach($endedMembers as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['Fname'] . ' ' . $member['Lname']); ?></td>
                                    <td><?php echo htmlspecialchars($endedScholarshipTitle); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($member['application_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No members found for this ended scholarship.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="view-spes-members-page" class="page">
                <div class="applicants-container">
                    <h2 class="applicants-h2">Members for: <?php echo htmlspecialchars($spesBatchName); ?></h2>
                    <button class="back-btn" onclick="showPage('scholarship-history-page')">Back to History</button>
                    <table class="applicants-table">
                        <thead>
                            <tr><th>Name</th><th>Program</th><th>Application Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($spesMembers) > 0): ?>
                                <?php foreach($spesMembers as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['Fname'] . ' ' . $member['Lname']); ?></td>
                                    <td><?php echo htmlspecialchars($spesBatchName); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($member['application_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">No members found for this SPES batch.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

            <div id="endSpesModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeEndSpesModal()">&times;</span>
                    <div class="modal-header">End SPES Batch</div>
                    <form method="POST">
                        <input type="hidden" name="batch_id" id="endSpesBatchId">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" required style="width:100%;padding:10px;box-sizing:border-box;margin-top:5px;">
                        <label for="end_time">End Time:</label>
                        <input type="time" id="end_time" name="end_time" required style="width:100%;padding:10px;box-sizing:border-box;margin-top:5px;">
                        <button type="submit" name="end_spes_batch" class="btn-danger" style="margin-top:10px;">Confirm End Batch</button>
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
                        <p><strong>Deadline:</strong> <?php echo $message['deadline'] ? date('F j, Y', strtotime($message['deadline'])) : 'No deadline'; ?></p>
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
     const BASE_URL = '<?php echo BASE_URL; ?>';   

    function toggleMenu() {
        var menu = document.getElementById("dropdownMenu");
        var chevron = document.getElementById("chevronIcon");
        const isOpen = menu.classList.toggle("show");
        chevron.classList.toggle("open", isOpen);
    }

    function showPage(pageId) {
        document.querySelectorAll('.page').forEach(page => {
            page.classList.remove('active');
        });
        
        const url = new URL(window.location);
        url.hash = pageId;

        const mainPages = ['home-page', 'application-page', 'scholarship-page', 'scholarship-history-page', 'send-updates-page', 'total-applicants-page', 'reports-page', 'user-concerns-page', 'user-request-page'];
        if (mainPages.includes(pageId)) {
            url.searchParams.delete('view_scholarship');
            url.searchParams.delete('view_approved');
            url.searchParams.delete('search_spes_name');
            url.searchParams.delete('search_spes_id');
            url.searchParams.delete('view_recent_members');
            url.searchParams.delete('view_ended_members');
            url.searchParams.delete('view_spes_members');
        }
        history.pushState({}, '', url);

        const pageElement = document.getElementById(pageId);
        if (pageElement) {
             pageElement.classList.add('active');
        }

        if (pageId === 'scholarship-page') {
            const activeTab = sessionStorage.getItem('activeScholarshipTab') || 'ScholarshipMgmt';
            openScholarshipMgmtTab(null, activeTab);
        }

        // START: === FIX ===
        // This new block checks sessionStorage for the last active history tab
        if (pageId === 'scholarship-history-page') {
            const activeTab = sessionStorage.getItem('activeHistoryTab') || 'RecentScholarshipsTab';
            openHistoryTab(null, activeTab);
        }
        // END: === FIX ===

        const navMapping = {
            'home-page': 'home-nav',
            'application-page': 'history-nav',
            'approved-scholarship-programs-page': 'history-nav',
            'approved-applicants-list-page': 'history-nav',
            'approved-spes-list-page': 'history-nav',
            'scholarship-page': 'scholarships-nav',
            'scholarship-history-page': 'scholarship-history-nav',
            'view-recent-members-page': 'scholarship-history-nav',
            'view-ended-members-page': 'scholarship-history-nav',
            'view-spes-members-page': 'scholarship-history-nav',
            'send-updates-page': 'communication-nav',
            'total-applicants-page': 'total-applicants-nav',
            'total-applicants-scholarship': 'total-applicants-nav',
            'scholarship-applicants-page': 'total-applicants-nav',
            'total-applicants-spes': 'total-applicants-nav',
            'reports-page': 'reports-nav',
            'scholarship-document-page': 'reports-nav',
            'spes-document-page': 'reports-nav',
            'user-concerns-page': 'user-concerns-nav',
            'user-request-page': 'user-request-nav'
        };
        
        if (navMapping[pageId]) {
            highlightActiveNav(navMapping[pageId]);
        }
    }

     function openHistoryTab(evt, tabName) {
        openTab(evt, 'scholarship-history-page', tabName);
        // START: === FIX ===
        // This line saves the currently clicked tab name to the session storage
        sessionStorage.setItem('activeHistoryTab', tabName);
        // END: === FIX ===
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
    
    if (urlParams.has('view_scholarship')) hash = 'scholarship-applicants-page';
    else if (urlParams.has('view_approved')) hash = 'approved-applicants-list-page';
    else if (urlParams.has('search_spes_name')) hash = 'approved-spes-list-page';
    else if (urlParams.has('search_spes_id')) hash = 'total-applicants-spes';
    else if (urlParams.has('view_recent_members')) hash = 'view-recent-members-page';
    else if (urlParams.has('view_ended_members')) hash = 'view-ended-members-page';
    else if (urlParams.has('view_spes_members')) hash = 'view-spes-members-page';

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
                animateValue(el, 0, targetValue, 1500);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.box-value').forEach(el => {
        observer.observe(el);
    });

});
    function showToast(message, type = 'success') {
        var toast = document.getElementById('toast-message');
        var toastText = document.getElementById('toast-text');
        var toastIcon = document.getElementById('toast-icon');

        toastText.textContent = message;

        if (type === 'success') {
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
        } else if (type === 'error') {
            toastIcon.className = 'fas fa-exclamation-triangle';
            toast.style.background = '#dc3545';
        } else if (type === 'info') {
            toastIcon.className = 'fas fa-info-circle';
            toast.style.background = '#17a2b8';
        }

        toast.classList.add('show');
        setTimeout(function() {
            toast.classList.remove('show');
        }, 3000);
    }

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
                    const fileNameWithId = docPath.split('/').pop();
                    const fileName = fileNameWithId.substring(fileNameWithId.indexOf('_') + 1);

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
    let modalContent = document.getElementById('userDetailsContent');
    const profilePicPath = user.profile_pic ? `../../../../${user.profile_pic}` : '../../../../images/user.png';

    const contentHTML = `
        <div class="user-details-modal-container">
            <div class="user-details-header">
                <img src="${profilePicPath}" alt="Profile Picture" class="user-details-pic" onerror="this.onerror=null;this.src='../../../../images/user.png';">
                <div class="user-details-name">${user.Fname} ${user.Mname || ''} ${user.Lname}</div>
            </div>

            <div class="user-details-section-header">Basic Information</div>
            <div class="user-details-grid">
                 <div class="user-details-item">
                    <span class="user-details-label">Full Name</span>
                    <div class="user-details-value">${user.Fname} ${user.Mname || ''} ${user.Lname}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Age</span>
                    <div class="user-details-value">${user.Age || 'N/A'}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Gender</span>
                    <div class="user-details-value">${user.Gender || 'N/A'}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Birthdate</span>
                    <div class="user-details-value">${user.Birthdate || 'N/A'}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Address</span>
                    <div class="user-details-value">${user.Address || 'N/A'}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Contact Number</span>
                    <div class="user-details-value">${user.contact_number || 'N/A'}</div>
                </div>
                <div class="user-details-item">
                    <span class="user-details-label">Email</span>
                    <div class="user-details-value">${user.Email || 'N/A'}</div>
                </div>
            </div>
        </div>
    `;

    modalContent.innerHTML = contentHTML;
    document.getElementById('userDetailsModal').style.display = 'flex';
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
        
        const docPath = `<?php echo BASE_URL; ?>${app.spes_documents_path ? app.spes_documents_path.replace('../../../../', '') : ''}`;

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

        html += '<hr style="margin: 15px 0;"><h4>Parental & Status Information</h4>';
        html += `<p><strong>GSIS Beneficiary:</strong> ${app.gsis_beneficiary || 'N/A'}</p>`;
        html += `<p><strong>Student Type:</strong> ${app.student_type || 'N/A'}</p>`;
        html += `<p><strong>Parent Status:</strong> ${app.parent_status || 'N/A'}</p>`;
        html += `<p><strong>Father:</strong> ${app.father_name_contact || 'N/A'} - <i>${app.father_occupation || 'N/A'}</i></p>`;
        html += `<p><strong>Mother:</strong> ${app.mother_name_contact || 'N/A'} - <i>${app.mother_occupation || 'N/A'}</i></p>`;

        html += '<hr style="margin: 15px 0;"><h4>Educational Background</h4>';
        html += `<table class="applicants-table" style="font-size: 11px;">
                    <thead><tr><th>Level</th><th>School</th><th>Degree/Course</th><th>Year/Level</th><th>Attendance</th></tr></thead>
                    <tbody>
                        <tr><td>Elementary</td><td>${app.elem_school||'N/A'}</td><td>${app.elem_degree||'N/A'}</td><td>${app.elem_year||'N/A'}</td><td>${app.elem_attendance||'N/A'}</td></tr>
                        <tr><td>Secondary</td><td>${app.sec_school||'N/A'}</td><td>${app.sec_degree||'N/A'}</td><td>${app.sec_year||'N/A'}</td><td>${app.sec_attendance||'N/A'}</td></tr>
                        <tr><td>Tertiary</td><td>${app.ter_school||'N/A'}</td><td>${app.ter_degree||'N/A'}</td><td>${app.ter_year||'N/A'}</td><td>${app.ter_attendance||'N/A'}</td></tr>
                        <tr><td>Tech-Voc</td><td>${app.tech_school||'N/A'}</td><td>${app.tech_degree||'N/A'}</td><td>${app.tech_year||'N/A'}</td><td>${app.tech_attendance||'N/A'}</td></tr>
                    </tbody>
                </table>`;
        
        html += '<hr style="margin: 15px 0;"><h4>Skills & SPES History</h4>';
        html += `<p><strong>Special Skills:</strong> ${app.special_skills || 'None'}</p>`;
        html += `<p><strong>Availment History:</strong> ${app.availment_history || 'None'}</p>`;
        html += `<p><strong>Year History:</strong> ${app.year_history || 'N/A'}</p>`;
        html += `<p><strong>SPES ID History:</strong> ${app.spes_id_history || 'N/A'}</p>`;
        
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

        html += '</div>';

        document.getElementById('spesAppModalBody').innerHTML = html;
        document.getElementById('spesAppModal').style.display = 'flex';
    }

    function closeSpesAppModal() {
        document.getElementById('spesAppModal').style.display = 'none';
        document.getElementById('spesAppModalBody').innerHTML = '';
    }

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

function viewUserDocuments(scholarshipData, userName) {
    document.getElementById('viewDocumentsModalHeader').textContent = `Scholarship Documents for ${userName}`;
    let html = '';

    // Handle requirement documents
    html += '<hr style="margin: 20px 0;"><h4>Requirement Documents:</h4>';
    try {
        const docs = JSON.parse(scholarshipData.docs);
        if (Array.isArray(docs) && docs.length > 0 && docs[0] !== null) {
            docs.forEach(docPath => {
                if (docPath) { 
                    const fullPath = `<?php echo BASE_URL; ?>${docPath.replace('../../../../', '')}`;
                    const fileName = docPath.substring(docPath.indexOf('_') + 1);
                    
                    html += `<p><a href="${fullPath}" target="_blank" download="${fileName}" class="btn-outline">
                    <i class="fas fa-file-alt"></i> ${fileName}</a></p>`;
                }
            });
        } else {
             html += '<p>No requirement documents were uploaded by this applicant.</p>';
        }
    } catch (e) {
        html += '<p>No requirement documents were uploaded by this applicant.</p>';
    }

    document.getElementById('viewDocumentsModalBody').innerHTML = html;
    document.getElementById('viewDocumentsModal').style.display = 'flex';
}

    function viewSpesUserDocuments(spesDocs, userName) {
        document.getElementById('viewDocumentsModalHeader').textContent = `SPES Documents for ${userName}`;
        let html = '';
        
        // Handle ID images
        html += '<h4>Uploaded IDs:</h4>';
        try {
            const idPaths = JSON.parse(spesDocs.ids);
            if (Array.isArray(idPaths) && idPaths.length > 0 && idPaths[0]) {
                 idPaths.forEach(path => {
                    if (path) {
                        const fullPath = `<?php echo BASE_URL; ?>${path.replace('../../../../', '')}`;
                        html += `<a href="${fullPath}" target="_blank" title="Click to view full size">
                                     <img src="${fullPath}" alt="User ID" style="max-width: 100%; height: auto; border-radius: 5px; margin-bottom: 15px; border: 1px solid #ddd; cursor: pointer;">
                                 </a>`;
                    }
                });
            } else {
                 html += '<p>No ID documents were uploaded.</p>';
            }
        } catch(e) {
            html += '<p>No ID documents were uploaded or there was an error reading them.</p>';
        }

        // Handle requirement documents
        html += '<hr style="margin: 20px 0;"><h4>Requirement Documents:</h4>';
        
        let requirementFiles = [];
        try {
            const parsedReqs = JSON.parse(spesDocs.reqs);
            if (Array.isArray(parsedReqs)) {
                requirementFiles = parsedReqs;
            }
        } catch (e) {
            if (spesDocs.reqs) {
                requirementFiles.push(spesDocs.reqs);
            }
        }

        if (requirementFiles.length > 0) {
            requirementFiles.forEach(path => {
                if(path) {
                    const fullPath = `<?php echo BASE_URL; ?>${path.replace('../../../../', '')}`;
                    const fileName = path.substring(path.indexOf('_') + 1);
                    html += `<p><a href="${fullPath}" target="_blank" download="${fileName}" class="btn-outline">
                    <i class="fas fa-file-alt"></i> ${fileName}</a></p>`;
                }
            });
        } else {
            html += '<p>No requirement documents were uploaded.</p>';
        }

        document.getElementById('viewDocumentsModalBody').innerHTML = html;
        document.getElementById('viewDocumentsModal').style.display = 'flex';
    }

    function closeViewDocumentsModal() {
        document.getElementById('viewDocumentsModal').style.display = 'none';
        document.getElementById('viewDocumentsModalBody').innerHTML = '';
    }

    function scrollAdminChatToBottom() {
        const chatMessages = document.getElementById('concernChatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    window.showPage = (function(origShowPage) {
        return function(pageId) {
            origShowPage(pageId);
            if (pageId === 'user-concerns-page') {
                // Use a small timeout to ensure the content is rendered before scrolling
                setTimeout(scrollAdminChatToBottom, 100);
            }
        };
    })(window.showPage || function() {});

function toggleMessageMenu(messageId) {
        // This makes sure only one menu is open at a time
        document.querySelectorAll('.message-menu').forEach(menu => {
            if (menu.id !== 'menu-' + messageId) {
                menu.style.display = 'none';
            }
        });
        
        // This toggles the specific menu you clicked on
        const menu = document.getElementById('menu-' + messageId);
        if (menu) {
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    }
    
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.message-options')) {
            document.querySelectorAll('.message-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
    
    function openTab(evt, parentId, tabName) {
        var i, tabcontent, tablinks;
        var parentElement = document.getElementById(parentId);
        tabcontent = parentElement.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = parentElement.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        
        if (evt && evt.currentTarget) {
            evt.currentTarget.className += " active";
        } else {
            const fallbackButton = document.querySelector(`#${parentId} .tab-link[onclick*="'${tabName}'"]`);
            if (fallbackButton) {
                fallbackButton.classList.add('active');
            }
        }
    }

    function openScholarshipMgmtTab(evt, tabName) {
        openTab(evt, 'scholarship-page', tabName);
        sessionStorage.setItem('activeScholarshipTab', tabName);
    }


    function showEndSpesModal(batchId) {
        document.getElementById('endSpesBatchId').value = batchId;
        document.getElementById('endSpesModal').style.display = 'flex';
    }

    function closeEndSpesModal() {
        document.getElementById('endSpesModal').style.display = 'none';
    }


    window.onclick = function(event) {
        const modals = ['appFormModal', 'editSlotsModal', 'rejectionModal', 'userDetailsModal', 'spesAppModal', 'validIdModal', 'viewDocumentsModal', 'endSpesModal'];
        modals.forEach(modalId => {
            let modal = document.getElementById(modalId);
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    };

function updateCurrentFileName(inputElement, displayElementId) {
    const file = inputElement.files[0];
    const displayElement = document.getElementById(displayElementId);
    
    if (file) {
        displayElement.innerHTML = `Selected: <strong>${file.name}</strong> <em>(Click upload to save)</em>`;
        displayElement.style.color = '#007bff';
    } else {
        // Reset to original content if no file selected
        const originalText = displayElement.getAttribute('data-original');
        displayElement.innerHTML = originalText;
        displayElement.style.color = '';
    }
}

</script>
</body>
</html>