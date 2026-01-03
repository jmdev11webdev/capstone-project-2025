<?php
session_start();
session_regenerate_id(true);
require_once "../connection/db_con.php";

class MapManager {
    private $conn;
    private $user_id;
    private $full_name;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
        $this->loadUserProfile();
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
     * Fetch Properties with Conversations
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

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param(
        "iii",
        $this->user_id,
        $this->user_id,
        $this->user_id
    );
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $data;
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
     * Get unread message and notification counts
     */
    public function getUnreadCounts() {
        $counts = [];
        
        // Count unread messages
        $msgCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id=? AND is_read=0");
        $msgCountQuery->bind_param("i", $this->user_id);
        $msgCountQuery->execute();
        $counts['messages'] = intval($msgCountQuery->get_result()->fetch_assoc()['count']);
        $msgCountQuery->close();
        
        // Count unread notifications
        $notifCountQuery = $this->conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND is_read=0");
        $notifCountQuery->bind_param("i", $this->user_id);
        $notifCountQuery->execute();
        $counts['notifications'] = intval($notifCountQuery->get_result()->fetch_assoc()['count']);
        $notifCountQuery->close();
        
        return $counts;
    }
    
    /**
     * Fetch properties with filters for map display
     */
    public function getFilteredProperties($filters = []) {
        $region   = trim($filters['region'] ?? "");
        $province = trim($filters['province'] ?? "");
        $city     = trim($filters['city'] ?? "");

        $sql = "SELECT * FROM properties WHERE 1=1";
        $params = [];
        $types  = "";

        if ($region !== "")   { $sql .= " AND region = ?";   $params[] = $region;   $types .= "s"; }
        if ($province !== "") { $sql .= " AND province = ?"; $params[] = $province; $types .= "s"; }
        if ($city !== "")     { $sql .= " AND city = ?";     $params[] = $city;     $types .= "s"; }

        $properties = [];
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if ($params) { 
                $stmt->bind_param($types, ...$params); 
            }
            $stmt->execute();
            $properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // Fallback if prepare fails
            $res = $this->conn->query($sql);
            if ($res) { 
                $properties = $res->fetch_all(MYSQLI_ASSOC); 
            } else { 
                $properties = []; 
            }
        }
        
        return $properties;
    }
    
    /**
     * Get count of unread messages per property for the current user
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
        while($row = $res->fetch_assoc()){
            $unreadCounts[$row['property_id']] = $row['unread_count'];
        }
        $unreadQuery->close();
        
        return $unreadCounts;
    }
    
    /**
     * Get available regions for filter dropdown
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
     * Get available provinces for filter dropdown (optionally filtered by region)
     */
    public function getAvailableProvinces($region = '') {
        $provinces = [];
        if ($region) {
            $stmt = $this->conn->prepare("SELECT DISTINCT province FROM properties WHERE region = ? AND province IS NOT NULL AND province != '' ORDER BY province ASC");
            $stmt->bind_param("s", $region);
        } else {
            $stmt = $this->conn->prepare("SELECT DISTINCT province FROM properties WHERE province IS NOT NULL AND province != '' ORDER BY province ASC");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $provinces[] = $row['province'];
        }
        $stmt->close();
        return $provinces;
    }
    
    /**
     * Get available cities for filter dropdown (optionally filtered by province)
     */
    public function getAvailableCities($province = '') {
        $cities = [];
        if ($province) {
            $stmt = $this->conn->prepare("SELECT DISTINCT city FROM properties WHERE province = ? AND city IS NOT NULL AND city != '' ORDER BY city ASC");
            $stmt->bind_param("s", $province);
        } else {
            $stmt = $this->conn->prepare("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cities[] = $row['city'];
        }
        $stmt->close();
        return $cities;
    }
    
    // Getters
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getFullName() {
        return $this->full_name;
    }
}

// Initialize the MapManager
$mapManager = new MapManager($conn);

// Handle AJAX requests for dynamic dropdowns
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'provinces' && isset($_GET['region'])) {
        $provinces = $mapManager->getAvailableProvinces($_GET['region']);
        echo json_encode($provinces);
        exit;
    } elseif ($_GET['ajax'] === 'cities' && isset($_GET['province'])) {
        $cities = $mapManager->getAvailableCities($_GET['province']);
        echo json_encode($cities);
        exit;
    }
}

// Get filter parameters from URL
$region = $_GET['region'] ?? '';
$province = $_GET['province'] ?? '';
$city = $_GET['city'] ?? '';

