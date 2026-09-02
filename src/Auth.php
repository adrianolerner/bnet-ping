<?php
namespace PingApp;

class Auth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin() {
        self::startSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: ?page=login");
            exit;
        }
    }

    public static function login($username, $password, $turnstileToken = null) {
        $db = Database::getConnection();
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (self::isRateLimited($ip)) {
            return ['success' => false, 'error' => 'Too many failed attempts. Try again later.'];
        }

        $settings = Settings::getAll();
        if (isset($settings['turnstile_enabled']) && $settings['turnstile_enabled'] == '1') {
            if (!$turnstileToken || !self::verifyTurnstile($turnstileToken, $settings['turnstile_secret_key'])) {
                self::recordFailedAttempt($ip);
                return ['success' => false, 'error' => 'Captcha verification failed.'];
            }
        }

        $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            self::startSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            self::clearFailedAttempts($ip);
            return ['success' => true];
        }

        self::recordFailedAttempt($ip);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    public static function logout() {
        self::startSession();
        session_destroy();
        header("Location: ?page=login");
        exit;
    }

    private static function isRateLimited($ip) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT attempts, last_attempt FROM rate_limits WHERE ip = ?");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        if ($row) {
            $lastAttemptTime = strtotime($row['last_attempt']);
            if (time() - $lastAttemptTime > 300) {
                // Reset after 5 minutes
                $db->prepare("UPDATE rate_limits SET attempts = 0 WHERE ip = ?")->execute([$ip]);
                return false;
            }
            return $row['attempts'] >= 5;
        }
        return false;
    }

    private static function recordFailedAttempt($ip) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO rate_limits (ip, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        $stmt->execute([$ip]);
    }
    
    private static function clearFailedAttempts($ip) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip = ?");
        $stmt->execute([$ip]);
    }

    private static function verifyTurnstile($token, $secret) {
        if (empty($secret)) return false;
        
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === FALSE) return false;

        $response = json_decode($result, true);
        return $response['success'] ?? false;
    }

    // User Management Functions
    public static function getAllUsers() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
        return $stmt->fetchAll();
    }

    public static function addUser($username, $password, $role = 'user') {
        $db = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            return $stmt->execute([$username, $hash, $role]);
        } catch (\PDOException $e) {
            return false; // likely duplicate username
        }
    }

    public static function deleteUser($id) {
        $db = Database::getConnection();
        // Prevent deleting the very last user
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() <= 1) {
            return false;
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function changePassword($userId, $currentPassword, $newPassword) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($currentPassword, $user['password'])) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            return $stmt->execute([$hash, $userId]);
        }
        return false;
    }
}
