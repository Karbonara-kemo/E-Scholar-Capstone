<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<script>
        // Function to display error messages for specific fields
        function displayError(fieldId, message) {
            const errorElement = document.getElementById(fieldId);
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }

        // Function to check for error messages in the URL
        function checkMessages() {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('error')) {
                const errorType = urlParams.get('error');

                // Display specific error messages
                if (errorType === 'invalid_password') {
                    displayError('password-error', 'Incorrect password. Please try again.');
                } else if (errorType === 'email_not_found') {
                    displayError('email-error', 'Email not found. Please check your email or sign up.');
                }
            }
        }

        // Call the function on page load
        window.onload = checkMessages;

        // Function to display a popup notification
function showPopupNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `popup-notification ${type}`;
    notification.innerHTML = `
        <i>${type === 'success' ? '✔️' : '❌'}</i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);

    // Show the notification
    notification.style.display = 'flex';

    // Automatically redirect after notification is shown
    setTimeout(() => {
        notification.style.display = 'none';
        notification.remove();
        
        // Get redirect URL from data attribute
        const redirectUrl = notification.getAttribute('data-redirect');
        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    }, 2000);
}

// Function to check for success and redirect messages in the URL
function checkSuccessMessage() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const successType = urlParams.get('success');
        const redirectTo = urlParams.get('redirect_to');
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'popup-notification success';
        notification.innerHTML = '<i>✔️</i><span>Login successful! Redirecting...</span>';
        if (redirectTo) {
            notification.setAttribute('data-redirect', redirectTo);
        }
        document.body.appendChild(notification);
        
        // Show the notification
        notification.style.display = 'flex';
        
        // Automatically redirect after notification is shown
        setTimeout(() => {
            notification.style.display = 'none';
            notification.remove();
            
            if (redirectTo) {
                window.location.href = redirectTo;
            }
        }, 2000);
        
        // Remove the query parameters from the URL
        const newUrl = window.location.href.split('?')[0];
        window.history.replaceState({}, document.title, newUrl);
    }
}

// Call both functions on page load
window.onload = function() {
    checkMessages();
    checkSuccessMessage();
};
    </script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../form/User_Landing_Page/style.css">
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Sign In</title>
</head>
<style>
    body {
        font-family: 'Roboto', sans-serif;
        overflow: hidden;
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
        height: 100vh`; /* Adjusted to account for fixed navbar height */
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        overflow: hidden;
        margin-top: 60px; /* Adjusted to account for fixed navbar height */
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

    /* New styles for the form header with image and title */
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

    /* New styles for the form toggle link */
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

     /* Styling for the popup notification */
     .popup-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #4CAF50;
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            z-index: 1000;
            font-size: 16px;
            text-align: center;
        }

        .popup-notification.success {
            background-color: #4CAF50;
        }

        .popup-notification.error {
            background-color: #f44336;
        }

        .popup-notification i {
            font-size: 24px;
        }

        /* Inline error message for input fields */
         /* Inline error message for input fields */
         .error-message {
            color: #f44336;
            font-size: 10px;
            margin-top: 5px;
        }

        /* Hide error message by default */
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
            font-weight: 600;
            font-size: 13px;
            color: #545863;
            margin-left: 20px;
        }
</style>
<body>
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
                    <img src="../../images/logo-san-julian_index.jpeg" alt="E-Scholar Logo">
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
                    <input type="password" id="signin-password" name="password" placeholder="Enter Password">
                    <div id="password-error" class="error-message hidden"></div>
                </div>
                
                <button type="submit" class="btn">Sign In</button>

                <!-- Forgot password link -->
                <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                
                <div class="form-toggle">
                    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Validate sign-in form before submit
    document.getElementById('signin-form').addEventListener('submit', function(e) {
        let valid = true;

        // Email validation
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

        // Password validation
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
</script>
</body>
</html>