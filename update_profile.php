<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Fetch current profile data
$sql = "SELECT * FROM employee_profiles WHERE user_id = (SELECT id FROM users WHERE username = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve profile information from the form
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $bank_account_number = $_POST['bank_account_number'];
    $email = $_POST['email'];

    if ($profile) {
        // Update existing profile
        $update_sql = "UPDATE employee_profiles 
                       SET full_name = ?, contact = ?, bank_account = ?, email = ? 
                       WHERE user_id = (SELECT id FROM users WHERE username = ?)";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssss", $full_name, $contact, $bank_account_number, $email, $username);
    } else {
        // Insert new profile
        $insert_sql = "INSERT INTO employee_profiles (user_id, full_name, contact, bank_account_number, email) 
                       VALUES ((SELECT id FROM users WHERE username = ?), ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssss", $username, $full_name, $contact, $bank_account_number, $email);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Profile saved successfully!');</script>";
    } else {
        echo "<script>alert('Error saving profile. Please try again.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link rel="stylesheet" href="css/profile.css"> <!-- Link to the new style.css -->
</head>
<body>

<div class="container">
    <h2>Update Profile</h2>
    <form method="POST">
        <input type="text" name="full_name" value="<?php echo isset($profile['full_name']) ? $profile['full_name'] : ''; ?>" required placeholder="Full Name">
        <input type="text" name="contact" value="<?php echo isset($profile['contact']) ? $profile['contact'] : ''; ?>" placeholder="Contact Info">
        <input type="text" name="bank_account_number" value="<?php echo isset($profile['bank_account_number']) ? $profile['bank_account_number'] : ''; ?>" required placeholder="Bank Account Number">
        <input type="email" name="email" value="<?php echo isset($profile['email']) ? $profile['email'] : ''; ?>" required placeholder="Email">
        <button type="submit"><?php echo $profile ? 'Update Profile' : 'Create Profile'; ?></button>
    </form>
    <a href="user.php">Back to Dashboard</a>
</div>

</body>
</html>
