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
    echo '<div class="td-wrap"><div class="td-alert td-alert--warning">Your tutor profile is not set up yet. Please <a href="/AralPal/tutors/edit-profile.php">complete your profile</a>.</div></div>';
    require_once __DIR__ . '/../includes/app-footer.php';
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

<style>
/* ── Tutor Dashboard — all selectors scoped to td- prefix ──────────────── */

.td-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 0 2.5rem;
}

/* ── Welcome banner ────────────────────────────────────────────────────── */
.td-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.5rem 2rem;
    margin-bottom: 1.75rem;
    box-shadow: var(--shadow-sm);
}

.td-banner__text h1 {
    font-size: 1.45rem;
    font-weight: 700;
    margin: 0 0 .25rem;
    color: var(--clr-text);
}

.td-banner__text p {
    margin: 0;
    font-size: .9rem;
    color: var(--clr-muted);
}

.td-banner__right {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
}

.td-plan-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .85rem;
    border-radius: 9999px;
    font-size: .8rem;
    font-weight: 600;
    white-space: nowrap;
}

.td-plan-badge--pro {
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde68a;
}

.td-plan-badge--starter {
    background: var(--clr-bg);
    color: var(--clr-muted);
    border: 1px solid var(--clr-border);
}

.td-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem 1.1rem;
    border-radius: var(--radius-sm);
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 2px solid transparent;
    transition: background var(--transition), color var(--transition), border-color var(--transition), box-shadow var(--transition);
    line-height: 1.4;
}

.td-btn--outline {
    background: transparent;
    border-color: var(--clr-primary);
    color: var(--clr-primary);
}

.td-btn--outline:hover {
    background: var(--clr-primary);
    color: #fff;
}

.td-btn--sm {
    padding: .3rem .75rem;
    font-size: .8rem;
}

.td-btn--success {
    background: #22c55e;
    color: #fff;
    border-color: #22c55e;
}

.td-btn--success:hover:not(:disabled) {
    background: #16a34a;
    border-color: #16a34a;
}

.td-btn--danger {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}

.td-btn--danger:hover:not(:disabled) {
    background: #dc2626;
    border-color: #dc2626;
}

.td-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* ── Stat cards row ────────────────────────────────────────────────────── */
.td-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.75rem;
}

@media (max-width: 900px) {
    .td-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .td-stats { grid-template-columns: 1fr; }
}

.td-stat-card {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.25rem 1.25rem 1.1rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    transition: box-shadow var(--transition), transform var(--transition);
}

.td-stat-card:hover {
    box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,.1));
    transform: translateY(-2px);
}

.td-stat-icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #fff;
}

