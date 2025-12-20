// Video Modal Functions
function openVideoModal(videoFilename) {
  const modal = document.getElementById("videoModal");
  const videoPlayer = document.getElementById("videoPlayer");
  
  // Set video source
  videoPlayer.src = "../uploads/videos/" + videoFilename;
  
  // Show modal
  modal.style.display = "block";
  
  // Attempt to play video
  videoPlayer.load();
  videoPlayer.onloadeddata = function() {
    videoPlayer.play().catch(error => {
      console.error("Video play failed:", error);
      // Autoplay might be blocked by browser policy
    });
  };
}

// Close video modal and stop video
function closeVideoModal() {
  const modal = document.getElementById("videoModal");
  const videoPlayer = document.getElementById("videoPlayer");
  
  // Pause video and reset
  videoPlayer.pause();
  videoPlayer.currentTime = 0;
  videoPlayer.src = "";
  
  // Hide modal
  modal.style.display = "none";
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
  const modal = document.getElementById("videoModal");
  if (event.target === modal) {
    closeVideoModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === "Escape") {
    closeVideoModal();
  }
});