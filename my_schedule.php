<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is an employee
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}

// Helper: Get the 7 dates of the week for a given start date (Monday to Sunday)
function getWeekDates($start_date) {
    $dates = [];
    $start = new DateTime($start_date);
    if ($start->format('N') != 1) {
        $start->modify('this monday');
    }
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
    return $dates;
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

$username = $_SESSION['username'];
// Get current employee's user_id
$user_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$my_user_id = $user_data ? $user_data['user_id'] : 0;
$user_stmt->close();

// Fetch all schedules for this week to display other baristas on shift together
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
$my_shift_count = 0;

while ($row = $schedules_res->fetch_assoc()) {
    $st = strtolower($row['schedules_time']);
    $date = $row['schedules_date'];
    
    if (isset($grid[$st])) {
        $grid[$st][$date][] = [
            'user_id' => intval($row['user_id']),
            'full_name' => $row['full_name'],
            'username' => $row['username']
        ];
    }
    
    if (intval($row['user_id']) === $my_user_id) {
        $my_shift_count++;
    }
}
$stmt->close();

$shifts_define = [
    'morning' => ['label' => 'Morning', 'hours' => '08:00 - 13:00'],
    'evening' => ['label' => 'Evening', 'hours' => '13:00 - 18:00'],
    'night' => ['label' => 'Night', 'hours' => '18:00 - 23:00']
];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Weekly Roster</title>
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
            body {
                background: white !important;
                color: black !important;
            }
            header, footer, nav, button, .relative, form, .print-hide, .grid, h1 + p, hr {
                display: none !important;
            }
            main {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .border {
                border-color: #d1d5db !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            th, td {
                border: 1px solid #ccc !important;
                page-break-inside: avoid;
            }
            .sticky {
                position: static !important;
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
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="my_schedule.php">My Schedule</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_payroll.php">My Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_profile.php">My Profile</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Employee</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1400px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Weekly Work Schedule</h1>
                <p class="text-sm text-secondary mt-0.5">Weekly work schedule. View your shifts and check who you are working with.</p>
            </div>
            
            <!-- Week Selector Controls -->
            <div class="flex items-center gap-2 print-hide flex-nowrap">
                <div class="h-10 px-2.5 border border-outline-variant bg-white flex items-center gap-1 text-xs font-semibold rounded-xl text-secondary whitespace-nowrap">
                    <span class="material-symbols-outlined text-sm text-primary">event</span>
                    <?php echo $my_shift_count; ?> Shifts
                </div>
                <button onclick="window.print()" class="h-10 px-2.5 border border-outline-variant hover:bg-surface-container-low transition-colors rounded-xl font-semibold flex items-center gap-1 text-xs whitespace-nowrap">
                    <span class="material-symbols-outlined text-base">print</span> Print
                </button>
                <div class="h-6 w-px bg-outline-variant mx-0.5"></div>
                <div style="display: inline-flex; flex-direction: row; flex-wrap: nowrap; align-items: center; gap: 6px;">
                    <a href="my_schedule.php?week_start=<?php echo $prev_week; ?>" 
                       class="h-10 w-10 border border-outline-variant flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl" title="Previous Week">
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </a>
                    
                    <form action="my_schedule.php" method="GET" style="display: inline-flex; margin: 0; align-items: center;">
                        <input type="date" name="week_start" id="week_start_picker" value="<?php echo $week_start; ?>" onchange="this.form.submit()"
                               class="h-10 px-3 border border-outline-variant text-sm text-on-surface bg-white outline-none rounded-xl font-mono">
                    </form>

                    <a href="my_schedule.php?week_start=<?php echo $next_week; ?>" 
                       class="h-10 w-10 border border-outline-variant flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl" title="Next Week">
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </a>
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
                                                    <div class="text-[11px] text-secondary/40 text-center py-4 border border-dashed border-outline-variant/60 rounded-lg select-none">
                                                        Empty
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($assigned_baristas as $barista): 
                                                        $is_me = ($barista['user_id'] === $my_user_id);
                                                        
                                                        // Pastel left border coloring matching role shifts
                                                        $chip_class = "bg-white border-outline-variant";
                                                        if ($st_key === 'morning') {
                                                            $chip_class = "border-l-4 border-l-amber-400 bg-amber-50/40 border-y border-r border-outline-variant";
                                                        } elseif ($st_key === 'evening') {
                                                            $chip_class = "border-l-4 border-l-blue-400 bg-blue-50/40 border-y border-r border-outline-variant";
                                                        } elseif ($st_key === 'night') {
                                                            $chip_class = "border-l-4 border-l-zinc-800 bg-neutral-100 border-y border-r border-outline-variant";
                                                        }
                                                        
                                                        // Bold border focus styling for the logged in barista
                                                        if ($is_me) {
                                                            $chip_class .= " ring-2 ring-primary ring-offset-1";
                                                        }
                                                    ?>
                                                        <div class="flex flex-col gap-1 px-2.5 py-1.5 transition-all rounded-lg shadow-sm <?php echo $chip_class; ?>">
                                                            <div class="flex items-center justify-between gap-1 w-full">
                                                                <div class="truncate w-full">
                                                                    <div class="text-xs font-semibold text-on-surface truncate flex items-center justify-between gap-1" title="<?php echo htmlspecialchars($barista['full_name']); ?>">
                                                                        <span class="truncate"><?php echo htmlspecialchars($barista['full_name']); ?></span>
                                                                        <?php if ($is_me): ?>
                                                                            <span class="text-[8px] bg-primary text-white font-bold px-1 py-0.5 rounded tracking-wide uppercase">Me</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
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
            <a href="user.php" class="inline-flex items-center justify-center bg-primary text-white font-semibold px-4 h-11 hover:bg-neutral-800 transition-colors rounded-xl text-sm">
                Back to Dashboard
            </a>
        </div>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12 print-hide">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>