<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<script>
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

            // Automatically hide it after 3 seconds
            setTimeout(() => {
                notification.style.display = 'none';
                notification.remove();
            }, 3000);
        }

        // Function to validate the signup form
        function validateSignupForm() {
            const passwordField = document.getElementById("signup-password");
            const confirmPasswordField = document.getElementById("confirm-password");
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;

            const passwordError = document.getElementById("password-error");
            const confirmPasswordError = document.getElementById("confirm-password-error");

            // Regular expression to enforce strict password rules
            const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

            let isValid = true;

            // Validate password
            if (!passwordRegex.test(password)) {
                passwordError.style.display = 'block';
                passwordError.textContent = "Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one special character, and one number.";
                isValid = false;
            } else {
                passwordError.style.display = 'none';
            }

            // Check if passwords match
            if (password !== confirmPassword) {
                confirmPasswordError.style.display = 'block';
                confirmPasswordError.textContent = "Passwords do not match.";
                isValid = false;
            } else {
                confirmPasswordError.style.display = 'none';
            }

            return isValid; // True if the form is valid
        }

        function checkSuccessMessage() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                // Show the success notification
                showPopupNotification('Account created successfully! You can now sign in.', 'success');

                // Remove the 'success' query parameter from the URL
                const newUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, newUrl);
            }
        }

        // Call the success message function on page load
        window.onload = checkSuccessMessage;
    </script>
    
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>
    <link rel="stylesheet" href="../form/User_Landing_Page/style.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Noto+Serif+JP:wght@200..900&display=swap" rel="stylesheet">
    <style> 
    body {
        font-family: 'Montserrat', sans-serif;
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
        background-color: #ffffff;
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
        background: #090549;
        /* Fix navbar at the top */
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000; /* Ensure navbar is above other content */
        height: 50px; /* Fixed height for calculation purposes */
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
  
    .back_btn_signup {
      margin-right: 350px;
    }
  
    .back_btn_signup {
      display: flex;
      align-items: center;
      gap: 8px;
      background-color: #090549;
      color: white;
      border: none;
      padding: 7px 10px;
      font-size: 10px;
      font-weight: bold;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.2s ease;
    }
  
    /* Hover Effect */
    .back_btn_signup:hover {
        background-color: #10087c;
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
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        margin-top: 50px; /* Adjusted to account for fixed navbar height */
    }
    
    .container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
    }
    
    label {
        display: block; 
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    input, select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 10px;
        box-sizing: border-box;
    }
    
    .btn {
        background-color: #090549;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        cursor: pointer;
        width: 100%;
        font-size: 13px;
        margin-top: 10px;
    }
    
    .btn:hover {
        background-color: #0a036b;
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
        font-size: 24px;
        font-weight: 600;
    }
    
    .form-header p {
        color: #666;
        margin: 5px 0 0;
        font-size: 14px;
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
        font-size: 14px;
    }
    
    .form-toggle a {
        color: #090549;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
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

        /* Inline validation error messages */
        .error-message {
            color: #f44336;
            font-size: 12px;
            margin-top: 5px;
            display: none;
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
</head>
<body>
<div class="navbar">
        <div class="logo-container">
            <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" alt="E-Scholar Logo" class="logo">
            <img src="../../images/Municipality_of_San_Julian_Logo.png" alt="E-Scholar Logo" class="logo">
            <div class="title">E-Scholar</div>
        </div>
        <div class="right-nav">
            <a href="../../landing_page/index.html" class="home">Home</a>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <form id="signup-form" class="form active" method="POST" action="process_signup.php" onsubmit="return validateSignupForm()">
                <div class="form-header">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz5StjSVwowC6t9KXjZs8I1fFyoWwZtt926g&s" alt="E-Scholar Logo">
                    <h2>Create Your E-Scholar Account</h2>
                    <p>Fill in your information to get started</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">First Name</label>
                        <input type="text" id="name" name="fname" required placeholder="Enter your first name">
                    </div>
                    <div class="form-group">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="lname" required placeholder="Enter your last name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="middle-name">Middle Name</label>
                    <input type="text" id="middle-name" name="mname" placeholder="Enter your middle name">
                </div>

                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" min="1" max="120" inputmode="numeric" pattern="[0-9]*" placeholder="Enter your age" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer-not-to-say">Prefer not to say</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birthdate">Birthdate</label>
                        <input type="date" id="birthdate" name="birthdate" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="signup-address">Address</label>
                    <input type="text" id="signup-address" name="address" required placeholder="Enter your complete address">
                </div>

                <!-- Updated Contact Number Field -->
                <div class="form-group">
                    <label for="contact-number">Contact Number</label>
                    <input type="text" id="contact-number" name="contact" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="Enter your contact number" required>
                </div>

                <div class="form-group">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" required placeholder="Enter your Email Address">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="signup-password">Password</label>
                        <input type="password" id="signup-password" name="password" required placeholder="Create a password" pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$">
                        <div id="password-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm-password" required placeholder="Re-enter your password">
                        <div id="confirm-password-error" class="error-message"></div>
                    </div>
                </div>

                <button type="submit" class="btn">Sign Up</button>

                <div class="form-toggle">
                    <p>Already have an account? <a href="signin.php">Sign In</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>