<?php
/**
 * LandSeek: A Digital Marketplace for Land Hunting
 * ------------------------------------------------
 * Dashboard Backend Module (OOP Version)
 * 
 * This class handles all backend functionalities for the user dashboard,
 * including fetching user information, property statistics, unread messages,
 * notifications, reports, and activity logs.
 */

session_start();
session_regenerate_id(true); // Regenerate session ID to prevent session fixation attacks

require_once "../connection/db_con.php";

/**
 * Dashboard Class
 * ----------------
 * Encapsulates all data retrieval and logic for the LandSeek user dashboard.
 */
class Dashboard {
    // ==============================
    // CLASS PROPERTIES
    // ==============================
    private $conn;       // Database connection
    private $user_id;    // Current user ID
    private $full_name;  // Full name of logged-in user

    // ==============================
    // CONSTRUCTOR
    // ==============================
    public function __construct($conn) {
        $this->conn = $conn;

        // Verify user session
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../login.html");
            exit;
        }

        $this->user_id = $_SESSION['user_id'];
        $this->full_name = $_SESSION['full_name'] ?? "User";

        // Refresh full name from database
        $this->fetchFullName();
        
        // Handle actions
        $this->handleActions();
    }

    /**
     * Handle various dashboard actions (delete, update price, set status)
     */
    private function handleActions() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'update_price':
                        $this->updatePropertyPrice();
                        break;
                    case 'update_discount_price': 
                        $this->updateDiscountPrice();
                        break;
                    case 'set_status':
                        $this->updatePropertyStatus();
                        break;
                }
            }
        } elseif (isset($_GET['action']) && isset($_GET['id'])) {
            if ($_GET['action'] === 'delete_property') {
                $this->deleteProperty($_GET['id']);
            }
        }
    }

    /**
     * Fetch user's full name from the database.
     */
    private function fetchFullName() {
        $stmt = $this->conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $this->full_name = $result['full_name'] ?? "User";
        $stmt->close();
    }

    /**
     * Delete a property and its associated images
     */
    public function deleteProperty($property_id) {
        $property_id = intval($property_id);

        // Fetch property to ensure ownership and get images
        $stmt = $this->conn->prepare("SELECT user_id, images FROM properties WHERE id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $stmt->bind_result($owner_id, $images_json);
        $stmt->fetch();
        $stmt->close();

        if (!$owner_id || $owner_id != $this->user_id) {
            // Not found or user does not own it
            header("Location: dashboard.php?error=unauthorized");
            exit;
        }

        // Delete images
        if ($images_json) {
            $images = json_decode($images_json, true);
            if (is_array($images)) {
                foreach ($images as $img) {
                    $file = "../uploads/properties/" . $img;
                    if (file_exists($file)) unlink($file);
                }
            }
        }

        // Delete the property
        $stmt = $this->conn->prepare("DELETE FROM properties WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $property_id, $this->user_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: dashboard.php?success=property_deleted");
            exit;
        } else {
            $stmt->close();
            header("Location: dashboard.php?error=delete_failed");
            exit;
        }
    }

    /**
     * Update property price
     */
    private function updatePropertyPrice() {
        if (!isset($_POST['property_id']) || !isset($_POST['price_range'])) {
            header("Location: dashboard.php?error=missing_data");
            exit;
        }

        $property_id = intval($_POST['property_id']);
        $price_range = floatval($_POST['price_range']);

        // Ensure the property belongs to the user
        $stmt = $this->conn->prepare("UPDATE properties SET price_range=? WHERE id=? AND user_id=?");
        $stmt->bind_param("dii", $price_range, $property_id, $this->user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: dashboard.php?success=price_updated");
            exit;
        } else {
            $stmt->close();
            header("Location: dashboard.php?error=price_update_failed");
            exit;
        }
    }

    private function updateDiscountPrice() {
      if (!isset($_POST['property_id']) || !isset($_POST['discount_price'])) {
          header("Location: dashboard.php?error=missing_data");
          exit;
      }

      $property_id = intval($_POST['property_id']);
      $discount_price = floatval($_POST['discount_price']);

      // Update discount price only if the property belongs to the user
      $stmt = $this->conn->prepare("UPDATE properties SET discount_price=? WHERE id=? AND user_id=?");
      $stmt->bind_param("dii", $discount_price, $property_id, $this->user_id);

      if ($stmt->execute()) {
          $stmt->close();
          header("Location: dashboard.php?success=discount_updated");
          exit;
      } else {
          $stmt->close();
          header("Location: dashboard.php?error=discount_update_failed");
          exit;
      }
    }

    /**
     * Update property status and notify users
     */
    private function updatePropertyStatus() {
    if (!isset($_POST['property_id']) || !isset($_POST['status'])) {
        header("Location: dashboard.php?error=missing_data");
        exit;
    }

    $property_id = (int) $_POST['property_id'];
    $status = $_POST['status'];

    /* ==============================
       1. Update property status
       ============================== */
    $update = $this->conn->prepare(
        "UPDATE properties SET status=? WHERE id=? AND user_id=?"
    );
    $update->bind_param("sii", $status, $property_id, $this->user_id);
    $update->execute();
    $update->close();

    /* ==============================
       2. Get property title
       ============================== */
    $titleQuery = $this->conn->prepare(
        "SELECT title FROM properties WHERE id=?"
    );
    $titleQuery->bind_param("i", $property_id);
    $titleQuery->execute();
    $result = $titleQuery->get_result()->fetch_assoc();
    $property_title = $result['title'] ?? 'Property';
    $titleQuery->close();

    /* ==============================
       3. Find users who saved property
       ============================== */
    $savedQuery = $this->conn->prepare(
        "SELECT user_id FROM saved_properties WHERE property_id=?"
    );
    $savedQuery->bind_param("i", $property_id);
    $savedQuery->execute();
    $savedUsers = $savedQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $savedQuery->close();

    /* ==============================
       4. Notify users
       ============================== */
    if (!empty($savedUsers)) {
        $notif = $this->conn->prepare("
            INSERT INTO notifications
            (user_id, notifiable_type, notifiable_id, type, title, message, is_read, created_at)
            VALUES (?,?,?,?,?,?,0,NOW())
        ");

        foreach ($savedUsers as $su) {
            $uid = (int) $su['user_id'];
            $msg = "The property '{$property_title}' is now marked as {$status}.";

            $notifiableType = 'property';
            $type = 'property_status';

            // 🔑 6 placeholders = 6 variables
            $notif->bind_param(
                "isisss",
                $uid,
                $notifiableType,
                $property_id,
                $type,
                $property_title,
                $msg
            );

            $notif->execute();
        }

        $notif->close();
    }

    /* ==============================
       5. If sold → remove saved links
       ============================== */
    if ($status === 'sold') {
        $del = $this->conn->prepare(
            "DELETE FROM saved_properties WHERE property_id=?"
        );
        $del->bind_param("i", $property_id);
        $del->execute();
        $del->close();
    }

    /* ==============================
       6. Redirect
       ============================== */
    header("Location: dashboard.php?success=status_updated");
    exit;
}


    /**
     * Return basic user information.
     */
    public function getUserInfo() {
        return [
            'user_id' => $this->user_id,
            'full_name' => $this->full_name
        ];
    }

    /**
     * Fetch all property conversations involving the current user.
     * Includes both properties they uploaded and those they messaged about.
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
    }

    /**
     * Fetch recent notifications for the user.
     * @param int $limit - maximum number of notifications to retrieve
     */
    public function getNotifications($limit = 20) {
        $stmt = $this->conn->prepare("
            SELECT id, type, title, message, is_read, created_at 
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $this->user_id, $limit);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Get unread message and notification counts.
     */
    public function getUnreadCounts() {
        $counts = [];

        // Count unread messages
        $msgStmt = $this->conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id = ? AND is_read = 0");
        $msgStmt->bind_param("i", $this->user_id);
        $msgStmt->execute();
        $counts['messages'] = intval($msgStmt->get_result()->fetch_assoc()['count']);
        $msgStmt->close();

        // Count unread notifications
        $notifStmt = $this->conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0");
        $notifStmt->bind_param("i", $this->user_id);
        $notifStmt->execute();
        $counts['notifications'] = intval($notifStmt->get_result()->fetch_assoc()['count']);
        $notifStmt->close();

        return $counts;
    }

    /**
     * Retrieve dashboard statistics:
     * - Total properties in system
     * - User's uploaded properties
     * - Saved properties
     * - Total messages
     * - Notifications
     */
    public function getStats() {
        $uid = $this->user_id;

        // Use prepared statements for security
        $total_stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM properties");
        $total_stmt->execute();
        $total_properties = $total_stmt->get_result()->fetch_assoc()['c'];
        $total_stmt->close();

        $user_stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM properties WHERE user_id = ?");
        $user_stmt->bind_param("i", $uid);
        $user_stmt->execute();
        $user_properties = $user_stmt->get_result()->fetch_assoc()['c'];
        $user_stmt->close();

        $saved_stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM saved_properties WHERE user_id = ?");
        $saved_stmt->bind_param("i", $uid);
        $saved_stmt->execute();
        $user_saved = $saved_stmt->get_result()->fetch_assoc()['c'];
        $saved_stmt->close();

        $msg_stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM messages WHERE sender_id = ? OR receiver_id = ?");
        $msg_stmt->bind_param("ii", $uid, $uid);
        $msg_stmt->execute();
        $user_messages = $msg_stmt->get_result()->fetch_assoc()['c'];
        $msg_stmt->close();

        $notif_stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?");
        $notif_stmt->bind_param("i", $uid);
        $notif_stmt->execute();
        $user_notifications = $notif_stmt->get_result()->fetch_assoc()['c'];
        $notif_stmt->close();

        return compact('total_properties', 'user_properties', 'user_saved', 'user_messages', 'user_notifications');
    }

    /**
     * Fetch recent user activities:
     * - Latest notifications
     * - Recently uploaded properties
     */
    public function getRecentActivities() {
        $activities = [];
        $uid = $this->user_id;

        // Get recent notifications
        $notifStmt = $this->conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
        $notifStmt->bind_param("i", $uid);
        $notifStmt->execute();
        $notifRes = $notifStmt->get_result();
        while ($r = $notifRes->fetch_assoc()) {
            $activities[] = "🔔 Notification: " . htmlspecialchars($r['message']);
        }
        $notifStmt->close();

        // Get recent property uploads
        $propStmt = $this->conn->prepare("SELECT title, created_at FROM properties WHERE user_id = ? ORDER BY created_at DESC LIMIT 2");
        $propStmt->bind_param("i", $uid);
        $propStmt->execute();
        $propRes = $propStmt->get_result();
        while ($r = $propRes->fetch_assoc()) {
            $activities[] = "📌 You uploaded: <b>" . htmlspecialchars($r['title']) . "</b>";
        }
        $propStmt->close();

        return $activities;
    }

    /**
     * Fetch list of properties uploaded by the user.
     * @param int $limit - number of properties to fetch
     */
    public function getUserProperties($limit = 15) {
        $stmt = $this->conn->prepare("
            SELECT id, title, city, province, price_range, discount_price, status, created_at
            FROM properties
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $this->user_id, $limit);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Retrieve reports submitted by the current user.
     * Includes related property titles if available.
     */
    public function getUserReports($limit = 15) {
        $stmt = $this->conn->prepare("
            SELECT r.id, r.reason, r.details, r.created_at, p.title AS property_title
            FROM reports r
            LEFT JOIN properties p ON r.property_id = p.id
            WHERE r.reporter_id = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $this->user_id, $limit);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Fetch analytics data for user's properties.
     * Includes total visits, saves, and inquiries.
     */
    public function getPropertyInsights() {
        $sql = "
            SELECT 
                p.id AS property_id,
                p.title,
                p.price_range,
                p.discount_price,
                p.visits,
                COALESCE(s.save_count, 0) AS saves,
                COALESCE(i.inquiry_count, 0) AS inquiries
            FROM properties p
            LEFT JOIN (
                SELECT property_id, COUNT(*) AS save_count
                FROM saved_properties
                GROUP BY property_id
            ) s ON p.id = s.property_id
            LEFT JOIN (
                SELECT property_id, COUNT(DISTINCT sender_id) AS inquiry_count
                FROM messages
                GROUP BY property_id
            ) i ON p.id = i.property_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Get count of unread messages per property for the current user.
     */
    public function getUnreadPerProperty() {
        $unreadCounts = [];
        $stmt = $this->conn->prepare("
            SELECT property_id, COUNT(*) AS unread_count
            FROM messages
            WHERE receiver_id = ? AND is_read = 0
            GROUP BY property_id
        ");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $unreadCounts[$row['property_id']] = $row['unread_count'];
        }
        $stmt->close();
        return $unreadCounts;
    }
}

/* ==============================
   DASHBOARD USAGE EXAMPLE
   ============================== */

// Initialize Dashboard class
$dashboard = new Dashboard($conn);

// Retrieve all needed data for dashboard display
$userInfo         = $dashboard->getUserInfo();
$user_id          = $userInfo['user_id'];
$notifications    = $dashboard->getNotifications();
$unreadCounts     = $dashboard->getUnreadCounts();
$stats            = $dashboard->getStats();
$activities       = $dashboard->getRecentActivities();
$userProps        = $dashboard->getUserProperties();
$userReports      = $dashboard->getUserReports();
$propertyInsights = $dashboard->getPropertyInsights();
$propertyConvos   = $dashboard->getPropertyConversations();
$unreadPerProp    = $dashboard->getUnreadPerProperty();

// Extract stats safely for frontend usage
$user_props   = $stats['user_properties'] ?? 0;
$user_msgs    = $stats['user_messages'] ?? 0;
$user_saved   = $stats['user_saved'] ?? 0;
$user_notifs  = $stats['user_notifications'] ?? 0;
$notifCount   = $unreadCounts['notifications'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LandSeek | Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="../styles/users.css">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

  <style>
    input[type="number"] {
      padding: 5px;
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
        <li><a href="dashboard.php" class="ordinary"><i class="fas fa-dashboard"></i> Dashboard</a></li>
        <li><a href="properties.php"><i class="fas fa-list"></i> Properties</a></li>
        <li><a href="saved_properties.php"><i class="fas fa-bookmark"></i> Favorites</a></li>
        <li><a href="map.php"><i class="fas fa-map"></i> Map</a></li>
      </ul>
    </nav>

    <div class="header-nav">
      <!-- Messages Dropdown -->
      <li class="dropdown">
        <button class="dropdown-btn" style="color:#28A228;" onclick="toggleDropdown('messages-dropdown')">
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
        <button class="dropdown-btn" style="color:#28A228;" onclick="toggleDropdown('notifications-dropdown')">
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
        <button class="dropdown-btn" style="color:#28A228;" onclick="toggleDropdown('profile-dropdown')">
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

  
  <main class="dashboard-main">
  <!-- Upload Property Button -->
<section class="upload-btn-section">
  <a href="post-a-property.php" class="btn-upload">
    <i class="fa-solid fa-plus"></i> 
  </a>
</section>

<!-- Your Properties -->
<section class="dashboard-section">
  <h2><i class="fa-solid fa-landmark"></i> Uploaded Properties</h2>
  <div class="scroll-container">
    <?php if(!empty($userProps)): ?>
      <?php foreach($userProps as $prop): ?>
        <div class="scroll-card">
          <h4><?php echo htmlspecialchars($prop['title']); ?></h4>

          <!-- Location -->
          <p><i class="fa-solid fa-location-dot"></i>
            <b>Location: </b><?php echo htmlspecialchars($prop['city'] . ', ' . $prop['province']); ?>
          </p>

          <!-- Price Range -->
          <p><i class="fa-solid fa-money-bill"></i>
            <b>Price Range:</b> ₱ <?php echo number_format($prop['price_range']); ?>
          </p>

          <!-- Discounted Price -->
          <p><i class="fa-solid fa-tags"></i>
            <b>Discount Price:</b> ₱ <?php echo number_format($prop['discount_price']); ?>
          </p>

          <!-- Uploaded Date -->
          <small>Upload: <?php echo date("M d, Y", strtotime($prop['created_at'])); ?></small>

          <!-- Status Selection -->
          <form action="dashboard.php" method="post" style="margin:8px 0;">
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="property_id" value="<?php echo (int)$prop['id']; ?>">
            <select name="status" onchange="this.form.submit()" class="status-select">
              <option value="available" <?php if($prop['status']=='available') echo 'selected';?>>Available</option>
              <option value="pending" <?php if($prop['status']=='pending') echo 'selected';?>>Pending</option>
              <option value="sold" <?php if($prop['status']=='sold') echo 'selected';?>>Sold</option>
            </select>
          </form>

          <!-- Actions -->
          <div class="card-actions">
            <a href="update_property.php?id=<?php echo $prop['id']; ?>" class="btn-small"><i class="fa-solid fa-edit"></i> Update</a>
            <a href="dashboard.php?action=delete_property&id=<?php echo $prop['id']; ?>" 
               class="btn-small btn-danger"
               onclick="return confirm('Are you sure you want to delete this property? This action cannot be undone.');"><i class="fa-solid fa-trash"></i> Delete</a>
            <a href="full_details.php?id=<?php echo $prop['id']; ?>" class="btn-small btn-view"> <i class="fa-solid fa-eye"></i> View</a> <br>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p><em>You haven't uploaded any properties yet.</em></p>
    <?php endif; ?>
  </div>
</section>

<section class="dashboard-section">
  <h2><i class="fa-solid fa-chart-line"></i> Property Insights</h2>
  <div class="scroll-container">
    <?php if(!empty($propertyInsights)): ?>
      <?php foreach($propertyInsights as $p): 
        // Calculate popularity score
        $score = min(100, round(($p['visits']*0.4 + $p['saves']*0.3 + $p['inquiries']*0.3)));

        // Determine indicator and message
        if ($score >= 50) {
          $indicator = "<span class='indicator high'><i class='fa-solid fa-arrow-trend-up'></i> Strong Demand</span>";
        } elseif ($score >= 40) {
          $indicator = "<span class='indicator medium'><i class='fa-solid fa-arrow-up'></i> Moderate Demand</span>";
        } else {
          $indicator = "<span class='indicator low'><i class='fa-solid fa-arrow-down'></i> Low Demand</span>";
        }
      ?>
        <div class="scroll-card">
          <h4><?php echo htmlspecialchars($p['title']); ?></h4>

          <p><i class="fas fa-eye"></i> Visits: <?php echo $p['visits']; ?></p>
          <p><i class="fas fa-bookmark"></i> Saves: <?php echo $p['saves']; ?></p>
          <p><i class="fas fa-users"></i> Inquiries: <?php echo $p['inquiries']; ?></p>

          <p><b>Popularity:</b> <?php echo $score; ?>% <?php echo $indicator; ?></p>

          <br>
          <!-- Update price range -->
          <form action="dashboard.php" method="post">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">
            <label><b>Price Range: ₱</b></label> <br>
            <input type="number" name="price_range" style="width:110px;" value="<?php echo $p['price_range']; ?>" step="1000" style="width:130px;" required>
            <button type="submit" class="btn-small"><i class="fa-solid fa-edit"></i> Update</button>
          </form> 

          <!-- New: Update Discount Price -->
        <form action="dashboard.php" method="post" style="margin-top:8px;">
          <input type="hidden" name="action" value="update_discount_price">
          <input type="hidden" name="property_id" value="<?php echo (int)$p['property_id']; ?>">

          <label><b>Discount Price: ₱</b></label><br>
          <input type="number" name="discount_price" style="width:110px;" step="0.01" min="0"
                value="<?php echo htmlspecialchars($p['discount_price'] ?? 0); ?>">
          <button type="submit" class="btn-small"><i class="fa-solid fa-pen"></i> Update</button>
        </form> <br>

          <small>
            Note: If the popularity indicator shows 
            <span style="color:#28a745;font-weight:bold;"><br>Strong Demand</span>,
            you may consider raising <br>
            your price.
          </small>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p><em>No insights available yet.</em></p>
    <?php endif; ?>
  </div>
</section>

<!-- Update Property Modal -->
<div id="updatePropertyModal" class="property-modal">
  <div class="property-modal-content">
    <span class="property-modal-close" onclick="closeUpdateModal()">&times;</span>
    <h3>Update Property</h3>
    <form id="updatePropertyForm" method="POST" action="update_property.php" enctype="multipart/form-data">
      <input type="hidden" name="property_id" id="upd_property_id">

      <label>Title:</label>
      <input type="text" name="title" id="upd_title" required>

      <label>Description:</label>
      <textarea name="description" id="upd_description" rows="4" required></textarea>

      <label>Price Range:</label>
      <input type="number" name="price_range" id="upd_price_range" step="1000" required>

      <label>Discount Price:</label>
      <input type="number" name="discount_price" id="upd_discount_price" step="1000">

      <label>Status:</label>
      <select name="status" id="upd_status">
        <option value="available">Available</option>
        <option value="pending">Pending</option>
        <option value="sold">Sold</option>
      </select>

      <label>Images (you can add more):</label>
      <input type="file" name="images[]" id="upd_images" multiple accept="image/*">

      <label>Land Tour Video:</label>
      <input type="file" name="land_tour_video" id="upd_video" accept="video/*">

      <button type="submit" class="btn-small">Update Property</button>
    </form>
  </div>
</div>

<!-- Your Reports -->
<section class="dashboard-section">
  <h2><i class="fa-solid fa-flag"></i> Reports You Sent</h2>
  <div class="scroll-container">
    <?php if(!empty($userReports)): ?>
      <?php foreach($userReports as $r): ?>
        <div class="scroll-card">
          <h4><?php echo ucfirst($r['reason']); ?></h4>
          <p>Property: <?php echo htmlspecialchars($r['property_title'] ?? 'N/A'); ?></p>
          <?php if(!empty($r['details'])): ?>
            <p><small><?php echo htmlspecialchars($r['details']); ?></small></p>
          <?php endif; ?>
          <small><?php echo date("M d, Y", strtotime($r['created_at'])); ?></small>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p><em>You haven't submitted any reports yet or reports have been resolved and issued.</em></p>
    <?php endif; ?>
  </div>
</section>

  <!-- Stats Overview -->
  <section class="stats-overview">
    <div class="stat-card">
      <i class="fas fa-landmark" style="color:#28A228"></i>
      <div>
        <h3><?php echo $user_props; ?></h3>
        <p>Your Properties</p>
      </div>
    </div>
    <div class="stat-card">
      <i class="fas fa-users" style="color:#28A228"></i>
      <div>
        <h3><?php echo $user_msgs; ?></h3>
        <p>Inquiries</p>
      </div>
    </div>
    <div class="stat-card">
      <i class="fas fa-bookmark" style="color:#28A228"></i>
      <div>
        <h3><?php echo $user_saved; ?></h3>
        <p>Favorites</p>
      </div>
    </div>
    <div class="stat-card">
      <i class="fas fa-bell" style="color:#28A228"></i>
      <div>
        <h3><?php echo $user_notifs; ?></h3>
        <p>Notifications</p>
      </div>
    </div>
  </section>

  <!-- Recent Activities -->
  <section class="recent-activities">
    <h2><i class="fas fa-history"></i> Recent Activities</h2>
    <ul class="activities-list">
      <?php if(!empty($activities)): ?>
        <?php foreach($activities as $a): ?>
          <li><?php echo $a; ?></li>
        <?php endforeach; ?>
      <?php else: ?>
        <li><em>No recent activity</em></li>
      <?php endif; ?>
    </ul>
  </section>
</main>

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

</body>
</html>
