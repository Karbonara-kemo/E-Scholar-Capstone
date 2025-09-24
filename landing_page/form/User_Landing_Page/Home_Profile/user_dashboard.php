<?php
include '../../../../connect.php';

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php");
    exit();
}

// FETCH USER FIRST!
$userId = $_SESSION['user_id'];
$sql = "SELECT * FROM user WHERE user_id = ?";
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


// --- START: FETCH SPES FORM IMAGES & DOCS ---
$spes_form_files = [
    'employment_contract' => [
        'image' => '../../../../images/Employment-contract.jpg', 
        'doc' => '../../../../download_assets/SPES-FORM-4-EMPLOYMENT-CONTRACT-1-1.docx'
    ],
    'oath_undertaking' => [
        'image' => '../../../../images/spesos-oath-of-undertaking.jpg',
        'doc' => '../../../../download_assets/SPES-FORM-2-A-OATH-OF-UNDERTAKING-.docx'
    ]
];
$spesFilesSqlUser = "SELECT doc_type, file_path, doc_file_path FROM spes_files WHERE doc_type IN ('employment_contract', 'oath_undertaking')";
$spesFilesResultUser = $conn->query($spesFilesSqlUser);
if ($spesFilesResultUser) {
    while($row = $spesFilesResultUser->fetch_assoc()) {
        if (!empty($row['file_path'])) {
            $spes_form_files[$row['doc_type']]['image'] = $row['file_path'];
        }
        if (!empty($row['doc_file_path'])) {
            $spes_form_files[$row['doc_type']]['doc'] = $row['doc_file_path'];
        }
    }
}
// --- END: FETCH SPES FORM IMAGES & DOCS ---

// --- START: NEW SCHOLARSHIP APPROVAL STATUS CHECK ---
$isApprovedForAnyScholarship = false;
// FIX: This query now joins with the scholarships table to ensure we only check for 
// approved status on scholarships that are currently 'active'.
$checkApprovalSql = "
    SELECT 1 
    FROM applications a
    JOIN scholarships s ON a.scholarship_id = s.scholarship_id
    WHERE a.user_id = ? AND a.status = 'approved' AND s.status = 'active'
    LIMIT 1
";
$checkApprovalStmt = $conn->prepare($checkApprovalSql);
$checkApprovalStmt->bind_param("i", $userId);
$checkApprovalStmt->execute();
$checkApprovalResult = $checkApprovalStmt->get_result();
if ($checkApprovalResult->num_rows > 0) {
    $isApprovedForAnyScholarship = true;
}
// --- END: NEW SCHOLARSHIP APPROVAL STATUS CHECK ---

// --- START: NEW CHECK FOR ACTIVE SPES BATCH ---
$isSpesActive = false;
$checkSpesBatchSql = "SELECT 1 FROM spes_batches WHERE status = 'active' LIMIT 1";
$checkSpesBatchResult = $conn->query($checkSpesBatchSql);
if ($checkSpesBatchResult->num_rows > 0) {
    $isSpesActive = true;
}
// --- END: NEW CHECK FOR ACTIVE SPES BATCH ---

// --- START: CHECK IF USER HAS EVER BEEN APPROVED FOR SPES ---
$hasBeenApprovedForSpes = false;
$checkSpesApprovalSql = "SELECT 1 FROM spes_applications WHERE user_id = ? AND status = 'approved' LIMIT 1";
$checkSpesApprovalStmt = $conn->prepare($checkSpesApprovalSql);
$checkSpesApprovalStmt->bind_param("i", $userId);
$checkSpesApprovalStmt->execute();
$checkSpesApprovalResult = $checkSpesApprovalStmt->get_result();
if ($checkSpesApprovalResult->num_rows > 0) {
    $hasBeenApprovedForSpes = true;
}
// --- END: CHECK IF USER HAS EVER BEEN APPROVED FOR SPES ---

// --- START: Scholarship FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $scholarshipId = $_POST['scholarship_id'];
    $fullname = trim($_POST['lname'] . ', ' . $_POST['fname'] . ' ' . $_POST['mname']);
    $birthdate = $_POST['dob'];
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    // Email is no longer collected from the form
    $school = $_POST['college_school'];
    $course = $_POST['college_course'];
    $year_level = ''; // This field seems unused but kept for structure
    $family_income = $_POST['family_income'];
    $facebook = $_POST['facebook'];
    $civil_status = $_POST['civil_status'];
    $gender = $_POST['gender'];
    $place_of_birth = $_POST['pob'];
    $mother_name = $_POST['mother_name'];
    $mother_occupation = $_POST['mother_occupation'];
    $father_name = $_POST['father_name'];
    $father_occupation = $_POST['father_occupation'];
    $dependents = $_POST['dependents'];
    $elem_school = $_POST['elem_school'];
    $elem_honors = $_POST['elem_honors'];
    $elem_grad = $_POST['elem_grad'];
    $hs_school = $_POST['hs_school'];
    $hs_honors = $_POST['hs_honors'];
    $hs_grad = $_POST['hs_grad'];
    $voc_school = $_POST['voc_school'];
    $voc_honors = $_POST['voc_honors'];
    $voc_grad = $_POST['voc_grad'];
    $college_school = $_POST['college_school'];
    $college_course = $_POST['college_course'];
    $college_average = $_POST['college_average'];
    $college_awards = $_POST['college_awards'];

    $documents = [];
    if (!empty($_FILES['supporting_documents']['name'][0])) {
        foreach ($_FILES['supporting_documents']['tmp_name'] as $key => $tmp_name) {
            $file_name = basename($_FILES['supporting_documents']['name'][$key]);
            $target_dir = "../../../../uploads/";
            $target_file = $target_dir . uniqid() . "_" . $file_name;
            if (move_uploaded_file($tmp_name, $target_file)) {
                $documents[] = $target_file;
            }
        }
    }
    $documents_json = json_encode($documents);

    // SQL statement updated to remove the 'email' column
    $sql = "INSERT INTO applications (
        user_id, scholarship_id, fullname, birthdate, address, contact, school, course, year_level, family_income, documents, status,
        facebook, civil_status, gender, place_of_birth, mother_name, mother_occupation, father_name, father_occupation, dependents,
        elem_school, elem_honors, elem_grad, hs_school, hs_honors, hs_grad, voc_school, voc_honors, voc_grad, college_school, college_course, college_average, college_awards
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending',
        ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";
    $stmt = $conn->prepare($sql);
    // Bind parameters updated to remove the email variable and its type 's'
    // FIXED: The type definition string now has 33 characters (ii + 31 s's) to match the 33 variables.
    $stmt->bind_param(
        "iisssssssssssssssssssssssssssssss",
        $userId, $scholarshipId, $fullname, $birthdate, $address, $contact, $school, $course, $year_level, $family_income, $documents_json,
        $facebook, $civil_status, $gender, $place_of_birth, $mother_name, $mother_occupation, $father_name, $father_occupation, $dependents,
        $elem_school, $elem_honors, $elem_grad, $hs_school, $hs_honors, $hs_grad, $voc_school, $voc_honors, $voc_grad, $college_school, $college_course, $college_average, $college_awards
    );
    if (!$stmt->execute()) {
        die("Error: " . $stmt->error);
    } else {
        // --- START: MODIFIED - SET SESSION AND REDIRECT ---
        $_SESSION['application_submitted'] = true;
        header("Location: user_dashboard.php#scholarships-page");
        exit();
        // --- END: MODIFIED - SET SESSION AND REDIRECT ---
    }
}
// --- END: Scholarship FORM SUBMISSION LOGIC ---

