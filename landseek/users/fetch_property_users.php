<?php
session_start();
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$property_id = intval($_GET['property_id'] ?? 0);

// Ensure property belongs to this user
$check = $conn->prepare("SELECT id FROM properties WHERE id=? AND user_id=?");
$check->bind_param("ii", $property_id, $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    http_response_code(403);
    exit;
}
$check->close();

// Fetch distinct users who messaged about this property
$stmt = $conn->prepare("
    SELECT DISTINCT u.user_id, u.full_name
    FROM messages m
    JOIN user_profiles u 
      ON u.user_id = CASE 
        WHEN m.sender_id=? THEN m.receiver_id 
        ELSE m.sender_id 
      END
    WHERE m.property_id=? 
      AND (m.sender_id=? OR m.receiver_id=?)
");
$stmt->bind_param("iiii", $user_id, $property_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($result);
