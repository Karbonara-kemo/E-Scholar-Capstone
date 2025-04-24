<?php
// Include database connection
include "../../connect.php";

// Start session to store user information
session_start();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capture form data
    $email = strtolower(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];

    // Check if the user is an admin
    $adminQuery = "SELECT * FROM admin WHERE LOWER(email) = '$email'";
    $adminResult = mysqli_query($conn, $adminQuery);

    if (!$adminResult) {
        error_log("Admin Query Error: " . mysqli_error($conn));
        header("Location: signin.php?error=query_error");
        exit();
    }

    if (mysqli_num_rows($adminResult) > 0) {
        $adminRow = mysqli_fetch_assoc($adminResult);

        // Verify the password for admin
        if (password_verify($password, $adminRow['password'])) {
            // Store admin info in session
            $_SESSION['admin_id'] = $adminRow['Id'];
            $_SESSION['admin_name'] = $adminRow['fname'] . ' ' . $adminRow['lname'];

            // Redirect to admin dashboard
            header("Location: User_Landing_Page/Home_Profile/admin_dashboard.php?success=1");
            exit();
        } else {
            // Redirect with an incorrect password error
            header("Location: signin.php?error=invalid_password");
            exit();
        }
    }

    // Check if the user is a regular user
    $userQuery = "SELECT * FROM user WHERE Email = '$email'";
    $userResult = mysqli_query($conn, $userQuery);

    if (!$userResult) {
        error_log("User  Query Error: " . mysqli_error($conn));
        header("Location: signin.php?error=query_error");
        exit();
    }

    if (mysqli_num_rows($userResult) > 0) {
        $userRow = mysqli_fetch_assoc($userResult);

        // Verify the password for user
        if (password_verify($password, $userRow['Password'])) {
            // Store user info in session
            $_SESSION['user_id'] = $userRow['Id'];
            $_SESSION['user_name'] = $userRow['Fname'] . ' ' . $userRow['Lname'];

            // Redirect to user dashboard
            header("Location: User_Landing_Page/Home_Profile/user_dashboard.php?success=1");
            exit();
        } else {
            // Redirect with an incorrect password error
            header("Location: signin.php?error=invalid_password");
            exit();
        }
    }

    // If no match is found, redirect with an email not found error
    header("Location: signin.php?error=email_not_found");
    exit();
}

// Close the database connection
mysqli_close($conn);
?>  