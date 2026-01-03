<?php
session_start();
require_once "../connection/db_con.php";

class PropertyUpdateManager {
    private $conn;
    private $user_id;
    private $full_name;
    private $property_id;
    private $property;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
        $this->loadUserProfile();
        $this->validateProperty();
        $this->handlePropertyUpdate();
    }
    
    private function initializeSession() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../login.html");
            exit;
        }
        $this->user_id = $_SESSION['user_id'];
        $this->full_name = $_SESSION['full_name'] ?? "User";
    }
    
    private function loadUserProfile() {
        $stmt = $this->conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $this->full_name = $user['full_name'] ?? "User";
        $stmt->close();
    }
    
    /**
     * Validate property ownership
     */
    private function validateProperty() {
        $this->property_id = intval($_POST['property_id'] ?? $_GET['id'] ?? 0);
        
        if (!$this->property_id) {
            if(isset($_POST['ajax'])) { 
                echo json_encode(['success'=>false,'message'=>'Invalid property ID']); 
                exit; 
            }
            die("Invalid property ID.");
        }

        // Fetch property info (only allow owner)
        $stmt = $this->conn->prepare("SELECT * FROM properties WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $this->property_id, $this->user_id);
        $stmt->execute();
        $this->property = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$this->property) {
            if(isset($_POST['ajax'])) { 
                echo json_encode(['success'=>false,'message'=>"Property not found or permission denied"]); 
                exit; 
            }
            die("Property not found or permission denied.");
        }
    }
    
    /**
     * Handle property update form submission
     */
    private function handlePropertyUpdate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Get form values
        $region = $_POST['region'] ?? '';
        $classification = $_POST['classification'] ?? '';
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $price_range = floatval($_POST['price_range'] ?? 0);
        $discount_price = floatval($_POST['discount_price'] ?? 0);
        $area = floatval($_POST['area'] ?? 0);
        $address = $_POST['address'] ?? '';
        $street = $_POST['street'] ?? '';
        $purok = $_POST['purok'] ?? '';
        $city = $_POST['city'] ?? '';
        $province = $_POST['province'] ?? '';
        $country = $_POST['country'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $status = $_POST['status'] ?? '';
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);

        // Handle image uploads
        $imagesJson = $this->handleImageUploads();

        // Handle video upload
        $videoName = $this->handleVideoUpload();

        // Update property in database
        $this->updateProperty(
            $region, $classification, $title, $description, $price_range, $discount_price,
            $area, $address, $street, $purok, $city, $province, $country, $postal_code,
            $status, $imagesJson, $videoName, $lat, $lng
        );
    }
    
    /**
     * Handle multiple image uploads
     */
    private function handleImageUploads() {
        $existingImages = json_decode($this->property['images'] ?? '[]', true);
        $uploadedImages = [];
        
        if(!empty($_FILES['images']['name'][0])) {
            $targetDir = "../uploads/";
            foreach($_FILES['images']['name'] as $key => $name) {
                $tmpName = $_FILES['images']['tmp_name'][$key];
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($tmpName);
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue; // Skip invalid files
                }

                // Validate file size (max 5MB)
                if ($_FILES['images']['size'][$key] > 5 * 1024 * 1024) {
                    continue; // Skip oversized files
                }

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                if(move_uploaded_file($tmpName, $targetDir . $newName)) {
                    $uploadedImages[] = $newName;
                }
            }
        }
        
        $allImages = array_merge($existingImages, $uploadedImages);
        return json_encode($allImages);
    }
    
    /**
     * Handle video upload
     */
    private function handleVideoUpload() {
        if(!empty($_FILES['land_tour_video']['name'])) {
            // Validate video type
            $allowedVideoTypes = ['video/mp4', 'video/mov', 'video/avi', 'video/webm'];
            $videoType = mime_content_type($_FILES['land_tour_video']['tmp_name']);
            
            if (!in_array($videoType, $allowedVideoTypes)) {
                return $this->property['land_tour_video']; // Return existing video
            }

            // Validate video size (max 50MB)
            if ($_FILES['land_tour_video']['size'] > 50 * 1024 * 1024) {
                return $this->property['land_tour_video']; // Return existing video
            }

            $videoName = time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['land_tour_video']['name'], PATHINFO_EXTENSION);
            $videoDir = "../uploads/videos/";
            
            if(!is_dir($videoDir)) {
                mkdir($videoDir, 0777, true);
            }
            
            if(move_uploaded_file($_FILES['land_tour_video']['tmp_name'], $videoDir . $videoName)) {
                return $videoName;
            }
        }
        
        return $this->property['land_tour_video']; // Return existing video if no new upload
    }
    
    /**
     * Update property in database
     */
    private function updateProperty($region, $classification, $title, $description, $price_range, $discount_price,
                                   $area, $address, $street, $purok, $city, $province, $country, $postal_code,
                                   $status, $imagesJson, $videoName, $lat, $lng) {
        $updateStmt = $this->conn->prepare("
            UPDATE properties
            SET region=?, classification=?, title=?, description=?, price_range=?, discount_price=?,
                area=?, address=?, street=?, purok=?, city=?, province=?, country=?, postal_code=?,
                status=?, images=?, land_tour_video=?, latitude=?, longitude=?
            WHERE id=? AND user_id=?
        ");
        
        $updateStmt->bind_param(
            "ssssddissssssssssddii",
            $region, $classification, $title, $description, $price_range, $discount_price,
            $area, $address, $street, $purok, $city, $province, $country, $postal_code,
            $status, $imagesJson, $videoName, $lat, $lng, $this->property_id, $this->user_id
        );

        if(isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            if($updateStmt->execute()) {
                echo json_encode(['success'=>true]);
            } else {
                echo json_encode(['success'=>false,'message'=>$this->conn->error]);
            }
            $updateStmt->close();
            exit;
        } else {
            if($updateStmt->execute()) {
                $updateStmt->close();
                header("Location: dashboard.php?msg=Property updated successfully");
                exit;
            } else {
                die("Error updating property: ".$this->conn->error);
            }
        }
    }
    
    /**
     * Fetch Properties with Conversations for dropdown
     */
    public function getPropertyConversations() {
    $sql = "
        SELECT DISTINCT
            p.id AS property_id,
            p.title,
            p.user_id AS owner_id,
            p.created_at
        FROM properties p
        JOIN messages m ON p.id = m.property_id
        WHERE p.user_id = ?
           OR m.sender_id = ?
           OR m.receiver_id = ?
        ORDER BY p.created_at DESC
    ";

    $propertyConvos = [];

    if ($stmt = $this->conn->prepare($sql)) {
        $stmt->bind_param(
            "iii",
            $this->user_id,
            $this->user_id,
            $this->user_id
        );
        $stmt->execute();
        $propertyConvos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    return $propertyConvos;
}
    
    /**
     * Fetch Notifications for Dropdown
     */
    public function getNotifications($limit = 20) {
        $notifQuery = $this->conn->prepare("
            SELECT id, type, title, message, is_read, created_at 
            FROM notifications 
            WHERE user_id=? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $notifQuery->bind_param("ii", $this->user_id, $limit);
        $notifQuery->execute();
        $notifications = $notifQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $notifQuery->close();
        
        return $notifications;
    }
    
    /**
     * Get unread message count
     */
    public function getUnreadMessageCount() {
        $msgCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id=? AND is_read=0");
        $msgCountQuery->bind_param("i", $this->user_id);
        $msgCountQuery->execute();
        $msgCount = intval($msgCountQuery->get_result()->fetch_assoc()['count']);
        $msgCountQuery->close();
        
        return $msgCount;
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadNotificationCount() {
        $notifCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND is_read=0");
        $notifCountQuery->bind_param("i", $this->user_id);
        $notifCountQuery->execute();
        $notifCount = intval($notifCountQuery->get_result()->fetch_assoc()['count']);
        $notifCountQuery->close();
        
        return $notifCount;
    }
    
    /**
     * Get unread message counts per property
     */
    public function getUnreadMessageCountsPerProperty() {
        $unreadCounts = [];
        $unreadQuery = $this->conn->prepare("
            SELECT property_id, COUNT(*) AS unread_count
            FROM messages
            WHERE receiver_id=? AND is_read=0
            GROUP BY property_id
        ");
        $unreadQuery->bind_param("i", $this->user_id);
        $unreadQuery->execute();
        $res = $unreadQuery->get_result();
        while($row = $res->fetch_assoc()) {
            $unreadCounts[$row['property_id']] = $row['unread_count'];
        }
        $unreadQuery->close();
        
        return $unreadCounts;
    }
    
    /**
     * Get available regions for dropdown
     */
    public function getAvailableRegions() {
        $regions = [];
        $stmt = $this->conn->prepare("SELECT DISTINCT region FROM properties WHERE region IS NOT NULL AND region != '' ORDER BY region ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $regions[] = $row['region'];
        }
        $stmt->close();
        return $regions;
    }
    
    /**
     * Get available classifications for dropdown
     */
    public function getAvailableClassifications() {
        $classifications = [];
        $stmt = $this->conn->prepare("SELECT DISTINCT classification FROM properties WHERE classification IS NOT NULL AND classification != '' ORDER BY classification ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $classifications[] = $row['classification'];
        }
        $stmt->close();
        return $classifications;
    }
    
    // Getters
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getFullName() {
        return $this->full_name;
    }
    
    public function getPropertyId() {
        return $this->property_id;
    }
    
    public function getProperty() {
        return $this->property;
    }
}

// Initialize the PropertyUpdateManager
$updateManager = new PropertyUpdateManager($conn);

// Get all data using the manager
$propertyConvos = $updateManager->getPropertyConversations();
$notifications = $updateManager->getNotifications();
$msgCount = $updateManager->getUnreadMessageCount();
$notifCount = $updateManager->getUnreadNotificationCount();
$unreadCounts = $updateManager->getUnreadMessageCountsPerProperty();
$availableRegions = $updateManager->getAvailableRegions();
$availableClassifications = $updateManager->getAvailableClassifications();

// Get user info and property data
$user_id = $updateManager->getUserId();
$full_name = $updateManager->getFullName();
$property_id = $updateManager->getPropertyId();
$property = $updateManager->getProperty();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles/users.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
<title>Edit Property</title>
<style>
#map { height: 300px; margin-bottom: 20px; }
.existing-images img { margin: 5px; width: 80px; }
</style>
</head>
<body>

<header>
    <span>
      <img src="../assets/logo/LandSeekLogo.png" alt="LandSeek Logo" width="80" height="80">
    </span>
    <span>
      <b>LandSeek</b> <br>
      <small>Digital Marketplace for Land Hunting</small>
    </span>

    <nav>
      <ul>
        <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
        <li><a href="properties.php"><i class="fas fa-list"></i> Properties</a></li>
        <li><a href="saved_properties.php"><i class="fas fa-bookmark"></i> Favorites</a></li>
        <li><a href="map.php"><i class="fas fa-map"></i> Map</a></li>
      </ul>
    </nav>

    <div class="header-nav">
      <!-- Messages Dropdown -->
      <li class="dropdown">
        <button class="dropdown-btn" onclick="toggleDropdown('messages-dropdown')">
          <i class="fa-solid fa-envelope"></i>
          <span id="messagesRedDot" class="red-dot" style="display:none;"></span>
        </button>
        <div id="messages-dropdown" class="dropdown-content scrollable">
          <?php if($propertyConvos): foreach($propertyConvos as $p): ?>
            <a href="javascript:void(0)" 
              data-property-id="<?php echo (int)$p['property_id']; ?>"
              data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES); ?>"
              onclick="openPropertyModal(this.dataset.propertyId, this.dataset.title)">
              <small><i class="fa-solid fa-landmark"></i> <?php echo htmlspecialchars($p['title']); ?></small>
              <span class="new-messages-label" style="display:none;"></span>
            </a>
          <?php endforeach; else: ?>
            <a href="#"><small>No conversations yet</small></a>
          <?php endif; ?>
        </div>
      </li>

      <!-- Notifications Dropdown -->
      <li class="dropdown">
        <button class="dropdown-btn" onclick="toggleDropdown('notifications-dropdown')">
          <i class="fa-solid fa-bell"></i>
          <?php if($notifCount > 0): ?><span class="badge" id="notif-badge"><?php echo $notifCount; ?></span><?php endif; ?>
        </button>
        <div id="notifications-dropdown" class="dropdown-content scrollable">
          <?php if(!empty($notifications)): foreach($notifications as $n): ?>
            <a href="#">
              <?php echo htmlspecialchars($n['message']); ?><br>
              <small><?php echo date("M d, H:i", strtotime($n['created_at'])); ?></small>
            </a>
          <?php endforeach; else: ?>
            <a href="#"><small>No notifications</small></a>
          <?php endif; ?>
        </div>
      </li>

      <!-- Profile Dropdown -->
      <div class="dropdown">
        <button class="dropdown-btn" onclick="toggleDropdown('profile-dropdown')">
          <i class="fa-solid fa-user"></i> Profile Menu ▼
        </button>
        <div id="profile-dropdown" class="dropdown-content">
          <a href="profile.php"><i class="fa-solid fa-id-card"></i> View Profile</a>
          <a href="../authentication/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
      </div>
    </div>

    <!-- Menu button -->
    <span class="menu-btn" onclick="openNav()">&#9776;</span>

    <!-- Side navigation -->
    <div id="mySidenav" class="side-nav">
      <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
      <a href="dashboard.php" class="active"><i class="fa-solid fa-dashboard"></i> Dashboard</a>
      <a href="properties.php"><i class="fa-solid fa-list"></i> Properties</a>
      <a href="saved_properties.php"><i class="fa-solid fa-bookmark"></i> Saved to Favorites</a>
      <a href="map.php"><i class="fa-solid fa-map"></i> Map</a>
    </div>

  </header>

  <form method="POST" class="update_property" data-property-id="<?php echo (int)$property['id']; ?>" enctype="multipart/form-data">
<h2 align="center" style="color:#00CE5A">Update your property</h2>
  <label>Region:</label>
    <select id="regionSelect" name="region" required>
        <option value="">Select Region</option>
        <?php
            $regions = [
                "National Capital Region (NCR)",
                "Ilocos Region (I)",
                "Cagayan Valley (II)",
                "Central Luzon (III)",
                "Calabarzon (IV-A)",
                "Mimaropa (IV-B)",
                "Bicol Region (V)",
                "Western Visayas (VI)",
                "Central Visayas (VII)",
                "Eastern Visayas (VIII)",
                "Zamboanga Peninsula (IX)",
                "Northern Mindanao (X)",
                "Davao Region (XI)",
                "Soccsksargen (XII)",
                "Caraga (XIII)",
                "Cordillera Administrative Region (CAR)",
                "Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)"
            ];
            foreach ($regions as $r) {
                $selected = ($property['region'] ?? '') === $r ? 'selected' : '';
                echo "<option value='".htmlspecialchars($r)."' $selected>".htmlspecialchars($r)."</option>";
            }
            ?>
    </select>

    <label>Property Classification:</label>
        <select name="classification" required>
            <option value="">Select Classification</option>
                <?php
                $classifications = [
                    "Residential",
                    "Residential & Commercial",
                    "Commercial",
                    "Agricultural",
                    "Industrial",
                    "Institutional",
                    "Park & Recreational",
                    "Others"
                ];
                foreach ($classifications as $c) {
                    $selected = ($property['classification'] ?? '') === $c ? 'selected' : '';
                    echo "<option value='".htmlspecialchars($c)."' $selected>".htmlspecialchars($c)."</option>";
                }
                ?>
        </select>

    <label>Title:</label>
    <input type="text" name="title" value="<?php echo htmlspecialchars($property['title']); ?>" required>

    <label>Description:</label>
    <textarea name="description" rows="4" required><?php echo htmlspecialchars($property['description']); ?></textarea>

    <label>Price Range:</label>
    <input type="number" name="price_range" value="<?php echo $property['price_range']; ?>" step="1000" required>

    <label>Discount Price:</label>
    <input type="number" name="discount_price" value="<?php echo $property['discount_price']; ?>" step="1000">

    <label>Area (sq.m):</label>
    <input type="number" name="area" value="<?php echo $property['area']; ?>" step="1" required>

    <label>Address:</label>
    <input type="text" name="address" value="<?php echo htmlspecialchars($property['address']); ?>">

    <label>Street:</label>
    <input type="text" name="street" value="<?php echo htmlspecialchars($property['street']); ?>">

    <label>Purok:</label>
    <input type="text" name="purok" value="<?php echo htmlspecialchars($property['purok']); ?>">

    <label>City:</label>
    <input type="text" name="city" value="<?php echo htmlspecialchars($property['city']); ?>">

    <label>Province:</label>
    <input type="text" name="province" value="<?php echo htmlspecialchars($property['province']); ?>">

    <label>Country:</label>
    <input type="text" name="country" value="<?php echo htmlspecialchars($property['country']); ?>">

    <label>Postal Code:</label>
    <input type="text" name="postal_code" value="<?php echo htmlspecialchars($property['postal_code']); ?>">

    <label>Land Tour Video:</label>
    <input type="file" name="land_tour_video" accept="video/*">
    <?php if(!empty($property['land_tour_video'])): ?>
        <video width="250" controls>
            <source src="../uploads/videos/<?php echo htmlspecialchars($property['land_tour_video']); ?>" type="video/mp4">
        </video>
    <?php endif; ?>

    <label>Status:</label>
    <select name="status">
        <option value="available" <?php if($property['status']=='available') echo 'selected';?>>Available</option>
        <option value="pending" <?php if($property['status']=='pending') echo 'selected';?>>Pending</option>
        <option value="sold" <?php if($property['status']=='sold') echo 'selected';?>>Sold</option>
    </select>

    <label>Images:</label>
    <input type="file" name="images[]" multiple>
    <div class="existing-images">
        <?php 
        $images = json_decode($property['images'] ?? '[]', true);
        if($images){
            foreach($images as $img){
                echo "<img src='../uploads/".htmlspecialchars($img)."' style='width:100px;margin:5px;' />";
            }
        }
        ?>
    </div>

    <label>Location:</label>
    <div id="map" style="height:400px;"></div>
    <input type="hidden" name="lat" id="lat" value="<?php echo $property['latitude'] ?? ''; ?>">
    <input type="hidden" name="lng" id="lng" value="<?php echo $property['longitude'] ?? ''; ?>">

    <button type="submit">Update Property</button> <br>
    <a href="dashboard.php">Cancel</a>
    <span class="update-status" style="margin-left:10px;color:green;"></span>
</form>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get input elements
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');

    // Validate latitude and longitude
    const latRaw = latInput.value;
    const lngRaw = lngInput.value;

    const lat = !isNaN(parseFloat(latRaw)) ? parseFloat(latRaw) : 14.5995; // default Manila
    const lng = !isNaN(parseFloat(lngRaw)) ? parseFloat(lngRaw) : 120.9842;

    // Initialize map
    const map = L.map('map').setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    // Update hidden fields when marker dragged
    marker.on('dragend', function() {
        const pos = marker.getLatLng();
        latInput.value = pos.lat;
        lngInput.value = pos.lng;
    });

    // Move marker on map click
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        latInput.value = e.latlng.lat;
        lngInput.value = e.latlng.lng;
    });

    // AJAX form submission
    const forms = document.querySelectorAll(".update_property");
    forms.forEach(form => {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const propertyId = form.dataset.propertyId;

            const formData = new FormData(form); // automatically includes files

            // Append property ID and AJAX flag
            formData.append('property_id', propertyId);
            formData.append('ajax', '1');

            fetch('update_property.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const statusEl = form.querySelector(".update-status");
                if(data.success) {
                    statusEl.textContent = "Property updated successfully!";
                    statusEl.style.color = "green";
                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = "dashboard.php";
                    }, 1000);
                } else {
                    statusEl.textContent = "Error: " + data.message;
                    statusEl.style.color = "red";
                }
            })
            .catch(err => {
                const statusEl = form.querySelector(".update-status");
                statusEl.textContent = "Network error.";
                statusEl.style.color = "red";
            });
        });
    });
});
</script>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script>
  const socket = io('http://localhost:3001');
  const currentUserId = <?php echo (int)$user_id; ?>;
  socket.emit('register', currentUserId);

  function toggleDropdown(id) {
    document.querySelectorAll(".dropdown-content").forEach(el => {
      if (el.id !== id) el.classList.remove("show");
    });
    const menu = document.getElementById(id);
    menu.classList.toggle("show");
    if (id === "notifications-dropdown" && menu.classList.contains("show")) {
      fetch("mark_notifications_read.php")
        .then(res => res.text())
        .then(res => {
          if (res.trim() === "OK") {
            const badge = document.getElementById("notif-badge");
            if (badge) badge.remove();
          }
        });
    }
  }

  function openPropertyModal(propertyId, title) {
  const modal = document.getElementById("propertyModal");
  const list = document.getElementById("interestedUsersList");

  list.innerHTML = "<p>Loading...</p>";

  fetch(`get_interested_users.php?property_id=${propertyId}`)
    .then(res => res.text())
    .then(html => {
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = html.trim();

      const firstLink = tempDiv.querySelector("a");
      if (firstLink) {
        const isOwner = firstLink.getAttribute("data-owner") === "1";

        if (isOwner) {
          // ✅ Owner → show modal with all inquiries
          document.getElementById("propertyTitle").textContent = `Inquiries for: ${title}`;
          list.innerHTML = tempDiv.innerHTML;
          modal.style.display = "flex";
        } else {
          // ✅ Non-owner → redirect directly to conversation
          window.location.href = firstLink.getAttribute("href");
        }
      } else {
        list.innerHTML = html;
        modal.style.display = "flex";
      }
    })
    .catch(() => list.innerHTML = "<p>Error loading users.</p>");
}

