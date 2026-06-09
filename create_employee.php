<?php
include 'session_init.php';
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $bank_account_number = $_POST['bank_account_number'];

    // Begin Transaction to ensure both database tables get populated
    $conn->begin_transaction();

    try {
        // 1. Insert into users
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, 'Employee', ?)");
        $stmt->bind_param("sss", $username, $password, $full_name);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // 2. Insert into employee_profiles
        $stmt2 = $conn->prepare("INSERT INTO employee_profiles (user_id, full_name, contact, bank_account_number, email, hours_worked) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt2->bind_param("issss", $user_id, $full_name, $contact, $bank_account_number, $email);
        $stmt2->execute();

        $conn->commit();
        $success_message = "Employee account created successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error creating account: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Create Employee</title>
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_leaves.php">Leaves</a>
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
    <main class="max-w-[600px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <h2 class="font-bold text-2xl text-on-surface border-b border-outline-variant pb-2">Create Employee Account</h2>

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

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <!-- Credentials Section -->
                <div class="space-y-4 border-b border-outline-variant pb-4">
                    <h3 class="font-semibold text-sm text-secondary uppercase tracking-wider">Account Credentials</h3>
                    
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="username">USERNAME</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="username" name="username" required placeholder="Choose username">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="password">PASSWORD</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="password" id="password" name="password" required placeholder="Temporary password">
                    </div>
                </div>

                <!-- Personal Info Section -->
                <div class="space-y-4">
                    <h3 class="font-semibold text-sm text-secondary uppercase tracking-wider">Profile Details</h3>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="full_name">FULL NAME</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="full_name" name="full_name" required placeholder="Full Name">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="email">EMAIL ADDRESS</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="email" id="email" name="email" required placeholder="Email Address">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="contact">CONTACT PHONE NUMBER</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="contact" name="contact" placeholder="Contact number">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="bank_account_number">BANK ACCOUNT NUMBER</label>
                        <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="bank_account_number" name="bank_account_number" required placeholder="Bank account number">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-6 border-t border-outline-variant flex gap-4">
                    <button type="submit" class="flex-1 bg-primary text-white font-semibold h-12 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                        Create Account
                    </button>
                    <a href="manage_employee_profile.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-12 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl">
                        Cancel & Return
                    </a>
                </div>
            </form>
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


