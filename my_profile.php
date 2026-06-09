<?php
include 'session_init.php';
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

$username = $_SESSION['username'];
$sql = "SELECT ep.* FROM employee_profiles ep 
        JOIN users u ON u.user_id = ep.user_id 
        WHERE u.username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | My Profile</title>
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_schedule.php">My Schedule</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="my_profile.php">My Profile</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Employee</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[600px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <h2 class="font-bold text-2xl text-on-surface border-b border-outline-variant pb-2">My Profile Details</h2>

            <?php if ($profile): ?>
                <div class="space-y-4">
                    <div>
                        <span class="block text-xs font-semibold text-secondary uppercase">FULL NAME</span>
                        <p class="text-lg font-medium text-on-surface mt-1"><?php echo htmlspecialchars($profile['full_name']); ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-secondary uppercase">BANK ACCOUNT NUMBER</span>
                        <p class="text-lg font-medium text-on-surface mt-1"><?php echo htmlspecialchars($profile['bank_account_number']); ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-secondary uppercase">EMAIL ADDRESS</span>
                        <p class="text-lg font-medium text-on-surface mt-1"><?php echo htmlspecialchars($profile['email']); ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-secondary uppercase">CONTACT NUMBER</span>
                        <p class="text-lg font-medium text-on-surface mt-1"><?php echo htmlspecialchars($profile['contact'] ? $profile['contact'] : 'Not provided'); ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-secondary uppercase">HOURS WORKED</span>
                        <p class="text-lg font-medium text-on-surface mt-1"><?php echo htmlspecialchars($profile['hours_worked']); ?> hrs</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-yellow-50 border border-yellow-200 p-4 text-yellow-800 rounded-xl">
                    <p class="text-sm font-semibold mb-1">No Profile Setup Yet</p>
                    <p class="text-xs">Please click the button below to update your personal details and complete your profile.</p>
                </div>
            <?php endif; ?>

            <div class="pt-6 border-t border-outline-variant flex gap-4">
                <a href="update_profile.php" class="flex-1 bg-primary text-white font-semibold h-12 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                    <?php echo $profile ? 'Update Profile' : 'Create Profile'; ?>
                </a>
                <a href="user.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-12 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl">
                    Back to Dashboard
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>