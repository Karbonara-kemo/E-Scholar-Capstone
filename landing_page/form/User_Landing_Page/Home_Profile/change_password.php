<?php
session_start();

include "../../../../connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php");
    exit();
}

$userId = $_SESSION['user_id'];

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmNewPassword = $_POST['confirm_new_password'];

    // FIX: Changed 'Id' to 'user_id' to match your database table column.
    $sql = "SELECT Password FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $hashedPassword = $user['Password'];

        if (password_verify($currentPassword, $hashedPassword)) {
            if ($newPassword === $confirmNewPassword) {

                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                // FIX: Changed 'Id' to 'user_id' here as well.
                $updateSql = "UPDATE user SET Password = ? WHERE user_id = ?";
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
    <link rel="icon" type="image/x-icon" href="../../../../assets/PESO Logo Assets.png">
    <link rel="stylesheet" href="../../../../resource/css/change_password.css">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .password-container {
            position: relative;
        }
        /* Adjust input to prevent text from overlapping the icon */
        .password-container input {
            width: 100%;
            padding-right: 45px; /* Make space for icon */
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../../../images/PESO_Logo.png" alt="PESO Logo" class="logo">                
            <img src="../../../../images/final-logo-san-julian.png" class="san-julian-logo" alt="E-Scholar Logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
    </div>

    <div class="change-password-wrapper">
        <div class="change-password-container">
            <div class="change-password-header">
            <img src="../../../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
                <h2>Change Your Password</h2>
                <p>Keep your account secure by updating your password</p>
            </div>
            <form method="POST" action="" class="change-password-form">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="password-container">
                        <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-container">
                        <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required placeholder="Re-enter new password">
                        <i class="fas fa-eye toggle-password"></i>
                    </div>
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

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const togglePasswordIcons = document.querySelectorAll('.toggle-password');

        togglePasswordIcons.forEach(icon => {
            icon.addEventListener('click', function () {
                // Get the input field next to the icon
                const passwordInput = this.previousElementSibling;

                // Toggle the input type between 'password' and 'text'
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle the icon class between 'fa-eye' and 'fa-eye-slash'
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    });
    </script>

</body>
</html>