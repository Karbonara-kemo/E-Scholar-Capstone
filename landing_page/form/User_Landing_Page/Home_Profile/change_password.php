<?php
// Start session
session_start();

// Include database connection
include "../../../../connect.php";

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php"); // Redirect to login page if not logged in
    exit();
}

// Get logged-in user's ID from session
$userId = $_SESSION['user_id'];

// Initialize variables for error/success messages
$error = "";
$success = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmNewPassword = $_POST['confirm_new_password'];

    // Fetch current password hash from database
    $sql = "SELECT Password FROM user WHERE Id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $hashedPassword = $user['Password'];

        // Verify current password
        if (password_verify($currentPassword, $hashedPassword)) {
            // Check if new passwords match
            if ($newPassword === $confirmNewPassword) {
                // Hash the new password
                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                // Update password in the database
                $updateSql = "UPDATE user SET Password = ? WHERE Id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("si", $newHashedPassword, $userId);

                if ($updateStmt->execute()) {
                    $success = "Password updated successfully!";
                } else {
                    $error = "Error updating password. Please try again later.";
                }
            } else {
                $error = "New password and confirm password do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="icon" type="image/x-icon" href="../../../../assets/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
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
            font-size: 20px;
            font-weight: bold;
            color: white;
        }

        .change-password-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px); /* Adjust height excluding navbar */
            padding: 20px;
        }

        .change-password-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
            text-align: center;
            margin-top: 70px; /* Adjust for navbar height */
        }

        .change-password-header img {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
        }

        .change-password-header h2 {
            color:rgb(44, 48, 53);
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .change-password-header p {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .change-password-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            text-align: left;
            gap: 5px;
        }

        .form-group label {
            font-size: 12px;
            color:rgb(34, 30, 30);
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dfe6e9;
            border-radius: 10px;
            font-size: 11px;
            color: #34495e;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color:rgb(0, 0, 0);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .error-message, .success-message {
            font-size: 12px;
            margin-top: 5px;
        }

        .error-message {
            color: #e74c3c;
        }

        .success-message {
            color: #2ecc71;
        }

        .change-password-form button {
            background-color: #090549;
            color: #ffffff;
            border: none;
            padding: 10px;
            border-radius: 14px;
            font-size: 11px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .change-password-form button:hover {
            background-color:rgb(14, 6, 116);
        }

        .back-to-profile {
            margin-top: 20px;
            font-size: 12px;
            color:#090549;
            text-decoration: none;
            display: inline-block;
        }

        .back-to-profile:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo-container">
            <img src="../../../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../../../images/PESO_Logo.png" alt="PESO Logo" class="logo">                
            <img src="../../../../images/final-logo-san-julian.png" class="san-julian-logo" alt="E-Scholar Logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
    </div>

    <!-- Change Password Content -->
    <div class="change-password-wrapper">
        <div class="change-password-container">
            <div class="change-password-header">
            <img src="../../../../images/final-logo-san-julian.png" alt="E-Scholar Logo">
                <h2>Change Your Password</h2>
                <p>Keep your account secure by updating your password</p>
            </div>
            <form method="POST" action="" class="change-password-form">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required placeholder="Re-enter new password">
                </div>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <button type="submit">Change Password</button>
            </form>
            <a href="user_profile.php" class="back-to-profile">Back To Profile</a>
        </div>
    </div>
</body>
</html>