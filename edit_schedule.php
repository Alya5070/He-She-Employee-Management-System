<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

// Get the schedule ID from the URL
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the current schedule data
$stmt = $conn->prepare("SELECT * FROM schedules WHERE id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();
$stmt->close();

if (!$schedule) {
    die("Schedule not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // Get new data from the form
    $new_date = $_POST['date'];
    $new_shift_time = $_POST['shift_time'];

    // Update the schedule in the database (removed location field)
    $stmt = $conn->prepare("UPDATE schedules SET date = ?, shift_time = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_date, $new_shift_time, $schedule_id);
    
    if ($stmt->execute()) {
        echo "Schedule updated successfully!<br><a href='manage_schedule.php'>Back to Manage Schedules</a>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Edit Schedule</title>
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
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[600px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <h2 class="font-bold text-2xl text-on-surface border-b border-outline-variant pb-2">Edit Shift Schedule</h2>
            <p class="text-sm text-secondary">Employee: <strong class="text-on-surface"><?php echo htmlspecialchars($schedule['employee_username']); ?></strong></p>

            <form action="edit_schedule.php?id=<?php echo $schedule['id']; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="date">DATE</label>
                    <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="date" id="date" name="date" value="<?php echo $schedule['date']; ?>" required>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="shift_time">SHIFT TIME</label>
                    <div class="relative">
                        <select id="shift_time" name="shift_time" required class="w-full bg-white border border-outline-variant px-4 py-2.5 pr-10 focus:border-primary focus:ring-1 focus:ring-primary outline-none appearance-none transition-all text-sm rounded-xl">
                            <option value="Morning" <?php echo ($schedule['shift_time'] == 'Morning') ? 'selected' : ''; ?>>Morning (8 AM - 12 PM)</option>
                            <option value="Middle" <?php echo ($schedule['shift_time'] == 'Middle') ? 'selected' : ''; ?>>Middle (12 PM - 4 PM)</option>
                            <option value="Closing" <?php echo ($schedule['shift_time'] == 'Closing') ? 'selected' : ''; ?>>Closing (4 PM - 8:30 PM)</option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                            <span class="material-symbols-outlined text-[20px] text-outline">expand_more</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-outline-variant flex gap-4">
                    <button type="submit" name="update_schedule" class="flex-1 bg-primary text-white font-semibold h-12 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                        Update Shift Details
                    </button>
                    <a href="manage_schedule.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-12 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl">
                        Cancel & Return
                    </a>
                </div>
            </form>
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



