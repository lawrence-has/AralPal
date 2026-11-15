<?php
/**
 * AralPal – Authentication & Authorization Helpers
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Returns the logged-in user array from the session, or null if not logged in.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Returns true when a user session is active.
 */
function is_logged_in(): bool
{
    return (bool) current_user();
}

/**
 * Redirects to the login page if the visitor is not authenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /AralPal/auth/login.php');
        exit;
    }
}

/**
 * Requires the visitor to be logged in and to hold the given role.
 * Shows a 403 page and exits otherwise.
 */
function require_role(string $role): void
{
    require_login();

    if (strcasecmp((string) (current_user()['role_name'] ?? ''), $role) !== 0) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
           . '<title>403 Forbidden</title></head><body>'
           . '<h1>403 – Forbidden</h1>'
           . '<p>You do not have permission to access this page.</p>'
           . '<a href="/AralPal/index.php">Return home</a>'
           . '</body></html>';
        exit;
    }
}

/**
 * Stores the user's data in the session and rotates the session ID.
 *
 * @param array $user Must contain keys: id, full_name, email, role_name, avatar, status
 */
function login_user(array $user): void
{
    // Rotate the session ID first to prevent session fixation attacks.
    // The 'true' parameter deletes the old session file immediately.
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role_name' => $user['role_name'],
        'avatar'    => $user['avatar'],
        'status'    => $user['status'],
    ];
}

/**
 * Destroys the session and redirects to the home page.
 *
 * Order matters:
 * 1. Capture the session name and cookie params BEFORE destroying the session.
 * 2. Clear the $_SESSION superglobal.
 * 3. Send a Set-Cookie header to expire the browser's session cookie, using
 *    the exact same attributes (path, secure, httponly, samesite) it was set
 *    with. Mismatched attributes mean the browser ignores the deletion.
 * 4. Destroy the server-side session storage.
 */
function logout_user(): void
{
    // 1. Capture cookie identity before destroying anything
    $cookie_name   = session_name();
    $cookie_params = session_get_cookie_params();

    // 2. Wipe the in-memory session data
    $_SESSION = [];

    // 3. Expire the browser cookie — attributes must match the original Set-Cookie
    if (ini_get('session.use_cookies')) {
        setcookie($cookie_name, '', [
            'expires'  => time() - 3600,
            'path'     => $cookie_params['path'],
            'domain'   => $cookie_params['domain'],
            'secure'   => $cookie_params['secure'],
            'httponly' => $cookie_params['httponly'],
            'samesite' => $cookie_params['samesite'] ?? 'Lax',
        ]);
    }

    // 4. Destroy the server-side session storage
    session_destroy();

    header('Location: /AralPal/index.php');
    exit;
}

/**
 * Returns the number of unread notifications for the given user.
 */
function get_unread_notification_count(int $user_id): int
{
    $stmt = get_db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}
