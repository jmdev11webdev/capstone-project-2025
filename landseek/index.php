<?php
require_once "./connection/db_con.php"; // adjust path if needed

// Fetch all properties with status, images, etc.
$result = $conn->query("SELECT id, title, price_range, address, status, images, latitude, longitude 
                        FROM properties 
                        WHERE latitude IS NOT NULL 
                        AND longitude IS NOT NULL");

$properties = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Decode first image if stored as JSON
        $row['image'] = '';
        if (!empty($row['images'])) {
            $imgs = json_decode($row['images'], true);
            if (is_array($imgs) && count($imgs) > 0) {
                $row['image'] = './uploads/' . $imgs[0]; // adjust path
            }
        }
        $properties[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./styles/styles.css">
    
    <title>LandSeek | Home</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

    <!-- Fontawesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

    <style>
    /* Section Titles */
.available-properties h2,
.sold-properties h2,
.pending-properties h2 {
  margin: 10px 0;
  font-size: 1.5rem;
  color: #fff; /* adjust for your theme */
}

/* Scrollable containers */
.available-properties,
.sold-properties,
.pending-properties {
  margin-bottom: 30px;
}

.available-properties .properties-list,
.sold-properties .properties-list,
.pending-properties .properties-list {
  display: flex;
  flex-direction: row;
  overflow-x: auto;
  gap: 15px;
  padding: 10px 0;

  /* scroll snap for smooth slider feel */
  scroll-snap-type: x mandatory;

  scrollbar-width: thin; /* Firefox */
  scrollbar-color: #666 #222; /* Firefox */
}

/* Webkit scrollbars (Chrome, Edge, Safari) */
.available-properties .properties-list::-webkit-scrollbar,
.sold-properties .properties-list::-webkit-scrollbar,
.pending-properties .properties-list::-webkit-scrollbar {
  height: 8px;
}

.available-properties .properties-list::-webkit-scrollbar-thumb,
.sold-properties .properties-list::-webkit-scrollbar-thumb,
.pending-properties .properties-list::-webkit-scrollbar-thumb {
  background: #666;
  border-radius: 4px;
}

.available-properties .properties-list::-webkit-scrollbar-track,
.sold-properties .properties-list::-webkit-scrollbar-track,
.pending-properties .properties-list::-webkit-scrollbar-track {
  background: #222;
}

/* Property Cards */
.property-card {
  flex: 0 0 auto;
  width: 220px;
  background: #444;
  border-radius: 10px;
  padding: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.4);
  color: #fff;

  /* scroll snap alignment */
  scroll-snap-align: start;
}

.property-card img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 10px;
}

