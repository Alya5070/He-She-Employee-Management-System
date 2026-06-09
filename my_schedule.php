<?php
include 'session_init.php';
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}
include 'db_connect.php';

// Logic taken from your code
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');
$current_month_label = date('F Y');

$weeks = [];
$cursor = strtotime($month_start);
$month_end_ts = strtotime($month_end);
$week_num = 1;

while ($cursor <= $month_end_ts) {
    $week_start = date('Y-m-d', $cursor);
    $end_of_week = strtotime('next sunday', $cursor) - 86400; 
    $week_end = date('Y-m-d', min($end_of_week, $month_end_ts));

    $weeks[] = [
        'label'  => 'Week ' . $week_num,
        'start'  => $week_start,
        'end'    => $week_end,
        'display'=> date('M d', strtotime($week_start)) . ' - ' . date('M d', strtotime($week_end))
    ];

    $cursor = strtotime($week_end . ' +1 day');
    $week_num++;
}

$today = date('Y-m-d');
$default_week = 0;
foreach ($weeks as $i => $week) {
    if ($today >= $week['start'] && $today <= $week['end']) {
        $default_week = $i;
        break;
    }
}

$selected_week = isset($_GET['week']) ? (int)$_GET['week'] : $default_week;

if ($selected_week < $default_week) {
    $selected_week = $default_week;
}
if ($selected_week >= count($weeks)) {
    $selected_week = count($weeks) - 1;
}

$week_start = $weeks[$selected_week]['start'];
$week_end   = $weeks[$selected_week]['end'];

// Fetch User ID
$username = $_SESSION['username'];
$userSql = "SELECT user_id FROM users WHERE username = '$username'";
$userResult = $conn->query($userSql);
$userRow = $userResult->fetch_assoc();
$user_id = $userRow['user_id'];

// Fetch shifts
// Using your column names: schedules_date, schedules_time
$stmt = $conn->prepare("
    SELECT id, schedules_date, schedules_time 
    FROM schedules 
    WHERE user_id = ? 
    AND schedules_date BETWEEN ? AND ?
    ORDER BY schedules_date ASC
");
$stmt->bind_param("iss", $user_id, $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();
$shifts = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | My Schedule</title>
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
                    <!-- Active State -->
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="my_schedule.php">My Schedule</a>
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
    <main class="max-w-[800px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            
            <!-- Header -->
            <div class="flex justify-between items-end border-b border-outline-variant pb-4">
                <div>
                    <h2 class="font-bold text-2xl text-on-surface">My Schedule</h2>
                    <p class="text-sm text-secondary mt-1"><?php echo $current_month_label; ?></p>
                </div>
                <div class="hidden md:block text-right">
                    <span class="text-xs font-semibold text-secondary uppercase bg-surface-container px-2 py-1 rounded">
                        <?php echo count($shifts); ?> Shifts
                    </span>
                </div>
            </div>

            <!-- Week Navigation -->
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    <?php foreach ($weeks as $i => $week): 
                        $is_past    = $i < $default_week;
                        $is_current = $i === $default_week;
                        $is_active  = $i === $selected_week;
                    ?>
                        <?php if ($is_past): ?>
                            <button class="flex-shrink-0 opacity-50 cursor-not-allowed px-4 py-2 rounded-xl border border-outline-variant bg-transparent text-secondary text-xs font-medium" disabled>
                                <?php echo $week['label']; ?>
                                <span class="block font-normal text-[10px]"><?php echo $week['display']; ?></span>
                            </button>
                        <?php elseif ($is_active): ?>
                            <a href="?week=<?php echo $i; ?>" class="flex-shrink-0 bg-primary text-white px-4 py-2 rounded-xl shadow-sm text-xs font-semibold flex flex-col items-center justify-center min-w-[100px]">
                                <?php echo $week['label']; ?>
                                <span class="block font-normal text-[10px] opacity-90"><?php echo $week['display']; ?></span>
                                <?php if($is_current): ?>
                                    <span class="absolute -top-2 -right-2 bg-yellow-400 text-black text-[10px] px-1.5 rounded-full font-bold">NOW</span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <a href="?week=<?php echo $i; ?>" class="flex-shrink-0 hover:bg-surface-container-low text-on-surface px-4 py-2 rounded-xl border border-transparent hover:border-outline-variant text-xs font-medium flex flex-col items-center justify-center min-w-[100px] transition-colors">
                                <?php echo $week['label']; ?>
                                <span class="block font-normal text-[10px] text-secondary"><?php echo $week['display']; ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shifts Table -->
            <?php if (empty($shifts)): ?>
                <div class="bg-surface-container p-8 text-center rounded-xl border border-dashed border-outline-variant">
                    <span class="material-symbols-outlined text-4xl text-secondary mb-2">event_busy</span>
                    <p class="text-lg font-semibold text-on-surface">No shifts this week</p>
                    <p class="text-sm text-secondary">You have no assigned shifts for <?php echo $weeks[$selected_week]['display']; ?>.</p>
                </div>
            <?php else: ?>
                <div class="border border-outline-variant rounded-xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container text-xs font-semibold text-secondary uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 border-b border-outline-variant">Date</th>
                                <th class="px-4 py-3 border-b border-outline-variant">Day</th>
                                <th class="px-4 py-3 border-b border-outline-variant text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php foreach ($shifts as $shift):
                                $is_today = $shift['schedules_date'] === $today;
                            ?>
                            <tr class="hover:bg-surface-container-low transition-colors <?php echo $is_today ? 'bg-yellow-50' : ''; ?>">
                                <td class="px-4 py-3">
                                    <span class="text-xs text-secondary font-medium">
                                        <?php echo date('M d, Y', strtotime($shift['schedules_date'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-semibold text-on-surface">
                                            <?php echo date('l', strtotime($shift['schedules_date'])); ?>
                                        </span>
                                        <?php if ($is_today): ?>
                                            <span class="bg-yellow-100 text-yellow-800 text-[10px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">Today</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-1 bg-surface-container px-3 py-1.5 rounded-lg text-sm font-medium text-on-surface">
                                        <span class="material-symbols-outlined text-base">schedule</span>
                                        <?php echo htmlspecialchars($shift['schedules_time']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Footer Actions -->
            <div class="pt-4 flex justify-end">
                <a href="user.php" class="border border-outline-variant text-on-surface font-semibold h-10 px-6 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl text-sm">
                    Back to Dashboard
                </a>
            </div>
        </section>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-auto">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>