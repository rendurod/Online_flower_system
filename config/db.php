<?php
// Database configuration
$host = 'localhost'; // or your database host
$dbname = 'flowershop_db';
$username = 'root';
$password = '';

try {
    // Create a PDO instance (connect to the database)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password);
     
    // Set PDO attributes
     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Enable exceptions on errors
     $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Set default fetch mode to associative array
     $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Disable emulation of prepared statements.
     
    // echo "Connected successfully!";

} catch (PDOException $e) {
    // Handle connection error
    echo "Connection failed: " . $e->getMessage();
}
?>