<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Handle employee selection from the dropdown
$employee_filter = '';
if (isset($_POST['employee_username']) && !empty($_POST['employee_username'])) {
    $employee_filter = $_POST['employee_username'];
}

// Fetch all employees for the dropdown
$sql = "SELECT username FROM users WHERE role = 'Employee'";
$employees_result = $conn->query($sql);

// Fetch schedules based on the selected employee (if any)
if ($employee_filter) {
    $sql = "SELECT schedules.id, users.username, schedules.date, schedules.shift_time
            FROM schedules
            JOIN users ON schedules.employee_username = users.username
            WHERE users.username = ?
            ORDER BY schedules.date";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT schedules.id, users.username, schedules.date, schedules.shift_time
            FROM schedules
            JOIN users ON schedules.employee_username = users.username
            ORDER BY schedules.date";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Shifts</title>
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
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_schedule.php">Schedules</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Manager</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-on-surface">Manage Employee Shifts</h2>
                    <p class="text-xs text-secondary mt-1">Filter schedules by choosing an employee below.</p>
                </div>

                <!-- Employee selection dropdown -->
                <form method="POST" action="manage_schedule.php" class="flex items-center gap-2">
                    <label for="employee_username" class="text-xs font-semibold text-secondary uppercase tracking-wider">Select Employee:</label>
                    <div class="relative">
                        <select name="employee_username" id="employee_username" onchange="this.form.submit()" class="h-10 px-3 pr-8 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none appearance-none transition-all duration-200 rounded-xl">
                            <option value="">-- All Employees --</option>
                            <?php while ($row = $employees_result->fetch_assoc()): ?>
                                <option value="<?php echo $row['username']; ?>" 
                                    <?php echo $employee_filter == $row['username'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                            <span class="material-symbols-outlined text-[18px] text-outline">expand_more</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Display schedules based on employee selection -->
            <div id="manage-schedule-table">
                <?php if ($result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                                    <th class="py-3 px-4">Employee</th>
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Shift Time</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $dot_color = "bg-slate-400";
                                    if ($row['shift_time'] == 'Morning') {
                                        $dot_color = "bg-green-500";
                                    } elseif ($row['shift_time'] == 'Middle') {
                                        $dot_color = "bg-amber-500";
                                    } elseif ($row['shift_time'] == 'Closing') {
                                        $dot_color = "bg-purple-500";
                                    }
                                ?>
                                    <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors text-sm">
                                        <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-on-surface">
                                                <span class="w-2 h-2 rounded-full <?php echo $dot_color; ?>"></span>
                                                <?php echo htmlspecialchars($row['shift_time']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right space-x-2">
                                            <a href="edit_schedule.php?id=<?php echo $row['id']; ?>" class="text-xs bg-primary text-white font-semibold px-2.5 py-1 hover:bg-neutral-800 transition-colors rounded-xl">Edit</a>
                                            <a href="delete_schedule.php?id=<?php echo $row['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="text-xs border border-red-200 text-red-600 font-semibold px-2.5 py-1 hover:bg-red-50 transition-colors rounded-xl" onclick="return confirm('Are you sure you want to delete this schedule?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="border border-dashed border-outline-variant p-8 text-center text-sm text-secondary rounded-xl">
                        No schedules found matching your filter selection.
                    </div>
                <?php endif; ?>
            </div>

            <div class="pt-4 border-t border-outline-variant">
                <a href="user.php" class="inline-flex items-center justify-center border border-outline-variant text-on-surface font-semibold px-4 h-11 hover:bg-surface-container-low transition-colors rounded-xl">
                    Back to Dashboard
                </a>
            </div>
        </section>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>
</body>
</html>