// --- START: NEW SPES APPLICATION SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_spes_application'])) {
    // Personal Information
    $surname = $_POST['surname'] ?? null;
    $firstname = $_POST['firstname'] ?? null;
    $middlename = $_POST['middlename'] ?? null;
    $gsis_beneficiary = $_POST['gsis_beneficiary'] ?? null;
    $dob = $_POST['dob'] ?? null;
    $place_of_birth = $_POST['place_of_birth'] ?? null;
    $citizenship = $_POST['citizenship'] ?? null;
    $contact = $_POST['contact'] ?? null;
    $email = $_POST['email'] ?? null;
    $social_media = $_POST['social_media'] ?? null;
    $civil_status = $_POST['civil_status'] ?? null; // Renamed from 'status' in form to avoid conflict
    $sex = $_POST['sex'] ?? null;
    $student_type = $_POST['student_type'] ?? null;
    $present_address = $_POST['present_address'] ?? null;
    $permanent_address = $_POST['permanent_address'] ?? null;

    // Handle Parent Status (combining radio and checkboxes)
    $parent_status_parts = [];
    if (!empty($_POST['parent_status'])) {
        $parent_status_parts[] = $_POST['parent_status'];
    }
    if (isset($_POST['parent_status_indigenous'])) {
        $parent_status_parts[] = 'Indigenous People';
    }
    if (isset($_POST['parent_status_displaced_local'])) {
        $parent_status_parts[] = 'Displaced Worker (Local)';
    }
    if (isset($_POST['parent_status_displaced_ofw'])) {
        $parent_status_parts[] = 'Displaced Worker (OFW)';
    }
    $parent_status = implode(', ', $parent_status_parts);

    // Parental Information
    $father_name_contact = $_POST['father_name_contact'] ?? null;
    $mother_name_contact = $_POST['mother_name_contact'] ?? null;
    $father_occupation = $_POST['father_occupation'] ?? null;
    $mother_occupation = $_POST['mother_occupation'] ?? null;

    // Educational Background
    $elem_school = $_POST['elem_school'] ?? null; $elem_degree = $_POST['elem_degree'] ?? null; $elem_year = $_POST['elem_year'] ?? null; $elem_attendance = $_POST['elem_attendance'] ?? null;
    $sec_school = $_POST['sec_school'] ?? null; $sec_degree = $_POST['sec_degree'] ?? null; $sec_year = $_POST['sec_year'] ?? null; $sec_attendance = $_POST['sec_attendance'] ?? null;
    $ter_school = $_POST['ter_school'] ?? null; $ter_degree = $_POST['ter_degree'] ?? null; $ter_year = $_POST['ter_year'] ?? null; $ter_attendance = $_POST['ter_attendance'] ?? null;
    $tech_school = $_POST['tech_school'] ?? null; $tech_degree = $_POST['tech_degree'] ?? null; $tech_year = $_POST['tech_year'] ?? null; $tech_attendance = $_POST['tech_attendance'] ?? null;

    // Skills & History
    $special_skills = $_POST['special_skills'] ?? null;
    
    // Process Availment History
    $availment_history = [];
    $year_history = [];
    $spes_id_history = [];
    for ($i = 1; $i <= 4; $i++) {
        if (isset($_POST["availment_$i"])) $availment_history[] = "{$i}";
        if (!empty($_POST["year_$i"])) $year_history[] = $_POST["year_$i"];
        if (!empty($_POST["spesid_$i"])) $spes_id_history[] = $_POST["spesid_$i"];
    }
    $availment_history_str = implode(', ', $availment_history);
    $year_history_str = implode(', ', $year_history);
    $spes_id_history_str = implode(', ', $spes_id_history);

    // --- START: MODIFIED CODE FOR MULTIPLE ID UPLOAD ---
    $id_image_paths = [];
    if (!empty($_FILES['id_images']['name'][0])) {
        $target_dir = "../../../../uploads/spes_ids/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        foreach ($_FILES['id_images']['tmp_name'] as $key => $tmp_name) {
            // Check for upload errors
            if ($_FILES['id_images']['error'][$key] == 0) {
                $file_name = basename($_FILES['id_images']['name'][$key]);
                $target_file = $target_dir . uniqid() . "_" . $file_name;
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $id_image_paths[] = $target_file;
                }
            }
        }
    }
    $id_images_json = json_encode($id_image_paths);
    // --- END: MODIFIED CODE FOR MULTIPLE ID UPLOAD ---
    
    // --- START: NEW CODE FOR SPES DOCUMENT UPLOAD ---
    $spes_documents_path = null;
    if (isset($_FILES['spes_documents']) && $_FILES['spes_documents']['error'] == 0) {
        $target_dir_docs = "../../../../uploads/spes_docs/";
        if (!is_dir($target_dir_docs)) {
            mkdir($target_dir_docs, 0755, true);
        }
        $file_name_docs = basename($_FILES['spes_documents']['name']);
        $target_file_docs = $target_dir_docs . uniqid() . "_" . $file_name_docs;
        if (move_uploaded_file($_FILES['spes_documents']['tmp_name'], $target_file_docs)) {
            $spes_documents_path = $target_file_docs;
        }
    }
    // --- END: NEW CODE FOR SPES DOCUMENT UPLOAD ---

    // Prepare SQL Statement (MODIFIED)
    $sql = "INSERT INTO spes_applications (
        user_id, surname, firstname, middlename, gsis_beneficiary, id_image_paths, spes_documents_path, dob, place_of_birth, citizenship, 
        contact, email, social_media, civil_status, sex, student_type, parent_status, present_address, permanent_address, 
        father_name_contact, mother_name_contact, father_occupation, mother_occupation, 
        elem_school, elem_degree, elem_year, elem_attendance, 
        sec_school, sec_degree, sec_year, sec_attendance, 
        ter_school, ter_degree, ter_year, ter_attendance, 
        tech_school, tech_degree, tech_year, tech_attendance, 
        special_skills, availment_history, year_history, spes_id_history
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    // Bind Param (MODIFIED: The type string now has 43 characters)
    $stmt->bind_param(
        "issssssssssssssssssssssssssssssssssssssssss",
        $userId, $surname, $firstname, $middlename, $gsis_beneficiary, $id_images_json, $spes_documents_path, $dob, $place_of_birth, $citizenship,
        $contact, $email, $social_media, $civil_status, $sex, $student_type, $parent_status, $present_address, $permanent_address,
        $father_name_contact, $mother_name_contact, $father_occupation, $mother_occupation,
        $elem_school, $elem_degree, $elem_year, $elem_attendance,
        $sec_school, $sec_degree, $sec_year, $sec_attendance,
        $ter_school, $ter_degree, $ter_year, $ter_attendance,
        $tech_school, $tech_degree, $tech_year, $tech_attendance,
        $special_skills, $availment_history_str, $year_history_str, $spes_id_history_str
    );

    if ($stmt->execute()) {
        // --- START: MODIFIED - SET SESSION AND REDIRECT ---
        $_SESSION['spes_application_submitted'] = true;
        header("Location: user_dashboard.php#spes-page");
        exit();
        // --- END: MODIFIED - SET SESSION AND REDIRECT ---
    } else {
        die("Error submitting SPES application: " . $stmt->error);
    }
}
// --- END: NEW SPES APPLICATION SUBMISSION LOGIC ---


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

$scholarshipSql = "
    SELECT s.*, COUNT(CASE WHEN a.status IN ('pending', 'approved') THEN 1 ELSE NULL END) as total_applicants
    FROM scholarships s
    LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id
    WHERE s.status = 'active'
    GROUP BY s.scholarship_id
";
$scholarshipResult = $conn->query($scholarshipSql);
$scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);

$totalScholarships = count($scholarships);

// --- START: MODIFIED - Count total applications from both Scholarship and SPES tables ---
$countCombinedSql = "
    SELECT 
        (SELECT COUNT(*) FROM applications WHERE user_id = ?) + 
        (SELECT COUNT(*) FROM spes_applications WHERE user_id = ?) 
    AS total_applications
";
$countStmt = $conn->prepare($countCombinedSql);
$countStmt->bind_param("ii", $userId, $userId);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalApplications = $countResult['total_applications'];
// --- END: MODIFIED ---

