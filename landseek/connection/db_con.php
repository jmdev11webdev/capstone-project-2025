<?php

$localhost = ''; // usually localhost
$username = 'root'; // default XAMPP username
$password = ''; // default XAMPP password is empty
$db_name = ''; // database name
$port = ''; // port

// This creates the connection to the database
$conn = new mysqli($localhost, $username, $password, $db_name, $port); // mysqli object

// This checks the connection
if ($conn->connect_error) { 
    // If connection fails, show error and stop execution
    die ("Connection failed: " .$conn->connect_error);
}