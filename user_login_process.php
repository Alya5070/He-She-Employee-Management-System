<?php
include 'session_init.php';
include 'db_connect.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Secure database authentication check for all accounts (Managers and Employees)
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verify the hashed password
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; // Store the role (Manager or Employee)
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: user.php');
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['error'] = "Username not found.";
        header('Location: login.php');
        exit();
    }

    $stmt->close();
}
?>
