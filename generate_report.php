<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Define default month filter
$month_filter = "";

// Check if month filter is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    if (isset($_POST['month'])) {
        $month_filter = $_POST['month'];
    }
}

// Determine target month (fallback to current year-month if not searched)
$target_month = !empty($month_filter) ? $month_filter : date('Y-m');

// Fetch data for the report with the option to filter by month (calculates shifts and salaries dynamically)
$query = "
SELECT 
    u.user_id AS employee_id,
    u.full_name AS employee_name,
    u.username AS employee_username,
    ep.contact AS contact,
    ep.bank_account_number AS bank_account_number,
    ep.email AS employee_email,
    COALESCE(sched.total_shifts, 0) AS total_shifts,
    COALESCE(sched.total_shifts, 0) * 28 AS calculated_salary
FROM users u
LEFT JOIN (
    SELECT user_id, COUNT(*) AS total_shifts
    FROM schedules
    WHERE DATE_FORMAT(schedules_date, '%Y-%m') = ?
    GROUP BY user_id
) sched ON u.user_id = sched.user_id
LEFT JOIN employee_profiles ep ON u.user_id = ep.user_id
WHERE u.role = 'Employee'
ORDER BY u.username;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $target_month);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Salary Report</title>
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
        @media print {
            .no-print {
                display: none !important;
            }
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
    <header class="bg-surface-container-lowest w-full top-0 border-b border-outline-variant sticky z-50 no-print">
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
    <main class="max-w-[1200px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-on-surface">Employee Salary Report</h2>
                    <p class="text-xs text-secondary mt-1">Report Sheet for Month: <strong class="text-on-surface"><?php echo date('F Y', strtotime($target_month)); ?></strong></p>
                </div>

                <!-- Search form for month filter -->
                <form action="generate_report.php" method="POST" class="flex items-center gap-2 no-print">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="month" class="text-xs font-semibold text-secondary uppercase tracking-wider">Select Month:</label>
                    <div class="relative">
                        <input type="month" name="month" id="month" value="<?php echo htmlspecialchars($target_month); ?>" required class="h-10 px-3 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all rounded-xl">
                    </div>
                    <button type="submit" class="h-10 px-4 bg-primary text-white font-semibold text-sm hover:bg-neutral-800 transition-colors rounded-xl">
                        Search
                    </button>
                </form>
            </div>

            <?php if (isset($result) && $result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                                <th class="py-3 px-4">Employee Name</th>
                                <th class="py-3 px-4">Username</th>
                                <th class="py-3 px-4">Contact</th>
                                <th class="py-3 px-4">Bank Account</th>
                                <th class="py-3 px-4">Email</th>
                                <th class="py-3 px-4 text-center">Total Shifts</th>
                                <th class="py-3 px-4 text-right">Calculated Salary (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors text-sm">
                                    <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                    <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($row['employee_username']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($row['contact'] ? $row['contact'] : 'N/A'); ?></td>
                                    <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['bank_account_number'] ? $row['bank_account_number'] : 'N/A'); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($row['employee_email'] ? $row['employee_email'] : 'N/A'); ?></td>
                                    <td class="py-3 px-4 text-center font-semibold"><?php echo $row['total_shifts']; ?></td>
                                    <td class="py-3 px-4 text-right font-mono font-semibold">RM <?php echo number_format($row['calculated_salary'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pt-6 border-t border-outline-variant flex gap-4 no-print">
                    <button class="bg-primary text-white font-semibold h-11 px-6 hover:bg-neutral-800 transition-colors rounded-xl flex items-center gap-2" onclick="window.print()">
                        <span class="material-symbols-outlined text-lg">print</span>
                        Print Report
                    </button>
                    <a href="user.php" class="border border-outline-variant text-on-surface font-semibold h-11 px-6 hover:bg-surface-container-low transition-colors rounded-xl flex items-center justify-center">
                        Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="border border-dashed border-outline-variant p-8 text-center text-sm text-secondary rounded-xl">
                    No data available for the selected month.
                </div>
                <div class="no-print pt-4">
                    <a href="user.php" class="border border-outline-variant text-on-surface font-semibold h-11 px-6 hover:bg-surface-container-low transition-colors rounded-xl inline-flex items-center justify-center">
                        Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12 no-print">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>




