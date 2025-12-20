<?php 
require_once "../connection/db_con.php"; 
session_start(); 

if (!isset($_SESSION['user_id'])) { 
    exit("Unauthorized"); 
} 

$userId = intval($_SESSION['user_id']); 
$propertyId = intval($_GET['property_id'] ?? 0); 

// Find property owner 
$ownerStmt = $conn->prepare("SELECT user_id FROM properties WHERE id = ?");
$ownerStmt->bind_param("i", $propertyId);
$ownerStmt->execute();
$ownerRes = $ownerStmt->get_result();
$ownerRow = $ownerRes->fetch_assoc();
$propertyOwnerId = $ownerRow['user_id'] ?? 0;
$ownerStmt->close();

if ($propertyOwnerId === 0) {
    exit("<p>Invalid property.</p>");
}

if ($userId === $propertyOwnerId) {
    // ✅ Case 1: Owner → show all inquirers + new message count
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id, 
            up.full_name, 
            u.email,
            SUM(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 ELSE 0 END) AS new_messages
        FROM messages m
        JOIN users u ON u.id = m.sender_id OR u.id = m.receiver_id
        JOIN user_profiles up ON up.user_id = u.id
        WHERE m.property_id = ? AND u.id != ?
        GROUP BY u.id, up.full_name, u.email
        ORDER BY MAX(m.created_at) DESC
    ");
    $stmt->bind_param("iii", $userId, $propertyId, $propertyOwnerId);
    $isOwner = true;
} else {
    // ✅ Case 2: Inquirer → only show the property owner + new message count
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id, 
            up.full_name, 
            u.email,
            SUM(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 ELSE 0 END) AS new_messages
        FROM users u
        JOIN user_profiles up ON up.user_id = u.id
        LEFT JOIN messages m ON m.property_id = ? AND ((m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id))
        WHERE u.id = ?
        GROUP BY u.id, up.full_name, u.email
        LIMIT 1
    ");
    $stmt->bind_param("iiiii", $userId, $propertyId, $userId, $userId, $propertyOwnerId);
    $isOwner = false;
}

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $newMessages = intval($row['new_messages']);
        $badge = $newMessages > 0 ? " <span class='new-msg-badge'>$newMessages</span>" : "";
        echo "<a href='messaging.php?user_id=" . (int)$row['user_id'] . "&property_id=" . $propertyId . "' data-owner='" . ($isOwner ? "1" : "0") . "' class='interested-user' style='display: block; margin-bottom: 10px; margin-top:10px; color:#fff; padding:10px; border-radius:5px; text-decoration: none;'>
            <i class='fa-solid fa-user'></i> " . htmlspecialchars($row['full_name']) . " (" . htmlspecialchars($row['email']) . ")" . $badge . "
        </a>";
    }
} else {
    echo "<p>No interested users yet.</p>";
}

$stmt->close();
$conn->close();
?>