function updateUnreadUI() {
    fetch('get_unread_count.php')
        .then(res => res.json())
        .then(data => {
            // 🔴 Red dot
            const dot = document.getElementById("messagesRedDot");
            if (dot) {
                dot.style.display = (data.count > 0) ? "inline-block" : "none";
            }

            // 🏠 Per-property counts
            document.querySelectorAll('[data-property-id]').forEach(el => {
                const propertyId = el.getAttribute('data-property-id');
                const count = data.properties && data.properties[propertyId] ? data.properties[propertyId] : 0;

                const label = el.querySelector('.new-messages-label');
                if (count > 0) {
                    label.textContent = count;
                    label.style.display = "inline-block";
                } else {
                    label.style.display = "none";
                }
            });
        })
        .catch(err => console.error("Unread counts error:", err));
}

// Run initially and every 5 seconds
updateUnreadUI();
setInterval(updateUnreadUI, 5000);

function closePropertyModal() {
  document.getElementById("propertyModal").style.display = "none";
}

// Optional: close modal if clicked outside
window.onclick = function(event) {
  const modal = document.getElementById("propertyModal");
  if (event.target === modal) modal.style.display = "none";
}

  function closePropertyModal() {
    document.getElementById("propertyModal").style.display = "none";
  }

  window.addEventListener("click", (e) => {
    const modal = document.getElementById("propertyModal");
    if (e.target === modal) modal.style.display = "none";
  });
