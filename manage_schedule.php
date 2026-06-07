<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Handle employee selection from the dropdown
$employee_filter = '';
if (isset($_POST['employee_username']) && !empty($_POST['employee_username'])) {
    $employee_filter = $_POST['employee_username'];
}

// Fetch all employees for the dropdown
$sql = "SELECT username FROM users WHERE role = 'Employee'";
$employees_result = $conn->query($sql);

// Fetch schedules based on the selected employee (if any)
$sql = "SELECT schedules.id, users.username, schedules.date, schedules.shift_time
        FROM schedules
        JOIN users ON schedules.employee_username = users.username";

if ($employee_filter) {
    $sql .= " WHERE users.username = '$employee_filter'";
}

$sql .= " ORDER BY schedules.date";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shifts</title>
    <link rel="stylesheet" href="css/manageschedule.css">
</head>
<body>

<div class="container">
    <h2>Manage Employee Shifts</h2>

    <!-- Employee selection dropdown -->
    <form method="POST" action="manage_schedule.php" class="employee-form">
        <label for="employee_username">Select Employee:</label>
        <select name="employee_username" id="employee_username" onchange="this.form.submit()">
            <option value="">-- Select Employee --</option>
            <?php while ($row = $employees_result->fetch_assoc()): ?>
                <option value="<?php echo $row['username']; ?>" 
                    <?php echo $employee_filter == $row['username'] ? 'selected' : ''; ?>>
                    <?php echo $row['username']; ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <!-- Display schedules based on employee selection -->
    <div id="manage-schedule-table">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Shift Time</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['date']; ?></td>
                        <td><?php echo $row['shift_time']; ?></td>
                        <td>
                            <a href="edit_schedule.php?id=<?php echo $row['id']; ?>" class="edit-link">Edit</a> | 
                            <a href="delete_schedule.php?id=<?php echo $row['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this schedule?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No schedules available for the selected employee.</p>
        <?php endif; ?>
    </div>

    <a href="user.php" class="back-btn">Back to Dashboard</a>
</div>

</body>
</html>

