<?php
session_start();
require_once "../connection/db_con.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    echo "<div class='error'>You must be logged in to view user info.</div>";
    exit;
}

// Validate user_id
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo "<div class='error'>User ID is required.</div>";
    exit;
}

$requested_user_id = intval($_GET['user_id']);

try {
    // Fetch user info including profile_picture as BLOB
    $stmt = $conn->prepare("
        SELECT u.id, u.email, up.full_name, up.phonenumber, up.profile_picture
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $stmt->bind_param("i", $requested_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo "<div class='error'>User not found.</div>";
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Fetch properties
    $stmt = $conn->prepare("
        SELECT title, price_range, status
        FROM properties
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $requested_user_id);
    $stmt->execute();
    $propertiesResult = $stmt->get_result();
    $properties = [];
    while ($row = $propertiesResult->fetch_assoc()) {
        $properties[] = $row;
    }
    $stmt->close();

    $user['properties'] = $properties;
    $user['total_properties'] = count($properties);

    // Render HTML
    echo "<div class='credentials-container'>";
    echo "<div class='credentials-header'>";

    // Profile picture from DB as base64
    if (!empty($user['profile_picture'])) {
        $base64Image = base64_encode($user['profile_picture']);
        echo "<img src='data:image/jpeg;base64,{$base64Image}' class='profile-picture-large' alt='Profile Picture'>";
    } else {
        echo "<img src='' class='profile-picture-large' alt='Profile Picture'>";
    }

    echo "<div class='profile-info'>";
    echo "<h4>" . htmlspecialchars($user['full_name'] ?? 'Not provided') . "</h4>";
    echo "<div class='user-email'>" . htmlspecialchars($user['email'] ?? 'Not available') . "</div>";
    echo "</div></div>"; // header

    echo "<div class='credentials-details'>";
    echo "<div class='detail-item'><span class='detail-label'>Full Name:</span> <span class='detail-value'>" . htmlspecialchars($user['full_name'] ?? 'Not provided') . "</span></div>";
    echo "<div class='detail-item'><span class='detail-label'>Email:</span> <span class='detail-value'>" . htmlspecialchars($user['email'] ?? 'Not available') . "</span></div>";
    echo "<div class='detail-item'><span class='detail-label'>Phone Number:</span> <span class='detail-value'>" . htmlspecialchars($user['phonenumber'] ?? 'Not provided') . "</span></div>";
    echo "<div class='detail-item'><span class='detail-label'>Total Properties:</span> <span class='detail-value'>" . $user['total_properties'] . "</span></div>";
    echo "</div>";

    echo "<div class='properties-section'>";
    echo "<h4>Properties Listed ({$user['total_properties']})</h4>";
    echo "<div class='properties-list'>";
    if (!empty($properties)) {
        foreach ($properties as $property) {
            $price = !empty($property['price_range']) ? "₱" . number_format($property['price_range']) : "Price not set";
            echo "<div class='property-item'>";
            echo "<div class='property-title'>" . htmlspecialchars($property['title'] ?? 'Untitled Property') . "</div>";
            echo "<div class='property-price'>{$price} • " . htmlspecialchars($property['status'] ?? 'Unknown') . "</div>";
            echo "</div>";
        }
    } else {
        echo "<div class='no-properties'>No properties listed</div>";
    }
    echo "</div></div>"; // properties-section
    echo "</div>"; // container

} catch (Exception $e) {
    echo "<div class='error'>Error fetching user information: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$conn->close();
?>
