<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: #090549;
            color: white;
        }

        .navbar .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar .logo {
            height: 40px;
        }

        .navbar .title {
            font-size: 20px;
            font-weight: bold;
        }

        .navbar .right-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .reset-password-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px); /* Adjust height excluding navbar */
            padding: 20px;
        }

        .reset-password-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .reset-password-header img {
            width: 50px;
            height: auto;
            margin-bottom: 15px;
        }

        .reset-password-header h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .reset-password-header p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .reset-password-form {
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
            font-size: 14px;
            color: #34495e;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe6e9;
            border-radius: 4px;
            font-size: 14px;
            color: #34495e;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }

        .reset-password-form button {
            background-color: #090549;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        .reset-password-form button:hover {
            background-color: #34495e;
        }

        /* Password requirements styling */
        .password-requirements {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 10px;
            text-align: left;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }

        .requirement-icon {
            margin-right: 5px;
            color: #bdc3c7;
        }

        .requirement.valid .requirement-icon {
            color: #2ecc71;
        }

        .requirement.valid {
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo-container">
            <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" class="logo" alt="E-Scholar Logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz5StjSVwowC6t9KXjZs8I1fFyoWwZtt926g&s" class="logo" alt="E-Scholar Logo">
            <div class="title">E-Scholar</div>
        </div>
        <div class="right-nav">
            <a href="../../landing_page/index.html">Home</a>
        </div>
    </div>

    <!-- Reset Password Content -->
    <div class="reset-password-wrapper">
        <div class="reset-password-container">
            <div class="reset-password-header">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz5StjSVwowC6t9KXjZs8I1fFyoWwZtt926g&s" alt="E-Scholar Logo">
                <h2>Reset Your Password</h2>
                <p>Enter your new password below</p>
            </div>
            <form id="reset-form" class="reset-password-form" method="POST" action="process_reset_pass.php" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                    <div id="password-error" class="error-message"></div>
                    
                    <div class="password-requirements">
                        <div class="requirement" id="length-req">
                            <span class="requirement-icon">•</span> At least 8 characters
                        </div>
                        <div class="requirement" id="uppercase-req">
                            <span class="requirement-icon">•</span> At least 1 uppercase letter
                        </div>
                        <div class="requirement" id="special-req">
                            <span class="requirement-icon">•</span> At least 1 special character
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter new password">
                    <div id="confirm-error" class="error-message"></div>
                    <?php
                    // Display error message directly below the field
                    if (isset($_GET['error'])) {
                        echo '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
                    }
                    ?>
                </div>

                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">

                <button type="submit">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordError = document.getElementById('password-error');
        const confirmError = document.getElementById('confirm-error');
        
        // Password requirement elements
        const lengthReq = document.getElementById('length-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const specialReq = document.getElementById('special-req');
        
        // Check password requirements as user types
        newPasswordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        
        function validatePassword() {
            const password = newPasswordInput.value;
            
            // Check for minimum length (8 characters)
            const hasLength = password.length >= 8;
            if (hasLength) {
                lengthReq.classList.add('valid');
            } else {
                lengthReq.classList.remove('valid');
            }
            
            // Check for uppercase letter
            const hasUppercase = /[A-Z]/.test(password);
            if (hasUppercase) {
                uppercaseReq.classList.add('valid');
            } else {
                uppercaseReq.classList.remove('valid');
            }
            
            // Check for special character
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            if (hasSpecial) {
                specialReq.classList.add('valid');
            } else {
                specialReq.classList.remove('valid');
            }
            
            // Check if confirm password needs to be validated
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        }
        
        function validatePasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password !== confirmPassword) {
                confirmError.textContent = "Passwords do not match";
            } else {
                confirmError.textContent = "";
            }
        }
        
        function validateForm() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isValid = true;
            
            // Check password requirements
            if (password.length < 8) {
                passwordError.textContent = "Password must be at least 8 characters";
                isValid = false;
            } else if (!/[A-Z]/.test(password)) {
                passwordError.textContent = "Password must contain at least 1 uppercase letter";
                isValid = false;
            } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                passwordError.textContent = "Password must contain at least 1 special character";
                isValid = false;
            } else {
                passwordError.textContent = "";
            }
            
            // Check if passwords match
            if (password !== confirmPassword) {
                confirmError.textContent = "Passwords do not match";
                isValid = false;
            } else {
                confirmError.textContent = "";
            }
            
            return isValid;
        }

        // Initialize visual indicators when page loads
        document.addEventListener('DOMContentLoaded', function() {
            validatePassword();
        });
    </script>
</body>
</html>