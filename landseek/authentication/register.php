<?php
// Start session
session_start();

// Database connection
require_once "../connection/db_con.php";   // mysqli $conn

// PHPMailer 
require "../vendor/autoload.php";          // PHPMailer

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../register.php");
    exit;
}

// Sanitize and validate inputs
// Trim inputs and use null coalescing to avoid undefined index
$fullname         = trim($_POST['fullname'] ?? "");
$email            = trim($_POST['email'] ?? "");
$password         = trim($_POST['password'] ?? "");
$confirm_password = trim($_POST['confirm_password'] ?? "");

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Invalid email format!'); window.location.href='../register.php';</script>";
    exit;
}

// Check password match
// If passwords do not match, show alert and redirect back
if ($password !== $confirm_password) {
    echo "<script>alert('Passwords do not match!'); window.location.href='../register.html';</script>";
    exit;
}

// Minimum password length
// If password is less than 6 characters, show alert and redirect back
if (strlen($password) < 6) {
    echo "<script>alert('Password must be at least 6 characters!'); window.location.href='../register.html';</script>";
    exit;
}

// Check if email already exists
// If email exists, show alert and redirect to login page
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

// Check if any row returned
if ($stmt->num_rows > 0) {
    $stmt->close();
    // Email exists
    // Redirect to login page with message
    echo "<script>alert('Email already registered! Please log in.'); window.location.href='../register.html';</script>";
    exit;
}
$stmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Generate both token link & 6-digit code
$verification_token = bin2hex(random_bytes(16)); // long random string
$verification_code  = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6-digit code

// Insert into users (save both separately)
$stmt = $conn->prepare("
    INSERT INTO users (email, password, is_verified, verification_code, verification_token, created_at, updated_at)
    VALUES (?, ?, 0, ?, ?, NOW(), NOW())
");

// Check if prepare() failed
// If it fails, show error and exit
// This is a rare case, but good to handle
$stmt->bind_param("ssss", $email, $hashedPassword, $verification_code, $verification_token);
// Execute and check for success
// If successful, get inserted user ID for profile
if ($stmt->execute()) {
    // Get the inserted user ID
    $user_id = $stmt->insert_id;
    $stmt->close();

    // Insert into user_profiles (fullname only, phone left empty)
    // Use prepared statement to prevent SQL injection
    // created_at and updated_at set to NOW()
    $stmtProfile = $conn->prepare("
        INSERT INTO user_profiles (user_id, full_name, phonenumber, created_at, updated_at)
        VALUES (?, ?, '', NOW(), NOW())
    ");
    $stmtProfile->bind_param("is", $user_id, $fullname);
    $stmtProfile->execute();
    $stmtProfile->close();

    // Send verification email with both link + code
    // PHPMailer setup
    // Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = ''; // Set the SMTP server to send through
        $mail->SMTPAuth   = ; // Enable SMTP authentication
        $mail->Username   = ''; // SMTP username
        $mail->Password   = ''; // this is the gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also acceptedS
        $mail->Port       = ; // TCP port to connect to 

        $mail->setFrom('', 'LandSeek'); // Sender info
        $mail->addAddress($email, $fullname ?: $email); // Recipient

        $verifyLink = "http://localhost/landseek/authentication/verifyemail.php?token=" .
                      urlencode($verification_token); // Verification link

        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = "Verify your LandSeek Account"; // Email subject

        // Email body with left button (link) and right box (code)
        $mail->Body = "
            <h2 style='color:#32a852'>Welcome to LandSeek".($fullname ? ", {$fullname}" : "")."!</h2>
            <p>You can verify your account by using the 6 digit verification code:</p>
            <div style='display:flex;gap:20px;align-items:center;'>
                <div style='padding:10px 16px;border:2px dashed #32a852;border-radius:8px;font-size:18px;font-weight:bold;color:#32a852'>
                    {$verification_code}
                </div>
            </div>
            <p>Use this 6-digit code if you prefer manual verification.</p>
        ";

        $mail->send();

        // Redirect user to enter verification code page
        echo "<script>alert('Registration successful! Please check your email for verification.'); window.location.href='verification_code.php';</script>";
        exit;
    } catch (Exception $e) {
        // Email sending failed
        // For security, do not reveal email errors to user
        // Log the error (in real application, use proper logging)
        // error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        // Redirect to login with generic message
        echo "<script>alert('Account created, but email could not be sent. Error: {$mail->ErrorInfo}'); window.location.href='../login.html';</script>";
    }

} else {
    // Insert failed
    // This is rare, but good to handle
    // Show error and redirect back
    echo "<script>alert('Registration failed: " . $stmt->error . "'); window.location.href='../register.html';</script>";
    $stmt->close();
}

// Close connection
$conn->close();
?>

