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

// Fetch data for the report with the option to filter by month
$query = "
SELECT 
    u.user_id AS employee_id,
    u.full_name AS employee_name,
    u.username AS employee_username,
    ep.full_name AS profile_name,
    ep.contact AS contact,
    ep.bank_account_number AS bank_account_number,
    ep.email AS employee_email,
    COALESCE(ep.shift_rate, 28.00) AS shift_rate,
    COALESCE(s.total_shifts, live_sched.shift_count, 0) AS total_shifts,
    COALESCE(s.calculated_salary, (COALESCE(live_sched.shift_count, 0) * COALESCE(ep.shift_rate, 28.00))) AS calculated_salary,
    COALESCE(s.bonus, 0) AS bonus,
    COALESCE(s.deduction, 0) AS deduction,
    COALESCE(s.status, 'Uncalculated') AS status
FROM users u
LEFT JOIN employee_profiles ep ON u.user_id = ep.user_id
LEFT JOIN salaries s ON u.user_id = s.user_id AND s.month = ?
LEFT JOIN (
    SELECT user_id, COUNT(*) AS shift_count
    FROM schedules
    WHERE DATE_FORMAT(schedules_date, '%Y-%m') = ?
    GROUP BY user_id
) live_sched ON u.user_id = live_sched.user_id
WHERE u.role = 'Employee'
ORDER BY u.username;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $target_month, $target_month);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$total_spent = 0;
$total_paid = 0;
$total_draft = 0;

while ($row = $result->fetch_assoc()) {
    $row['is_incomplete'] = empty($row['profile_name']) || empty($row['contact']) || empty($row['bank_account_number']) || empty($row['employee_email']);
    $row['net_pay'] = floatval($row['calculated_salary']) + floatval($row['bonus']) - floatval($row['deduction']);
    $rows[] = $row;
    $total_spent += $row['net_pay'];
    if ($row['status'] === 'Paid') {
        $total_paid += $row['net_pay'];
    } else {
        $total_draft += $row['net_pay'];
    }
}
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_leaves.php">Leaves</a>
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

            <?php if (!empty($rows)): ?>
                <!-- Financial Overview Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pb-2">
                    <div class="bg-surface-container-low border border-outline-variant p-4 rounded-xl shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Projected Roster Cost</span>
                        <div class="text-xl font-bold text-on-surface font-mono">RM <?php echo number_format($total_spent, 2); ?></div>
                    </div>
                    <div class="bg-surface-container-low border border-outline-variant p-4 rounded-xl shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Confirmed & Paid</span>
                        <div class="text-xl font-bold text-green-700 font-mono">RM <?php echo number_format($total_paid, 2); ?></div>
                    </div>
                    <div class="bg-surface-container-low border border-outline-variant p-4 rounded-xl shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Pending Drafts</span>
                        <div class="text-xl font-bold text-amber-700 font-mono">RM <?php echo number_format($total_draft, 2); ?></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                                <th class="py-3 px-4">Employee Name</th>
                                <th class="py-3 px-4">Contact</th>
                                <th class="py-3 px-4">Bank Account</th>
                                <th class="py-3 px-4">Shift Rate</th>
                                <th class="py-3 px-4 text-center">Total Shifts</th>
                                <th class="py-3 px-4 text-right">Base Salary (RM)</th>
                                <th class="py-3 px-4 text-right">Bonus</th>
                                <th class="py-3 px-4 text-right">Deduction</th>
                                <th class="py-3 px-4 text-right">Net Pay (RM)</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors text-sm">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-on-surface flex items-center gap-1.5">
                                            <?php echo htmlspecialchars($row['employee_name']); ?>
                                            <?php if ($row['is_incomplete']): ?>
                                                <span class="material-symbols-outlined text-red-600 text-base" title="Incomplete Profile">warning</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[10px] text-secondary font-mono">@<?php echo htmlspecialchars($row['employee_username']); ?></div>
                                    </td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($row['contact'] ? $row['contact'] : 'N/A'); ?></td>
                                    <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['bank_account_number'] ? $row['bank_account_number'] : 'N/A'); ?></td>
                                    <td class="py-3 px-4 font-mono text-xs">RM <?php echo number_format($row['shift_rate'], 2); ?></td>
                                    <td class="py-3 px-4 text-center font-semibold"><?php echo $row['total_shifts']; ?></td>
                                    <td class="py-3 px-4 text-right font-mono font-semibold">RM <?php echo number_format($row['calculated_salary'], 2); ?></td>
                                    <td class="py-3 px-4 text-right font-mono text-green-700"><?php echo $row['bonus'] > 0 ? '+RM '.number_format($row['bonus'], 2) : '—'; ?></td>
                                    <td class="py-3 px-4 text-right font-mono text-red-700"><?php echo $row['deduction'] > 0 ? '-RM '.number_format($row['deduction'], 2) : '—'; ?></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold">RM <?php echo number_format($row['net_pay'], 2); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($row['is_incomplete']): ?>
                                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded-xl">Profile Incomplete</span>
                                        <?php elseif ($row['status'] === 'Paid'): ?>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-xl">Paid</span>
                                        <?php elseif ($row['status'] === 'Draft'): ?>
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded-xl">Draft</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-neutral-100 text-neutral-500 text-[10px] font-bold rounded-xl">Uncalculated</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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




