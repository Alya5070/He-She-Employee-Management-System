<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Define default month filter (empty)
$month_filter = "";

// Check if month filter is provided
if (isset($_POST['month'])) {
    $month_filter = mysqli_real_escape_string($conn, $_POST['month']);
}

// Fetch data for the report with the option to filter by month
$query = "
SELECT 
    u.id AS employee_id,
    u.full_name AS employee_name,
    u.username AS employee_username,
    ep.contact AS contact,
    ep.bank_account_number AS bank_account_number,
    ep.email AS employee_email,
    COALESCE(sal.total_shifts, 0) AS total_shifts,
    COALESCE(sal.calculated_salary, 0) AS calculated_salary
FROM users u
LEFT JOIN salaries sal ON u.username = sal.employee_username
LEFT JOIN employee_profiles ep ON u.id = ep.user_id
WHERE u.role = 'Employee'
AND sal.month = '2025-01'  -- Adjust this condition to match the selected month in PHP
ORDER BY u.username, sal.month DESC;
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report</title>
    <link rel="stylesheet" href="css/report.css">
    <style>
        .print-btn, .back-btn {
            display: inline-block;
            margin: 10px 0;
            padding: 10px 20px;
            background-color: #39335b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
        }

        .print-btn:hover, .back-btn:hover {
            background-color: #39335b;
        }

        .container {
            margin: 20px auto;
            width: 90%;
            max-width: 1200px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Employee Salary Report</h2>

    <!-- Search form for month filter -->
    <form action="generate_report.php" method="POST">
        <label for="month">Select Month:</label>
        <input type="month" name="month" id="month" required>

        <button type="submit">Search</button>
    </form>
    
    <?php if (isset($result) && $result->num_rows > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
            <tr>
                <th>Employee Name</th>
                <th>Contact</th>
                <th>Username</th>
                <th>Bank Account Number</th>
                <th>Email</th>
                <th>Total Shifts</th>
                <th>Calculated Salary (RM)</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['contact']); ?></td>
                    <td><?php echo htmlspecialchars($row['employee_username']); ?></td>
                    <td><?php echo htmlspecialchars($row['bank_account_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['employee_email']); ?></td>
                    <td><?php echo $row['total_shifts']; ?></td>
                    <td><?php echo number_format($row['calculated_salary'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <button class="print-btn" onclick="window.print()">Print Report</button>
    <?php else: ?>
        <p>No data available for the report.</p>
    <?php endif; ?>
    <a href="user.php" class="back-btn">Back to Dashboard</a>
</div>
</body>
</html>

