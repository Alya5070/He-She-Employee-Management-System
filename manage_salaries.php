<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $month = $_POST['month'];

    // Calculate total shifts for the employee in the given month
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS shift_count
        FROM schedules
        WHERE employee_username = ?
        AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $stmt->bind_param("ss", $username, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $total_shifts = $data['shift_count'];
    $calculated_salary = $total_shifts * 28; // RM28 per shift

    // Insert or update the salary record
    $stmt = $conn->prepare("
        INSERT INTO salaries (employee_username, month, total_shifts, calculated_salary)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE total_shifts = VALUES(total_shifts), calculated_salary = VALUES(calculated_salary)
    ");
    $stmt->bind_param("ssii", $username, $month, $total_shifts, $calculated_salary);

    if ($stmt->execute()) {
        $message = "Salary calculated for $username in $month: RM$calculated_salary (Total Shifts: $total_shifts)";
    } else {
        $message = "Error: " . $conn->error;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Salaries</title>
    <link rel="stylesheet" href="css/managesalaries.css">
</head>
<body>
<div class="container">
    <h2>Manage Salaries</h2>
    <?php if ($message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="username">Select Employee:</label>
        <select name="username" required>
            <option value="" disabled selected>Select Employee</option>
            <?php
            $sql = "SELECT username FROM users WHERE role = 'Employee'";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['username']}'>{$row['username']}</option>";
            }
            ?>
        </select>

        <label for="month">Select Month:</label>
        <input type="month" name="month" required>

        <button type="submit">Calculate Salary</button>
    </form>
    <a href="user.php" class="back-btn">Back to Dashboard</a>
</div>
</body>
</html>

