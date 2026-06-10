<?php
// db.php - Database connection and automatic schema initialization

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'lx_db';

// 1. Establish connection to MySQL server
$conn = @new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// 2. Create database if it does not exist
$sql_db = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$conn->query($sql_db)) {
    die("Error creating database: " . $conn->error);
}

// 3. Select the database
if (!$conn->select_db($db_name)) {
    die("Database selection failed: " . $conn->error);
}

// 4. Create Tables
// Users Table (for single admin auth)
$sql_users = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Friends Table
$sql_friends = "CREATE TABLE IF NOT EXISTS `friends` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Transactions Table
$sql_transactions = "CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `friend_id` INT NOT NULL,
    `type` ENUM('lend', 'repayment') NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `date` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`friend_id`) REFERENCES `friends`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Settings Table
$sql_settings = "CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute creations
if (!$conn->query($sql_users) || !$conn->query($sql_friends) || !$conn->query($sql_transactions) || !$conn->query($sql_settings)) {
    die("Error initializing database schema: " . $conn->error);
}

// Helper to get active DB connection
function get_db_connection() {
    global $conn;
    return $conn;
}
?>
