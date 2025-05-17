<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Noto+Serif+JP:wght@200..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #090549;
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

        .title {
            font-size: 20px;
            font-weight: bold;
            color: white;
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
            min-height: calc(100vh - 70px); /* Adjust height excluding navbar */
            padding: 20px;
        }

        .forgot-password-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            margin-top: 70px; /* Adjust for navbar height */
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo-container">
            <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" class="logo" alt="E-Scholar Logo">
            <img src="../../images/Municipality_of_San_Julian_Logo.png" alt="E-Scholar Logo" class="logo" alt="E-Scholar Logo">
            <div class="title">PESO MIS SAN JULIAN</div>
        </div>
        <div class="right-nav">
            <a href="../../landing_page/index.html">Home</a>
        </div>
    </div>

    <!-- Forgot Password Content -->
    <div class="forgot-password-wrapper">
        <div class="forgot-password-container">
            <div class="forgot-password-header">
                <img src="../../assets/scholar-logo.png" alt="E-Scholar Logo">
                <h2>Forgot your password</h2>
                <p>Please enter the email address you'd like your password reset information sent to.</p>
            </div>
            <form action="process_forgot_password.php" method="POST" class="forgot-password-form">
                <label for="email">Enter email address</label>
                <input type="email" id="email" name="email" placeholder="example@domain.com" required>
                <button type="submit">Request reset link</button>
            </form>
            <a href="signin.php" class="back-to-login">Back To Login</a>
        </div>
    </div>
</body>
</html>