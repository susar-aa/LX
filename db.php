<?php
// db.php - Database connection and automatic schema initialization

// 1. Load configuration if exists
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'suzxlabs');
if (!defined('DB_PASS')) define('DB_PASS', 'Susara@200611003614');
if (!defined('DB_NAME')) define('DB_NAME', 'lx_db');

// 2. Establish connection to MySQL server
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed. Please ensure your config.php contains the correct credentials for lx.suzxlabs.com.',
        'details' => $conn->connect_error
    ]);
    exit;
}

// 3. Select database or attempt to create it
$db_selected = @$conn->select_db(DB_NAME);

if (!$db_selected) {
    $sql_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql_db)) {
        if (!$conn->select_db(DB_NAME)) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to select created database: ' . DB_NAME,
                'details' => $conn->error
            ]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Database "' . DB_NAME . '" does not exist and could not be created automatically. Please create it manually via Plesk and grant privileges to the database user.',
            'details' => $conn->error
        ]);
        exit;
    }
}

// 4. Create Tables
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
if (!$conn->query($sql_friends) || !$conn->query($sql_transactions) || !$conn->query($sql_settings)) {
    die("Error initializing database schema: " . $conn->error);
}

// Helper to get active DB connection
function get_db_connection() {
    global $conn;
    return $conn;
}
?>
