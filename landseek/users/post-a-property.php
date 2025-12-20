<?php
session_start();
require_once "../connection/db_con.php";

class PropertyUploadManager {
  // objects
    private $conn;
    private $user_id;
    private $full_name;
    private $success = "";
    private $error = "";
    
    // function-construct
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
        $this->loadUserProfile();
        $this->handlePropertyUpload();
    }
    
    // function to initialize session
    private function initializeSession() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../login.php");
            exit;
        }
        $this->user_id = $_SESSION['user_id'];
        $this->full_name = $_SESSION['full_name'] ?? "User";
    }
    
    // function to load user profile
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
     * Handle property upload form submission
     */
    private function handlePropertyUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Get form values
        $region         = $_POST['region'] ?? '';
        $classification = $_POST['classification'] ?? '';
        $title          = $_POST['title'] ?? '';
        $description    = $_POST['description'] ?? '';
        $price_range    = $_POST['price_range'] ?? 0;
        $discount_price = $_POST['discount_price'] ?? 0;
        $area           = $_POST['area'] ?? 0;
        $address        = $_POST['address'] ?? '';
        $street         = $_POST['street'] ?? '';
        $purok          = $_POST['purok'] ?? '';
        $city           = $_POST['city'] ?? '';
        $province       = $_POST['province'] ?? '';
        $country        = $_POST['country'] ?? 'Philippines';
        $postal_code    = $_POST['postal_code'] ?? '';
        $latitude       = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $longitude      = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
        $status         = $_POST['status'] ?? 'available';

        // Validate required fields
        if (empty($title) || empty($description) || empty($region) || empty($classification) || empty($city) || empty($province)) {
            $this->error = "❌ Please fill in all required fields.";
            return;
        }

        // Handle multiple images
        $uploadedFiles = $this->handleImageUploads();
        if (empty($uploadedFiles)) {
            $this->error = "❌ Please upload at least one image.";
            return;
        }
        $imagesJson = json_encode($uploadedFiles);

        // Handle optional land tour video
        $uploadedVideo = $this->handleVideoUpload();

        // Insert into database
        if (empty($this->error)) {
            $this->insertProperty(
                $region, $classification, $title, $description, $price_range, 
                $discount_price, $area, $address, $street, $purok, $city, 
                $province, $country, $postal_code, $latitude, $longitude, 
                $imagesJson, $uploadedVideo, $status
            );
        }
    }
    
    /**
     * Handle multiple image uploads
     */
    private function handleImageUploads() {
        $uploadedFiles = [];
        
        if (empty($_FILES['images']['name'][0])) {
            return $uploadedFiles;
        }

        // Create uploads directory if it doesn't exist
        if (!is_dir("../uploads")) {
            mkdir("../uploads", 0755, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($tmp_name);
                
                if (!in_array($fileType, $allowedTypes)) {
                    $this->error = "❌ Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.";
                    continue;
                }

                // Validate file size (max 5MB)
                if ($_FILES['images']['size'][$key] > 5 * 1024 * 1024) {
                    $this->error = "❌ File size too large. Maximum size is 5MB.";
                    continue;
                }

                $filename = time() . "_" . uniqid() . "_" . preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $_FILES['images']['name'][$key]);
                $target = "../uploads/" . $filename;
                
                if (move_uploaded_file($tmp_name, $target)) {
                    $uploadedFiles[] = $filename;
                } else {
                    $this->error = "❌ Failed to upload one or more images.";
                }
            }
        }
        
        return $uploadedFiles;
    }
    
    /**
     * Handle video upload
     */
    private function handleVideoUpload() {
        if (empty($_FILES['land_tour_video']['name'])) {
            return "";
        }

        $videoFile = $_FILES['land_tour_video'];
        
        if ($videoFile['error'] !== UPLOAD_ERR_OK) {
            $this->error = "❌ Video upload error: " . $this->getUploadError($videoFile['error']);
            return "";
        }

        // Validate video type
        $allowedVideoTypes = ['video/mp4', 'video/mov', 'video/avi', 'video/webm'];
        $videoType = mime_content_type($videoFile['tmp_name']);
        
        if (!in_array($videoType, $allowedVideoTypes)) {
            $this->error = "❌ Invalid video format. Only MP4, MOV, AVI, and WebM are allowed.";
            return "";
        }

        // Validate video size (max 50MB)
        if ($videoFile['size'] > 50 * 1024 * 1024) {
            $this->error = "❌ Video file too large. Maximum size is 50MB.";
            return "";
        }

        $videoName = time() . "_" . uniqid() . "_" . preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $videoFile['name']);
        $videoTarget = "../uploads/videos/" . $videoName;
        
        if (!is_dir("../uploads/videos")) {
            mkdir("../uploads/videos", 0755, true);
        }
        
        if (move_uploaded_file($videoFile['tmp_name'], $videoTarget)) {
            return $videoName;
        } else {
            $this->error = "❌ Failed to upload video file.";
            return "";
        }
    }
    
    /**
     * Insert property into database
     */
    private function insertProperty($region, $classification, $title, $description, $price_range, 
                                  $discount_price, $area, $address, $street, $purok, $city, 
                                  $province, $country, $postal_code, $latitude, $longitude, 
                                  $imagesJson, $uploadedVideo, $status) {
        try {
            // First, let's check the actual structure of the properties table
            $checkTable = $this->conn->query("DESCRIBE properties");
            $columns = [];
            while ($row = $checkTable->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            // Debug: Show available columns
            error_log("Available columns: " . implode(", ", $columns));

            // Use a simpler, more direct approach
            $stmt = $this->conn->prepare("
                INSERT INTO properties 
                (region, user_id, classification, title, description, price_range, discount_price, 
                 area, address, street, purok, city, province, country, postal_code, 
                 latitude, longitude, images, land_tour_video, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            // Convert null values to appropriate types for bind_param
            $latitude = ($latitude === null || $latitude === '') ? null : floatval($latitude);
            $longitude = ($longitude === null || $longitude === '') ? null : floatval($longitude);
            $price_range = floatval($price_range);
            $discount_price = floatval($discount_price);
            $area = floatval($area);

            // For null values in bind_param, we need to handle them differently
            if ($latitude === null || $longitude === null) {
                // Alternative approach for null values
                $stmt = $this->conn->prepare("
                    INSERT INTO properties 
                    (region, user_id, classification, title, description, price_range, discount_price, 
                     area, address, street, purok, city, province, country, postal_code, 
                     images, land_tour_video, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->bind_param(
                    "sisssdddisssssssss", 
                    $region, $this->user_id, $classification, $title, $description, 
                    $price_range, $discount_price, $area, $address, $street, $purok, 
                    $city, $province, $country, $postal_code, $imagesJson, $uploadedVideo, $status
                );
            } else {
                $stmt->bind_param(
                    "sisssdddissssssddsss", 
                    $region, $this->user_id, $classification, $title, $description, 
                    $price_range, $discount_price, $area, $address, $street, $purok, 
                    $city, $province, $country, $postal_code, $latitude, $longitude, 
                    $imagesJson, $uploadedVideo, $status
                );
            }

            if ($stmt->execute()) {
                $this->success = "✅ Property uploaded successfully!";
                
                // Optional: Clear form by redirecting
                // header("Location: post-a-property.php?success=1");
                // exit;
            } else {
                $this->error = "❌ Failed to upload property: " . $stmt->error;
            }

            $stmt->close();
        } catch (Exception $e) {
            $this->error = "❌ Error: " . $e->getMessage();
            
            // Additional debug info
            error_log("Insert Property Error: " . $e->getMessage());
            error_log("Data being inserted: " . print_r(func_get_args(), true));
        }
    }
    
    /**
     * Get upload error message
     */
    private function getUploadError($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Fetch Properties with Conversations for dropdown
     */
    public function getPropertyConversations() {
        $msgQuery = $this->conn->prepare("
            SELECT DISTINCT p.id AS property_id, p.title
            FROM messages m
            JOIN properties p ON p.id = m.property_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $msgQuery->bind_param("i", $this->user_id);
        $msgQuery->execute();
        $propertyConvos = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $msgQuery->close();
        
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
    
    // Getters
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getFullName() {
        return $this->full_name;
    }
    
    public function getSuccessMessage() {
        return $this->success;
    }
    
    public function getErrorMessage() {
        return $this->error;
    }
}

// Initialize the PropertyUploadManager
$uploadManager = new PropertyUploadManager($conn);

// Get all data using the manager
$propertyConvos = $uploadManager->getPropertyConversations();
$notifications = $uploadManager->getNotifications();
$notifCount = $uploadManager->getUnreadNotificationCount();

// Get user info and messages
$user_id = $uploadManager->getUserId();
$full_name = $uploadManager->getFullName();
$success = $uploadManager->getSuccessMessage();
$error = $uploadManager->getErrorMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LandSeek | Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="../styles/users.css">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

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

  <section class="upload-form">
  <h2>Upload Land Property</h2>
  <?php if(!empty($success)) echo "<p class='success'>$success</p>"; ?>
  <?php if(!empty($error)) echo "<p class='error'>$error</p>"; ?>

  <form method="POST" enctype="multipart/form-data">
            <label>Region:</label>
            <select id="regionSelect" name="region" required>
                <option value="">Region</option>
                <option value="National Capital Region (NCR)">National Capital Region (NCR)</option>
                <option value="Ilocos Region (I)">Ilocos Region (I)</option>
                <option value="Cagayan Valley (II)">Cagayan Valley (II)</option>
                <option value="Central Luzon (III)">Central Luzon (III)</option>
                <option value="Calabarzon (IV-A)">Calabarzon (IV-A)</option>
                <option value="Mimaropa (IV-B)">Mimaropa (IV-B)</option>
                <option value="Bicol Region (V)">Bicol Region (V)</option>
                <option value="Western Visayas (VI)">Western Visayas (VI)</option>
                <option value="Central Visayas (VII)">Central Visayas (VII)</option>
                <option value="Eastern Visayas (VIII)">Eastern Visayas (VIII)</option>
                <option value="Zamboanga Peninsula (IX)">Zamboanga Peninsula (IX)</option>
                <option value="Northern Mindanao (X)">Northern Mindanao (X)</option>
                <option value="Davao Region (XI)">Davao Region (XI)</option>
                <option value="Soccsksargen (XII)">Soccsksargen (XII)</option>
                <option value="Caraga (XIII)">Caraga (XIII)</option>
                <option value="Cordillera Administrative Region (CAR)">Cordillera Administrative Region (CAR)</option>
                <option value="Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)">Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)</option>
            </select>
            
            <label>Property Classification:</label>
            <select name="classification" required>
                <option value="">-- Select Classification --</option>
                <option value="Residential">Residential</option>
                <option value="Residential & Commercial">Residential & Commercial</option>
                <option value="Commercial">Commercial</option>
                <option value="Agricultural">Agricultural</option>
                <option value="Industrial">Industrial</option>
                <option value="Institutional">Institutional</option>
                <option value="Park & Recreational">Park & Recreational</option>
                <option value="Others">Others</option>
            </select>
            
            <label>Property Title:</label>
            <input type="text" name="title" placeholder="Property Title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">

            <label>Description:</label>
            <textarea name="description" placeholder="Description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

            <label>Price Range (₱):</label>
            <input type="number" step="0.01" name="price_range" placeholder="Price Range (₱)" required value="<?php echo htmlspecialchars($_POST['price_range'] ?? ''); ?>">
            
            <label>Discount Price (₱):</label>
            <input type="number" step="0.01" name="discount_price" placeholder="Discount Price (₱)" value="<?php echo htmlspecialchars($_POST['discount_price'] ?? ''); ?>">
            
            <label>Measurement of Area (sqm):</label>
            <input type="number" step="0.01" name="area" placeholder="Area (sqm)" required value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>">

            <label>Complete Address:</label>
            <input type="text" name="address" placeholder="Address" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">

            <label>Street:</label>
            <input type="text" name="street" placeholder="Street" value="<?php echo htmlspecialchars($_POST['street'] ?? ''); ?>">

            <label>Purok / Barangay:</label>
            <input type="text" name="purok" placeholder="Purok / Barangay" value="<?php echo htmlspecialchars($_POST['purok'] ?? ''); ?>">
            
            <label>Province:</label>
            <input type="text" name="province" placeholder="Province" required value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
            
            <label>City:</label>
            <input type="text" name="city" placeholder="City / Municipality" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
            
            <label>Country:</label>
            <input type="text" name="country" placeholder="Country" required value="Philippines">
            
            <label>Postal Code:</label>
            <input type="text" name="postal_code" placeholder="Postal Code" value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">

            <label style="display:none;">Latitude:</label>
            <input type="hidden" id="latitude" name="latitude" placeholder="Latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">

            <label style="display:none;">Longitude:</label>
            <input type="hidden" id="longitude" name="longitude" placeholder="Longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">

            <label>Upload Images (Multiple):</label>
            <input type="file" name="images[]" multiple accept="image/*" required>
            
            <label>Upload Land Tour Video:</label>
            <input type="file" name="land_tour_video" accept="video/mp4,video/mkv,video/avi" required>
            
            <label>Status:</label>
            <select name="status" required>
                <option value="available">Available</option>
                <option value="sold">Sold</option>
                <option value="pending">Pending</option>
            </select>

            <button type="submit" style="background-color:#28A228">Upload Property</button>
        </form>

  <div id="map"></div>
</section>

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

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
  // ✅ Initialize Leaflet map
  var map = L.map('map').setView([12.8797, 121.7740], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker;

  // ✅ On map click -> set marker + save lat/long into hidden fields
  map.on('click', function(e) {
    var lat = e.latlng.lat.toFixed(7);
    var lng = e.latlng.lng.toFixed(7);

    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;

    if (marker) {
      marker.setLatLng(e.latlng);
    } else {
      marker = L.marker(e.latlng).addTo(map);
    }
  });

  // ✅ Fix tile rendering issue
  setTimeout(function() {
    map.invalidateSize();
  }, 300);

  // ✅ PSGC cascading dropdowns with error handling
  async function loadRegions() {
    try {
      let res = await fetch('https://psgc.gitlab.io/api/regions/');
      if (!res.ok) throw new Error("HTTP " + res.status);
      let data = await res.json();
      data.sort((a,b)=>a.name.localeCompare(b.name));
      data.forEach(r => {
        let opt = document.createElement('option');
        opt.value = r.name;
        opt.textContent = r.name;
        opt.dataset.code = r.code;
        document.getElementById('regionSelect').appendChild(opt);
      });
    } catch (err) {
      console.error("Failed to load regions:", err);
      document.getElementById('regionSelect').innerHTML =
        "<option value=''>⚠ Failed to load regions</option>";
    }
  }

  async function loadProvinces() {
    try {
      let sel = document.getElementById('regionSelect').selectedOptions[0];
      let provinceSel = document.getElementById('provinceSelect');
      provinceSel.innerHTML = '<option value="">-- Province --</option>';
      document.getElementById('citySelect').innerHTML = '<option value="">-- City / Municipality --</option>';
      if (sel && sel.dataset.code) {
        let res = await fetch(`https://psgc.gitlab.io/api/regions/${sel.dataset.code}/provinces/`);
        if (!res.ok) throw new Error("HTTP " + res.status);
        let data = await res.json();
        data.sort((a,b)=>a.name.localeCompare(b.name));
        data.forEach(p => {
          let opt = document.createElement('option');
          opt.value = p.name;
          opt.textContent = p.name;
          opt.dataset.code = p.code;
          provinceSel.appendChild(opt);
        });
        provinceSel.disabled = false;
      }
    } catch (err) {
      console.error("Failed to load provinces:", err);
      document.getElementById('provinceSelect').innerHTML =
        "<option value=''>⚠ Failed to load provinces</option>";
    }
  }

  async function loadCities() {
    try {
      let sel = document.getElementById('provinceSelect').selectedOptions[0];
      let citySel = document.getElementById('citySelect');
      citySel.innerHTML = '<option value="">-- City / Municipality --</option>';
      if (sel && sel.dataset.code) {
        let res = await fetch(`https://psgc.gitlab.io/api/provinces/${sel.dataset.code}/cities-municipalities/`);
        if (!res.ok) throw new Error("HTTP " + res.status);
        let data = await res.json();
        data.sort((a,b)=>a.name.localeCompare(b.name));
        data.forEach(c => {
          let opt = document.createElement('option');
          opt.value = c.name;
          opt.textContent = c.name;
          citySel.appendChild(opt);
        });
        citySel.disabled = false;
      }
    } catch (err) {
      console.error("Failed to load cities:", err);
      document.getElementById('citySelect').innerHTML =
        "<option value=''>⚠ Failed to load cities</option>";
    }
  }

  document.getElementById('regionSelect').addEventListener('change', loadProvinces);
  document.getElementById('provinceSelect').addEventListener('change', loadCities);
  loadRegions();
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
      document.getElementById("propertyTitle").textContent = `Inquiries for: ${title}`;
      list.innerHTML = "<p>Loading...</p>";
      modal.style.display = "flex";

      fetch(`fetch_property_users.php?property_id=${propertyId}`)
        .then(res => res.json())
        .then(users => {
          if(users.length === 0){
            list.innerHTML = "<p>No inquiries yet.</p>";
          } else {
            list.innerHTML = "";
            users.forEach(u => {
              const a = document.createElement("a");
              a.href = `messaging.php?user_id=${u.user_id}&property_id=${propertyId}`;
              a.textContent = u.full_name;
              a.style.display = "block";
              list.appendChild(a);
            });
          }
        })
        .catch(() => list.innerHTML = "<p>Error loading users.</p>");
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
</body>
</html>
