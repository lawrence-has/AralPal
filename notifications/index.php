<?php
/**
 * AralPal – Notifications
 *
 * Displays all notifications for the logged-in user.
 * POST action=mark_all_read : marks every notification as read, then redirects.
 * POST action=mark_read&id=X: marks one notification as read, returns JSON.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    // Both POST actions require a valid CSRF token
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        if ($action === 'mark_read') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }
        redirect('/AralPal/notifications/index.php');
    }

    // Mark all read
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$user_id]);
        redirect('/AralPal/notifications/index.php');
    }

    // Mark single notification as read (AJAX)
    if ($action === 'mark_read') {
        header('Content-Type: application/json; charset=utf-8');
        $notif_id = (int) ($_POST['id'] ?? 0);
        if ($notif_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID.']);
            exit;
        }
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notif_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Unknown action – redirect back
    redirect('/AralPal/notifications/index.php');
}

// ── Fetch notifications ───────────────────────────────────────────────────────
$notif_stmt = $db->prepare(
    'SELECT id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll();

// ── Helpers ───────────────────────────────────────────────────────────────────
/**
 * Returns a human-readable relative time string for a datetime.
 *
 * @param  string $datetime MySQL datetime string
 * @return string e.g. 'just now', '5 minutes ago', '2 hours ago', '3 days ago'
 */
function time_ago(string $datetime): string
{
    $ts   = strtotime($datetime);
    $diff = max(0, time() - $ts);

    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff / 60)    . ' minute'  . (floor($diff / 60)   === 1 ? '' : 's') . ' ago';
    if ($diff < 86400)      return floor($diff / 3600)  . ' hour'    . (floor($diff / 3600)  === 1 ? '' : 's') . ' ago';
    if ($diff < 7 * 86400)  return floor($diff / 86400) . ' day'     . (floor($diff / 86400) === 1 ? '' : 's') . ' ago';
    if ($diff < 30 * 86400) return floor($diff / 604800) . ' week'   . (floor($diff / 604800) === 1 ? '' : 's') . ' ago';
    if ($diff < 365 * 86400) return floor($diff / 2592000) . ' month' . (floor($diff / 2592000) === 1 ? '' : 's') . ' ago';
    return floor($diff / 31536000) . ' year' . (floor($diff / 31536000) === 1 ? '' : 's') . ' ago';
}

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title  = 'Notifications';
$csrf_token  = generate_csrf();
$unread_count = count(array_filter($notifications, fn($n) => !(int) $n['is_read']));

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container notif-page">

    <!-- ── Page header ───────────────────────────────────────────────── -->
    <div class="notif-page__header">
        <div>
            <h1 class="notif-page__title">Notifications</h1>
            <?php if ($unread_count > 0): ?>
                <p class="notif-page__subtitle muted">
                    You have <strong><?= $unread_count ?></strong> unread
                    notification<?= $unread_count !== 1 ? 's' : '' ?>.
                </p>
            <?php else: ?>
                <p class="notif-page__subtitle muted">All caught up!</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($notifications)): ?>
            <!-- Mark all read form -->
            <form method="post" action="/AralPal/notifications/index.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action"     value="mark_all_read">
                <button type="submit" class="btn btn-outline btn-sm">
                    ✓ Mark All Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ── Notification list ─────────────────────────────────────────── -->
    <?php if (empty($notifications)): ?>
        <div class="notif-empty" role="status">
            <span class="notif-empty__icon" aria-hidden="true">🔔</span>
            <p>You have no notifications yet.</p>
        </div>
    <?php else: ?>
        <ul class="notif-list" role="list" id="notif-list">
            <?php foreach ($notifications as $notif): ?>
                <?php
                $nid     = (int) $notif['id'];
                $is_read = (bool) (int) $notif['is_read'];
                $ago     = time_ago((string) $notif['created_at']);
                ?>
                <li
                    class="notification-item<?= $is_read ? '' : ' notification-item--unread' ?>"
                    id="notif-item-<?= $nid ?>"
                    data-id="<?= $nid ?>"
                >
                    <!-- Unread indicator dot -->
                    <?php if (!$is_read): ?>
                        <span
                            class="notif-dot"
                            aria-label="Unread"
                            title="Unread"
                        ></span>
                    <?php else: ?>
                        <span class="notif-dot notif-dot--read" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="notif-body">
                        <p class="notif-msg">
                            <?= htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <time
                            class="notif-time muted"
                            datetime="<?= htmlspecialchars($notif['created_at'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?= htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </div>

                    <?php if (!$is_read): ?>
                        <!-- AJAX mark-read button -->
                        <button
                            type="button"
                            class="btn btn-xs btn-outline notif-mark-read-btn"
                            data-notif-id="<?= $nid ?>"
                            data-csrf="<?= e($csrf_token) ?>"
                            aria-label="Mark notification as read"
                        >
                            Mark read
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>

