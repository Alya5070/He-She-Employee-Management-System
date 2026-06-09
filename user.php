<?php
include 'session_init.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

include 'db_connect.php';

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch stats for Manager dashboard
$total_employees = 0;
$total_shifts = 0;
$total_payroll = 0;

if ($role == 'Manager') {
    // 1. Total Employees
    $emp_res = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'Employee'");
    if ($emp_res) {
        $row = $emp_res->fetch_assoc();
        $total_employees = $row['total'];
    }

    // 2. Total Schedules/Shifts this month
    $current_month = date('Y-m');
    $stmt1 = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM schedules 
    WHERE DATE_FORMAT(schedules_date, '%Y-%m') = ?
    ");
    $stmt1->bind_param("s", $current_month);
    $stmt1->execute();
    $shift_res = $stmt1->get_result();

    if ($shift_res) {
    $row = $shift_res->fetch_assoc();
    $total_shifts = $row['total'];
    }

$stmt1->close();


    // 3. Total Payroll calculated for this month
    $stmt2 = $conn->prepare("SELECT SUM(calculated_salary) AS total FROM salaries WHERE month = ?");
    $stmt2->bind_param("s", $current_month);
    $stmt2->execute();
    $payroll_res = $stmt2->get_result();
    if ($payroll_res) {
        $row = $payroll_res->fetch_assoc();
        $total_payroll = $row['total'] ? $row['total'] : 0;
    }
    $stmt2->close();
} else {
    // Fetch employee profiles hours and shifts count
    $stmt3 = $conn->prepare("SELECT * FROM employee_profiles WHERE user_id = (SELECT user_id FROM users WHERE username = ?)");
    $stmt3->bind_param("s", $username);
    $stmt3->execute();
    $profile_res = $stmt3->get_result();
    $profile = $profile_res ? $profile_res->fetch_assoc() : null;
    $stmt3->close();

    $stmt4 = $conn->prepare("SELECT COUNT(*) AS total FROM schedules WHERE user_id = (SELECT user_id FROM users WHERE username = ?)");
    $stmt4->bind_param("s", $username);
    $stmt4->execute();
    $emp_shift_res = $stmt4->get_result();
    $emp_total_shifts = $emp_shift_res ? $emp_shift_res->fetch_assoc()['total'] : 0;
    $stmt4->close();

    // Fetch latest monthly salary status
    $latest_sal = null;
    if ($profile) {
        $stmt_latest = $conn->prepare("
            SELECT month, total_shifts, calculated_salary, status
            FROM salaries
            WHERE user_id = ?
            ORDER BY month DESC
            LIMIT 1
        ");
        $stmt_latest->bind_param("i", $profile['user_id']);
        $stmt_latest->execute();
        $latest_sal = $stmt_latest->get_result()->fetch_assoc();
        $stmt_latest->close();
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Dashboard</title>
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
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="user.php">Dashboard</a>
                    <?php if ($role == 'Manager'): ?>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_schedule.php">Schedules</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_leaves.php">Leaves</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                    <?php else: ?>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_schedule.php">My Schedule</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_payroll.php">My Payroll</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_profile.php">My Profile</a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary hidden sm:inline">Role: <strong class="text-on-surface"><?php echo htmlspecialchars($role); ?></strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main class="max-w-[1440px] mx-auto px-6 py-8 flex-grow w-full">
        <!-- Welcome Header -->
        <section class="mb-8">
            <h1 class="text-3xl font-bold text-on-surface mb-1">Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="text-secondary">Operations Portal • <?php echo date('F d, Y'); ?></p>
        </section>

        <?php if ($role == 'Manager'): ?>
            <!-- Manager Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Active Employees -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Registered Employees</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-bold"><?php echo $total_employees; ?></span>
                            <span class="text-sm text-secondary">baristas total</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="text-xs text-on-surface-variant">Database active</span>
                    </div>
                </div>
                <!-- Scheduled Shifts -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Shifts Scheduled</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-bold"><?php echo $total_shifts; ?></span>
                            <span class="text-sm text-secondary">this month</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="material-symbols-outlined text-xs text-primary">calendar_today</span>
                        <span class="text-xs text-on-surface-variant">Update via Schedules board</span>
                    </div>
                </div>
                <!-- Month's Payroll -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Payroll Disbursed</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-bold">RM <?php echo number_format($total_payroll, 2); ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="material-symbols-outlined text-xs text-primary">payments</span>
                        <span class="text-xs text-on-surface-variant">Standard Rate: RM28/shift</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Button-Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Profiles -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl" href="manage_employee_profile.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">group</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Manage Profiles</h3>
                            <p class="text-sm text-secondary">View and edit employee details, contact info, and bank accounts.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
                <!-- Schedules -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl" href="manage_schedule.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">event_note</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Manage Shift Schedules</h3>
                            <p class="text-sm text-secondary">Schedule barista shifts and modify the weekly roster.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
                <!-- Salaries -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl" href="manage_salaries.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">payments</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Manage Salaries</h3>
                            <p class="text-sm text-secondary">Calculate monthly pay and lock payroll runs.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
                <!-- Reports -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl" href="generate_report.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">analytics</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Generate Reports</h3>
                            <p class="text-sm text-secondary">View cost totals and print the monthly salary report.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
                <!-- Leave Approvals -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl md:col-span-2" href="manage_leaves.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">event_busy</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Leave Approvals</h3>
                            <p class="text-sm text-secondary">Review and approve or reject employee leave requests.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
            </div>

        <?php elseif ($role == 'Employee'): ?>
            <!-- Employee Stats & Overview Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Shift Overview -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl shadow-sm">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Scheduled Shifts</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-bold text-primary"><?php echo $emp_total_shifts; ?></span>
                            <span class="text-sm text-secondary">total shifts</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="material-symbols-outlined text-xs text-primary">calendar_today</span>
                        <span class="text-xs text-on-surface-variant"><?php echo $profile ? $profile['hours_worked'] : '0'; ?> hours logged</span>
                    </div>
                </div>

                <!-- Latest Payroll -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl shadow-sm">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Latest Pay Status</span>
                        <?php if ($latest_sal): ?>
                            <div class="flex items-baseline gap-1 mt-1">
                                <span class="text-2xl font-bold text-primary">RM <?php echo number_format($latest_sal['calculated_salary'], 2); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="text-sm font-semibold text-secondary mt-1">No payments processed</div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <?php if ($latest_sal): ?>
                            <span class="w-2 h-2 rounded-full <?php echo $latest_sal['status'] === 'Paid' ? 'bg-green-500' : 'bg-blue-500'; ?>"></span>
                            <span class="text-xs text-on-surface-variant font-mono"><?php echo date('F Y', strtotime($latest_sal['month'] . '-01')); ?> (<?php echo $latest_sal['status']; ?>)</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined text-xs text-secondary">payments</span>
                            <span class="text-xs text-on-surface-variant">Check Payroll Portal</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Completion Status -->
                <div class="bg-white border border-outline-variant p-6 flex flex-col justify-between min-h-[140px] rounded-xl shadow-sm">
                    <div>
                        <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Profile Status</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-lg font-bold <?php echo $profile ? 'text-green-700' : 'text-amber-700'; ?>">
                                <?php echo $profile ? 'Active & Complete' : 'Needs Setup'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="material-symbols-outlined text-xs <?php echo $profile ? 'text-green-700' : 'text-amber-700'; ?>">
                            <?php echo $profile ? 'check_circle' : 'warning'; ?>
                        </span>
                        <span class="text-xs text-on-surface-variant">
                            <?php echo $profile ? 'Bank details registered' : 'Add details to receive payouts'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Employee Quick-Action Modules Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- My Schedule -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl shadow-sm" href="my_schedule.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">calendar_today</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">My Schedule</h3>
                            <p class="text-sm text-secondary">View weekly shifts and check coworker coverage.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>

                <!-- Leave Requests -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl shadow-sm" href="request_leave.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">event_busy</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Request Leave</h3>
                            <p class="text-sm text-secondary">Submit day-off requests and track manager approvals.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>

                <!-- Availability Preferences -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl shadow-sm" href="set_availability.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">edit_calendar</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">Set Availability</h3>
                            <p class="text-sm text-secondary">Define preferred weekly work days and time slots.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>

                <!-- My Payroll Portal -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl shadow-sm" href="my_payroll.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">My Payroll Portal</h3>
                            <p class="text-sm text-secondary">Review monthly payslips, bonuses, deductions, and payout history.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>

                <!-- My Profile Settings -->
                <a class="group bg-white border border-outline-variant p-6 flex items-center justify-between hover:bg-surface-container-low transition-all duration-200 rounded-xl shadow-sm md:col-span-2" href="my_profile.php">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-surface-container flex items-center justify-center border border-outline-variant rounded-xl">
                            <span class="material-symbols-outlined text-[32px] text-secondary group-hover:text-primary transition-colors">person</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-on-surface">My Profile & Bank Details</h3>
                            <p class="text-sm text-secondary">Manage contact numbers, emails, and bank details for salary disbursements.</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:translate-x-1 transition-transform">chevron_right</span>
                </a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex flex-col md:flex-row justify-between items-center max-w-[1440px] mx-auto w-full gap-2">
            <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">BrewManager Systems</span>
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>






