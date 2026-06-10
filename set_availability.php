<?php
include 'session_init.php';
include 'db_connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_row = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();
$user_id = $user_row['user_id'];

$message = "";
$message_type = "info";

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$slots = ['morning', 'evening', 'night'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // Validate no conflicts with future scheduled shifts
    $conflict_found = false;
    $conflict_msg = "";
    
    $conflict_stmt = $conn->prepare("
        SELECT DATE_FORMAT(schedules_date, '%W (%d %M %Y)') AS formatted_date
        FROM schedules
        WHERE user_id = ?
          AND schedules_date >= CURDATE()
          AND WEEKDAY(schedules_date) + 1 = ?
          AND LOWER(schedules_time) = LOWER(?)
        LIMIT 1
    ");

    for ($day = 1; $day <= 7; $day++) {
        foreach ($slots as $slot) {
            $is_available = isset($_POST["avail_{$day}_{$slot}"]) ? 1 : 0;
            if ($is_available === 0) {
                $conflict_stmt->bind_param("iis", $user_id, $day, $slot);
                $conflict_stmt->execute();
                $conflict_res = $conflict_stmt->get_result()->fetch_assoc();
                if ($conflict_res) {
                    $conflict_found = true;
                    $conflict_msg = "You cannot mark " . ucfirst($slot) . " on " . $days[$day - 1] . " as unavailable because you are already scheduled to work on " . $conflict_res['formatted_date'] . ". Please submit a leave request instead.";
                    break 2;
                }
            }
        }
    }
    $conflict_stmt->close();

    if ($conflict_found) {
        $message = $conflict_msg;
        $message_type = "error";
    } else {
        // Delete all existing preferences for this user
        $del = $conn->prepare("DELETE FROM availability_preferences WHERE user_id = ?");
        $del->bind_param("i", $user_id);
        $del->execute();
        $del->close();

        // Insert checked slots (unchecked = unavailable)
        $ins = $conn->prepare("INSERT INTO availability_preferences (user_id, day_of_week, time_slot, is_available) VALUES (?, ?, ?, ?)");
        for ($day = 1; $day <= 7; $day++) {
            foreach ($slots as $slot) {
                $is_available = isset($_POST["avail_{$day}_{$slot}"]) ? 1 : 0;
                $ins->bind_param("iisi", $user_id, $day, $slot, $is_available);
                $ins->execute();
            }
        }
        $ins->close();

        $message = "Availability preferences saved.";
        $message_type = "success";
    }
}

// Load current prefs into a keyed array [day][slot] = is_available
$prefs = [];
$pref_res = $conn->prepare("SELECT day_of_week, time_slot, is_available FROM availability_preferences WHERE user_id = ?");
$pref_res->bind_param("i", $user_id);
$pref_res->execute();
$rows = $pref_res->get_result()->fetch_all(MYSQLI_ASSOC);
$pref_res->close();

// Default: all available
for ($d = 1; $d <= 7; $d++) {
    foreach ($slots as $s) {
        $prefs[$d][$s] = 1;
    }
}
foreach ($rows as $r) {
    $prefs[$r['day_of_week']][$r['time_slot']] = $r['is_available'];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Set Availability</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        body { font-family: 'Inter', sans-serif; }
        .avail-cell input[type="checkbox"] { display: none; }
        .avail-cell label {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            cursor: pointer;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            transition: background 0.15s, color 0.15s;
            padding: 10px 4px;
            user-select: none;
        }
        .avail-cell input[type="checkbox"]:checked + label {
            background-color: #dcfce7;
            color: #166534;
        }
        .avail-cell input[type="checkbox"]:not(:checked) + label {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .avail-cell input[type="checkbox"]:checked + label::before { content: '✓ Available'; }
        .avail-cell input[type="checkbox"]:not(:checked) + label::before { content: '✗ Unavailable'; }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                "secondary": "#545f73", "surface-container": "#eceef0", "surface-container-lowest": "#ffffff",
                "on-background": "#191c1e", "on-surface-variant": "#434655", "on-primary": "#ffffff",
                "surface": "#f7f9fb", "primary": "#000000", "background": "#f7f9fb",
                "primary-container": "#000000", "on-primary-container": "#ffffff",
                "on-surface": "#191c1e", "outline-variant": "#c3c6d7", "outline": "#737686",
                "surface-container-low": "#f2f4f6"
              },
              borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" }
            }
          }
        }
    </script>
</head>
<body class="bg-background text-on-surface min-h-screen flex flex-col justify-between">
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_payroll.php">My Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_profile.php">My Profile</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary hidden sm:inline">Role: <strong class="text-on-surface">Employee</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-[1000px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <div class="border-b border-outline-variant pb-4">
            <h1 class="text-2xl font-bold text-on-surface">Availability Preferences</h1>
            <p class="text-sm text-secondary mt-0.5">Toggle your availability per shift and day. The manager sees a warning before scheduling you on an unavailable slot.</p>
        </div>

        <?php if ($message): ?>
            <?php $bg = $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
            <div class="border p-4 text-sm rounded-xl <?php echo $bg; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-surface-container-low border-b border-outline-variant">
                                <th class="py-3 px-4 text-xs font-semibold text-secondary uppercase tracking-wider text-left w-28">Shift</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="py-3 px-2 text-xs font-semibold text-secondary uppercase tracking-wider text-center"><?php echo substr($day, 0, 3); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php foreach ($slots as $slot): ?>
                                <tr>
                                    <td class="py-3 px-4">
                                        <span class="text-xs font-semibold capitalize text-on-surface"><?php echo ucfirst($slot); ?></span>
                                        <div class="text-[9px] text-secondary mt-0.5">
                                            <?php echo $slot === 'morning' ? '7am–12pm' : ($slot === 'evening' ? '12pm–6pm' : '6pm–11pm'); ?>
                                        </div>
                                    </td>
                                    <?php for ($day = 1; $day <= 7; $day++): ?>
                                        <?php
                                            $checked = $prefs[$day][$slot] ? 'checked' : '';
                                            $id = "avail_{$day}_{$slot}";
                                        ?>
                                        <td class="py-2 px-1 avail-cell text-center">
                                            <input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $id; ?>" <?php echo $checked; ?>>
                                            <label for="<?php echo $id; ?>"></label>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit" class="h-11 px-6 bg-primary text-white font-semibold text-sm hover:bg-neutral-800 transition-colors rounded-xl flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">save</span> Save Preferences
                </button>
                <div class="flex items-center gap-4 text-xs text-secondary">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-green-200 border border-green-400 inline-block"></span> Available</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-200 border border-red-400 inline-block"></span> Unavailable</span>
                </div>
            </div>
        </form>
    </main>

    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>
