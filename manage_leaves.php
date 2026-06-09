<?php
include 'session_init.php';
include 'db_connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

$message = "";
$message_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $leave_id = intval($_POST['leave_id']);

    if ($_POST['action'] === 'approve_leave') {
        $upd = $conn->prepare("UPDATE leave_requests SET status = 'Approved' WHERE id = ?");
        $upd->bind_param("i", $leave_id);
        if ($upd->execute()) {
            $message = "Leave request approved.";
            $message_type = "success";
        }
        $upd->close();
    }

    if ($_POST['action'] === 'reject_leave') {
        $upd = $conn->prepare("UPDATE leave_requests SET status = 'Rejected' WHERE id = ?");
        $upd->bind_param("i", $leave_id);
        if ($upd->execute()) {
            $message = "Leave request rejected.";
            $message_type = "success";
        }
        $upd->close();
    }
}

// Fetch all leave requests with employee info
$res = $conn->query("
    SELECT lr.id, lr.leave_date, lr.reason, lr.status, lr.created_at,
           u.full_name, u.username
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.user_id
    ORDER BY lr.status = 'Pending' DESC, lr.leave_date DESC
");
$leaves = $res->fetch_all(MYSQLI_ASSOC);

$pending_count = array_reduce($leaves, fn($c, $r) => $c + ($r['status'] === 'Pending' ? 1 : 0), 0);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Leaves</title>
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_schedule.php">Schedules</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_leaves.php">
                        Leaves
                        <?php if ($pending_count > 0): ?>
                            <span class="ml-1.5 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_swaps.php">Swaps</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary hidden sm:inline">Role: <strong class="text-on-surface">Manager</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-[1100px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <div class="flex items-center justify-between border-b border-outline-variant pb-4">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Employee Leave Requests</h1>
                <p class="text-sm text-secondary mt-0.5">Approve or reject leave requests. Approved leaves block shift assignment for that day.</p>
            </div>
            <?php if ($pending_count > 0): ?>
                <span class="px-3 py-1.5 bg-amber-100 text-amber-800 text-xs font-bold rounded-xl flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">pending</span> <?php echo $pending_count; ?> Pending
                </span>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <?php $bg = $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
            <div class="border p-4 text-sm rounded-xl <?php echo $bg; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-3 px-4">Employee</th>
                            <th class="py-3 px-4">Leave Date</th>
                            <th class="py-3 px-4">Reason</th>
                            <th class="py-3 px-4">Submitted</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php if (empty($leaves)): ?>
                            <tr><td colspan="6" class="py-8 text-center text-secondary">No leave requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($leaves as $lv): ?>
                                <tr class="hover:bg-surface-container-low/20 transition-colors <?php echo $lv['status'] === 'Pending' ? 'bg-amber-50/30' : ''; ?>">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold"><?php echo htmlspecialchars($lv['full_name']); ?></div>
                                        <div class="text-[10px] text-secondary font-mono">@<?php echo htmlspecialchars($lv['username']); ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-semibold"><?php echo date('d M Y (D)', strtotime($lv['leave_date'])); ?></td>
                                    <td class="py-3 px-4 max-w-[200px] truncate"><?php echo htmlspecialchars($lv['reason']); ?></td>
                                    <td class="py-3 px-4 text-xs text-secondary"><?php echo date('d M Y', strtotime($lv['created_at'])); ?></td>
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
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="approve_leave">
                                                    <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                                    <button type="submit" class="text-xs bg-green-600 text-white px-3 py-1 hover:bg-green-700 rounded-xl transition-colors font-semibold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-sm">check</span> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="reject_leave">
                                                    <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                                    <button type="submit" class="text-xs border border-red-200 text-red-600 px-3 py-1 hover:bg-red-50 rounded-xl transition-colors font-semibold flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-sm">close</span> Reject
                                                    </button>
                                                </form>
                                            </div>
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
