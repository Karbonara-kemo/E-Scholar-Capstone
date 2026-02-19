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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../resource/css/forgot_password.css">
    <script src="../../resource/javascript/forgot_password.js"></script>
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
            <a href="../../index.html" class="home">
                <i class="fas fa-home"></i> Home
            </a>
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
</body>
</html>