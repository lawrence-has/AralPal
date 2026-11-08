<?php
/**
 * AralPal – User Registration
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-templates.php';

if (is_logged_in()) {
    $role = current_user()['role_name'];
    if ($role === 'Admin')      header('Location: /AralPal/admin/content.php');
    elseif ($role === 'Tutor')  header('Location: /AralPal/dashboard/tutor.php');
    else                        header('Location: /AralPal/dashboard/student.php');
    exit;
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        header('Location: /AralPal/auth/register.php');
        exit;
    }

    $full_name        = trim((string)($_POST['full_name']        ?? ''));
    $email            = trim((string)($_POST['email']            ?? ''));
    $password         = (string)($_POST['password']         ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');
    $role             = (string)($_POST['role']             ?? '');
    $hourly_rate      = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT) ?: 0.00;

    $old = ['full_name' => $full_name, 'email' => $email, 'role' => $role, 'hourly_rate' => $hourly_rate];

    if ($role === 'Tutor' && $hourly_rate < 0) $errors[] = 'Hourly rate must be a positive number.';

    if ($full_name === '')                              $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'A valid email address is required.';
    if (strlen($password) < 8)                         $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password_confirm)               $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['Student', 'Tutor'], true))  $errors[] = 'Please select a valid role.';

    if (empty($errors)) {
        $stmt = get_db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'An account with that email already exists.';
    }

    if (empty($errors)) {
        $stmt = get_db()->prepare('SELECT id FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$role]);
        $roleRow = $stmt->fetch();
        if (!$roleRow) {
            $errors[] = 'Selected role not found.';
        } else {
            $role_id       = (int)$roleRow['id'];
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = get_db()->prepare(
                'INSERT INTO users (role_id, full_name, email, password_hash, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$role_id, $full_name, $email, $password_hash, 'active']);
            $user_id = (int)get_db()->lastInsertId();

            if ($role === 'Tutor') {
                $stmt = get_db()->prepare('INSERT INTO tutors (user_id, bio, hourly_rate) VALUES (?, ?, ?)');
                $stmt->execute([$user_id, '', $hourly_rate]);
            }

            send_mail(
                $email,
                'Welcome to AralPal, ' . $full_name . '!',
                email_welcome($full_name)
            );

            header('Location: /AralPal/auth/login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – AralPal</title>
    <link rel="icon" type="image/png" href="/AralPal/assets/aralpal-logo.png">
    <link rel="stylesheet" href="/AralPal/assets/Style.css?v=20261104-realfix">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>(function(){var s=localStorage.getItem('ap-theme'),d=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.setAttribute('data-theme',(s?s==='dark':d)?'dark':'light')})();</script>
</head>
<body class="auth-body">

<a class="auth-back-link" href="/AralPal/index.php">
    <span>←</span> Back to AralPal
</a>

<div class="auth-split">

    <!-- ── Left branding panel ── -->
    <div class="auth-split__left">
        <a class="auth-split__logo" href="/AralPal/index.php">
            <img src="/AralPal/assets/aralpal-logo.png" alt="" width="48" height="48">
            <span>AralPal</span>
        </a>
        <div class="auth-split__tagline">
            <h1>Start Learning for Free.</h1>
            <p>Create your account in under a minute and access everything AralPal has to offer — no credit card needed.</p>
        </div>
        <div class="auth-split__features">
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-rocket" aria-hidden="true"></i></span>
                <span>Get started in seconds</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>
                <span>Student or Tutor account</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                <span>Secure &amp; private</span>
            </div>
            <div class="auth-feature">
                <span class="auth-feature__icon"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
                <span>Free forever for learners</span>
            </div>
        </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="auth-split__right">
        <div class="auth-form-card">

            <div class="auth-form-card__header">
                <div class="auth-form-card__icon"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></div>
                <h2>Create your account</h2>
                <p>Join AralPal and start learning today.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/AralPal/auth/register.php" novalidate class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">

                <!-- Role picker — shown first so user picks context -->
                <div class="form-group">
                    <label class="form-label">I am a…</label>
                    <div class="role-picker">
                        <label class="role-picker__option <?= (($old['role'] ?? '') === 'Student' || empty($old)) ? 'role-picker__option--active' : '' ?>" id="role-student-label">
                            <input
                                type="radio"
                                name="role"
                                value="Student"
                                <?= (($old['role'] ?? '') === 'Student' || empty($old)) ? 'checked' : '' ?>
                            >
                            <span class="role-picker__icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>
                            <div>
                                <strong>Student</strong>
                                <small>I want to learn</small>
                            </div>
                        </label>
                        <label class="role-picker__option <?= (($old['role'] ?? '') === 'Tutor') ? 'role-picker__option--active' : '' ?>" id="role-tutor-label">
                            <input
                                type="radio"
                                name="role"
                                value="Tutor"
                                <?= (($old['role'] ?? '') === 'Tutor') ? 'checked' : '' ?>
                            >
                            <span class="role-picker__icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                            <div>
                                <strong>Tutor</strong>
                                <small>I want to teach</small>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input
                        class="form-input"
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Juan dela Cruz"
                        required
                        autocomplete="name"
                        maxlength="120"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        class="form-input"
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="you@example.com"
                        required
                        autocomplete="email"
                        maxlength="190"
                    >
                </div>

                <!-- Hourly rate — shown only for Tutors -->
                <div class="form-group" id="hourly-rate-group" style="display:none">
                    <label class="form-label" for="hourly_rate">Hourly Rate (₱)</label>
                    <input
                        class="form-input"
                        type="number"
                        id="hourly_rate"
                        name="hourly_rate"
                        min="0"
                        step="0.01"
                        placeholder="e.g. 250.00"
                        value="<?= htmlspecialchars($old['hourly_rate'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-icon-wrap">
                            <input
                                class="form-input"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="At least 8 characters"
                                required
                                autocomplete="new-password"
                                minlength="8"
                            >
                            <button type="button" class="toggle-pw" aria-label="Show password" data-target="password"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirm Password</label>
                        <div class="input-icon-wrap">
                            <input
                                class="form-input"
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                placeholder="Repeat password"
                                required
                                autocomplete="new-password"
                                minlength="8"
                            >
                            <button type="button" class="toggle-pw" aria-label="Show confirm password" data-target="password_confirm"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Password strength bar -->
                <div class="pw-strength" id="pwStrength" aria-live="polite">
                    <div class="pw-strength__bar">
                        <div class="pw-strength__fill" id="pwFill"></div>
                    </div>
                    <span class="pw-strength__label" id="pwLabel"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-full auth-submit-btn">
                    Create Account →
                </button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <div class="auth-form-card__footer">
                <p>Already have an account? <a href="/AralPal/auth/login.php">Sign in</a></p>
            </div>

        </div>
    </div>

</div>

<script>
/* Password visibility toggle */
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

