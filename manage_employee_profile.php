<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || ($_SESSION['role'] != 'Manager' && $_SESSION['role'] != 'Employee')) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Fetch all employee profiles (if manager)
if ($_SESSION['role'] == 'Manager') {
    $sql = "SELECT * FROM employee_profiles";
    $result = $conn->query($sql);
} else {
    // Fetch only the current employee's profile (if employee)
    $sql = "SELECT * FROM employee_profiles WHERE user_id = (SELECT id FROM users WHERE username = '$username')";
    $result = $conn->query($sql);
    $profile = $result->fetch_assoc();
}

// Handle profile update for both manager and employee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $bank_account_number = $_POST['bank_account_number'];
    $email = $_POST['email'];

    if ($_SESSION['role'] == 'Manager') {
        // If manager, allow editing any employee profile
        $employee_id = $_POST['employee_id']; // Employee ID to update
        $update_sql = "UPDATE employee_profiles SET full_name = '$full_name', contact = '$contact', 
                       bank_account_number = '$bank_account_number', email = '$email' 
                       WHERE id = $employee_id";
    } else {
        // If employee, only update their own profile
        $update_sql = "UPDATE employee_profiles SET full_name = '$full_name', contact = '$contact', 
                       bank_account_number = '$bank_account_number', email = '$email' 
                       WHERE user_id = (SELECT id FROM users WHERE username = '$username')";
    }

    if ($conn->query($update_sql) === TRUE) {
        echo "Profile updated successfully!";
        // Redirect after update to avoid re-submitting the form
        header('Location: manage_employee_profile.php');
        exit();
    } else {
        echo "Error updating profile: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employee Profiles</title>
    <link rel="stylesheet" href="css/manageprofile.css"> <!-- Link to the new CSS -->
</head>
<body>
    <div class="container">
        <h2>Manage Employee Profiles</h2>
        
        <?php if ($_SESSION['role'] == 'Manager'): ?>
            <!-- For Manager: Display all employee profiles with an "Edit" link -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Contact Info</th>
                            <th>Bank Account Number</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo $row['full_name']; ?></td>
                                <td><?php echo $row['contact']; ?></td>
                                <td><?php echo isset($row['bank_account_number']) ? $row['bank_account_number'] : 'N/A'; ?></td>
                                <td><?php echo isset($row['email']) ? $row['email'] : 'N/A'; ?></td>
                                <td><a href="manage_employee_profile.php?edit=<?php echo $row['id']; ?>">Edit</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'Employee' || isset($_GET['edit'])): ?>
            <!-- For both Employee and Manager (if editing an employee): Display the profile editing form -->
            <?php
            if (isset($_GET['edit'])) {
                // If manager is editing an employee's profile, fetch the selected employee's profile
                $employee_id = $_GET['edit'];
                $sql = "SELECT * FROM employee_profiles WHERE id = $employee_id";
                $result = $conn->query($sql);
                $profile = $result->fetch_assoc();
            }
            ?>
            
            <h3><?php echo isset($profile) ? 'Edit Profile' : 'Create Profile'; ?></h3>
            <form method="POST">
                <input type="hidden" name="employee_id" value="<?php echo isset($profile['id']) ? $profile['id'] : ''; ?>">
                <input type="text" name="full_name" value="<?php echo isset($profile['full_name']) ? $profile['full_name'] : ''; ?>" required placeholder="Full Name">
                <input type="text" name="contact" value="<?php echo isset($profile['contact']) ? $profile['contact'] : ''; ?>" placeholder="Contact Info">
                <input type="text" name="bank_account_number" value="<?php echo isset($profile['bank_account_number']) ? $profile['bank_account_number'] : ''; ?>" placeholder="Bank Account Number">
                <input type="email" name="email" value="<?php echo isset($profile['email']) ? $profile['email'] : ''; ?>" placeholder="Email">
                <button type="submit"><?php echo isset($profile) ? 'Update Profile' : 'Create Profile'; ?></button>
            </form>
        <?php endif; ?>
        
        <a href="user.php" class="back-btn">Back to Dashboard</a>
    </div>
</body>
</html>