/* Per-card icon chip colours — only these use hardcoded hex */
.td-stat-icon--earnings  { background: #10b981; }
.td-stat-icon--sessions  { background: #6366f1; }
.td-stat-icon--rate      { background: #f59e0b; }
.td-stat-icon--rating    { background: #ec4899; }

.td-stat-body {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
}

.td-stat-label {
    font-size: .75rem;
    color: var(--clr-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    white-space: nowrap;
}

.td-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--clr-text);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Two-column body grid ──────────────────────────────────────────────── */
.td-body-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}

@media (max-width: 768px) {
    .td-body-grid { grid-template-columns: 1fr; }
}

/* Full-width pending section */
.td-pending {
    grid-column: 1 / -1;
}

/* ── Widget card ───────────────────────────────────────────────────────── */
.td-card {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    box-shadow: var(--shadow-sm);
}

.td-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: 1rem;
    padding-bottom: .75rem;
    border-bottom: 1px solid var(--clr-border);
}

.td-card__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--clr-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.td-card__title i {
    color: var(--clr-primary);
    font-size: .95rem;
}

.td-card__link {
    font-size: .82rem;
    color: var(--clr-primary);
    text-decoration: none;
    white-space: nowrap;
    font-weight: 500;
}

.td-card__link:hover {
    text-decoration: underline;
}

/* ── Badges ────────────────────────────────────────────────────────────── */
.td-badge {
    display: inline-block;
    padding: .18rem .55rem;
    border-radius: 9999px;
    font-size: .72rem;
    font-weight: 600;
    white-space: nowrap;
}

.td-badge--warning   { background: #fef3c7; color: #92400e; }
.td-badge--success   { background: #dcfce7; color: #166534; }
.td-badge--danger    { background: #fee2e2; color: #991b1b; }
.td-badge--info      { background: #dbeafe; color: #1e40af; }
.td-badge--secondary { background: var(--clr-border); color: var(--clr-muted); }
.td-badge--role      { background: var(--clr-bg); color: var(--clr-primary); border: 1px solid var(--clr-border); }

/* ── Pending bookings list ─────────────────────────────────────────────── */
.td-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: 2rem 1rem;
    color: var(--clr-muted);
    text-align: center;
}

.td-empty i {
    font-size: 2rem;
    opacity: .4;
}

.td-empty p {
    margin: 0;
    font-size: .9rem;
}

.td-booking-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

.td-booking-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .875rem 1rem;
    background: var(--clr-bg);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    flex-wrap: wrap;
    transition: opacity .3s;
}

.td-booking-info {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
    flex: 1;
}

.td-booking-student {
    font-weight: 600;
    font-size: .93rem;
    color: var(--clr-text);
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}

.td-booking-subject {
    font-size: .85rem;
    color: var(--clr-primary);
}

.td-booking-datetime {
    font-size: .8rem;
    color: var(--clr-muted);
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
}

.td-booking-datetime i {
    font-size: .78rem;
}

.td-booking-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    align-self: center;
}

/* ── Notifications list ────────────────────────────────────────────────── */
.td-notif-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .6rem;
}

.td-notif-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .6rem .75rem;
    background: var(--clr-bg);
    border-radius: var(--radius-sm);
    border: 1px solid var(--clr-border);
}

.td-notif-dot {
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    margin-top: .45rem;
    border-radius: 50%;
    background: var(--clr-primary);
}

.td-notif-body {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
}

.td-notif-msg {
    font-size: .875rem;
    line-height: 1.5;
    color: var(--clr-text);
    margin: 0;
}

.td-notif-time {
    font-size: .75rem;
    color: var(--clr-muted);
}

/* ── Quick links bar ───────────────────────────────────────────────────── */
.td-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}

.td-quick-btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .6rem 1.2rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    font-size: .875rem;
    font-weight: 500;
    text-decoration: none;
    color: var(--clr-text);
    box-shadow: var(--shadow-sm);
    transition: background var(--transition), box-shadow var(--transition), transform var(--transition), color var(--transition);
}

.td-quick-btn i {
    color: var(--clr-primary);
    font-size: .95rem;
}

.td-quick-btn:hover {
    background: var(--clr-bg);
    box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,.1));
    transform: translateY(-1px);
    color: var(--clr-primary);
}

/* ── Alert helper ──────────────────────────────────────────────────────── */
.td-alert {
    padding: 1rem 1.25rem;
    border-radius: var(--radius-sm);
    margin-bottom: 1.5rem;
    font-size: .9rem;
}

.td-alert--warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
</style>

