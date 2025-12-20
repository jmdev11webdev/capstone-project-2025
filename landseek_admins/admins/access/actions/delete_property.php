<?php
session_start();
require_once "../../../connection/db_con.php";

// Only allow admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../manage_data.php?error=missing_id");
    exit;
}

$property_id = intval($_GET['id']);

// Optional: delete associated images if stored in uploads folder
$stmt = $conn->prepare("SELECT images FROM properties WHERE id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($images_json);
$stmt->fetch();
$stmt->close();

if ($images_json) {
    $images = json_decode($images_json, true);
    if (is_array($images)) {
        foreach ($images as $img) {
            $file = "../../uploads/properties/" . $img;
            if (file_exists($file)) unlink($file);
        }
    }
}

// Delete property from database
$stmt = $conn->prepare("DELETE FROM properties WHERE id = ?");
$stmt->bind_param("i", $property_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: ../manage_data.php?success=property_deleted");
    exit;
} else {
    $stmt->close();
    header("Location: ../manage_data.php?error=delete_failed");
    exit;
}