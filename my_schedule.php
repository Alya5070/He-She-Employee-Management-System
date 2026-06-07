<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Default to current month and year if not provided
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Handle AJAX request to insert schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'insert_schedule') {
    $date = $_POST['date'];
    $shift_time = $_POST['shift_time'];

    // Validate and insert the new schedule
    $sql = "INSERT INTO schedules (employee_username, date, shift_time) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $date, $shift_time);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'Error: ' . $conn->error;
    }
    exit(); // End the script to prevent HTML output
}

// Fetch all employee schedules for the selected month
$sql = "SELECT schedules.id, schedules.date, schedules.shift_time
        FROM schedules
        WHERE employee_username = ? AND DATE_FORMAT(schedules.date, '%Y-%m') = ?
        ORDER BY schedules.date";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $month);
$stmt->execute();
$result = $stmt->get_result();
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    <main class="max-w-[1000px] mx-auto px-6 py-8 flex-grow w-full space-y-8">
        <!-- Dashboard Widget Card -->
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-outline-variant pb-4 gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-on-surface">My Schedule</h2>
                    <p class="text-xs text-secondary mt-1">Select month and add/claim shifts below.</p>
                </div>
                <!-- Month Selection Form -->
                <form action="my_schedule.php" method="GET" class="flex items-center gap-2">
                    <label for="month" class="text-xs font-semibold text-secondary uppercase tracking-wider">Select Month:</label>
                    <div class="relative">
                        <select name="month" id="month" onchange="this.form.submit()" class="h-10 px-3 pr-8 border border-outline-variant bg-surface-container-lowest text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none appearance-none transition-all duration-200 rounded-xl">
                            <?php
                            $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
                            foreach ($months as $key => $value) {
                                $selected = ($key == date('m', strtotime($month))) ? 'selected' : '';
                                echo "<option value='" . date('Y') . "-$key' $selected>$value " . date('Y', strtotime($month)) . "</option>";
                            }
                            ?>
                        </select>
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                            <span class="material-symbols-outlined text-[18px] text-outline">expand_more</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Current Schedule List -->
            <div id="schedule-table" class="space-y-4">
                <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider">Shifts for <?php echo date('F Y', strtotime($month)); ?></h3>
                
                <?php if ($result->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                                    <th class="py-3 px-4">Day</th>
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Shift Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while ($row = $result->fetch_assoc()): 
                                    $day = date('l', strtotime($row['date']));
                                    
                                    // Assign colors/badge indications based on shift time
                                    $badge_class = "bg-slate-100 text-slate-800";
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
                                        <td class="py-3 px-4 font-semibold"><?php echo $day; ?></td>
                                        <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-on-surface">
                                                <span class="w-2 h-2 rounded-full <?php echo $dot_color; ?>"></span>
                                                <?php echo htmlspecialchars($row['shift_time']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="border border-dashed border-outline-variant p-8 text-center text-sm text-secondary rounded-xl">
                        No schedules claimed for this month yet.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form to Insert New Schedule -->
            <div class="border border-outline-variant p-6 rounded-xl bg-surface-container-low space-y-4">
                <h3 class="font-bold text-lg text-on-surface">Claim a New Shift</h3>
                <form id="schedule-form" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="date">DATE</label>
                        <input type="date" name="date" id="date" required class="w-full bg-white border border-outline-variant px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all rounded-xl">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="shift_time">SHIFT TIME</label>
                        <div class="relative">
                            <select name="shift_time" id="shift_time" required class="w-full bg-white border border-outline-variant px-3 py-2 pr-8 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none appearance-none transition-all rounded-xl">
                                <option value="Morning">Morning (8 AM - 12 PM)</option>
                                <option value="Middle">Middle (12 PM - 4 PM)</option>
                                <option value="Closing">Closing (4 PM - 8:30 PM)</option>
                            </select>
                            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                                <span class="material-symbols-outlined text-[18px] text-outline">expand_more</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="bg-primary text-white font-semibold h-10 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                        Add Shift to Schedule
                    </button>
                </form>
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

    <script>
    $(document).ready(function() {
        // Handle form submission with AJAX
        $('#schedule-form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            $.ajax({
                url: 'my_schedule.php',
                type: 'POST',
                data: $(this).serialize() + '&ajax_action=insert_schedule',
                success: function(response) {
                    if (response.trim() === 'success') {
                        // Reload the schedule table container
                        $('#schedule-table').load(location.href + ' #schedule-table > *');
                        alert('Schedule added successfully!');
                    } else {
                        alert('Error: ' + response);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX Error: ' + status + ' - ' + error);
                }
            });
        });
    });
    </script>
</body>
</html>


