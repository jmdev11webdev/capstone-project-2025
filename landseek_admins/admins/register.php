<?php
// admins/register.php
session_start();
require_once "../connection/db_con.php";   // mysqli $conn

// Run registration only when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname         = trim($_POST['fullname'] ?? "");
    $username         = trim($_POST['username'] ?? "");
    $email            = trim($_POST['email'] ?? "");
    $password         = trim($_POST['password'] ?? "");
    $confirm_password = trim($_POST['confirm_password'] ?? "");

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!'); window.location.href='register.php';</script>";
        exit;
    }

    // Check password match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.location.href='register.php';</script>";
        exit;
    }

    // Minimum password length
    if (strlen($password) < 6) {
        echo "<script>alert('Password must be at least 6 characters!'); window.location.href='register.php';</script>";
        exit;
    }

    // Check if email or username already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email=? OR username=? LIMIT 1");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo "<script>alert('Email or username already registered!'); window.location.href='register.php';</script>";
        exit;
    }
    $stmt->close();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into admins table (no verification fields)
    $stmt = $conn->prepare("
        INSERT INTO admins (fullname, username, email, password, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param("ssss", $fullname, $username, $email, $hashedPassword);

    if ($stmt->execute()) {
        $stmt->close();
        echo "<script>alert('Registration successful! You may now login.'); window.location.href='index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Registration failed: " . $stmt->error . "'); window.location.href='register.php';</script>";
        $stmt->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LandSeek | Admin Registration</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h2><i class="fa-solid fa-user-tie"></i> LandSeek <b>Admin Access</b></h2>
        <nav>
            <ul>
                <li><a href="index.php"><i class="fa-solid fa-sign-in-alt"></i> Admin Login</a></li>
                <li><a href="register.php" class="active"><i class="fa-solid fa-user-plus"></i> Admin Register</a></li>
                <li><a href="../../index.php"><i class="fa-solid fa-laptop"></i> LandSeek Website</a></li>
            </ul>
        </nav>
    </header>

    <section class="register-section">
        <div class="register-form-container">
            <h2>Create Your Admin Account</h2>
            <form class="register-form" method="POST" action="register.php">
                <label for="fullname">Full Name</label>
                <input type="text" name="fullname" id="fullname" placeholder="Enter full name" required>

                <label for="username">Username</label>
                <input type="text" name="username" id="username" placeholder="Choose a username" required>

                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter email address" required>

                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter password" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>

                <button type="submit"><i class="fa-solid fa-user-plus"></i> Register</button>
            </form>
            <p>Already have an account? <a href="index.php">Login here</a></p>
        </div>
    </section>
</body>
</html>
