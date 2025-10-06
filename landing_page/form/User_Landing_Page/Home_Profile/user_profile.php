<?php
session_start();

include "../../../../connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../signin.php");
    exit();
}

$userId = $_SESSION['user_id'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePic'])) {
    $targetDir = "../../../../images/";
    $targetFile = $targetDir . basename($_FILES["profilePic"]["name"]);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    $check = getimagesize($_FILES["profilePic"]["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $targetFile)) {
            $imagePath = "images/" . basename($_FILES["profilePic"]["name"]);
            // Corrected: Changed 'Id' to 'user_id'
            $updateSql = "UPDATE user SET profile_pic = ? WHERE user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $imagePath, $userId);
            if ($updateStmt->execute()) {
                $successMessage = "Profile picture updated successfully!";
                $user['profile_pic'] = $imagePath;
            } else {
                $errorMessage = "Failed to update profile picture in the database.";
            }
        } else {
            $errorMessage = "Failed to upload the profile picture.";
        }
    } else {
        $errorMessage = "File is not a valid image.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profile</title>
  <link rel="icon" type="image/x-icon" href="../../../../assets/PESO Logo Assets.png">
  <link rel="stylesheet" href="../../../../resource/css/user_profile.css">
  <script src="../../../../resource/javascript/user_profile.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Darker+Grotesque:wght@300..900&family=LXGW+WenKai+TC&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Noto+Serif+Todhri&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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

  <div class="profile-container">
    <div class="profile-picture-section">
      <img id="profilePic" src="../../../../<?php echo htmlspecialchars($user['profile_pic'] ?? 'images/user.png'); ?>" alt="Profile Picture" class="profile-pic">
      <form action="" method="POST" enctype="multipart/form-data">
        <div class="profile-picture-actions">
          <input type="file" name="profilePic" accept="image/*" required onchange="previewProfilePic(event)">
          <button type="submit">Upload Your Photo</button>
        </div>
      </form>
    </div>

    <div class="section-header">Basic Information</div>
      <div class="info-grid">
        <div class="info-item">
          <label class="info-label">Full Name</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Fname'] . " " . $user['Mname'] . " " . $user['Lname']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Age</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Age']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Gender</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Gender']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Birthdate</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Birthdate']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Address</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Address']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Contact Number</label>
          <div class="info-value"><?php echo htmlspecialchars($user['contact_number']); ?></div>
        </div>
        <div class="info-item">
          <label class="info-label">Email</label>
          <div class="info-value"><?php echo htmlspecialchars($user['Email']); ?></div>
        </div>
      </div>

      <div class="action-buttons">
        <button onclick="location.href='../../User_Landing_Page/Home_Profile/user_dashboard.php'">Back</button>
        <button onclick="location.href='edit_profile.php'">Edit</button>
        <button onclick="location.href='change_password.php'">Change Password</button>
      </div>
   </div>
</body>
</html>