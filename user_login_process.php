<?php
session_start();
include 'db_connect.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if the username is 'admin123' (for Manager login)
    if ($username === 'admin123') {
        // Manager login with predefined password
        if ($password == '123') {
            $_SESSION['username'] = 'admin123';
            $_SESSION['role'] = 'Manager'; // Manager role
            header('Location: user.php');
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
            header('Location: login.php');
            exit();
        }
    } else {
        // Employee login with hashed password check
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Verify the hashed password for employees
            if (password_verify($password, $row['password'])) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $row['role']; // Store the role (Manager or Employee)
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
}
?>
