<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Get the schedule ID from the URL
$schedule_id = $_GET['id'];

// Fetch the current schedule data
$sql = "SELECT * FROM schedules WHERE id = '$schedule_id'";
$result = $conn->query($sql);
$schedule = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    // Get new data from the form
    $new_date = $_POST['date'];
    $new_shift_time = $_POST['shift_time'];

    // Update the schedule in the database (removed location field)
    $update_sql = "UPDATE schedules SET date = '$new_date', shift_time = '$new_shift_time' WHERE id = '$schedule_id'";
    if ($conn->query($update_sql) === TRUE) {
        echo "Schedule updated successfully!<br><a href='manage_schedule.php'>Back to Manage Schedules</a>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            width: 50%;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-container input, .form-container select {
            padding: 8px;
            margin: 5px;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .form-container button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit Schedule for Employee: <?php echo $schedule['employee_username']; ?></h2>
    <form action="edit_schedule.php?id=<?php echo $schedule['id']; ?>" method="POST">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?php echo $schedule['date']; ?>" required>

        <label for="shift_time">Shift Time</label>
        <select id="shift_time" name="shift_time" required>
            <option value="Morning" <?php echo ($schedule['shift_time'] == 'Morning') ? 'selected' : ''; ?>>Morning (8 AM - 12 PM)</option>
            <option value="Middle" <?php echo ($schedule['shift_time'] == 'Middle') ? 'selected' : ''; ?>>Middle (12 PM - 4 PM)</option>
            <option value="Closing" <?php echo ($schedule['shift_time'] == 'Closing') ? 'selected' : ''; ?>>Closing (4 PM - 8:30 PM)</option>
        </select>

        <button type="submit" name="update_schedule">Update Schedule</button>
    </form>
</div>

</body>
</html>
