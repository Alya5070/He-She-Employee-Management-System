<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

// Fetch employee profiles
$sql = "SELECT * FROM employee_profiles";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Employee Profiles</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
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
                "outline": "#737686",
                "surface-container-low": "#f2f4f6"
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
<body class="bg-background text-on-surface min-h-screen flex flex-col justify-between">
    <!-- TopNavBar -->
    <header class="bg-surface-container-lowest w-full top-0 border-b border-outline-variant sticky z-50">
        <div class="flex justify-between items-center h-16 px-6 max-w-[1440px] mx-auto">
            <div class="flex items-center gap-6">
                <div class="font-bold text-xl text-primary flex items-center gap-2">
                    <img src="images/logo.png" alt="He&She Coffee Logo" class="h-8 w-auto object-contain">
                    He&She Coffee
                </div>
                <nav class="hidden md:flex items-center gap-6 h-full mt-1">
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="user.php">Dashboard</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_schedule.php">Schedules</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Manager</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <div class="flex justify-between items-center border-b border-outline-variant pb-2">
                <h2 class="font-bold text-2xl text-on-surface">Employee Hours Overview</h2>
                <a href="manage_employee_profile.php" class="text-xs bg-primary text-white font-semibold px-3 py-1.5 hover:bg-neutral-800 transition-colors rounded-xl">
                    Edit Detailed Profiles
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                            <th class="py-3 px-4">Profile ID</th>
                            <th class="py-3 px-4">Full Name</th>
                            <th class="py-3 px-4">Hours Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors text-sm">
                                    <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($row['hours_worked']); ?> hrs</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="py-6 text-center text-sm text-secondary">No employee profiles found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pt-4 border-t border-outline-variant">
                <a href="user.php" class="inline-flex items-center justify-center border border-outline-variant text-on-surface font-semibold px-4 h-11 hover:bg-surface-container-low transition-colors rounded-xl">
                    Back to Dashboard
                </a>
            </div>
        </section>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>

