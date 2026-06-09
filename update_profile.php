<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Fetch current profile data
$sql = "SELECT * FROM employee_profiles WHERE user_id = (SELECT user_id FROM users WHERE username = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // Retrieve profile information from the form
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $bank_account_number = $_POST['bank_account_number'];
    $email = $_POST['email'];

    if ($profile) {
        // Update existing profile
        $update_sql = "UPDATE employee_profiles 
                       SET full_name = ?, contact = ?, bank_account_number = ?, email = ? 
                       WHERE user_id = (SELECT user_id FROM users WHERE username = ?)";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssss", $full_name, $contact, $bank_account_number, $email, $username);
    } else {
        // Insert new profile
        $insert_sql = "INSERT INTO employee_profiles (user_id, full_name, contact, bank_account_number, email) 
                       VALUES ((SELECT user_id FROM users WHERE username = ?), ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssss", $username, $full_name, $contact, $bank_account_number, $email);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Profile saved successfully!');</script>";
        $stmt->close();
    } else {
        echo "<script>alert('Error saving profile. Please try again.');</script>";
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Update Profile</title>
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_schedule.php">My Schedule</a>
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
    <main class="max-w-[600px] mx-auto px-6 py-8 flex-grow w-full">
        <section class="bg-white border border-outline-variant p-6 rounded-xl space-y-6">
            <h2 class="font-bold text-2xl text-on-surface border-b border-outline-variant pb-2">
                <?php echo $profile ? 'Update Profile Settings' : 'Create Profile Settings'; ?>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <!-- Full Name -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="full_name">FULL NAME</label>
                    <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="full_name" name="full_name" value="<?php echo isset($profile['full_name']) ? htmlspecialchars($profile['full_name']) : ''; ?>" required placeholder="Full Name">
                </div>

                <!-- Contact -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="contact">CONTACT PHONE NUMBER</label>
                    <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="contact" name="contact" value="<?php echo isset($profile['contact']) ? htmlspecialchars($profile['contact']) : ''; ?>" placeholder="Contact Info">
                </div>

                <!-- Bank Account Number -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="bank_account_number">BANK ACCOUNT NUMBER</label>
                    <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="text" id="bank_account_number" name="bank_account_number" value="<?php echo isset($profile['bank_account_number']) ? htmlspecialchars($profile['bank_account_number']) : ''; ?>" required placeholder="Bank Account Number">
                </div>

                <!-- Email -->
                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider" for="email">EMAIL ADDRESS</label>
                    <input class="w-full bg-white border border-outline-variant px-4 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm rounded-xl" type="email" id="email" name="email" value="<?php echo isset($profile['email']) ? htmlspecialchars($profile['email']) : ''; ?>" required placeholder="Email">
                </div>

                <!-- Submit / Back -->
                <div class="pt-6 border-t border-outline-variant flex gap-4">
                    <button type="submit" class="flex-1 bg-primary text-white font-semibold h-12 flex items-center justify-center hover:bg-neutral-800 transition-colors rounded-xl">
                        <?php echo $profile ? 'Update Profile' : 'Create Profile'; ?>
                    </button>
                    <a href="user.php" class="flex-1 border border-outline-variant text-on-surface font-semibold h-12 flex items-center justify-center hover:bg-surface-container-low transition-colors rounded-xl">
                        Back to Dashboard
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


