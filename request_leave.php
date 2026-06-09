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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    if ($_POST['action'] === 'submit_leave') {
        $leave_date = trim($_POST['leave_date']);
        $reason = trim($_POST['reason']);

        if (empty($leave_date) || empty($reason)) {
            $message = "Date and reason are required.";
            $message_type = "error";
        } else {
            // Check for duplicate pending/approved request on same date
            $chk = $conn->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND leave_date = ? AND status IN ('Pending','Approved')");
            $chk->bind_param("is", $user_id, $leave_date);
            $chk->execute();
            $dup = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($dup) {
                $message = "A leave request for that date already exists.";
                $message_type = "error";
            } else {
                $ins = $conn->prepare("INSERT INTO leave_requests (user_id, leave_date, reason) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $user_id, $leave_date, $reason);
                if ($ins->execute()) {
                    $message = "Leave request submitted successfully.";
                    $message_type = "success";
                } else {
                    $message = "Failed to submit request.";
                    $message_type = "error";
                }
                $ins->close();
            }
        }
    }

    if ($_POST['action'] === 'cancel_leave') {
        $leave_id = intval($_POST['leave_id']);
        $del = $conn->prepare("DELETE FROM leave_requests WHERE id = ? AND user_id = ? AND status = 'Pending'");
        $del->bind_param("ii", $leave_id, $user_id);
        if ($del->execute() && $del->affected_rows > 0) {
            $message = "Leave request cancelled.";
            $message_type = "success";
        } else {
            $message = "Could not cancel that request.";
            $message_type = "error";
        }
        $del->close();
    }
}

// Fetch leave history
$hist = $conn->prepare("SELECT id, leave_date, reason, status, created_at FROM leave_requests WHERE user_id = ? ORDER BY leave_date DESC");
$hist->bind_param("i", $user_id);
$hist->execute();
$leaves = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
$hist->close();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Request Leave</title>
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
              borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" }
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

    <main class="max-w-[900px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <div class="border-b border-outline-variant pb-4">
            <h1 class="text-2xl font-bold text-on-surface">Leave Requests</h1>
            <p class="text-sm text-secondary mt-0.5">Submit a day off request for manager approval.</p>
        </div>

        <?php if ($message): ?>
            <?php $bg = $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($message_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800'); ?>
            <div class="border p-4 text-sm rounded-xl <?php echo $bg; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Submit form -->
        <div class="bg-white border border-outline-variant p-6 rounded-xl shadow-sm">
            <h2 class="font-bold text-base text-on-surface mb-4">New Leave Request</h2>
            <form method="POST" class="flex flex-col sm:flex-row gap-4 items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="submit_leave">
                <div class="flex-1">
                    <label class="text-xs font-semibold text-secondary uppercase tracking-wider block mb-1.5" for="leave_date">Leave Date</label>
                    <input type="date" id="leave_date" name="leave_date" required min="<?php echo date('Y-m-d'); ?>"
                           class="w-full h-10 px-3 border border-outline-variant text-sm bg-white outline-none rounded-xl focus:ring-1 focus:ring-primary focus:border-primary transition-all">
                </div>
                <div class="flex-[2]">
                    <label class="text-xs font-semibold text-secondary uppercase tracking-wider block mb-1.5" for="reason">Reason</label>
                    <input type="text" id="reason" name="reason" required placeholder="e.g. Medical appointment, Family event..."
                           class="w-full h-10 px-3 border border-outline-variant text-sm bg-white outline-none rounded-xl focus:ring-1 focus:ring-primary focus:border-primary transition-all">
                </div>
                <button type="submit" class="h-10 px-5 bg-primary text-white font-semibold text-sm hover:bg-neutral-800 transition-colors rounded-xl whitespace-nowrap flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">send</span> Submit Request
                </button>
            </form>
        </div>

        <!-- History table -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-outline-variant">
                <h2 class="font-bold text-base text-on-surface">Request History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-3 px-4">Date Requested</th>
                            <th class="py-3 px-4">Leave Date</th>
                            <th class="py-3 px-4">Reason</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php if (empty($leaves)): ?>
                            <tr><td colspan="5" class="py-8 text-center text-secondary text-sm">No leave requests yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($leaves as $lv): ?>
                                <tr class="hover:bg-surface-container-low/20 transition-colors">
                                    <td class="py-3 px-4 text-secondary text-xs"><?php echo date('d M Y', strtotime($lv['created_at'])); ?></td>
                                    <td class="py-3 px-4 font-semibold"><?php echo date('d M Y (D)', strtotime($lv['leave_date'])); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($lv['reason']); ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($lv['status'] === 'Approved'): ?>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-xl">Approved</span>
                                        <?php elseif ($lv['status'] === 'Rejected'): ?>
                                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded-xl">Rejected</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold rounded-xl">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <?php if ($lv['status'] === 'Pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="action" value="cancel_leave">
                                                <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                                <button type="submit" onclick="return confirm('Cancel this request?')"
                                                        class="text-xs border border-red-200 text-red-600 px-3 py-1 hover:bg-red-50 rounded-xl transition-colors font-semibold">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-secondary">—</span>
                                        <?php endif; ?>
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
</body>
</html>
