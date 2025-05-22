<?php
// Set the database host (usually localhost for XAMPP)
$host = "localhost";

// Set the database username (default for XAMPP is 'root')
$user = "root";

// Set the database password (default for XAMPP is an empty string)
$pass = "";

// Set the name of your database (the one you created in phpMyAdmin)
$db   = "apartment_monitoring_db";

// Create a new MySQLi connection using the settings above
$conn = new mysqli($host, $user, $pass, $db);

// Check if the connection was successful
// If there is an error, stop the script and display an error message
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>