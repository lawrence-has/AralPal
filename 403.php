<?php
/**
 * AralPal – 403 Forbidden
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

http_response_code(403);
$pageTitle = '403 – Access Denied';
include __DIR__ . '/includes/header.php';
?>

<main class="error-page">
    <div class="error-container">
        <div class="error-icon" aria-hidden="true">🚫</div>
        <h1>Access Denied</h1>
        <p class="error-code">403 Forbidden</p>
        <p class="error-message">
            You don't have permission to view this page.
            Please make sure you are logged in with the correct account.
        </p>
        <div class="error-actions">
            <a class="primary-btn" href="<?= e(SITE_URL) ?>/index.php">Go to Homepage</a>
            <?php if (!is_logged_in()): ?>
                <a class="outline-btn" href="<?= e(SITE_URL) ?>/auth/login.php">Login</a>
            <?php else: ?>
                <?php $u = current_user(); ?>
                <a class="outline-btn"
                   href="<?= e(SITE_URL) ?>/dashboard/<?= e($u['role'] === 'tutor' ? 'tutor' : 'student') ?>.php">
                    My Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
