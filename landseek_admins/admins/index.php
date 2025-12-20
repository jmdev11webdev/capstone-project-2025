<?php
// ✅ Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',  // universal path
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ✅ FIX: Check if we're already in the access folder
if (!empty($_SESSION['admin_id'])) {
    // If already logged in, redirect to dashboard
    if (strpos($_SERVER['PHP_SELF'], 'access/') === false) {
        // We're not in access folder, redirect to it
        header("Location: access/manage_data.php");
    } else {
        // We're already in access folder, redirect to current directory
        header("Location: manage_data.php");
    }
    exit;
}

require_once "../connection/db_con.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, username, email, password FROM admins WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                // ✅ Set session variables AFTER successful login
                $_SESSION['admin_id']    = $admin['id'];
                $_SESSION['admin_name']  = $admin['fullname'];
                $_SESSION['admin_user']  = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];

                // ✅ FIX: Redirect to manage_data.php in access folder
                header("Location: access/manage_data.php");
                exit;
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "No admin account found with that username.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles/styles.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

  <title>LandSeek | Admin Access</title>
</head>
<body>
    <header>
        <h2>
            <i class="fa-solid fa-user-tie"></i>
            LandSeek <b>Admin Access</b>
        </h2>

        <nav>
            <ul>
                <li>
                    <a href="index.php" class="active">
                        <i class="fa-solid fa-sign-in-alt"></i> 
                        Admin Login
                    </a>
                </li>

                <li>
                    <a href="register.php">
                        <i class="fa-solid fa-user-plus"></i> 
                        Admin Register
                    </a>
                </li>

                <li>
                    <a href="../../landseek/index.php">
                        <i class="fa-solid fa-laptop"></i> 
                        LandSeek Website
                    </a>
                </li>
            </ul>
        </nav>
    </header>

    <section class="login-section">
    <div class="login-form-container">
        <h2>Admin Login</h2>
        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
        <form class="login-form" method="POST" action="">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit"><i class="fa-solid fa-sign-in-alt"></i> Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</section>

</body>
</html>