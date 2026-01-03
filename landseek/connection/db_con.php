<?php

$localhost = '127.0.0.1'; // usually localhost
$username = 'root'; // default XAMPP username
$password = 'Jmdev2025isjmtl@2003!!'; // default XAMPP password is empty
$db_name = 'landseek'; // database name
$port = 3307;

// This creates the connection to the database
$conn = new mysqli($localhost, $username, $password, $db_name, $port); // mysqli object

// This checks the connection
if ($conn->connect_error) { 
    // If connection fails, show error and stop execution
    die ("Connection failed: " .$conn->connect_error);
}