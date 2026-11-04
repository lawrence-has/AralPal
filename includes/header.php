<?php
/**
 * AralPal – Site Header / Navbar Partial
 *
 * Set $page_title before including this file to override the default title.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$_ap_user          = current_user();
$_ap_unread_count  = 0;

if (is_logged_in() && isset($_ap_user['id'])) {
    $_ap_unread_count = get_unread_notification_count((int) $_ap_user['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'AralPal', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="/AralPal/assets/aralpal-logo.png">
    <link rel="stylesheet" href="/AralPal/assets/Style.css?v=20261104-realfix">
</head>
<body>

<nav class="navbar" id="navbar">

    <!-- Logo -->
    <a class="logo logo-brand" href="/AralPal/index.php" aria-label="AralPal home"><img src="/AralPal/assets/aralpal-logo.png" alt="" class="brand-logo" width="34" height="34"><span>AralPal</span></a>

    <!-- Primary nav links -->
    <ul class="nav-links">
        <li><a href="/AralPal/index.php">Home</a></li>
        <li><a href="/AralPal/tutors/index.php">Find a Tutor</a></li>
        <li><a href="/AralPal/subscriptions/plans.php">Plans</a></li>
    </ul>

    <!-- Right-side controls -->
    <div class="nav-actions">

        <!-- Dark-mode toggle -->
        <button
            id="themeToggle"
            class="theme-toggle"
            aria-label="Toggle dark mode"
            title="Toggle dark mode"
        >🌙</button>

        <?php if (is_logged_in()): ?>

            <!-- Notification bell -->
            <a
                class="notif-bell"
                href="/AralPal/notifications/index.php"
                aria-label="Notifications<?= $_ap_unread_count ? ' (' . $_ap_unread_count . ' unread)' : '' ?>"
            >
                🔔
                <?php if ($_ap_unread_count > 0): ?>
                    <span class="notif-badge" aria-hidden="true">
                        <?= $_ap_unread_count > 99 ? '99+' : $_ap_unread_count ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- User dropdown -->
            <div class="user-dropdown">
                <button class="user-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                    <?= htmlspecialchars($_ap_user['full_name'] ?? 'Account', ENT_QUOTES, 'UTF-8') ?>
                    <span class="role-badge"><?= htmlspecialchars($_ap_user['role_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    ▾
                </button>
                <ul class="user-dropdown-menu" role="menu">
                    <?php
                    $dashboardPage = (strtolower((string) ($_ap_user['role_name'] ?? '')) === 'tutor') ? 'tutor.php' : 'student.php';
                    ?>
                    <li role="menuitem">
                        <a href="/AralPal/dashboard/<?= $dashboardPage ?>">Dashboard</a>
                    </li>
                    <li role="menuitem">
                        <a href="/AralPal/auth/logout.php">Logout</a>
                    </li>
                </ul>
            </div>

        <?php else: ?>

            <!-- Guest buttons -->
            <a class="btn btn-outline" href="/AralPal/auth/login.php">Login</a>
            <a class="btn btn-primary" href="/AralPal/auth/register.php">Register</a>

        <?php endif; ?>

    </div><!-- /.nav-actions -->

</nav><!-- /.navbar -->

<!-- Dark-mode: restore saved preference before first paint (no FOUC) -->
<script>
(function () {
    var saved = localStorage.getItem('ap-theme');
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var isDark = saved ? saved === 'dark' : prefersDark;
    /* Use data-theme attribute so CSS [data-theme="dark"] selectors work */
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
})();

document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    function applyTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        toggle.textContent = dark ? '☀️' : '🌙';
        toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
    }

    /* Sync icon with current state on load */
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');

    toggle.addEventListener('click', function () {
        applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark');
    });

    /* User dropdown */
    var dropToggle = document.querySelector('.user-dropdown-toggle');
    var dropMenu   = document.querySelector('.user-dropdown-menu');
    if (dropToggle && dropMenu) {
        dropToggle.addEventListener('click', function () {
            var open = dropToggle.getAttribute('aria-expanded') === 'true';
            dropToggle.setAttribute('aria-expanded', String(!open));
            dropMenu.classList.toggle('open', !open);
        });
        document.addEventListener('click', function (e) {
            if (!dropToggle.contains(e.target) && !dropMenu.contains(e.target)) {
                dropToggle.setAttribute('aria-expanded', 'false');
                dropMenu.classList.remove('open');
            }
        });
    }

    /* Navbar scroll shadow */
    var navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        }, { passive: true });
    }
});
</script>
