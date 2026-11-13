<?php
/**
 * AralPal – User Login
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    $role = current_user()['role_name'];
    if ($role === 'Admin')       header('Location: /AralPal/admin/content.php');
    elseif ($role === 'Tutor')   header('Location: /AralPal/dashboard/tutor.php');
    else                         header('Location: /AralPal/dashboard/student.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        header('Location: /AralPal/auth/login.php');
        exit;
    }

    $email    = trim((string)($_POST['email']    ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = get_db()->prepare(
        'SELECT u.id, u.full_name, u.email, u.password_hash, u.status, u.avatar, r.role_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Invalid email or password.';
    } elseif ($user['status'] !== 'active') {
        $error = 'Your account has been suspended. Please contact support.';
    } else {
        login_user([
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role_name' => $user['role_name'],
            'avatar'    => $user['avatar'] ?? null,
            'status'    => $user['status'],
        ]);
        if ($user['role_name'] === 'Admin')      header('Location: /AralPal/admin/content.php');
        elseif ($user['role_name'] === 'Tutor')  header('Location: /AralPal/dashboard/tutor.php');
        else                                     header('Location: /AralPal/dashboard/student.php');
        exit;
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
$reset      = isset($_GET['reset'])      && $_GET['reset']      === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – AralPal</title>
    <link rel="icon" type="image/png" href="/AralPal/assets/aralpal-logo.png">
    <link rel="stylesheet" href="/AralPal/assets/Style.css?v=20261104-realfix">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>(function(){var s=localStorage.getItem('ap-theme'),d=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.setAttribute('data-theme',(s?s==='dark':d)?'dark':'light')})();</script>
</head>
<body class="auth-body">

<!-- Floating back link -->
<a class="auth-back-link" href="/AralPal/index.php">
    <span>←</span> Back to AralPal
</a>

<div class="auth-split">

    <!-- ── Left panel: branding ── -->
    <div class="auth-split__left">
        <a class="auth-split__logo" href="/AralPal/index.php">
            <img src="/AralPal/assets/aralpal-logo.png" alt="" width="48" height="48">
            <span>AralPal</span>
        </a>
        <div class="auth-split__tagline">
            <h1>Learn Something New Today.</h1>
            <p>Join thousands of students already using AralPal to build real skills — for free.</p>
        </div>
        <div class="auth-split__features">
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-book" aria-hidden="true"></i></span>
                <span>Free self-paced lessons</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-pencil" aria-hidden="true"></i></span>
                <span>Interactive quizzes</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                <span>Expert tutors on demand</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                <span>Progress tracking</span>
            </div>
        </div>
    </div>

    <!-- ── Right panel: form ── -->
    <div class="auth-split__right">
        <div class="auth-form-card">

            <div class="auth-form-card__header">
                <div class="auth-form-card__icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></div>
                <h2>Welcome back</h2>
                <p>Sign in to continue your learning journey.</p>
            </div>

            <?php if ($registered): ?>
                <div class="alert alert-success" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Account created! You can now sign in.
                </div>
            <?php endif; ?>
            <?php if ($reset): ?>
                <div class="alert alert-success" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Password reset successfully. Sign in with your new password.
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/AralPal/auth/login.php" novalidate class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        class="form-input"
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        autocomplete="username"
                        maxlength="190"
                    >
                </div>

                <div class="form-group">
                    <div class="auth-label-row">
                        <label class="form-label" for="password">Password</label>
                        <a class="forgot-link" href="/AralPal/auth/forgot-password.php">Forgot password?</a>
                    </div>
                    <div class="input-icon-wrap">
                        <input
                            class="form-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-pw" aria-label="Show password" data-target="password"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full auth-submit-btn">
                    Sign In →
                </button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <div class="auth-form-card__footer">
                <p>Don't have an account? <a href="/AralPal/auth/register.php">Create one free</a></p>
            </div>

        </div>
    </div>

</div>

<script>
/* Theme */
const tt = document.getElementById && document.getElementById('themeToggle');
/* Password toggle */
document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(btn.dataset.target);
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});
</script>
</body>
</html>
