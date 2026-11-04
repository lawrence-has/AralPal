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
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role_name' => $user['role_name'],
        'avatar'    => $user['avatar'],
        'status'    => $user['status'],
    ];
    session_regenerate_id(true);
}

/**
 * Destroys the session and redirects to the home page.
 */
function logout_user(): void
{
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
