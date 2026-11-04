<?php
/**
 * AralPal – Student Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_role('Student');

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

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

// ── Upcoming bookings ──────────────────────────────────────────────────────
$upcoming_stmt = $db->prepare('
    SELECT
        b.id,
        b.session_date,
        b.start_time,
        b.end_time,
        b.status,
        tu.full_name  AS tutor_name,
        tu.avatar     AS tutor_avatar,
        s.subject_name
    FROM bookings b
    JOIN tutors   t  ON t.id  = b.tutor_id
    JOIN users    tu ON tu.id = t.user_id
    JOIN subjects s  ON s.id  = b.subject_id
    WHERE b.student_id = ?
      AND b.status IN (\'pending\', \'accepted\')
      AND b.session_date >= CURDATE()
    ORDER BY b.session_date ASC, b.start_time ASC
    LIMIT 5
');
$upcoming_stmt->execute([$user_id]);
$upcoming_bookings = $upcoming_stmt->fetchAll();

// ── Favorites ─────────────────────────────────────────────────────────────
$fav_stmt = $db->prepare('
    SELECT
        t.id          AS tutor_id,
        tu.full_name  AS tutor_name,
        tu.avatar     AS tutor_avatar,
        t.hourly_rate,
        t.rating_average
    FROM favorites f
    JOIN tutors t  ON t.id  = f.tutor_id
    JOIN users  tu ON tu.id = t.user_id
    WHERE f.user_id = ?
    ORDER BY f.id DESC
    LIMIT 4
');
$fav_stmt->execute([$user_id]);
$favorites = $fav_stmt->fetchAll();

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

// ── Status badge mapping ───────────────────────────────────────────────────
$status_badges = [
    'pending'   => ['label' => 'Pending',  'class' => 'badge-warning'],
    'accepted'  => ['label' => 'Accepted', 'class' => 'badge-success'],
    'declined'  => ['label' => 'Declined', 'class' => 'badge-danger'],
    'completed' => ['label' => 'Done',     'class' => 'badge-info'],
    'cancelled' => ['label' => 'Cancelled','class' => 'badge-secondary'],
];

// ── Page setup ─────────────────────────────────────────────────────────────
$page_title = 'Student Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main container">

    <!-- ── Welcome Banner ──────────────────────────────────────────────── -->
    <section class="dashboard-banner">
        <div class="dashboard-banner__text">
            <h1>Welcome back, <?= htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="muted">Here's what's coming up for you.</p>
        </div>
        <div class="dashboard-banner__badge">
            <?php if ($subscription && $subscription['price'] > 0): ?>
                <span class="sub-badge sub-badge--pro">
                    ⭐ <?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php else: ?>
                <span class="sub-badge sub-badge--free">Free Plan</span>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline" href="/AralPal/subscriptions/plans.php">
                <?= $subscription && $subscription['price'] > 0 ? 'Manage Plan' : 'Upgrade' ?>
            </a>
        </div>
    </section>

    <!-- ── Dashboard Grid ──────────────────────────────────────────────── -->
    <div class="dashboard-grid">

        <!-- Widget: Upcoming Bookings -->
        <section class="widget widget--bookings" aria-labelledby="widget-bookings-title">
            <div class="widget__header">
                <h2 id="widget-bookings-title">📅 Upcoming Bookings</h2>
                <a class="widget__link" href="/AralPal/booking/my-bookings.php">View all →</a>
            </div>

            <?php if (empty($upcoming_bookings)): ?>
                <p class="widget__empty">No upcoming sessions. <a href="/AralPal/tutors/index.php">Find a tutor</a> to get started.</p>
            <?php else: ?>
                <ul class="widget__list" role="list">
                    <?php foreach ($upcoming_bookings as $bk): ?>
                        <?php
                        $si   = $status_badges[$bk['status']] ?? ['label' => htmlspecialchars($bk['status'], ENT_QUOTES, 'UTF-8'), 'class' => 'badge-secondary'];
                        $date = date('M j, Y', strtotime($bk['session_date']));
                        $time = date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']));
                        ?>
                        <li class="widget__list-item booking-item">
                            <div class="booking-item__info">
                                <span class="booking-item__tutor">
                                    <?= htmlspecialchars($bk['tutor_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="booking-item__subject">
                                    <?= htmlspecialchars($bk['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="booking-item__datetime">
                                    📅 <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                    &nbsp;🕐 <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <span class="badge <?= htmlspecialchars($si['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($si['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Widget: Favorites -->
        <section class="widget widget--favorites" aria-labelledby="widget-fav-title">
            <div class="widget__header">
                <h2 id="widget-fav-title">❤️ Favorite Tutors</h2>
                <a class="widget__link" href="/AralPal/tutors/index.php">View all →</a>
            </div>

            <?php if (empty($favorites)): ?>
                <p class="widget__empty">No favorites yet. <a href="/AralPal/tutors/index.php">Browse tutors</a>.</p>
            <?php else: ?>
                <div class="fav-grid">
                    <?php foreach ($favorites as $fav): ?>
                        <a class="fav-card" href="/AralPal/tutors/profile.php?id=<?= (int) $fav['tutor_id'] ?>">
                            <img
                                class="fav-card__avatar"
                                src="<?= htmlspecialchars($fav['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($fav['tutor_name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.src='/AralPal/assets/avatar-default.png'"
                            >
                            <span class="fav-card__name">
                                <?= htmlspecialchars($fav['tutor_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="fav-card__rate">
                                ₱<?= htmlspecialchars(number_format((float) $fav['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?>/hr
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                        $created_ts  = strtotime($notif['created_at']);
                        $diff        = time() - $created_ts;
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
        <a class="quick-link-btn" href="/AralPal/tutors/index.php">
            <span class="quick-link-btn__icon" aria-hidden="true">🔍</span>
            Find a Tutor
        </a>
        <a class="quick-link-btn" href="/AralPal/booking/my-bookings.php">
            <span class="quick-link-btn__icon" aria-hidden="true">📋</span>
            My Bookings
        </a>
        <a class="quick-link-btn" href="/AralPal/subscriptions/plans.php">
            <span class="quick-link-btn__icon" aria-hidden="true">⭐</span>
            Plans
        </a>
        <a class="quick-link-btn" href="/AralPal/tutors/edit-profile.php">
            <span class="quick-link-btn__icon" aria-hidden="true">✏️</span>
            Edit Profile
        </a>
    </nav>

</main>

<style>
/* ── Student Dashboard Styles ─────────────────────────────────────────────── */
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
    grid-template-rows: auto auto;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
