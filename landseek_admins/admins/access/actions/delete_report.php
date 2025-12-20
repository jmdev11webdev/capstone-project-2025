<?php
session_start();
require_once "../../../connection/db_con.php";

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$report_id = intval($_GET['id']);
$conn->query("DELETE FROM reports WHERE id=$report_id");

header("Location: ../manage_data.php");
exit;
