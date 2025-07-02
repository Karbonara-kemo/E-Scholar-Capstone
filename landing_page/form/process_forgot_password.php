<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include "../../connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=invalid_email");
        exit;
    }

    $query = "SELECT * FROM user WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: forgot_password.php?error=email_not_found");
        exit;
    }

    $auth_code = mt_rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    $query = "UPDATE user SET reset_token = ?, reset_token_expiry = ? WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $auth_code, $expiry, $email);
    $stmt->execute();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'edlexus59@gmail.com';
        $mail->Password = 'ofjj kgwx ivaj cxzj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'PESO San Julian Eastern Samar Team');
        $mail->addAddress($email);
        $mail->Subject = 'Password Reset Code';
        $mail->Body = "Hello,\n\nWe received a request to reset your password. Your authentication code is:\n\n"
            . $auth_code
            . "\n\nThis code is valid for 10 minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nPESO San Julian Eastern Samar Team";

        $mail->send();

        header("Location: auth_code.php?email=" . urlencode($email));
        exit;
    } catch (Exception $e) {
        header("Location: forgot_password.php?error=email_not_sent");
        exit;
    }
} else {
    header("Location: forgot_password.php");
    exit;
}
?>