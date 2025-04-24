<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../../assets/scholar-logo.png" type="image/png">
    <title>Forgot Password</title>
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
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .forgot-password-header img {
            width: 50px;
            height: auto;
            margin-bottom: 15px;
        }

        .forgot-password-header h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .forgot-password-header p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .forgot-password-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .forgot-password-form label {
            font-size: 14px;
            color: #34495e;
            text-align: left;
            margin-bottom: 5px;
        }

        .forgot-password-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe6e9;
            border-radius: 4px;
            font-size: 14px;
            color: #34495e;
            box-sizing: border-box;
        }

        .forgot-password-form input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .forgot-password-form button {
            background-color: #090549;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .forgot-password-form button:hover {
            background-color: #34495e;
        }

        .back-to-login {
            margin-top: 20px;
            font-size: 14px;
            color: #545863;
            text-decoration: none;
            display: inline-block;
        }

        .back-to-login:hover {
            text-decoration: underline;
        }

        .language-selector {
            margin-top: 30px;
            font-size: 14px;
            color: #7f8c8d;
        }

        .language-selector select {
            font-size: 14px;
            padding: 5px;
            border: 1px solid #dfe6e9;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
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

    <!-- Forgot Password Content -->
    <div class="forgot-password-wrapper">
        <div class="forgot-password-container">
            <div class="forgot-password-header">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz5StjSVwowC6t9KXjZs8I1fFyoWwZtt926g&s" alt="E-Scholar Logo">
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