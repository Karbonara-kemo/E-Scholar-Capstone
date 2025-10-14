<?php
include "../../connect.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="../form/User_Landing_Page/style.css">
    <link rel="icon" type="image/x-icon" href="../../assets/PESO Logo Assets.png">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../resource/css/signup.css">
    <script src="../../resource/javascript/signup.js"></script>
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
        <form id="signup-form" class="form active" method="POST" action="process_signup.php" enctype="multipart/form-data" onsubmit="return validateSignupForm()">
            <div class="form-header">
                <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
                <h2>Create an Account</h2>
                <p>Fill in your information to get started</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">First Name</label>
                    <input type="text" id="name" name="fname" placeholder="Enter your first name">
                    <div id="fname-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="last-name">Last Name</label>
                    <input type="text" id="last-name" name="lname" placeholder="Enter your last name">
                    <div id="lname-error" class="error-message"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="middle-name">Middle Name</label>
                <input type="text" id="middle-name" name="mname" placeholder="Enter your middle name">
            </div>

            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" min="1" max="99" oninput="javascript: if (this.value.length > 2) this.value = this.value.slice(0, 2);" placeholder="Enter your age">
                <div id="age-error" class="error-message"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                        <option value="prefer-not-to-say">Prefer not to say</option>
                    </select>
                    <div id="gender-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="birthdate">Birthdate</label>
                    <input type="date" id="birthdate" name="birthdate" max="2009-12-31">
                    <div id="birthdate-error" class="error-message"></div>
                </div>
            </div>

            <div class="form-group address-group">
                <label for="barangay">Address (Barangay)</label>
                <select id="barangay">
                    <option value="" disabled selected>-- Select your Barangay --</option>
                    <option value="Bunacan">Bunacan</option>
                    <option value="Campidhan">Campidhan</option>
                    <option value="Casoroy">Casoroy</option>
                    <option value="Libas">Libas</option>
                    <option value="Lunang">Lunang</option>
                    <option value="Nena (Luna)">Nena (Luna)</option>
                    <option value="Pagbabangnan">Pagbabangnan</option>
                    <option value="Barangay No. 1 Poblacion">Barangay No. 1 Poblacion</option>
                    <option value="Barangay No. 2 Poblacion">Barangay No. 2 Poblacion</option>
                    <option value="Barangay No. 3 Poblacion">Barangay No. 3 Poblacion</option>
                    <option value="Barangay No. 4 Poblacion">Barangay No. 4 Poblacion</option>
                    <option value="Barangay No. 5 Poblacion">Barangay No. 5 Poblacion</option>
                    <option value="Barangay No. 6 Poblacion">Barangay No. 6 Poblacion</option>
                    <option value="Putong">Putong</option>
                    <option value="San Isidro">San Isidro</option>
                    <option value="San Miguel">San Miguel</option>
                </select>
                <div class="fixed-address">San Julian, Eastern Samar</div>
                <input type="hidden" id="signup-address" name="address">
                <div id="address-error" class="error-message"></div>
            </div>
            
            <div class="form-group">
                <label for="contact-number">Contact Number</label>
                <input type="text" id="contact-number" name="contact" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="Enter your contact number">
                <div id="contact-error" class="error-message"></div>
                <small style="font-size:11px;color:#8B8E98;">(e.g., 09123456789, +639123456789)</small>
            </div>

            <div class="form-group">
                <label for="signup-email">Email</label>
                <input type="email" id="signup-email" name="email" placeholder="Enter your Valid Email Address">
                <div id="email-error" class="error-message"></div>
                <small style="font-size:11px;color:#8B8E98;">example@gmail.com</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="signup-password" name="password" placeholder="Create a password">
                        <i class="fas fa-eye" id="togglePassword"></i>
                    </div>
                    <small style="font-size:11px;color:#8B8E98;">Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one special character, and one number.</small>
                    <div id="password-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                     <div class="password-wrapper">
                        <input type="password" id="confirm-password" name="confirm-password" placeholder="Re-enter your password">
                        <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                    </div>
                    <div id="confirm-password-error" class="error-message"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="valid-id">Upload Valid ID (Front and Back)</label>
                <div class="file-input-wrapper">
                    <input type="file" id="valid-id" name="valid_id[]" accept="image/*,.pdf" multiple>
                    <span class="file-input-clear-button" id="clear-valid-id">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
                <div id="valid-id-error" class="error-message"></div>
                <small style="font-size:11px;color:#8B8E98;">Upload both front and back. Accepted formats: JPG, PNG</small>
            </div>
            
            <button type="submit" class="btn">Submit Request</button>

            <div class="form-toggle">
                <p>Already have an account? <a href="signin.php">Sign In</a></p>
            </div>
        </form>
    </div>
</div>
</body>
</html>