<?php
// Start session
session_start();

// Database connection
require_once "../connection/db_con.php"; // mysqli $conn

// Initialize variables
// Sanitize and validate inputs
// Check if email exists and is verified and active
// Verify password 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Include is_active in the query
    $stmt = $conn->prepare("SELECT id, email, password, is_verified, is_active FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    // Bind parameters and execute
    // "s" indicates the type is string
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // Check if user exists
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_email, $db_password, $is_verified, $is_active);
        $stmt->fetch();

        // if is_active is 0, user is banned
        // if is_active is 1, user is active
        if ($is_active == 0) {
            // if user is banned then show alert and redirect to login page
            echo "<script>
                    alert('This account has been banned. Please contact support.');
                    window.location.href = '../login.html';
                  </script>";
            exit;
        }

        // Verify password
        // Use password_verify for hashed passwords
        if (password_verify($password, $db_password)) {
            if ($is_verified == 1) {
                // Fetch full name from user_profiles
                // Assuming a user_profiles table with user_id and full_name columns
                // Prepare and execute the query
                $profileStmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_id = ? LIMIT 1");
                $profileStmt->bind_param("i", $id);
                $profileStmt->execute();
                $profileResult = $profileStmt->get_result();
                $profile = $profileResult->fetch_assoc();
                $fullname = $profile ? $profile['full_name'] : "";

                // Store session variables
                $_SESSION['user_id'] = $id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $db_email;

                header("Location: ../users/dashboard.php");
                exit;
            } else {
                // Not verified
                $_SESSION['verify_email'] = $db_email;
                echo "<script>
                        alert('Please verify your email before logging in.');
                        window.location.href = 'verifyemail.php?email=" . urlencode($db_email) . "';
                      </script>";
                exit;
            }
        } else {
            // Invalid password
            echo "<script>
                    alert('Invalid email or password.');
                    window.location.href = '../login.html';
                  </script>";
        }
    } else {
        // No user found
        echo "<script>
                alert('No account found with that email.');
                window.location.href = '../login.html';
              </script>";
    }

    // Close statement
    $stmt->close();
}

// Close connection
$conn->close();
?>