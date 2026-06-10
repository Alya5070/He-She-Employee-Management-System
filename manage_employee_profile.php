<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || ($_SESSION['role'] != 'Manager' && $_SESSION['role'] != 'Employee')) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$error_message = '';
$success_message = '';

// Handle delete employee request
if (isset($_GET['delete']) && $_SESSION['role'] == 'Manager') {
    // CSRF check
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    
    $delete_user_id = intval($_GET['delete']);
    
    // Fetch user details to verify role
    $stmt = $conn->prepare("SELECT role, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_user_id);
    $stmt->execute();
    $user_res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user_res && $user_res['role'] == 'Employee') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $delete_user_id);
        if ($stmt->execute()) {
            $success_message = "Employee account '" . htmlspecialchars($user_res['username']) . "' successfully removed.";
        } else {
            $error_message = "Error removing employee: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Invalid employee identifier or cannot delete managers.";
    }
}

// Fetch all employee profiles (if manager)
if ($_SESSION['role'] == 'Manager') {
    $sql = "SELECT ep.*, COALESCE((SELECT COUNT(*) FROM schedules s WHERE s.user_id = ep.user_id), 0) * 5 AS hours_worked FROM employee_profiles ep";
    $result = $conn->query($sql);
} else {
    // Fetch only the current employee's profile (if employee)
    $sql = "SELECT * FROM employee_profiles WHERE user_id = (SELECT user_id FROM users WHERE username = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
}

