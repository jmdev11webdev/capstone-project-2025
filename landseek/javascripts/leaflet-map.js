// Initialize the map
var map = L.map('map').setView([14.5995, 120.9842], 12); // Manila as example

// Add OpenStreetMap tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

// Add a marker
L.marker([14.5995, 120.9842]).addTo(map)
.bindPopup('Manila')
.openPopup();