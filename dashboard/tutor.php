<?php
/**
 * AralPal – Tutor Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_role('Tutor');

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Tutor record ───────────────────────────────────────────────────────────
$tutor_stmt = $db->prepare('SELECT id, bio, hourly_rate, rating_average FROM tutors WHERE user_id = ? LIMIT 1');
$tutor_stmt->execute([$user_id]);
$tutor = $tutor_stmt->fetch();

if (!$tutor) {
    // User has Tutor role but no tutors row yet — surface a helpful message.
    $page_title = 'Tutor Dashboard';
    require_once __DIR__ . '/../includes/app-header.php';
    echo '<main class="dashboard-main container"><div class="alert alert-warning">Your tutor profile is not set up yet. Please <a href="/AralPal/tutors/edit-profile.php">complete your profile</a>.</div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$tutor_id = (int) $tutor['id'];

// ── Active subscription ────────────────────────────────────────────────────
$sub_stmt = $db->prepare('
    SELECT s.id, s.status, s.end_date, sp.plan_name, sp.price
    FROM subscriptions s
    JOIN subscription_plans sp ON sp.id = s.plan_id
    WHERE s.user_id = ? AND s.status = \'active\' AND s.end_date >= CURDATE()
    ORDER BY s.end_date DESC
    LIMIT 1
');
$sub_stmt->execute([$user_id]);
$subscription = $sub_stmt->fetch();
$is_pro = $subscription && (float) $subscription['price'] > 0;

// ── Pending bookings ───────────────────────────────────────────────────────
$pending_stmt = $db->prepare('
    SELECT
        b.id,
        b.session_date,
        b.start_time,
        b.end_time,
        b.status,
        su.full_name  AS student_name,
        su.avatar     AS student_avatar,
        sub.subject_name
    FROM bookings b
    JOIN users    su  ON su.id = b.student_id
    JOIN subjects sub ON sub.id = b.subject_id
    WHERE b.tutor_id = ? AND b.status = \'pending\'
    ORDER BY b.session_date ASC, b.start_time ASC
    LIMIT 10
');
$pending_stmt->execute([$tutor_id]);
$pending_bookings = $pending_stmt->fetchAll();

// ── Earnings ───────────────────────────────────────────────────────────────
$earn_stmt = $db->prepare('
    SELECT COALESCE(SUM(p.amount), 0) AS total_earnings
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    WHERE b.tutor_id = ? AND p.status = \'paid\'
');
$earn_stmt->execute([$tutor_id]);
$earnings_row     = $earn_stmt->fetch();
$total_earnings   = (float) ($earnings_row['total_earnings'] ?? 0);

// ── Completed bookings count ───────────────────────────────────────────────
$completed_stmt = $db->prepare('
    SELECT COUNT(*) AS total_completed
    FROM bookings
    WHERE tutor_id = ? AND status = \'completed\'
');
$completed_stmt->execute([$tutor_id]);
$completed_row    = $completed_stmt->fetch();
$total_completed  = (int) ($completed_row['total_completed'] ?? 0);

// ── Unread notifications ───────────────────────────────────────────────────
$notif_stmt = $db->prepare('
    SELECT id, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
');
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll();

// ── Page setup ─────────────────────────────────────────────────────────────
$page_title = 'Tutor Dashboard';
$csrf_token = generate_csrf();

require_once __DIR__ . '/../includes/app-header.php';
?>

<main class="dashboard-main container">

    <!-- ── Welcome Banner ──────────────────────────────────────────────── -->
    <section class="dashboard-banner">
        <div class="dashboard-banner__text">
            <h1>Welcome back, <?= htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="muted">Manage your sessions and track your earnings.</p>
        </div>
        <div class="dashboard-banner__badge">
            <?php if ($is_pro): ?>
                <span class="sub-badge sub-badge--pro">
                    ⭐ <?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php else: ?>
                <span class="sub-badge sub-badge--free">Starter Plan</span>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline" href="/AralPal/subscriptions/plans.php">
                <?= $is_pro ? 'Manage Plan' : 'Go Pro' ?>
            </a>
        </div>
    </section>

    <!-- ── Dashboard Grid ──────────────────────────────────────────────── -->
    <div class="dashboard-grid">

        <!-- Widget: Pending Requests -->
        <section class="widget widget--pending" aria-labelledby="widget-pending-title">
            <div class="widget__header">
                <h2 id="widget-pending-title">📥 Pending Requests
                    <?php if (!empty($pending_bookings)): ?>
                        <span class="badge badge-warning" style="margin-left:.5rem"><?= count($pending_bookings) ?></span>
                    <?php endif; ?>
                </h2>
                <a class="widget__link" href="/AralPal/booking/my-bookings.php">View all →</a>
            </div>

            <?php if (empty($pending_bookings)): ?>
                <p class="widget__empty">No pending requests at the moment.</p>
            <?php else: ?>
                <ul class="widget__list" role="list" id="pending-list">
                    <?php foreach ($pending_bookings as $bk): ?>
                        <?php
                        $bk_id = (int) $bk['id'];
                        $date  = date('M j, Y', strtotime($bk['session_date']));
                        $time  = date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']));
                        ?>
                        <li
                            class="widget__list-item booking-item booking-item--pending"
                            id="pending-row-<?= $bk_id ?>"
                            data-booking-id="<?= $bk_id ?>"
                        >
                            <div class="booking-item__info">
                                <span class="booking-item__tutor">
                                    <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="role-badge">Student</span>
                                </span>
                                <span class="booking-item__subject">
                                    <?= htmlspecialchars($bk['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="booking-item__datetime">
                                    📅 <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                    &nbsp;🕐 <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="booking-item__actions" id="actions-<?= $bk_id ?>">
                                <span class="badge badge-warning pending-status-badge">Pending</span>
                                <button
                                    class="btn btn-sm btn-success tutor-dash-action"
                                    data-action="accept"
                                    data-booking="<?= $bk_id ?>"
                                    data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Accept booking from <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    ✓ Accept
                                </button>
                                <button
                                    class="btn btn-sm btn-danger tutor-dash-action"
                                    data-action="decline"
                                    data-booking="<?= $bk_id ?>"
                                    data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Decline booking from <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    ✕ Decline
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Widget: Earnings Summary -->
        <section class="widget widget--earnings" aria-labelledby="widget-earnings-title">
            <div class="widget__header">
                <h2 id="widget-earnings-title">💰 Earnings Summary</h2>
            </div>
            <div class="earnings-grid">
                <div class="earnings-stat">
                    <span class="earnings-stat__label">Total Earnings</span>
                    <span class="earnings-stat__value">₱<?= htmlspecialchars(number_format($total_earnings, 2), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="earnings-stat">
                    <span class="earnings-stat__label">Completed Sessions</span>
                    <span class="earnings-stat__value"><?= htmlspecialchars((string) $total_completed, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="earnings-stat">
                    <span class="earnings-stat__label">Hourly Rate</span>
                    <span class="earnings-stat__value">₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="earnings-stat">
                    <span class="earnings-stat__label">Rating</span>
                    <span class="earnings-stat__value">
                        ⭐ <?= htmlspecialchars(number_format((float) $tutor['rating_average'], 1), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- Widget: Recent Notifications -->
        <section class="widget widget--notifications" aria-labelledby="widget-notif-title">
            <div class="widget__header">
                <h2 id="widget-notif-title">🔔 Notifications</h2>
                <a class="widget__link" href="/AralPal/notifications/index.php">View all →</a>
            </div>

            <?php if (empty($notifications)): ?>
                <p class="widget__empty">No unread notifications.</p>
            <?php else: ?>
                <ul class="widget__list notif-list" role="list">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $created_ts = strtotime($notif['created_at']);
                        $diff       = time() - $created_ts;
                        if ($diff < 60)          $time_ago = 'Just now';
                        elseif ($diff < 3600)    $time_ago = floor($diff / 60) . 'm ago';
                        elseif ($diff < 86400)   $time_ago = floor($diff / 3600) . 'h ago';
                        else                     $time_ago = date('M j', $created_ts);
                        ?>
                        <li class="widget__list-item notif-item">
                            <span class="notif-item__dot" aria-hidden="true"></span>
                            <div class="notif-item__body">
                                <p class="notif-item__msg">
                                    <?= htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <time class="notif-item__time muted" datetime="<?= htmlspecialchars($notif['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($time_ago, ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div><!-- /.dashboard-grid -->

    <!-- ── Quick Links ──────────────────────────────────────────────────── -->
    <nav class="quick-links" aria-label="Quick links">
        <a class="quick-link-btn" href="/AralPal/booking/my-bookings.php">
            <span class="quick-link-btn__icon" aria-hidden="true">📋</span>
            My Bookings
        </a>
        <a class="quick-link-btn" href="/AralPal/tutors/edit-profile.php">
            <span class="quick-link-btn__icon" aria-hidden="true">✏️</span>
            Edit Profile
        </a>
        <a class="quick-link-btn" href="/AralPal/subscriptions/plans.php">
            <span class="quick-link-btn__icon" aria-hidden="true">⭐</span>
            Plans
        </a>
    </nav>

</main>

<!-- ── AJAX for tutor Accept / Decline ────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    var statusMap = {
        accepted : { label: 'Accepted', cls: 'badge-success' },
        declined : { label: 'Declined', cls: 'badge-danger'  },
    };

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tutor-dash-action');
        if (!btn) return;

        var action    = btn.getAttribute('data-action');
        var bookingId = btn.getAttribute('data-booking');
        var csrf      = btn.getAttribute('data-csrf');
        var row       = document.getElementById('pending-row-' + bookingId);
        var actionsEl = document.getElementById('actions-' + bookingId);

        if (!row || !actionsEl) return;

        // Disable both buttons in this row
        var rowBtns = actionsEl.querySelectorAll('.tutor-dash-action');
        rowBtns.forEach(function (b) { b.disabled = true; });
        btn.textContent = 'Saving…';

        var body = new URLSearchParams();
        body.append('action',     action);
        body.append('booking_id', bookingId);
        body.append('csrf_token', csrf);

        fetch('/AralPal/booking/my-bookings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) {
                alert('Error: ' + (data.error || 'Unknown error.'));
                rowBtns.forEach(function (b) { b.disabled = false; });
                btn.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                return;
            }

            var info = statusMap[data.status] || { label: data.status, cls: 'badge-secondary' };

            // Replace action buttons with updated badge
            actionsEl.innerHTML = '<span class="badge ' + info.cls + '">' + info.label + '</span>';

            // Fade the row slightly to signal it's been actioned
            row.style.opacity = '0.6';
        })
        .catch(function () {
            alert('Network error. Please try again.');
            rowBtns.forEach(function (b) { b.disabled = false; });
        });
    });
})();
</script>

<style>
/* ── Tutor Dashboard Styles ───────────────────────────────────────────────── */
.dashboard-main { padding: 2rem clamp(1rem, 4vw, 3rem); }

