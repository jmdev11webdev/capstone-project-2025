{# <?php
session_start();
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = intval($_POST['property_id']);
    $status = $_POST['status'];

    // ✅ 1. Update property status
    $update = $conn->prepare("UPDATE properties SET status=? WHERE id=?");
    $update->bind_param("si", $status, $property_id);
    $update->execute();
    $update->close();

    // ✅ 2. Get property title for notification message
    $titleQuery = $conn->prepare("SELECT title FROM properties WHERE id=?");
    $titleQuery->bind_param("i", $property_id);
    $titleQuery->execute();
    $titleResult = $titleQuery->get_result()->fetch_assoc();
    $property_title = $titleResult['title'] ?? 'Property';
    $titleQuery->close();

    // ✅ 3. Find all users who saved this property
    $savedQuery = $conn->prepare("SELECT user_id FROM saved_properties WHERE property_id=?");
    $savedQuery->bind_param("i", $property_id);
    $savedQuery->execute();
    $savedUsers = $savedQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $savedQuery->close();

    // ✅ 4. Notify each user
    if ($savedUsers) {
        foreach ($savedUsers as $su) {
            $uid = $su['user_id'];
            $msg = "The property '".$property_title."' is now marked as ".$status.".";
            $notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, is_read, created_at) VALUES (?,?,?,?,0,NOW())");
            $type = "property_status";
            $notif->bind_param("isss", $uid, $type, $property_title, $msg);
            $notif->execute();
            $notif->close();
        }
    }

    // ✅ 5. If sold → remove from saved_properties
    if ($status === 'sold') {
        $del = $conn->prepare("DELETE FROM saved_properties WHERE property_id=?");
        $del->bind_param("i", $property_id);
        $del->execute();
        $del->close();
    }

    // ✅ Redirect back
    header("Location: properties.php?status_update=success");
    exit;
}
?> #}
