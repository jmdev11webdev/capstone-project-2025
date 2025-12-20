<?php
session_start();
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Unauthorized. Please log in first.'); window.history.back();</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporter_id = $_SESSION['user_id'];
    $reported_user_id = isset($_POST['reported_user_id']) ? intval($_POST['reported_user_id']) : null;
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : null;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : null;

    if (empty($reason)) {
        echo "<script>alert('Reason is required.'); window.history.back();</script>";
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO reports (reporter_id, reported_user_id, property_id, reason, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiss", $reporter_id, $reported_user_id, $property_id, $reason, $details);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Report submitted successfully!');
                    window.location.href='../users/properties.php';
                  </script>";
        } else {
            echo "<script>alert('Failed to submit report. Please try again.'); window.history.back();</script>";
        }

        $stmt->close();
    } catch (Exception $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Invalid request method.'); window.history.back();</script>";
}
