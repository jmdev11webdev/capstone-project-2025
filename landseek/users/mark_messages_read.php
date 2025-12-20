<?php
require_once "../connection/db_con.php";
session_start();

if(!isset($_SESSION['user_id'])) exit("Unauthorized");

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND is_read=0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => true]);
