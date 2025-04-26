<?php
include '../../../../connect.php';

// Start session to access user data
session_start();

// Set no-cache headers for all protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../signin.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "User";

// Get user information from the database
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


$sql = "UPDATE notifications SET status = 'read' WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark notifications as read']);
}

$notificationSql = "SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationSql);
$notificationStmt->bind_param("i", $userId);
$notificationStmt->execute();
$notificationResult = $notificationStmt->get_result();
$notifications = $notificationResult->fetch_all(MYSQLI_ASSOC);

// Count unread notifications
$countUnreadSql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND status = 'unread'";
$countUnreadStmt = $conn->prepare($countUnreadSql);
$countUnreadStmt->bind_param("i", $userId);
$countUnreadStmt->execute();
$countUnreadResult = $countUnreadStmt->get_result();
$unreadCount = $countUnreadResult->fetch_assoc()['unread_count'];

echo json_encode(['status' => 'success', 'unread_count' => $unreadCount]);

// Fetch all active scholarships
$scholarshipSql = "SELECT * FROM scholarships WHERE status = 'active'";
$scholarshipResult = $conn->query($scholarshipSql);
$scholarships = $scholarshipResult->fetch_all(MYSQLI_ASSOC);

// Count total active scholarships for the Home page
$totalScholarships = count($scholarships);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Scholar</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../../../../assets/scholar-logo.png" type="image/png">
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
    background: #090549;
    /* Fix navbar at the top */
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000; /* Ensure navbar is above other content */
    height: 50px; /* Fixed height for calculation purposes */
}

.navbar .title {
    font-size: 20px;
    font-weight: bold;
    color: white;
    margin-left: 20px;
}

.navbar a {
    color: white;
    text-decoration: none;
    margin: 0 15px;
    font-size: 14px;
}

.container {
    display: flex;
    flex: 1;
    padding-top: 50px; /* Equal to navbar height */
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
            width: 60px; /* Width when collapsed */
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
            background-color: #10087c; /* Highlighted background color */
            border-left: 4px solid #ffffff; /* Left border indicator */
        }

        /* Adjust padding when active to compensate for the border */
        .nav-item.active .nav-icon {
            margin-left: -4px; 
        }

        /* Style for active item in collapsed state */
        .sidebar.collapsed .nav-item.active {
            background-color: #10087c;
            border-left: 4px solid #ffffff;
        }

        /* Ensure the icon is centered in collapsed state */
        .sidebar.collapsed .nav-item.active .nav-icon {
            /* Adjust for the border and center the icon */
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
    height: 400px; /* Fixed height for slideshow */
    overflow: hidden; /* Hide overflowing images */
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
        height: 300px; /* Smaller height on mobile */
    }
}

.main-content {
    padding: 30px;
    flex: 1;
    box-sizing: border-box;
    margin-left: 250px; /* Default margin - same as sidebar width */
    transition: margin-left 0.3s ease; /* Smooth transition */
}

.main-content.sidebar-collapsed {
    margin-left: 60px; /* Reduced margin when sidebar is collapsed */
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
    font-weight: bold;
}

.dropdown-menu {
    display: none;
    position: absolute;
    background-color: white;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 10px 10px;
    right: 10spx;
    top: 55px;
    z-index: 1000;
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
    display: none; /* Hidden by default */
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

/* New styles for notification bell */
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
    width: 15px;
    height: 15px;
    background-color: red;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 10px;
    color: white;
    font-weight: bold;
}

.user-menu-container {
    display: flex;
    align-items: center;
    gap: 10px; /* Increased gap for better spacing */
}

