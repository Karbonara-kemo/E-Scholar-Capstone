<?php
include '../../../../connect.php';

// Start session to access user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../signin.php");
    exit();
}

// Get admin ID from session
$admin_id = $_SESSION['user_id'];

// Fetch admin details from the database
$sql = "SELECT * FROM admin WHERE Id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $admin_name = $admin['Fname'] . " " . $admin['Lname'];
    $profile_pic = $admin['profile_pic'] ?? 'images\admin-default.png'; // Use default image if profile pic is not set
} else {
    // If admin details are not found, use default values
    $admin_name = "Admin";
    $profile_pic = 'images/admin-default.png'; // Default profile picture
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

    // Redirect to prevent form resubmission
    header("Location: admin_dashboard.php#communication-page");
    exit();
}

// Handle scholarship actions
$scholarshipAdded = false; // Flag to control the success notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_scholarship'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $requirements = $_POST['requirements'];
        $benefits = $_POST['benefits'];
        $eligibility = $_POST['eligibility'];
        $insertSql = "INSERT INTO scholarships (title, description, requirements, benefits, eligibility, status) VALUES (?, ?, ?, ?, ?, 'pending')";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sssss", $title, $description, $requirements, $benefits, $eligibility);
        if ($insertStmt->execute()) {
            $_SESSION['scholarship_added'] = true;
        }
        // Redirect to stay on scholarship page and prevent resubmission
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['delete_scholarship'])) {
        $id = $_POST['id'];
        $deleteSql = "DELETE FROM scholarships WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $_SESSION['scholarship_deleted'] = true;
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    } elseif (isset($_POST['publish_scholarship'])) {
        $id = $_POST['id'];
        $uploadSql = "UPDATE scholarships SET status = 'active' WHERE id = ?";
        $uploadStmt = $conn->prepare($uploadSql);
        $uploadStmt->bind_param("i", $id);
        $uploadStmt->execute();
        $_SESSION['scholarship_published'] = true; // <-- Add this line
        header("Location: admin_dashboard.php#scholarship-page");
        exit();
    }
}

if (isset($_POST['delete_message'])) {
    $messageId = $_POST['message_id'];

    // Delete the message and its related notifications
    $deleteMessageSql = "DELETE FROM notifications WHERE id = ?";
    $deleteMessageStmt = $conn->prepare($deleteMessageSql);
    $deleteMessageStmt->bind_param("i", $messageId);
    $deleteMessageStmt->execute();

    // Set a flag to trigger JS alert after reload
    $_SESSION['message_deleted'] = true;

    // Redirect to the communication page only (not home)
    header("Location: admin_dashboard.php#communication-page");
    exit();
}

// Fetch all scholarships
$fetchSql = "SELECT * FROM scholarships";
$fetchResult = $conn->query($fetchSql);
$scholarships = $fetchResult->fetch_all(MYSQLI_ASSOC);

// Count total scholarships for the "Listed Scholarships" box
$totalScholarships = count($scholarships);

// Fetch all messages
$messagesSql = "SELECT * FROM notifications ORDER BY created_at DESC";
$messagesResult = $conn->query($messagesSql);
$messages = $messagesResult->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="../../../../assets/favicon.ico"  />
    <!-- <link rel="icon" href="../../../../assets/scholar-logo.png" type="image/png"> -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Noto+Serif+JP:wght@200..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<style>