// Fetch scholarship groups the user is APPROVED for
$approvedGroups = [];
$approvedSql = "SELECT s.scholarship_id, s.title 
                FROM scholarships s 
                JOIN applications a ON s.scholarship_id = a.scholarship_id 
                WHERE a.user_id = ? AND a.status = 'approved'";
$approvedStmt = $conn->prepare($approvedSql);
$approvedStmt->bind_param("i", $userId);
$approvedStmt->execute();
$approvedResult = $approvedStmt->get_result();
while ($row = $approvedResult->fetch_assoc()) {
    $approvedGroups[] = $row;
}

// --- START: NEW SPES APPLICATION STATUS CHECK ---
$spesApplicationStatus = null;
$checkSpesSql = "SELECT status FROM spes_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$checkSpesStmt = $conn->prepare($checkSpesSql);
$checkSpesStmt->bind_param("i", $userId);
$checkSpesStmt->execute();
$checkSpesResult = $checkSpesStmt->get_result();
if ($checkSpesResult->num_rows > 0) {
    $spesApp = $checkSpesResult->fetch_assoc();
    // Only consider 'pending' or 'approved' status as a blocker
    if ($spesApp['status'] === 'pending' || $spesApp['status'] === 'approved') {
        $spesApplicationStatus = $spesApp['status'];
    }
}
// --- END: NEW SPES APPLICATION STATUS CHECK ---

// Handle chat logic
$selectedGroupId = isset($_GET['chat_group']) ? intval($_GET['chat_group']) : null;
$messages = [];
$chatTitle = 'Chat with Admin'; // Default title

if ($selectedGroupId) {
    // Security check: Make sure the user is a member of the group they're trying to view.
    $isMember = false;
    foreach ($approvedGroups as $group) {
        if ($group['scholarship_id'] == $selectedGroupId) {
            $isMember = true;
            $chatTitle = htmlspecialchars($group['title']) . " (Updates)";
            break;
        }
    }

    if ($isMember) {
        // Fetch group messages (admin posts)
        $stmt = $conn->prepare("SELECT * FROM concerns WHERE scholarship_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $selectedGroupId);
    }
} else {
    // Fetch personal messages
    $stmt = $conn->prepare("SELECT * FROM concerns WHERE user_id = ? AND scholarship_id IS NULL ORDER BY created_at ASC");
    $stmt->bind_param("i", $userId);
}

// Execute the prepared statement if it was set
if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}


if (isset($_POST['send_concern'])) {
    $message = trim($_POST['concern_message']);
    $attachmentPath = null;

    // Handle file upload
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

    // Proceed only if there is a message or an attachment
    // Assumption: `concerns` table has been altered to include `attachment_path` VARCHAR(255) NULL
    if (!empty($message) || $attachmentPath) {
        $stmt = $conn->prepare("INSERT INTO concerns (user_id, sender, message, attachment_path) VALUES (?, 'user', ?, ?)");
        $stmt->bind_param("iss", $userId, $message, $attachmentPath);
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

/* --- NEW: Added transition to all action buttons --- */
.btn, .get-started, .submit-btn, .back-btn {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

/* --- NEW: Added hover effect for buttons --- */
.btn:hover, .get-started:hover, .submit-btn:hover, .back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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

.btn-primary:disabled {
    background-color: #808080;
    border-color: #808080;
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
    top: -5px;
    right: -5px;
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

/* --- MODIFIED: Removed hover transition from .box --- */
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

.get-started:hover {
    background-color: #10087c; /* Darker shade on hover */
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
    background-color: #f4f4f4;
    border-radius: 12px;
}

.page {
    max-width: 1200px;
    margin: auto;
    display: none;
}
.page.active {
    display: block;
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

.submit-btn:hover {
    background-color: #10087c;
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
    margin: 0;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
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
    align-items: center;
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

.chat-input .upload-btn {
    background: none;
    border: none;
    color: #555;
    font-size: 20px;
    cursor: pointer;
    padding: 0 10px;
    border-radius: 50%;
    height: 40px;
    width: 40px;
    transition: background 0.2s, color 0.2s;
}
.chat-input .upload-btn:hover {
    background: #e9ecef;
    color: #090549;
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

.message-attachment {
    margin-top: 8px;
    padding: 8px 12px;
    background-color: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.message-attachment a {
    color: inherit;
    text-decoration: none;
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.message-attachment a:hover {
    text-decoration: underline;
}
.admin-message .message-attachment {
    background-color: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

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
        padding-bottom: 70px; /* Added padding to avoid content being hidden by bottom nav */
    }
    
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

    #communication-page {
        width: 100%;
    }

    .concerns-layout {
        flex-direction: column;
        height: calc(100vh - 140px); /* Adjust for navbars */
        width: 100%;
    }

    .concerns-list {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #eee;
        flex-shrink: 0;
        max-height: 200px;
        overflow-y: auto;
    }

    .concerns-list li a {
        font-size: 11px;
        padding: 10px 15px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .concerns-chat-area {
        flex-grow: 1;
        overflow: hidden;
        width: 100%;
        min-width: 0;
    }

    .chat-container {
        height: 100%;
        margin: 0;
        width: 100%;
        min-width: 0;
    }

    .chat-messages {
        width: 100%;
        min-width: 0;
    }

    .message {
        max-width: 80%;
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .chat-input {
        width: 100%;
        min-width: 0;
    }
    
        .chat-input textarea {
        height: 35px;
        min-width: 0;
        flex: 1;
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
        font-size: 9px;
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

.status-pending {
    background-color: #FFF3CD;
    color: #856404;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: bold;
}
.status-approved {
    background-color: #D4EDDA;
    color: #155724;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: bold;
}
.status-rejected {
    background-color: #F8D7DA;
    color: #721C24;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: bold;
}

/* New Chat Layout Styles */
.concerns-layout {
    display: flex;
    height: calc(100vh - 120px);
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.concerns-list {
    width: 220px;
    background: #f4f4f4;
    border-right: 1px solid #eee;
    padding: 10px 0;
    overflow-y: auto;
}
.concerns-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.concerns-list li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    text-decoration: none;
    color: #333;
    font-size: 12px;
}
.concerns-list li a:hover {
    background: #e9ecef;
}
.concerns-list li.active a {
    background: #090549;
    color: white;
    font-weight: bold;
}
.concerns-chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* --- Styles converted from inline --- */
.welcome-screen {
    margin-top: 100px;
}
#home-page .box .get-started {
    margin-top: 20px;
}
.get-started:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}
#spes-employment-contract-page .form-container-application,
#spes-oath-of-undertaking-page .form-container-application {
    position: relative;
    z-index: 1;
    text-align: center;
}
#spes-employment-contract-page h2,
#spes-oath-of-undertaking-page h2 {
    margin-bottom: 20px;
}
#spes-employment-contract-page .image,
#spes-oath-of-undertaking-page .image {
    max-width: 100%;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-bottom: 30px;
}
#spes-employment-contract-page .submit-btn,
#spes-oath-of-undertaking-page .submit-btn {
    width: auto;
    display: inline-block;
    margin-top: 20px;
    text-decoration: none;
}
#spes-employment-contract-page .back-btn,
#spes-oath-of-undertaking-page .back-btn {
    margin-top: 20px;
}
#spes-application-form-page {
    position: relative;
}
#spes-application-form-page .form-container-application {
    position: relative;
    z-index: 1;
}
#spes-form-logo-1 {
    width: 80px;
    position: absolute;
    top: 130px;
    left: 7%;
    opacity: 1;
    pointer-events: none;
}
#spes-form-logo-2 {
    width: 80px;
    position: absolute;
    top: 135px;
    right: 7%;
    opacity: 1;
    pointer-events: none;
}
#spes-form-logo-bg-1 {
    width: 550px;
    position: absolute;
    top: 10%;
    left: 50px;
    opacity: 0.1;
    pointer-events: none;
}
#spes-form-logo-bg-2 {
    width: 550px;
    position: absolute;
    top: 43%;
    left: 50px;
    opacity: 0.1;
    pointer-events: none;
}
#spes-form-logo-bg-3 {
    width: 550px;
    position: absolute;
    top: 75%;
    left: 50px;
    opacity: 0.1;
    pointer-events: none;
}
#spes-application-form-page a.submit-btn {
    padding: 8px 20px;
    width: auto;
    display: inline-block;
    margin-bottom: 10px;
    right: 20px;
    position: absolute;
    top: 10px;
    text-align: center;
    text-decoration: none;
}
.flex-container {
    display: flex;
    gap: 10px;
}
.flex-item {
    flex: 1;
}
.table-container {
    overflow-x: auto;
}
#spes-application-form-page .doc-req-section div {
    margin-left: 20px;
}
.form-group {
    margin: 20px 0;
}
.form-helper-text {
    font-size: 10px;
    color: #555;
    margin-top: 5px;
    margin-bottom: 10px;
}
.form-section-header {
    font-weight: bold;
    margin-bottom: 10px;
}
.form-section-header.top-margin {
    margin-top: 20px;
}
.spes-history-input {
    display: inline-block;
    width: 22%;
}
#history-page .history-table td {
    padding: 10px;
}
#history-page .history-table tr.no-history-row td {
    text-align: center;
    padding: 20px;
}
.chat-header {
    padding: 15px 20px;
    font-weight: bold;
    border-bottom: 1px solid #eee;
    background: #f7f7fa;
}
.no-messages {
    text-align: center;
    color: #888;
    margin-top: 20px;
}
#detailsModal .submit-btn {
    text-decoration: none;
    display: inline-block;
    text-align: center;
    margin-top: 20px;
    width: auto;
    padding: 10px 20px;
}
#notificationModal hr {
    border: 0;
    border-top: 1px solid #ccc;
}
.notification-message {
    white-space: pre-wrap;
}
.notification-deadline {
    margin-top: 5px;
    color: red;
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
#toast-icon {
    margin-left: 10px;
    font-size: 16px;
    vertical-align: middle;
}
/* --- NEW STYLES FOR SCHOLARSHIP TABS --- */
.scholarship-tabs-container {
    overflow: hidden;
    border: 1px solid #ccc;
    background-color: #f1f1f1;
    border-radius: 8px 8px 0 0;
    display: flex;
    flex-wrap: wrap;
}

