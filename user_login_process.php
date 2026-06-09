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
        $user_id = $row['user_id'];

        // 1. Check if account is locked out
        if ($row['lockout_until'] !== null) {
            $lockout_time = strtotime($row['lockout_until']);
            if ($lockout_time > time()) {
                $diff = $lockout_time - time();
                $minutes = ceil($diff / 60);
                $_SESSION['error'] = "This account is temporarily locked. Try again in $minutes minute(s).";
                header('Location: login.php');
                $stmt->close();
                exit();
            }
        }
        
        // 2. Verify the hashed password
        if (password_verify($password, $row['password'])) {
            // Success: Reset failed attempts and lockout
            $reset_stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE user_id = ?");
            $reset_stmt->bind_param("i", $user_id);
            $reset_stmt->execute();
            $reset_stmt->close();

            session_regenerate_id(true);
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; // Store the role (Manager or Employee)
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: user.php');
            exit();
        } else {
            // Failure: Increment login attempts
            $new_attempts = intval($row['login_attempts']) + 1;
            $max_attempts = 5;

            if ($new_attempts >= $max_attempts) {
                // Lockout the account for 15 minutes
                $lockout_time = date('Y-m-d H:i:s', time() + 900); // 15 minutes = 900 seconds
                $lock_stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lockout_until = ? WHERE user_id = ?");
                $lock_stmt->bind_param("si", $lockout_time, $user_id);
                $lock_stmt->execute();
                $lock_stmt->close();

                $_SESSION['error'] = "Too many failed attempts. Account locked for 15 minutes.";
            } else {
                // Update attempts
                $upd_stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE user_id = ?");
                $upd_stmt->bind_param("ii", $new_attempts, $user_id);
                $upd_stmt->execute();
                $upd_stmt->close();

                $remaining = $max_attempts - $new_attempts;
                $_SESSION['error'] = "Invalid password. $remaining attempt(s) remaining.";
            }
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
