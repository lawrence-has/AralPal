<?php
declare(strict_types=1);

/*
 * AralPal CMS configuration.
 * Loaded by both the admin panel AND index.php (for CMS content on the homepage).
 * All functions are guarded with function_exists() to prevent fatal redeclaration
 * errors when includes/config.php has already defined overlapping helpers.
 */

// ── Database credentials ──────────────────────────────────────────────────────
// Shares the same database as the main AralPal application.
if (!defined('CMS_DB_HOST')) define('CMS_DB_HOST', 'localhost');
if (!defined('CMS_DB_NAME')) define('CMS_DB_NAME', 'aralpal');
if (!defined('CMS_DB_USER')) define('CMS_DB_USER', 'root');
if (!defined('CMS_DB_PASS')) define('CMS_DB_PASS', '');

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── db() — CMS PDO singleton ──────────────────────────────────────────────────
// When index.php loads this file it already has get_db() from includes/db.php.
// db() is the CMS admin's shorthand; they use the same underlying connection.
if (!function_exists('db')) {
    function db(): PDO {
        // Reuse get_db() if it is available (same process, same DSN).
        if (function_exists('get_db')) {
            return get_db();
        }

        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $dsn = 'mysql:host=' . CMS_DB_HOST . ';dbname=' . CMS_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, CMS_DB_USER, CMS_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    }
}

// ── e() — HTML escape helper ─────────────────────────────────────────────────
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ── require_admin() — CMS access guard ───────────────────────────────────────
if (!function_exists('require_admin')) {
    function require_admin(): void {
        if (empty($_SESSION['cms_admin_id'])) {
            header('Location: login.php');
            exit;
        }
    }
}

// ── csrf_token() — CMS-specific CSRF token (separate from app token) ─────────
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['cms_csrf'])) {
            $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['cms_csrf'];
    }
}

// ── verify_csrf() — CMS CSRF verification ────────────────────────────────────
// The main app uses verify_csrf(string $token): bool  (in includes/config.php).
// The CMS version has a different signature: verify_csrf(): void (reads $_POST).
// We only define it when the main-app version is not already loaded.
if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        $sent = $_POST['csrf_token'] ?? '';
        if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
            http_response_code(403);
            exit('Invalid security token. Refresh the page and try again.');
        }
    }
}

// ── redirect() — safe redirect helper ────────────────────────────────────────
if (!function_exists('redirect')) {
    function redirect(string $path): never {
        header('Location: ' . $path);
        exit;
    }
}
