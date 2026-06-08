<?php
include 'session_init.php';
include 'include/db_connect.php';

$check_managers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'Manager'");
$manager_count = $check_managers ? $check_managers->fetch_assoc()['total'] : 0;
if ($manager_count == 0) {
    header('Location: setup.php');
    exit();
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | login</title>
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
                <h1 class="font-bold text-2xl text-on-surface">He&amp;She Coffee</h1>
                <p class="text-sm text-secondary">Employee Management System</p>
            </div>
            <!-- Login Form -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 p-4 text-red-800 text-sm rounded-xl">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            <form action="user_login_process.php" method="POST" class="space-y-4">
                <!-- Username Input -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider" for="username">USERNAME</label>
                    <input class="w-full h-11 px-4 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded" id="username" name="username" placeholder="Enter your username" type="text" required/>
                </div>
                <!-- Password Input -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider" for="password">PASSWORD</label>
                    <input class="w-full h-11 px-4 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded" id="password" name="password" placeholder="••••••••" type="password" required/>
                </div>
                
                <!-- Primary Action -->
                <button class="w-full h-12 bg-primary text-white font-semibold flex items-center justify-center hover:bg-neutral-800 transition-colors duration-200 rounded" type="submit" name="login">
                    Sign In
                </button>
            </form>
            <!-- Secondary Context -->
            <div class="pt-4 border-t border-outline-variant flex flex-col space-y-2 text-center">
                <p class="text-xs text-secondary">Please contact your manager if you do not have an account.</p>
            </div>
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






