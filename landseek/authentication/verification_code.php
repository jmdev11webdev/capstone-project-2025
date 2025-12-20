<?php
session_start(); // Start session

include '../connection/db_con.php'; // DB connection

// Initialize messages
$error = "";
$success = "";

// Show resend messages if available
// After displaying, unset them
if (isset($_SESSION['resend_success'])) {
    $success = $_SESSION['resend_success'];
    unset($_SESSION['resend_success']);
}
if (isset($_SESSION['resend_error'])) {
    $error = $_SESSION['resend_error'];
    unset($_SESSION['resend_error']);
}

// Handle 6-digit code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
    $code = trim($_POST['verification_code']); // Get code from form

    // Validate code format
    // Must be exactly 6 digits
    // If invalid, show error
    $stmt = $conn->prepare("SELECT id, email FROM users 
    WHERE verification_code = ? AND is_verified = 0 LIMIT 1"); // Only unverified users
    $stmt->bind_param("s", $code); // "s" = string
    $stmt->execute(); // Execute query
    $result = $stmt->get_result(); // Get result

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $email   = $user['email'];

        // Mark verified
        $update = $conn->prepare("
            UPDATE users 
            SET is_verified = 1, email_verified_at = NOW(), verification_code = NULL 
            WHERE id = ?
        ");
        $update->bind_param("i", $user_id);
        $update->execute();

        // Auto-login and redirect to dashboard
        $_SESSION['user_email'] = $email;
        header("Location: ../users/dashboard.php");
        exit;
    } else {
        $error = "Invalid or expired verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Verification | LandSeek</title>
  <link rel="stylesheet" href="../styles/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" />
  <style>
    .verify-section {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 60px 10%;
      background: #f9f9f9;
      min-height: 70vh;
    }
    .verify-box {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      max-width: 500px;
      text-align: center;
    }
    .verify-box h2 {
      color: #32a852;
      margin-bottom: 20px;
    }
    .verify-box input {
      width: 100%;
      padding: 12px;
      margin: 12px 0;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }
    .verify-btn, .email-btn {
      display: inline-block;
      width: 100%;
      margin: 10px 0;
      background: #32a852;
      color: #fff;
      padding: 12px 20px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
      transition: 0.3s;
      border: none;
      cursor: pointer;
    }
    .verify-btn:hover, .email-btn:hover {
      background: #278a41;
    }
    .resend-btn {
      background: #007BFF;
    }
    .resend-btn:hover {
      background: #0056b3;
    }
    .error { color: red; margin-bottom: 15px; }
    .success { color: green; margin-bottom: 15px; }
  </style>
</head>
<body>
  <!-- New Header -->
  <header>
        <span>
        <img src="../assets/logo/LandSeekLogo.png" alt="LandSeek Logo" width="80" height="80">
        </span>

        <span>
        <small><b>Digital Marketplace <br>
        for Land Hunting </b></small>
        </span>

        <nav class="index-nav">
            <ul>
            <li><a href="../index.php" ><i class="fas fa-house"></i> Home</a></li>
            <li><a href="../about.html"><i class="fas fa-circle-question"></i> About</a></li>
            <li><a href="../contact.html"><i class="fas fa-phone-volume"></i> Contacts</a></li>
            <li><a href="../services.html"><i class="fas fa-briefcase"></i> Services</a></li>
            <li><a href="../login.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a></li>
            <li><a href="../register.html"><i class="fa-solid fa-user-plus"></i> Register</a></li>
        </nav>
        
        <!-- Menu button -->
        <span class="menu-btn" onclick="openNav()">&#9776;</span>
        
        <!-- Side navigation -->
        <div id="mySidenav" class="side-nav">
            <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
            <a href="../index.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
            <a href="../about.html"><i class="fa-solid fa-circle-question"></i> About</a>
            <a href="../contact.html"><i class="fa-solid fa-phone-volume"></i> Contacts</a>
            <a href="../services.html"><i class="fa-solid fa-briefcase"></i> Services</a>
            <a href="../login.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
            <a href="../register.html"><i class="fa-solid fa-user-plus"></i> Register</a>
        </div>
    </header>

  <!-- Verification Section -->
  <section class="verify-section">
    <div class="verify-box">
      <h2>Verify Your Email</h2>

      <?php if (!empty($error)): ?>
        <p class="error"><?php echo $error; ?></p>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <p class="success"><?php echo $success; ?></p>
      <?php endif; ?>

      <!-- 6-digit code form -->
      <form method="POST">
        <input type="text" name="verification_code" placeholder="Enter 6-digit code" required>
        <button type="submit" class="verify-btn">Verify with Code</button>
      </form>

      <!-- Resend Button -->
      <form action="send_verification.php" method="POST" style="margin-top:15px;">
        <button type="submit" class="verify-btn resend-btn">Resend Code</button>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-about">
        <h3>LandSeek</h3>
        <p>A Digital Marketplace for Land Hunting. 
           Find, buy, sell, and communicate with ease — 
           without middlemen.</p>
      </div>
      <div class="footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">User Guidelines</a></li>
          <li><a href="#">FAQs</a></li>
        </ul>
      </div>
      <div class="footer-support">
        <h4>Support</h4>
        <ul>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Community</a></li>
          <li><a href="#">Report an Issue</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2025 LandSeek. All rights reserved.</p>
    </div>
  </footer>
</body>
</html>
