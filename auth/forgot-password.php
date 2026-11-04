<?php
/**
 * AralPal – Forgot Password
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        header('Location: /AralPal/auth/forgot-password.php');
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));

    // 2. Look up user – act silently whether found or not
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = get_db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate token and expiry
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 3600);

            // 3. Replace any existing reset row for this email
            $stmt = get_db()->prepare('DELETE FROM password_resets WHERE email = ?');
            $stmt->execute([$email]);

            $stmt = get_db()->prepare(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$email, $token, $expires_at]);

            // 4. Build reset URL
            $reset_url = SITE_URL . '/auth/reset-password.php?token=' . urlencode($token);

            // 5. Send email
            send_mail(
                $email,
                'AralPal Password Reset',
                '<p>Click the link below to reset your password. This link expires in 1 hour.</p>'
                . '<p><a href="' . htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8')
                . '</a></p>'
            );
        }
    }

    // 6. Always show success (do not reveal whether email exists)
    $sent = true;
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="auth-main">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="auth-icon">🔒</span>
            <h1>Forgot Password</h1>
            <p class="auth-sub">Enter your email and we'll send a reset link.</p>
        </div>

        <?php if ($sent): ?>
            <div class="alert alert-success" role="status">
                If that email is registered, a reset link has been sent. Check your inbox.
            </div>
        <?php else: ?>
            <form method="post" action="/AralPal/auth/forgot-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        autocomplete="email"
                        maxlength="190"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-full">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <p class="auth-footer-link">
            <a href="/AralPal/auth/login.php">← Back to login</a>
        </p>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
