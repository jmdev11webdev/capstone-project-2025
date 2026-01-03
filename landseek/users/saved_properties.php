<?php
session_start();
session_regenerate_id(true);
require_once "../connection/db_con.php";

class UserDashboard {
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
        $notifications = $notifQuery->get_result()->fetch_all(MYSQLI_ASSOC);
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
        $notifCount = intval($notifCountQuery->get_result()->fetch_assoc()['count']);
        $notifCountQuery->close();
        
        return $notifCount;
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
    
    // Getters
    public function getUserId() {
        return $this->user_id;
    }
    
    public function getFullName() {
        return $this->full_name;
    }
}

// Initialize the UserDashboard
$userDashboard = new UserDashboard($conn);

// Fetch all data using the class methods
$propertyConvos = $userDashboard->getPropertyConversations();
$notifications = $userDashboard->getNotifications();
$msgCount = $userDashboard->getUnreadMessageCount();
$notifCount = $userDashboard->getUnreadNotificationCount();
$saved_properties = $userDashboard->getSavedProperties();
$unreadCounts = $userDashboard->getUnreadMessageCountsPerProperty();

// Get user info for template
$user_id = $userDashboard->getUserId();
$full_name = $userDashboard->getFullName();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LandSeek | Saved Properties</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="../styles/users.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
  <style>
    
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
        <li><a href="saved_properties.php" class="ordinary"><i class="fas fa-bookmark"></i> Favorites</a></li>
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

  <!-- Page Title -->
  <section style="text-align:center; padding:30px;">
    <h1><i class="fa-solid fa-bookmark"></i> Your Saved Properties</h1>
    <p>All properties you bookmarked will appear here.</p>
  </section>

  <!-- Saved Properties Grid -->
<div class="property-grid">
<?php if($saved_properties && count($saved_properties) > 0): ?>
  <?php foreach($saved_properties as $p): ?>
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
        <br><br>

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

        <!-- Classification -->
        <?php if (!empty($p['classification'])): 
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
            <!-- Disabled buttons -->
            <a href="javascript:void(0)" class="btn btn-green-dark disabled-btn"><i class="fa-solid fa-message"></i> Inquire</a>
            <a href="javascript:void(0)" class="btn btn-green-mid disabled-btn"><i class="fa-solid fa-bookmark"></i> Save</a>
            <a href="javascript:void(0)" class="btn btn-green-dark disabled-btn"><i class="fa-solid fa-eye"></i> View Details</a>
            <small class="property-guide" style="color:#e74c3c;">This property has been sold</small>
          <?php else: ?>
            <!-- Normal actions if not sold -->
            <a href="messaging.php?user_id=<?php echo (int)$p['user_id']; ?>&property_id=<?php echo (int)$p['id']; ?>" 
               class="btn btn-green-dark">
               <i class="fa-solid fa-message"></i> Inquire Uploader
            </a>

            <!-- Unsave -->
            <a href="unsave_property.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-green-mid">
              <i class="fa-solid fa-xmark"></i> Unsave
            </a>

            <small class="property-guide"><p>Click to view full details</p></small>
            <a href="full_details.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-green-dark">
              <i class="fa-solid fa-eye"></i> View Details
            </a>

            <!-- View on Map -->
            <?php if (!empty($p['latitude']) && !empty($p['longitude'])): ?>
              <a href="javascript:void(0)" 
                 class="btn btn-green-dark" 
                 onclick="openMapModal(<?php echo (float)$p['latitude']; ?>, <?php echo (float)$p['longitude']; ?>, '<?php echo htmlspecialchars($p['title']); ?>')">
                 <i class="fa-solid fa-map-location-dot"></i> View on Map
              </a>
            <?php endif; ?>

            <!-- View Images -->
            <?php if(!empty($images) && count($images) > 0): ?>
              <a href="javascript:void(0)" class="btn btn-green-darker" 
                 onclick="openImagesModal(<?php echo htmlspecialchars(json_encode($images)); ?>)">
                 <i class="fa-solid fa-image"></i> View Images
              </a>
            <?php endif; ?>

            <!-- Land Tour Video -->
            <?php if(!empty($p['land_tour_video'])): ?>
              <a href="javascript:void(0)" class="btn btn-green-darker" 
                onclick="openVideoModal('<?php echo htmlspecialchars($p['land_tour_video']); ?>')">
                <i class="fa-solid fa-video"></i> Land Tour Video
              </a>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Status -->
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
  <p style="text-align:center;grid-column:1/-1;">You have no saved properties yet.</p>
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

  <script>
    let map; 

    function openMapModal(lat, lng, title) {
      document.getElementById('mapModal').style.display = 'flex';
      document.getElementById('mapTitle').innerText = title;

      if (!map) {
        map = L.map('mapContainer').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 18
        }).addTo(map);
      } else {
        map.setView([lat, lng], 15);
      }

      if (window.currentMarker) {
        map.removeLayer(window.currentMarker);
      }

      window.currentMarker = L.marker([lat, lng]).addTo(map)
        .bindPopup(title)
        .openPopup();

      setTimeout(() => { map.invalidateSize(); }, 200);
    }

    function closeMapModal() {
      document.getElementById('mapModal').style.display = 'none';
    }
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