body {
    font-family: 'Montserrat', sans-serif;
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
    background: linear-gradient(155deg, #aa0505 9.5%,rgb(184, 153, 2) 39.5%);
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

.title {
    font-size: 20px !important;
    font-weight: bold;
    color: white;
}

.right-nav {
    display: flex;
    align-items: center;
    margin-right: 20px;
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
    margin-left: 5px;
}

.menu-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dropdown-menu {
    display: none;
    position: absolute;
    background-color: white;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 10px 10px;
    /* right: 1px; */
    top: 55px;
    z-index: 1000;
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
            width: 250px; /* Default expanded width */
            height: 100vh;
            position: fixed;
            top: 70px;
            left: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 900;
            transition: width 0.3s ease; /* Smooth transition when collapsing/expanding */
        }

        .sidebar.collapsed {
            width: 60px; /* Width when collapsed */
        }

        .nav-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden; /* Hide overflowing text */
        }

        .nav-item:hover {
            background-color: #10087c;
        }

        .nav-item.active {
            background-color: #10087c; /* Highlighted background color */
            border-left: 4px solid #ffffff; /* Left border indicator */
        }

        .nav-item.active .nav-icon {
            margin-left: -4px; /* Adjust padding to compensate for border */
        }

        .sidebar.collapsed .nav-item.active {
            background-color: #10087c;
            border-left: 4px solid #ffffff;
        }

        .sidebar.collapsed .nav-item.active .nav-icon {
            margin-left: -2px; /* Adjust for collapsed state */
        }

.nav-icon {
            margin-right: 10px;
            font-size: 14px;
            min-width: 20px; /* Ensure icon has fixed width */
            text-align: center; /* Center the icon */
        }

        .nav-text {
            color: white;
            transition: opacity 0.2s ease; /* Smooth transition for text appearance */
        }

        .sidebar.collapsed .nav-text {
            opacity: 0; /* Hide text when sidebar is collapsed */
            display: none;
        }

        .toggle-sidebar {
            background-color: transparent;
            color: white;
            border: none;
            cursor: pointer;
            padding: 15px;
            text-align: left; /* Align the arrow to the left */
            font-size: 14px;
            display: flex;
            margin-left: 10px;
            justify-content: flex-start; /* Move arrow to the left */
            align-items: center;
        }

        .toggle-sidebar:hover {
            background-color: #10087c;
        }

        .main-content.sidebar-collapsed {
            margin-left: 60px; /* Reduced margin when sidebar is collapsed */
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
    flex: 1; /* Allow boxes to grow equally */
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
    border-radius: 14px;
    cursor: pointer;
    font-size: 10px;
    margin-top: 10px; /* Added margin for spacing */
}

.view-details:hover {
    background: #10087c;
}

/* Additional styles for scholarship management */
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


.communication-form textarea {
    width: 98%;
    font-size: 12px;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #ccc;
}

.communication-form input[type="datetime-local"] {
    margin-top: 10px;
    width: 100%;
    font-size: 12px;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.communication-form button {
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

.communication-form button:hover {
    background-color: #10087c;
}

.main-title-communication {
    color: black;
    margin-top: 0;
    font-size: 25px;
    margin-bottom: 20px;
    margin-top: 20px;
}

.communication-h3 {
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

/* Message delete button styles */
.btn-delete-message {
    background-color:#f44336; /* Orange color for delete */
    color: white; /* White text color */
    font-size: 10px !important; /* Adjust text size if needed */
    padding: 8px 14px !important; /* Adjust padding */
    border: none; /* Remove border */
    border-radius: 14px !important; /* Rounded corners */
    cursor: pointer; /* Pointer cursor on hover */
    transition: background 0.3s ease, box-shadow 0.3s ease; /* Smooth transition for hover effects */
}

.btn-delete-message:hover {
    background-color:#d32f2f; /* Darker orange on hover */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow effect on hover */
}

/* Scholarship Card Layout */
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

/* Footer Buttons Below Fields */
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

/* Button Styles */

.btn-publish {
    background-color: #28A745;
    border-radius: 14px !important; /* Adjust as needed */
    font-size: 10px !important; /* Adjust text size if needed */
}

.btn-publish:hover {
    background-color: #218838;
}

/* Button Styles */
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
    padding: 5px 10px; /* Match the width with 'status-active' */
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    background-color: #D4EDDA; /* Same green color as 'status-active' */
    color: #155724;
    margin-bottom: 10px;
}

.message-footer {
    margin-top: 10px;
}

.message-footer form {
    display: inline;
}

/* Scholarship delete button styles */
.btn-delete-scholarship {
    background-color: #f44336; /* Red color for delete */
    color: white; /* White text color */
    font-size: 10px !important; /* Adjust text size if needed */
 /* Adjust padding */
    border: none; /* Remove border */
    border-radius: 14px !important;
    cursor: pointer; /* Pointer cursor on hover */
    transition: background 0.3s ease, box-shadow 0.3s ease; /* Smooth transition for hover effects */
}

.btn-delete-scholarship:hover {
    background-color: #d32f2f; /* Darker red on hover */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow effect on hover */
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
    color: #090549;
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
</style>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../../../images/Municipality_of_San_Julian_Logo.png" alt="E-Scholar Logo" class="logo">
            <div class="title">SPESOS MIS SAN JULIAN</div>
        </div>
        <div class="right-nav">
            <div class="menu-container">
            <img src="../../../../<?php echo htmlspecialchars($profile_pic); ?>" alt="Admin Icon" class="user-icon">
            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <i class="fas fa-chevron-down" style="color: white; cursor: pointer;" onclick="toggleMenu()"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <!-- <a href="admin_profile.php">Profile</a> -->
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
            <div class="nav-item" id="communication-nav" onclick="showPage('communication-page')">
                <div class="nav-icon"><i class="fas fa-envelope"></i></div>
                <div class="nav-text">Communication</div>
            </div>
            <div class="nav-item" id="total-applicants-nav" onclick="showPage('total-applicants-page')">
                <div class="nav-icon"><i class="fas fa-users"></i></div>
                <div class="nav-text">Total Applicants</div>
            </div>
            <!-- Reports Sidebar Item (no tree) -->
            <div class="nav-item" id="reports-nav" onclick="showPage('reports-page')">
                <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="nav-text">Reports</div>
            </div>
        </div>

        <?php if (isset($_SESSION['scholarship_added'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            toast.textContent = 'Added successfully';
            toast.style.display = 'block';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_added']); endif; ?>

        <?php if (isset($_SESSION['scholarship_deleted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            toast.textContent = 'Deleted successfully';
            toast.style.display = 'block';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_deleted']); endif; ?>

        <?php if (isset($_SESSION['scholarship_published'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            toast.textContent = 'Published successfully';
            toast.style.display = 'block';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['scholarship_published']); endif; ?>

        <div id="toast-message" style="
            display:none;
            position:fixed;
            top:20px;
            left:50%;
            transform:translateX(-50%);
            background:#28a745;
            color:white;
            padding:14px 28px;
            border-radius:8px;
            font-size:16px;
            z-index:2000;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
        "></div>
        <?php if (isset($_SESSION['message_deleted'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast-message');
            toast.textContent = 'Message deleted successfully';
            toast.style.display = 'block';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 2500);
        });
        </script>
        <?php unset($_SESSION['message_deleted']); endif; ?>
        <!-- Main Content -->
        <div class="main-content">

            <div id="home-page" class="page active">
                <h1 class="h1-home-welcome">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h1>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Total Applicants</div>
                        <div class="box-value">150</div>
                        <div class="box-description">All applicants in the system</div>
                        <button class="view-details">View Details</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Rejected Applicants</div>
                        <div class="box-value">30</div>
                        <div class="box-description">Applicants not meeting criteria</div>
                        <button class="view-details">View Details</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Approved Applicants</div>
                        <div class="box-value">70</div>
                        <div class="box-description">Applicants who got approved</div>
                        <button class="view-details">View Details</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Listed Scholarships</div>
                        <div class="box-value"><?php echo $totalScholarships; ?></div>
                        <div class="box-description"><?php echo $totalScholarships; ?> scholarships have been listed in the system</div>
                        <button class="view-details" onclick="showPage('scholarship-page')">View Details</button>
                    </div>
                </div>
            </div>

            <div id="total-applicants-page" class="page">
                <h1 class="h1-title-appManagement">Total Applicants</h1>
                <p class="p-description-appM">This section displays the total number of applicants in the system.</p>
                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Total Applicants</div>
                        <div class="box-value">150</div> <!-- Replace with dynamic value if needed -->
                        <div class="box-description">All applicants in the system</div>
                    </div>
                </div>
            </div>

            <div id="application-page" class="page">
                <h1 class="h1-title-appManagement">Approved Applicants</h1>
                <p class="p-description-appM">This section displays the details of approved scholarships and their applicants.</p>
                <div class="dashboard-boxes">
                    <?php foreach ($scholarships as $scholarship): ?>
                        <div class="box">
                            <div class="box-title"><?php echo htmlspecialchars($scholarship['title']); ?></div>
                            <button class="view-details" onclick="viewApplicants('<?php echo htmlspecialchars($scholarship['id']); ?>')">View Applicants</button>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($scholarships) === 0): ?>
                        <p>No scholarships found. Add scholarships to display them here.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add this after your other .page sections in .main-content -->
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

            <div id="scholarship-page" class="page">
                <h1 class="main-title-scholar">Manage Scholarships</h1>
                <!-- Add Scholarship Form -->
                <form class="scholarship-form" method="POST">
                    <h3>Add New Scholarship</h3>
                    <input type="text" name="title" placeholder="Scholarship Title" required>
                    <textarea name="description" placeholder="Scholarship Description" required></textarea>
                    <textarea name="requirements" placeholder="Requirements" required></textarea>
                    <textarea name="benefits" placeholder="Benefits" required></textarea>
                    <textarea name="eligibility" placeholder="Eligibility Criteria" required></textarea>
                    <button type="submit" name="add_scholarship">Add Scholarship</button>
                </form>
                <!-- List of Scholarships -->

                <h3>Scholarship List</h3>
                <?php foreach ($scholarships as $scholarship): ?>
                <div class="scholarship-card">
                    <h3><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                    
                    <div class="scholarship-status <?php echo $scholarship['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                        Status: <?php echo ucfirst($scholarship['status']); ?>
                    </div>
                    
                    
                    <div class="scholarship-details">
                        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($scholarship['description'])); ?></p>
                        <p><strong>Requirements:</strong> <?php echo nl2br(htmlspecialchars($scholarship['requirements'])); ?></p>
                        <p><strong>Benefits:</strong> <?php echo nl2br(htmlspecialchars($scholarship['benefits'])); ?></p>
                        <p><strong>Eligibility:</strong> <?php echo nl2br(htmlspecialchars($scholarship['eligibility'])); ?></p>
                    </div>
                    
                    <div class="scholarship-footer">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $scholarship['id']; ?>">
                            <button type="submit" name="delete_scholarship" class="btn-delete-scholarship" title="Delete" onclick="return confirm('Are you sure you want to delete this scholarship?')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                        
                        <?php if ($scholarship['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $scholarship['id']; ?>">
                            <button type="submit" name="publish_scholarship" class="btn-publish">Publish</button>
                        </form>
                        <?php else: ?>
                        <button disabled class="btn-publish" style="opacity: 0.5;">Published</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($scholarships) === 0): ?>
                <p>No scholarships found. Add a new scholarship using the form above.</p>
                <?php endif; ?>
            </div>

            
            <div id="communication-page" class="page">
                <h1 class="main-title-communication">Communication</h1>
                <!-- Form to Send a Message -->
                <form class="communication-form" method="POST">
                    <h3 class="communication-h3">Send a Message to Users</h3>
                    <textarea name="message" placeholder="Title/Subject:                 
Greeting/Opening:
Body/Message Content:
Closing/Signature:" rows="5" required></textarea>
                    <button type="submit" name="send_message">Send Message</button>
                </form>

                <!-- Display Sent Messages -->
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
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
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
        </div>
    </div>
    <script>
        function toggleMenu() {
            var menu = document.getElementById("dropdownMenu");
            menu.style.display = (menu.style.display === "block") ? "none" : "block";
        }

        window.onclick = function(event) {
            if (!event.target.matches('.user-icon') && !event.target.matches('.fa-chevron-down')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].style.display = "none";
                }
            }
        }

       // Update showPage to close the tree when navigating
        function showPage(pageId) {
            document.querySelectorAll('.page').forEach(page => {
                page.style.display = 'none';
                page.classList.remove('active');
            });
            document.getElementById(pageId).style.display = 'block';
            document.getElementById(pageId).classList.add('active');
            switch (pageId) {
                case 'home-page':
                    highlightActiveNav('home-nav');
                    break;
                case 'application-page':
                    highlightActiveNav('history-nav');
                    break;
                case 'scholarship-page':
                    highlightActiveNav('scholarships-nav');
                    break;
                case 'communication-page':
                    highlightActiveNav('communication-nav');
                    break;
                case 'total-applicants-page':
                    highlightActiveNav('total-applicants-nav');
                    break;
                case 'reports-page':
                    highlightActiveNav('reports-nav');
                    break;
            }
        }
        
        function highlightActiveNav(navId) {
            // Remove active class from all nav items first
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to the selected nav item
            document.getElementById(navId).classList.add('active');
        }
        
        // Check if there's a hash in the URL and show that page
        document.addEventListener('DOMContentLoaded', function() {
            let hash = window.location.hash.substr(1);
            if (hash) {
                let pageId = hash + '-page';
                if (document.getElementById(pageId)) {
                    showPage(pageId);
                }
            }
        });

        // Add sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleBtn = document.getElementById('toggleSidebar');
            const toggleIcon = document.getElementById('toggleIcon');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                
                // Change icon direction based on sidebar state
                if (sidebar.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            });
            
            // Rest of your initialization code
        });

        function viewApplicants(scholarshipId) {
            alert('View applicants for scholarship ID: ' + scholarshipId);
            // Replace this alert with logic to navigate to a detailed applicants page or modal
        }
    </script>
</body>
</html>