@media (max-width: 768px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}
.widget--bookings { grid-column: 1 / -1; }

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
    gap: .75rem;
    padding: .75rem;
    background: var(--clr-bg);
    border-radius: var(--radius-sm);
    border: 1px solid var(--clr-border);
}
.booking-item__info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
.booking-item__tutor { font-weight: 600; font-size: .95rem; }
.booking-item__subject { color: var(--clr-primary); font-size: .85rem; }
.booking-item__datetime { color: var(--clr-muted); font-size: .8rem; }

/* Badge helpers (re-usable across the site) */
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

/* Favorites grid */
.fav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
}
.fav-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .4rem;
    padding: .75rem .5rem;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    background: var(--clr-bg);
    text-decoration: none;
    color: inherit;
    transition: box-shadow var(--transition), transform var(--transition);
    text-align: center;
}
.fav-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.fav-card__avatar {
    width: 52px; height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--clr-primary-light);
}
.fav-card__name { font-size: .85rem; font-weight: 600; line-height: 1.3; }
.fav-card__rate { font-size: .78rem; color: var(--clr-muted); }

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

/* Utility */
.muted { color: var(--clr-muted); }
.btn { display: inline-block; padding: .5rem 1.1rem; border-radius: var(--radius-sm); font-weight: 600; text-decoration: none; cursor: pointer; font-size: .9rem; border: 2px solid transparent; transition: background var(--transition), color var(--transition), border-color var(--transition); }
.btn-sm { padding: .3rem .75rem; font-size: .8rem; }
.btn-outline { background: transparent; border-color: var(--clr-primary); color: var(--clr-primary); }
.btn-outline:hover { background: var(--clr-primary); color: #fff; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
