<?php
/**
 * AralPal – Reset Password
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$token_value  = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$token_error  = false;
$reset_done   = false;
$errors       = [];
$reset_email  = '';

/**
 * Look up a valid, unexpired token.
 *
 * @return array|false The password_resets row or false
 */
function lookup_token(string $token): array|false
{
    if ($token === '') {
        return false;
    }
    $stmt = get_db()->prepare(
        'SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        header('Location: /AralPal/auth/forgot-password.php');
        exit;
    }

    $post_token       = trim((string)($_POST['token'] ?? ''));
    $password         = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    // Re-validate token
    $row = lookup_token($post_token);
    if (!$row) {
        $token_error = true;
    } else {
        // 2. Validate passwords
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // 3. Update user password
            $stmt = get_db()->prepare(
                'UPDATE users SET password_hash = ? WHERE email = ?'
            );
            $stmt->execute([$password_hash, $row['email']]);

            // 4. Delete the used token
            $stmt = get_db()->prepare('DELETE FROM password_resets WHERE token = ?');
            $stmt->execute([$post_token]);

            // 5. Redirect
            header('Location: /AralPal/auth/login.php?reset=1');
            exit;
        }

        // Keep token in view for re-submit
        $token_value = $post_token;
    }
} else {
    // GET – validate token before showing form
    $row = lookup_token($token_value);
    if (!$row) {
        $token_error = true;
    }
}

$page_title = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="auth-main">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="auth-icon">🔐</span>
            <h1>Reset Password</h1>
            <p class="auth-sub">Enter and confirm your new password.</p>
        </div>

        <?php if ($token_error): ?>
            <div class="alert alert-error" role="alert">
                This reset link is invalid or has expired.
                <a href="/AralPal/auth/forgot-password.php">Request a new one.</a>
            </div>

        <?php else: ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/AralPal/auth/reset-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token_value, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-icon-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="At least 8 characters"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <button type="button" class="toggle-pw" aria-label="Show password" data-target="password">👁</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm New Password</label>
                    <div class="input-icon-wrap">
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            placeholder="Repeat your new password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <button type="button" class="toggle-pw" aria-label="Show confirm password" data-target="password_confirm">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Set New Password</button>
            </form>

        <?php endif; ?>

        <p class="auth-footer-link">
            <a href="/AralPal/auth/login.php">← Back to login</a>
        </p>
    </div>
</main>

<script>
document.querySelectorAll('.toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        if (!input) return;
        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.textContent = isPassword ? '🙈' : '👁';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