.scholarship-tab-link {
    background-color: inherit;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 14px 16px;
    transition: 0.3s;
    font-size: 13px;
    font-weight: 500;
    color: #555;
    border-bottom: 3px solid transparent;
}

.scholarship-tab-link:hover {
    background-color: #ddd;
}

.scholarship-tab-link.active {
    background-color: #fff;
    font-weight: bold;
    color: #090549;
    border-bottom: 3px solid #090549;
}

.scholarship-tab-content {
    padding: 20px;
    border: 1px solid #ccc;
    border-top: none;
    background-color: #fff;
    border-radius: 0 0 8px 8px;
    animation: fadeIn 0.5s;
}

.scholarship-tab-content h3 {
    margin-top: 0;
    color: #090549;
}

.scholarship-tab-content h4 {
    margin-top: 15px;
    margin-bottom: 5px;
    color: #333;
}

.scholarship-tab-content hr {
    margin: 20px 0;
    border: 0;
    border-top: 1px solid #eee;
}

.scholarship-tab-content .scholarship-actions a.btn {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.scholarship-tab-content .scholarship-actions .btn-primary {
    gap: 8px;
}

@keyframes fadeIn {
    from {opacity: 0;}
    to {opacity: 1;}
}

/* START: Styles for File Input Clear Button */
.file-input-wrapper {
    position: relative;
    width: 100%;
}

.file-input-wrapper input.file-field {
    width: 100%;
    padding-right: 35px; /* Make space for the clear button */
    box-sizing: border-box;
}

.file-input-clear-button {
    display: none; /* Hidden by default */
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    cursor: pointer;
    color: white;
    background-color: #f44336;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    text-align: center;
    line-height: 20px;
    z-index: 2; /* Ensure it's above the input field */
}
/* END: Styles for File Input Clear Button */
</style>
</head>
<body>
    <div id="toast-message">
        <span id="toast-text"></span>
        <i id="toast-icon"></i>
    </div>
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

        
        <?php if (isset($_SESSION['spes_application_submitted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('SPES application submitted successfully!', 'success');
        });
        </script>
        <?php unset($_SESSION['spes_application_submitted']); endif; ?>



        <div class="main-content">
            <div id="home-page" class="page active">
                <div class="welcome-screen">
                    <h1 class="main-title">Welcome to PESO MIS SAN JULIAN</h1>
                    <p class="description">
                        Connecting Students and Out-of-School Youth in San Julian to Life-Changing Opportunities. Supporting Education,  <br>Building Careers, and Shaping Tomorrow Through Scholarships and SPES Programs.</p>
                    </div>

                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-history"></i></div>
                        <div class="box-title">Applications History</div>
                        <div class="box-value" data-target="<?php echo $totalApplications; ?>">0</div>
                        <div class="box-description">You have <?php echo $totalApplications; ?> application(s) in your history</div>
                        <button class="get-started" onclick="showPage('history-page')">Browse</button>
                    </div>
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="box-title">Total Scholarships</div>
                        <div class="box-value" data-target="<?php echo $totalScholarships; ?>">0</div>
                        <div class="box-description"><?php echo $totalScholarships; ?> scholarship programs available for application</div>
                        <button class="get-started" onclick="showPage('scholarships-page')">Browse</button>
                    </div>
                </div>
            </div>

            <div id="scholarships-page" class="page">
                <div class="scholarship-list">
                    <h2>Available Scholarships</h2>
                    <p>Browse and apply for available scholarship programs by selecting a tab below.</p>

                    <?php if (count($scholarships) > 0): ?>
                        <div class="scholarship-tabs-container">
                            <?php foreach ($scholarships as $index => $scholarship): ?>
                                <button 
                                    class="scholarship-tab-link <?php if ($index === 0) echo 'active'; ?>" 
                                    onclick="openScholarshipTab(event, '<?php echo $scholarship['scholarship_id']; ?>')">
                                    <?php echo htmlspecialchars($scholarship['title']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($scholarships as $index => $scholarship): 
                            // Calculate remaining slots
                            $remainingSlots = $scholarship['number_of_slots'] - $scholarship['total_applicants'];

                            // --- Button Logic ---
                            $hasPendingOrApprovedForThis = false;
                            $applicationStatusForThis = '';
                            $checkSql = "SELECT status FROM applications WHERE user_id = ? AND scholarship_id = ? AND status IN ('pending', 'approved') ORDER BY created_at DESC LIMIT 1";
                            $checkStmt = $conn->prepare($checkSql);
                            $checkStmt->bind_param("ii", $userId, $scholarship['scholarship_id']);
                            $checkStmt->execute();
                            $checkResult = $checkStmt->get_result();
                            if ($checkResult->num_rows > 0) {
                                $app = $checkResult->fetch_assoc();
                                $applicationStatusForThis = $app['status'];
                                $hasPendingOrApprovedForThis = true;
                            }
                            $isDisabled = false;
                            $buttonText = 'Apply Now';
                            if ($isApprovedForAnyScholarship) {
                                $isDisabled = true;
                                $buttonText = ($applicationStatusForThis === 'approved') ? 'Approved' : 'Cannot Apply Anymore';
                            } elseif ($hasPendingOrApprovedForThis) {
                                $isDisabled = true;
                                $buttonText = 'Pending';
                            } elseif ($remainingSlots <= 0) {
                                $isDisabled = true;
                                $buttonText = 'Fully Booked';
                            }
                        ?>
                            <div id="scholarship-content-<?php echo $scholarship['scholarship_id']; ?>" class="scholarship-tab-content" style="<?php if ($index !== 0) echo 'display:none;'; ?>">
                                <h3><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($scholarship['description']); ?></p>
                                <p><strong>Slots:</strong> <?php echo max(0, $remainingSlots); ?> of <?php echo htmlspecialchars($scholarship['number_of_slots']); ?> remaining</p>
                                <hr>
                                <h4>Requirements:</h4>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['requirements'])); ?></p>
                                <h4>Benefits:</h4>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['benefits'])); ?></p>
                                <h4>Eligibility Criteria:</h4>
                                <p><?php echo nl2br(htmlspecialchars($scholarship['eligibility'])); ?></p>
                                
                                <div class="scholarship-actions">
                                    <button class="btn btn-primary" <?php echo $isDisabled ? 'disabled' : ''; ?> 
                                        onclick="showApplicationForm('<?php echo htmlspecialchars($scholarship['title']); ?>', '<?php echo $scholarship['scholarship_id']; ?>')">
                                        <i class="fas fa-pen-alt"></i> <?php echo $buttonText; ?>
                                    </button>
                                    <a href="../../../../download_assets/SCHOLARSHIP-FORM.docx" download class="btn btn-outline">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>There are currently no active scholarship programs. Please check back later.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="history-page" class="page">
                <div class="application-history">
                    <h2 class="history-h2">Application History</h2>
                    <p class="history-p">Review your previous scholarship applications</p>

                    <div class="table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Program Name</th>
                                <th>Type</th>
                                <th>Date Applied</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // --- START: MODIFIED QUERY TO FETCH BOTH SCHOLARSHIP AND SPES HISTORY ---
                        $combinedHistorySql = "
                            (SELECT
                                a.application_id AS id,
                                s.title AS program_name,
                                'Scholarship' AS application_type,
                                a.created_at AS date_applied,
                                a.status AS status,
                                a.rejection_message AS rejection_message
                            FROM applications a
                            JOIN scholarships s ON a.scholarship_id = s.scholarship_id
                            WHERE a.user_id = ?)
                            
                            UNION ALL
                            
                            (SELECT
                                sa.spes_application_id AS id,
                                'SPES Application' AS program_name,
                                'SPES' AS application_type,
                                sa.created_at AS date_applied,
                                sa.status AS status,
                                NULL AS rejection_message -- Add a NULL column to match the structure
                            FROM spes_applications sa
                            WHERE sa.user_id = ?)
                            
                            ORDER BY date_applied DESC
                        ";

                        $applicationsStmt = $conn->prepare($combinedHistorySql);
                        // Bind the user ID twice, once for each part of the UNION query
                        $applicationsStmt->bind_param("ii", $userId, $userId);
                        $applicationsStmt->execute();
                        $applicationsResult = $applicationsStmt->get_result();
                        // --- END: MODIFIED QUERY ---
                        
                        if ($applicationsResult->num_rows > 0):
                            while ($application = $applicationsResult->fetch_assoc()):
                                // Determine status class for styling
                                $statusClass = 'status-' . strtolower(htmlspecialchars($application['status']));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($application['id']); ?></td>
                                <td><?php echo htmlspecialchars($application['program_name']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($application['application_type']); ?></strong>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($application['date_applied'])); ?></td>
                                <td>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($application['application_type'] === 'Scholarship' && $application['status'] === 'rejected' && !empty($application['rejection_message'])): ?>
                                        <button class="btn btn-danger" onclick='showRejectionMessageModal(<?php echo json_encode(htmlspecialchars($application["rejection_message"])); ?>)'>See Why...</button>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr class="no-history-row">
                                <td colspan="6">No application history found.</td>
                            </tr>
                        <?php
                        endif;
                        ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div id="spes-page" class="page">
                <h3>SPECIAL PROGRAM FOR EMPLOYMENT OF STUDENTS AND OUT-OF-SCHOOL YOUTH (SPESOS)</h3>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-file-contract"></i></div>
                        <div class="box-title">Employment Contract Form</div>
                        <div class="box-description">Download your employment contract for SPES.</div>
                        <button class="get-started" onclick="showPage('spes-employment-contract-page')">Open Form</button>
                    </div>
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="box-title">Application Form</div>
                        <div class="box-description">Apply for the SPES program here.</div>
                        
                        <?php 
                        // UPDATED LOGIC: Determine button text and disabled state
                        $spesButtonText = 'Open Form';
                        $isSpesDisabled = false;

                        if ($hasBeenApprovedForSpes) {
                            $isSpesDisabled = true;
                            $spesButtonText = 'Already Availed';
                        } elseif (!$isSpesActive) {
                            $isSpesDisabled = true;
                            $spesButtonText = 'Application Closed';
                        } elseif (!is_null($spesApplicationStatus)) {
                            $isSpesDisabled = true;
                            $spesButtonText = 'Application is ' . ucfirst($spesApplicationStatus);
                        }
                        ?>
                        
                        <button class="get-started" 
                                <?php echo $isSpesDisabled ? 'disabled' : ''; ?> 
                                onclick="showPage('spes-application-form-page')">
                            <?php echo $spesButtonText; ?>
                        </button>
                    </div>
                    <div class="box">
                        <div class="box-icon"><i class="fas fa-scroll"></i></div>
                        <div class="box-title">Oath of Undertaking Form</div>
                        <div class="box-description">Complete your Oath of Undertaking for SPES.</div>
                        <button class="get-started" onclick="showPage('spes-oath-of-undertaking-page')">Open Form</button>
                    </div>
                </div>
            </div>

            <div id="spes-employment-contract-page" class="page">
                <div class="form-container-application">
                    <h2>Employment Contract Form</h2>
                    <img src="<?php echo htmlspecialchars($spes_form_files['employment_contract']['image']); ?>" alt="Employment contract image" class="image">
                    <br>
                    <a href="<?php echo htmlspecialchars($spes_form_files['employment_contract']['doc']); ?>" download class="submit-btn">
                        Download Employment Contract
                    </a>
                    <br>
                    <button class="back-btn" onclick="showPage('spes-page')">Back to SPESOS</button>
                </div>
            </div>

            <div id="spes-oath-of-undertaking-page" class="page">
                <div class="form-container-application">
                    <h2>Oath of Undertaking Form</h2>
                    <img src="<?php echo htmlspecialchars($spes_form_files['oath_undertaking']['image']); ?>" alt="Oath of Undertaking image" class="image">
                    <br>
                    <a href="<?php echo htmlspecialchars($spes_form_files['oath_undertaking']['doc']); ?>" download class="submit-btn">
                        Download Oath-of-Undertaking Form
                    </a>
                    <br>
                    <button class="back-btn" onclick="showPage('spes-page')">Back to SPESOS</button>
                </div>
            </div>

            <div id="spes-application-form-page" class="page">
            <div class="form-container-application">
                <img src="../../../../images/Peso_logo1.gif"  alt="SPES_Logo1" id="spes-form-logo-1">
                <img src="../../../../images/PESO_Logo.png"  alt="PESO_Logo" id="spes-form-logo-2">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" id="spes-form-logo-bg-1">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" id="spes-form-logo-bg-2">
                <img src="../../../../images/SPES_Logo.png"  alt="SPES_Logo" id="spes-form-logo-bg-3">
                <button class="back-btn" onclick="showPage('spes-page')">Back to SPESOS</button>
                <a href="../../../../download_assets/SPES-FORM-2-APPLICATION-FORM-1-1.docx" download class="submit-btn">
                    Download SPES Application Form
                </a>
                <p class="title-description-p">REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF LABOR AND EMPLOYMENT<br>Regional Office No. VIII<br>PUBLIC EMPLOYMENT SERVICE OFFICE<br>SAN JULIAN, EASTERN SAMAR<br>City/Municipality/Province<br>SPECIAL PROGRAM FOR EMPLOYMENT OF STUDENTS (SPES)<br>(RA 7323, as amended by RAs 9547 and 10917)
                </p>
                <h2 id="spes-application-form-title">Application Form</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <h4>Personal Information</h4>
                    <div class="flex-container">
                        <div class="flex-item">
                            <label class="label-application">Surname</label>
                            <input type="text" name="surname" class="input-field" required value="<?php echo htmlspecialchars($user['Lname']); ?>">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">First Name</label>
                            <input type="text" name="firstname" class="input-field" required value="<?php echo htmlspecialchars($user['Fname']); ?>">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Middle Name</label>
                            <input type="text" name="middlename" class="input-field" value="<?php echo htmlspecialchars($user['Mname']); ?>">
                        </div>
                    </div>
                    <label class="label-application">GSIS Beneficiary/Relationship</label>
                    <input type="text" name="gsis_beneficiary" class="input-field">
                    
                    <label class="label-application">Upload ID (Front, Back, etc.)</label>
                    <p class="form-helper-text">Select Valid ID front and back.</p>
                    <div class="file-input-wrapper">
                        <input type="file" id="spes_id_images" name="id_images[]" class="input-field file-field" accept="image/*" multiple>
                        <span class="file-input-clear-button" id="clear-spes-id-images">
                            <i class="fas fa-times"></i>
                        </span>
                    </div>
                    <div class="flex-container">
                        <div class="flex-item">
                            <label class="label-application">Date of Birth</label>
                            <input type="date" name="dob" class="input-field" required value="<?php echo htmlspecialchars($user['Birthdate']); ?>">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Place of Birth</label>
                            <input type="text" name="place_of_birth" class="input-field">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Citizenship</label>
                            <input type="text" name="citizenship" class="input-field">
                        </div>
                    </div>
                    <div class="flex-container">
                        <div class="flex-item">
                            <label class="label-application">Contact Details/Cellphone No.</label>
                            <input type="text" name="contact" class="input-field" value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Email Address</label>
                            <input type="email" name="email" class="input-field" value="<?php echo htmlspecialchars($user['Email']); ?>">
                        </div>
                    </div>
                    <label class="label-application">Social Media Account (Facebook, Twitter, Instagram, etc.)</label>
                    <input type="text" name="social_media" class="input-field">

                    <label class="label-application">Civil Status</label>
                    <div>
                        <label><input type="radio" name="civil_status" value="Single" required> Single</label>
                        <label><input type="radio" name="civil_status" value="Married"> Married</label>
                        <label><input type="radio" name="civil_status" value="Widow/er"> Widow/er</label>
                        <label><input type="radio" name="civil_status" value="Separated"> Separated</label>
                    </div>

                    <label class="label-application">Sex</label>
                        <select name="sex" class="input-field" required>
                            <option value="">Select gender</option>
                            <option value="male" <?php if(strtolower($user['Gender']) == 'male') echo 'selected'; ?>>Male</option>
                            <option value="female" <?php if(strtolower($user['Gender']) == 'female') echo 'selected'; ?>>Female</option>
                            <option value="other" <?php if(strtolower($user['Gender']) == 'other') echo 'selected'; ?>>Other</option>
                            <option value="prefer-not-to-say" <?php if(strtolower($user['Gender']) == 'prefer-not-to-say') echo 'selected'; ?>>Prefer not to say</option>
                        </select>

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
                        <label><input type="checkbox" name="parent_status_indigenous"> Indigenous People</label>
                        <label><input type="checkbox" name="parent_status_displaced_local"> Displaced Worker (Local)</label>
                        <label><input type="checkbox" name="parent_status_displaced_ofw"> Displaced Worker (OFW)</label>
                    </div>

                    <label class="label-application">Present Address</label>
                    <input type="text" name="present_address" class="input-field">
                    <label class="label-application">Permanent Address</label>
                    <input type="text" name="permanent_address" class="input-field">

                    <h4>Parental Information</h4>
                    <div class="flex-container">
                        <div class="flex-item">
                            <label class="label-application">Father’s Name / Contact No.</label>
                            <input type="text" name="father_name_contact" class="input-field">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Mother’s Maiden Name / Contact No.</label>
                            <input type="text" name="mother_name_contact" class="input-field">
                        </div>
                    </div>
                    <div class="flex-container">
                        <div class="flex-item">
                            <label class="label-application">Father’s Occupation</label>
                            <input type="text" name="father_occupation" class="input-field">
                        </div>
                        <div class="flex-item">
                            <label class="label-application">Mother’s Occupation</label>
                            <input type="text" name="mother_occupation" class="input-field">
                        </div>
                    </div>

                        <h4>Educational Background</h4>
                        <div class="table-container">
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
                        </div>

                        <h4>Documentary Requirements</h4>
                        <div class="doc-req-section">
                            <label>• Photocopy of Birth Certificate or any document indicating date of birth or age (age must be 15-30)</label><br>
                            <label>• Photocopy of the latest Income Tax Return (ITR) of parents/legal guardian OR certification issued by BIR that the Parents/guardians are exempted from payment of tax OR original Certificate of Indigence OR original Certificate of Low Income issued by the Barangay/DSWD or CSWD where the applicant resides</label><br>
                            <label>• For students, any of the following, in addition to requirements no. 1 and 2:</label>
                            <div>
                                <label>• a) Photocopy of proof of average passing grade such as (1) class card or (2) Form 138 of the previous semester or year immediately preceding the application</label><br>
                                <label>• b) Original copy of Certification by the School Registrar as to passing grade immediately preceding semester/year if grades are not yet available</label>
                            </div>
                            <label>• For Out of School Youth (OSY), original copy of Certification as OSY issued by DSWD/CSWD or the authorized Barangay Official where the OSY resides, in addition to requirements no. 1 and 2.</label>
                        </div>

                        <div class="form-group">
                            <label class="label-application" for="spes_documents">Upload Compiled Documents Here</label>
                            <p class="form-helper-text">Please compile all required documents (e.g., Birth Certificate, ITR/Certificate of Indigence, Grades) into a single PDF or DOCX file.</p>
                            <div class="file-input-wrapper">
                                <input type="file" id="spes_documents" name="spes_documents" class="input-field file-field" accept=".pdf,.doc,.docx" required>
                                <span class="file-input-clear-button" id="clear-spes-documents">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                        </div>
                        <h4>Special Skills</h4>
                        <input type="text" name="special_skills" class="input-field">

                        <h4>History of SPES Availment/Name of Establishment</h4>
                        <label><input type="checkbox" name="availment_1" value="1"> 1st Availment</label>
                        <label><input type="checkbox" name="availment_2" value="2"> 2nd Availment</label>
                        <label><input type="checkbox" name="availment_3" value="3"> 3rd Availment</label>
                        <label><input type="checkbox" name="availment_4" value="4"> 4th Availment</label>

                        <h4>Year</h4>
                        <input type="text" name="year_1" placeholder="Year 1" class="input-field spes-history-input">
                        <input type="text" name="year_2" placeholder="Year 2" class="input-field spes-history-input">
                        <input type="text" name="year_3" placeholder="Year 3" class="input-field spes-history-input">
                        <input type="text" name="year_4" placeholder="Year 4" class="input-field spes-history-input">

                        <h4>SPES ID No. (if applicable)</h4>
                        <input type="text" name="spesid_1" placeholder="ID 1" class="input-field spes-history-input">
                        <input type="text" name="spesid_2" placeholder="ID 2" class="input-field spes-history-input">
                        <input type="text" name="spesid_3" placeholder="ID 3" class="input-field spes-history-input">
                        <input type="text" name="spesid_4" placeholder="ID 4" class="input-field spes-history-input">
                        
                        <button type="submit" name="submit_spes_application" class="submit-btn">Submit Application</button>
                    </form>
                </div>
            </div>

            <div id="spes-oath-of-undertaking-page" class="page">
                <div class="form-container-application">
                    <h2>Oath of Undertaking Form</h2>
                    <img src="../../../../images/spesos-oath-of-undertaking.jpg" alt="Oath of Undertaking image" class="image">
                    <br>
                    <a href="../../../../download_assets/SPES-FORM-2-A-OATH-OF-UNDERTAKING-.docx" download class="submit-btn">
                        Download Oath-of-Undertaking Form
                    </a>
                    <br>
                    <button class="back-btn" onclick="showPage('spes-page')">Back to SPESOS</button>
                </div>
            </div>


            <div id="rejectionMessageModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeRejectionMessageModal()">&times;</span>
                    <div class="modal-header">Rejection Reason</div>
                    <div class="modal-body">
                        <p id="rejectionMessageText"></p>
                    </div>
                </div>
            </div>

            <div id="userApplicationFormModal" class="modal">
                <div class="modal-content" style="max-width:800px;text-align:left;font-size:12px;">
                    <span class="modal-close" onclick="closeUserApplicationFormModal()">&times;</span>
                    <div class="modal-header">Your Application Details</div>
                    <div class="modal-body" id="userApplicationFormBody"></div>
                </div>
            </div>

