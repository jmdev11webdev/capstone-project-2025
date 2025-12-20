{# <?php
session_start();
require_once "../connection/db_con.php";

if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $property_id = intval($_POST['property_id']);
    $price_range = floatval($_POST['price_range']);

    // Ensure the property belongs to the user
    $stmt = $conn->prepare("UPDATE properties SET price_range=? WHERE id=? AND user_id=?");
    $stmt->bind_param("dii", $price_range, $property_id, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php");
    exit;
} #}
