<?php
session_start();
session_regenerate_id(true);
require_once "../connection/db_con.php";

class MessagingManager {
    private $conn;
    private $user_id;
    private $full_name;
    private $activeReceiver;
    private $property_id;
    private $property;
    private $conversations = [];
    private $messages = [];
    private $unreadCounts = [];
    private $conversationPropertyId;
    
    // public function construct for connecton
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeSession();
        $this->loadUserProfile();
        $this->setRequestParameters();
        $this->handleConversationStart();
        $this->loadConversations();
        $this->markMessagesAsRead();
        $this->loadActiveConversation();
        $this->loadUnreadCounts();
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
    
    private function setRequestParameters() {
        $this->activeReceiver = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
        $this->property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : null;
        
        if ($this->property_id) {
            $this->loadPropertyDetails();
        }
    }
    
    private function loadPropertyDetails() {
        $propStmt = $this->conn->prepare("SELECT * FROM properties WHERE id=?");
        $propStmt->bind_param("i", $this->property_id);
        $propStmt->execute();
        $this->property = $propStmt->get_result()->fetch_assoc();
        $propStmt->close();
    }
    
    private function handleConversationStart() {
        if ($this->activeReceiver && $this->property_id) {
            $checkMsg = $this->conn->prepare("
                SELECT id FROM messages 
                WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) 
                  AND property_id=? 
                LIMIT 1
            ");
            $checkMsg->bind_param("iiiii", $this->user_id, $this->activeReceiver, $this->activeReceiver, $this->user_id, $this->property_id);
            $checkMsg->execute();
            $exists = $checkMsg->get_result()->fetch_assoc();
            $checkMsg->close();

            if (!$exists) {
                $this->createStarterMessage();
            }
        }
    }
    
    private function createStarterMessage() {
        $starter = "Hello, I'm interested in this property.";
        $insertMsg = $this->conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, property_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insertMsg->bind_param("iiis", $this->user_id, $this->activeReceiver, $this->property_id, $starter);
        $insertMsg->execute();
        $insertMsg->close();
    }
    
    private function loadConversations() {
    $sql = "
        SELECT 
            p.id AS property_id,
            p.title AS property_title,
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id
                ELSE m.sender_id
            END AS other_user_id,
            MAX(u.full_name) AS full_name,
            MAX(m.created_at) AS last_message_time
        FROM messages m
        JOIN user_profiles u
          ON u.user_id = CASE 
                WHEN m.sender_id = ? THEN m.receiver_id
                ELSE m.sender_id
            END
        LEFT JOIN properties p ON p.id = m.property_id
        WHERE m.sender_id = ?
           OR m.receiver_id = ?
        GROUP BY p.id, other_user_id
        ORDER BY property_title ASC, last_message_time DESC
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param(
        "iiii",
        $this->user_id,
        $this->user_id,
        $this->user_id,
        $this->user_id
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $propId = (int) ($row['property_id'] ?? 0);

        if (!isset($this->conversations[$propId])) {
            $this->conversations[$propId] = [
                'title' => $row['property_title'] ?? 'Untitled Property',
                'users' => []
            ];
        }

        $this->conversations[$propId]['users'][] = [
            'user_id' => (int) $row['other_user_id'],
            'full_name' => $row['full_name'],
            'last_message_time' => $row['last_message_time']
        ];
    }
}
    