// Prepare filters array
$filters = [
    'region' => $region,
    'province' => $province,
    'city' => $city
];

// Get all data using the MapManager
$propertyConvos = $mapManager->getPropertyConversations();
$notifications = $mapManager->getNotifications();
$unreadCounts = $mapManager->getUnreadCounts();
$properties = $mapManager->getFilteredProperties($filters);
$unreadCountsPerProperty = $mapManager->getUnreadMessageCountsPerProperty();
$availableRegions = $mapManager->getAvailableRegions();

// Get user info
$user_id = $mapManager->getUserId();
$full_name = $mapManager->getFullName();

// Extract counts for easy access
$msgCount = $unreadCounts['messages'] ?? 0;
$notifCount = $unreadCounts['notifications'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>LandSeek | Map</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"/>
<link rel="stylesheet" href="../styles/users.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
<style>
  span {
    font-size: small;
  }
  .mobile-filter-btn {
    display: none; /* Hidden by default, shown on mobile */
  }
  .mobile-filter-content {
    display:none;
  }
  /* Mobile Filter Styles */
@media (max-width: 768px) {
  .mobile-filter-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: #2e7d32;
    color: white;
    border: none;
    border-radius: 50%;
    width: 55px;
    height: 55px;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
    display: flex !important; /* Force display */
    justify-content: center;
    align-items: center;
    z-index: 1000;
    transition: transform 0.2s ease, background 0.2s ease;
  }
  
  .mobile-filter-btn:hover {
    background-color: #1b5e20;
    transform: scale(1.05);
  }

  /* Hide desktop filter nav on mobile */
  .filter-nav {
    display: none !important;
  }

  /* Modal background */
  .mobile-filter-modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
  }

  /* Modal content */
  .mobile-filter-content {
    display: block;
    background-color: #fff;
    border-radius: 10px;
    width: 90%;
    max-width: 350px;
    max-height: 80vh;
    overflow-y: auto;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    animation: popIn 0.3s ease;
  }
  
  @keyframes popIn {
    from {transform: scale(0.9); opacity: 0;}
    to {transform: scale(1); opacity: 1;}
  }

  /* Close button */
  .close-filter {
    float: right;
    font-size: 22px;
    cursor: pointer;
    color: #555;
    background: none;
    border: none;
  }
  
  .close-filter:hover {
    color: #000;
  }

  /* Apply button */
  .apply-filter-btn {
    background-color: #2e7d32;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 5px;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    margin-top: 10px;
  }
  
  .apply-filter-btn:hover {
    background-color: #1b5e20;
  }

  /* Form styling */
  #mobileFilterForm select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
  }
}
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
        <li><a href="dashboard.php" ><i class="fas fa-dashboard"></i> Dashboard</a></li>
        <li><a href="properties.php"><i class="fas fa-list"></i> Properties</a></li>
        <li><a href="saved_properties.php"><i class="fas fa-bookmark"></i> Favorites</a></li>
        <li><a href="map.php" class="ordinary"><i class="fas fa-map"></i> Map</a></li>
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

<div class="filter-nav">
  <span><i class="fa-solid fa-filter"></i> Select Locations to find your suitable land:</span>
  <form id="filterForm" method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
    <select id="regionSelect" name="region"><option value="">-- Region --</option></select>
    <select id="provinceSelect" name="province" disabled><option value="">-- Province --</option></select>
    <select id="citySelect" name="city" disabled><option value="">-- City / Municipality --</option></select>
  </form>
</div>

<!-- 📱 Floating Filter Button (Mobile Only) -->
<button id="mobileFilterBtn" class="mobile-filter-btn">
  <i class="fa-solid fa-filter"></i>
</button>

<!-- 📱 Floating Filter Modal -->
<div id="mobileFilterModal" class="mobile-filter-modal">
  <div class="mobile-filter-content">
    <span class="close-filter" onclick="toggleMobileFilter(false)">&times;</span>
    <h3><i class="fa-solid fa-filter"></i> Filter Properties</h3>
    <form id="mobileFilterForm" method="GET" style="display:flex; flex-direction:column; gap:10px;">
      <select id="regionSelectMobile" name="region"><option value="">-- Region --</option></select>
      <select id="provinceSelectMobile" name="province" disabled><option value="">-- Province --</option></select>
      <select id="citySelectMobile" name="city" disabled><option value="">-- City / Municipality --</option></select>
      <button type="submit" class="apply-filter-btn"><i class="fa-solid fa-search"></i> Apply Filter</button>
    </form>
  </div>
