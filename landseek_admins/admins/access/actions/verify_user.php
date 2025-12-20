<?php
session_start();
require_once "../../../connection/db_con.php";

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = intval($_GET['id']);

$update = $conn->prepare("UPDATE users SET is_verified=1 WHERE id=?");
$update->bind_param("i", $user_id);
$update->execute();
$update->close();

header("Location: ../index.php");
exit;
