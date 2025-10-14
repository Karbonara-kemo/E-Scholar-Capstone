<?php
include "../../connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    if (!isset($_FILES['valid_id']) || !is_array($_FILES['valid_id']['name']) || count($_FILES['valid_id']['name']) < 2) {
        header("Location: signup.php?error=valid_id_required");
        exit();
    }

    foreach ($_FILES['valid_id']['error'] as $err) {
        if ($err !== UPLOAD_ERR_OK) {
            header("Location: signup.php?error=valid_id_required");
            exit();
        }
    }

    $validIdPaths = [];
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/form_prac/uploads/valid_ids/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['valid_id']) && count($_FILES['valid_id']['name']) >= 2) {
        foreach ($_FILES['valid_id']['name'] as $key => $name) {
            if ($_FILES['valid_id']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($name);
                $targetFile = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['valid_id']['tmp_name'][$key], $targetFile)) {
                    $validIdPaths[] = '/form_prac/uploads/valid_ids/' . $fileName;
                }
            }
        }
        $validIdPath = json_encode($validIdPaths);
    } else {
        header("Location: signup.php?error=valid_id_required");
        exit();
    }

    $checkEmailSql = "SELECT * FROM user WHERE Email = ?";
    $stmt = $conn->prepare($checkEmailSql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: signup.php?error=email_taken&email=" . urlencode($email));
        exit();
    }

    $sql = "INSERT INTO user (Fname, Lname, Mname, Age, Gender, Birthdate, Address, contact_number, Email, Password, valid_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $fname, $lname, $mname, $age, $gender, $birthdate, $address, $contact_number, $email, $password, $validIdPath);
    
    if ($stmt->execute()) {
        header("Location: signup.php?success=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>