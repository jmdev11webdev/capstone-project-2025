<?php
session_start();
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

echo "OK";
