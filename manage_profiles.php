<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

// Fetch employee profiles
$sql = "SELECT * FROM employee_profiles";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employee Profiles</title>
    <!-- Link to the new CSS file -->
    <link rel="stylesheet" href="css/manageprofile.css">
</head>
<body>
    <div class="container">
        <h2>Manage Employee Profiles</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Hours Worked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['hours_worked']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <a href="user.php" class="back-btn">Back to Dashboard</a>
    </div>
</body>
</html>
