<?php
include 'db_connect.php'; // Include database connection

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $role = isset($_POST['role']) ? $_POST['role'] : 'Employee'; // Default role is 'Employee' if not provided
    $full_name = $_POST['full_name'];

    // SQL query to insert the user with the hashed password
    $sql = "INSERT INTO users (username, password, role, full_name) VALUES ('$username', '$password', '$role', '$full_name')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Registration successful. <a href='login.php'>Login here</a>";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="css/loginstyle.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <form action="" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="full_name" placeholder="Full Name" required>

            <!-- Dropdown for selecting role -->
            <select name="role" required>
                <option value="Employee">Employee</option>
                <option value="Manager">Manager</option>
            </select>

            <button type="submit">Register</button>
        </form>
        <a href="login.php">Already have an account? Login here</a>
    </div>
</body>
</html>