</div>

<div id="map"></div>
<?php if (empty($properties)): ?>
<div class="empty-note">No properties found for the selected location.</div>
<?php endif; ?>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-about"><h3>LandSeek</h3><p>A Digital Marketplace for Land Hunting. Find, buy, sell, and communicate with ease — without middlemen.</p></div>
    <div class="footer-links"><h4>Quick Links</h4><ul>
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="properties.php">Properties</a></li>
      <li><a href="saved_properties.php">Saved</a></li>
      <li><a href="map.php">Map</a></li>
    </ul></div>
    <div class="footer-support"><h4>Support</h4><ul>
      <li><a href="#">Help Center</a></li>
      <li><a href="#">Community</a></li>
      <li><a href="#">Report an Issue</a></li>
    </ul></div>
  </div>
  <div class="footer-bottom">© <?php echo date("Y"); ?> LandSeek. All rights reserved.</div>
</footer>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- Custom JS for Advanced search filters-->
<script>
var map = L.map('map').setView([12.8797, 121.7740], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  maxZoom:18, attribution:'&copy; OpenStreetMap contributors'
}).addTo(map);

var properties = <?php echo json_encode($properties, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;

// ---- place markers ----
properties.forEach(function(p){
  p.price = p.price || p.price_range;
  var lat=parseFloat(p.latitude); var lng=parseFloat(p.longitude);
  if(!isNaN(lat)&&!isNaN(lng)){
    var title=p.title?p.title:"Untitled Property";
    var loc=[p.city||'',p.province||'',p.region||''].filter(Boolean).join(", ");
    var price=(p.price&&!isNaN(p.price))?Number(p.price).toLocaleString():"N/A";
    L.marker([lat,lng]).addTo(map).bindPopup(`
      <b>${escapeHtml(title)}</b><br>
      ${escapeHtml(loc)}<br>
      ₱${price}<br>
      <a href="full_details.php?id=${p.id}" class="btn btn-blue btn-sm" style="color:#fff">View Details</a>
    `);
  }
});

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g,function(m){
    return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
  });
}

// ---- dropdowns ----
const regionSel=document.getElementById('regionSelect');
const provinceSel=document.getElementById('provinceSelect');
const citySel=document.getElementById('citySelect');

const preRegion=<?php echo json_encode($region); ?>;
const preProvince=<?php echo json_encode($province); ?>;
const preCity=<?php echo json_encode($city); ?>;

function initDropdowns(){
  // collect distinct regions
  const regions = [...new Set(properties.map(p=>p.region).filter(Boolean))].sort();
  regionSel.innerHTML = '<option value="">-- Region --</option>';
  if (regions.length > 0) regionSel.disabled = false;
  regions.forEach(r=>{
    const opt=document.createElement('option');
    opt.value=r; opt.textContent=r;
    if(preRegion && preRegion===r) opt.selected=true;
    regionSel.appendChild(opt);
  });

  // build provinces + cities (if region is preselected)
  buildProvinceOptions(preRegion);
  buildCityOptions(preProvince);

  zoomToSelection();
}

function buildProvinceOptions(selectedRegion){
  const provinces = [...new Set(
    properties
      .filter(p=>!selectedRegion || p.region===selectedRegion)
      .map(p=>p.province)
      .filter(Boolean)
  )].sort();

  provinceSel.innerHTML = '<option value="">-- Province --</option>';
  if (provinces.length > 0) provinceSel.disabled = false;
  provinces.forEach(p=>{
    const opt=document.createElement('option');
    opt.value=p; opt.textContent=p;
    if(preProvince && preProvince===p) opt.selected=true;
    provinceSel.appendChild(opt);
  });
}

function buildCityOptions(selectedProvince){
  const cities = [...new Set(
    properties
      .filter(p=>!selectedProvince || p.province===selectedProvince)
      .map(p=>p.city)
      .filter(Boolean)
  )].sort();

  citySel.innerHTML = '<option value="">-- City / Municipality --</option>';
  if (cities.length > 0) citySel.disabled = false;
  cities.forEach(c=>{
    const opt=document.createElement('option');
    opt.value=c; opt.textContent=c;
    if(preCity && preCity===c) opt.selected=true;
    citySel.appendChild(opt);
  });
}

