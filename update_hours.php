<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $employee_id = $_POST['employee_id'];
    $hours_worked = $_POST['hours_worked'];

    // Parameterized query
    $stmt = $conn->prepare("UPDATE employee_profiles SET hours_worked = ? WHERE id = ?");
    $stmt->bind_param("di", $hours_worked, $employee_id);

    if ($stmt->execute()) {
        echo "Hours updated successfully. <a href='user.php'>Back to Dashboard</a>";
    } else {
        echo "Error updating hours: " . $stmt->error;
    }

    $stmt->close();
}
?>