<div id="communication-page" class="page">
    <div class="concerns-layout">
        <div class="concerns-list">
            <ul>
                <li class="<?php if(!$selectedGroupId) echo 'active'; ?>">
                    <a href="user_dashboard.php#communication-page">
                       <i class="fas fa-user"></i>&nbsp; Chat with Admin
                    </a>
                </li>
                <?php foreach ($approvedGroups as $group): ?>
                    <li class="<?php if($selectedGroupId == $group['scholarship_id']) echo 'active'; ?>">
                        <a href="user_dashboard.php?chat_group=<?php echo $group['scholarship_id']; ?>#communication-page">
                            <i class="fas fa-users"></i>&nbsp; <?php echo htmlspecialchars($group['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="concerns-chat-area">
             <div class="chat-container">
                <div class="chat-header">
                    <?php echo $chatTitle; ?>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender'] === 'user' ? 'user-message' : 'admin-message'; ?>">
                                <div class="message-content">
                                    <?php if (!empty($message['message'])): ?>
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    <?php endif; ?>

                                    <?php if (!empty($message['attachment_path'])): ?>
                                        <div class="message-attachment">
                                            <a href="<?php echo htmlspecialchars($message['attachment_path']); ?>" target="_blank" download>
                                                <i class="fas fa-file-download"></i>
                                                <span><?php echo htmlspecialchars(preg_replace('/^[a-f0-9]+_/', '', basename($message['attachment_path']))); ?></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>

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
                            <p>No messages in this conversation yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$selectedGroupId): // Only show input form for personal chat ?>
                <form class="chat-input" method="POST" autocomplete="off" enctype="multipart/form-data">
                    <input type="file" name="attachment" id="chatAttachment" style="display: none;" onchange="updatePlaceholder()">
                    <button type="button" class="upload-btn" onclick="document.getElementById('chatAttachment').click();" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <textarea name="concern_message" id="concernMessage" placeholder="Type your message to admin..." rows="1"></textarea>
                    <button type="submit" name="send_concern">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
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



            <div id="application-form-page" class="page">
                <div class="form-container-application">
                    <button class="back-btn" onclick="showScholarshipsPage()">Back to Scholarships</button>
                    <h2 id="application-form-title">SCHOLARSHIP FORM</h2>
                     <form enctype="multipart/form-data" method="POST">
                        <input type="hidden" name="scholarship_id" id="scholarship_id_field" value="">
                        <p class="form-section-header">Section 1. Student Applicant’s Information.</p>
                        <p class="form-section-header">1. Personal Information</p>
                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="lname">Last Name</label>
                                <input type="text" id="lname" name="lname" class="input-field" required value="<?php echo htmlspecialchars($user['Lname']); ?>" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="fname">First Name</label>
                                <input type="text" id="fname" name="fname" class="input-field" required value="<?php echo htmlspecialchars($user['Fname']); ?>" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="mname">Middle Name</label>
                                <input type="text" id="mname" name="mname" class="input-field" value="<?php echo htmlspecialchars($user['Mname']); ?>" />
                            </div>
                        </div>
                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="gender">Gender</label>
                                    <select name="gender" class="input-field" required>
                                        <option value="">Select gender</option>
                                        <option value="male" <?php if(strtolower($user['Gender']) == 'male') echo 'selected'; ?>>Male</option>
                                        <option value="female" <?php if(strtolower($user['Gender']) == 'female') echo 'selected'; ?>>Female</option>
                                        <option value="other" <?php if(strtolower($user['Gender']) == 'other') echo 'selected'; ?>>Other</option>
                                        <option value="prefer-not-to-say" <?php if(strtolower($user['Gender']) == 'prefer-not-to-say') echo 'selected'; ?>>Prefer not to say</option>
                                    </select>
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="civil_status">Civil Status</label>
                                <select id="civil_status" name="civil_status" class="input-field" required>
                                    <option value="">--Select--</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widow/er">Widow/er</option>
                                    <option value="Separated">Separated</option>
                                </select>
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="dob">Date of Birth</label>
                                <input type="date" id="dob" name="dob" class="input-field" required value="<?php echo htmlspecialchars($user['Birthdate']); ?>" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="pob">Place of Birth</label>
                                <input type="text" id="pob" name="pob" class="input-field" required />
                            </div>
                        </div>
                        <label class="label-application" for="address">Home Address</label>
                        <input type="text" id="address" name="address" class="input-field" required value="<?php echo htmlspecialchars($user['Address']); ?>" />

                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="contact">Contact Number</label>
                                <input type="text" id="contact" name="contact" class="input-field" required value="<?php echo htmlspecialchars($user['contact_number']); ?>" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="facebook">Facebook Account</label>
                                <input type="text" id="facebook" name="facebook" class="input-field" />
                            </div>
                        </div>

                        <p class="form-section-header top-margin">2. Family Background</p>
                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="mother_name">Mother’s Name (Last, First, Middle)</label>
                                <input type="text" id="mother_name" name="mother_name" class="input-field" required />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="mother_occupation">Mother’s Occupation</label>
                                <input type="text" id="mother_occupation" name="mother_occupation" class="input-field" />
                            </div>
                        </div>
                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="father_name">Father’s Name (Last, First, Middle)</label>
                                <input type="text" id="father_name" name="father_name" class="input-field" required />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="father_occupation">Father’s Occupation</label>
                                <input type="text" id="father_occupation" name="father_occupation" class="input-field" />
                            </div>
                        </div>
                        <div class="flex-container">
                            <div class="flex-item">
                                <label class="label-application" for="family_income">Monthly Family Income (Gross Amount)</label>
                                <input type="number" id="family_income" name="family_income" class="input-field" required />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="dependents">Number of Dependents in the Family</label>
                                <input type="number" id="dependents" name="dependents" class="input-field" required />
                            </div>
                        </div>

                        <p class="form-section-header top-margin">3. Educational Background</p>
                        <div class="table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Name of School</th>
                                    <th>Honors Received</th>
                                    <th>Date Graduated/Current Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Elementary</td>
                                    <td><input type="text" name="elem_school" class="input-field"></td>
                                    <td><input type="text" name="elem_honors" class="input-field"></td>
                                    <td><input type="text" name="elem_grad" class="input-field"></td>
                                </tr>
                                <tr>
                                    <td>High School</td>
                                    <td><input type="text" name="hs_school" class="input-field"></td>
                                    <td><input type="text" name="hs_honors" class="input-field"></td>
                                    <td><input type="text" name="hs_grad" class="input-field"></td>
                                </tr>
                                <tr>
                                    <td>Vocational</td>
                                    <td><input type="text" name="voc_school" class="input-field"></td>
                                    <td><input type="text" name="voc_honors" class="input-field"></td>
                                    <td><input type="text" name="voc_grad" class="input-field"></td>
                                </tr>
                            </tbody>
                        </table>
                        </div>

                        <p class="form-section-header top-margin">3-A. College Background</p>
                        <div class="flex-container">
                            <div style="flex:2;">
                                <label class="label-application" for="college_school">Name of School</label>
                                <input type="text" id="college_school" name="college_school" class="input-field" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="college_course">Course & Year</label>
                                <input type="text" id="college_course" name="college_course" class="input-field" />
                            </div>
                            <div class="flex-item">
                                <label class="label-application" for="college_average">Average from Previous Semester</label>
                                <input type="text" id="college_average" name="college_average" class="input-field" />
                            </div>
                        </div>
                        <label class="label-application" for="college_awards" style="margin-top:10px;">Awards and Recognitions</label>
                        <textarea id="college_awards" name="college_awards" class="input-field textarea-field" rows="2"></textarea>

                        <div class="form-group">
                            <label class="label-application" for="supporting_documents">Upload Supporting Documents (Documents Requirements.)</label>
                            <p class="form-helper-text">Please compile your documents (e.g., COG, COI) into PDF or DOCX format before uploading. You can paste images into a Word document and save it as a PDF.</p>
                            
                            <p class="form-helper-text" style="color: #c0392b; font-weight: bold;">
                                Important: Please rename your file to your full name (e.g., LastName_FirstName_MI.pdf) for easy identification.
                            </p>

                            <div class="file-input-wrapper">
                                <input type="file" id="supporting_documents" name="supporting_documents[]" class="input-field file-field" multiple accept=".pdf,.doc,.docx">
                                <span class="file-input-clear-button" id="clear-supporting-documents">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                        </div>
                        
                       <button type="submit" name="submit_application" class="submit-btn">Submit Application</button>
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

            <a href="../../../../download_assets/SCHOLARSHIP-FORM.docx" download class="submit-btn">
                Download Application Form
            </a>
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
                                <hr />
                            <?php endif; ?>
                            <h4><?php echo $notificationDate; ?></h4>
                            <?php $currentDate = $notificationDate;?>
                        <?php endif; ?>
                        
                        <li>
                            <strong><?php echo date('h:i A', strtotime($notification['created_at'])); ?>:</strong>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <?php if (!empty($notification['deadline'])): ?>
                                <div class="notification-deadline">
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
            page.classList.remove('active');
        });

        const newUrl = new URL(window.location);
        newUrl.hash = pageId;
        
        const params = newUrl.searchParams;
        if (pageId !== 'communication-page') {
             params.delete('chat_group');
        }
        
        window.history.pushState({}, '', newUrl);

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
        fetch('mark_notification_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateNotificationDot();
                }
            })
            .catch(error => console.error('Error marking notifications as read:', error));
    }

    function closeNotificationModal() {
        document.getElementById('notificationModal').style.display = "none";
    }

    function updateNotificationDot() {
        fetch('get_unread_count_notifcation.php')
            .then(response => response.json())
            .then(data => {
                const notificationBadge = document.getElementById('notificationBadge');
                if (data.status === 'success' && data.unread_count > 0) {
                    notificationBadge.style.display = 'flex';
                    notificationBadge.textContent = data.unread_count;
                } else {
                    notificationBadge.style.display = 'none';
                    notificationBadge.textContent = '';
                }
            })
            .catch((error) => {
                console.error('Error fetching unread count:', error);
                document.getElementById('notificationBadge').style.display = 'none';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateNotificationDot(); 
        setInterval(updateNotificationDot, 15000); 

        const hash = window.location.hash.substring(1);
        const pageId = hash || 'home-page';

        if (document.getElementById(pageId)) {
            showPage(pageId);
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

        // Number Animation Logic
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

        // START: Logic for file input clear buttons
        function setupFileInputClear(inputId, clearButtonId) {
            const fileInput = document.getElementById(inputId);
            const clearButton = document.getElementById(clearButtonId);

            if (!fileInput || !clearButton) return;

            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files.length > 0) {
                    clearButton.style.display = 'block';
                } else {
                    clearButton.style.display = 'none';
                }
            });

            clearButton.addEventListener('click', function() {
                fileInput.value = ''; // This clears the file selection
                // Create a new 'change' event to trigger the listener above
                const changeEvent = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(changeEvent);
            });
        }

        // Apply the logic to all relevant file inputs
        setupFileInputClear('spes_id_images', 'clear-spes-id-images');
        setupFileInputClear('spes_documents', 'clear-spes-documents');
        setupFileInputClear('supporting_documents', 'clear-supporting-documents');
        // END: Logic for file input clear buttons
    });

    function showApplicationForm(scholarshipTitle, scholarshipId) {
        document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
        document.getElementById('application-form-page').classList.add('active');
        document.getElementById('application-form-title').textContent = `Apply for ${scholarshipTitle}`;
        document.getElementById('scholarship_id_field').value = scholarshipId;
    }

    function showScholarshipsPage() {
        showPage('scholarships-page');
    }

    function highlightActiveNav(navId) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.getElementById(navId).classList.add('active');
    }

    let messageToDelete = null;

    function toggleMessageOptions(messageId) {
        document.querySelectorAll('.options-menu').forEach(menu => {
            if (menu.id !== `options-${messageId}`) menu.classList.remove('show');
        });
        document.getElementById(`options-${messageId}`).classList.toggle('show');
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
    
    let currentApplicationData = null;

    function showRejectionMessageModal(rejectionMessage) {
        document.getElementById('rejectionMessageText').innerHTML = rejectionMessage;
        document.getElementById('rejectionMessageModal').style.display = "block";
    }

    function closeRejectionMessageModal() {
        document.getElementById('rejectionMessageModal').style.display = "none";
    }

    function showApplicationDetails(appData) {
        currentApplicationData = appData;
        showUserApplicationFormDetails();
    }
    
    function updatePlaceholder() {
        const fileInput = document.getElementById('chatAttachment');
        const messageInput = document.getElementById('concernMessage');
        if (fileInput.files.length > 0) {
            messageInput.placeholder = "File selected: " + fileInput.files[0].name;
        } else {
            messageInput.placeholder = "Type your message to admin...";
        }
    }

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.message-options')) {
            document.querySelectorAll('.options-menu').forEach(menu => menu.classList.remove('show'));
        }
        if (!event.target.matches('.user-icon') && !event.target.matches('.fa-chevron-down') && !event.target.closest('.dropdown-menu')) {
            document.getElementById("dropdownMenu").classList.remove("show");
            document.getElementById("chevronIcon").classList.remove("open");
        }
        const uploadPopup = document.getElementById('uploadPopup');
        if (uploadPopup && !uploadPopup.contains(event.target) && !uploadPopup.closest('.upload-btn')) {
            uploadPopup.style.display = 'none';
        }
    });

    window.onclick = function(event) {
        if (event.target.id === 'rejectionMessageModal') closeRejectionMessageModal();
        if (event.target.id === 'userApplicationFormModal') closeUserApplicationFormModal();
        if (event.target.id === 'detailsModal') closeModal();
        if (event.target.id === 'notificationModal') closeNotificationModal();
        if (event.target.id === 'deleteMessageModal') closeDeleteModal();
    };

    function openScholarshipTab(evt, scholarshipId) {
    // Hide all tab content
    document.querySelectorAll('.scholarship-tab-content').forEach(tabContent => {
        tabContent.style.display = 'none';
    });

    // Deactivate all tab links
    document.querySelectorAll('.scholarship-tab-link').forEach(tabLink => {
        tabLink.classList.remove('active');
    });

    // Show the current tab content and activate the link
    document.getElementById('scholarship-content-' + scholarshipId).style.display = 'block';
    if (evt && evt.currentTarget) {
        evt.currentTarget.classList.add('active');
    }
}

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
        </script>
            
        <?php if (isset($_SESSION['application_submitted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Application submitted successfully!', 'success');
        });
        </script>
        <?php unset($_SESSION['application_submitted']); endif; ?>
    </body>
</html>