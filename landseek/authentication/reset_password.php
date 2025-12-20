<?php
// authentication/reset_password.php
// Make sure this file lives in authentication/ (same folder as update_password.php)

require __DIR__ . "/../connection/db_con.php"; // correct relative path

// Check token & email from query string
if (isset($_GET['token'], $_GET['email'])) {
    $token = trim($_GET['token']);
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    $stmt = $conn->prepare("SELECT created_at FROM password_reset_tokens WHERE email = ? AND token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<script>alert('Invalid or expired token. Please request a new reset link.'); window.location = '/login.html';</script>";
        exit;
    }

    $row = $result->fetch_assoc();
    $createdAt = $row['created_at'] ? strtotime($row['created_at']) : 0;

    // Token expiry: 1 hour (3600 seconds)
    if ($createdAt + 3600 < time()) {
        // cleanup expired token
        $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();

        echo "<script>alert('Reset token expired. Please request a new password reset.'); window.location = '/login.html';</script>";
        exit;
    }
} else {
    header("Location: /login.html");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>LandSeek | Reset Password</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

  <!-- If you prefer to put CSS into styles.css, copy the block below into styles/styles.css -->
  <style>
    body {
      background-color: #1fd56e;
      font-family: 'poppins', sans-serif;
    }
    /* wrapper keeps this layout isolated from global body rules */
    .reset-page-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 36px 20px;
      background-color: #1fd56e;
      box-sizing: border-box;
    }

    .reset-container {
      width: 100%;
      max-width: 420px;
      background: #ffffff;
      border-radius: 12px;
      padding: 28px 26px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      box-sizing: border-box;
    }

    .reset-container h2 {
      margin: 0 0 8px 0;
      font-size: 1.35rem;
      color: #222;
      font-weight: 600;
      text-align: left;
    }

    .reset-container p.lead {
      margin: 0 0 18px 0;
      color: #555;
      font-size: 0.95rem;
      text-align: left;
    }

    .form-row { margin-bottom: 14px; }

    .reset-container label {
      display: block;
      font-size: 0.9rem;
      margin-bottom: 6px;
      color: #333;
      text-align: left;
    }

    .reset-container input[type="password"],
    .reset-container input[type="email"],
    .reset-container input[type="text"] {
      width: 100%;
      padding: 12px 12px;
      font-size: 0.95rem;
      border-radius: 8px;
      border: 1px solid #d0d4d8;
      box-sizing: border-box;
      transition: box-shadow .15s, border-color .15s;
    }

    .reset-container input:focus {
      outline: none;
      border-color: #1fd56e;
      box-shadow: 0 4px 12px rgba(31,213,110,0.12);
    }

    #errorText {
      color: #c0392b;
      font-size: 0.88rem;
      min-height: 18px;
      margin-bottom: 6px;
    }

    .reset-container button {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: none;
      background: #1fd56e;
      color: #fff;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(31,213,110,0.12);
    }

    .reset-container button:hover { background: #17a95a; }

    @media (max-width: 420px) {
      .reset-container { padding: 22px; border-radius: 10px; }
      .reset-container h2 { font-size: 1.15rem; }
    }
  </style>
</head>
<body>
  <div class="reset-page-wrapper">
    <div class="reset-container" role="main" aria-labelledby="resetHeading">
      <h2 id="resetHeading">Reset your LandSeek password</h2>
      <p class="lead">Please enter a new password and confirm it. Passwords must be at least 6 characters.</p>

      <form method="POST" action="update_password.php" onsubmit="return validatePasswords()">
        <!-- Hidden inputs so update_password.php can re-verify -->
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">

        <div class="form-row">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" placeholder="New password" required minlength="6" autocomplete="new-password">
        </div>

        <div class="form-row">
          <label for="confirm_password">Confirm new password</label>
          <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm new password" required minlength="6" autocomplete="new-password">
        </div>

        <div id="errorText" aria-live="polite"></div>

        <button type="submit">Reset Password</button>
      </form>
    </div>
  </div>

  <script>
    function validatePasswords() {
      const p = document.getElementById('password').value.trim();
      const c = document.getElementById('confirm_password').value.trim();
      const error = document.getElementById('errorText');

      if (p.length < 6) {
        error.textContent = 'Password must be at least 6 characters.';
        return false;
      }
      if (p !== c) {
        error.textContent = 'Passwords do not match.';
        return false;
      }
      error.textContent = '';
      return true;
    }
  </script>
</body>
</html>
