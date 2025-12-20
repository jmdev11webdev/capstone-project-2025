<?php
require_once "../connection/db_con.php";
session_start();

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['count'=>0, 'properties'=>[]]);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Total unread (for red dot)
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id=? AND is_read=0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Per-property unread counts
$stmt = $conn->prepare("
    SELECT property_id, COUNT(*) as cnt 
    FROM messages 
    WHERE receiver_id=? AND is_read=0 
    GROUP BY property_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$properties = [];
while($row = $result->fetch_assoc()){
    $properties[$row['property_id']] = (int)$row['cnt'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'count' => (int)$total,
    'properties' => $properties
]);
