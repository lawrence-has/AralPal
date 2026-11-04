<?php
declare(strict_types=1);
require_once __DIR__ . '/cms-config.php';

$count = (int)db()->query('SELECT COUNT(*) FROM cms_admins')->fetchColumn();
$message = '';
$error = '';
if ($count > 0) {
    http_response_code(403);
    exit('An admin account already exists. Delete setup-admin.php and use login.php.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $error = 'Enter a name, valid email, and password with at least 10 characters.';
    } else {
        $stmt = db()->prepare('INSERT INTO cms_admins (full_name, email, password_hash) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $message = 'Admin account created. Delete setup-admin.php now, then sign in.';
        } catch (PDOException $ex) {
            $error = 'Could not create account. Check whether the email is already used.';
        }
    }
}
$pageTitle = 'Create First Admin';
$loginPage = true;
require __DIR__ . '/partials/header.php';
?>
<div class="login-shell"><form class="login-card" method="post">
  <div class="brand-mark">A</div><p class="eyebrow">ONE-TIME SETUP</p><h1>Create admin account</h1>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>Full name<input name="full_name" required maxlength="120"></label>
  <label>Email address<input type="email" name="email" required maxlength="190"></label>
  <label>Password (minimum 10 characters)<input type="password" name="password" required minlength="10"></label>
  <button class="button full" type="submit">Create admin</button>
  <p class="small muted">After successful setup, remove this file from the admin folder.</p>
</form></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
