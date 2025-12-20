<?php
session_start();
session_regenerate_id(true);
require_once "../connection/db_con.php";

class PropertyDetailsManager {
    private $conn;
    private $user_id;
    private $full_name;
    private $property_id;
    private $property;
    private $images = [];
    private $savedIds = [];
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
        $this->loadUserProfile();
        $this->loadSavedProperties();
        $this->validateProperty();
        $this->handlePropertyVisit();
        $this->handleReportSubmission();
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
     * Load user's saved properties
     */
    private function loadSavedProperties() {
        $checkSaved = $this->conn->prepare("SELECT property_id FROM saved_properties WHERE user_id=?");
        $checkSaved->bind_param("i", $this->user_id);
        $checkSaved->execute();
        $res = $checkSaved->get_result();
        while($row = $res->fetch_assoc()) {
            $this->savedIds[] = intval($row['property_id']);
        }
        $checkSaved->close();
    }
    
    /**
     * Validate and load property details
     */
    private function validateProperty() {
        if (!isset($_GET['id'])) {
            die("Property ID missing.");
        }
        
        $this->property_id = intval($_GET['id']);
        
        $stmt = $this->conn->prepare("SELECT * FROM properties WHERE id = ?");
        $stmt->bind_param("i", $this->property_id);
        $stmt->execute();
        $this->property = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$this->property) {
            die("Property not found.");
        }
        
        // Decode images JSON
        if (!empty($this->property['images'])) {
            $decoded = json_decode($this->property['images'], true);
            if (is_array($decoded)) {
                $this->images = $decoded;
            }
        }
    }
    
    /**
     * Handle property visit tracking
     */
    private function handlePropertyVisit() {
        if (!isset($_SESSION['visited_properties'])) {
            $_SESSION['visited_properties'] = [];
        }
        
        if (!in_array($this->property_id, $_SESSION['visited_properties'])) {
            $updateVisit = $this->conn->prepare("UPDATE properties SET visits = visits + 1 WHERE id = ?");
            $updateVisit->bind_param("i", $this->property_id);
            $updateVisit->execute();
            $updateVisit->close();
            
            $_SESSION['visited_properties'][] = $this->property_id;
        }
    }
    
    /**
     * Handle report submission
     */
    private function handleReportSubmission() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_property_id'])) {
            $report_property_id = intval($_POST['report_property_id']);
            $reported_user_id   = intval($_POST['reported_user_id'] ?? 0);
            $reason             = trim($_POST['reason'] ?? '');
            $details            = trim($_POST['details'] ?? '');
            
            if (!empty($reason)) {
                $stmt = $this->conn->prepare("INSERT INTO reports (property_id, reported_user_id, reporter_id, reason, details, created_at) VALUES (?,?,?,?,?,NOW())");
                $stmt->bind_param("iiiss", $report_property_id, $reported_user_id, $this->user_id, $reason, $details);
                $stmt->execute();
                $stmt->close();
                
                echo "<script>
                    alert('You have reported this property!');
                    window.location.href = 'full_details.php?id={$report_property_id}';
                </script>";
                exit;
            }
        }
    }
    
    /**
     * Fetch Properties with Conversations for dropdown
     */
    public function getPropertyConversations() {
        $sql = "
          SELECT DISTINCT p.id AS property_id, p.title, p.user_id AS owner_id
          FROM properties p
          JOIN messages m ON p.id = m.property_id
          WHERE p.user_id = ? 
             OR m.sender_id = ? 
             OR m.receiver_id = ?
          ORDER BY p.created_at DESC
        ";
        
        $propertyConvos = [];
        if ($msgQuery = $this->conn->prepare($sql)) {
            $msgQuery->bind_param("iii", $this->user_id, $this->user_id, $this->user_id);
            $msgQuery->execute();
            $propertyConvos = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
            $msgQuery->close();
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
    
    public function getPropertyId() {
        return $this->property_id;
    }
    
    public function getProperty() {
        return $this->property;
    }
    
    public function getImages() {
        return $this->images;
    }
    
    public function getSavedIds() {
        return $this->savedIds;
    }
    
    /**
     * Check if property is saved by current user
     */
    public function isPropertySaved() {
        return in_array($this->property_id, $this->savedIds);
    }
    
    /**
     * Format price with Philippine Peso symbol
     */
    public function formatPrice($price) {
        return '₱' . number_format(floatval($price), 2);
    }
    
    /**
     * Format date for display
     */
    public function formatDate($date) {
        return date("F j, Y", strtotime($date));
    }
}

// Initialize the PropertyDetailsManager
$detailsManager = new PropertyDetailsManager($conn);

// Get all data using the manager
$propertyConvos = $detailsManager->getPropertyConversations();
$notifications = $detailsManager->getNotifications();
$notifCount = $detailsManager->getUnreadNotificationCount();

// Get property data and user info
$user_id = $detailsManager->getUserId();
$full_name = $detailsManager->getFullName();
$property_id = $detailsManager->getPropertyId();
$property = $detailsManager->getProperty();
$images = $detailsManager->getImages();
$savedIds = $detailsManager->getSavedIds();
$isSaved = $detailsManager->isPropertySaved();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LandSeek | <?php echo htmlspecialchars($property['title']); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="../styles/users.css">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
  <style>
.details-container {
  max-width: 1100px;
  margin: 50px auto;
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  z-index: 1;
  position: relative;
}
.details-container h1 {
  color: #32a852;
  margin-bottom: 15px;
}
.details-container p {
  margin: 8px 0;
  color: #444;
}

/* ==============================
   Flex layout for details + video
================================*/
.details-flex {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
}
.details-left {
  flex: 1 1 60%;
}
.details-right {
  flex: 1 1 40%;
}

/* ==============================
   Header: Title + Info (left) / Actions (right)
================================*/
.details-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 15px;
}
.details-info {
  flex: 1;
}
.property-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

