<?php
include "../../connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the email and code from the form
    $email = trim($_POST['email']);
    
    // Combine the 6 code digits into a single auth code
    $auth_code = '';
    for ($i = 1; $i <= 6; $i++) {
        $auth_code .= trim($_POST['code_' . $i]);
    }

    // Check if the email and code are valid
    $query = "SELECT reset_token, reset_token_expiry FROM user WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $db_code = $row['reset_token'];
        $expiry = $row['reset_token_expiry'];

        // Check if the code matches and is not expired
        if ($auth_code === $db_code && strtotime($expiry) > time()) {
            // Code is valid, redirect to reset password page
            header("Location: reset_pass.php?email=" . urlencode($email));
            exit;
        } else {
            $error = "Invalid or expired code.";
        }
    } else {
        $error = "Invalid email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Code</title>
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
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
            background: linear-gradient(155deg, #aa0505 9.5%, #b99b03 49.5%);
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
            margin-right: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .auth-code-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px); /* Adjust height excluding navbar */
            padding: 20px;
        }

        .auth-code-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            margin-top: 70px; /* Adjust for navbar height */
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .auth-code-header img {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
        }

        .auth-code-header h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .auth-code-header p {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .auth-code-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .auth-code-form label {
            font-size: 12px;
            color: #34495e;
            text-align: left;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .code-input-container {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
        }

        .code-box {
            width: 35px;
            height: 35px;
            text-align: center;
            border: 1px solid #dfe6e9;
            border-radius: 5px;
            font-size: 18px;
            padding: 0;
            outline: none;
        }

        .code-box:focus {
            outline: none;
            border-color: #090549;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .auth-code-form button {
            background-color: #090549;
            color: #ffffff;
            border: none;
            padding: 10px;
            border-radius: 14px;
            font-size: 11px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        .auth-code-form button:hover {
            background-color: #090549;
        }

        .back-to-forgot {
            margin-top: 20px;
            font-size: 11px;
            color: #090549;
            text-decoration: none;
            display: inline-block;
        }

        .back-to-forgot:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo-container">
            <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" class="logo" alt="E-Scholar Logo">
            <img src="../../images/PESO_Logo.png" alt="PESO Logo" class="logo">
            <img src="../../images/Municipality_of_San_Julian_Logo.png" class="logo" alt="E-Scholar Logo">
            <div class="title">PESO MIS SAN JULIAN</div>
        </div>
        <div class="right-nav">
            <a href="../../landing_page/index.html">Home</a>
        </div>
    </div>

    <!-- Authentication Code Content -->
    <div class="auth-code-wrapper">
        <div class="auth-code-container">
            <div class="auth-code-header">
                <img src="../../assets/scholar-logo.png" alt="E-Scholar Logo">
                <h2>Authentication Code</h2>
                <p>Please enter the code sent to your registered email address</p>
            </div>
            <form action="auth_code.php" method="POST" class="auth-code-form">
                <label for="auth_code">Enter 6-digit code</label>
                <div class="code-input-container">
                    <input type="text" id="code_1" name="code_1" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="code_2" name="code_2" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="code_3" name="code_3" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="code_4" name="code_4" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="code_5" name="code_5" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="code_6" name="code_6" class="code-box" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                <button type="submit">Verify</button>
            </form>
            <?php if (!empty($error)) { echo "<div class='error-message'>$error</div>"; } ?>
            <a href="forgot_password.php" class="back-to-forgot">Back to Forgot Password</a>
        </div>
    </div>

    <script>
    // Auto-focus next input when a digit is entered
    document.addEventListener('DOMContentLoaded', function() {
        const codeInputs = document.querySelectorAll('.code-box');
        
        // Focus the first input box when page loads
        codeInputs[0].focus();
        
        codeInputs.forEach((input, index) => {
            // Handle key input
            input.addEventListener('input', function() {
                if (this.value.length === 1) {
                    // If it's not the last box, focus the next one
                    if (index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                }
            });
            
            // Handle backspace
            input.addEventListener('keydown', function(e) {
                // If backspace is pressed and the field is empty
                if (e.key === 'Backspace' && this.value.length === 0) {
                    // If it's not the first box, focus the previous one
                    if (index > 0) {
                        codeInputs[index - 1].focus();
                    }
                }
            });
            
            // Only allow numbers
            input.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            
            // Prevent pasting multiple characters
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').slice(0, 1);
                if (/[0-9]/.test(pastedData)) {
                    this.value = pastedData;
                    
                    // Trigger input event to focus next input if needed
                    const event = new Event('input');
                    this.dispatchEvent(event);
                }
            });
        });
    });
    </script>
</body>
</html>