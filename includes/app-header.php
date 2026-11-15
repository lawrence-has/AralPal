<?php
/**
 * AralPal – Authenticated Application Header / Sidebar Partial
 *
 * Include this file at the top of every authenticated page.
 * Set $page_title before including to override the default title.
 *
 * Requirements:
 *   - Calls require_login(), so any page including it is automatically protected.
 *   - Renders a collapsible sidebar (not a top navbar).
 *   - Ends with an opening <main class="ap-main"> so page body follows naturally.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

$current             = current_user();
$_ap_unread_count    = get_unread_notification_count((int) ($current['id'] ?? 0));
$_ap_role            = strtolower((string) ($current['role_name'] ?? ''));
$_ap_current_uri     = $_SERVER['REQUEST_URI'] ?? '';

/**
 * Returns 'active' when $href appears anywhere in the current URI.
 */
function _ap_active(string $href, string $uri): string
{
    return (strpos($uri, $href) !== false) ? 'active' : '';
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
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    >

    <!-- Restore dark-mode preference before first paint (prevents FOUC) -->
    <script>
    (function () {
        var saved       = localStorage.getItem('ap-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var isDark      = saved ? saved === 'dark' : prefersDark;
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    })();
    </script>

    <style>
    /* ── App layout shell ──────────────────────────────────────────────────── */
    .ap-app-layout {
        display: flex;
        min-height: 100vh;
    }

    /* ── Sidebar ───────────────────────────────────────────────────────────── */
    .ap-sidebar {
        width: 240px;
        min-width: 240px;
        background: var(--clr-surface);
        border-right: 1px solid var(--clr-border);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 200;
        overflow-y: auto;
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition), width var(--transition);
    }

    .ap-sidebar__header {
        display: flex;
        align-items: center;
        gap: .625rem;
        padding: 1.25rem 1rem 1rem;
        border-bottom: 1px solid var(--clr-border);
        text-decoration: none;
        color: var(--clr-text);
    }

    .ap-sidebar__logo {
        width: 36px;
        height: 36px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .ap-sidebar__brand {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--clr-primary);
        white-space: nowrap;
        overflow: hidden;
    }

    /* User info block */
    .ap-sidebar__user {
        padding: .875rem 1rem;
        border-bottom: 1px solid var(--clr-border);
    }

    .ap-sidebar__user-name {
        font-weight: 600;
        font-size: .9rem;
        color: var(--clr-text);
        margin: 0 0 .25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ap-sidebar__user-role {
        display: inline-block;
        font-size: .7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: .15em .5em;
        border-radius: var(--radius-md);
        background: var(--clr-primary);
        color: #fff;
    }

    /* Navigation list */
    .ap-sidebar__nav {
        flex: 1;
        padding: .5rem 0;
        list-style: none;
        margin: 0;
    }

    .ap-sidebar__nav li {
        margin: 0;
    }

    .ap-sidebar__nav a {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .625rem 1rem;
        font-size: .875rem;
        color: var(--clr-text);
        text-decoration: none;
        border-radius: 0;
        transition: background var(--transition), color var(--transition);
        position: relative;
    }

    .ap-sidebar__nav a:hover {
        background: var(--clr-bg);
        color: var(--clr-primary);
    }

    .ap-sidebar__nav a.active {
        background: var(--clr-bg);
        color: var(--clr-primary);
        font-weight: 600;
    }

    .ap-sidebar__nav a.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--clr-primary);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
    }

    .ap-sidebar__nav .nav-icon {
        width: 1.1rem;
        text-align: center;
        flex-shrink: 0;
        color: var(--clr-muted);
        transition: color var(--transition);
    }

    .ap-sidebar__nav a:hover  .nav-icon,
    .ap-sidebar__nav a.active .nav-icon {
        color: var(--clr-primary);
    }

    /* Notification badge inside nav */
    .ap-nav-badge {
        margin-left: auto;
        background: #e53e3e;
        color: #fff;
        font-size: .65rem;
        font-weight: 700;
        min-width: 1.2em;
        height: 1.2em;
        line-height: 1.2em;
        padding: 0 .35em;
        border-radius: 9999px;
        text-align: center;
    }

    /* Divider line inside nav */
    .ap-sidebar__divider {
        height: 1px;
        background: var(--clr-border);
        margin: .375rem .75rem;
    }

    /* Sidebar footer controls */
    .ap-sidebar__footer {
        padding: .75rem 1rem;
        border-top: 1px solid var(--clr-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .ap-sidebar__footer-btn {
        background: none;
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        color: var(--clr-muted);
        cursor: pointer;
        padding: .35rem .6rem;
        font-size: .8rem;
        transition: background var(--transition), color var(--transition);
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    .ap-sidebar__footer-btn:hover {
        background: var(--clr-bg);
        color: var(--clr-text);
    }

    /* ── Hamburger (mobile) ────────────────────────────────────────────────── */
    .ap-hamburger {
        display: none;
        position: fixed;
        top: .75rem;
        left: .75rem;
        z-index: 300;
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        padding: .45rem .6rem;
        cursor: pointer;
        color: var(--clr-text);
        box-shadow: var(--shadow-sm);
        line-height: 1;
    }

    /* Overlay backdrop (mobile) */
    .ap-sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 199;
    }

    .ap-sidebar-overlay.visible {
        display: block;
    }

    /* ── Main content area ─────────────────────────────────────────────────── */
    .ap-main {
        flex: 1;
        margin-left: 240px;
        min-width: 0;
        padding: 1.5rem;
        background: var(--clr-bg);
        min-height: 100vh;
    }

    /* ── App footer ────────────────────────────────────────────────────────── */
    .ap-app-footer {
        margin-left: 240px;
        padding: .75rem 1.5rem;
        font-size: .8rem;
        color: var(--clr-muted);
        border-top: 1px solid var(--clr-border);
        background: var(--clr-surface);
    }

    /* ── Responsive ────────────────────────────────────────────────────────── */
    @media (max-width: 768px) {
        .ap-hamburger {
            display: flex;
            align-items: center;
        }

        .ap-sidebar {
            transform: translateX(-100%);
        }

        .ap-sidebar.open {
            transform: translateX(0);
        }

        .ap-main {
            margin-left: 0;
            padding: 1rem;
            padding-top: 3.5rem; /* clear the hamburger button */
        }

        .ap-app-footer {
            margin-left: 0;
        }
    }
    </style>
</head>
<body>

<!-- Mobile hamburger button -->
<button
    class="ap-hamburger"
    id="apHamburger"
    aria-label="Toggle sidebar"
    aria-expanded="false"
    aria-controls="apSidebar"
>
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
</button>

<!-- Overlay backdrop (mobile) -->
<div class="ap-sidebar-overlay" id="apSidebarOverlay" aria-hidden="true"></div>

<div class="ap-app-layout">

    <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
    <aside class="ap-sidebar" id="apSidebar" aria-label="Application navigation">

        <!-- Logo / brand -->
        <a class="ap-sidebar__header" href="/AralPal/index.php" aria-label="AralPal home">
            <img
                class="ap-sidebar__logo"
                src="/AralPal/assets/aralpal-logo.png"
                alt="AralPal logo"
                width="36"
                height="36"
            >
            <span class="ap-sidebar__brand">AralPal</span>
        </a>

        <!-- User info -->
        <div class="ap-sidebar__user">
            <p class="ap-sidebar__user-name">
                <?= htmlspecialchars($current['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <span class="ap-sidebar__user-role">
                <?= htmlspecialchars($current['role_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <!-- Navigation links -->
        <ul class="ap-sidebar__nav" role="list">

            <?php if ($_ap_role === 'student'): ?>

                <!-- ── Student navigation ── -->
                <li>
                    <a href="/AralPal/dashboard/student.php"
                       class="<?= _ap_active('/dashboard/student.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-gauge" aria-hidden="true"></i></span>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="/AralPal/tutors/index.php"
                       class="<?= _ap_active('/tutors/index.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                        Find Tutors
                    </a>
                </li>
                <li>
                    <a href="/AralPal/booking/my-bookings.php"
                       class="<?= _ap_active('/booking/my-bookings.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                        My Bookings
                    </a>
                </li>
                <li>
                    <a href="/AralPal/pages/learn/index.php"
                       class="<?= _ap_active('/pages/learn/', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                        Learning Center
                    </a>
                </li>
                <li>
                    <a href="/AralPal/notifications/index.php"
                       class="<?= _ap_active('/notifications/index.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                        Notifications
                        <?php if ($_ap_unread_count > 0): ?>
                            <span class="ap-nav-badge" aria-label="<?= $_ap_unread_count ?> unread">
                                <?= $_ap_unread_count > 99 ? '99+' : $_ap_unread_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li><div class="ap-sidebar__divider"></div></li>

                <li>
                    <a href="/AralPal/subscriptions/plans.php"
                       class="<?= _ap_active('/subscriptions/plans.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-star" aria-hidden="true"></i></span>
                        My Plan
                    </a>
                </li>
                <li>
                    <a href="/AralPal/auth/settings.php"
                       class="<?= _ap_active('/auth/settings.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></span>
                        Account Settings
                    </a>
                </li>
                <li>
                    <a href="/AralPal/auth/logout.php">
                        <span class="nav-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></span>
                        Logout
                    </a>
                </li>

            <?php elseif ($_ap_role === 'tutor'): ?>

                <!-- ── Tutor navigation ── -->
                <li>
                    <a href="/AralPal/dashboard/tutor.php"
                       class="<?= _ap_active('/dashboard/tutor.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-gauge" aria-hidden="true"></i></span>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="/AralPal/booking/my-bookings.php"
                       class="<?= _ap_active('/booking/my-bookings.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                        My Bookings
                    </a>
                </li>
                <li>
                    <a href="/AralPal/tutors/edit-profile.php"
                       class="<?= _ap_active('/tutors/edit-profile.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
                        Edit Profile
                    </a>
                </li>
                <li>
                    <a href="/AralPal/tutors/availability.php"
                       class="<?= _ap_active('/tutors/availability.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
                        Manage Availability
                    </a>
                </li>

                <li><div class="ap-sidebar__divider"></div></li>

                <li>
                    <a href="/AralPal/subscriptions/plans.php"
                       class="<?= _ap_active('/subscriptions/plans.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-star" aria-hidden="true"></i></span>
                        My Plan
                    </a>
                </li>
                <li>
                    <a href="/AralPal/notifications/index.php"
                       class="<?= _ap_active('/notifications/index.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                        Notifications
                        <?php if ($_ap_unread_count > 0): ?>
                            <span class="ap-nav-badge" aria-label="<?= $_ap_unread_count ?> unread">
                                <?= $_ap_unread_count > 99 ? '99+' : $_ap_unread_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="/AralPal/auth/settings.php"
                       class="<?= _ap_active('/auth/settings.php', $_ap_current_uri) ?>">
                        <span class="nav-icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></span>
                        Account Settings
                    </a>
                </li>
                <li>
                    <a href="/AralPal/auth/logout.php">
                        <span class="nav-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></span>
                        Logout
                    </a>
                </li>

            <?php else: ?>

                <!-- ── Fallback for unexpected roles ── -->
                <li>
                    <a href="/AralPal/auth/logout.php">
                        <span class="nav-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></span>
                        Logout
                    </a>
                </li>

            <?php endif; ?>

        </ul><!-- /.ap-sidebar__nav -->

        <!-- Sidebar footer: dark-mode toggle -->
        <div class="ap-sidebar__footer">
            <button
                id="apThemeToggle"
                class="ap-sidebar__footer-btn"
                aria-label="Toggle dark mode"
                title="Toggle dark mode"
            >
                <i class="fa-solid fa-moon" aria-hidden="true" id="apThemeIcon"></i>
                <span id="apThemeLabel">Dark mode</span>
            </button>
        </div>

    </aside><!-- /.ap-sidebar -->

    <!-- Page body inserted here by consuming pages -->
<main class="ap-main">

<script>
(function () {
    'use strict';

    /* ── Dark-mode toggle ──────────────────────────────────────────────── */
    var toggle    = document.getElementById('apThemeToggle');
    var themeIcon = document.getElementById('apThemeIcon');
    var themeLabel = document.getElementById('apThemeLabel');

    function applyTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
        if (themeIcon)  themeIcon.className  = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        if (themeLabel) themeLabel.textContent = dark ? 'Light mode' : 'Dark mode';
        if (toggle) toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }

    /* Sync icon on load */
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');

    if (toggle) {
        toggle.addEventListener('click', function () {
            applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark');
        });
    }

    /* ── Sidebar toggle (mobile) ───────────────────────────────────────── */
    var hamburger = document.getElementById('apHamburger');
    var sidebar   = document.getElementById('apSidebar');
    var overlay   = document.getElementById('apSidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) { overlay.classList.add('visible'); overlay.setAttribute('aria-hidden', 'false'); }
        if (hamburger) {
            hamburger.setAttribute('aria-expanded', 'true');
            var icon = hamburger.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-xmark';
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) { overlay.classList.remove('visible'); overlay.setAttribute('aria-hidden', 'true'); }
        if (hamburger) {
            hamburger.setAttribute('aria-expanded', 'false');
            var icon = hamburger.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-bars';
        }
    }

    if (hamburger) hamburger.addEventListener('click', function () {
        sidebar && sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    if (overlay) overlay.addEventListener('click', closeSidebar);

    /* Close sidebar when a nav link is tapped on mobile */
    if (sidebar) {
        sidebar.querySelectorAll('.ap-sidebar__nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });
    }

    /* ── Active-link highlighting ──────────────────────────────────────── */
    /* PHP already sets .active on the server side; this JS pass adds it
       for any links whose href matches a path segment of the current URL,
       without removing PHP-set classes. */
    var currentPath = window.location.pathname;
    if (sidebar) {
        sidebar.querySelectorAll('.ap-sidebar__nav a[href]').forEach(function (a) {
            var href = a.getAttribute('href');
            if (href && href !== '#' && currentPath.indexOf(href.split('?')[0]) !== -1) {
                a.classList.add('active');
            }
        });
    }

})();
</script>
