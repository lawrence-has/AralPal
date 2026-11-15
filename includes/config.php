<?php
/**
 * AralPal – Core Configuration
 */

// ── Load .env into the process environment ───────────────────────────────────
// Parses KEY=VALUE lines from the project root .env file and calls putenv()
// so that all subsequent getenv() calls in this file return real values.
// Rules:
//   • Blank lines and lines starting with # are skipped.
//   • Optional surrounding quotes on values are stripped.
//   • Variables already set in the server/system environment are never
//     overwritten — real server env always wins over .env.
//   • If .env is absent (e.g. on a server that uses true env vars) this
//     block is a silent no-op.
(function () {
    $env_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($env_file)) {
        return;
    }
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and blank lines
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Split on first = only
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Strip optional surrounding single or double quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        // Only set if not already in the environment
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
})();

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
// Values are read from environment variables.
// Set them in your server environment or in a .env file loaded before this runs.
// See .env.example for the required variable names.
define('STRAPI_URL',   getenv('STRAPI_URL')   ?: 'http://localhost:1337');
define('STRAPI_TOKEN', getenv('STRAPI_TOKEN') ?: '');

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('ARALPAL_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,          // browser-session cookie (expires on browser close)
        'path'     => '/AralPal',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// ── PayMongo ──────────────────────────────────────────────────────────────────
// Values are read from environment variables (same pattern as STRAPI_TOKEN).
// Set them in your .env file. See .env.example for required variable names.
if (!defined('PAYMONGO_SECRET_KEY'))
    define('PAYMONGO_SECRET_KEY',     getenv('PAYMONGO_SECRET_KEY')     ?: 'sk_test_PLACEHOLDER_REPLACE_ME');
if (!defined('PAYMONGO_PUBLIC_KEY'))
    define('PAYMONGO_PUBLIC_KEY',     getenv('PAYMONGO_PUBLIC_KEY')     ?: 'pk_test_PLACEHOLDER_REPLACE_ME');
if (!defined('PAYMONGO_WEBHOOK_SECRET'))
    define('PAYMONGO_WEBHOOK_SECRET', getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');

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
