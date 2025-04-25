<?php
// Include database connection
include "../../connect.php";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capture form data
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $mname = mysqli_real_escape_string($conn, $_POST['mname']);
    $age = intval($_POST['age']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $birthdate = mysqli_real_escape_string($conn, $_POST['birthdate']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check if the email already exists in the database
    $checkEmailSql = "SELECT * FROM user WHERE Email = ?";
    $stmt = $conn->prepare($checkEmailSql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Email already exists, redirect back with an error
        header("Location: signup.php?error=email_taken&email=" . urlencode($email));
        exit();
    }

    // SQL query to insert data into the database
    $sql = "INSERT INTO user (Fname, Lname, Mname, Age, Gender, Birthdate, Address, contact_number, Email, Password)
            VALUES ('$fname', '$lname', '$mname', $age, '$gender', '$birthdate', '$address', '$contact_number', '$email', '$password')";

    // Execute the query and check for success
    if (mysqli_query($conn, $sql)) {
        // Redirect back to the signup page with a success message
        header("Location: signup.php?success=1");
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

    // Close the database connection
    mysqli_close($conn);
}
?>