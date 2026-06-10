<?php
// includes/db.php
// This file connects to MySQL using PDO (PHP Data Objects).
// We include this file anytime we need to talk to the database.

require_once __DIR__ . '/../config.php';

try {
    // Create the connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    
    // Set PDO to throw an Exception if there is an error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set PDO to return data as an associative array (e.g., $row['full_name'])
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // If the connection fails, stop the page and show the error
    die("Database connection failed: " . $e->getMessage());
}
