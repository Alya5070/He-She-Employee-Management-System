<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Helper: Get the 7 dates of the week for a given start date (Monday to Sunday)
function getWeekDates($start_date) {
    $dates = [];
    $start = new DateTime($start_date);
    // Force to Monday if it's not already
    if ($start->format('N') != 1) {
        $start->modify('this monday');
    }
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
    return $dates;
}

// Helper: Recalculate and update total hours worked for an employee (5 hours per shift)
function recalculateEmployeeHours($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total_shifts FROM schedules WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $total_shifts = $data ? $data['total_shifts'] : 0;
    $stmt->close();

    $hours = $total_shifts * 5.00;

    $upd = $conn->prepare("UPDATE employee_profiles SET hours_worked = ? WHERE user_id = ?");
    $upd->bind_param("di", $hours, $user_id);
    $upd->execute();
    $upd->close();
}

// Default target week start (Monday)
$week_start = isset($_GET['week_start']) ? $_GET['week_start'] : '';
if (empty($week_start)) {
    $d = new DateTime();
    if ($d->format('N') != 1) {
        $d->modify('this monday');
    }
    $week_start = $d->format('Y-m-d');
} else {
    $d = new DateTime($week_start);
    if ($d->format('N') != 1) {
        $d->modify('this monday');
    }
    $week_start = $d->format('Y-m-d');
}

