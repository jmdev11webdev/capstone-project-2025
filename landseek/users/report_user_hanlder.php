<?php
session_start();

// Set JSON header at the very beginning
header('Content-Type: application/json; charset=utf-8');

// Prevent accidental output
ob_start();

require_once "../connection/db_con.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Collect and sanitize inputs
$reporter_id = $_SESSION['user_id'];
$reported_user_id = intval($_POST['reported_user_id'] ?? 0);
$property_id = intval($_POST['property_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');

// Validation
if ($reported_user_id <= 0 || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Prevent reporting oneself
if ($reporter_id === $reported_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot report yourself']);
    exit;
}

// Check if reported user exists
$userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
if (!$userCheck) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}
$userCheck->bind_param("i", $reported_user_id);
$userCheck->execute();
$userResult = $userCheck->get_result();
if ($userResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Check for duplicate report within 24 hours
$duplicateCheck = $conn->prepare("
    SELECT id FROM report_users 
    WHERE reporter_id = ? AND reported_user_id = ? AND reason = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    LIMIT 1
");
if (!$duplicateCheck) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}
$duplicateCheck->bind_param("iis", $reporter_id, $reported_user_id, $reason);
$duplicateCheck->execute();
$duplicateResult = $duplicateCheck->get_result();
if ($duplicateResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reported this user for the same reason recently']);
    exit;
}

// Insert the report
$stmt = $conn->prepare("
    INSERT INTO report_users (reporter_id, reported_user_id, reason, details, created_at) 
    VALUES (?, ?, ?, ?, NOW())
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iiss", $reporter_id, $reported_user_id, $reason, $details);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User reported successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

// Close resources
$stmt->close();
$userCheck->close();
$duplicateCheck->close();
$conn->close();

// End output buffer
ob_end_flush();
