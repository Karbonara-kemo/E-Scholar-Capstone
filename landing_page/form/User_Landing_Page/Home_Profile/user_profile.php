<?php
// Start session to retrieve logged-in user information
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

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePic'])) {
    $targetDir = "../../../../images/";
    $targetFile = $targetDir . basename($_FILES["profilePic"]["name"]);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check if the file is an image
    $check = getimagesize($_FILES["profilePic"]["tmp_name"]);
    if ($check !== false) {
        // Move uploaded file to the target directory
        if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $targetFile)) {
            // Update the profile picture path in the database
            $imagePath = "images/" . basename($_FILES["profilePic"]["name"]);
            $updateSql = "UPDATE user SET profile_pic = ? WHERE Id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $imagePath, $userId);
            if ($updateStmt->execute()) {
                $successMessage = "Profile picture updated successfully!";
                $user['profile_pic'] = $imagePath; // Update the user's profile picture in the session
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
  <link rel="icon" type="image/x-icon" href="../../../../assets/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Noto+Serif+JP:wght@200..900&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
    }

    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      background: #090549;
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

  .title {
      font-size: 20px;
      font-weight: bold;
      color: white;
  }


    .profile-container {
      max-width: 800px;
      margin: 40px auto;
      margin-top: 120px; /* Adjusted to account for fixed navbar */
      padding: 30px;
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .section-header {
      font-size: 18px;
      font-weight: bold;
      color: #333;
      margin-bottom: 20px;
      border-bottom: 1px solid #ddd;
      padding-bottom: 10px;
    }

    .profile-picture-section {
      display: flex;
      align-items: center;
      margin-bottom: 30px;
    }

    .profile-pic {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #090549;
      margin-right: 20px;
    }

    .profile-picture-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .profile-picture-actions button {
      background-color: #090549;
      color: white;
      border: none;
      padding: 8px 18px;
      border-radius: 14px;
      cursor: pointer;
      font-size: 10px;
    }

    .profile-picture-actions button:hover {
      background-color: #10087c;
    }

    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 30px;
    }

    .info-item {
      display: flex;
      flex-direction: column;
    }

    .info-label {
      font-weight: bold;
      color: #333;
      margin-bottom: 5px;
      font-size: 14px;
    }

    .info-value {
      color: #666;
      font-size: 10px;
    }

    .action-buttons {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }

    .action-buttons button {
      background-color: #090549;
      color: white;
      border: none;
      padding: 10px 20px;
      font-size: 10px;
      border-radius: 14px;
      cursor: pointer;
    }

    .action-buttons button:hover {
      background-color: #10087c;
    }

    .back-btn {
      text-decoration: none;
      color: white;
      background-color: #090549;
      padding: 6px 12px;
      border-radius: 14px;
      font-size: 10px;
      cursor: pointer;
    }

    .back-btn:hover {
      background-color: #10087c;
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="logo-container">
      <img src="https://car.neda.gov.ph/wp-content/uploads/2024/07/LOGO-Bagong-Pilipinas-Logo-White.png" alt="E-Scholar Logo" class="logo">
      <img src="../../../../images/Municipality_of_San_Julian_Logo.png" alt="E-Scholar Logo" class="logo">
      <div class="title">PESO MIS SAN JULIAN</div>
    </div>
  </div>

  <!-- PROFILE SECTION -->
  <div class="profile-container">
    <!-- Profile Picture Section -->
    <div class="profile-picture-section">
      <img id="profilePic" src="../../../../<?php echo htmlspecialchars($user['profile_pic'] ?? 'images/user.png'); ?>" alt="Profile Picture" class="profile-pic">
      <form action="" method="POST" enctype="multipart/form-data">
        <div class="profile-picture-actions">
          <input type="file" name="profilePic" accept="image/*" required>
          <button type="submit">Upload Your Photo</button>
        </div>
      </form>
    </div>

    <!-- Basic Information Section -->
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

      <!-- Action Buttons -->
      <div class="action-buttons">
        <button onclick="location.href='../../User_Landing_Page/Home_Profile/user_dashboard.php'">Back</button>
        <button onclick="location.href='edit_profile.php'">Edit</button>
        <button onclick="location.href='change_password.php'">Change Password</button>
      </div>
   </div>

</body>
</html>