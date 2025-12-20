<?php
// Start session
session_start();

// Destroy session and redirect to login page
session_unset();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../login.html");
exit;
