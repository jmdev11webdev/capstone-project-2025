<?php
session_start(); // start session
session_regenerate_id(true); // This removes the current session and create new one to prevent of using session tokens
require_once "../connection/db_con.php"; // mysqli $conn
header('Content-Type: application/json'); // return JSON

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// --- SEND MESSAGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0); // recipient user ID
    $message = trim($_POST['message'] ?? ''); // message text
    $property_id = intval($_POST['property_id'] ?? 0); // property ID

    // Validate inputs
    if (!$receiver_id || !$message || !$property_id) {
        echo json_encode(["error" => "Invalid input"]);
        exit;
    }

    // Insert message into database
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, property_id, message, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    "); // Prepare statement

    // "iiis" = int, int, int, string
    $stmt->bind_param("iiis", $user_id, $receiver_id, $property_id, $message); // Bind parameters

    // Execute and check
    if ($stmt->execute()) {
        echo json_encode(["success" => true]); // Success
    } else {
        // Failure
        echo json_encode(["error" => "Failed to send message: " . $stmt->error]); // include error
    }
    $stmt->close(); // close statement

    // Stop further processing
    exit;
}

// --- FETCH MESSAGES ---
$receiver_id = intval($_GET['receiver_id'] ?? 0); // recipient user ID
if (!$receiver_id) {
    // Invalid receiver ID
    echo json_encode([]); // return empty array
    
    // Stop further processing
    exit;
}

// Fetch messages between logged-in user and receiver
$stmt = $conn->prepare("
    SELECT m.*, u.full_name 
    FROM messages m
    JOIN user_profiles u ON u.user_id = m.sender_id
    WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
    ORDER BY m.created_at ASC
");

// "iiii" = int, int, int, int
// Bind parameters
$stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id); // Bind parameters
$stmt->execute(); // Execute query
$result = $stmt->get_result(); // Get result
$messages = []; // Array to hold messages
while ($row = $result->fetch_assoc()) {
    // Add each message to array
    $messages[] = [
        "id" => $row['id'],
        "sender_id" => $row['sender_id'],
        "receiver_id" => $row['receiver_id'],
        "message" => $row['message'],
        "created_at" => $row['created_at'],
        "full_name" => $row['full_name']
    ];
}

// Close statement
$stmt->close();

// Return messages as JSON
echo json_encode($messages);
