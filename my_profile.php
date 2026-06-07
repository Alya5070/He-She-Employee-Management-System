<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

$username = $_SESSION['username'];
$sql = "SELECT * FROM employee_profiles JOIN users ON users.id = employee_profiles.user_id WHERE users.username = '$username'";
$result = $conn->query($sql);
$profile = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="css/loginstyle.css">
</head>
<body>
    <div class="container">
        <h2>My Profile</h2>
        <p><strong>Full Name:</strong> <?php echo $profile['full_name']; ?></p>
        <p><strong>Bank Account Number:</strong> <?php echo $profile['bank_account_number']; ?></p>
        <p><strong>Email:</strong> <?php echo $profile['email']; ?></p>
        <a href="user.php">Back to Dashboard</a>
    </div>
</body>
</html>