/* Banner */
.dashboard-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: var(--clr-primary-light);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
}
.dashboard-banner h1 { font-size: 1.5rem; margin-bottom: .25rem; }
.dashboard-banner__badge { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* Sub badge */
.sub-badge {
    display: inline-block;
    padding: .25rem .75rem;
    border-radius: 99px;
    font-size: .8rem;
    font-weight: 600;
    white-space: nowrap;
}
.sub-badge--pro  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.sub-badge--free { background: var(--clr-border); color: var(--clr-muted); }

/* Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
@media (max-width: 768px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}
.widget--pending { grid-column: 1 / -1; }

/* Widgets */
.widget {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.widget__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: .5rem;
}
.widget__header h2 { font-size: 1rem; font-weight: 600; }
.widget__link { font-size: .85rem; color: var(--clr-primary); text-decoration: none; white-space: nowrap; }
.widget__link:hover { text-decoration: underline; }
.widget__empty { color: var(--clr-muted); font-size: .9rem; }
.widget__list { list-style: none; display: flex; flex-direction: column; gap: .75rem; }

/* Booking items */
.booking-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .75rem;
    background: var(--clr-bg);
    border-radius: var(--radius-sm);
    border: 1px solid var(--clr-border);
    flex-wrap: wrap;
}
.booking-item__info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; flex: 1; }
.booking-item__tutor { font-weight: 600; font-size: .95rem; display: flex; align-items: center; gap: .4rem; }
.booking-item__subject { color: var(--clr-primary); font-size: .85rem; }
.booking-item__datetime { color: var(--clr-muted); font-size: .8rem; }
.booking-item__actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; flex-wrap: wrap; }

