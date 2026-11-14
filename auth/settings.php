<?php
/**
 * AralPal – Account Settings
 *
 * Three independent POST forms:
 *   1. Update Display Name
 *   2. Update Email
 *   3. Change Password
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$user    = current_user();
$user_id = (int) $user['id'];
$errors  = [];

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check applies to every POST branch
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
           . '<title>403 Forbidden</title></head><body>'
           . '<h1>403 – Forbidden</h1>'
           . '<p>Invalid or missing security token. Please go back and try again.</p>'
           . '</body></html>';
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    // ── 1. Update Display Name ───────────────────────────────────────────────
    if ($action === 'update_name') {

        $full_name = trim(strip_tags((string) ($_POST['full_name'] ?? '')));

        if ($full_name === '') {
            $errors['name'] = 'Display name is required.';
        } elseif (mb_strlen($full_name) < 2) {
            $errors['name'] = 'Display name must be at least 2 characters.';
        } elseif (mb_strlen($full_name) > 100) {
            $errors['name'] = 'Display name must be 100 characters or fewer.';
        }

        if (empty($errors['name'])) {
            $db   = get_db();
            $stmt = $db->prepare('UPDATE users SET full_name = ? WHERE id = ?');
            $stmt->execute([$full_name, $user_id]);

            $_SESSION['user']['full_name'] = $full_name;

            flash('success', 'Display name updated successfully.');
            redirect('/AralPal/auth/settings.php');
        }
    }

    // ── 2. Update Email ──────────────────────────────────────────────────────
    elseif ($action === 'update_email') {

        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'] = 'Email address must be 255 characters or fewer.';
        }

        if (empty($errors['email'])) {
            $db       = get_db();
            $chk_stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk_stmt->execute([$email, $user_id]);

            if ($chk_stmt->fetch()) {
                $errors['email'] = 'Email is already in use.';
            } else {
                $upd_stmt = $db->prepare('UPDATE users SET email = ? WHERE id = ?');
                $upd_stmt->execute([$email, $user_id]);

                $_SESSION['user']['email'] = $email;

                flash('success', 'Email address updated successfully.');
                redirect('/AralPal/auth/settings.php');
            }
        }
    }

    // ── 3. Change Password ───────────────────────────────────────────────────
    elseif ($action === 'change_password') {

        $current_password  = (string) ($_POST['current_password']  ?? '');
        $new_password      = (string) ($_POST['new_password']       ?? '');
        $confirm_password  = (string) ($_POST['confirm_password']   ?? '');

        $db       = get_db();
        $pwd_stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $pwd_stmt->execute([$user_id]);
        $row = $pwd_stmt->fetch();

        if (!$row || !password_verify($current_password, $row['password'])) {
            $errors['password'] = 'Current password is incorrect.';
        } elseif (mb_strlen($new_password) < 8) {
            $errors['password'] = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $errors['password'] = 'New password and confirmation do not match.';
        }

        if (empty($errors['password'])) {
            $hashed   = password_hash($new_password, PASSWORD_DEFAULT);
            $upd_stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd_stmt->execute([$hashed, $user_id]);

            flash('success', 'Password changed successfully.');
            redirect('/AralPal/auth/settings.php');
        }
    }
}

// ── Render page ──────────────────────────────────────────────────────────────
$page_title = 'Account Settings';
require_once __DIR__ . '/../includes/app-header.php';
?>

<style>
/* ── Settings layout ──────────────────────────────────────────────────────── */
.settings-wrap {
    max-width: 640px;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.settings-wrap h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--clr-text);
    margin: 0 0 1.25rem;
}

/* Card */
.settings-card {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-lg, .75rem);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.settings-card__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--clr-text);
    margin: 0 0 1.25rem;
    padding-bottom: .75rem;
    border-bottom: 1px solid var(--clr-border);
    display: flex;
    align-items: center;
    gap: .5rem;
}

.settings-card__title i {
    color: var(--clr-primary);
}

/* Form fields */
.settings-field {
    margin-bottom: 1rem;
}