/* ==============================
   Buttons
================================*/
.action-btn {
  color: #fff;
  border: none;
  padding: 8px 15px;
  border-radius: 6px;
  cursor: pointer;
  transition: 0.2s;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.action-btn:hover {
  opacity: 0.9;
}

.default-btn { background: #32a852; }  /* green */
.save-btn { background: #32a852; }     /* blue */
.unsave-btn { background: #ff4444; }   /* red */
.report-btn { background: #ff9800; }   /* orange */

/* ==============================
   Images gallery below
================================*/
.details-images {
  margin-top: 30px;
}
.image-gallery {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.image-gallery img {
  width: 200px;
  height: 150px;
  object-fit: cover;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Modal styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
}
.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 20px;
  border-radius: 8px;
  width: 80%;
  max-width: 500px;
}
.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}
.close:hover {
  color: black;
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
      <a href="dashboard.php"><i class="fa-solid fa-dashboard"></i> Dashboard</a>
      <a href="properties.php"><i class="fa-solid fa-list"></i> Properties</a>
      <a href="saved_properties.php"><i class="fa-solid fa-bookmark"></i> Saved to Favorites</a>
      <a href="map.php"><i class="fa-solid fa-map"></i> Map</a>
    </div>
  </header>

  <div class="details-container">
    <div class="details-header">
      <div class="details-info">
        <!-- FIXED: Using actual property data instead of hardcoded values -->
        <h1><?php echo htmlspecialchars($property['title']); ?></h1>
        <p><strong>Visits:</strong> <?php echo htmlspecialchars($property['visits']); ?></p>
        <p><strong>Classification:</strong> <?php echo htmlspecialchars($property['classification']); ?></p>
        <p><strong>Price:</strong> <?php echo $detailsManager->formatPrice($property['price_range']); ?></p>
        <?php if($property['discount_price'] > 0): ?>
          <p><strong>Discounted Price:</strong> <?php echo $detailsManager->formatPrice($property['discount_price']); ?></p>
        <?php endif; ?>
        <p><strong>Area:</strong> <?php echo htmlspecialchars($property['area']); ?> sqm</p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($property['status']); ?></p>
        <p><strong>Address:</strong> 
          <?php 
          $addressParts = [
              $property['address'] ?? '',
              $property['street'] ?? '',
              $property['purok'] ?? '',
              $property['city'] ?? '',
              $property['province'] ?? '',
              $property['country'] ?? 'Philippines',
              $property['postal_code'] ?? ''
          ];
          echo htmlspecialchars(implode(', ', array_filter($addressParts)));
          ?>
        </p>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($property['description']); ?></p>
        <p><strong>Uploaded At:</strong> <?php echo $detailsManager->formatDate($property['created_at']); ?></p>
      </div>

      <!-- Action Buttons -->
      <div class="property-actions">
        <!-- Inquire -->
        <a href="messaging.php?user_id=<?php echo (int)$property['user_id']; ?>&property_id=<?php echo (int)$property['id']; ?>" 
          class="action-btn default-btn">
          <i class="fa-solid fa-comment"></i> Inquire
        </a>

        <!-- Save / Unsave -->
        <?php if($isSaved): ?>
          <a href="properties.php?action=unsave&id=<?php echo (int)$property['id']; ?>" class="action-btn unsave-btn">
            <i class="fa-solid fa-bookmark"></i> Unsave
          </a>
        <?php else: ?>
          <a href="properties.php?action=save&id=<?php echo (int)$property['id']; ?>" class="action-btn save-btn">
            <i class="fa-solid fa-bookmark"></i> Save
          </a>
        <?php endif; ?>

        <!-- Report -->
        <button class="action-btn report-btn" onclick="openReportModal(
              <?php echo (int)$property['id']; ?>, 
              <?php echo (int)$property['user_id']; ?>, 
              '<?php echo htmlspecialchars($property['title']); ?>'
            )">
          <i class="fa-solid fa-circle-exclamation"></i> Report
        </button>
      </div>
    </div>

    <!-- Content split left/right -->
    <div class="details-flex">
      <!-- Left side (images) -->
      <div class="details-left">
        <div class="details-images">
          <h3>Property Images</h3>
          <div class="image-gallery">
            <?php if (!empty($images)): ?>
              <?php foreach ($images as $img): ?>
                <img src="../uploads/<?php echo htmlspecialchars($img); ?>" alt="Property Image">
              <?php endforeach; ?>
            <?php else: ?>
              <p><em>No images uploaded for this property.</em></p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right side (video) -->
      <div class="details-right">
        <h3>Land Tour Video</h3>
        <?php if (!empty($property['land_tour_video'])): ?>
          <video width="100%" height="auto" controls>
            <source src="../uploads/videos/<?php echo htmlspecialchars($property['land_tour_video']); ?>" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        <?php else: ?>
          <p><em>No land tour video uploaded.</em></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Property Inquiries Modal -->
  <div id="propertyModal" class="property-modal">
    <div class="property-modal-content">
      <span class="property-modal-close" onclick="closePropertyModal()">&times;</span>
      <h3 id="propertyTitle"></h3>
      <div id="interestedUsersList"></div>
    </div>
  </div>

  <!-- Report Modal -->
  <div id="reportModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close" onclick="closeReportModal()">&times;</span>
      <h2>Report Property</h2> <br>
      <form method="post" action="">
        <input type="hidden" name="report_property_id" id="report_property_id">
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

        <label for="details">Details (required):</label> <br><br>
        <textarea name="details" id="details" rows="4" required></textarea>

        <button type="submit" class="action-btn default-btn">Submit Report</button>
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
            document.getElementById("propertyTitle").textContent = `Inquiries for: ${title}`;
            list.innerHTML = tempDiv.innerHTML;
            modal.style.display = "flex";
          } else {
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

  function openReportModal(propertyId, userId, title) {
    document.getElementById("report_property_id").value = propertyId;
    document.getElementById("report_user_id").value = userId;
    document.getElementById("report_property_title").innerText = title;
    document.getElementById("reportModal").style.display = "block";
  }

  function closeReportModal() {
    document.getElementById("reportModal").style.display = "none";
  }

  // Close modals when clicking outside
  window.onclick = function(event) {
    const propertyModal = document.getElementById("propertyModal");
    const reportModal = document.getElementById("reportModal");
    
    if (event.target === propertyModal) {
      propertyModal.style.display = "none";
    }
    if (event.target === reportModal) {
      reportModal.style.display = "none";
    }
  }

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