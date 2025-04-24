<!DOCTYPE html>
<html>
<head>
    <title>Password Hasher</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: auto;
            background-color: #f4f4f4;
        }
        input, button {
            padding: 10px;
            margin: 10px 0;
            width: 100%;
        }
        .result {
            background: #e8e8e8;
            padding: 10px;
            word-wrap: break-word;
            border-left: 4px solid #007BFF;
        }
    </style>
</head>
<body>

<h2>Password Hasher</h2>

<form method="post">
    <label for="password">Enter Password:</label>
    <input type="password" name="password" id="password" required>

    <button type="submit">Hash Password</button>
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    echo "<div class='result'><strong>Hashed Password:</strong><br>$hashed</div>";
}
?>

</body>
</html>