// Handle profile update for both manager and employee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $bank_account_number = $_POST['bank_account_number'];
    $email = $_POST['email'];

    if ($_SESSION['role'] == 'Manager') {
        // If manager, allow editing any employee profile
        $employee_id = intval($_POST['employee_id']); // Employee ID to update
        $shift_rate = isset($_POST['shift_rate']) ? floatval($_POST['shift_rate']) : 28.00;
        $stmt = $conn->prepare("UPDATE employee_profiles SET full_name = ?, contact = ?, bank_account_number = ?, email = ?, shift_rate = ? WHERE id = ?");
        $stmt->bind_param("ssssdi", $full_name, $contact, $bank_account_number, $email, $shift_rate, $employee_id);
    } else {
        // If employee, only update their own profile
        $stmt = $conn->prepare("UPDATE employee_profiles SET full_name = ?, contact = ?, bank_account_number = ?, email = ? WHERE user_id = (SELECT user_id FROM users WHERE username = ?)");
        $stmt->bind_param("sssss", $full_name, $contact, $bank_account_number, $email, $username);
    }

    if ($stmt->execute()) {
        $stmt->close();
        echo "Profile updated successfully!";
        // Redirect after update to avoid re-submitting the form
        header('Location: manage_employee_profile.php');
        exit();
    } else {
        echo "Error updating profile: " . $stmt->error;
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Profiles</title>
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
                    <?php if ($_SESSION['role'] == 'Manager'): ?>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_schedule.php">Schedules</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_leaves.php">Leaves</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                        <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                    <?php else: ?>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_schedule.php">My Schedule</a>
                        <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_profile.php">My Profile</a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface"><?php echo htmlspecialchars($_SESSION['role']); ?></strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1200px] mx-auto px-6 py-8 flex-grow w-full space-y-8">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <div class="flex justify-between items-center border-b border-outline-variant pb-2">
                <h2 class="font-bold text-2xl text-on-surface">Manage Employee Profiles</h2>
                <?php if ($_SESSION['role'] == 'Manager'): ?>
                    <a href="create_employee.php" class="text-xs bg-primary text-white font-semibold px-3 py-1.5 hover:bg-neutral-800 transition-colors rounded-xl">
                        Create New Employee
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 p-4 text-red-800 text-sm rounded-xl">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border border-green-200 p-4 text-green-800 text-sm rounded-xl">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] == 'Manager'): ?>
                <!-- For Manager: Display all employee profiles with an "Edit" link -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider bg-surface-container-low">
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Full Name</th>
                                <th class="py-3 px-4">Contact Info</th>
                                <th class="py-3 px-4">Bank Account</th>
                                <th class="py-3 px-4">Email</th>
                                <th class="py-3 px-4">Hours Worked</th>
                                <th class="py-3 px-4">Shift Rate</th>
                                <th class="py-3 px-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors text-sm">
                                        <td class="py-3 px-4 font-mono"><?php echo $row['id']; ?></td>
                                        <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['contact'] ? $row['contact'] : 'N/A'); ?></td>
                                        <td class="py-3 px-4 font-mono"><?php echo htmlspecialchars($row['bank_account_number'] ? $row['bank_account_number'] : 'N/A'); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['email'] ? $row['email'] : 'N/A'); ?></td>
                                        <td class="py-3 px-4 font-mono"><?php echo number_format($row['hours_worked'], 1); ?> hrs</td>
                                        <td class="py-3 px-4 font-mono">RM<?php echo number_format($row['shift_rate'], 2); ?></td>
                                        <td class="py-3 px-4 flex gap-2">
                                            <a href="manage_employee_profile.php?edit=<?php echo $row['id']; ?>" class="text-xs bg-primary-container text-white px-2.5 py-1 hover:bg-neutral-800 transition-colors rounded-xl">Edit</a>
                                            <a href="manage_employee_profile.php?delete=<?php echo $row['user_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" onclick="return confirm('Are you sure you want to delete this employee? This will permanently delete their account, profile, schedules, and payroll records.');" class="text-xs bg-red-600 text-white px-2.5 py-1 hover:bg-red-700 transition-colors rounded-xl">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-sm text-secondary">No employee profiles found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['role'] == 'Employee' || isset($_GET['edit'])): ?>
                <!-- For both Employee and Manager (if editing an employee): Display the profile editing form -->
                <?php
                if (isset($_GET['edit'])) {
                    // If manager is editing an employee's profile, fetch the selected employee's profile
                    $employee_id = intval($_GET['edit']);
                    $stmt = $conn->prepare("SELECT * FROM employee_profiles WHERE id = ?");
                    $stmt->bind_param("i", $employee_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $profile = $result ? $result->fetch_assoc() : null;
                    $stmt->close();
                }
                $shifts_count = 0;
                if (isset($profile['user_id'])) {
                    $stmt_s = $conn->prepare("SELECT COUNT(*) AS count FROM schedules WHERE user_id = ?");
                    $stmt_s->bind_param("i", $profile['user_id']);
                    $stmt_s->execute();
                    $shifts_count = $stmt_s->get_result()->fetch_assoc()['count'];
                    $stmt_s->close();
                }
                $hours_worked_computed = $shifts_count * 5;
                ?>
                
                <div id="editProfileModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 transition-opacity duration-300">
                    <div class="bg-white border border-outline-variant p-6 rounded-xl w-full max-w-[600px] shadow-2xl relative space-y-4 max-h-[90vh] overflow-y-auto">
                        <!-- Close Button -->
                        <a href="manage_employee_profile.php" class="absolute top-4 right-4 text-secondary hover:text-primary transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </a>

                        <h3 class="font-bold text-lg text-on-surface"><?php echo isset($profile) ? 'Edit Profile Details' : 'Create Profile Details'; ?></h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="employee_id" value="<?php echo isset($profile['id']) ? $profile['id'] : ''; ?>">
                            
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="full_name">FULL NAME</label>
                                <input class="w-full bg-white border border-outline-variant px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="full_name" name="full_name" value="<?php echo isset($profile['full_name']) ? htmlspecialchars($profile['full_name']) : ''; ?>" required placeholder="Full Name">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="contact">CONTACT INFO</label>
                                <input class="w-full bg-white border border-outline-variant px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="contact" name="contact" value="<?php echo isset($profile['contact']) ? htmlspecialchars($profile['contact']) : ''; ?>" placeholder="Contact Info">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="bank_account_number">BANK ACCOUNT NUMBER</label>
                                <input class="w-full bg-white border border-outline-variant px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="bank_account_number" name="bank_account_number" value="<?php echo isset($profile['bank_account_number']) ? htmlspecialchars($profile['bank_account_number']) : ''; ?>" placeholder="Bank Account Number">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="email">EMAIL</label>
                                <input class="w-full bg-white border border-outline-variant px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="email" id="email" name="email" value="<?php echo isset($profile['email']) ? htmlspecialchars($profile['email']) : ''; ?>" placeholder="Email">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider">HOURS WORKED (Computed)</label>
                                <input class="w-full bg-neutral-100 border border-outline-variant px-4 py-2 text-sm rounded-xl cursor-not-allowed text-secondary" type="text" readonly value="<?php echo $hours_worked_computed; ?> hours (<?php echo $shifts_count; ?> shifts scheduled)">
                                <span class="text-[10px] text-secondary">Automatically computed from scheduled rosters (5 hours/shift).</span>
                            </div>

                            <?php if ($_SESSION['role'] == 'Manager'): ?>
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="shift_rate">SHIFT RATE (RM)</label>
                                <input class="w-full bg-white border border-outline-variant px-4 py-2 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="number" step="0.01" id="shift_rate" name="shift_rate" value="<?php echo isset($profile['shift_rate']) ? htmlspecialchars($profile['shift_rate']) : '28.00'; ?>" required placeholder="28.00">
                            </div>
                            <?php endif; ?>

                            <div class="flex gap-4 pt-2">
                                <button type="submit" class="flex-1 bg-primary text-white font-semibold h-11 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                                    Update Profile
                                </button>
                                <a href="manage_employee_profile.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-11 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl text-center flex items-center justify-center">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') {
                            window.location.href = 'manage_employee_profile.php';
                        }
                    });
                    document.getElementById('editProfileModal').addEventListener('click', function(event) {
                        if (event.target === this) {
                            window.location.href = 'manage_employee_profile.php';
                        }
                    });
                </script>
            <?php endif; ?>
            
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