/* Replace the notification-badge style with this simpler dot style */
.notification-dot {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 8px;
    height: 8px;
    background-color: red;
    border-radius: 50%;
    display: none; /* Initially hidden */
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
.dashboard-boxes {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    padding: 0 20px; /* Added padding for spacing */
}
.get-started {
    background-color: #090549;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 14px;
    cursor: pointer;
    font-size: 10px;
    margin-top: 10px; /* Added margin for spacing */
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
        /* padding: 20px; */
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
    margin-top: 20px; /* Added margin for spacing */
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

</style>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" alt="E-Scholar Logo" class="logo">
            <img src="../../../../images/Municipality_of_San_Julian_Logo.png" alt="E-Scholar Logo" class="logo">
            <div class="title">E-Scholar</div>
        </div>
        <div class="right-nav">
            <div class="menu-container">
                <!-- Add notification bell icon -->
                <div class="notification-bell" onclick="openNotificationModal()">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-dot" style="display: none;"></span>
                </div>
                
                <div class="user-menu-container">
                    <img src="../../../../<?php echo htmlspecialchars($user['profile_pic'] ?? 'images/default-user.png'); ?>" alt="User Icon" class="user-icon">
                    <span class="user-name"><?php echo htmlspecialchars($user['Fname'] . " " . $user['Lname']); ?></span>
                    <i class="fas fa-chevron-down" style="color: white; cursor: pointer;" onclick="toggleMenu()"></i>
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
                <div class="nav-text">View Scholarship</div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Home Page -->
            <div id="home-page" class="page active">
                <div class="welcome-screen" style="margin-top: 100px;">
                    <h1 class="main-title">Welcome to E-Scholar</h1>
                    <p class="description">Your Pathway to Educational Support. Helping Local Scholars Thrive. Supporting Students, Building Tomorrow. <br>Empowering Futures Through Education. A Gateway to Academic Opportunities.</p>
                    <!-- <button class="get-started" onclick="showPage('scholarships-page')">Browse Scholarships</button> -->
                </div>

                <div class="dashboard-boxes">
                    <div class="box">
                        <div class="box-title">Approved Applications</div>
                        <div class="box-value">2</div>
                        <div class="box-description">You have 2 approved scholarship applications</div>
                        <!-- Added margin-top for spacing -->
                        <button class="get-started" style="margin-top: 20px;" onclick="showPage('history-page')">Browse</button>
                    </div>
                    <div class="box">
                        <div class="box-title">Total Schemes</div>
                        <div class="box-value"><?php echo $totalScholarships; ?></div>
                        <div class="box-description"><?php echo $totalScholarships; ?> scholarship programs available for application</div>
                        <!-- Added margin-top for spacing -->
                        <button class="get-started" style="margin-top: 20px;" onclick="showPage('scholarships-page')">Browse</button>
                    </div>
                </div>
            </div>
            
            <!-- Application History Page -->
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
                                <th>Action</th> <!-- New column for the View Details button -->
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
                                <td>APP-2023-002</td>
                                <td>UNIFAST Scholarship</td>
                                <td>Feb 28, 2023</td>
                                <td class="status-approved">Approved</td>
                                <td>
                                    <button class="btn btn-outline" onclick="viewDetails('APP-2023-002')">View Details</button>
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
            
            <!-- Scholarships Listing Page -->
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
                    $currentDate = null; // Track the current date
                    foreach ($notifications as $notification): 
                        $notificationDate = date('F j, Y', strtotime($notification['created_at'])); // Format the date
                    ?>
                        <?php if ($currentDate !== $notificationDate): ?>
                            <!-- Display a new header when the date changes -->
                            <?php if ($currentDate !== null): ?>
                                <hr style="border: 1px solid #ccc;" />
                            <?php endif; ?>
                            <h4><?php echo $notificationDate; ?></h4>
                            <?php $currentDate = $notificationDate; // Update the current date ?>
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
    // Toggle user menu
    function toggleMenu() {
        var menu = document.getElementById("dropdownMenu");
        menu.style.display = (menu.style.display === "block") ? "none" : "block";
    }

    function showDetails(title, requirements, benefits, eligibility) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalRequirements').innerHTML = requirements;
        document.getElementById('modalBenefits').innerHTML = benefits;
        document.getElementById('modalEligibility').innerHTML = eligibility;
        document.getElementById('detailsModal').style.display = "block";
    }
    
    // Close modal
    function closeModal() {
        document.getElementById('detailsModal').style.display = "none";
    }

    // Page navigation
    function showPage(pageId) {
    // Hide all pages
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    
    // Show the selected page
    document.getElementById(pageId).style.display = 'block';
    
    // Highlight the corresponding nav item
    switch(pageId) {
        case 'home-page':
            highlightActiveNav('home-nav');
            break;
        case 'history-page':
            highlightActiveNav('history-nav');
            break;
        case 'scholarships-page':
            highlightActiveNav('scholarships-nav');
            break;
        // Keep the current highlight when showing application form
        case 'application-form-page':
            // Don't change the highlight
            break;
    }
}

    function openNotificationModal() {
        document.getElementById('notificationModal').style.display = "block";

        // Save the current timestamp as the last checked time
        localStorage.setItem('lastCheckedNotification', new Date().toISOString());

        // Hide the red dot
        document.getElementById('notificationBadge').style.display = 'none';
    }

    function closeNotificationModal() {
        document.getElementById('notificationModal').style.display = "none";

        // Hide the red dot
        document.getElementById('notificationBadge').style.display = 'none';
    }

    function updateNotificationDot() {
        fetch('get_unread_count_notification.php')
            .then(response => response.json())
            .then(data => {
                const notificationDot = document.getElementById('notificationBadge');
                
                if (data.status === 'success') {
                    const latestNotification = data.latest_notification;
                    const lastChecked = localStorage.getItem('lastCheckedNotification') || null;

                    // Show the red dot if there is a new notification
                    if (!lastChecked || new Date(latestNotification) > new Date(lastChecked)) {
                        notificationDot.style.display = 'block';
                    } else {
                        notificationDot.style.display = 'none';
                    }
                } else {
                    console.error('Failed to fetch notification data:', data.message);
                }
            })
            .catch(error => console.error('Error fetching notification data:', error));
    }

// Call this function every 10 seconds to check for new notifications
    setInterval(updateNotificationDot, 10000);

    function receiveNotification() {
        // Simulate receiving a new notification
        document.getElementById('notificationBadge').style.display = 'block';
    }
    setTimeout(receiveNotification, 1000);

    document.addEventListener('DOMContentLoaded', function() {
    // Set up default page
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });

    document.getElementById('home-page').style.display = 'block';
    highlightActiveNav('home-nav');
    
    // Handle back button and page reload
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // Disable browser cache for this page
    window.history.pushState(null, '', window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, '', window.location.href);
    };

    // Add sidebar toggle functionality
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
});

fetch(`get_unread_count_notifications.php?timestamp=${new Date().getTime()}`)
.then(response => response.json())
.then(data => {
    const notificationBadge = document.getElementById('notificationBadge');
    if (data.status === 'success') {
        const unreadCount = data.unread_count;
        notificationBadge.style.display = unreadCount > 0 ? 'block' : 'none';
    }
})
.catch(error => console.error('Error fetching unread count:', error));

function showApplicationForm(scholarshipTitle) {
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    document.getElementById('application-form-page').style.display = 'block';
    document.getElementById('application-form-title').textContent = `Apply for ${scholarshipTitle}`;
    // No need to change highlight - scholarship nav should remain highlighted
}

function showScholarshipsPage() {
    document.querySelectorAll('.page').forEach(page => {
        page.style.display = 'none';
    });
    document.getElementById('scholarships-page').style.display = 'block';
    highlightActiveNav('scholarships-nav');
}

function highlightActiveNav(navId) {
    // Remove active class from all nav items first
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to the selected nav item
    document.getElementById(navId).classList.add('active');
}


</script>
</body>
</html>