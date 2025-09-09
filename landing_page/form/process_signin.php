<?php
include "../../connect.php";

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];

    $adminQuery = "SELECT * FROM admin WHERE LOWER(email) = '$email'";
    $adminResult = mysqli_query($conn, $adminQuery);

    if (!$adminResult) {
        error_log("Admin Query Error: " . mysqli_error($conn));
        header("Location: signin.php?error=query_error");
        exit();
    }

    if (mysqli_num_rows($adminResult) > 0) {
        $adminRow = mysqli_fetch_assoc($adminResult);

        if (password_verify($password, $adminRow['password'])) {
            $_SESSION['admin_id'] = $adminRow['admin_id']; // FIXED
            $_SESSION['admin_name'] = $adminRow['fname'] . ' ' . $adminRow['lname'];

            $redirectUrl = "User_Landing_Page/Home_Profile/admin_dashboard.php";
            header("Location: signin.php?success=1&redirect_to=" . urlencode($redirectUrl));
            exit();
        } else {
            header("Location: signin.php?error=invalid_password");
            exit();
        }
    }

    $userQuery = "SELECT * FROM user WHERE LOWER(Email) = '$email'";
    $userResult = mysqli_query($conn, $userQuery);

    if (!$userResult) {
        error_log("User Query Error: " . mysqli_error($conn));
        header("Location: signin.php?error=query_error");
        exit();
    }

    if (mysqli_num_rows($userResult) > 0) {
        $userRow = mysqli_fetch_assoc($userResult);

        if ($userRow['status'] !== 'approved') {
            header("Location: signin.php?error=not_approved");
            exit();
        }

        if (password_verify($password, $userRow['Password'])) {
            $_SESSION['user_id'] = $userRow['user_id']; // FIXED
            $_SESSION['user_name'] = $userRow['Fname'] . ' ' . $userRow['Lname'];

            $redirectUrl = "User_Landing_Page/Home_Profile/user_dashboard.php";
            header("Location: signin.php?success=1&redirect_to=" . urlencode($redirectUrl));
            exit();
        } else {
            header("Location: signin.php?error=invalid_password");
            exit();
        }
    }

    header("Location: signin.php?error=email_not_found");
    exit();
}

mysqli_close($conn);
?>