<div class="td-wrap">

    <!-- ── Welcome Banner ─────────────────────────────────────────────────── -->
    <section class="td-banner">
        <div class="td-banner__text">
            <h1>Welcome back, <?= htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p>Manage your sessions and track your earnings.</p>
        </div>
        <div class="td-banner__right">
            <?php if ($is_pro): ?>
                <span class="td-plan-badge td-plan-badge--pro">
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    <?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php else: ?>
                <span class="td-plan-badge td-plan-badge--starter">
                    <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                    Starter Plan
                </span>
            <?php endif; ?>
            <a class="td-btn td-btn--outline" href="/AralPal/subscriptions/plans.php">
                <i class="fa-solid fa-<?= $is_pro ? 'gear' : 'arrow-up' ?>" aria-hidden="true"></i>
                <?= $is_pro ? 'Manage Plan' : 'Go Pro' ?>
            </a>
        </div>
    </section>

    <!-- ── Stat Cards ─────────────────────────────────────────────────────── -->
    <div class="td-stats" role="list" aria-label="Dashboard statistics">

        <div class="td-stat-card" role="listitem">
            <div class="td-stat-icon td-stat-icon--earnings" aria-hidden="true">
                <i class="fa-solid fa-peso-sign"></i>
            </div>
            <div class="td-stat-body">
                <span class="td-stat-label">Total Earnings</span>
                <span class="td-stat-value">₱<?= htmlspecialchars(number_format($total_earnings, 2), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="td-stat-card" role="listitem">
            <div class="td-stat-icon td-stat-icon--sessions" aria-hidden="true">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="td-stat-body">
                <span class="td-stat-label">Completed Sessions</span>
                <span class="td-stat-value"><?= htmlspecialchars((string) $total_completed, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="td-stat-card" role="listitem">
            <div class="td-stat-icon td-stat-icon--rate" aria-hidden="true">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="td-stat-body">
                <span class="td-stat-label">Hourly Rate</span>
                <span class="td-stat-value">₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="td-stat-card" role="listitem">
            <div class="td-stat-icon td-stat-icon--rating" aria-hidden="true">
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="td-stat-body">
                <span class="td-stat-label">Rating</span>
                <span class="td-stat-value">★ <?= htmlspecialchars(number_format((float) $tutor['rating_average'], 1), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

    </div><!-- /.td-stats -->

    <!-- ── Body Grid ──────────────────────────────────────────────────────── -->
    <div class="td-body-grid">

        <!-- Full-width: Pending Booking Requests -->
        <section class="td-card td-pending" aria-labelledby="td-pending-title">
            <div class="td-card__header">
                <h2 class="td-card__title" id="td-pending-title">
                    <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                    Pending Booking Requests
                    <?php if (!empty($pending_bookings)): ?>
                        <span class="td-badge td-badge--warning"><?= count($pending_bookings) ?></span>
                    <?php endif; ?>
                </h2>
                <a class="td-card__link" href="/AralPal/booking/my-bookings.php">
                    View all <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <?php if (empty($pending_bookings)): ?>
                <div class="td-empty">
                    <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                    <p>No pending requests at the moment.</p>
                </div>
            <?php else: ?>
                <ul class="td-booking-list" role="list" id="pending-list">
                    <?php foreach ($pending_bookings as $bk): ?>
                        <?php
                        $bk_id = (int) $bk['id'];
                        $date  = date('M j, Y', strtotime($bk['session_date']));
                        $time  = date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']));
                        ?>
                        <li
                            class="td-booking-row booking-item booking-item--pending"
                            id="pending-row-<?= $bk_id ?>"
                            data-booking-id="<?= $bk_id ?>"
                            role="listitem"
                        >
                            <div class="td-booking-info">
                                <div class="td-booking-student">
                                    <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="td-badge td-badge--role">Student</span>
                                </div>
                                <div class="td-booking-subject">
                                    <?= htmlspecialchars($bk['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="td-booking-datetime">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="td-booking-actions" id="actions-<?= $bk_id ?>">
                                <span class="td-badge td-badge--warning pending-status-badge">Pending</span>
                                <button
                                    class="td-btn td-btn--sm td-btn--success tutor-dash-action"
                                    data-action="accept"
                                    data-booking="<?= $bk_id ?>"
                                    data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Accept booking from <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> Accept
                                </button>
                                <button
                                    class="td-btn td-btn--sm td-btn--danger tutor-dash-action"
                                    data-action="decline"
                                    data-booking="<?= $bk_id ?>"
                                    data-csrf="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Decline booking from <?= htmlspecialchars($bk['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Decline
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Left column: Earnings Summary -->
        <section class="td-card" aria-labelledby="td-earnings-title">
            <div class="td-card__header">
                <h2 class="td-card__title" id="td-earnings-title">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    Earnings Summary
                </h2>
            </div>
            <div class="td-stats" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
                <div class="td-stat-card" style="box-shadow:none;border-color:var(--clr-border);">
                    <div class="td-stat-icon td-stat-icon--earnings" aria-hidden="true">
                        <i class="fa-solid fa-peso-sign"></i>
                    </div>
                    <div class="td-stat-body">
                        <span class="td-stat-label">Total Earnings</span>
                        <span class="td-stat-value" style="font-size:1.1rem;">₱<?= htmlspecialchars(number_format($total_earnings, 2), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="td-stat-card" style="box-shadow:none;border-color:var(--clr-border);">
                    <div class="td-stat-icon td-stat-icon--sessions" aria-hidden="true">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="td-stat-body">
                        <span class="td-stat-label">Completed</span>
                        <span class="td-stat-value" style="font-size:1.1rem;"><?= htmlspecialchars((string) $total_completed, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="td-stat-card" style="box-shadow:none;border-color:var(--clr-border);">
                    <div class="td-stat-icon td-stat-icon--rate" aria-hidden="true">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="td-stat-body">
                        <span class="td-stat-label">Hourly Rate</span>
                        <span class="td-stat-value" style="font-size:1.1rem;">₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="td-stat-card" style="box-shadow:none;border-color:var(--clr-border);">
                    <div class="td-stat-icon td-stat-icon--rating" aria-hidden="true">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="td-stat-body">
                        <span class="td-stat-label">Rating</span>
                        <span class="td-stat-value" style="font-size:1.1rem;">★ <?= htmlspecialchars(number_format((float) $tutor['rating_average'], 1), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right column: Recent Notifications -->
        <section class="td-card" aria-labelledby="td-notif-title">
            <div class="td-card__header">
                <h2 class="td-card__title" id="td-notif-title">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    Recent Notifications
                </h2>
                <a class="td-card__link" href="/AralPal/notifications/index.php">
                    View all <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="td-empty">
                    <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
                    <p>No unread notifications.</p>
                </div>
            <?php else: ?>
                <ul class="td-notif-list" role="list">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $created_ts = strtotime($notif['created_at']);
                        $diff       = time() - $created_ts;
                        if ($diff < 60)          $time_ago = 'Just now';
                        elseif ($diff < 3600)    $time_ago = floor($diff / 60) . 'm ago';
                        elseif ($diff < 86400)   $time_ago = floor($diff / 3600) . 'h ago';
                        else                     $time_ago = date('M j', $created_ts);
                        ?>
                        <li class="td-notif-item" role="listitem">
                            <span class="td-notif-dot" aria-hidden="true"></span>
                            <div class="td-notif-body">
                                <p class="td-notif-msg">
                                    <?= htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <time class="td-notif-time" datetime="<?= htmlspecialchars($notif['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($time_ago, ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div><!-- /.td-body-grid -->

    <!-- ── Quick Links ────────────────────────────────────────────────────── -->
    <nav class="td-quick-links" aria-label="Quick links">
        <a class="td-quick-btn" href="/AralPal/booking/my-bookings.php">
            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
            My Bookings
        </a>
        <a class="td-quick-btn" href="/AralPal/tutors/edit-profile.php">
            <i class="fa-solid fa-id-card" aria-hidden="true"></i>
            Edit Profile
        </a>
        <a class="td-quick-btn" href="/AralPal/subscriptions/plans.php">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            Plans
        </a>
    </nav>

</div><!-- /.td-wrap -->

<!-- ── AJAX for tutor Accept / Decline ────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    var statusMap = {
        accepted : { label: 'Accepted', cls: 'td-badge--success' },
        declined : { label: 'Declined', cls: 'td-badge--danger'  },
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

            var info = statusMap[data.status] || { label: data.status, cls: 'td-badge--secondary' };

            // Replace action buttons with updated badge
            actionsEl.innerHTML = '<span class="td-badge ' + info.cls + '">' + info.label + '</span>';

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

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
