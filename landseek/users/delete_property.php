{# <?php
session_start();
require_once "../connection/db_con.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate property ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php?error=missing_id");
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property to ensure ownership and get images
$stmt = $conn->prepare("SELECT user_id, images FROM properties WHERE id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$stmt->bind_result($owner_id, $images_json);
$stmt->fetch();
$stmt->close();

if (!$owner_id || $owner_id != $user_id) {
    // Not found or user does not own it
    header("Location: dashboard.php?error=unauthorized");
    exit;
}

// Delete images
if ($images_json) {
    $images = json_decode($images_json, true);
    if (is_array($images)) {
        foreach ($images as $img) {
            $file = "../uploads/properties/" . $img;
            if (file_exists($file)) unlink($file);
        }
    }
}

// Delete the property
$stmt = $conn->prepare("DELETE FROM properties WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $property_id, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: dashboard.php?success=property_deleted");
    exit;
} else {
    $stmt->close();
    header("Location: dashboard.php?error=delete_failed");
    exit;
} #}
