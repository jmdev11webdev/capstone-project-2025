<?php

$localhost = 'localhost';
$username = 'root';
$password = '';
$db_name = 'landseek';

// This creates the connection to the database
$conn = new mysqli($localhost, $username, $password, $db_name);

// This checks the connection
if ($conn->connect_error) {
    die ("Connection failed: " .$conn->connect_error);
}