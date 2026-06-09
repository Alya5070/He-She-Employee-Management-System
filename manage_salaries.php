<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $username = $_POST['username'];
    $month = $_POST['month'];

    // Get user_id for the username
    $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt_user->bind_param("s", $username);
    $stmt_user->execute();
    $user_res = $stmt_user->get_result();
    $user_data = $user_res->fetch_assoc();
    $user_id = $user_data ? $user_data['user_id'] : 0;
    $stmt_user->close();

    if ($user_id === 0) {
        die("Employee not found.");
    }

    // Calculate total shifts for the employee in the given month
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS shift_count
        FROM schedules
        WHERE user_id = ?
        AND DATE_FORMAT(schedules_date, '%Y-%m') = ?
    ");
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $total_shifts = $data['shift_count'];
    $calculated_salary = $total_shifts * 28; // RM28 per shift

    // Insert or update the salary record
    $stmt = $conn->prepare("
        INSERT INTO salaries (user_id, month, total_shifts, calculated_salary)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE total_shifts = VALUES(total_shifts), calculated_salary = VALUES(calculated_salary)
    ");
    $stmt->bind_param("isii", $user_id, $month, $total_shifts, $calculated_salary);

    if ($stmt->execute()) {
        $message = "Salary calculated for $username in $month: RM$calculated_salary (Total Shifts: $total_shifts)";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Salaries</title>
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
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
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
            <h2 class="font-bold text-2xl text-on-surface border-b border-outline-variant pb-2">Calculate Salaries</h2>
            
            <?php if ($message): ?>
                <div class="bg-blue-50 border border-blue-200 p-4 text-blue-800 text-sm rounded-xl">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="space-y-1">
                    <label for="username" class="block text-xs font-semibold text-secondary uppercase tracking-wider">Select Employee</label>
                    <div class="relative">
                        <select name="username" id="username" required class="w-full bg-white border border-outline-variant px-4 py-2.5 pr-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none appearance-none transition-all text-sm rounded-xl">
                            <option value="" disabled selected>Select Employee</option>
                            <?php
                            $sql = "SELECT username FROM users WHERE role = 'Employee'";
                            $result = $conn->query($sql);
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['username']}'>{$row['username']}</option>";
                            }
                            ?>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                            <span class="material-symbols-outlined text-[20px] text-outline">expand_more</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="month" class="block text-xs font-semibold text-secondary uppercase tracking-wider">Select Month</label>
                    <input type="month" name="month" id="month" required class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl">
                </div>

                <div class="pt-6 border-t border-outline-variant flex gap-4">
                    <button type="submit" class="flex-1 bg-primary text-white font-semibold h-12 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                        Calculate & Save Salary
                    </button>
                    <a href="user.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-12 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl">
                        Back to Dashboard
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





