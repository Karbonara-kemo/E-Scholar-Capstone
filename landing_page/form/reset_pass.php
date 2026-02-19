<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../assets/PESO Logo Assets.png">
    <title>Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet"> 
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../resource/css/reset_pass.css">
</head>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../images/final-logo-san-julian.png" class="san-julian-logo" alt="E-Scholar Logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
        <div class="right-nav">
            <a href="../../index.html">
                <i class="fas fa-home"></i> Home
            </a>
        </div>
    </div>

    <div class="reset-password-wrapper">
        <div class="reset-password-container">
            <div class="reset-password-header">
                <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
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

        const lengthReq = document.getElementById('length-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const specialReq = document.getElementById('special-req');
        
        newPasswordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        
        function validatePassword() {
            const password = newPasswordInput.value;
            
            const hasLength = password.length >= 8;
            if (hasLength) {
                lengthReq.classList.add('valid');
            } else {
                lengthReq.classList.remove('valid');
            }

            const hasUppercase = /[A-Z]/.test(password);
            if (hasUppercase) {
                uppercaseReq.classList.add('valid');
            } else {
                uppercaseReq.classList.remove('valid');
            }

            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            if (hasSpecial) {
                specialReq.classList.add('valid');
            } else {
                specialReq.classList.remove('valid');
            }

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
            
            if (password !== confirmPassword) {
                confirmError.textContent = "Passwords do not match";
                isValid = false;
            } else {
                confirmError.textContent = "";
            }
            
            return isValid;
        }

        document.addEventListener('DOMContentLoaded', function() {
            validatePassword();
        });
    </script>
</body>
</html>