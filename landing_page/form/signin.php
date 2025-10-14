<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../form/User_Landing_Page/style.css">
    <link rel="icon" type="image/x-icon" href="../../assets/PESO Logo Assets.png">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../../resource/css/signin.css">
    <script src="../../resource/javascript/signin.js"></script>
    <title>Sign In</title>
</head>
<body>
    <div id="toast-message">
        <span id="toast-text"></span>
        <i id="toast-icon"></i>
    </div>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../images/final-logo-san-julian.png" alt="E-Scholar Logo" class="san-julian-logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
        <div class="right-nav">
            <a href="../../index.html" class="home">Home</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <form id="signin-form" class="form active" method="POST" action="process_signin.php">
                <div class="form-header">
                    <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
                    <h2 class="title-h2">Welcome</h2>
                    <p class="desc-p">Sign in to continue to your account</p>
                </div>
                
                <div class="form-group">
                    <label for="signin-email">Email</label>
                    <input type="email" id="signin-email" name="email" placeholder="Enter Email Address">
                    <div id="email-error" class="error-message hidden"></div>
                </div>
                
                <div class="form-group">
                    <label for="signin-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="signin-password" name="password" placeholder="Enter Password">
                        <i class="fas fa-eye" id="togglePassword"></i>
                    </div>
                    <div id="password-error" class="error-message hidden"></div>
                </div>
                
                <button type="submit" class="btn">Sign In</button>

                <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                
                <div class="form-toggle">
                    <p>Don't have an account? <a href="signup.php">Send Request</a></p>
                </div>
            </form>
        </div>
    </div>
    <script>
            document.getElementById('signin-form').addEventListener('submit', function(e) {
        let valid = true;
        const emailInput = document.getElementById('signin-email');
        const emailError = document.getElementById('email-error');
        if (!emailInput.value.trim()) {
            emailError.textContent = "Email is required.";
            emailError.classList.remove('hidden');
            valid = false;
        } else {
            emailError.textContent = "";
            emailError.classList.add('hidden');
        }

        const passwordInput = document.getElementById('signin-password');
        const passwordError = document.getElementById('password-error');
        if (!passwordInput.value.trim()) {
            passwordError.textContent = "Password is required.";
            passwordError.classList.remove('hidden');
            valid = false;
        } else {
            passwordError.textContent = "";
            passwordError.classList.add('hidden');
        }

        if (!valid) {
            e.preventDefault();
        }
    });

    function displayError(fieldId, message) {
        const errorElement = document.getElementById(fieldId);
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }

    function checkMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            const errorType = urlParams.get('error');

            if (errorType === 'invalid_password') {
                displayError('password-error', 'Incorrect password. Please try again.');
            } else if (errorType === 'email_not_found') {
                displayError('email-error', 'Email not found. Please check your email or sign up.');
            } else if (errorType === 'not_approved') {
                displayError('email-error', 'Your account is pending approval. You cannot login until an admin approves your request.');
            }
        }
    }

    function checkSuccessMessage() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const redirectTo = urlParams.get('redirect_to');
            
            const toast = document.getElementById('toast-message');
            const toastText = document.getElementById('toast-text');
            const toastIcon = document.getElementById('toast-icon');
            
            toastText.textContent = 'Login successful! Redirecting...';
            toastIcon.className = 'fas fa-check-circle';
            toast.style.background = '#28a745';
            
            toast.classList.add('show');

            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
            
            setTimeout(() => {
                if (redirectTo) {
                    window.location.href = redirectTo;
                }
            }, 2000);
        }
    }
    
    window.onload = function() {
        checkMessages();
        checkSuccessMessage();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#signin-password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    });
    </script>
</body>
</html>