</script>

    <script>
    function openNav() {
      document.getElementById("mySidenav").style.width = "250px";
    }

    function closeNav() {
      document.getElementById("mySidenav").style.width = "0";
    }

    // Optional: close when clicking outside
    window.addEventListener('click', function(e){
      const sidenav = document.getElementById("mySidenav");
      const menuBtn = document.querySelector(".menu-btn");
      if(sidenav.style.width === "250px" && !sidenav.contains(e.target) && e.target !== menuBtn){
        closeNav();
      }
    });
  </script>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-about">
        <h3>LandSeek</h3>
        <p>A Digital Marketplace for Land Hunting. 
        Find, buy, sell, and communicate with ease — 
        without middlemen.</p>
      </div>
      <div class="footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">User Guidelines</a></li>
          <li><a href="#">FAQs</a></li>
        </ul>
      </div>
      <div class="footer-support">
        <h4>Support</h4>
        <ul>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Community</a></li>
          <li><a href="#">Report an Issue</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2025 LandSeek. All rights reserved.</p>
    </div>
  </footer>

    <!-- Property Inquiries Modal -->
  <div id="propertyModal" class="property-modal">
    <div class="property-modal-content">
      <span class="property-modal-close" onclick="closePropertyModal()">&times;</span>
      <h3 id="propertyTitle"></h3>
      <div id="interestedUsersList"></div>
    </div>
  </div>
</body>
</html>