/* Earnings grid */
.earnings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 480px) {
    .earnings-grid { grid-template-columns: 1fr; }
}
.earnings-stat {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: 1rem;
    background: var(--clr-bg);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
}
.earnings-stat__label { font-size: .78rem; color: var(--clr-muted); text-transform: uppercase; letter-spacing: .04em; }
.earnings-stat__value { font-size: 1.4rem; font-weight: 700; color: var(--clr-primary); }

/* Notifications */
.notif-list { gap: .6rem; }
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
}
.notif-item__dot {
    flex-shrink: 0;
    width: 8px; height: 8px;
    margin-top: .45rem;
    border-radius: 50%;
    background: var(--clr-primary);
}
.notif-item__body { display: flex; flex-direction: column; gap: .2rem; }
.notif-item__msg  { font-size: .88rem; line-height: 1.5; }
.notif-item__time { font-size: .78rem; }

/* Quick links */
.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}
.quick-link-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .6rem 1.2rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    font-size: .9rem;
    font-weight: 500;
    text-decoration: none;
    color: var(--clr-text);
    transition: background var(--transition), box-shadow var(--transition), transform var(--transition);
}
.quick-link-btn:hover {
    background: var(--clr-primary-light);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}
.quick-link-btn__icon { font-size: 1.1rem; }