.settings-field label {
    display: block;
    font-size: .85rem;
    font-weight: 500;
    color: var(--clr-text);
    margin-bottom: .35rem;
}

.settings-field input {
    width: 100%;
    padding: .55rem .75rem;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md, .5rem);
    background: var(--clr-bg);
    color: var(--clr-text);
    font-size: .9rem;
    box-sizing: border-box;
    transition: border-color .15s;
}

.settings-field input:focus {
    outline: none;
    border-color: var(--clr-primary);
    box-shadow: 0 0 0 3px rgba(var(--clr-primary-rgb, 59, 130, 246), .15);
}

.settings-field input.is-invalid {
    border-color: #e53e3e;
}

/* Inline error below field */
.settings-field-error {
    font-size: .8rem;
    color: #e53e3e;
    margin-top: .3rem;
}

/* Flash messages */
.settings-flash {
    padding: .75rem 1rem;
    border-radius: var(--radius-md, .5rem);
    margin-bottom: 1rem;
    font-size: .9rem;
}

.settings-flash--success {
    background: #f0fff4;
    border: 1px solid #9ae6b4;
    color: #276749;
}

.settings-flash--error {
    background: #fff5f5;
    border: 1px solid #feb2b2;
    color: #c53030;
}

/* Submit button */
.settings-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: var(--clr-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md, .5rem);
    padding: .55rem 1.25rem;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s;
    margin-top: .25rem;
}

.settings-btn:hover {
    opacity: .88;
}
</style>

<div class="settings-wrap">
    <h1><i class="fa-solid fa-gear" aria-hidden="true"></i> Account Settings</h1>

    <?php
    // Global flash (success shown at top of page after redirect)
    $flash_success = get_flash('success');
    if ($flash_success !== null): ?>
        <div class="settings-flash settings-flash--success" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <?= e($flash_success) ?>
        </div>
    <?php endif; ?>

    <!-- ── Card 1: Update Display Name ──────────────────────────────────────── -->
    <div class="settings-card">
        <h2 class="settings-card__title">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            Display Name
        </h2>

        <?php if (!empty($errors['name'])): ?>
            <div class="settings-flash settings-flash--error" role="alert">
                <?= e($errors['name']) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/AralPal/auth/settings.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf()) ?>">
            <input type="hidden" name="action"     value="update_name">

            <div class="settings-field">
                <label for="full_name">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= e($_POST['full_name'] ?? $user['full_name'] ?? '') ?>"
                    maxlength="100"
                    required
                    autocomplete="name"
                    class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                >
            </div>

            <button type="submit" class="settings-btn">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save Name
            </button>
        </form>
    </div>

    <!-- ── Card 2: Update Email ──────────────────────────────────────────────── -->
    <div class="settings-card">
        <h2 class="settings-card__title">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            Email Address
        </h2>

        <?php if (!empty($errors['email'])): ?>
            <div class="settings-flash settings-flash--error" role="alert">
                <?= e($errors['email']) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/AralPal/auth/settings.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf()) ?>">
            <input type="hidden" name="action"     value="update_email">

            <div class="settings-field">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>"
                    maxlength="255"
                    required
                    autocomplete="email"
                    class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                >
            </div>

            <button type="submit" class="settings-btn">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save Email
            </button>
        </form>
    </div>

    <!-- ── Card 3: Change Password ───────────────────────────────────────────── -->
    <div class="settings-card">
        <h2 class="settings-card__title">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Change Password
        </h2>

        <?php if (!empty($errors['password'])): ?>
            <div class="settings-flash settings-flash--error" role="alert">
                <?= e($errors['password']) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/AralPal/auth/settings.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf()) ?>">
            <input type="hidden" name="action"     value="change_password">

            <div class="settings-field">
                <label for="current_password">Current Password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="<?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                >
            </div>

            <div class="settings-field">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                    class="<?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                >
            </div>

            <div class="settings-field">
                <label for="confirm_password">Confirm New Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                    class="<?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                >
            </div>

            <button type="submit" class="settings-btn">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                Change Password
            </button>
        </form>
    </div>

</div><!-- /.settings-wrap -->

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
