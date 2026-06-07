<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_id = $_POST['employee_id'];
    $hours_worked = $_POST['hours_worked'];

    $sql = "UPDATE employee_profiles SET hours_worked = $hours_worked WHERE id = $employee_id";
    if ($conn->query($sql) === TRUE) {
        echo "Hours updated successfully. <a href='user.php'>Back to Dashboard</a>";
    } else {
        echo "Error updating hours: " . $conn->error;
    }
}
?>