/* Badge helpers */
.badge {
    display: inline-block;
    padding: .2rem .55rem;
    border-radius: 99px;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
}
.badge-warning   { background: #fef3c7; color: #92400e; }
.badge-success   { background: #dcfce7; color: #166534; }
.badge-danger    { background: #fee2e2; color: #991b1b; }
.badge-info      { background: #dbeafe; color: #1e40af; }
.badge-secondary { background: var(--clr-border); color: var(--clr-muted); }

/* Button helpers */
.btn { display: inline-block; padding: .5rem 1.1rem; border-radius: var(--radius-sm); font-weight: 600; text-decoration: none; cursor: pointer; font-size: .9rem; border: 2px solid transparent; transition: background var(--transition), color var(--transition), border-color var(--transition); }
.btn-sm { padding: .3rem .75rem; font-size: .8rem; }
.btn-outline { background: transparent; border-color: var(--clr-primary); color: var(--clr-primary); }
.btn-outline:hover { background: var(--clr-primary); color: #fff; }
.btn-success { background: #22c55e; color: #fff; border-color: #22c55e; }
.btn-success:hover:not(:disabled) { background: #16a34a; border-color: #16a34a; }
.btn-danger  { background: #ef4444; color: #fff; border-color: #ef4444; }
.btn-danger:hover:not(:disabled)  { background: #dc2626; border-color: #dc2626; }
.btn:disabled { opacity: .6; cursor: not-allowed; }

/* Alert helper */
.alert { padding: 1rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* Utility */
.muted { color: var(--clr-muted); }
.role-badge {
    display: inline-block;
    padding: .1rem .45rem;
    background: var(--clr-primary-light);
    color: var(--clr-primary);
    border-radius: 99px;
    font-size: .7rem;
    font-weight: 600;
    vertical-align: middle;
}
</style>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
