<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Default to current month and year if not provided
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Handle AJAX request to insert schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'insert_schedule') {
    $date = $_POST['date'];
    $shift_time = $_POST['shift_time'];

    // Validate and insert the new schedule
    $sql = "INSERT INTO schedules (employee_username, date, shift_time) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $date, $shift_time);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'Error: ' . $conn->error;
    }
    exit(); // End the script to prevent HTML output
}

// Fetch all employee schedules for the selected month
$sql = "SELECT schedules.id, schedules.date, schedules.shift_time
        FROM schedules
        WHERE employee_username = ? AND DATE_FORMAT(schedules.date, '%Y-%m') = ?
        ORDER BY schedules.date";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $month);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule</title>
    <link rel="stylesheet" href="css/schedule.css"> <!-- Link to the new schedule.css -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="container">
    <!-- Month Selection Form -->
    <form action="my_schedule.php" method="GET">
        <label for="month">Select Month: </label>
        <select name="month" id="month" onchange="this.form.submit()">
            <?php
            // Generate a dropdown for the months of the year
            $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
            foreach ($months as $key => $value) {
                $selected = ($key == date('m', strtotime($month))) ? 'selected' : '';
                echo "<option value='" . date('Y') . "-$key' $selected>$value " . date('Y', strtotime($month)) . "</option>";
            }
            ?>
        </select>
    </form>

    <h2>My Schedule for <?php echo date('F Y', strtotime($month)); ?></h2>
    
    <!-- Display current schedules -->
    <div id="schedule-table">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Day</th>
                    <th>Date</th>
                    <th>Shift Time</th>
                </tr>
                <?php 
                while ($row = $result->fetch_assoc()): 
                    $day = date('l', strtotime($row['date']));  // Get the day of the week
                ?>
                    <tr>
                        <td><?php echo $day; ?></td>
                        <td><?php echo $row['date']; ?></td>
                        <td><?php echo $row['shift_time']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No schedule available for this month.</p>
        <?php endif; ?>
    </div>

    <!-- Form to Insert New Schedule -->
    <div class="form-container">
        <h3>Add New Schedule</h3>
        <form id="schedule-form">
            <input type="date" name="date" required>
            <select name="shift_time" required>
                <option value="Morning">Morning (8 AM - 12 PM)</option>
                <option value="Middle">Middle (12 PM - 4 PM)</option>
                <option value="Closing">Closing (4 PM - 8:30 PM)</option>
            </select>
            <button type="submit">Add Schedule</button>
        </form>
    </div>

    <a href="user.php" class="button">Back to Dashboard</a>
</div>

<script>
$(document).ready(function() {
    // Handle form submission with AJAX
    $('#schedule-form').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        $.ajax({
            url: 'my_schedule.php',
            type: 'POST',
            data: $(this).serialize() + '&ajax_action=insert_schedule',
            success: function(response) {
                if (response.trim() === 'success') {
                    // Reload the schedule table
                    $('#schedule-table').load(location.href + ' #schedule-table > *');
                    alert('Schedule added successfully!');
                } else {
                    alert('Error: ' + response);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Error: ' + status + ' - ' + error);
            }
        });
    });
});
</script>

</body>
</html>

