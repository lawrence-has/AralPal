<?php
/**
 * AralPal – Core Configuration
 */

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'aralpal');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── Site ──────────────────────────────────────────────────────────────────────
define('SITE_URL', 'http://localhost/AralPal');
define('SITE_NAME', 'AralPal');

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Strapi CMS ────────────────────────────────────────────────────────────────
define('STRAPI_URL',   'http://localhost:1337');   // change if hosted remotely
define('STRAPI_TOKEN', 'f202df091528755af6a80725ff9c466dba584bd252ba58de6353e59990582de21e5a0f999555e9b0ee342fc24248d015a2ed201fd9b5574757e3ccd2b718c8e022bb68d9cccd35fff0c2530c7f2ae016c8df42dbb2791f065bdf706ce71f63b1843c4310fbfbe0d3fc179dec28ee4e182c4fa467c9e4d7c4c93ae5820d9fa1c9');

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('ARALPAL_SESSION');
    session_set_cookie_params([
        'path'     => '/AralPal',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── CSRF helpers ──────────────────────────────────────────────────────────────

/**
 * Returns the current CSRF token, creating one if absent.
 */
function generate_csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token using a timing-safe comparison.
 */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
