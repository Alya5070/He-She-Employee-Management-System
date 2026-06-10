<?php
include 'session_init.php';
include 'db_connect.php';

// Check if any manager exists; if so, redirect to login
$check_managers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'Manager'");
$manager_count = $check_managers ? $check_managers->fetch_assoc()['total'] : 0;
if ($manager_count > 0) {
    header('Location: login.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $raw_password = $_POST['password'];
    $full_name = $_POST['full_name'];

    // Password strength validation
    if (strlen($raw_password) < 8 || 
        !preg_match('/[A-Z]/', $raw_password) || 
        !preg_match('/[a-z]/', $raw_password) || 
        !preg_match('/[0-9]/', $raw_password) || 
        !preg_match('/[^A-Za-z0-9]/', $raw_password)) {
        $error_message = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);

        // Insert initial manager
        $sql = "INSERT INTO users (username, password, role, full_name) VALUES (?, ?, 'Manager', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $password, $full_name);

        if ($stmt->execute()) {
            $success_message = "Initial Manager account created successfully! Redirecting to login...";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>";
        } else {
            $error_message = "Error creating account: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | First-time Setup</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                "secondary": "#545f73",
                "surface-container": "#eceef0",
                "surface-container-lowest": "#ffffff",
                "on-background": "#191c1e",
                "on-surface-variant": "#434655",
                "on-primary": "#ffffff",
                "surface": "#f7f9fb",
                "primary": "#000000",
                "background": "#f7f9fb",
                "primary-container": "#000000",
                "on-primary-container": "#ffffff",
                "on-surface": "#191c1e",
                "outline-variant": "#c3c6d7",
                "outline": "#737686"
              },
              borderRadius: {
                "DEFAULT": "0.125rem",
                "lg": "0.25rem",
                "xl": "0.5rem",
                "full": "0.75rem"
              }
            }
          }
        }
      </script>
</head>
<body class="bg-surface min-h-screen flex flex-col">
    <!-- Main Content Canvas -->
    <main class="flex-grow flex items-center justify-center px-4 py-8">
        <!-- Authentication Card -->
        <div class="w-full max-w-[420px] bg-surface-container-lowest border border-outline-variant p-6 flex flex-col space-y-6 rounded-xl">
            <!-- Logo/Identity Area -->
            <div class="text-center space-y-2">
                <div class="flex justify-center mb-4">
                    <img src="images/logo.png" alt="He&She Coffee Logo" class="h-16 w-auto object-contain">
                </div>
                <h1 class="font-bold text-2xl text-on-surface">System Setup</h1>
                <p class="text-sm text-secondary">Create the initial Manager account to configure your system.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 p-4 text-red-800 text-sm rounded-xl">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border border-green-200 p-4 text-green-800 text-sm rounded-xl">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Setup Form -->
            <form action="" method="POST" class="space-y-4">
                <!-- Full Name Input -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider" for="full_name">FULL NAME</label>
                    <input class="w-full h-11 px-4 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded-xl" id="full_name" name="full_name" placeholder="Enter your full name" type="text" required/>
                </div>

                <!-- Username Input -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider" for="username">USERNAME</label>
                    <input class="w-full h-11 px-4 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded-xl" id="username" name="username" placeholder="Choose a username" type="text" required/>
                </div>

                <!-- Password Input -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider" for="password">PASSWORD</label>
                    <input class="w-full h-11 px-4 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded-xl" id="password" name="password" placeholder="••••••••" type="password" required/>
                    <p class="text-xs text-secondary mt-1">Must be at least 8 characters long, and include an uppercase letter, a lowercase letter, a number, and a special character.</p>
                </div>
                
                <!-- Primary Action -->
                <button class="w-full h-12 bg-primary text-white font-semibold flex items-center justify-center hover:bg-neutral-800 transition-colors duration-200 rounded-xl" type="submit">
                    Initialize System
                </button>
            </form>
        </div>
    </main>
    <!-- Global Footer -->
    <footer class="bg-surface-container border-t border-outline-variant w-full">
        <div class="flex flex-col md:flex-row justify-between items-center py-4 px-6 max-w-[1440px] mx-auto w-full">
            <div class="text-xs text-on-surface-variant mb-2 md:mb-0">
                © 2026 He&amp;She Coffee. All rights reserved.
            </div>
            <div class="flex space-x-4">
                <span class="text-xs text-secondary">BrewManager Systems v2.0</span>
            </div>
        </div>
    </footer>
</body>
</html>


