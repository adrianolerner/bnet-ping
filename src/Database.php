<?php
namespace PingApp;

use PDO;
use PDOException;

class Database {
    private static $pdo = null;
    
    public static function getConnection() {
        if (self::$pdo === null) {
            $configPath = __DIR__ . '/../config.php';
            if (!file_exists($configPath)) {
                die("Configuration file config.php not found.");
            }
            $config = require $configPath;
            
            try {
                $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::initSchema();
            } catch (PDOException $e) {
                if ($e->getCode() == 1049) { // Database doesn't exist
                    self::createDatabase($config);
                    return self::getConnection();
                }
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    private static function createDatabase($config) {
        try {
            $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}`");
        } catch (PDOException $e) {
            die("Could not create database: " . $e->getMessage());
        }
    }

    private static function initSchema() {
        $db = self::$pdo;
        
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'admin'");
        }

        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT NOT NULL
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS hosts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            ip VARCHAR(255) NOT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'unknown',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            active TINYINT(1) DEFAULT 1
        )");

        // Migration for existing tables
        try {
            $db->exec("ALTER TABLE hosts ADD COLUMN active TINYINT(1) DEFAULT 1");
        } catch (\PDOException $e) {
            // Column already exists
        }

        try {
            $db->exec("ALTER TABLE hosts ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL, ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL");
        } catch (\PDOException $e) {
            // Columns already exist
        }

        try {
            $db->exec("ALTER TABLE hosts ADD COLUMN last_status_change DATETIME DEFAULT CURRENT_TIMESTAMP");
        } catch (\PDOException $e) {
            // Column already exists
        }

        try {
            $db->exec("ALTER TABLE host_downtimes ADD COLUMN notified_down TINYINT(1) DEFAULT 0");
            $db->exec("ALTER TABLE host_downtimes ADD COLUMN notified_up TINYINT(1) DEFAULT 0");
        } catch (\PDOException $e) {
            // Columns already exist
        }

        $db->exec("CREATE TABLE IF NOT EXISTS ping_results (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            host_id INT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) NOT NULL,
            min_ms FLOAT,
            max_ms FLOAT,
            avg_ms FLOAT,
            packet_loss FLOAT,
            ttl INT,
            jitter FLOAT,
            FOREIGN KEY(host_id) REFERENCES hosts(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS host_downtimes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            host_id INT NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            notified_down TINYINT(1) DEFAULT 0,
            notified_up TINYINT(1) DEFAULT 0,
            FOREIGN KEY(host_id) REFERENCES hosts(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS archived_host_downtimes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            host_id INT NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            FOREIGN KEY(host_id) REFERENCES hosts(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip VARCHAR(45) PRIMARY KEY,
            attempts INT DEFAULT 0,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert default user if not exists
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO users (username, password) VALUES ('admin', '$hash')");
        }

        // Insert default settings
        $defaultSettings = [
            'ping_interval' => '60',
            'ping_count' => '4',
            'turnstile_enabled' => '0',
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
            'waha_enabled' => '0',
            'waha_url' => 'http://localhost:3001/api/sendText',
            'waha_api_key' => 'sua_api_key_aqui_exemplo',
            'waha_chat_id' => 'xxxxxxxx@g.us',
            'waha_session' => 'default',
            'app_url' => 'http://localhost/ping'
        ];
        
        $stmt = $db->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
        foreach ($defaultSettings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
