<?php
// Database configuration using environment variables for flexibility
// These fall back to local XAMPP defaults if env vars are not set
$db_server   = getenv('DB_HOST') ?: 'localhost';
$db_username = getenv('DB_USERNAME') ?: 'root';
$db_password = getenv('DB_PASSWORD') ?: '';
$db_name     = getenv('DB_NAME') ?: 'marc_school';
// Port is optional; default to 3306 if not provided
$db_port     = getenv('DB_PORT') ?: 3306;

// Attempt to connect to MySQL database
$conn = new mysqli($db_server, $db_username, $db_password, $db_name, (int)$db_port);

// Check connection
if ($conn->connect_error) {
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>
