<?php
require_once "../connection/db_con.php";

$id = $_GET['id'] ?? 0;
$ajax = $_GET['ajax'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    echo "<p>Property not found.</p>";
    exit;
}

if ($ajax) {
    $images = [];
    if (!empty($property['images'])) {
        $decoded = json_decode($property['images'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $images = $decoded;
        } else {
            $images = array_filter(array_map('trim', explode(',', $property['images'])));
        }
    }
    if (count($images) === 0) $images = ['no-image.png'];

    // Property details
    echo '<h2>'.htmlspecialchars($property['title']).'</h2>';
    echo '<p>'.htmlspecialchars($property['description']).'</p>';
    echo '<p><b>Price:</b> ₱'.number_format($property['price_range']).'</p>';
    echo '<p><b>Area:</b> '.htmlspecialchars($property['area']).'</p>';
    echo '<p><b>Address:</b> '.htmlspecialchars($property['address'].', '.$property['street'].', '.$property['purok'].', '.$property['city'].', '.$property['province'].', '.$property['country'].', '.$property['postal_code']).'</p>';

    // Images
    echo '<div class="modal-images">';
    foreach($images as $img) {
        echo '<img src="../uploads/'.htmlspecialchars($img).'" alt="Property">';
    }
    echo '</div>';

    // Leaflet Map container
    echo '<div id="modalMap" style="height:300px;margin-top:15px;"></div>';

    // Leaflet CSS & JS
    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>';
    echo '<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>';

    // Leaflet initialization
    echo '<script>
        const map = L.map("modalMap").setView(['.floatval($property['latitude']).','.floatval($property['longitude']).'], 15);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "© OpenStreetMap contributors"
        }).addTo(map);
        L.marker(['.floatval($property['latitude']).','.floatval($property['longitude']).']).addTo(map)
            .bindPopup("'.addslashes($property['title']).'").openPopup();
    </script>';

    exit;
}
?>
