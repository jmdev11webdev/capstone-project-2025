<?php

// Start session
session_start();

include '../connection/db_con.php'; // DB connection

// Initialize error/success
// Default to empty error and false success
$error = "";
$success = false;

if (!isset($_GET['token'])) { // No token provided
    // Show error
    $error = "Invalid verification link."; 
} else { // Token provided
  // Get token from URL
    $token = $_GET['token'];

    // Look up user by verification_token
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE 
    verification_token = ? AND is_verified = 0 LIMIT 1"); // Only unverified users
    $stmt->bind_param("s", $token); // "s" = string
    $stmt->execute(); // Execute query
    $result = $stmt->get_result(); // Get result

    // If found, mark verified
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $email   = $user['email'];

        // Update verification status
        $update = $conn->prepare("
            UPDATE users 
            SET is_verified = 1, 
                email_verified_at = NOW(), 
                verification_token = NULL, 
                verification_code = NULL
            WHERE id = ?
        ");
        $update->bind_param("i", $user_id);
        $update->execute();

        // Auto-login: set same session vars as login.php
        $_SESSION['user_id']    = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['is_logged_in'] = true;

        unset($_SESSION['pending_email']);

        // Redirect immediately to dashboard
        header("Location: ../users/dashboard.php");
        exit;
    } else { // Not found or already verified
        $error = "This verification link is invalid or has already been used."; // Show error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Email | LandSeek</title>
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" />
  <style>
    .verify-section { 
      display:flex; 
      justify-content:center; 
      align-items:center; 
      padding:60px 10%; 
      gap:40px; 
      background:#f9f9f9; 
      min-height:70vh; 
    }

    .verify-left { 
      flex:1; 
      text-align:center; 
    }

    .verify-left img { 
      max-width:220px; 
      margin-bottom:20px; 
    }

    .message-cloud { 
      display:inline-block; 
      background:#32a852; 
      color:#fff; padding:15px 20px; 
      border-radius:20px; 
      position:relative; 
      font-size:1.1rem; 
      font-weight:500; 
    }

    .message-cloud::after { 
      content:""; 
      position:absolute; 
      bottom:-15px; 
      left:30px; 
      border-width:15px 15px 0; 
      border-style:solid; 
      border-color:#32a852 transparent transparent transparent; 
    }

    .verify-right { 
      flex:1; background:#fff; 
      padding:40px; 
      border-radius:12px; 
      box-shadow:0 4px 6px rgba(0,0,0,0.1); 
      text-align:center; 
    }

    .verify-right h2 { 
      color:#32a852; 
      margin-bottom:15px; 
    }

    .verify-right p { 
      margin-bottom:20px; 
      color:#444; 
    }

    .verify-btn { 
      display:inline-block; 
      background:#32a852; 
      color:#fff; 
      padding:12px 20px; 
      border-radius:8px; 
      text-decoration:none; 
      font-weight:bold; 
      transition:0.3s; 
    }

    .verify-btn:hover { 
      background:#278a41; 
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <span class="logo">
      <b>LandSeek</b><br>
      <small>Digital Marketplace for Land Hunting</small>
    </span>
    <ul>
      <li><a href="../index.php">Home</a></li>
      <li><a href="../about.php">About</a></li>
      <li><a href="../services.php">Services</a></li>
      <li><a href="../contacts.php">Contacts</a></li>
      <li class="dropdown">
        <a class="active"><i class="fa-solid fa-user"></i></a>
        <div class="dropdown-content">
          <a href="../login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
          <a href="../register.php"><i class="fa-solid fa-user-plus"></i> Register</a>
        </div>
      </li>
    </ul>
  </nav>

  <!-- Verify Section -->
  <section class="verify-section">
    <div class="verify-left">
      <img src="../assets/logo/LandSeekLogo.png" alt="LandSeek Logo">
      <div class="message-cloud">
        <?php if ($error): ?>
          Verification failed. Try again.
        <?php else: ?>
          Redirecting you to your dashboard...
        <?php endif; ?>
      </div>
    </div>

    <div class="verify-right">
      <?php if ($error): ?>
        <h2>Verification Failed</h2>
        <p style="color:red;"><?php echo $error; ?></p>
        <p><a href="../register.php">Go back to Register</a></p>
      <?php endif; ?>
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