$week_dates = getWeekDates($week_start);
$prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
        exit;
    }

    if ($_POST['action'] === 'add_shift') {
        $user_id = intval($_POST['user_id']);
        $date = $_POST['date'];
        $shift_time = strtolower(trim($_POST['shift_time']));

        $allowed_shifts = ['morning', 'evening', 'night'];
        if (!in_array($shift_time, $allowed_shifts)) {
            echo json_encode(['success' => false, 'message' => 'Invalid shift type.']);
            exit;
        }

        // Check if employee is already working this specific shift
        $stmt = $conn->prepare("SELECT id FROM schedules WHERE user_id = ? AND schedules_date = ? AND LOWER(schedules_time) = ?");
        $stmt->bind_param("iss", $user_id, $date, $shift_time);
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res->fetch_assoc();
        $stmt->close();

        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Barista is already scheduled for this shift.']);
            exit;
        }

        // Block shift if employee has an approved leave on this date
        $leave_chk = $conn->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND leave_date = ? AND status = 'Approved'");
        $leave_chk->bind_param("is", $user_id, $date);
        $leave_chk->execute();
        $on_leave = $leave_chk->get_result()->fetch_assoc();
        $leave_chk->close();

        if ($on_leave) {
            echo json_encode(['success' => false, 'message' => 'This barista has an approved leave on ' . date('D d M Y', strtotime($date)) . '. Cannot assign a shift.']);
            exit;
        }

        // Check availability preference — warn but do not block
        $day_of_week = intval(date('N', strtotime($date))); // 1=Mon ... 7=Sun
        $avail_chk = $conn->prepare("SELECT is_available FROM availability_preferences WHERE user_id = ? AND day_of_week = ? AND time_slot = ?");
        $avail_chk->bind_param("iis", $user_id, $day_of_week, $shift_time);
        $avail_chk->execute();
        $avail_row = $avail_chk->get_result()->fetch_assoc();
        $avail_chk->close();

        $avail_warning = false;
        if ($avail_row && intval($avail_row['is_available']) === 0) {
            // Only block if force_confirm flag not sent
            if (!isset($_POST['force_confirm']) || $_POST['force_confirm'] !== '1') {
                echo json_encode(['success' => false, 'availability_warning' => true, 'message' => 'This barista marked themselves unavailable for ' . ucfirst($shift_time) . ' shifts on ' . date('l', strtotime($date)) . 's. Confirm to schedule anyway.']);
                exit;
            }
        }

        // Insert new shift schedule
        $stmt = $conn->prepare("INSERT INTO schedules (user_id, schedules_date, schedules_time) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $date, $shift_time);
        if ($stmt->execute()) {
            recalculateEmployeeHours($conn, $user_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database save error.']);
        }
        $stmt->close();
        exit;
    }

    if ($_POST['action'] === 'remove_shift') {
        $schedule_id = intval($_POST['schedule_id']);
        
        $find = $conn->prepare("SELECT user_id FROM schedules WHERE id = ?");
        $find->bind_param("i", $schedule_id);
        $find->execute();
        $f_res = $find->get_result()->fetch_assoc();
        $user_id = $f_res ? $f_res['user_id'] : 0;
        $find->close();

        $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
        $stmt->bind_param("i", $schedule_id);
        if ($stmt->execute()) {
            if ($user_id > 0) {
                recalculateEmployeeHours($conn, $user_id);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database delete error.']);
        }
        $stmt->close();
        exit;
    }

    if ($_POST['action'] === 'copy_previous_week') {
        $current_week_start = $_POST['week_start'];
        $current_monday = new DateTime($current_week_start);
        if ($current_monday->format('N') != 1) {
            $current_monday->modify('this monday');
        }
        $current_week_dates = [];
        $temp = clone $current_monday;
        for ($i = 0; $i < 7; $i++) {
            $current_week_dates[] = $temp->format('Y-m-d');
            $temp->modify('+1 day');
        }

        $prev_monday = clone $current_monday;
        $prev_monday->modify('-7 days');
        $prev_week_dates = [];
        $temp = clone $prev_monday;
        for ($i = 0; $i < 7; $i++) {
            $prev_week_dates[] = $temp->format('Y-m-d');
            $temp->modify('+1 day');
        }

        $placeholders = implode(',', array_fill(0, 7, '?'));
        $types = str_repeat('s', 7);
        $stmt = $conn->prepare("SELECT user_id, schedules_date, schedules_time FROM schedules WHERE schedules_date IN ($placeholders)");
        $stmt->bind_param($types, ...$prev_week_dates);
        $stmt->execute();
        $res = $stmt->get_result();
        $prev_schedules = [];
        while ($row = $res->fetch_assoc()) {
            $prev_schedules[] = $row;
        }
        $stmt->close();

        if (empty($prev_schedules)) {
            echo json_encode(['success' => false, 'message' => 'No schedules found in the previous week to copy.']);
            exit;
        }

        $affected_users = [];
        $conn->begin_transaction();
        try {
            foreach ($prev_schedules as $sched) {
                $prev_date = new DateTime($sched['schedules_date']);
                $day_idx = intval($prev_date->format('N')) - 1;
                $target_date = $current_week_dates[$day_idx];
                $uid = $sched['user_id'];
                $st = $sched['schedules_time'];

                $chk = $conn->prepare("SELECT id FROM schedules WHERE user_id = ? AND schedules_date = ? AND LOWER(schedules_time) = LOWER(?)");
                $chk->bind_param("iss", $uid, $target_date, $st);
                $chk->execute();
                $chk_res = $chk->get_result();
                $exists = $chk_res->fetch_assoc();
                $chk->close();

                if (!$exists) {
                     $ins = $conn->prepare("INSERT INTO schedules (user_id, schedules_date, schedules_time) VALUES (?, ?, ?)");
                     $ins->bind_param("iss", $uid, $target_date, $st);
                     $ins->execute();
                     $ins->close();
                     $affected_users[] = $uid;
                }
            }
            $conn->commit();
            
            $unique_users = array_unique($affected_users);
            foreach ($unique_users as $uid) {
                recalculateEmployeeHours($conn, $uid);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database copy error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'auto_schedule') {
        $target_week_start = $_POST['week_start'];
        $current_monday = new DateTime($target_week_start);
        if ($current_monday->format('N') != 1) {
            $current_monday->modify('this monday');
        }
        
        $current_week_dates = [];
        $temp = clone $current_monday;
        for ($i = 0; $i < 7; $i++) {
            $current_week_dates[] = $temp->format('Y-m-d');
            $temp->modify('+1 day');
        }

        // Begin transaction to guarantee consistent changes
        $conn->begin_transaction();
        try {
            // 1. Clear all existing schedules for this week
            $placeholders = implode(',', array_fill(0, 7, '?'));
            $types = str_repeat('s', 7);
            $del_stmt = $conn->prepare("DELETE FROM schedules WHERE schedules_date IN ($placeholders)");
            $del_stmt->bind_param($types, ...$current_week_dates);
            $del_stmt->execute();
            $del_stmt->close();

            // 2. Fetch all active employees
            $emp_res = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'Employee'");
            $employees_list = $emp_res->fetch_all(MYSQLI_ASSOC);

            if (empty($employees_list)) {
                echo json_encode(['success' => false, 'message' => 'No active employees found to schedule.']);
                exit;
            }

            // 3. Load availability preferences
            // Default to available (1) for everyone
            $avail = [];
            foreach ($employees_list as $emp) {
                $uid = $emp['user_id'];
                for ($d = 1; $d <= 7; $d++) {
                    foreach (['morning', 'evening', 'night'] as $s) {
                        $avail[$uid][$d][$s] = 1;
                    }
                }
            }
            
            // Load custom overrides from database
            $pref_res = $conn->query("SELECT user_id, day_of_week, time_slot, is_available FROM availability_preferences");
            while ($p_row = $pref_res->fetch_assoc()) {
                $uid = $p_row['user_id'];
                $d = $p_row['day_of_week'];
                $s = strtolower($p_row['time_slot']);
                if (isset($avail[$uid])) {
                    $avail[$uid][$d][$s] = intval($p_row['is_available']);
                }
            }
            $pref_res->close();

            // 4. Load approved leave requests for the week
            $leaves = [];
            $leave_res = $conn->prepare("SELECT user_id, leave_date FROM leave_requests WHERE status = 'Approved' AND leave_date IN ($placeholders)");
            $leave_res->bind_param($types, ...$current_week_dates);
            $leave_res->execute();
            $l_rows = $leave_res->get_result()->fetch_all(MYSQLI_ASSOC);
            $leave_res->close();
            
            foreach ($l_rows as $lr) {
                $leaves[$lr['user_id']][$lr['leave_date']] = true;
            }

            // Track weekly workload (shift counts) for even distribution
            $workload = [];
            foreach ($employees_list as $emp) {
                $workload[$emp['user_id']] = 0;
            }

            $slots_list = ['morning', 'evening', 'night'];
            $ins_stmt = $conn->prepare("INSERT INTO schedules (user_id, schedules_date, schedules_time) VALUES (?, ?, ?)");

            // Populate shifts day-by-day
            foreach ($current_week_dates as $date_idx => $date) {
                $day_of_week = $date_idx + 1; // 1=Monday ... 7=Sunday
                
                // Track daily shifts to prevent double booking on the same day
                $daily_allocation = [];
                foreach ($employees_list as $emp) {
                    $daily_allocation[$emp['user_id']] = 0;
                }

                foreach ($slots_list as $slot) {
                    $candidates = [];
                    foreach ($employees_list as $emp) {
                        $uid = $emp['user_id'];
                        $is_pref_available = isset($avail[$uid][$day_of_week][$slot]) ? $avail[$uid][$day_of_week][$slot] : 1;
                        $on_leave = isset($leaves[$uid][$date]);
                        $already_scheduled = ($daily_allocation[$uid] > 0);

                        if ($is_pref_available === 1 && !$on_leave && !$already_scheduled) {
                            $candidates[] = $uid;
                        }
                    }

                    if (!empty($candidates)) {
                        // Sort by workload (fewest shifts first) to balance allocation, shuffling to randomize tie-breakers
                        shuffle($candidates);
                        usort($candidates, function($a, $b) use ($workload) {
                            return $workload[$a] - $workload[$b];
                        });

                        $selected_uid = $candidates[0];

                        $ins_stmt->bind_param("iss", $selected_uid, $date, $slot);
                        $ins_stmt->execute();

                        $workload[$selected_uid]++;
                        $daily_allocation[$selected_uid]++;
                    }
                }
            }
            $ins_stmt->close();
            $conn->commit();

            // Update hours worked in profiles
            foreach ($employees_list as $emp) {
                recalculateEmployeeHours($conn, $emp['user_id']);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Auto-scheduling failed: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Fetch all employees for dropdown options
$employees_res = $conn->query("SELECT user_id, username, full_name FROM users WHERE role = 'Employee' ORDER BY full_name");
$employees = [];
while ($row = $employees_res->fetch_assoc()) {
    $employees[] = $row;
}

// Fetch all schedules for this week and group them by [shift_time][date]
$placeholders = implode(',', array_fill(0, count($week_dates), '?'));
$types = str_repeat('s', count($week_dates));
$stmt = $conn->prepare("
    SELECT s.id AS schedule_id, s.user_id, s.schedules_date, s.schedules_time, u.full_name, u.username
    FROM schedules s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.schedules_date IN ($placeholders)
");
$stmt->bind_param($types, ...$week_dates);
$stmt->execute();
$schedules_res = $stmt->get_result();

$grid = [
    'morning' => [],
    'evening' => [],
    'night' => []
];
$weekly_shift_counts = [];
$daily_shift_counts = [];

while ($row = $schedules_res->fetch_assoc()) {
    $st = strtolower($row['schedules_time']);
    $uid = $row['user_id'];
    $date = $row['schedules_date'];

    if (isset($grid[$st])) {
        $grid[$st][$date][] = [
            'schedule_id' => $row['schedule_id'],
            'user_id' => $uid,
            'full_name' => $row['full_name'],
            'username' => $row['username']
        ];
    }

    // Track weekly shift counts
    if (!isset($weekly_shift_counts[$uid])) {
        $weekly_shift_counts[$uid] = 0;
    }
    $weekly_shift_counts[$uid]++;

    // Track daily shift counts
    if (!isset($daily_shift_counts[$uid][$date])) {
        $daily_shift_counts[$uid][$date] = 0;
    }
    $daily_shift_counts[$uid][$date]++;
}
$stmt->close();

$shifts_define = [
    'morning' => ['label' => 'Morning', 'hours' => '08:00 - 13:00'],
    'evening' => ['label' => 'Evening', 'hours' => '13:00 - 18:00'],
    'night' => ['label' => 'Night', 'hours' => '18:00 - 23:00']
];

// Calculate employee weekly allocation counts & statuses
$unassigned_baristas = [];
$low_baristas = [];
$optimal_baristas = [];
$overworked_baristas = [];

foreach ($employees as $emp) {
    $uid = $emp['user_id'];
    $count = isset($weekly_shift_counts[$uid]) ? $weekly_shift_counts[$uid] : 0;
    $emp_info = [
        'name' => $emp['full_name'],
        'username' => $emp['username'],
        'count' => $count
    ];
    
    if ($count === 0) {
        $unassigned_baristas[] = $emp_info;
    } elseif ($count < 3) {
        $low_baristas[] = $emp_info;
    } elseif ($count <= 5) {
        $optimal_baristas[] = $emp_info;
    } else {
        $overworked_baristas[] = $emp_info;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Weekly Calendar Grid</title>
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
            @page {
                size: landscape;
                margin: 6mm;
            }
            body {
                background: white !important;
                color: black !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            header, footer, nav, button, .relative, form, .print-hide, .grid, h1 + p, hr, .h-6.w-px {
                display: none !important;
            }
            main {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .overflow-x-auto {
                overflow: visible !important;
            }
            .border {
                border-color: #d1d5db !important;
            }
            .shadow-sm, .shadow-xl {
                box-shadow: none !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
            }
            th, td {
                border: 1px solid #d1d5db !important;
                page-break-inside: avoid;
                padding: 4px !important;
                font-size: 11px !important;
            }
            th {
                font-size: 11px !important;
                padding: 6px 4px !important;
            }
            h1 {
                font-size: 20px !important;
                margin: 0 0 10px 0 !important;
            }
            /* force colors in print */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .sticky {
                position: static !important;
            }
            .min-h-\[120px\] {
                min-h: auto !important;
                height: auto !important;
            }
            .min-h-\[90px\] {
                min-h: auto !important;
                height: auto !important;
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
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_schedule.php">Schedules</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_leaves.php">Leaves</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Manager</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1400px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Weekly Schedule Calendar</h1>
                <p class="text-sm text-secondary mt-0.5">Weekly roster. Assign baristas to shifts or remove them as needed.</p>
            </div>
            
            <!-- Week Selector & Actions Controls -->
            <div class="flex items-center gap-2 print-hide flex-nowrap">
                <!-- Highlight Filter Dropdown -->
                <div class="flex items-center gap-1 mr-1">
                    <label for="highlight_barista" class="text-[10px] font-semibold text-secondary uppercase tracking-wider whitespace-nowrap">Highlight:</label>
                    <select id="highlight_barista" onchange="applyHighlightFilter(this.value)"
                            class="h-10 px-2 border border-outline-variant text-xs text-on-surface bg-white outline-none rounded-xl">
                        <option value="">Show All</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp['username']); ?>">
                                <?php echo htmlspecialchars($emp['full_name']); ?> (@<?php echo htmlspecialchars($emp['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button onclick="autoSchedule()" class="h-10 px-2.5 bg-primary text-white hover:bg-neutral-800 transition-colors rounded-xl font-semibold flex items-center gap-1 text-xs whitespace-nowrap" title="Auto-schedule shifts for this week">
                    <span class="material-symbols-outlined text-base text-white">auto_awesome</span> Auto-Schedule
                </button>
                <button onclick="copyPreviousWeek()" class="h-10 px-2.5 border border-outline-variant hover:bg-surface-container-low transition-colors rounded-xl font-semibold flex items-center gap-1 text-xs whitespace-nowrap" title="Copy last week's shifts to this week">
                    <span class="material-symbols-outlined text-base">content_copy</span> Copy Last Week
                </button>
                <button onclick="window.print()" class="h-10 px-2.5 border border-outline-variant hover:bg-surface-container-low transition-colors rounded-xl font-semibold flex items-center gap-1 text-xs whitespace-nowrap">
                    <span class="material-symbols-outlined text-base">print</span> Print Roster
                </button>
                <div class="h-6 w-px bg-outline-variant mx-0.5"></div>
                <div style="display: inline-flex; flex-direction: row; flex-wrap: nowrap; align-items: center; gap: 6px;">
                    <a href="manage_schedule.php?week_start=<?php echo $prev_week; ?>" 
                       class="h-10 w-10 border border-outline-variant flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl" title="Previous Week">
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </a>
                    
                    <form action="manage_schedule.php" method="GET" style="display: inline-flex; margin: 0; align-items: center;">
                        <input type="date" name="week_start" id="week_start_picker" value="<?php echo $week_start; ?>" onchange="this.form.submit()"
                               class="h-10 px-3 border border-outline-variant text-sm text-on-surface bg-white outline-none rounded-xl font-mono">
                    </form>

                    <a href="manage_schedule.php?week_start=<?php echo $next_week; ?>" 
                       class="h-10 w-10 border border-outline-variant flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl" title="Next Week">
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Allocation & Alerts Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Unassigned Card -->
            <div class="bg-white border border-outline-variant p-4 rounded-xl shadow-sm space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Unassigned</span>
                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded">Alert</span>
                </div>
                <div class="text-2xl font-bold text-on-surface"><?php echo count($unassigned_baristas); ?></div>
                <div class="text-xs text-secondary truncate">
                    <?php 
                    if (empty($unassigned_baristas)) {
                        echo "All scheduled";
                    } else {
                        echo implode(', ', array_map(fn($b) => $b['name'], $unassigned_baristas));
                    }
                    ?>
                </div>
            </div>

            <!-- Under-scheduled Card -->
            <div class="bg-white border border-outline-variant p-4 rounded-xl shadow-sm space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Under-scheduled (&lt; 3)</span>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold rounded">Low</span>
                </div>
                <div class="text-2xl font-bold text-on-surface"><?php echo count($low_baristas); ?></div>
                <div class="text-xs text-secondary truncate">
                    <?php 
                    if (empty($low_baristas)) {
                        echo "None";
                    } else {
                        echo implode(', ', array_map(fn($b) => $b['name'] . " (" . $b['count'] . ")", $low_baristas));
                    }
                    ?>
                </div>
            </div>

            <!-- Optimal Allocation Card -->
            <div class="bg-white border border-outline-variant p-4 rounded-xl shadow-sm space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Optimal (3-5 Shifts)</span>
                    <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded">Good</span>
                </div>
                <div class="text-2xl font-bold text-on-surface"><?php echo count($optimal_baristas); ?></div>
                <div class="text-xs text-secondary truncate">
                    <?php echo count($optimal_baristas) . " baristas in green zone"; ?>
                </div>
            </div>

            <!-- Overworked Card -->
            <div class="bg-white border border-outline-variant p-4 rounded-xl shadow-sm space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-secondary uppercase tracking-wider">Overworked (&gt; 5)</span>
                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded">High</span>
                </div>
                <div class="text-2xl font-bold text-on-surface"><?php echo count($overworked_baristas); ?></div>
                <div class="text-xs text-secondary truncate text-red-600 font-semibold">
                    <?php 
                    if (empty($overworked_baristas)) {
                        echo "None";
                    } else {
                        echo implode(', ', array_map(fn($b) => $b['name'] . " (" . $b['count'] . ")", $overworked_baristas));
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Weekly Calendar Grid -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse table-fixed text-left">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-4 px-4 w-[160px] sticky left-0 bg-surface-container-low z-10 border-r border-outline-variant">Shift / Time</th>
                            <?php foreach ($week_dates as $date): 
                                $day_name = date('D', strtotime($date));
                                $day_num = date('d M', strtotime($date));
                                $is_today = ($date === date('Y-m-d')) ? 'bg-amber-50 text-amber-900 border-x border-amber-300' : '';
                            ?>
                                <th class="py-3 px-3 text-center <?php echo $is_today; ?>">
                                    <div class="font-bold text-on-surface"><?php echo $day_name; ?></div>
                                    <div class="text-[10px] text-secondary font-mono mt-0.5"><?php echo $day_num; ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php foreach ($shifts_define as $st_key => $st_val): ?>
                            <tr class="align-top hover:bg-surface-container-low/20 transition-colors">
                                <!-- Shift Info Column -->
                                <td class="py-4 px-4 font-semibold sticky left-0 bg-white border-r border-outline-variant z-10">
                                    <div class="text-on-surface"><?php echo $st_val['label']; ?></div>
                                    <div class="text-[10px] text-secondary font-mono mt-0.5"><?php echo $st_val['hours']; ?></div>
                                </td>

                                <!-- Days Columns -->
                                <?php foreach ($week_dates as $date): 
                                    $assigned_baristas = isset($grid[$st_key][$date]) ? $grid[$st_key][$date] : [];
                                    $assigned_user_ids = array_column($assigned_baristas, 'user_id');
                                ?>
                                    <td class="py-3 px-3 min-h-[120px] bg-slate-50/20 border-r border-outline-variant last:border-r-0">
                                        <div class="flex flex-col gap-2 min-h-[90px] justify-between h-full">
                                            <?php 
                                            $coverage_count = count($assigned_baristas);
                                            ?>
                                            <!-- Coverage Badge -->
                                            <div class="flex justify-between items-center border-b border-outline-variant/30 pb-1">
                                                <span class="text-[9px] uppercase tracking-wider font-semibold text-secondary">Coverage</span>
                                                <?php if ($coverage_count === 0): ?>
                                                    <span class="px-1.5 py-0.5 bg-red-50 text-red-600 text-[8px] font-bold rounded-lg border border-red-100 uppercase tracking-wider">0 Staffed</span>
                                                <?php elseif ($coverage_count === 1): ?>
                                                    <span class="px-1.5 py-0.5 bg-amber-50 text-amber-600 text-[8px] font-bold rounded-lg border border-amber-100 uppercase tracking-wider">1 Staffed</span>
                                                <?php else: ?>
                                                    <span class="px-1.5 py-0.5 bg-green-50 text-green-600 text-[8px] font-bold rounded-lg border border-green-100 uppercase tracking-wider"><?php echo $coverage_count; ?> Staffed</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- List of Assigned Baristas (Event Chips) -->
                                            <div class="space-y-1.5 flex-grow">
                                                <?php if (empty($assigned_baristas)): ?>
                                                    <div class="text-[11px] text-secondary/60 text-center py-4 border border-dashed border-outline-variant/60 rounded-lg select-none">
                                                        Empty
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($assigned_baristas as $barista): 
                                                         $uid = $barista['user_id'];
                                                         $has_double = (isset($daily_shift_counts[$uid][$date]) && $daily_shift_counts[$uid][$date] > 1);
                                                         $has_overwork = (isset($weekly_shift_counts[$uid]) && $weekly_shift_counts[$uid] > 5);
                                                         
                                                         $chip_class = "bg-white border-outline-variant";
                                                         if ($st_key === 'morning') {
                                                             $chip_class = "border-l-4 border-l-amber-400 bg-amber-50/40 border-y border-r border-outline-variant";
                                                         } elseif ($st_key === 'evening') {
                                                             $chip_class = "border-l-4 border-l-blue-400 bg-blue-50/40 border-y border-r border-outline-variant";
                                                         } elseif ($st_key === 'night') {
                                                             $chip_class = "border-l-4 border-l-zinc-800 bg-neutral-100 border-y border-r border-outline-variant";
                                                         }
                                                     ?>
                                                         <div data-username="<?php echo htmlspecialchars($barista['username']); ?>" class="barista-card flex flex-col gap-1 px-2.5 py-1.5 hover:border-primary transition-all rounded-lg shadow-sm <?php echo $chip_class; ?>">
                                                            <div class="flex items-center justify-between gap-1 w-full">
                                                                <div class="truncate">
                                                                    <div class="text-xs font-semibold text-on-surface truncate" title="<?php echo htmlspecialchars($barista['full_name']); ?>">
                                                                        <?php echo htmlspecialchars($barista['full_name']); ?>
                                                                    </div>
                                                                </div>
                                                                <button onclick="removeBarista(this, <?php echo $barista['schedule_id']; ?>)" 
                                                                        class="text-secondary hover:text-red-600 rounded-full h-5 w-5 flex items-center justify-center transition-colors flex-shrink-0"
                                                                        title="Remove from Shift">
                                                                    <span class="material-symbols-outlined text-sm">close</span>
                                                                </button>
                                                            </div>
                                                            <?php if ($has_double || $has_overwork): ?>
                                                                <div class="flex flex-wrap gap-1 mt-0.5">
                                                                    <?php if ($has_double): ?>
                                                                        <span class="px-1 py-0.5 bg-red-50 text-red-700 text-[8px] font-bold rounded border border-red-100 uppercase tracking-wider">Double</span>
                                                                    <?php endif; ?>
                                                                    <?php if ($has_overwork): ?>
                                                                        <span class="px-1 py-0.5 bg-amber-50 text-amber-700 text-[8px] font-bold rounded border border-amber-100 uppercase tracking-wider">Overwork</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Add Barista Trigger Button & Menu -->
                                            <div class="relative pt-1 border-t border-outline-variant/30">
                                                <button onclick="toggleAddMenu(this)" 
                                                        class="w-full h-7 border border-outline-variant bg-white hover:bg-surface-container-low text-[11px] font-semibold text-secondary hover:text-primary transition-all flex items-center justify-center gap-1 rounded-lg">
                                                    <span class="material-symbols-outlined text-xs">add</span> Add Barista
                                                </button>

                                                <!-- Inline Popover Barista Select -->
                                                <div class="hidden absolute z-20 bottom-[105%] left-0 w-full bg-white border border-outline-variant p-2 rounded-xl shadow-xl space-y-1 max-h-48 overflow-y-auto">
                                                    <?php 
                                                    $available_found = false;
                                                    foreach ($employees as $emp):
                                                        if (in_array($emp['user_id'], $assigned_user_ids)) continue;
                                                        $available_found = true;

                                                        $pot_uid = $emp['user_id'];
                                                        $pot_double = (isset($daily_shift_counts[$pot_uid][$date]) && $daily_shift_counts[$pot_uid][$date] >= 1);
                                                        $pot_overwork = (isset($weekly_shift_counts[$pot_uid]) && $weekly_shift_counts[$pot_uid] >= 5);
                                                        
                                                        $suffix = "";
                                                        if ($pot_double && $pot_overwork) {
                                                            $suffix = " (Double & Max)";
                                                        } elseif ($pot_double) {
                                                            $suffix = " (Double)";
                                                        } elseif ($pot_overwork) {
                                                            $suffix = " (Max)";
                                                        }
                                                    ?>
                                                        <button onclick="addBaristaToShift(this, <?php echo $emp['user_id']; ?>, '<?php echo $date; ?>', '<?php echo $st_key; ?>')" 
                                                                class="w-full text-left px-2.5 py-1.5 text-xs text-on-surface hover:bg-surface-container-low rounded-lg truncate font-medium flex justify-between items-center">
                                                            <span class="truncate"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                                            <?php if (!empty($suffix)): ?>
                                                                <span class="text-[8px] font-bold text-red-500 uppercase tracking-wider flex-shrink-0 ml-1"><?php echo $suffix; ?></span>
                                                            <?php endif; ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if (!$available_found): ?>
                                                        <div class="text-[10px] text-secondary text-center py-2 select-none">
                                                            All assigned
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-4 print-hide">
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

    <script>
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    function autoSchedule() {
        if (!confirm('⚠️ This will clear this week\'s current schedule and auto-generate new shifts based on employee availability. Proceed?')) {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'auto_schedule');
        fd.append('week_start', '<?php echo $week_start; ?>');
        fd.append('csrf_token', csrfToken);

        fetch('manage_schedule.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Connection error. Please try again.');
        });
    }

    function copyPreviousWeek() {
        if (!confirm('Are you sure you want to copy the entire schedule from the previous week? This will not overwrite existing schedules.')) {
            return;
        }
        const fd = new FormData();
        fd.append('action', 'copy_previous_week');
        fd.append('week_start', '<?php echo $week_start; ?>');
        fd.append('csrf_token', csrfToken);

        fetch('manage_schedule.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Connection error. Please try again.');
        });
    }
    
    // Toggle popover menu
    function toggleAddMenu(btn) {
        // Close all dropdowns
        document.querySelectorAll('td relative div').forEach(div => {
            if (div !== btn.nextElementSibling) {
                div.classList.add('hidden');
            }
        });
        const menu = btn.nextElementSibling;
        menu.classList.toggle('hidden');

        // Setup click outside listener
        const closeMenu = (e) => {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                document.removeEventListener('click', closeMenu);
            }
        };
        if (!menu.classList.contains('hidden')) {
            document.addEventListener('click', closeMenu);
        }
    }

    // Add barista shift via AJAX
    function addBaristaToShift(item, userId, date, shiftTime, forceConfirm) {
        const menu = item.parentElement;
        const btn = menu.previousElementSibling;

        menu.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<span>Saving…</span>';

        const fd = new FormData();
        fd.append('action', 'add_shift');
        fd.append('user_id', userId);
        fd.append('date', date);
        fd.append('shift_time', shiftTime);
        fd.append('csrf_token', csrfToken);
        if (forceConfirm) {
            fd.append('force_confirm', '1');
        }

        fetch('manage_schedule.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else if (data.availability_warning) {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-xs">add</span> Add Barista';
                if (confirm('⚠️ ' + data.message)) {
                    addBaristaToShift(item, userId, date, shiftTime, true);
                }
            } else {
                alert('Error: ' + data.message);
                location.reload();
            }
        })
        .catch(err => {
            alert('Connection error. Please try again.');
            location.reload();
        });
    }

    // Remove barista shift via AJAX
    function removeBarista(btn, scheduleId) {
        if (!confirm('Are you sure you want to remove this barista from this shift?')) {
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-xs">hourglass_empty</span>';

        const fd = new FormData();
        fd.append('action', 'remove_shift');
        fd.append('schedule_id', scheduleId);
        fd.append('csrf_token', csrfToken);

        fetch('manage_schedule.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
                location.reload();
            }
        })
        .catch(err => {
            alert('Connection error. Please try again.');
            location.reload();
        });
    }

    function applyHighlightFilter(username) {
        const cards = document.querySelectorAll('.barista-card');
        if (!username) {
            cards.forEach(card => {
                card.style.opacity = '1';
                card.classList.remove('ring-4', 'ring-primary', 'ring-offset-1');
            });
            return;
        }
        cards.forEach(card => {
            if (card.getAttribute('data-username') === username) {
                card.style.opacity = '1';
                card.classList.add('ring-4', 'ring-primary', 'ring-offset-1');
            } else {
                card.style.opacity = '0.35';
                card.classList.remove('ring-4', 'ring-primary', 'ring-offset-1');
            }
        });
    }
    </script>
</body>
</html>