<?php
declare(strict_types=1);

/*
 * Copy this file to cms-config.php and update the database settings.
 * Use the database AralPal already uses if you want to share its data store.
 */
const CMS_DB_HOST = '127.0.0.1';
const CMS_DB_NAME = 'aralpal'; // CHANGE to your existing AralPal database name
const CMS_DB_USER = 'root';
const CMS_DB_PASS = ''; // CHANGE if your MySQL has a password

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . CMS_DB_HOST . ';dbname=' . CMS_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, CMS_DB_USER, CMS_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_admin(): void {
    if (empty($_SESSION['cms_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['cms_csrf'])) {
        $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cms_csrf'];
}

function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}
