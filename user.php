<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo $_SESSION['username']; ?>!</h2>
        <p><strong>Role:</strong> <?php echo ucfirst($_SESSION['role']); ?></p>

        <!-- Role-specific sections -->
        <div class="role-specific">
            <?php if ($_SESSION['role'] == 'Manager'): ?>
                <h3>Manager Dashboard</h3>
                <div class="dashboard-options">
                <a href="manage_employee_profile.php" class="option-card">Manage Employee Profiles</a>
                    <a href="manage_schedule.php" class="option-card">Manage Shift Schedules</a>
                    <a href="manage_salaries.php" class="option-card">Manage Salaries</a>
                    <a href="generate_report.php" class="option-card">Generate Report</a>
                </div>
            <?php elseif ($_SESSION['role'] == 'Employee'): ?>
                <h3>Employee Dashboard</h3>
                <div class="dashboard-options">
                    <a href="my_schedule.php" class="option-card">My Schedule</a>
                    <a href="update_profile.php" class="option-card">Update Profile</a>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</body>
</html>




