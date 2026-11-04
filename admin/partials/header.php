<?php
$loginPage = $loginPage ?? false;
$pageTitle = $pageTitle ?? 'Admin';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | AralPal CMS</title>
<link rel="stylesheet" href="assets/admin.css">
<link rel="icon" type="image/png" href="../assets/aralpal-logo.png">
</head>
<body class="<?= $loginPage ? 'login-page' : '' ?>">
<?php if (!$loginPage && !empty($_SESSION['cms_admin_id'])): ?>
<div class="app-shell">
<aside class="sidebar">
  <a class="brand" href="index.php"><img class="admin-brand-logo" src="../assets/aralpal-logo.png" alt="AralPal logo" width="40" height="40"><span><strong>AralPal</strong><small>CONTENT STUDIO</small></span></a>
  <p class="nav-label">WORKSPACE</p>
  <nav>
    <a class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php"><span>▦</span> Dashboard</a>
    <a class="<?= basename($_SERVER['PHP_SELF']) === 'content.php' ? 'active' : '' ?>" href="content.php"><span>▤</span> Website content</a>
    <a class="<?= basename($_SERVER['PHP_SELF']) === 'announcements.php' ? 'active' : '' ?>" href="announcements.php"><span>◉</span> Announcements</a>
  </nav>
  <div class="sidebar-bottom"><div class="avatar"><?= e(strtoupper(substr($_SESSION['cms_admin_name'] ?? 'A', 0, 1))) ?></div><div class="admin-mini"><strong><?= e($_SESSION['cms_admin_name'] ?? 'Admin') ?></strong><small>Administrator</small></div><a class="logout" href="logout.php" title="Log out">↗</a></div>
</aside>
<main class="main-area"><header class="topbar"><span>AralPal / <?= e($pageTitle) ?></span><span class="online-dot">● <span>Admin workspace</span></span></header><div class="content-area">
<?php else: ?>
<main class="login-main">
<?php endif; ?>
