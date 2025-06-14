<?php
// Start session
session_start();

// Include database connection
include "../../../../connect.php";

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php"); // Redirect to login page if not logged in
    exit();
}

// Get logged-in user's ID from session
$userId = $_SESSION['user_id'];

// Fetch user information from the database
$sql = "SELECT * FROM user WHERE Id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "User not found.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $mname = $_POST['mname'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $address = $_POST['address'];
    $email = $_POST['email'];

    // Update user information in the database
    $updateSql = "UPDATE user SET Fname = ?, Lname = ?, Mname = ?, Age = ?, Gender = ?, Birthdate = ?, Address = ?, Email = ? WHERE Id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssissssi", $fname, $lname, $mname, $age, $gender, $birthdate, $address, $email, $userId);

    if ($updateStmt->execute()) {
        $successMessage = "Profile updated successfully!";
    } else {
        $errorMessage = "Error updating profile. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../../../assets/PESO Logo Assets.png">
    <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <title>Edit Profile</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: linear-gradient(155deg, #090549 23.3%, #aa0505 50.5%,rgb(165, 137, 0) 50.5%);
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

        .san-julian-logo {
            height: 58px;
            margin-right: 10px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            color: white;
        }

        .form-container {
            max-width: 600px;
            margin: 40px auto;
            margin-top: 100px;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-container h2 {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: #333;
            font-size : 12px;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select {
            width: 98%;
            padding: 10px;
            font-size: 10px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        #gender {
            width: 102%;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .form-actions button, .form-actions a {
            background-color: #090549;
            color: white;
            border: none;
            padding: 10px 20px;
            text-decoration: none;
            font-size: 10px;
            border-radius: 14px;
            cursor: pointer;
            text-align: center;
        }

        .form-actions button:hover, .form-actions a:hover {
            background-color: #10087c;
        }

        .message {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .message.success {
            color: #4CAF50;
        }

        .message.error {
            color: #f44336;
        }

        .form-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 25px;
        text-align: center;
    }
    
    .form-header img {
        width: 100px;
        height: auto;
        margin-bottom: 15px;
    }
    
    .form-header h2 {
        color: #090549;
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }
    
    .form-header p {
        color: #666;
        margin: 5px 0 0;
        font-size: 12px;
    }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="logo-container">
            <img src="../../../../images/LOGO-Bagong-Pilipinas-Logo-White.png" alt="Bagong Pilipinas Logo" class="logo">
            <img src="../../../../images/PESO_Logo.png" alt="PESO Logo" class="logo">            
            <img src="../../../../images/final-logo-san-julian.png" alt="E-Scholar Logo" class="san-julian-logo">
            <div class="title">PESO SAN JULIAN MIS </div>
        </div>
    </div>

    <div class="form-container">
            <div class="form-header">
                <img src="../../../../images/final-logo-san-julian.png" alt="E-Scholar Logo">
                <h2 class="title-h2">Edit Profile</h2>
                <p class="desc-p">Update personal information</p>
            </div>

        <?php if (isset($successMessage)) : ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if (isset($errorMessage)) : ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="fname">First Name</label>
                <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($user['Fname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($user['Lname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="mname">Middle Name</label>
                <input type="text" id="mname" name="mname" value="<?php echo htmlspecialchars($user['Mname']); ?>">
            </div>
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($user['Age']); ?>" required>
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="Male" <?php echo ($user['Gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['Gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="birthdate">Birthdate</label>
                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($user['Birthdate']); ?>" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['Address']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
            </div>
            <div class="form-actions">
                <a href="user_profile.php">Cancel</a>
                <button type="submit">Save Changes</button>
            </div>
        </form>
    </div>

</body>
</html>