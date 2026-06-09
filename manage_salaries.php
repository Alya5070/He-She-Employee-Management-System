<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

$message = "";
$message_type = "info"; 

// Handle month selection
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $action = $_POST['action'];

    if ($action === 'calculate_all') {
        // Fetch all employees and their profile shift rates/details
        $employees_res = $conn->query("
            SELECT u.user_id, u.username, u.full_name, COALESCE(p.shift_rate, 28.00) AS rate,
                   p.full_name AS profile_name, p.contact, p.bank_account_number, p.email
            FROM users u
            LEFT JOIN employee_profiles p ON u.user_id = p.user_id
            WHERE u.role = 'Employee'
        ");

        $conn->begin_transaction();
        try {
            $count = 0;
            while ($emp = $employees_res->fetch_assoc()) {
                $uid = $emp['user_id'];
                $rate = $emp['rate'];

                // Skip if incomplete profile
                if (empty($emp['profile_name']) || empty($emp['contact']) || empty($emp['bank_account_number']) || empty($emp['email'])) {
                    continue;
                }

                // Check if already paid/locked
                $chk = $conn->prepare("SELECT status FROM salaries WHERE user_id = ? AND month = ?");
                $chk->bind_param("is", $uid, $month);
                $chk->execute();
                $chk_res = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($chk_res && $chk_res['status'] === 'Paid') {
                    continue;
                }

                // Count shifts for this employee in the given month
                $stmt_shifts = $conn->prepare("
                    SELECT COUNT(*) AS shift_count
                    FROM schedules
                    WHERE user_id = ?
                    AND DATE_FORMAT(schedules_date, '%Y-%m') = ?
                ");
                $stmt_shifts->bind_param("is", $uid, $month);
                $stmt_shifts->execute();
                $shifts_data = $stmt_shifts->get_result()->fetch_assoc();
                $total_shifts = $shifts_data ? $shifts_data['shift_count'] : 0;
                $stmt_shifts->close();

                $calculated_salary = $total_shifts * $rate;

                // Save or update (if not paid)
                $save_stmt = $conn->prepare("
                    INSERT INTO salaries (user_id, month, total_shifts, calculated_salary, status)
                    VALUES (?, ?, ?, ?, 'Draft')
                    ON DUPLICATE KEY UPDATE total_shifts = VALUES(total_shifts), calculated_salary = VALUES(calculated_salary)
                ");
                $save_stmt->bind_param("isid", $uid, $month, $total_shifts, $calculated_salary);
                $save_stmt->execute();
                $save_stmt->close();
                $count++;
            }
            $conn->commit();
            $message = "Successfully calculated and saved draft payroll for $count employees.";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error calculating salaries: " . $e->getMessage();
            $message_type = "error";
        }
    }

    if ($action === 'save_adjustment') {
        $adj_uid = intval($_POST['adj_user_id']);
        $bonus_val = floatval($_POST['bonus']);
        $deduction_val = floatval($_POST['deduction']);

        // Ensure a record exists before updating
        $ensure = $conn->prepare("INSERT IGNORE INTO salaries (user_id, month, total_shifts, calculated_salary, bonus, deduction, status) VALUES (?, ?, 0, 0, 0, 0, 'Draft')");
        $ensure->bind_param("is", $adj_uid, $month);
        $ensure->execute();
        $ensure->close();

        $upd = $conn->prepare("UPDATE salaries SET bonus = ?, deduction = ? WHERE user_id = ? AND month = ? AND status != 'Paid'");
        $upd->bind_param("ddis", $bonus_val, $deduction_val, $adj_uid, $month);
        if ($upd->execute()) {
            $message = "Adjustments saved.";
            $message_type = "success";
        } else {
            $message = "Failed to save adjustments.";
            $message_type = "error";
        }
        $upd->close();
    }

    if ($action === 'lock_month') {
        // Double check for incomplete profiles
        $inc_chk = $conn->query("
            SELECT COUNT(*) AS cnt
            FROM users u
            LEFT JOIN employee_profiles p ON u.user_id = p.user_id
            WHERE u.role = 'Employee' AND (p.full_name IS NULL OR p.full_name = '' OR p.contact IS NULL OR p.contact = '' OR p.bank_account_number IS NULL OR p.bank_account_number = '' OR p.email IS NULL OR p.email = '')
        ");
        $inc_cnt = $inc_chk->fetch_assoc()['cnt'];
        if ($inc_cnt > 0) {
            $message = "Cannot lock payroll while incomplete profiles exist.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("UPDATE salaries SET status = 'Paid' WHERE month = ? AND status = 'Draft'");
            $stmt->bind_param("s", $month);
            if ($stmt->execute()) {
                $message = "Payroll for $month has been locked and finalised.";
                $message_type = "success";
            } else {
                $message = "Error locking payroll: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }

    if ($action === 'unlock_month') {
        $stmt = $conn->prepare("UPDATE salaries SET status = 'Draft' WHERE month = ? AND status = 'Paid'");
        $stmt->bind_param("s", $month);
        if ($stmt->execute()) {
            $message = "Payroll for $month has been unlocked.";
            $message_type = "success";
        } else {
            $message = "Error unlocking payroll: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Load current statuses and calculations for the table view
$payroll_data = [];
$employees_res = $conn->query("
    SELECT u.user_id, u.username, u.full_name, COALESCE(p.shift_rate, 28.00) AS rate, 
           p.full_name AS profile_name, p.contact, p.bank_account_number, p.email
    FROM users u
    LEFT JOIN employee_profiles p ON u.user_id = p.user_id
    WHERE u.role = 'Employee'
    ORDER BY u.full_name
");

$all_locked = true;
$any_records = false;
$has_incomplete = false;

while ($emp = $employees_res->fetch_assoc()) {
    $uid = $emp['user_id'];
    
    // Get saved record
    $stmt_sal = $conn->prepare("SELECT total_shifts, calculated_salary, bonus, deduction, status FROM salaries WHERE user_id = ? AND month = ?");
    $stmt_sal->bind_param("is", $uid, $month);
    $stmt_sal->execute();
    $sal_record = $stmt_sal->get_result()->fetch_assoc();
    $stmt_sal->close();

    // Live count from schedules
    $stmt_live = $conn->prepare("
        SELECT COUNT(*) AS shift_count
        FROM schedules
        WHERE user_id = ?
        AND DATE_FORMAT(schedules_date, '%Y-%m') = ?
    ");
    $stmt_live->bind_param("is", $uid, $month);
    $stmt_live->execute();
    $live_data = $stmt_live->get_result()->fetch_assoc();
    $live_shifts = $live_data ? $live_data['shift_count'] : 0;
    $stmt_live->close();

    $status = 'Uncalculated';
    $shifts = $live_shifts;
    $salary = $live_shifts * $emp['rate'];
    $bonus = 0;
    $deduction = 0;

    if ($sal_record) {
        $status = $sal_record['status'];
        $shifts = $sal_record['total_shifts'];
        $salary = $sal_record['calculated_salary'];
        $bonus = floatval($sal_record['bonus']);
        $deduction = floatval($sal_record['deduction']);
        $any_records = true;
        if ($status !== 'Paid') {
            $all_locked = false;
        }
    } else {
        $all_locked = false;
    }

    $net_pay = $salary + $bonus - $deduction;

    $is_incomplete = empty($emp['profile_name']) || empty($emp['contact']) || empty($emp['bank_account_number']) || empty($emp['email']);
    if ($is_incomplete) {
        $has_incomplete = true;
    }

    $payroll_data[] = [
        'user_id' => $uid,
        'username' => $emp['username'],
        'full_name' => $emp['full_name'],
        'rate' => $emp['rate'],
        'bank' => $emp['bank_account_number'],
        'email' => $emp['email'],
        'shifts' => $shifts,
        'salary' => $salary,
        'bonus' => $bonus,
        'deduction' => $deduction,
        'net_pay' => $net_pay,
        'status' => $status,
        'is_incomplete' => $is_incomplete
    ];
}
if (!$any_records) {
    $all_locked = false;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Monthly Payroll</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }

        @media print {
            body * {
                visibility: hidden;
            }
            #payslip-modal-content, #payslip-modal-content * {
                visibility: visible;
            }
            #payslip-modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                color: black !important;
            }
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
    <main class="max-w-[1200px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Monthly Employee Payroll</h1>
                <p class="text-sm text-secondary mt-0.5">Calculate monthly earnings, track statuses, and view payslips.</p>
            </div>

            <!-- Month Selection Controls -->
            <form action="manage_salaries.php" method="GET" class="flex items-center gap-2">
                <label for="month" class="text-xs font-semibold text-secondary uppercase tracking-wider">Payroll Month:</label>
                <input type="month" name="month" id="month" value="<?php echo $month; ?>" onchange="this.form.submit()"
                       class="h-10 px-3 border border-outline-variant text-sm text-on-surface bg-white outline-none rounded-xl font-mono">
            </form>
        </div>

        <?php if ($message): ?>
            <?php 
                $bg_color = "bg-blue-50 border-blue-200 text-blue-800";
                if ($message_type === 'success') {
                    $bg_color = "bg-green-50 border-green-200 text-green-800";
                } elseif ($message_type === 'error') {
                    $bg_color = "bg-red-50 border-red-200 text-red-800";
                }
            ?>
            <div class="border p-4 text-sm rounded-xl <?php echo $bg_color; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Payroll Operations Panel -->
        <div class="bg-white border border-outline-variant p-4 rounded-xl shadow-sm flex flex-wrap gap-3 items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Payroll Status for <?php echo date('F Y', strtotime($month . '-01')); ?>:</span>
                <?php if ($all_locked): ?>
                    <span class="px-2.5 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-xl flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">lock</span> Locked & Paid
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-xs font-bold rounded-xl flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">edit</span> In Draft
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex gap-2">
                <!-- Calculate All Form -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="calculate_all">
                    <button type="submit" <?php echo $all_locked ? 'disabled' : ''; ?>
                            class="h-10 px-4 bg-primary text-white font-semibold hover:bg-neutral-800 disabled:bg-neutral-200 disabled:text-neutral-400 disabled:cursor-not-allowed transition-colors rounded-xl text-sm flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-lg">calculate</span> Calculate All
                    </button>
                </form>

                <!-- Lock/Unlock Form -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <?php if ($all_locked): ?>
                        <input type="hidden" name="action" value="unlock_month">
                        <button type="submit" 
                                class="h-10 px-4 border border-red-200 text-red-600 font-semibold hover:bg-red-50 transition-colors rounded-xl text-sm flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-lg">lock_open</span> Unlock Month
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="lock_month">
                        <div class="flex flex-col items-end gap-1">
                            <button type="submit" <?php echo (!$any_records || $has_incomplete) ? 'disabled' : ''; ?>
                                    class="h-10 px-4 border border-outline-variant font-semibold hover:bg-surface-container-low disabled:border-neutral-200 disabled:text-neutral-400 disabled:cursor-not-allowed transition-colors rounded-xl text-sm flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-lg">lock</span> Finalise & Lock
                            </button>
                            <?php if ($has_incomplete): ?>
                                <span class="text-[10px] text-red-600 font-semibold">Incomplete profiles present</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-4 px-4">Employee</th>
                            <th class="py-4 px-4">Shift Rate</th>
                            <th class="py-4 px-4">Total Shifts</th>
                            <th class="py-4 px-4">Base Salary</th>
                            <th class="py-4 px-4">Bonus / Deduction</th>
                            <th class="py-4 px-4">Net Pay</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php foreach ($payroll_data as $row): ?>
                            <tr class="hover:bg-surface-container-low/20 transition-colors">
                                <td class="py-4 px-4">
                                    <div class="font-bold text-on-surface flex items-center gap-1.5">
                                        <?php echo htmlspecialchars($row['full_name']); ?>
                                        <?php if ($row['is_incomplete']): ?>
                                            <span class="material-symbols-outlined text-red-600 text-base" title="Incomplete Profile">warning</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-secondary font-mono">@<?php echo htmlspecialchars($row['username']); ?></div>
                                </td>
                                <td class="py-4 px-4 font-mono">RM<?php echo number_format($row['rate'], 2); ?></td>
                                <td class="py-4 px-4 font-mono font-semibold"><?php echo $row['shifts']; ?></td>
                                <td class="py-4 px-4 font-mono font-semibold">RM<?php echo number_format($row['salary'], 2); ?></td>
                                <td class="py-4 px-4">
                                    <?php if ($row['status'] !== 'Paid'): ?>
                                        <form method="POST" class="flex gap-1.5 items-center">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="save_adjustment">
                                            <input type="hidden" name="adj_user_id" value="<?php echo $row['user_id']; ?>">
                                            <input type="number" step="0.01" min="0" name="bonus" value="<?php echo number_format($row['bonus'], 2, '.', ''); ?>"
                                                   class="w-20 h-7 px-1.5 text-xs border border-outline-variant rounded-lg text-green-700 font-mono bg-green-50 outline-none focus:border-green-400"
                                                   placeholder="Bonus">
                                            <input type="number" step="0.01" min="0" name="deduction" value="<?php echo number_format($row['deduction'], 2, '.', ''); ?>"
                                                   class="w-20 h-7 px-1.5 text-xs border border-outline-variant rounded-lg text-red-700 font-mono bg-red-50 outline-none focus:border-red-400"
                                                   placeholder="Deduct">
                                            <button type="submit" class="h-7 px-2 bg-primary text-white text-[10px] font-semibold rounded-lg hover:bg-neutral-800 transition-colors">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="text-xs font-mono">
                                            <span class="text-green-700">+RM<?php echo number_format($row['bonus'], 2); ?></span>
                                            <span class="text-secondary mx-1">/</span>
                                            <span class="text-red-700">-RM<?php echo number_format($row['deduction'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 font-mono font-bold text-primary">RM<?php echo number_format($row['net_pay'], 2); ?></td>
                                <td class="py-4 px-4">
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
                                <td class="py-4 px-4 text-right">
                                    <?php if ($row['is_incomplete']): ?>
                                        <button disabled class="text-xs border border-neutral-200 text-neutral-400 px-3 py-1.5 cursor-not-allowed rounded-xl font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">description</span> View Payslip
                                        </button>
                                    <?php else: ?>
                                        <button onclick="openPayslip(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors rounded-xl font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">description</span> View Payslip
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-4">
            <a href="user.php" class="inline-flex items-center justify-center border border-outline-variant text-on-surface font-semibold px-4 h-11 hover:bg-surface-container-low transition-colors rounded-xl">
                Back to Dashboard
            </a>
        </div>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>

    <!-- Payslip View Modal -->
    <div id="payslip-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-outline-variant flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-outline-variant flex justify-between items-center bg-surface-container-low no-print">
                <span class="font-bold text-sm text-secondary">Employee Monthly Payslip</span>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-xl flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">print</span> Print
                    </button>
                    <button onclick="closePayslip()" class="px-3 py-1.5 border border-outline-variant text-xs font-semibold rounded-xl flex items-center gap-1 hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-sm">close</span> Close
                    </button>
                </div>
            </div>

            <div id="payslip-modal-content" class="p-8 space-y-6 overflow-y-auto bg-white text-black font-sans">
                <!-- Brand Header -->
                <div class="flex justify-between items-start border-b-2 border-primary pb-4">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight uppercase">He&She Coffee</h2>
                        <p class="text-[10px] text-gray-500 uppercase mt-0.5">Premium Cafe & Roastery</p>
                    </div>
                    <div class="text-right">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400">Salary Statement</h3>
                        <p class="text-xs font-mono font-semibold mt-1" id="payslip-month"></p>
                    </div>
                </div>

                <!-- Profile Info -->
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Employee Name</span>
                        <strong class="text-sm font-semibold" id="payslip-name"></strong>
                        <span class="text-gray-500 block" id="payslip-email"></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Bank Account</span>
                        <strong class="font-mono" id="payslip-bank"></strong>
                    </div>
                </div>

                <!-- Breakdown Table -->
                <table class="w-full text-left border-collapse text-xs mt-4">
                    <thead>
                        <tr class="border-b border-gray-300 font-bold uppercase tracking-wider text-gray-500">
                            <th class="py-2">Description</th>
                            <th class="py-2 text-right">Rate</th>
                            <th class="py-2 text-right">Units (Shifts)</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-3 font-semibold">Standard Shift Duties</td>
                            <td class="py-3 text-right font-mono" id="payslip-rate-cell"></td>
                            <td class="py-3 text-right font-mono" id="payslip-shifts-cell"></td>
                            <td class="py-3 text-right font-mono font-semibold" id="payslip-subtotal-cell"></td>
                        </tr>
                        <tr id="payslip-bonus-row" class="hidden">
                            <td class="py-3 text-green-700 font-semibold">Bonus</td>
                            <td class="py-3"></td>
                            <td class="py-3"></td>
                            <td class="py-3 text-right font-mono font-semibold text-green-700" id="payslip-bonus-cell"></td>
                        </tr>
                        <tr id="payslip-deduction-row" class="hidden">
                            <td class="py-3 text-red-700 font-semibold">Deduction</td>
                            <td class="py-3"></td>
                            <td class="py-3"></td>
                            <td class="py-3 text-right font-mono font-semibold text-red-700" id="payslip-deduction-cell"></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Total Summary -->
                <div class="border-t border-gray-300 pt-4 flex justify-between items-center text-sm font-bold">
                    <span>Total Net Salary Pay</span>
                    <span class="text-lg font-mono text-primary" id="payslip-total-cell"></span>
                </div>

                <!-- Footer Signatures -->
                <div class="pt-12 grid grid-cols-2 gap-8 text-[10px] text-gray-400">
                    <div class="border-t border-dashed border-gray-300 pt-2 text-center">
                        Authorized Manager Signature
                    </div>
                    <div class="border-t border-dashed border-gray-300 pt-2 text-center">
                        Employee Acknowledgment
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openPayslip(data) {
        document.getElementById('payslip-month').innerText = '<?php echo date('F Y', strtotime($month . "-01")); ?>';
        document.getElementById('payslip-name').innerText = data.full_name;
        document.getElementById('payslip-email').innerText = data.email ? data.email : 'No email registered';
        document.getElementById('payslip-bank').innerText = data.bank ? data.bank : 'N/A';
        
        const rateVal = parseFloat(data.rate);
        const shiftsVal = parseInt(data.shifts);
        const salaryVal = parseFloat(data.salary);
        const bonusVal = parseFloat(data.bonus) || 0;
        const deductionVal = parseFloat(data.deduction) || 0;
        const netVal = salaryVal + bonusVal - deductionVal;

        document.getElementById('payslip-rate-cell').innerText = 'RM ' + rateVal.toFixed(2);
        document.getElementById('payslip-shifts-cell').innerText = shiftsVal.toString();
        document.getElementById('payslip-subtotal-cell').innerText = 'RM ' + salaryVal.toFixed(2);

        const bonusRow = document.getElementById('payslip-bonus-row');
        const deductRow = document.getElementById('payslip-deduction-row');
        if (bonusVal > 0) {
            bonusRow.classList.remove('hidden');
            document.getElementById('payslip-bonus-cell').innerText = '+ RM ' + bonusVal.toFixed(2);
        } else { bonusRow.classList.add('hidden'); }
        if (deductionVal > 0) {
            deductRow.classList.remove('hidden');
            document.getElementById('payslip-deduction-cell').innerText = '- RM ' + deductionVal.toFixed(2);
        } else { deductRow.classList.add('hidden'); }

        document.getElementById('payslip-total-cell').innerText = 'RM ' + netVal.toFixed(2);
        document.getElementById('payslip-modal').classList.remove('hidden');
    }

    function closePayslip() {
        document.getElementById('payslip-modal').classList.add('hidden');
    }

    // Close modal if clicked outside content card
    document.getElementById('payslip-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePayslip();
        }
    });
    </script>
</body>
</html>
