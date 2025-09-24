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
    <title>Sign In</title>
</head>
<style>
    body {
        font-family: 'Roboto', sans-serif;
    }

    .form_container {
        margin-top: 30px;
        height: fit-content;
        width: fit-content;
        flex-direction: column;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 50px 40px 20px 40px;
        background-color: #f4f4f4;
        gap: 15px;
        box-shadow: 0px 106px 42px rgba(0, 0, 0, 0.01),
        0px 59px 36px rgba(0, 0, 0, 0.05), 0px 26px 26px rgba(0, 0, 0, 0.09),
        0px 7px 15px rgba(0, 0, 0, 0.1), 0px 0px 0px rgba(0, 0, 0, 0.1);
        border-radius: 11px;
        font-family: "Inter", sans-serif;
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
        color: white;
        margin-left: 0;
    }

    .navbar a {
    color: white;
    text-decoration: none;
    margin: 0 15px;
    font-size: 14px;
    position: relative;
    transition: color 0.2s;
}

.navbar a::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -3px;
    width: 0;
    height: 2px;
    background: #fff;
    transition: width 0.3s cubic-bezier(.4,0,.2,1);
}

.navbar a:hover::after {
    width: 100%;
}
    
    .title_container {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    
    .title {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 700;
      color: #212121;
    }
    
    .subtitle {
      font-size: 0.725rem;
      max-width: 80%;
      text-align: center;
      line-height: 1.1rem;
      color: #8B8E98
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

    .san-julian-logo {
        height: 58px;
        margin-right: 10px;
    }
    
    .input_container {
      width: 100%;
      height: fit-content;
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .icon {
      width: 20px;
      position: absolute;
      z-index: 99;
      left: 12px;
      bottom: 9px;
    }
    
    .input_label {
      font-size: 0.75rem;
      color: #000000;
      font-weight: 600;
    }
    
    .input_field {
      width: auto;
      height: 35px;
      padding: 0 0 0 10px;
      border-radius: 4px;
      outline: none;
      border: 1px solid #e5e5e5;
    }
    
    .input_field:focus {
      border: 1px solid transparent;
      box-shadow: 0px 0px 0px 2px #242424;
      background-color: transparent;
    }
    
    .sign-in_btn {
      width: 100%;
      height: 40px;
      border: 0;
      background: #090549;
      border-radius: 7px;
      outline: none;
      color: #ffffff;
      cursor: pointer;
    }

    .sign-in_btn:hover {
        background-color: #10087c;
    }
    
    .sign-in_ggl {
      width: 100%;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: #ffffff;
      border-radius: 7px;
      outline: none;
      color: #242424;
      border: 1px solid #e5e5e5;
      filter: drop-shadow(0px 1px 0px #efefef)
        drop-shadow(0px 1px 0.5px rgba(239, 239, 239, 0.5));
      cursor: pointer;
    }
    
    .sign-in_apl {
      width: 100%;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: #212121;
      border-radius: 7px;
      outline: none;
      color: #ffffff;
      border: 1px solid #e5e5e5;
      filter: drop-shadow(0px 1px 0px #efefef)
        drop-shadow(0px 1px 0.5px rgba(239, 239, 239, 0.5));
      cursor: pointer;
    }
    
    .separator {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 30px;
      color: #8B8E98;
    }
    
    .separator .line {
      display: block;
      width: 100%;
      height: 1px;
      border: 0;
      background-color: #e8e8e8;
    }
    
    .note {
      font-size: 0.75rem;
      color: #8B8E98;
      text-decoration: underline;
    }  
  
    .have_acc {
      text-decoration: none;
      font-size: 13px;
      color: #545863;
    }
  
    .logo_form {
        height: 100px;
        }
        
    .main-content {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        margin-top: 60px;
        box-sizing: border-box;
    }
    
    .container {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 500px;
        padding: 40px 10px;
    }
    
    .form {
        display: none;
    }
    
    .form.active {
        display: block;
    }
    
    .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
    }


    
    .form-group {
        margin-bottom: 15px;
        flex: 1;
        margin-right: 30px;
        margin-left: 30px;
    }
    
    label {
        display: block; 
        margin-bottom: 5px;
        font-weight: bold;
        font-size : 14px;
    }
    
    input, select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 10px;
        box-sizing: border-box;
        font-size : 11px;
    }
    
    .btn {
        background-color: #090549;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 7px;
        cursor: pointer;
        width: 88%;
        font-size: 10px;
        margin-top: 10px;
        margin-left: 30px;
    }
    
    .btn:hover {
        background-color:rgb(14, 7, 105);
    }
    
    .error {
        color: red;
        font-size: 14px;
        margin-top: 5px;
        display: none;
    }
    
    .success-message {
        color: #4CAF50;
        text-align: center;
        margin-top: 15px;
        font-weight: bold;
        display: none;
    }

    .form-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 25px;
        text-align: center;
    }
    
    .form-header img {
        width: 100px;
        height: auto;
        margin-bottom: 15px;
    }
    
    .form-header h2 {
        color: #090549;
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }
    
    .form-header p {
        color: #666;
        margin: 5px 0 0;
        font-size: 12px;
    }

    .form-toggle {
        text-align: center;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    
    .form-toggle p {
        margin: 0;
        color: #666;
        font-size: 12px;
    }
    
    .form-toggle a {
        color: #090549;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
        font-size : 12px;
    }
    
    .form-toggle a:hover {
        text-decoration: underline;
    }

    .error-message {
        color: #f44336;
        font-size: 10px;
        margin-top: 5px;
    }

    .error-message.hidden {
        display: none;
    }

    .forgot-password {
        display: block;
        margin-top: 10px;
        text-align: center;
        font-size: 11px;
        color: #545863;
        text-decoration: none;
        font-weight: 600;
    }

    .forgot-password:hover {
        text-decoration: underline;
    }

    .home 
    {
        text-decoration: none;
        font-size: 13px;
        color: #545863;
        margin-left: 20px;
    }

    /* --- START: STYLES FOR PASSWORD TOGGLE --- */
    .password-wrapper {
        position: relative;
        width: 100%;
    }
    
    .password-wrapper input {
        padding-right: 40px; /* Make space for icon */
    }

    .password-wrapper .fa-eye,
    .password-wrapper .fa-eye-slash {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
    }
    /* --- END: STYLES FOR PASSWORD TOGGLE --- */

    /* --- START: NEW TOAST NOTIFICATION STYLES --- */
    #toast-message {
        display: block;
        position: fixed;
        top: 0px;
        left: 50%;
        transform: translateX(-50%);
        background: #28a745;
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
    /* --- END: NEW TOAST NOTIFICATION STYLES --- */

    @media (max-width: 768px) {
        .main-content {
            justify-content: center;
        }
        .navbar {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            height: 45px;
            padding: 5px 10px;
        }
        .logo-container {
            flex-direction: row;
            align-items: center;
            margin-left: 0;
            gap: 5px;
        }
        .navbar .logo-container .logo {
            height: 33px !important;
            margin-right: 2px !important;
        }
        .logo, .san-julian-logo {
            height: 38px !important;
            margin-right: 2px !important;
        }
        .navbar .title {
            font-size: 10px !important;
            margin-left: 0 !important;
        }
        .navbar a,
        .right-nav a,
        .home {
            font-size: 10px !important;
            margin: 0 6px !important;
        }
        .right-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }
    }
</style>
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
            <a href="../../landing_page/index.html" class="home">Home</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container">
            <form id="signin-form" class="form active" method="POST" action="process_signin.php">
                <div class="form-header">
                    <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
                    <h2 class="title-h2">Welcome Back</h2>
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
    // Validate sign-in form before submit
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

    // --- START: NEW TOAST NOTIFICATION LOGIC ---
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

            // Clean the URL immediately
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
            
            // Redirect after the toast has been visible
            setTimeout(() => {
                if (redirectTo) {
                    window.location.href = redirectTo;
                }
            }, 2000); // Redirect after 2 seconds
        }
    }
    
    window.onload = function() {
        checkMessages();
        checkSuccessMessage();
    };
    // --- END: NEW TOAST NOTIFICATION LOGIC ---

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