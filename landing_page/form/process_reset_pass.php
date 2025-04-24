<?php
// Include the database connection
include "../../connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the email and new password from the form
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate the form inputs
    if (empty($new_password) || empty($confirm_password)) {
        header("Location: reset_password.php?email=" . urlencode($email) . "&error=Fields cannot be empty.");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: reset_password.php?email=" . urlencode($email) . "&error=Passwords do not match.");
        exit;
    }

    // Hash the new password for security
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update the password in the database
    $query = "UPDATE user SET Password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE Email = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        header("Location: reset_password.php?email=" . urlencode($email) . "&error=Database error: " . $conn->error);
        exit;
    }

    $stmt->bind_param("ss", $hashed_password, $email);
    if ($stmt->execute()) {
        // Redirect to success page or login page
        header("Location: signin.php?success=Password reset successful. You can now log in.");
        exit;
    } else {
        header("Location: reset_password.php?email=" . urlencode($email) . "&error=Failed to reset password. Please try again.");
        exit;
    }
} else {
    // Redirect to reset password page if accessed without POST
    header("Location: reset_password.php");
    exit;
}
?>