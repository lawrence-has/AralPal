<?php
declare(strict_types=1);
require_once __DIR__ . '/cms-config.php';

if (!empty($_SESSION['cms_admin_id'])) {
    redirect('index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, full_name, email, password_hash FROM cms_admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['cms_admin_id'] = (int)$admin['id'];
        $_SESSION['cms_admin_name'] = $admin['full_name'];
        $_SESSION['cms_admin_email'] = $admin['email'];
        redirect('index.php');
    }
    $error = 'Invalid email or password.';
}
$pageTitle = 'Admin Login';
$loginPage = true;
require __DIR__ . '/partials/header.php';
?>
<div class="login-shell">
  <form class="login-card" method="post">
    <div class="brand-mark">A</div>
    <p class="eyebrow">ARALPAL CMS</p>
    <h1>Admin sign in</h1>
    <p class="muted">Sign in to manage website content.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Email address<input type="email" name="email" autocomplete="username" required maxlength="190"></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="button full" type="submit">Sign in</button>
    <p class="small muted">First time? Use the one-time setup page to create the admin account, then remove that file.</p>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
