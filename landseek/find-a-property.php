<?php
session_start();
require_once "./connection/db_con.php";

// Fetch properties with coordinates
$stmt = $conn->prepare("
    SELECT id, title, price_range, city, province, latitude, longitude
    FROM properties
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");
$stmt->execute();
$result = $stmt->get_result();
$properties = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LandSeek | Find a Property</title>

  <!-- Styles -->
  <link rel="stylesheet" href="./styles/styles.css">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" />

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  
  <!-- This is the Poppins font from Google -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
  <style>
    #map { height: 500px; width: 100%; margin: 20px auto; border-radius: 10px; }
    .note {
      background: #fff3cd;
      border: 1px solid #ffeeba;
      padding: 12px;
      margin: 0;
      text-align: center;
      color: #856404;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <header>
        <span>
        <img src="./assets/logo/LandSeekLogo.png" alt="LandSeek Logo" width="80" height="80">
        </span>

        <span>
        <small><b>Digital Marketplace <br>
        for Land Hunting </b></small>
        </span>

        <nav class="index-nav">
            <ul>
            <li><a href="index.php"><i class="fas fa-house"></i> Home</a></li>
            <li><a href="about.html"><i class="fas fa-circle-question"></i> About</a></li>
            <li><a href="contact.html"><i class="fas fa-phone-volume"></i> Contacts</a></li>
            <li><a href="services.html"><i class="fas fa-briefcase"></i> Services</a></li>
            <li><a href="login.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a></li>
            <li><a href="register.html"><i class="fa-solid fa-user-plus"></i> Register</a></li>
        </nav>
        
        <!-- Menu button -->
        <span class="menu-btn" onclick="openNav()">&#9776;</span>
        
        <!-- Side navigation -->
        <div id="mySidenav" class="side-nav">
            <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
            <a href="index.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
            <a href="about.html"><i class="fa-solid fa-circle-question"></i> About</a>
            <a href="saved_properties.php"><i class="fa-solid fa-bookmark"></i> Saved to Favorites</a>
            <a href="map.php"><i class="fa-solid fa-map"></i> Map</a>
            <a href="login.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
            <a href="register.html"><i class="fa-solid fa-user-plus"></i> Register</a>
        </div>
    </header>

  <!-- Note for guests -->
  <div class="note">
    ⚠ You are currently only in <b>view mode</b>. Please register and login to access the full features of <b>LandSeek</b>.
  </div>

  <!-- Hero Section -->
  <section class="find-a-property-container">
    <div class="hero-text">
      <h1>Explore Properties on the Map</h1>
      <p>Discover available land listings from different regions, provinces, and municipalities across the country.</p>
    </div>
    <div class="hero-image">
      <img src="./assets/logo/LandSeekLogo.png" alt="LandSeek Logo" width="190" height="190">
    </div>
  </section>

  <!-- Map Section -->
  <section style="padding: 20px 8%;">
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

  <!-- Leaflet -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    // Initialize map
    var map = L.map('map').setView([12.8797, 121.7740], 6); // Center: Philippines

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Add property markers
    var properties = <?php echo json_encode($properties); ?>;
    properties.forEach(function(p) {
      if (p.latitude && p.longitude) {
        var marker = L.marker([p.latitude, p.longitude]).addTo(map);
        marker.bindPopup(
          "<b>" + p.title + "</b><br>" +
          "₱" + new Intl.NumberFormat().format(p.price_range) + "<br>" +
          p.city + ", " + p.province + "<br><br>" +
          "<a href='login.html' style='color:#32a852; font-weight:bold;'>Login to Inquire</a>"
        );
      }
    });
  </script>

  <!-- ChatBot Floating Button -->
  <div class="chat-bot">
    <i class="fa-solid fa-robot fa-2x" id="chatBotBtn"></i>
  </div>

  <!-- Chat Window -->
  <div class="chat-window" id="chatWindow">
    <div class="chat-header">
      LandSeek Bot
      <span id="closeChat" style="cursor:pointer;">&times;</span>
    </div>
    <div class="chat-body" id="chatBody"></div>
  </div>

  <!-- ChatBot Script -->
  <script>
    const chatBtn = document.getElementById('chatBotBtn');
    const chatWindow = document.getElementById('chatWindow');
    const closeChat = document.getElementById('closeChat');
    const chatBody = document.getElementById('chatBody');

    const conversation = [
      "👋 Hi! Welcome to LandSeek.",
      "You are currently viewing properties in guest mode.",
      "To access full features, please register and login.",
      "By logging in, you can post properties, save favorites, and directly inquire with sellers.",
      "✅ Click 'Login' or 'Register' on the top-right to get started!"
    ];

    let step = 0;

    function showMessage(text) {
      let msg = document.createElement('p');
      msg.innerHTML = `<b>Bot:</b> ${text}`;
      chatBody.appendChild(msg);
      chatBody.scrollTop = chatBody.scrollHeight;
    }

    chatBtn.addEventListener('click', () => {
      chatWindow.style.display = 'block';
      chatBody.innerHTML = "";
      step = 0;
      runConversation();
    });

    closeChat.addEventListener('click', () => {
      chatWindow.style.display = 'none';
    });

    function runConversation() {
      if (step < conversation.length) {
        showMessage(conversation[step]);
        step++;
        setTimeout(runConversation, 2500);
      }
    }
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