// ---- zoom logic ----
function zoomToSelection(){
  let matches=[];

  if(citySel.value){
    matches = properties.filter(p=>p.city===citySel.value);
    if(matches.length){
      const avgLat=matches.reduce((a,c)=>a+parseFloat(c.latitude||0),0)/matches.length;
      const avgLng=matches.reduce((a,c)=>a+parseFloat(c.longitude||0),0)/matches.length;
      map.setView([avgLat,avgLng],12);
      return;
    }
  }

  if(provinceSel.value){
    matches = properties.filter(p=>p.province===provinceSel.value);
    if(matches.length){
      const avgLat=matches.reduce((a,c)=>a+parseFloat(c.latitude||0),0)/matches.length;
      const avgLng=matches.reduce((a,c)=>a+parseFloat(c.longitude||0),0)/matches.length;
      map.setView([avgLat,avgLng],9);
      return;
    }
  }

  if(regionSel.value){
    matches = properties.filter(p=>p.region===regionSel.value);
    if(matches.length){
      const avgLat=matches.reduce((a,c)=>a+parseFloat(c.latitude||0),0)/matches.length;
      const avgLng=matches.reduce((a,c)=>a+parseFloat(c.longitude||0),0)/matches.length;
      map.setView([avgLat,avgLng],7);
      return;
    }
  }

  // default PH
  map.setView([12.8797, 121.7740], 6);
}

// listeners
regionSel.addEventListener('change', function(){
  buildProvinceOptions(this.value);
  buildCityOptions(""); // reset cities
  zoomToSelection();
});
provinceSel.addEventListener('change', function(){
  buildCityOptions(this.value);
  zoomToSelection();
});
citySel.addEventListener('change', zoomToSelection);

// init
document.addEventListener('DOMContentLoaded', initDropdowns);
</script>

<!-- Messaging + Notifications + Side Nav JS -->
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

<script>
// Toggle modal visibility
function toggleMobileFilter(show = true) {
  document.getElementById("mobileFilterModal").style.display = show ? "flex" : "none";
}

// Handle open button
document.getElementById("mobileFilterBtn").addEventListener("click", () => toggleMobileFilter(true));

// Close when clicking outside content
window.addEventListener("click", function(e){
  const modal = document.getElementById("mobileFilterModal");
  if(e.target === modal) toggleMobileFilter(false);
});

// Initialize mobile dropdowns using same data
document.addEventListener('DOMContentLoaded', function(){
  const regionMobile = document.getElementById('regionSelectMobile');
  const provinceMobile = document.getElementById('provinceSelectMobile');
  const cityMobile = document.getElementById('citySelectMobile');

  const regions = [...new Set(properties.map(p=>p.region).filter(Boolean))].sort();
  regionMobile.innerHTML = '<option value="">-- Region --</option>';
  regions.forEach(r=>{
    const opt=document.createElement('option');
    opt.value=r; opt.textContent=r;
    regionMobile.appendChild(opt);
  });

  regionMobile.addEventListener('change', function(){
    const selectedRegion = this.value;
    const provinces = [...new Set(properties.filter(p=>p.region===selectedRegion).map(p=>p.province).filter(Boolean))].sort();
    provinceMobile.innerHTML = '<option value="">-- Province --</option>';
    provinceMobile.disabled = provinces.length === 0;
    provinces.forEach(p=>{
      const opt=document.createElement('option');
      opt.value=p; opt.textContent=p;
      provinceMobile.appendChild(opt);
    });
    cityMobile.innerHTML = '<option value="">-- City / Municipality --</option>';
    cityMobile.disabled = true;
  });

  provinceMobile.addEventListener('change', function(){
    const selectedProvince = this.value;
    const cities = [...new Set(properties.filter(p=>p.province===selectedProvince).map(p=>p.city).filter(Boolean))].sort();
    cityMobile.innerHTML = '<option value="">-- City / Municipality --</option>';
    cityMobile.disabled = cities.length === 0;
    cities.forEach(c=>{
      const opt=document.createElement('option');
      opt.value=c; opt.textContent=c;
      cityMobile.appendChild(opt);
    });
  });
});
</script>

  <div id="propertyModal" class="property-modal">
  <div class="property-modal-content">
    <span class="property-modal-close" onclick="closePropertyModal()">&times;</span>
    <h3 id="propertyTitle"></h3>
    <div id="interestedUsersList"></div>
  </div>
</div>

</body>
</html>
