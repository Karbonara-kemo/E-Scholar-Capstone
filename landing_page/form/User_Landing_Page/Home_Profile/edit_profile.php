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
// Corrected: Changed 'Id' to 'user_id'
$sql = "SELECT * FROM user WHERE user_id = ?";
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
    $contact_number = $_POST['contact_number'];
    $email = $_POST['email'];

    // Update user information in the database
    // Corrected: Changed 'Id' to 'user_id' and added contact_number
    $updateSql = "UPDATE user SET Fname = ?, Lname = ?, Mname = ?, Age = ?, Gender = ?, Birthdate = ?, Address = ?, contact_number = ?, Email = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    // Corrected: Updated bind_param to include contact_number
    $updateStmt->bind_param("sssisssssi", $fname, $lname, $mname, $age, $gender, $birthdate, $address, $contact_number, $email, $userId);

    if ($updateStmt->execute()) {
        // Refresh user data after successful update
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
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
    <link rel="stylesheet" href="../../../../resource/css/edit_profile.css">
    <title>Edit Profile</title>
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
                <img src="../../../../assets/PESO Logo Assets.png" alt="E-Scholar Logo">
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
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
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