<style>
/* ── Notifications page styles ─────────────────────────────────────────────── */
.notif-page {
    max-width: 680px;
    padding: 2rem clamp(1rem, 4vw, 2rem);
}

/* Page header */
.notif-page__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.75rem;
}
.notif-page__title {
    font-size: 1.6rem;
    font-weight: 700;
    margin: 0 0 .25rem;
}
.notif-page__subtitle { margin: 0; font-size: .9rem; }

/* Empty state */
.notif-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .75rem;
    padding: 3rem 1rem;
    color: var(--clr-muted);
    text-align: center;
}
.notif-empty__icon { font-size: 2.5rem; }

/* Notification list */
.notif-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

/* Each notification item */
.notification-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.1rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    transition: background var(--transition), box-shadow var(--transition);
}
.notification-item--unread {
    background: var(--clr-primary-light, #eff6ff);
    border-left: 3px solid var(--clr-primary, #3b82f6);
}
.notification-item:hover {
    box-shadow: var(--shadow-sm);
}

/* Dot indicator */
.notif-dot {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--clr-primary, #3b82f6);
}
.notif-dot--read {
    background: transparent;
}

/* Body */
.notif-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: .2rem;
}
.notif-msg {
    font-size: .92rem;
    line-height: 1.5;
    margin: 0;
    word-break: break-word;
}
.notif-time {
    font-size: .78rem;
}

/* Mark read button – fades out when done */
.notif-mark-read-btn {
    flex-shrink: 0;
    opacity: 1;
    transition: opacity .3s ease;
}
.notif-mark-read-btn.notif-mark-read-btn--done {
    opacity: 0;
    pointer-events: none;
}

/* Button helpers */
.btn { display: inline-block; padding: .55rem 1.1rem; border-radius: var(--radius-sm); font-weight: 600; text-decoration: none; cursor: pointer; font-size: .9rem; border: 2px solid transparent; transition: background var(--transition), color var(--transition), border-color var(--transition); }
.btn-sm  { padding: .35rem .85rem; font-size: .82rem; }
.btn-xs  { padding: .2rem .55rem; font-size: .76rem; }
.btn-outline { background: transparent; border-color: var(--clr-primary, #3b82f6); color: var(--clr-primary, #3b82f6); }
.btn-outline:hover { background: var(--clr-primary, #3b82f6); color: #fff; }

/* Utility */
.muted { color: var(--clr-muted); }
</style>

<script>
(function () {
    'use strict';

    // ── AJAX mark-single-read ─────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.notif-mark-read-btn');
        if (!btn) return;

        var notifId = parseInt(btn.dataset.notifId, 10);
        var csrf    = btn.dataset.csrf;
        var item    = document.getElementById('notif-item-' + notifId);

        if (!notifId || !csrf) return;

        btn.disabled = true;

        var body = new URLSearchParams();
        body.append('action',     'mark_read');
        body.append('id',         String(notifId));
        body.append('csrf_token', csrf);

        fetch('/AralPal/notifications/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) {
                btn.disabled = false;
                return;
            }

            // Visual: remove unread styling, hide dot and button
            if (item) {
                item.classList.remove('notification-item--unread');
                var dot = item.querySelector('.notif-dot');
                if (dot) dot.classList.add('notif-dot--read');
            }

            btn.classList.add('notif-mark-read-btn--done');
            setTimeout(function () { btn.remove(); }, 350);

            // Decrement the badge count in the navbar
            var badge = document.querySelector('.notif-badge');
            if (badge) {
                var count = parseInt(badge.textContent, 10) - 1;
                if (count <= 0) {
                    badge.remove();
                } else {
                    badge.textContent = count > 99 ? '99+' : String(count);
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
