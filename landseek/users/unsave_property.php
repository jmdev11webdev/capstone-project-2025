{# <?php
session_start();
require_once "../connection/db_con.php";

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.html");
    exit;
}

$property_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Delete saved property
$stmt = $conn->prepare("DELETE FROM saved_properties WHERE user_id=? AND property_id=?");
$stmt->bind_param("ii",$user_id,$property_id);
$stmt->execute();
$stmt->close();

header("Location: properties.php");
exit;
?> #}
