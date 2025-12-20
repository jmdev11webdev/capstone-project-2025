<?php
session_start();
require_once "../../../connection/db_con.php";

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = intval($_GET['id']);

// Get current status
$stmt = $conn->prepare("SELECT is_active FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($is_active);
$stmt->fetch();
$stmt->close();

// Toggle status
$new_status = $is_active ? 0 : 1;
$update = $conn->prepare("UPDATE users SET is_active=? WHERE id=?");
$update->bind_param("ii", $new_status, $user_id);
$update->execute();
$update->close();

header("Location: ../manage_data.php");
exit;
