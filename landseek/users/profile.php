<?php
session_start();
session_regenerate_id(true); // This removes the current session and create new one to prevent of using session tokens
require_once "../connection/db_con.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit;
}

$user_id  = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? "User";

// Fetch full_name from user_profiles
$stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userProfile = $result->fetch_assoc();
$full_name = $userProfile['full_name'] ?? "User";
$stmt->close();

/* ==============================
   Fetch Properties with Conversations
   - Uploaders: properties they own
   - Non-uploaders: properties they’ve messaged on
================================*/
$propertyConvos = [];

$sql = "
  SELECT DISTINCT p.id AS property_id, p.title, p.user_id AS owner_id
  FROM properties p
  JOIN messages m ON p.id = m.property_id
  WHERE p.user_id = ? 
     OR m.sender_id = ? 
     OR m.receiver_id = ?
  ORDER BY p.created_at DESC
";

if ($msgQuery = $conn->prepare($sql)) {
    $msgQuery->bind_param("iii", $user_id, $user_id, $user_id);
    $msgQuery->execute();
    $propertyConvos = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $msgQuery->close();
}

/* ==============================
   Count Unread Messages
================================*/
$msgCountQuery = $conn->prepare("SELECT COUNT(*) AS count FROM messages WHERE receiver_id=? AND is_read=0");
$msgCountQuery->bind_param("i", $user_id);
$msgCountQuery->execute();
$msgCount = intval($msgCountQuery->get_result()->fetch_assoc()['count']);
$msgCountQuery->close();

/* ==============================
   Count Unread Notifications
================================*/
$notifCountQuery = $conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id=? AND is_read=0");
$notifCountQuery->bind_param("i", $user_id);
$notifCountQuery->execute();
$notifCount = intval($notifCountQuery->get_result()->fetch_assoc()['count']);
$notifCountQuery->close();

/* ==============================
   Fetch Notifications
================================*/
$notifQuery = $conn->prepare("
    SELECT id, type, title, message, is_read, created_at 
    FROM notifications 
    WHERE user_id=? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$notifQuery->bind_param("i", $user_id);
$notifQuery->execute();
$notifications = $notifQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$notifQuery->close();

/* ==============================
   Fetch User Profile Details
================================*/
$stmt = $conn->prepare("
    SELECT u.email, p.full_name, p.suffix, p.phonenumber, p.profile_picture
    FROM users u
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

/* ==============================
   Update Profile
================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $suffix = $_POST['suffix'];
    $phonenumber = $_POST['phonenumber'];
    $profile_picture = $user['profile_picture']; // keep old by default

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $profile_picture = file_get_contents($_FILES['profile_picture']['tmp_name']);
    }

    $stmt1 = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt1->bind_param("si", $email, $user_id);
    $stmt1->execute();
    $stmt1->close();

    $check = $conn->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt2 = $conn->prepare("UPDATE user_profiles 
            SET full_name=?, suffix=?, phonenumber=?, profile_picture=? 
            WHERE user_id=?");
        $stmt2->bind_param("ssssi", $full_name, $suffix, $phonenumber, $profile_picture, $user_id);
        $stmt2->send_long_data(3, $profile_picture);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO user_profiles (user_id, full_name, suffix, phonenumber, profile_picture) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("issss", $user_id, $full_name, $suffix, $phonenumber, $profile_picture);
        $stmt2->send_long_data(4, $profile_picture);
        $stmt2->execute();
        $stmt2->close();
    }

    $check->close();
    header("Location: profile.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile | LandSeek</title>
  <link rel="stylesheet" href="../styles/users.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">

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

    <span class="menu-btn" onclick="openNav()">&#9776;</span>
    <div id="mySidenav" class="side-nav">
      <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
      <a href="dashboard.php"><i class="fa-solid fa-dashboard"></i> Dashboard</a>
      <a href="properties.php"><i class="fa-solid fa-list"></i> Properties</a>
      <a href="saved_properties.php"><i class="fa-solid fa-bookmark"></i> Saved to Favorites</a>
      <a href="map.php"><i class="fa-solid fa-map"></i> Map</a>
    </div>
</header>

<main class="profile-main">
    <h1><i class="fas fa-user-circle"></i> My Profile</h1>

    <?php if (isset($_GET['success'])): ?>
        <p class="success-msg">Profile updated successfully!</p>
    <?php endif; ?>
    
    <br>

    <form method="POST" enctype="multipart/form-data" class="profile-form">
        <div class="form-row">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>

        <div class="form-row">
            <label>Phone Number</label>
            <input type="text" name="phonenumber" value="<?php echo htmlspecialchars($user['phonenumber']); ?>" required>
        </div>

        <div class="form-row">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
        </div>

        <div class="form-row">
            <label>Suffix</label>
            <input type="text" name="suffix" value="<?php echo htmlspecialchars($user['suffix']); ?>">
        </div>

        <div class="form-row">
            <label>Profile Picture</label>
            <input type="file" name="profile_picture" accept="image/*">

            <br>
            
            <?php if ($user['profile_picture']): ?>
                <img src="data:image/jpeg;base64,<?php echo base64_encode($user['profile_picture']); ?>" alt="Profile Picture" class="profile-pic">
            <?php endif; ?>
        </div>

        <button type="submit" class="save-btn">Save Changes</button>
    </form>
</main>

<!-- Property Inquiries Modal -->
  <div id="propertyModal" class="property-modal">
    <div class="property-modal-content">
      <span class="property-modal-close" onclick="closePropertyModal()">&times;</span>
      <h3 id="propertyTitle"></h3>
      <div id="interestedUsersList"></div>
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
