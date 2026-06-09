<?php
include 'session_init.php';
include 'db_connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_row = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();
$user_id = $user_row['user_id'];

$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    if ($_POST['action'] === 'clock_in') {
        // Check no open session
        $open = $conn->prepare("SELECT id FROM time_clock WHERE user_id = ? AND clock_out IS NULL");
        $open->bind_param("i", $user_id);
        $open->execute();
        if ($open->get_result()->fetch_assoc()) {
            $message = "You're already clocked in. Clock out first.";
            $message_type = "error";
        } else {
            $ins = $conn->prepare("INSERT INTO time_clock (user_id, clock_in) VALUES (?, NOW())");
            $ins->bind_param("i", $user_id);
            if ($ins->execute()) {
                $message = "Clocked in at " . date('H:i');
                $message_type = "success";
            }
            $ins->close();
        }
        $open->close();
    }

    if ($_POST['action'] === 'clock_out') {
        // Find open session
        $open = $conn->prepare("SELECT id, clock_in FROM time_clock WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
        $open->bind_param("i", $user_id);
        $open->execute();
        $session = $open->get_result()->fetch_assoc();
        $open->close();

        if (!$session) {
            $message = "No active clock-in found.";
            $message_type = "error";
        } else {
            $clock_in = new DateTime($session['clock_in']);
            $clock_out = new DateTime();
            $diff = $clock_in->diff($clock_out);
            $hours = $diff->h + ($diff->days * 24) + ($diff->i / 60);

            $upd = $conn->prepare("UPDATE time_clock SET clock_out = NOW(), hours_worked = ? WHERE id = ?");
            $upd->bind_param("di", $hours, $session['id']);
            if ($upd->execute()) {
                $message = sprintf("Clocked out. Duration: %.2f hours.", $hours);
                $message_type = "success";
            }
            $upd->close();
        }
    }
}

// Check open session
$open_chk = $conn->prepare("SELECT id, clock_in FROM time_clock WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
$open_chk->bind_param("i", $user_id);
$open_chk->execute();
$open_session = $open_chk->get_result()->fetch_assoc();
$open_chk->close();

// Clock history
$hist = $conn->prepare("SELECT clock_in, clock_out, hours_worked FROM time_clock WHERE user_id = ? ORDER BY clock_in DESC LIMIT 20");
$hist->bind_param("i", $user_id);
$hist->execute();
$clock_records = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
$hist->close();

$total_hours = array_sum(array_column($clock_records, 'hours_worked'));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Time Clock</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        body { font-family: 'Inter', sans-serif; }
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="request_leave.php">Leave</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="request_swap.php">Swap Shifts</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="set_availability.php">Availability</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="time_clock.php">Time Clock</a>
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

    <main class="max-w-[800px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <div class="border-b border-outline-variant pb-4">
            <h1 class="text-2xl font-bold text-on-surface">Time Clock</h1>
            <p class="text-sm text-secondary mt-0.5">Record your shift start and end times.</p>
        </div>

        <?php if ($message): ?>
            <?php $bg = $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
            <div class="border p-4 text-sm rounded-xl <?php echo $bg; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Clock card -->
        <div class="bg-white border border-outline-variant p-8 rounded-xl shadow-sm text-center space-y-6">
            <div>
                <div id="live-clock" class="text-5xl font-mono font-bold tracking-tight text-on-surface"></div>
                <div id="live-date" class="text-sm text-secondary mt-2"></div>
            </div>

            <?php if ($open_session): ?>
                <div class="bg-green-50 border border-green-200 p-3 rounded-xl text-sm text-green-800 inline-flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse inline-block"></span>
                    Clocked in since <strong><?php echo date('H:i', strtotime($open_session['clock_in'])); ?></strong>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="clock_out">
                    <button type="submit" id="clock-out-btn"
                            class="w-full max-w-xs mx-auto h-14 bg-red-600 text-white font-bold text-base hover:bg-red-700 transition-colors rounded-xl flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xl">timer_off</span> Clock Out
                    </button>
                </form>
            <?php else: ?>
                <div class="text-sm text-secondary">You are not currently clocked in.</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="clock_in">
                    <button type="submit" id="clock-in-btn"
                            class="w-full max-w-xs mx-auto h-14 bg-green-600 text-white font-bold text-base hover:bg-green-700 transition-colors rounded-xl flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xl">timer</span> Clock In
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Total hours stat -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white border border-outline-variant p-5 rounded-xl shadow-sm">
                <div class="text-xs font-semibold text-secondary uppercase tracking-wider">Total Logged Hours</div>
                <div class="text-3xl font-bold font-mono mt-1"><?php echo number_format($total_hours, 2); ?></div>
                <div class="text-xs text-secondary mt-1">All time</div>
            </div>
            <div class="bg-white border border-outline-variant p-5 rounded-xl shadow-sm">
                <div class="text-xs font-semibold text-secondary uppercase tracking-wider">Sessions Recorded</div>
                <div class="text-3xl font-bold font-mono mt-1"><?php echo count(array_filter($clock_records, fn($r) => $r['clock_out'])); ?></div>
                <div class="text-xs text-secondary mt-1">Completed sessions</div>
            </div>
        </div>

        <!-- History table -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-outline-variant">
                <h2 class="font-bold text-base text-on-surface">Recent Sessions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-3 px-4">Clock In</th>
                            <th class="py-3 px-4">Clock Out</th>
                            <th class="py-3 px-4 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php if (empty($clock_records)): ?>
                            <tr><td colspan="3" class="py-8 text-center text-secondary">No sessions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($clock_records as $rec): ?>
                                <tr class="hover:bg-surface-container-low/20 transition-colors">
                                    <td class="py-3 px-4 font-mono text-xs"><?php echo date('D d M Y, H:i', strtotime($rec['clock_in'])); ?></td>
                                    <td class="py-3 px-4 font-mono text-xs">
                                        <?php if ($rec['clock_out']): ?>
                                            <?php echo date('D d M Y, H:i', strtotime($rec['clock_out'])); ?>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-xl">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-semibold">
                                        <?php echo $rec['clock_out'] ? number_format($rec['hours_worked'], 2) . ' hrs' : '—'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>

    <script>
    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').textContent = now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        document.getElementById('live-date').textContent = now.toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    updateClock();
    setInterval(updateClock, 1000);
    </script>
</body>
</html>
