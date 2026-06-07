<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/loginstyle.css">
</head>
<body>
    <div class="container">
    <div class="logo">
    <img src="images/logo.png" alt="He&She Coffee Logo">
    </div>
        <h2>He&She Coffee Employee Management System</h2>
        <h3>Login</h3>
        <form action="user_login_process.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <a href="register.php">Don't have an account? Register here</a>
    </div>
</body>
</html>




