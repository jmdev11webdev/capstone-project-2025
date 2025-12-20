<?php
session_start();
session_regenerate_id(true);
require_once "../connection/db_con.php";

class PropertyManager {
    private $conn;
    private $user_id;
    private $full_name;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->user_id = $_SESSION['user_id'] ?? 0;
        $this->full_name = "User";
        $this->initializeUser();
    }
    
    private function initializeUser() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../login.html");
            exit;
        }
        $this->loadUserProfile();
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
    
    public function handleAjaxRequests() {
        if (isset($_GET['ajax'])) {
            if ($_GET['ajax'] === 'province' && isset($_GET['region'])) {
                $this->getProvincesByRegion($_GET['region']);
            } elseif ($_GET['ajax'] === 'city' && isset($_GET['province'])) {
                $this->getCitiesByProvince($_GET['province']);
            }
        }
    }
    
    private function getProvincesByRegion($region) {
        $stmt = $this->conn->prepare("SELECT DISTINCT province FROM properties WHERE region=? ORDER BY province ASC");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = "<option value=''>-- Province --</option>";
        while($row = $res->fetch_assoc()) {
            $out .= "<option value='{$row['province']}'>{$row['province']}</option>";
        }
        echo $out; 
        exit;
    }
    
    private function getCitiesByProvince($province) {
        $stmt = $this->conn->prepare("SELECT DISTINCT city FROM properties WHERE province=? ORDER BY city ASC");
        $stmt->bind_param("s", $province);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = "<option value=''>-- City --</option>";
        while($row = $res->fetch_assoc()) {
            $out .= "<option value='{$row['city']}'>{$row['city']}</option>";
        }
        echo $out; 
        exit;
    }
    
    public function getFilteredProperties($filters = []) {
        $region = $filters['region'] ?? '';
        $province = $filters['province'] ?? '';
        $city = $filters['city'] ?? '';
        $classification = $filters['classification'] ?? '';
        $status = $filters['status'] ?? '';
        $min_price = $filters['min_price'] ?? '';
        $max_price = $filters['max_price'] ?? '';
        $min_discount_price = $filters['min_discount_price'] ?? '';
        $max_discount_price = $filters['max_discount_price'] ?? '';
        
        $this->handleStatusRedirect($status, $filters);
        
        // makes the properties show if the user (uploader) of proprty is active
        // if not active the properties won't show
        $query = "
            SELECT p.*
            FROM properties p
            INNER JOIN users u ON p.user_id = u.id
            WHERE u.is_active = 1
        ";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if ($region) { 
            $query .= " AND region=?"; 
            $params[] = $region; 
            $types .= "s"; 
        }
        if ($province) { 
            $query .= " AND province=?"; 
            $params[] = $province; 
            $types .= "s"; 
        }
        if ($city) { 
            $query .= " AND city=?"; 
            $params[] = $city; 
            $types .= "s"; 
        }
        if ($classification) { 
            $query .= " AND classification=?"; 
            $params[] = $classification; 
            $types .= "s"; 
        }
        if ($status) { 
            $query .= " AND status=?"; 
            $params[] = $status; 
            $types .= "s"; 
        }
        
        // Price range
        if (!empty($min_price)) {
            $query .= " AND price_range >= ?";
            $params[] = (int)$min_price;
            $types .= "i";
        }
        if (!empty($max_price)) {
            $query .= " AND price_range <= ?";
            $params[] = (int)$max_price;
            $types .= "i";
        }
        
        // Discount price range
        if (!empty($min_discount_price)) {
            $query .= " AND discount_price >= ?";
            $params[] = (int)$min_discount_price;
            $types .= "i";
        }
        if (!empty($max_discount_price)) {
            $query .= " AND discount_price <= ?";
            $params[] = (int)$max_discount_price;
            $types .= "i";
        }

        // Exclude current user's properties
        $query .= " AND user_id != ?";
        $params[] = $this->user_id;
        $types .= "i";
        
        $stmt = $this->conn->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $properties;
    }
    
    private function handleStatusRedirect($status, $filters) {
        if ($status === '') {
            $queryParams = $filters;
            $queryParams['status'] = 'available';
            $redirectUrl = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($queryParams);
            header("Location: " . $redirectUrl);
            exit();
        }
    }
    
    public function getSavedPropertyIds() {
        $savedIds = [];
        $checkSaved = $this->conn->prepare("SELECT property_id FROM saved_properties WHERE user_id=?");
        $checkSaved->bind_param("i", $this->user_id);
        $checkSaved->execute();
        $res = $checkSaved->get_result();
        while($row = $res->fetch_assoc()){
            $savedIds[] = intval($row['property_id']);
        }
        $checkSaved->close();
        return $savedIds;
    }
    
    public function getPropertyConversations() {
        $msgQuery = $this->conn->prepare("
            SELECT DISTINCT p.id AS property_id, p.title
            FROM messages m
            JOIN properties p ON p.id = m.property_id
            WHERE m.sender_id = ? OR m.receiver_id = ? OR p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $msgQuery->bind_param("iii", $this->user_id, $this->user_id, $this->user_id);
        $msgQuery->execute();
        $propertyConvos = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $msgQuery->close();
        return $propertyConvos;
    }
    
    public function getNotifications() {
        $notifQuery = $this->conn->prepare("
            SELECT id, type, title, message, is_read, created_at 
            FROM notifications 
            WHERE user_id=? 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $notifQuery->bind_param("i", $this->user_id);
        $notifQuery->execute();
        $notifResult = $notifQuery->get_result();
        $notifications = $notifResult->fetch_all(MYSQLI_ASSOC);
        $notifQuery->close();
        return $notifications;
    }
    
    public function getUnreadMessageCount() {
        $msgCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id=? AND is_read=0");
        $msgCountQuery->bind_param("i", $this->user_id);
        $msgCountQuery->execute();
        $msgCount = intval($msgCountQuery->get_result()->fetch_assoc()['count']);
        $msgCountQuery->close();
        return $msgCount;
    }
    
    public function getUnreadNotificationCount() {
        $notifCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND is_read=0");
        $notifCountQuery->bind_param("i", $this->user_id);
        $notifCountQuery->execute();
        $notifCountResult = $notifCountQuery->get_result()->fetch_assoc();
        $notifCount = intval($notifCountResult['count']);
        $notifCountQuery->close();
        return $notifCount;
    }
    
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
        while($row = $res->fetch_assoc()){
            $unreadCounts[$row['property_id']] = $row['unread_count'];
        }
        $unreadQuery->close();
        return $unreadCounts;
    }
    
    public function saveProperty($property_id) {
        $property_id = intval($property_id);
        
        // Check if already saved
        $stmt = $this->conn->prepare("SELECT id FROM saved_properties WHERE user_id=? AND property_id=?");
        $stmt->bind_param("ii", $this->user_id, $property_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if($res->num_rows > 0){
            // Already saved, do nothing
            $stmt->close();
            return false;
        } else {
            $stmt->close();

            // Insert into saved_properties
            $stmt = $this->conn->prepare("INSERT INTO saved_properties (user_id, property_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $this->user_id, $property_id);
            $stmt->execute();
            $stmt->close();

            // Fetch property uploader
            $prop = $this->conn->prepare("SELECT user_id, title FROM properties WHERE id=?");
            $prop->bind_param("i", $property_id);
            $prop->execute();
            $p = $prop->get_result()->fetch_assoc();
            $prop->close();

            if($p){
                $uploader_id = intval($p['user_id']);
                $title = htmlspecialchars($p['title'] ?? 'your property');

                // Insert notification for uploader
                $notif = $this->conn->prepare("
                    INSERT INTO notifications 
                    (user_id, type, title, message, is_read, notifiable_type, notifiable_id, created_at, updated_at)
                    VALUES (?, 'property_saved', 'Property Saved', ?, 0, 'property', ?, NOW(), NOW())
                ");
                $msg = "A user has saved your property: $title";
                $notif->bind_param("isi", $uploader_id, $msg, $property_id);
                $notif->execute();
                $notif->close();
            }
            return true;
        }
    }
    
    public function unsaveProperty($property_id) {
        $property_id = intval($property_id);
        
        // Delete saved property
        $stmt = $this->conn->prepare("DELETE FROM saved_properties WHERE user_id=? AND property_id=?");
        $stmt->bind_param("ii", $this->user_id, $property_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows > 0;
    }
    
    public function getSavedProperties() {
        $query = "
            SELECT p.*, sp.created_at AS saved_on
            FROM saved_properties sp
            JOIN properties p ON sp.property_id = p.id
            WHERE sp.user_id = ?
            ORDER BY sp.created_at DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $saved_properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $saved_properties;
    }
    
    public function buildFilterQuery($baseParams = []) {
        $baseQuery = $baseParams;
        unset($baseQuery['status']);
        $filterQuery = http_build_query($baseQuery);
        return $filterQuery ? $filterQuery . '&' : '';
    }
    
    public function buildStatusUrl($status, $params = []) {
        $newParams = $params;
        unset($newParams['status']);
        $newParams['status'] = $status;

        if (isset($_GET['min_price'])) {
            $newParams['min_price'] = $_GET['min_price'];
        }
        if (isset($_GET['max_price'])) {
            $newParams['max_price'] = $_GET['max_price'];
        }

        return '?' . http_build_query($newParams);
    }
    
    // Getters
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getFullName() {
        return $this->full_name;
    }
}

// Initialize the PropertyManager
$propertyManager = new PropertyManager($conn);

// Handle save/unsave actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $property_id = intval($_GET['id']);
    
    if ($_GET['action'] === 'save') {
        $propertyManager->saveProperty($property_id);
        header("Location: properties.php");
        exit;
    } elseif ($_GET['action'] === 'unsave') {
        $propertyManager->unsaveProperty($property_id);
        header("Location: properties.php");
        exit;
    }
}

// Handle AJAX requests first
$propertyManager->handleAjaxRequests();

// Get filter parameters
$region = $_GET['region'] ?? '';
$province = $_GET['province'] ?? '';
$city = $_GET['city'] ?? '';
$classification = $_GET['classification'] ?? '';
$status = $_GET['status'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$min_discount_price = $_GET['min_discount_price'] ?? '';
$max_discount_price = $_GET['max_discount_price'] ?? '';

// Prepare filters array
$filters = [
    'region' => $region,
    'province' => $province,
    'city' => $city,
    'classification' => $classification,
    'status' => $status,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'min_discount_price' => $min_discount_price,
    'max_discount_price' => $max_discount_price
];

// Get data using the PropertyManager
$properties = $propertyManager->getFilteredProperties($filters);
$savedIds = $propertyManager->getSavedPropertyIds();
$propertyConvos = $propertyManager->getPropertyConversations();
$notifications = $propertyManager->getNotifications();
$msgCount = $propertyManager->getUnreadMessageCount();
$notifCount = $propertyManager->getUnreadNotificationCount();
$unreadCounts = $propertyManager->getUnreadMessageCountsPerProperty();
$saved_properties = $propertyManager->getSavedProperties();

// Get user info
$user_id = $propertyManager->getUserId();
$full_name = $propertyManager->getFullName();

// Build filter query for UI
$baseQuery = $_GET;
unset($baseQuery['status']);
$filterQuery = http_build_query($baseQuery);
$filterQuery = $filterQuery ? $filterQuery . '&' : '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LandSeek | Properties</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles/users.css">
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
        <li><a href="dashboard.php" ><i class="fas fa-dashboard"></i> Dashboard</a></li>
        <li><a href="properties.php" class="ordinary"><i class="fas fa-list"></i> Properties</a></li>
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
      <a href="saved_properties.php"><i class="fa-solid fa-bookmark"></i> Favorites</a>
      <a href="map.php"><i class="fa-solid fa-map"></i> Map</a>
    </div>
    
  </header>

<!-- Filter Bar -->
<div class="filter-nav">
<form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap: 5px;">
    <!-- Price Range -->
    <input type="number" name="min_price" placeholder="₱ Min Price"
          value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>"
          style="width:110px;">

    <input type="number" name="max_price" placeholder="₱ Max Price"
          value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>"
          style="width:110px;">

    <!-- Discount Price Range -->
    <input type="number" name="min_discount_price" placeholder="₱ Min Discount"
          value="<?php echo htmlspecialchars($_GET['min_discount_price'] ?? ''); ?>"
          style="width:110px;">

    <input type="number" name="max_discount_price" placeholder="₱ Max Discount"
          value="<?php echo htmlspecialchars($_GET['max_discount_price'] ?? ''); ?>"
          style="width:110px;">
    <br><br>

    <!-- Region -->
    <select id="regionSelect" name="region" style="font-size:xx-small;">
        <option value="">Region</option>
        <?php
        $regions = $conn->query("SELECT DISTINCT region FROM properties ORDER BY region ASC");
        while($r = $regions->fetch_assoc()):
        ?>
        <option value="<?php echo $r['region']; ?>" <?php if($region==$r['region']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($r['region']); ?>
        </option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <!-- Rest of your existing filter code remains the same -->
    <!-- Province -->
    <select id="provinceSelect" name="province">
        <option value="">Province</option>
        <?php
        if ($region) {
            $provinces = $conn->prepare("SELECT DISTINCT province FROM properties WHERE region=? ORDER BY province ASC");
            $provinces->bind_param("s",$region);
            $provinces->execute();
            $resProv = $provinces->get_result();
            while($p = $resProv->fetch_assoc()):
        ?>
        <option value="<?php echo $p['province']; ?>" <?php if($province==$p['province']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($p['province']); ?>
        </option>
        <?php endwhile; $provinces->close(); } ?>
    </select>
    <br><br>

    <!-- City -->
    <select id="citySelect" name="city">
        <option value="">City</option>
        <?php
        if ($province) {
            $cities = $conn->prepare("SELECT DISTINCT city FROM properties WHERE province=? ORDER BY city ASC");
            $cities->bind_param("s",$province);
            $cities->execute();
            $resCity = $cities->get_result();
            while($c = $resCity->fetch_assoc()):
        ?>
        <option value="<?php echo $c['city']; ?>" <?php if($city==$c['city']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($c['city']); ?>
        </option>
        <?php endwhile; $cities->close(); } ?>
    </select>

    <!-- Classification -->
    <select name="classification">
        <option value="">Classification</option>
        <option value="Residential" <?php if($classification=='Residential') echo 'selected'; ?>>Residential</option>
        <option value="Residential & Commercial" <?php if($classification=='Residential & Commercial') echo 'selected'; ?>>Residential & Commercial</option>
        <option value="Commercial" <?php if($classification=='Commercial') echo 'selected'; ?>>Commercial</option>
        <option value="Agricultural" <?php if($classification=='Agricultural') echo 'selected'; ?>>Agricultural</option>
        <option value="Industrial" <?php if($classification=='Industrial') echo 'selected'; ?>>Industrial</option>
        <option value="Institutional" <?php if($classification=='Institutional') echo 'selected'; ?>>Institutional</option>
        <option value="Park & Recreational" <?php if($classification=='Park & Recreational') echo 'selected'; ?>>Park & Recreational</option>
        <option value="Others" <?php if($classification=='Others') echo 'selected'; ?>>Others</option>
    </select>

    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>

    <!-- Category Nav (desktop) -->
    <div class="category-nav-desktop">
      <a href="?<?php echo $filterQuery; ?>status=available" 
      class="<?php echo ($_GET['status'] ?? '')==='available'?'active':''; ?>">Available</a>

      <a href="?<?php echo $filterQuery; ?>status=pending" 
        class="<?php echo ($_GET['status'] ?? '')==='pending'?'active':''; ?>">Pending</a>

      <a href="?<?php echo $filterQuery; ?>status=sold" 
        class="<?php echo ($_GET['status'] ?? '')==='sold'?'active':''; ?>">Sold</a>
    </div>
</form>
</div>

<?php
if (!function_exists('buildStatusUrl')) {
    function buildStatusUrl($status, $params = []) {
        $newParams = $params;
        unset($newParams['status']);
        $newParams['status'] = $status;

        // preserve budget filters if present in the URL
        if (isset($_GET['min_price'])) $newParams['min_price'] = $_GET['min_price'];
        if (isset($_GET['max_price'])) $newParams['max_price'] = $_GET['max_price'];

        return '?' . http_build_query($newParams);
    }
}
?>

<!-- Property Grid -->
<div class="property-grid">
<?php if($properties && count($properties) > 0): ?>
  <?php foreach($properties as $p): ?>
    <div class="property-card">
      <div class="property-actions">
      <p class="visits" style="color: #fff;">
        <b>
          <i class="fa-solid fa-eye" style="color: #fff; margin-right: 5px;"></i>
          Visits:
        </b> 
        <?php echo (int)$p['visits']; ?>
      </p>


        <div class="property-menu">
          <button class="report-btn" onclick="openReportModal(
            <?php echo (int)$p['id']; ?>, 
            <?php echo (int)$p['user_id']; ?>, 
            '<?php echo htmlspecialchars($p['title']); ?>'
          )">
            <i class="fa-solid fa-circle-exclamation"></i> Report
          </button>
        </div>
        <br> <br>
      <!-- Main Image -->
      <?php 
      $images = json_decode($p['images'], true);
      $mainImage = !empty($images[0]) ? $images[0] : null;
      ?>
      <?php if($mainImage): ?>
        <img src="../uploads/<?php echo htmlspecialchars($mainImage); ?>" 
             alt="Property Image" width="300" height="200" style="border-radius:6px;">
      <?php endif; ?>

      <h3><?php echo htmlspecialchars($p['title'] ?? 'Untitled'); ?></h3>
      <p><i class="fa-solid fa-location-dot"></i> 
        <?php echo htmlspecialchars(($p['city'] ?? '') . ($p['province'] ? ', '.$p['province'] : '')); ?>
      </p>
      <p><i class="fa-solid fa-money-bill"></i><b> Price Range:</b> ₱ <?php echo number_format($p['price_range'] ?? 0); ?></p> 
      <p><i class="fa-solid fa-money-bill"></i><b> Discount Price:</b> ₱ <?php echo number_format($p['discount_price'] ?? 0); ?></p> 
      
      <?php 
      // Classification label
      if (!empty($p['classification'])): 
        $classKey = str_replace([' ', '&'], '', $p['classification']); ?>
        <span class="classification-label classification-<?php echo $classKey; ?>">
          <i class="fa-regular fa-circle-question"></i><b> Classification: </b><?php echo htmlspecialchars($p['classification']); ?>
        </span>
      <?php endif; ?>
      <br>

      <!-- Property Actions -->
        <?php if($p['user_id'] == $user_id): ?>
          <!-- Owner actions -->
          <a href="update-property.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-green-mid">Update</a>
          <a href="delete-property.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-green-dark" onclick="return confirm('Delete this property?')">Delete</a>
          <form action="set-status.php" method="post" style="display:inline;">
            <input type="hidden" name="property_id" value="<?php echo (int)$p['id']; ?>">
            <select name="status" onchange="this.form.submit()" class="btn-select">
              <option value="available" <?php if($p['status']=='available') echo 'selected';?>>Available</option>
              <option value="pending" <?php if($p['status']=='pending') echo 'selected';?>>Pending</option>
              <option value="sold" <?php if($p['status']=='sold') echo 'selected';?>>Sold</option>
            </select>
          </form>

        <?php else: ?>
          <?php if($p['status'] === 'sold'): ?>
            <!-- Disabled buttons if sold -->
            <a href="javascript:void(0)" class="btn btn-green-dark disabled-btn"><i class="fa-solid fa-message"></i> Inquire</a>
            <a href="javascript:void(0)" class="btn btn-green-dark disabled-btn"><i class="fa-solid fa-bookmark"></i> Save </a>
            <a href="javascript:void(0)" class="btn btn-green-dark disabled-btn"><i class="fa-solid fa-eye"></i> View Details</a>
            <small class="property-guide" style="color:#e74c3c;">This property has been sold</small>
          <?php else: ?>
            <!-- Normal actions if not sold -->
            <a href="messaging.php?user_id=<?php echo (int)$p['user_id']; ?>&property_id=<?php echo (int)$p['id']; ?>" 
               class="btn btn-green-dark">
               <i class="fa-solid fa-message"></i> Inquire Uploader
            </a>

            <?php if(in_array((int)$p['id'], $savedIds)): ?>
                <a href="properties.php?action=unsave&id=<?php echo (int)$p['id']; ?>" class="btn btn-green-mid">Unsave</a>
            <?php else: ?>
                <a href="properties.php?action=save&id=<?php echo (int)$p['id']; ?>" class="btn btn-green-dark"><i class="fa-solid fa-bookmark"></i> Save to Favorites</a>
            <?php endif; ?>

            <small class="property-guide"><p>Click to view full details</p></small>
            <a href="full_details.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-green-dark">
              <i class="fa-solid fa-eye"></i> View Details
            </a>
          <?php endif; ?>
        <?php endif; ?>

        <!-- View on Map -->
        <?php if (!empty($p['latitude']) && !empty($p['longitude'])): ?>
          <a href="javascript:void(0)" 
             class="btn btn-green-dark" 
             onclick="openMapModal(<?php echo (float)$p['latitude']; ?>, <?php echo (float)$p['longitude']; ?>, '<?php echo htmlspecialchars($p['title']); ?>')">
             <i class="fa-solid fa-map-location-dot"></i> View on Map
          </a>
        <?php endif; ?>

        <!-- View Images Button -->
        <?php if(!empty($images) && count($images) > 0): ?>
          <a href="javascript:void(0)" class="btn btn-green-darker" 
             onclick="openImagesModal(<?php echo htmlspecialchars(json_encode($images)); ?>)">
             <i class="fa-solid fa-image"></i> View Images
          </a>
        <?php endif; ?>

        <!-- Land Tour Video Button -->
        <?php if(!empty($p['land_tour_video'])): ?>
          <a href="javascript:void(0)" class="btn btn-green-darker" 
            onclick="openVideoModal('<?php echo htmlspecialchars($p['land_tour_video']); ?>')">
            <i class="fa-solid fa-video"></i> Land Tour Video
          </a>
        <?php endif; ?>

        <!-- Status Label -->
        <?php if (!empty($p['status'])): ?>
          <p>
            <span class="status-label 
              <?php 
                echo ($p['status'] === 'available') ? 'status-available' : 
                    (($p['status'] === 'pending') ? 'status-pending' : 'status-sold'); 
              ?>">
              <?php echo ucfirst($p['status']); ?>
            </span>
          </p>
        <?php endif; ?>

      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <p style="text-align:center;grid-column:1/-1;">No properties found.</p>
<?php endif; ?>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeReportModal()">&times;</span>
    <h2>Report Property</h2> <br>
    <form id="reportForm" method="post" action="report-post.php">
      <input type="hidden" name="property_id" id="report_property_id">
      <input type="hidden" name="reported_user_id" id="report_user_id">

      <p><b>Property:</b> <span id="report_property_title"></span></p> <br>

      <label for="reason"><b>Reason:</b></label> <br> <br>
      <select name="reason" id="reason" required>
        <option value="">-- Select Reason --</option>
        <option value="spam">Spam</option>
        <option value="fraud">Fraud</option>
        <option value="duplicate">Duplicate Post</option>
        <option value="other">Other</option>
      </select><br><br><br>

      <label for="details">Details (optional):</label> <br><br>
      <textarea name="details" id="details" rows="4"></textarea>

      <button type="submit" class="btn btn-green-dark">Submit Report</button>
    </form>
  </div>
</div>

<!-- Images Modal -->
<div id="imagesModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeImagesModal()">&times;</span>
    <div id="imagesContainer" style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;"></div>
  </div>
</div>

<!-- Video Modal -->
<div id="videoModal" class="modal" style="display:none;">
  <div class="video-modal-content">
    <span class="close" onclick="closeVideoModal()">&times;</span>
    <div> 
      <video id="videoPlayer" autoplay muted controls style="">
        Your browser does not support the video tag.
      </video>
    </div>
  </div>
</div>

<!-- Floating Filter Bubble (only visible in mobile) -->
<div class="filter-bubble" onclick="openFilterModal()">
  <i class="fa-solid fa-filter"></i>
</div>

<!-- Filter Modal -->
<div id="filterModal" class="filter-modal">
  <div class="filter-modal-content">
    <span class="close" onclick="closeFilterModal()">&times;</span>
    <h3>Filter Properties</h3>
    <form method="GET" class="filter-form">
      <br>
      <!-- 🔹 Budget Filter -->
      <label><b>Budget Range (₱):</b></label> <br>
      <input type="number" name="min_price" placeholder="Min Price" 
            value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>" 
            style="width:120px;">

      <input type="number" name="max_price" placeholder="Max Price" 
            value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>" 
            style="width:120px; margin-left:10px;">
      <br><br>

      <label><b>Discout Range (₱):</b></label> <br>
      <!-- Discount Price Range -->
      <input type="number" name="min_discount_price" placeholder="₱ Min Discount"
            value="<?php echo htmlspecialchars($_GET['min_discount_price'] ?? ''); ?>"
            style="width: 120px; margin-right:10px;">

      <input type="number" name="max_discount_price" placeholder="₱ Max Discount"
            value="<?php echo htmlspecialchars($_GET['max_discount_price'] ?? ''); ?>"
            style="width:120px;">
      <br><br>

      <label><b>Region:</b></label> 
      <!-- Region -->
      <select id="modalRegionSelect" name="region">
        <option value="">-- Region --</option>
        <?php
        $regions = $conn->query("SELECT DISTINCT region FROM properties ORDER BY region ASC");
        while($r = $regions->fetch_assoc()):
        ?>
        <option value="<?php echo $r['region']; ?>" <?php if($region==$r['region']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($r['region']); ?>
        </option>
        <?php endwhile; ?>
      </select>
      <br><br>
      
      <label><b>Province:</b></label> 
      <!-- Province -->
      <select id="modalProvinceSelect" name="province">
        <option value="">-- Province --</option>
        <?php
        if ($region) {
            $provinces = $conn->prepare("SELECT DISTINCT province FROM properties WHERE region=? ORDER BY province ASC");
            $provinces->bind_param("s",$region);
            $provinces->execute();
            $resProv = $provinces->get_result();
            while($p = $resProv->fetch_assoc()):
        ?>
        <option value="<?php echo $p['province']; ?>" <?php if($province==$p['province']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($p['province']); ?>
        </option>
        <?php endwhile; $provinces->close(); } ?>
      </select>
      <br><br>
      
      <label><b>City:</b></label> 
      <!-- City -->
      <select id="modalCitySelect" name="city">
        <option value="">-- City --</option>
        <?php
        if ($province) {
            $cities = $conn->prepare("SELECT DISTINCT city FROM properties WHERE province=? ORDER BY city ASC");
            $cities->bind_param("s",$province);
            $cities->execute();
            $resCity = $cities->get_result();
            while($c = $resCity->fetch_assoc()):
        ?>
        <option value="<?php echo $c['city']; ?>" <?php if($city==$c['city']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($c['city']); ?>
        </option>
        <?php endwhile; $cities->close(); } ?>
      </select>
      <br><br>
      
      <label><b>Classification:</b></label> 
      <!-- Classification -->
      <select name="classification">
        <option value="">-- Classification --</option>
        <option value="Residential" <?php if($classification=='Residential') echo 'selected'; ?>>Residential</option>
        <option value="Residential & Commercial" <?php if($classification=='Residential & Commercial') echo 'selected'; ?>>Residential & Commercial</option>
        <option value="Commercial" <?php if($classification=='Commercial') echo 'selected'; ?>>Commercial</option>
        <option value="Agricultural" <?php if($classification=='Agricultural') echo 'selected'; ?>>Agricultural</option>
        <option value="Industrial" <?php if($classification=='Industrial') echo 'selected'; ?>>Industrial</option>
        <option value="Institutional" <?php if($classification=='Institutional') echo 'selected'; ?>>Institutional</option>
        <option value="Park & Recreational" <?php if($classification=='Park & Recreational') echo 'selected'; ?>>Park & Recreational</option>
        <option value="Others" <?php if($classification=='Others') echo 'selected'; ?>>Others</option>
      </select>
      <br><br>

      <button type="submit" class="btn btn-green">
        <i class="fa-solid fa-magnifying-glass"></i> Apply Filter
      </button>
    </form>
  </div>
</div>

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

<!-- Map Modal -->
<div id="mapModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeMapModal()">&times;</span>
    <h3 id="mapTitle"></h3>
    <div id="mapContainer" style="height:400px; width:100%; border-radius:8px;"></div>
  </div>
</div>

<!-- Interested Users Modal -->
<div id="propertyModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closePropertyModal()">&times;</span>
    <h3 id="propertyTitle">Interested Users</h3>
    <div id="interestedUsersList" style="max-height:300px;overflow-y:auto;"></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>

let mapInstance;

function openMapModal(lat, lng, title) {
  const modal = document.getElementById("mapModal");
  modal.style.display = "flex";
  document.getElementById("mapTitle").textContent = title;

  if (!mapInstance) {
    mapInstance = L.map("mapContainer");
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
    }).addTo(mapInstance);
  }

  mapInstance.setView([lat, lng], 15);

  // Remove old layers before adding a new marker
  mapInstance.eachLayer(layer => {
    if (layer instanceof L.Marker) {
      mapInstance.removeLayer(layer);
    }
  });

  L.marker([lat, lng]).addTo(mapInstance).bindPopup(title).openPopup();

  setTimeout(() => {
    mapInstance.invalidateSize();
  }, 200);
}

function closeMapModal() {
  document.getElementById("mapModal").style.display = "none";
}

// ==============================
// FILTER MODAL
// ==============================
function openFilterModal() {
  document.getElementById("filterModal").style.display = "block";
}

function closeFilterModal() {
  document.getElementById("filterModal").style.display = "none";
}

// ==============================
// CLOSE MODALS ON OUTSIDE CLICK
// ==============================
window.onclick = function(event) {
  const mapModal = document.getElementById("mapModal");
  const filterModal = document.getElementById("filterModal");

  if (event.target === mapModal) {
    mapModal.style.display = "none";
  }
  if (event.target === filterModal) {
    filterModal.style.display = "none";
  }
};
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
// ==============================
// FILTER MODAL DYNAMIC SELECTS FIX + PERSISTENCE
// ==============================
document.addEventListener("DOMContentLoaded", function() {
    const modalRegionSelect = document.getElementById("modalRegionSelect");
    const modalProvinceSelect = document.getElementById("modalProvinceSelect");
    const modalCitySelect = document.getElementById("modalCitySelect");
    const modalStatusSelect = document.getElementById("modalStatusSelect"); // classification dropdown

    // Save modal filter state
    function saveModalState() {
        const state = {
            region: modalRegionSelect?.value || "",
            province: modalProvinceSelect?.value || "",
            city: modalCitySelect?.value || "",
            classification: "<?php echo addslashes($classification ?? ''); ?>", // persist PHP classification
            status: modalStatusSelect?.value || ""
        };
        localStorage.setItem("modalFilterState", JSON.stringify(state));
    }

    // Restore modal filter state
    function restoreModalState() {
        const saved = localStorage.getItem("modalFilterState");
        let state = {
            region: "",
            province: "",
            city: "",
            classification: "<?php echo addslashes($classification ?? ''); ?>",
            status: "<?php echo addslashes($status ?? 'available'); ?>"
        };

        if (saved) {
            const parsed = JSON.parse(saved);
            // Merge PHP defaults with saved state
            state = { ...state, ...parsed };
        }

        if (modalRegionSelect) modalRegionSelect.value = state.region || "";
        if (modalStatusSelect) modalStatusSelect.value = state.status || "";

        // ✅ classification persists using PHP value (not overridden by status clicks)
        const modalClassificationSelect = document.getElementById("modalClassificationSelect");
        if (modalClassificationSelect) modalClassificationSelect.value = state.classification || "";

        // Province & City require fetching
        if (state.region && modalProvinceSelect) {
            fetch("properties.php?ajax=province&region=" + encodeURIComponent(state.region))
                .then(res => res.text())
                .then(html => {
                    modalProvinceSelect.innerHTML = html;
                    modalProvinceSelect.value = state.province || "";

                    if (state.province && modalCitySelect) {
                        fetch("properties.php?ajax=city&province=" + encodeURIComponent(state.province))
                            .then(res => res.text())
                            .then(html => {
                                modalCitySelect.innerHTML = html;
                                modalCitySelect.value = state.city || "";
                            });
                    }
                });
        }
    }

    // ==============================
    // Event Listeners
    // ==============================
    if (modalRegionSelect) {
        modalRegionSelect.addEventListener("change", function() {
            const region = this.value;
            modalProvinceSelect.innerHTML = "<option value=''>-- Province --</option>";
            modalCitySelect.innerHTML = "<option value=''>-- City --</option>";

            if (region) {
                fetch("properties.php?ajax=province&region=" + encodeURIComponent(region))
                    .then(res => res.text())
                    .then(html => { modalProvinceSelect.innerHTML = html; });
            }
            saveModalState();
        });
    }

    if (modalProvinceSelect) {
        modalProvinceSelect.addEventListener("change", function() {
            const province = this.value;
            modalCitySelect.innerHTML = "<option value=''>-- City --</option>";

            if (province) {
                fetch("properties.php?ajax=city&province=" + encodeURIComponent(province))
                    .then(res => res.text())
                    .then(html => { modalCitySelect.innerHTML = html; });
            }
            saveModalState();
        });
    }

    if (modalCitySelect) modalCitySelect.addEventListener("change", saveModalState);
    if (modalStatusSelect) modalStatusSelect.addEventListener("change", saveModalState);

    // ✅ Classification dropdown persistence
    const modalClassificationSelect = document.getElementById("modalClassificationSelect");
    if (modalClassificationSelect) {
        modalClassificationSelect.addEventListener("change", function() {
            saveModalState();
        });
    }

    // Restore state on load
    restoreModalState();
});
</script>

<script>
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
          // ✅ Owner → show modal with all inquirers
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

function closePropertyModal() {
  document.getElementById("propertyModal").style.display = "none";
}

// Close property modal on outside click
window.onclick = function(event) {
  const modals = ["mapModal", "filterModal", "propertyModal"];
  modals.forEach(id => {
    const modal = document.getElementById(id);
    if (event.target === modal) {
      modal.style.display = "none";
    }
  });
};
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
  
  <script>
    function openReportModal(propertyId, userId, title) {
      document.getElementById("report_property_id").value = propertyId;
      document.getElementById("report_user_id").value = userId;
      document.getElementById("report_property_title").innerText = title;
      document.getElementById("reportModal").style.display = "block";
    }

    function closeReportModal() {
      document.getElementById("reportModal").style.display = "none";
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      let modal = document.getElementById("reportModal");
      if (event.target === modal) {
        modal.style.display = "none";
      }
    };
  </script>

  <script>
// ==============================
// DESKTOP FILTER DYNAMIC SELECTS
// ==============================
document.addEventListener("DOMContentLoaded", function() {
    const regionSelect = document.getElementById("regionSelect");
    const provinceSelect = document.getElementById("provinceSelect");
    const citySelect = document.getElementById("citySelect");

    if (regionSelect) {
        regionSelect.addEventListener("change", function() {
            const region = this.value;
            provinceSelect.innerHTML = "<option value=''>-- Province --</option>";
            citySelect.innerHTML = "<option value=''>-- City --</option>";

            if (region) {
                fetch("properties.php?ajax=province&region=" + encodeURIComponent(region))
                    .then(res => res.text())
                    .then(html => { provinceSelect.innerHTML = html; });
            }
        });
    }

    if (provinceSelect) {
        provinceSelect.addEventListener("change", function() {
            const province = this.value;
            citySelect.innerHTML = "<option value=''>-- City --</option>";

            if (province) {
                fetch("properties.php?ajax=city&province=" + encodeURIComponent(province))
                    .then(res => res.text())
                    .then(html => { citySelect.innerHTML = html; });
            }
        });
    }
});
</script>

<script src="../javascripts/video-modal.js"></script>

<script>
let currentImageIndex = 0;
let imageList = [];

function openImagesModal(images) {
  imageList = images;
  currentImageIndex = 0;

  // Remove old modal if it exists
  let oldModal = document.querySelector(".image-modal");
  if (oldModal) oldModal.remove();

  // Create modal container
  let modal = document.createElement("div");
  modal.classList.add("image-modal");

  // Build modal content
  let content = document.createElement("div");
  content.classList.add("image-modal-content");

  // Close button
  let closeBtn = document.createElement("span");
  closeBtn.classList.add("close");
  closeBtn.innerHTML = "&times;";
  closeBtn.onclick = () => modal.remove();
  content.appendChild(closeBtn);

  // Prev button
  let prevBtn = document.createElement("span");
  prevBtn.classList.add("prev");
  prevBtn.innerHTML = "&#10094;";
  prevBtn.onclick = () => changeImage(-1);
  content.appendChild(prevBtn);

  // Next button
  let nextBtn = document.createElement("span");
  nextBtn.classList.add("next");
  nextBtn.innerHTML = "&#10095;";
  nextBtn.onclick = () => changeImage(1);
  content.appendChild(nextBtn);

  // Image element
  let img = document.createElement("img");
  img.id = "modalImage";
  img.src = "../uploads/" + imageList[currentImageIndex];
  content.appendChild(img);

  modal.appendChild(content);
  document.body.appendChild(modal);

  // Keyboard navigation
  document.onkeydown = function(e) {
    if (e.key === "ArrowLeft") changeImage(-1);
    if (e.key === "ArrowRight") changeImage(1);
    if (e.key === "Escape") modal.remove();
  };
}

function changeImage(direction) {
  currentImageIndex += direction;

  // Wrap around
  if (currentImageIndex < 0) {
    currentImageIndex = imageList.length - 1;
  } else if (currentImageIndex >= imageList.length) {
    currentImageIndex = 0;
  }

  document.getElementById("modalImage").src = "../uploads/" + imageList[currentImageIndex];
}
</script>

</body>
</html>
