<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer
require '../../vendor/autoload.php';
include "../../connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the email from the form
    $email = trim($_POST['email']);

    // Check if the email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=invalid_email");
        exit;
    }

    // Check if the email exists in the database
    $query = "SELECT * FROM user WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: forgot_password.php?error=email_not_found");
        exit;
    }

    // Generate a random 6-digit authentication code
    $auth_code = mt_rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes")); // Code expires in 10 minutes

    // Save the code and expiry in the database
    $query = "UPDATE user SET reset_token = ?, reset_token_expiry = ? WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $auth_code, $expiry, $email);
    $stmt->execute();

    // Send the code via email using PHPMailer
    $mail = new PHPMailer(true);

    try {
        // SMTP server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use your email provider's SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'edlexus59@gmail.com'; // Your email address
        $mail->Password = 'ofjj kgwx ivaj cxzj'; // Your email password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email settings
        $mail->setFrom('your-email@gmail.com', 'E-Scholar Support');
        $mail->addAddress($email);
        $mail->Subject = 'Password Reset Code';
        $mail->Body = "Hello,\n\nWe received a request to reset your password. Your authentication code is:\n\n"
            . $auth_code
            . "\n\nThis code is valid for 10 minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nE-Scholar Team";

        $mail->send();

        // Redirect to the authentication code page
        header("Location: auth_code.php?email=" . urlencode($email));
        exit;
    } catch (Exception $e) {
        header("Location: forgot_password.php?error=email_not_sent");
        exit;
    }
} else {
    // Redirect to forgot password page if accessed without POST
    header("Location: forgot_password.php");
    exit;
}
?>