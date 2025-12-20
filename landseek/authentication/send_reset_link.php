<?php
require "../connection/db_con.php";
require "../vendor/autoload.php"; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<script>alert('No account found with that email.'); window.history.back();</script>";
        exit;
    }

    // Generate unique token
    $token = bin2hex(random_bytes(32));

    // Store token (replace existing one if user already requested)
    $stmt = $conn->prepare("
        INSERT INTO password_reset_tokens (email, token, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()
    ");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();

    // 🔗 Dynamically build reset link for both localhost & ngrok
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; // works for localhost or ngrok
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /landseek/authentication
    $resetLink = "{$scheme}://{$host}{$basePath}/reset_password.php?token={$token}&email=" . urlencode($email);

    // --- Email sending ---
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lahorrajm@gmail.com';  // your Gmail
        $mail->Password = 'ggawqjtqjwtuiaoa';     // app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('lahorrajm@gmail.com', 'LandSeek Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LandSeek';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif; color:#333;'>
                <h2 style='color:#1fd56e;'>LandSeek Password Reset</h2>
                <p>Hello,</p>
                <p>You recently requested to reset your LandSeek password. Click the button below to reset it:</p>
                <p><a href='$resetLink' style='background:#1fd56e; color:#fff; padding:10px 15px; text-decoration:none; border-radius:6px;'>Reset Password</a></p>
                <p>Or copy and paste this link into your browser:</p>
                <p><a href='$resetLink'>$resetLink</a></p>
                <p><small>This link will expire in 1 hour. If you didn’t request this, you can safely ignore this email.</small></p>
            </div>
        ";

        $mail->send();

        echo "<script>
                alert('Password reset link sent to your email!');
                window.location.href = '../login.html';
              </script>";

    } catch (Exception $e) {
        echo "<script>
                alert('Failed to send email. Please try again later.');
                window.history.back();
              </script>";
    }
}
?>