/* Role picker highlight */
document.querySelectorAll('.role-picker__option input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.role-picker__option').forEach(function(opt) {
            opt.classList.remove('role-picker__option--active');
        });
        if (radio.checked) {
            radio.closest('.role-picker__option').classList.add('role-picker__option--active');
        }
        // Show hourly rate field only for Tutors
        var rateGroup = document.getElementById('hourly-rate-group');
        if (rateGroup) rateGroup.style.display = radio.value === 'Tutor' ? 'block' : 'none';
    });
});

// Set initial state on page load
(function() {
    var checked = document.querySelector('.role-picker__option input[type="radio"]:checked');
    var rateGroup = document.getElementById('hourly-rate-group');
    if (checked && rateGroup) rateGroup.style.display = checked.value === 'Tutor' ? 'block' : 'none';
})();

/* Password strength meter */
var pwInput = document.getElementById('password');
var pwFill  = document.getElementById('pwFill');
var pwLabel = document.getElementById('pwLabel');
if (pwInput && pwFill && pwLabel) {
    pwInput.addEventListener('input', function() {
        var val = pwInput.value;
        var score = 0;
        if (val.length >= 8)                   score++;
        if (/[A-Z]/.test(val))                 score++;
        if (/[0-9]/.test(val))                 score++;
        if (/[^A-Za-z0-9]/.test(val))          score++;

        var levels = [
            { pct: '0%',   cls: '',           txt: '' },
            { pct: '25%',  cls: 'weak',        txt: 'Weak' },
            { pct: '50%',  cls: 'fair',        txt: 'Fair' },
            { pct: '75%',  cls: 'good',        txt: 'Good' },
            { pct: '100%', cls: 'strong',      txt: 'Strong' },
        ];
        var lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
        pwFill.style.width = lvl.pct;
        pwFill.className   = 'pw-strength__fill ' + lvl.cls;
        pwLabel.textContent = lvl.txt;
    });
}
</script>
</body>
</html>
