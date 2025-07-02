<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../assets/PESO Logo Assets.png">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
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
            margin-left: 0;
        }

        .navbar .right-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin-right: 20px; 
            font-size: 14px;
        }

        .forgot-password-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px);
            padding: 20px;
        }

        .forgot-password-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            margin-top: 70px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .forgot-password-header img {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
        }

        .forgot-password-header h2 {
            color: #090549;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .forgot-password-header p {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .forgot-password-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .forgot-password-form label {
            font-size: 13px;
            color:rgb(52, 61, 61);
            text-align: left;   
            font-weight: bold;

        }

        .forgot-password-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dfe6e9;
            border-radius: 14px;
            font-size: 11px;
            color:rgb(0, 0, 0);
            box-sizing: border-box;
        }

        .forgot-password-form input:focus {
            outline: none;
            border-color:rgb(0, 0, 0);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .forgot-password-form button {
            background-color: #090549;
            color: #ffffff;
            border: none;
            padding: 10px;
            border-radius: 14px;
            font-size: 11px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .forgot-password-form button:hover {
            background-color: #34495e;
        }

        .back-to-login {
            margin-top: 20px;
            font-size: 12px;
            color: #545863;
            text-decoration: none;
            display: inline-block;
        }

        .back-to-login:hover {
            text-decoration: underline;
        }

        .error-message {
        color: #f44336;
        font-size: 10px;
        text-align: left;
        width: 100%;
        padding-left: 2px;
    }

    @media (max-width: 768px) {
    .main-content {
        justify-content: center;
        align-items: center;
        display: flex;
        min-height: 100vh;
        margin-top: 0;
        padding: 0;
    }
    .forgot-password-container, .container {
        margin: 0 auto;
        width: 100%;
        max-width: 370px;
        min-width: 0;
        padding: 24px 16px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .forgot-password-form, .form {
        width: 100%;
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
    .navbar .right-nav a,
    .navbar a {
        font-size: 8px !important;
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
</head>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../images/LOGO-Bagong-Pilipinas-Logo-White.png" class="logo" alt="E-Scholar Logo">
            <img src="../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../images/final-logo-san-julian.png" alt="E-Scholar Logo" class="san-julian-logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
        <div class="right-nav">
            <a href="../../landing_page/index.html">Home</a>
        </div>
    </div>

<div class="main-content">
    <div class="content-wrapper">
    <div class="forgot-password-wrapper">
        <div class="forgot-password-container">
            <div class="forgot-password-header">
                <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
                <h2>Forgot your password</h2>
                <p>Please enter the email address you'd like your password reset information sent to.</p>
            </div>
            <form action="process_forgot_password.php" method="POST" class="forgot-password-form">
                <label for="email">Enter email address</label>
                <input type="email" id="email" name="email" placeholder="example@domain.com">
                <div id="email-error" class="error-message" style="display:none"></div>
                <button type="submit">Request reset link</button>
            </form>
            <a href="signin.php" class="back-to-login">Back To Login</a>
        </div>
    </div>
    </div>
</div>
    

    <script>
    document.querySelector('.forgot-password-form').addEventListener('submit', function(e) {
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('email-error');
        if (!emailInput.value.trim()) {
            emailError.textContent = "Email is required.";
            emailError.style.display = "block";
            e.preventDefault();
        } else {
            emailError.textContent = "";
            emailError.style.display = "none";
        }
    });
    </script>
</body>
</html>