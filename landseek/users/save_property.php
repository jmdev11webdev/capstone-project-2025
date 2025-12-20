{# <?php
session_start();
require_once "../connection/db_con.php";

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.html");
    exit;
}

$property_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if already saved
$stmt = $conn->prepare("SELECT id FROM saved_properties WHERE user_id=? AND property_id=?");
$stmt->bind_param("ii", $user_id, $property_id);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows > 0){
    // Already saved, do nothing
    $stmt->close();
} else {
    $stmt->close();

    // Insert into saved_properties
    $stmt = $conn->prepare("INSERT INTO saved_properties (user_id, property_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $property_id);
    $stmt->execute();
    $stmt->close();

    // Fetch property uploader
    $prop = $conn->prepare("SELECT user_id, title FROM properties WHERE id=?");
    $prop->bind_param("i", $property_id);
    $prop->execute();
    $p = $prop->get_result()->fetch_assoc();
    $prop->close();

    if($p){
        $uploader_id = intval($p['user_id']);
        $title = htmlspecialchars($p['title'] ?? 'your property');

        // Insert notification for uploader
        $notif = $conn->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, is_read, notifiable_type, notifiable_id, created_at, updated_at)
            VALUES (?, 'property_saved', 'Property Saved', ?, 0, 'property', ?, NOW(), NOW())
        ");
        $msg = "A user has saved your property: $title";
        $notif->bind_param("isi", $uploader_id, $msg, $property_id);
        $notif->execute();
        $notif->close();
    }
}

header("Location: properties.php");
exit;
?> #}
