<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Get the schedule ID from the URL
if (isset($_GET['id'])) {
    // CSRF Verification
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    $schedule_id = $_GET['id'];

    // Prepare and bind
    $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
    $stmt->bind_param("i", $schedule_id); // "i" for integer type

    // Execute the query
    if ($stmt->execute()) {
        echo "Schedule deleted successfully!<br><a href='manage_schedule.php'>Back to Manage Schedules</a>";
    } else {
        echo "Error: " . $stmt->error . "<br><a href='manage_schedule.php'>Back to Manage Schedules</a>";
    }

    // Close the statement
    $stmt->close();
} else {
    echo "Schedule ID is missing.<br><a href='manage_schedule.php'>Back to Manage Schedules</a>";
}
?>