    private function markMessagesAsRead() {
        if ($this->activeReceiver && $this->property_id) {
            $markRead = $this->conn->prepare("
                UPDATE messages
                SET is_read=1
                WHERE sender_id=? AND receiver_id=? AND property_id=? AND is_read=0
            ");
            $markRead->bind_param("iii", $this->activeReceiver, $this->user_id, $this->property_id);
            $markRead->execute();
            $markRead->close();
        }
    }
    
    private function loadActiveConversation() {
        if (!$this->activeReceiver) {
            return;
        }

        $this->conversationPropertyId = $this->property_id;
        
        if (!$this->conversationPropertyId) {
            $this->conversationPropertyId = $this->findConversationPropertyId();
        }

        if ($this->conversationPropertyId && !$this->property) {
            $this->property_id = $this->conversationPropertyId;
            $this->loadPropertyDetails();
        }

        $this->fetchConversationMessages();
    }
    
    private function findConversationPropertyId() {
        $propStmt = $this->conn->prepare("
            SELECT property_id 
            FROM messages 
            WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) 
            ORDER BY created_at ASC LIMIT 1
        ");
        $propStmt->bind_param("iiii", $this->user_id, $this->activeReceiver, $this->activeReceiver, $this->user_id);
        $propStmt->execute();
        $res = $propStmt->get_result()->fetch_assoc();
        $propStmt->close();
        
        return $res['property_id'] ?? null;
    }
    
    private function fetchConversationMessages() {
        $msgFetch = $this->conn->prepare("
            SELECT m.*, u.full_name 
            FROM messages m
            JOIN user_profiles u ON u.user_id = m.sender_id
            WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
              AND (m.property_id=?)
            ORDER BY m.created_at ASC
        ");
        $msgFetch->bind_param("iiiii", $this->user_id, $this->activeReceiver, $this->activeReceiver, $this->user_id, $this->conversationPropertyId);
        $msgFetch->execute();
        $this->messages = $msgFetch->get_result()->fetch_all(MYSQLI_ASSOC);
        $msgFetch->close();
    }
    
    private function loadUnreadCounts() {
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
            $this->unreadCounts[$row['property_id']] = $row['unread_count'];
        }
        $unreadQuery->close();
    }
    
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
    
    public function getPropertyConversations() {
        $propertyConvos = [];
        foreach ($this->conversations as $propId => $prop) {
            $propertyConvos[] = [
                'property_id' => $propId,
                'title' => $prop['title']
            ];
        }
        return $propertyConvos;
    }
    
    public function sendMessage($receiver_id, $property_id, $message) {
        $stmt = $this->conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, property_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiis", $this->user_id, $receiver_id, $property_id, $message);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    // Getters
    public function getUserId() { return $this->user_id; }
    public function getFullName() { return $this->full_name; }
    public function getActiveReceiver() { return $this->activeReceiver; }
    public function getPropertyId() { return $this->property_id; }
    public function getProperty() { return $this->property; }
    public function getConversations() { return $this->conversations; }
    public function getMessages() { return $this->messages; }
    public function getUnreadCounts() { return $this->unreadCounts; }
    public function getConversationPropertyId() { return $this->conversationPropertyId; }
    
    public function getUnreadCountForProperty($propertyId) {
        return $this->unreadCounts[$propertyId] ?? 0;
    }
    
    /**
     * Get receiver name for active conversation
     */
    public function getActiveReceiverName() {
        if (!$this->activeReceiver) return null;
        
        foreach ($this->conversations as $prop) {
            foreach ($prop['users'] as $user) {
                if ($user['user_id'] == $this->activeReceiver) {
                    return $user['full_name'];
                }
            }
        }
        return null;
    }
}

// Initialize MessagingManager
$messagingManager = new MessagingManager($conn);

// Get all data for the template
$notifications = $messagingManager->getNotifications();
$msgCount = $messagingManager->getUnreadMessageCount();
$notifCount = $messagingManager->getUnreadNotificationCount();
$propertyConvos = $messagingManager->getPropertyConversations();

// Get conversation data
$user_id = $messagingManager->getUserId();
$full_name = $messagingManager->getFullName();
$activeReceiver = $messagingManager->getActiveReceiver();
$property_id = $messagingManager->getPropertyId();
$property = $messagingManager->getProperty();
$conversations = $messagingManager->getConversations();
$messages = $messagingManager->getMessages();
$unreadCounts = $messagingManager->getUnreadCounts();
$conversationPropertyId = $messagingManager->getConversationPropertyId();
$activeReceiverName = $messagingManager->getActiveReceiverName();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LandSeek | Messaging</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles/users.css">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

<style>
  .message-time {
    font-size: 10px;
    margin-top: 5px;
    opacity: 0.7;
    text-align: right;
}
.container {
  max-width: 900px;
  height: 80vh; /* fixed height for scrollable layout */
  margin: 40px auto;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  overflow: hidden;
  display: flex;
}

.sidebar {
  width: 250px;
  border-right: 1px solid #ddd;
  background: #f7f7f7;
  padding: 10px;
  overflow-y: auto;   /* enable scroll */
  max-height: 80vh;   /* match container height */
}

.sidebar h3 {
  margin: 0 0 10px;
  color: #32a852;
  font-size: 18px;
}

.property-group {
  margin-bottom: 8px;
}

.property-title {
  font-weight: bold;
  cursor: pointer;
  padding: 6px 8px;
  background: #eee;
  border-radius: 4px;
}

.property-title:hover {
  background: #32a852;
  color: #fff;
}

.property-users {
  display: none;
  margin-left: 10px;
}

.property-users a {
  display: block;
  padding: 6px 10px;
  border-radius: 6px;
  text-decoration: none;
  color: #333;
  margin: 2px 0;
  transition: 0.2s;
}

.property-users a.active,
.property-users a:hover {
  background: #32a852;
  color: #fff;
}

.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chat-header {
  background: #32a852;
  color: #fff;
  padding: 12px 16px;
  font-weight: bold;
}

.property-info {
  padding: 12px;
  border-bottom: 1px solid #ddd;
  background: #f9f9f9;
}

/* Chat messages scrollable area */
.chat-messages {
  flex: 1;
  padding: 16px;
  overflow-y: auto; /* enable scroll */
  background: #f2f2f2;
  max-height: calc(80vh - 140px); /* subtract header + property-info + input height */
}

/* Row wrapper to force full-left or full-right alignment */
.message-row {
  display: flex;
  width: 100%;
  margin: 6px 0;
}
.message-row.me {
  justify-content: flex-end;
}
.message-row.other {
  justify-content: flex-start;
}

/* Bubble sizing and look */
.chat-bubble {
  display: inline-block;          /* shrink to fit text */
  max-width: 70%;                 /* wrap long messages */
  padding: 10px 14px;
  border-radius: 18px;
  font-size: 14px;
  line-height: 1.4;
  word-wrap: break-word;
  word-break: break-word;
}

/* Right-side (logged-in user) */
.chat-bubble.me {
  background: #32a852;
  color: #fff;
  text-align: right;
  border-bottom-right-radius: 6px;
  border-bottom-left-radius: 18px;
}

/* Left-side (other user) */
.chat-bubble.other {
  background: #e1e1e1;
  color: #333;
  text-align: left;
  border-bottom-left-radius: 6px;
  border-bottom-right-radius: 18px;
}

.chat-input {
  display: flex;
  border-top: 1px solid #ddd;
  padding: 8px;
}

.chat-input input {
  flex: 1;
  padding: 8px;
  border-radius: 6px;
  border: 1px solid #ccc;
  font-size: 14px;
}

.chat-input button {
  background: #32a852;
  color: #fff;
  border: none;
  padding: 8px 12px;
  margin-left: 6px;
  border-radius: 6px;
  cursor: pointer;
}

.messages-dropdown-btn {
  display: none; /* hidden on desktop */
}

/* Credentials Modal Specific Styles */
.credentials-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.credentials-header {
  display: flex;
  align-items: center;
  gap: 15px;
  padding-bottom: 15px;
  border-bottom: 1px solid #eee;
}

.profile-picture-large {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #32a852;
}

.profile-info h4 {
  margin: 0 0 5px 0;
  color: #333;
  font-size: 18px;
}

.profile-info .user-email {
  color: #666;
  font-size: 14px;
}

.credentials-details {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #f5f5f5;
}

.detail-label {
  font-weight: bold;
  color: #555;
}

.detail-value {
  color: #333;
}

.properties-list {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 10px;
}

.property-item {
  padding: 8px;
  border-bottom: 1px solid #eee;
  font-size: 14px;
}

.property-item:last-child {
  border-bottom: none;
}

.property-title {
  font-weight: bold;
  color: #32a852;
}

.property-price {
  color: #666;
  font-size: 12px;
}

.no-properties {
  text-align: center;
  color: #999;
  font-style: italic;
  padding: 20px;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}

.error {
  text-align: center;
  padding: 40px;
  color: #dc3545;
}

/* Credentials button in chat header */
.credentials-btn {
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  margin-left: 10px;
}

.report-user-btn {
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

/* Update modal content width for credentials */
#credentialsModal .modal-content {
  max-width: 600px;
}

@media (max-width: 680px) {
  .container .sidebar{
    display: none;
  }

  .dropdown-btn {
    display: inline-block !important;
  }

  .messages-dropdown-btn {
    display: inline-block !important;
  }
  .container {
    margin: 20px;
  }
  .chat-header, .chatheader i {
    font-size: 12px;
  }
}

@media (max-width: 844px) {
  .dropdown-btn {
    display: inline-block !important;
  }
}

@media (max-width: 720px) {
  .dropdown-btn {
    display: inline-block !important;
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

<div class="container">
  <div class="sidebar">
    <h3>Conversations</h3>
    <?php if($conversations): foreach($conversations as $pid=>$prop): ?>
      <div class="property-group">
        <div class="property-title" onclick="toggleProperty(<?= $pid ?>)">
          🏡 <?= htmlspecialchars($prop['title']); ?>
        </div>
        <div id="prop-<?= $pid ?>" class="property-users">
          <?php foreach($prop['users'] as $u): ?>
            <a href="?user_id=<?= $u['user_id'] ?>&property_id=<?= $pid ?>" 
               class="<?php echo ($activeReceiver==$u['user_id'] && $conversationPropertyId==$pid)?'active':''; ?>">
              <?= htmlspecialchars($u['full_name']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; else: ?>
      <small>No conversations yet</small>
    <?php endif; ?>
  </div>

  <div class="chat-area">
    <div class="chat-header">
      <?php 
        if($activeReceiver){
            echo "Chat with ";
            foreach($conversations as $pid=>$prop){
                foreach($prop['users'] as $u){
                    if($u['user_id']==$activeReceiver && $conversationPropertyId==$pid){
                        echo htmlspecialchars($u['full_name']);
                        // Add both buttons
                        echo '<button class="credentials-btn" style="background-color:#1fd56e; margin-right:15px;" onclick="openCredentialsModal(' . $u['user_id'] . ')">
                                <i class="fas fa-id-card"></i> 
                              </button>';
                        echo '<button class="report-user-btn" style="background-color:#1fd56e;" onclick="openReportModal(' . $u['user_id'] . ', ' . $conversationPropertyId . ')">
                                <i class="fas fa-flag"></i> 
                              </button>';
                        break 2;
                    }
                }
            }
        } else { echo "Select a conversation"; }
      ?>
    </div>

    <?php if($property): ?>
    <div class="property-info">
        <h4><?= htmlspecialchars($property['title']) ?></h4>
        <p><?= htmlspecialchars($property['description']) ?></p>
        <p><b>Price:</b> ₱<?= number_format($property['price_range']) ?></p>
    </div>
    <?php endif; ?>

    <div class="chat-messages" id="chatMessages">
  <?php foreach($messages as $msg): 
    $isMe = ($msg['sender_id']==$user_id);
    $timestamp = date("M j, g:i A", strtotime($msg['created_at']));
  ?>
    <div class="message-row <?php echo $isMe ? 'me' : 'other'; ?>">
      <div class="chat-bubble <?php echo $isMe ? 'me' : 'other'; ?>">
        <?php if(!$isMe) echo "<b>".htmlspecialchars($msg['full_name']).":</b> "; ?>
        <?php echo htmlspecialchars($msg['message']); ?>
        <div class="message-time" style="font-size: 10px; margin-top: 5px; opacity: 0.7;">
          <?php echo $timestamp; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

    <?php if($activeReceiver): ?>
    <div class="chat-input">
      <input type="text" id="chatInput" placeholder="Type a message...">
      <button onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i> Send</button>
    </div>
    <?php endif; ?>
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

  <!-- Add this modal after the Property Inquiries Modal -->

<!-- Report User Modal -->
<div id="reportUserModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeReportModal()">&times;</span>
    <h3>Report User</h3>
    <form id="reportUserForm">
      <input type="hidden" id="reportedUserId" name="reported_user_id">
      <input type="hidden" id="reportPropertyId" name="property_id">
      
      <div class="form-group">
        <label for="reportReason"><b>Reason for Reporting:</b></label> <br>
        <select id="reportReason" name="reason" required>
          <option value="">Select a reason</option>
          <option value="spam">Spam or Harassment</option>
          <option value="inappropriate">Inappropriate Content</option>
          <option value="fraud">Fraud or Scam</option>
          <option value="fake_profile">Fake Profile</option>
          <option value="other">Other</option>
        </select>
      </div> <br>
      
      <div class="form-group">
        <label for="reportDetails"><b>Additional Details:</b></label> <br> <br>
        <textarea id="reportDetails" name="details" rows="4" placeholder="Please provide more details about your report..." required></textarea>
      </div> <br>
      
      <div class="form-actions">
        <button type="button" onclick="closeReportModal()">Cancel</button>
        <button type="submit">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<!-- Credentials Modal -->
<div id="credentialsModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeCredentialsModal()">&times;</span>
    <h3>User Credentials</h3> <br>
    <div id="credentialsContent">
      <div class="loading">Loading user information...</div>
    </div>
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

<script>
const CURRENT_USER_ID = <?php echo $user_id; ?>;
let activeReceiver = <?php echo $activeReceiver ?? 'null'; ?>;
let propertyId = <?php echo $conversationPropertyId ?? 'null'; ?>;

function toggleProperty(pid){
  const el = document.getElementById("prop-"+pid);  
  if(el.style.display==="none" || el.style.display===""){
    el.style.display="block";
  } else {
    el.style.display="none";
  }
}

function loadMessages(){
    if(!activeReceiver || !propertyId) return;
    fetch(`chat_handler.php?receiver_id=${activeReceiver}&property_id=${propertyId}`)
    .then(res=>res.json())
    .then(data=>{
        const chatBox = document.getElementById("chatMessages");
        chatBox.innerHTML = "";
        data.forEach(msg=>{
            const isMe = (msg.sender_id==CURRENT_USER_ID);
            const row = document.createElement("div");
            row.className = 'message-row ' + (isMe ? 'me' : 'other');

            const bubble = document.createElement("div");
            bubble.className = 'chat-bubble ' + (isMe ? 'me' : 'other');
            const timestamp = new Date(msg.created_at).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
});

bubble.innerHTML = (isMe ? '' : `<b>${msg.full_name}:</b> `) + msg.message + 
    `<div class="message-time" style="font-size: 10px; margin-top: 5px; opacity: 0.7;">${timestamp}</div>`;

            row.appendChild(bubble);
            chatBox.appendChild(row);
        });
        chatBox.scrollTop = chatBox.scrollHeight;
    });
}

function sendMessage(){
    const input = document.getElementById("chatInput");
    const msg = input.value.trim();
    if(!msg || !activeReceiver || !propertyId) return;
    input.value = "";
    const fd = new FormData();
    fd.append("receiver_id", activeReceiver);
    fd.append("message", msg);
    fd.append("property_id", propertyId ?? 0);
    fetch("chat_handler.php",{method:"POST", body:fd}).then(()=>loadMessages());
}

if(activeReceiver) setInterval(loadMessages, 3000);
window.addEventListener('DOMContentLoaded', loadMessages);
</script>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
  const socket = io('http://localhost:3001');
  const currentUserId = <?php echo (int)$user_id; ?>;
  socket.emit('register', currentUserId);

  socket.on('receive_message', (data) => { console.log('New message:', data); });
  socket.on('receive_notification', (notif) => { console.log('New notification:', notif); });

  function toggleDropdown(id) {
    document.querySelectorAll(".dropdown-content").forEach(el => {
      if (el.id !== id) el.classList.remove("show");
    });
    const menu = document.getElementById(id);
    menu.classList.toggle("show");
    if (id === "notifications-dropdown" && menu.classList.contains("show")) {
      fetch("mark_notifications_read.php").then(res=>res.text()).then(res=>{
        if (res.trim()==="OK") {
          const badge = document.getElementById("notif-badge");
          if (badge) badge.remove();
        }
      });
    }
  }
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".dropdown")) {
      document.querySelectorAll(".dropdown-content").forEach(el => el.classList.remove("show"));
    }
  });

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
    // Report User Functions
