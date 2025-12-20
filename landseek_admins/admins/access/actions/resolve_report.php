<?php
session_start();
require_once "../../../connection/db_con.php";

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$report_id = intval($_GET['id']);
$conn->query("UPDATE reports SET status='Resolved' WHERE id=$report_id");

// FIX: Redirect back to manage_data.php instead of index.php
header("Location: ../manage_data.php");
exit;