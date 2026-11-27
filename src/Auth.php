<?php
namespace PingApp;

class Auth {
    public static function isHttps() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
    }

    public static function applySecurityHeaders() {
        if (headers_sent()) {
            return;
        }

        // Remove cabeçalho que revela a tecnologia/versão do PHP
        header_remove('X-Powered-By');

        // Previne MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Proteção contra Clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Política de Referrer segura (evita vazamento de URL completa externamente)
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Restringe acesso a recursos de hardware/sensores não utilizados
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');

        // HSTS (HTTP Strict Transport Security) caso HTTPS esteja em uso
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy (CSP)
        // Permite os recursos legítimos da aplicação (CDNs Chart.js, Turnstile, Leaflet, Google Fonts, OpenStreetMap/Carto)
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://challenges.cloudflare.com https://unpkg.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; " .
               "font-src 'self' https://fonts.gstatic.com data:; " .
               "img-src 'self' data: blob: https://*.basemaps.cartocdn.com https://*.tile.openstreetmap.org https://*.openstreetmap.org; " .
               "connect-src 'self' https://challenges.cloudflare.com https://*.basemaps.cartocdn.com https://*.tile.openstreetmap.org https://*.openstreetmap.org; " .
               "frame-src 'self' https://challenges.cloudflare.com; " .
               "media-src 'self' blob: data:; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'; " .
               "frame-ancestors 'self'; " .
               "upgrade-insecure-requests;";
        header("Content-Security-Policy: {$csp}");
    }

    public static function generateCsrfToken() {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken($token) {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token) || !is_string($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = self::isHttps();

            ini_set('session.cookie_httponly', '1');
            if ($isHttps) {
                ini_set('session.cookie_secure', '1');
            }
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', '1');

            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
            }

            session_start();
        }
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function isPublicAccessAuthorized() {
        $expected = Settings::get('public_access_code', '');
        if (empty($expected)) {
            return true;
        }

        // Se houver sessão ativa do usuário logado (ex: admin), libera acesso
        $sessName = session_name();
        if ((isset($_COOKIE[$sessName]) || session_status() === PHP_SESSION_ACTIVE) && self::isLoggedIn()) {
            return true;
        }

        // Verifica cookie de acesso público
        if (isset($_COOKIE['public_access_code']) && is_string($_COOKIE['public_access_code'])) {
            if (hash_equals($expected, $_COOKIE['public_access_code'])) {
                return true;
            }
        }

        // Verifica parâmetro de URL (magic link de notificações WAHA)
        $codeParam = $_GET['code'] ?? $_GET['access_code'] ?? null;
        if (!empty($codeParam) && is_string($codeParam)) {
            if (hash_equals($expected, $codeParam)) {
                if (!headers_sent()) {
                    $isHttps = self::isHttps();
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie('public_access_code', $expected, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => $isHttps,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    } else {
                        setcookie('public_access_code', $expected, time() + (30 * 24 * 60 * 60), '/; SameSite=Lax' . ($isHttps ? '; Secure' : '') . '; HttpOnly');
                    }
                }
                return true;
            }
        }

        // Verifica cabeçalho X-Public-Access-Code enviado pelo frontend
        $headerCode = $_SERVER['HTTP_X_PUBLIC_ACCESS_CODE'] ?? null;
        if (!empty($headerCode) && is_string($headerCode)) {
            if (hash_equals($expected, $headerCode)) {
                return true;
            }
        }

        return false;
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
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
