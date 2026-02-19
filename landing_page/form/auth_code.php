<?php
include "../../connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $auth_code = '';
    for ($i = 1; $i <= 6; $i++) {
        $auth_code .= trim($_POST['code_' . $i]);
    }

    $query = "SELECT reset_token, reset_token_expiry FROM user WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $db_code = $row['reset_token'];
        $expiry = $row['reset_token_expiry'];

        if ($auth_code === $db_code && strtotime($expiry) > time()) {
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
    <link rel="icon" type="image/x-icon" href="../../assets/PESO Logo Assets.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <<link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../resource/css/auth_code.css">
    <script src="../../resource/javascript/auth_code.js"></script>
</head>
<body>
    <div class="navbar">
        <div class="logo-container">
            <img src="../../images/LOGO-Bagong-Pilipinas-Logo-White.png" class="logo" alt="E-Scholar Logo">
            <img src="../../images/PESO_Logo.png" alt="PESO Logo" class="logo">
            <img src="../../images/final-logo-san-julian.png" class="san-julian-logo" alt="E-Scholar Logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
        <div class="right-nav">
            <a href="../../index.html">
              <i class="fas fa-home"></i> Home
            </a>
        </div>
    </div>

    <div class="auth-code-wrapper">
        <div class="auth-code-container">
            <div class="auth-code-header">
                <img src="../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
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
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? ''); ?>">
                <button type="submit">Verify</button>
            </form>
            <?php if (!empty($error)) { echo "<div class='error-message'>$error</div>"; } ?>
            <a href="forgot_password.php" class="back-to-forgot">Back to Forgot Password</a>
            <a href="process_forgot_password.php?email=<?php echo urlencode($_GET['email'] ?? ''); ?>" class="resend-link">Resend code</a>
        </div>
    </div>
</body>
</html>