function openReportModal(userId, propertyId) {
    document.getElementById('reportedUserId').value = userId;
    document.getElementById('reportPropertyId').value = propertyId;
    document.getElementById('reportUserModal').style.display = 'block';
}

function closeReportModal() {
    document.getElementById('reportUserModal').style.display = 'none';
    document.getElementById('reportUserForm').reset();
}

// Handle form submission
document.getElementById('reportUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../users/report_user_hanlder.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('User reported successfully. Our team will review your report.');
            closeReportModal();
        } else {
            alert('Error reporting user: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while reporting the user.');
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('reportUserModal');
    if (e.target === modal) {
        closeReportModal();
    }
});
  </script>

  <script>
function openCredentialsModal(userId) {
    console.log('Opening credentials modal for user:', userId);
    const modal = document.getElementById('credentialsModal');
    modal.style.display = 'block';
    loadUserCredentials(userId);
}

function closeCredentialsModal() {
    document.getElementById('credentialsModal').style.display = 'none';
}

function loadUserCredentials(userId) {
    const content = document.getElementById('credentialsContent');
    content.innerHTML = '<div class="loading">Loading user information...</div>';

    fetch(`get_user_credentials.php?user_id=${userId}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.text();
        })
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading credentials:', error);
            content.innerHTML = `<div class="error">
                Failed to load user information: ${error.message}
            </div>`;
        });
}
</script>

</body>
</html>
