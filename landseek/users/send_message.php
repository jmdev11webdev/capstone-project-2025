<?php
// users/send_message.php
session_start();
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}

$user_id     = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$message     = trim($_POST['message'] ?? '');

if ($receiver_id <= 0 || $message === "") {
    http_response_code(400);
    echo "Invalid input";
    exit;
}

$sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo "Prepare failed: " . $conn->error;
    exit;
}

$stmt->bind_param("iis", $user_id, $receiver_id, $message);

if ($stmt->execute()) {
    echo "OK";
} else {
    http_response_code(500);
    echo "DB Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