/* Status Colors */
.property-card.available { border: 2px solid #28a745; }
.property-card.sold { border: 2px solid #dc3545; }
.property-card.pending { border: 2px solid #ffc107; }
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
            <li><a href="index.php" class="ordinary"><i class="fas fa-house"></i> Home</a></li>
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
            <a href="contact.php"><i class="fa-solid fa-phone-volume"></i> Contacts</a>
            <a href="services.php"><i class="fa-solid fa-briefcase"></i> Services</a>
            <a href="login.html"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
            <a href="register.html"><i class="fa-solid fa-user-plus"></i> Register</a>
        </div>
    </header>

    <div class="container">
        <div class="content">
            <h1>Welcome to LandSeek</h1>
            <p>Your trusted digital marketplace for land hunting. <br> 
            Explore, find, and secure your ideal land with ease.</p>
            
            <div class="buttons">
                <a href="register.html" class="pap-btn"><i class="fas fa-upload"></i> Post a property</a>
                <a href="find-a-property.php" class="sap-btn"><i class="fas fa-eye"></i> Seek a property</a>
            </div>
        </div>

        <div id="map"></div>
    </div>

    <?php
    // ✅ Fetch full data for listing AFTER the map
    $result = $conn->query("SELECT id, title, price_range, address, images, status 
                            FROM properties");

    $available = [];
    $sold = [];
    $pending = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['images'] = json_decode($row['images'], true);

            switch (strtolower($row['status'])) {
                case "available":
                    $available[] = $row;
                    break;
                case "sold":
                    $sold[] = $row;
                    break;
                case "pending":
                    $pending[] = $row;
                    break;
            }
        }
    }
    ?>
    
    <h2 style="color:#333; text-align:center;"><i class="fa-solid fa-tags"></i> Available Properties</h2>
    <!-- Available Section -->
    <section class="available-properties" style="margin: 5%; font-size: small;">  
    <div class="properties-list">
        <?php if (!empty($available)): ?>
            <?php foreach ($available as $property): ?>
                <div class="property-card available">
                    <!-- Image -->
                    <?php if (!empty($property['images'])): ?>
                        <img src="uploads/<?= htmlspecialchars($property['images'][0]); ?>" alt="Property Image">
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($property['title']); ?></h3>
                    <p><strong><i class="fa-solid fa-money-bill"></i> Price:</strong> ₱ <?= htmlspecialchars($property['price_range']); ?></p>
                    <p><strong><i class="fa-solid fa-location"></i> Address:</strong> <?= htmlspecialchars($property['address']); ?></p>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No available properties.</p>
        <?php endif; ?>
    </div>
</section>

<h2 style="color:#333; text-align:center;"><i class="fa-solid fa-tags"></i> Sold Properties</h2>
<!-- Sold Section -->
<section class="sold-properties" style="margin:5%; font-size: small;">
    <div class="properties-list">
        <?php if (!empty($sold)): ?>
            <?php foreach ($sold as $property): ?>
                <div class="property-card sold">
                    <?php if (!empty($property['images'])): ?>
                        <img src="uploads/<?= htmlspecialchars($property['images'][0]); ?>" alt="Property Image">
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($property['title']); ?></h3>
                    <p><strong>Price:</strong> <?= htmlspecialchars($property['price_range']); ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($property['address']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No sold properties.</p>
        <?php endif; ?>
    </div>
</section>

<h2 style="color:#333; text-align:center;"><i class="fa-solid fa-tags"></i> Pending Properties</h2>
<!-- Pending Section -->
<section class="pending-properties" style="margin:5%; font-size:small;">
    <div class="properties-list">
        <?php if (!empty($pending)): ?>
            <?php foreach ($pending as $property): ?>
                <div class="property-card pending">
                    <?php if (!empty($property['images'])): ?>
                        <img src="uploads/<?= htmlspecialchars($property['images'][0]); ?>" alt="Property Image">
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($property['title']); ?></h3>
                    <p><strong>Price:</strong> <?= htmlspecialchars($property['price_range']); ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($property['address']); ?></p>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No pending properties.</p>
        <?php endif; ?>
    </div>
</section>

<?php $conn->close(); // close at the very end ?>
    
    <!-- Chatbot button -->
    <div class="chat-bot">
        <i class="fa-solid fa-robot fa-2x" id="chatBotBtn"></i>
    </div>

    <!-- Chatbot window -->
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            LandSeek Bot
            <span id="closeChat" style="cursor:pointer;">&times;</span>
        </div>
        <div class="chat-body" id="chatBody"></div>
    </div>

    <!-- Features Section -->
    <section class="features">
        <h2>Why Choose LandSeek?</h2>
        <div class="feature-cards">
            <div class="card">
                <i class="fa-solid fa-map-location-dot fa-2x"></i>
                <h3>Find Properties</h3>
                <p>Search land listings in just a few clicks with easy filters and categories.</p>
            </div>
            <div class="card">
                <i class="fa-solid fa-handshake fa-2x"></i>
                <h3>Direct Communication</h3>
                <p>Message buyers and sellers directly — no middlemen, no hassle.</p>
            </div>
            <div class="card">
                <i class="fa-solid fa-lock fa-2x"></i>
                <h3>Trusted Marketplace</h3>
                <p>Secure and reliable environment for connecting with real people.</p>
            </div>
        </div>
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
    
    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Initialize map, fixed center in Legazpi City
        const map = L.map("map").setView([13.1391, 123.7438], 13);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
        }).addTo(map);

        const properties = <?php echo json_encode($properties); ?>;

        properties.forEach(p => {
            if (p.latitude && p.longitude) {
                L.marker([p.latitude, p.longitude])
                    .addTo(map);
            }
        });
    </script>
    <script src="./javascripts/chatbot.js"></script>
</body>
</html>
