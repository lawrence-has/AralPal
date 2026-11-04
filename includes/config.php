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

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
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
