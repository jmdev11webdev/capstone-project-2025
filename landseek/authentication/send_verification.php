<?php

session_start(); // Start session

include '../connection/db_con.php'; // mysqli $conn
require '../vendor/autoload.php'; // PHPMailer

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure we have an email to work with
// If not, redirect to register
if (!isset($_SESSION['pending_email'])) {
    header("Location: ../register.php");
    exit;
}

// Get email from session
$email = $_SESSION['pending_email'];

// Generate new token & 6-digit code
// Token for link, code for manual entry
$new_token = bin2hex(random_bytes(16)); // 32-char hex token
$new_code  = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6-digit code

// Update DB
// Set new code and token, update timestamp
// Use prepared statements to prevent SQL injection
$stmt = $conn->prepare("UPDATE users SET verification_code = ?, updated_at = NOW() WHERE email = ?");
$stmt->bind_param("ss", $new_code, $email); // "ss" = two strings
$stmt->execute(); // Execute update

// Also update the token
// Separate statement for clarity
// In a real application, you might combine these
// but this is fine for now
$stmtToken = $conn->prepare("UPDATE users SET verification_link_token = ?, updated_at = NOW() WHERE email = ?");
$stmtToken->bind_param("ss", $new_token, $email); // "ss" = two strings
$stmtToken->execute(); // Execute update

// Send again
$mail = new PHPMailer(true);
try {
    $mail->isSMTP(); // Send using SMTP
    $mail->Host = '; // Set the SMTP server to send through
    $mail->SMTPAuth = true; // Enable SMTP authentication
    $mail->Username = ''; // SMTP username
    $mail->Password = ''; // Gmail app password
    $mail->SMTPSecure = 'tls'; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
    $mail->Port = 587; // TCP port to connect to

    $mail->setFrom('', ''); // Sender
    $mail->addAddress($email); // Recipient

    // Verification link with new token
    $verifyLink = "http://localhost/landseek/authentication/verifyemail.php?token={$new_token}";
    
    // Email content
    $mail->isHTML(true); // Set email format to HTML
    $mail->Subject = "Your New LandSeek Verification Code"; // Subject
    // Body with both code and link
    $mail->Body = " 
        <h2 style='color:#32a852'>LandSeek Verification</h2>
        <p>Your new verification code is: <b style='font-size:20px;'>{$new_code}</b></p>
        <p>You can also verify by clicking the button below:</p>
        <a href='{$verifyLink}' style='
            display:inline-block;
            padding:10px 16px;
            background:#32a852;
            color:#fff;
            border-radius:8px;
            text-decoration:none;
            font-weight:bold;'
        >Verify My Account</a>
    ";

    // Send the email
    $mail->send();

    // Stay on verification_code.php with success message
    // Set a session variable to show success message
    // This avoids resending on page refresh
    // You can check for this variable on verification_code.php
    // After showing the message, unset it
    // For simplicity, we just redirect here
    // In a real app, consider using flash messages
    $_SESSION['resend_success'] = "A new verification code has been sent to your email."; // Store success
    header("Location: verification_code.php"); // Redirect back
    exit; // Stop further execution

    // If email sending fails, catch the error
} catch (Exception $e) {
    // Log the error (in real application, use proper logging)
    // error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    // Stay on verification_code.php with error message
    // Set a session variable to show error message
    // This avoids resending on page refresh
    $_SESSION['resend_error'] = "Error sending email: " . $mail->ErrorInfo; // Store error
    header("Location: verification_code.php"); // Redirect back
    exit; // Stop further execution
}
?>

