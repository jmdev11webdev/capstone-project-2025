<?php
// authentication/update_password.php
require __DIR__ . "/../connection/db_con.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.html");
    exit;
}

// sanitize
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// basic server-side validation
if (!$email || !$token || !$password || !$confirm) {
    echo "<script>alert('Missing required fields.'); window.history.back();</script>";
    exit;
}

if ($password !== $confirm) {
    echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
    exit;
}

if (strlen($password) < 6) {
    echo "<script>alert('Password must be at least 6 characters.'); window.history.back();</script>";
    exit;
}

// re-verify token exists and not expired (1 hour)
$stmt = $conn->prepare("SELECT created_at FROM password_reset_tokens WHERE email = ? AND token = ?");
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<script>alert('Invalid or already used reset token. Please request a new reset link.'); window.location = '../login.html';</script>";
    exit;
}

$row = $res->fetch_assoc();
$createdAt = $row['created_at'] ? strtotime($row['created_at']) : 0;
if ($createdAt + 3600 < time()) {
    // token expired — delete and prompt user to request again
    $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
    $del->bind_param("s", $email);
    $del->execute();

    echo "<script>alert('Reset token expired. Please request a new password reset.'); window.location = '../login.html';</script>";
    exit;
}

// Update password
$hashed = password_hash($password, PASSWORD_BCRYPT); // encryption of password

$upd = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
$upd->bind_param("ss", $hashed, $email);
$success = $upd->execute();

if (!$success) {
    // DB error
    echo "<script>alert('Could not update password. Please try again later.'); window.history.back();</script>";
    exit;
}

// Remove token (so it can't be reused)
$del = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
$del->bind_param("s", $email);
$del->execute();

// Build a host-aware login URL for redirect (works on localhost & ngrok)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$loginUrl = $scheme . '://' . $host . '/landseek/login.html';

// Success -> alert then redirect
echo "<script>
        alert('Password successfully reset. You may now log in.');
        window.location.href = " . json_encode($loginUrl) . ";
      </script>";
exit;
