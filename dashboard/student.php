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

// ── Active subscription ───────────────────────────────────────────────────
$sub_stmt = $db->prepare("
    SELECT s.id, s.status, s.end_date, sp.plan_name, sp.price
    FROM subscriptions s
    JOIN subscription_plans sp ON sp.id = s.plan_id
    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURDATE()
    ORDER BY s.end_date DESC LIMIT 1
");
$sub_stmt->execute([$user_id]);
$subscription = $sub_stmt->fetch();
$is_pro = $subscription && (float)$subscription['price'] > 0;

// ── Upcoming bookings ─────────────────────────────────────────────────────
$upcoming_stmt = $db->prepare("
    SELECT b.id, b.session_date, b.start_time, b.end_time, b.status,
           tu.full_name AS tutor_name, tu.avatar AS tutor_avatar, s.subject_name
    FROM bookings b
    JOIN tutors t  ON t.id  = b.tutor_id
    JOIN users  tu ON tu.id = t.user_id
    JOIN subjects s ON s.id = b.subject_id
    WHERE b.student_id = ? AND b.status IN ('pending','accepted') AND b.session_date >= CURDATE()
    ORDER BY b.session_date ASC, b.start_time ASC LIMIT 5
");
$upcoming_stmt->execute([$user_id]);
$upcoming_bookings = $upcoming_stmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────
$stats = $db->prepare("
    SELECT
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN MONTH(session_date)=MONTH(CURDATE()) AND YEAR(session_date)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_month
    FROM bookings WHERE student_id = ?
");
$stats->execute([$user_id]);
$booking_stats = $stats->fetch();

// ── Lesson progress ───────────────────────────────────────────────────────
$lesson_slugs = [
    'php'        => ['title' => 'Introduction to PHP',       'icon' => 'fa-code'],
    'html-css'   => ['title' => 'HTML & CSS Basics',         'icon' => 'fa-brands fa-html5'],
    'javascript' => ['title' => 'JavaScript Fundamentals',   'icon' => 'fa-brands fa-js'],
];
$lesson_progress_map = [];
try {
    $lp_stmt = $db->prepare('SELECT lesson_slug, status FROM lesson_progress WHERE user_id = ?');
    $lp_stmt->execute([$user_id]);
    foreach ($lp_stmt->fetchAll() as $row) {
        $lesson_progress_map[$row['lesson_slug']] = $row['status'];
    }
} catch (Exception $e) {}

$completed_lessons = count(array_filter($lesson_progress_map, fn($s) => $s === 'completed'));
$total_lessons     = count($lesson_slugs);
$overall_pct       = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;

// ── Quiz attempts ─────────────────────────────────────────────────────────
$quiz_attempts = [];
$best_scores   = [];
try {
    $qa_stmt = $db->prepare('SELECT lesson_slug, score, total, created_at FROM quiz_attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $qa_stmt->execute([$user_id]);
    $quiz_attempts = $qa_stmt->fetchAll();

    $best_stmt = $db->prepare('SELECT lesson_slug, MAX(score) as best, total FROM quiz_attempts WHERE user_id = ? GROUP BY lesson_slug, total');
    $best_stmt->execute([$user_id]);
    foreach ($best_stmt->fetchAll() as $row) {
        $best_scores[$row['lesson_slug']] = $row;
    }
} catch (Exception $e) {}

// ── Favorites ─────────────────────────────────────────────────────────────
$fav_stmt = $db->prepare("
    SELECT t.id AS tutor_id, tu.full_name AS tutor_name, tu.avatar AS tutor_avatar,
           t.hourly_rate, t.rating_average
    FROM favorites f
    JOIN tutors t  ON t.id  = f.tutor_id
    JOIN users  tu ON tu.id = t.user_id
    WHERE f.user_id = ?
    ORDER BY f.id DESC LIMIT 4
");
$fav_stmt->execute([$user_id]);
$favorites = $fav_stmt->fetchAll();

// ── Notifications ─────────────────────────────────────────────────────────
$notif_stmt = $db->prepare('SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5');
$notif_stmt->execute([$user_id]);
$notifications  = $notif_stmt->fetchAll();
$unread_count   = count($notifications);

// ── Status badge mapping ──────────────────────────────────────────────────
$status_badges = [
    'pending'   => ['label' => 'Pending',   'class' => 'warning'],
    'accepted'  => ['label' => 'Confirmed', 'class' => 'success'],
    'declined'  => ['label' => 'Declined',  'class' => 'danger'],
    'completed' => ['label' => 'Done',      'class' => 'info'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'secondary'],
];

$page_title = 'Student Dashboard';
require_once __DIR__ . '/../includes/app-header.php';
?>

<div class="sd-wrap">

    <!-- ── Welcome Banner ─────────────────────────────────────────────── -->
    <div class="sd-banner">
        <div class="sd-banner__left">
            <p class="sd-banner__eyebrow">Student Dashboard</p>
            <h1 class="sd-banner__name">
                Hello, <?= htmlspecialchars(explode(' ', $current['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>! 👋
            </h1>
            <p class="sd-banner__sub">Here's your learning summary for today.</p>
        </div>
        <div class="sd-banner__right">
            <?php if ($is_pro): ?>
                <div class="sd-plan-chip sd-plan-chip--pro">
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    <?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <p class="sd-plan-expiry">
                    Expires <?= htmlspecialchars(date('M j, Y', strtotime($subscription['end_date'])), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php else: ?>
                <div class="sd-plan-chip sd-plan-chip--free">
                    <i class="fa-solid fa-user" aria-hidden="true"></i> Free Plan
                </div>
                <a class="sd-upgrade-btn" href="/AralPal/subscriptions/plans.php">
                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Upgrade
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stat Cards ─────────────────────────────────────────────────── -->
    <div class="sd-stats" role="list" aria-label="Dashboard statistics">
        <div class="sd-stat" role="listitem">
            <span class="sd-stat__icon" style="background:#e0f2fe;color:#0369a1" aria-hidden="true">
                <i class="fa-solid fa-calendar-check"></i>
            </span>
            <div>
                <p class="sd-stat__val"><?= (int)($booking_stats['total_bookings'] ?? 0) ?></p>
                <p class="sd-stat__lbl">Total Bookings</p>
            </div>
        </div>
        <div class="sd-stat" role="listitem">
            <span class="sd-stat__icon" style="background:#dcfce7;color:#15803d" aria-hidden="true">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <div>
                <p class="sd-stat__val"><?= (int)($booking_stats['completed'] ?? 0) ?></p>
                <p class="sd-stat__lbl">Completed</p>
            </div>
        </div>
        <div class="sd-stat" role="listitem">
            <span class="sd-stat__icon" style="background:#fff7ed;color:#c2410c" aria-hidden="true">
                <i class="fa-solid fa-calendar-day"></i>
            </span>
            <div>
                <p class="sd-stat__val"><?= (int)($booking_stats['this_month'] ?? 0) ?></p>
                <p class="sd-stat__lbl">This Month</p>
            </div>
        </div>
        <div class="sd-stat" role="listitem">
            <span class="sd-stat__icon" style="background:#fef9c3;color:#a16207" aria-hidden="true">
                <i class="fa-solid fa-book-open"></i>
            </span>
            <div>
                <p class="sd-stat__val"><?= $completed_lessons ?>/<?= $total_lessons ?></p>
                <p class="sd-stat__lbl">Lessons Done</p>
            </div>
        </div>
        <div class="sd-stat" role="listitem">
            <span class="sd-stat__icon" style="background:#ede9fe;color:#6d28d9" aria-hidden="true">
                <i class="fa-solid fa-bell"></i>
            </span>
            <div>
                <p class="sd-stat__val"><?= $unread_count ?></p>
                <p class="sd-stat__lbl">Notifications Unread</p>
            </div>
        </div>
    </div>

    <!-- ── Main Grid ──────────────────────────────────────────────────── -->
    <div class="sd-grid">

        <!-- Left wide column: Upcoming Sessions -->
        <section class="sd-widget sd-widget--wide" aria-labelledby="sdw-bookings">
            <div class="sd-widget__head">
                <h2 id="sdw-bookings">
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i> Upcoming Sessions
                </h2>
                <a class="sd-widget__more" href="/AralPal/booking/my-bookings.php">View all →</a>
            </div>
            <?php if (empty($upcoming_bookings)): ?>
                <div class="sd-empty">
                    <i class="fa-regular fa-calendar-xmark fa-2x" aria-hidden="true"></i>
                    <p>No upcoming sessions. <a href="/AralPal/tutors/index.php">Find a tutor</a> to book one.</p>
                </div>
            <?php else: ?>
                <ul class="sd-session-list">
                    <?php foreach ($upcoming_bookings as $bk):
                        $si   = $status_badges[$bk['status']] ?? ['label' => htmlspecialchars($bk['status'], ENT_QUOTES, 'UTF-8'), 'class' => 'secondary'];
                        $date = htmlspecialchars(date('D, M j', strtotime($bk['session_date'])), ENT_QUOTES, 'UTF-8');
                        $time = htmlspecialchars(date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time'])), ENT_QUOTES, 'UTF-8');
                    ?>
                        <li class="sd-session-item">
                            <img class="sd-session-item__avatar"
                                 src="<?= htmlspecialchars($bk['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" loading="lazy"
                                 onerror="this.src='/AralPal/assets/avatar-default.png'">
                            <div class="sd-session-item__info">
                                <strong><?= htmlspecialchars($bk['tutor_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars($bk['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="sd-session-item__time">
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <?= $date ?> · <?= $time ?>
                                </span>
                            </div>
                            <span class="sd-badge sd-badge--<?= htmlspecialchars($si['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($si['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Right column: Lesson Progress -->
        <section class="sd-widget" aria-labelledby="sdw-progress">
            <div class="sd-widget__head">
                <h2 id="sdw-progress">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Lesson Progress
                </h2>
                <a class="sd-widget__more" href="/AralPal/pages/progress.php">Details →</a>
            </div>

            <!-- SVG ring chart -->
            <div class="sd-ring-wrap">
                <svg class="sd-ring" viewBox="0 0 80 80" aria-label="<?= $overall_pct ?>% overall progress">
                    <circle cx="40" cy="40" r="34" fill="none" stroke="var(--clr-border)" stroke-width="8"/>
                    <circle cx="40" cy="40" r="34" fill="none" stroke="var(--clr-primary)" stroke-width="8"
                            stroke-dasharray="<?= round(213.6 * $overall_pct / 100) ?> 213.6"
                            stroke-dashoffset="53.4" stroke-linecap="round"/>
                </svg>
                <div class="sd-ring__label" aria-hidden="true">
                    <span class="sd-ring__pct"><?= $overall_pct ?>%</span>
                    <span class="sd-ring__sub">Complete</span>
                </div>
            </div>

            <!-- Per-lesson progress bars -->
            <ul class="sd-lesson-list">
                <?php foreach ($lesson_slugs as $slug => $meta):
                    $status = $lesson_progress_map[$slug] ?? 'not_started';
                    $pct    = $status === 'completed' ? 100 : ($status === 'in_progress' ? 50 : 0);
                    $bar_color = $status === 'completed' ? '#16a34a' : ($status === 'in_progress' ? '#f59e0b' : '#e2e8f0');
                    $link   = '/AralPal/pages/lesson-' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '.php';
                ?>
                    <li class="sd-lp-item">
                        <a href="<?= $link ?>" class="sd-lp-item__icon" aria-label="<?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa <?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        </a>
                        <div class="sd-lp-item__info">
                            <span class="sd-lp-item__title"><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="sd-lp-track" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="sd-lp-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>"></div>
                            </div>
                        </div>
                        <span class="sd-lp-item__pct" style="color:<?= $bar_color ?>"><?= $pct ?>%</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Quiz Scores -->
        <section class="sd-widget" aria-labelledby="sdw-quiz">
            <div class="sd-widget__head">
                <h2 id="sdw-quiz">
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i> Quiz Scores
                </h2>
                <a class="sd-widget__more" href="/AralPal/pages/quizzes.php">Take quiz →</a>
            </div>
            <?php if (empty($quiz_attempts)): ?>
                <div class="sd-empty">
                    <i class="fa-solid fa-pen-to-square fa-2x" aria-hidden="true"></i>
                    <p>No quiz attempts yet. <a href="/AralPal/pages/quizzes.php">Take a quiz</a>.</p>
                </div>
            <?php else: ?>
                <ul class="sd-quiz-list">
                    <?php foreach ($quiz_attempts as $att):
                        $pct  = $att['total'] > 0 ? round(($att['score'] / $att['total']) * 100) : 0;
                        $cls  = $pct >= 75 ? 'good' : ($pct >= 50 ? 'ok' : 'low');
                        $lbl  = ucwords(str_replace('-', ' ', $att['lesson_slug']));
                        $when = htmlspecialchars(date('M j', strtotime($att['created_at'])), ENT_QUOTES, 'UTF-8');
                    ?>
                        <li class="sd-qs-item">
                            <div class="sd-qs-item__info">
                                <span class="sd-qs-item__name"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="sd-qs-item__date"><?= $when ?></span>
                            </div>
                            <div class="sd-qs-score sd-qs-score--<?= $cls ?>">
                                <?= (int)$att['score'] ?>/<?= (int)$att['total'] ?>
                                <small><?= $pct ?>%</small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Favourite Tutors -->
        <section class="sd-widget" aria-labelledby="sdw-favs">
            <div class="sd-widget__head">
                <h2 id="sdw-favs">
                    <i class="fa-solid fa-heart" aria-hidden="true"></i> Favourite Tutors
                </h2>
                <a class="sd-widget__more" href="/AralPal/tutors/index.php">Browse →</a>
            </div>
            <?php if (empty($favorites)): ?>
                <div class="sd-empty">
                    <i class="fa-regular fa-heart fa-2x" aria-hidden="true"></i>
                    <p>No favourites yet. <a href="/AralPal/tutors/index.php">Browse tutors</a>.</p>
                </div>
            <?php else: ?>
                <div class="sd-fav-row">
                    <?php foreach ($favorites as $fav): ?>
                        <a class="sd-fav-chip" href="/AralPal/tutors/profile.php?id=<?= (int)$fav['tutor_id'] ?>">
                            <img src="<?= htmlspecialchars($fav['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars(explode(' ', $fav['tutor_name'])[0], ENT_QUOTES, 'UTF-8') ?>"
                                 onerror="this.src='/AralPal/assets/avatar-default.png'">
                            <span><?= htmlspecialchars(explode(' ', $fav['tutor_name'])[0], ENT_QUOTES, 'UTF-8') ?></span>
                            <small>₱<?= number_format((float)$fav['hourly_rate'], 0) ?>/hr</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Notifications Feed (full width) -->
        <section class="sd-widget sd-widget--full" aria-labelledby="sdw-notifs">
            <div class="sd-widget__head">
                <h2 id="sdw-notifs">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i> Notifications
                    <?php if ($unread_count): ?>
                        <span class="sd-notif-count" aria-label="<?= $unread_count ?> unread">
                            <?= $unread_count ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <a class="sd-widget__more" href="/AralPal/notifications/index.php">All →</a>
            </div>
            <?php if (empty($notifications)): ?>
                <div class="sd-empty">
                    <i class="fa-regular fa-bell-slash fa-2x" aria-hidden="true"></i>
                    <p>No unread notifications.</p>
                </div>
            <?php else: ?>
                <ul class="sd-notif-feed">
                    <?php foreach ($notifications as $n):
                        $diff = time() - strtotime($n['created_at']);
                        $ago  = $diff < 60
                            ? 'Just now'
                            : ($diff < 3600
                                ? floor($diff / 60) . 'm ago'
                                : ($diff < 86400
                                    ? floor($diff / 3600) . 'h ago'
                                    : htmlspecialchars(date('M j', strtotime($n['created_at'])), ENT_QUOTES, 'UTF-8')));
                    ?>
                        <li class="sd-notif-item">
                            <span class="sd-notif-dot" aria-hidden="true"></span>
                            <div>
                                <p><?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                <time><?= $ago ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div><!-- /.sd-grid -->

    <!-- ── Quick Actions ──────────────────────────────────────────────── -->
    <nav class="sd-actions" aria-label="Quick actions">
        <a class="sd-action-btn" href="/AralPal/tutors/index.php">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Find a Tutor
        </a>
        <a class="sd-action-btn" href="/AralPal/booking/my-bookings.php">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i> My Bookings
        </a>
        <a class="sd-action-btn" href="/AralPal/pages/quizzes.php">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Take a Quiz
        </a>
        <a class="sd-action-btn" href="/AralPal/pages/progress.php">
            <i class="fa-solid fa-chart-line" aria-hidden="true"></i> My Progress
        </a>
        <a class="sd-action-btn" href="/AralPal/subscriptions/plans.php">
            <i class="fa-solid fa-crown" aria-hidden="true"></i> Plans
        </a>
    </nav>

</div><!-- /.sd-wrap -->

<style>
/* ══════════════════════════════════════════════════════════════════════════
   Student Dashboard  — all rules scoped to .sd-* prefix
   Uses only CSS variables from the app design system.
   Safe hex values used only for stat-icon backgrounds/colours and
   colour-coded quiz score badges (accessibility contrast confirmed).
   ══════════════════════════════════════════════════════════════════════════ */

/* Wrapper */
.sd-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem clamp(1rem, 4vw, 2.5rem);
}

/* ── Welcome Banner ────────────────────────────────────────────────────── */
.sd-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    background: var(--clr-primary);
    color: #fff;
    border-radius: var(--radius-md);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
}

.sd-banner__eyebrow {
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    opacity: .75;
    margin-bottom: .25rem;
}

.sd-banner__name {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 .25rem;
    color: #fff;
}

.sd-banner__sub {
    opacity: .8;
    font-size: .95rem;
    margin: 0;
}

.sd-banner__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: .5rem;
}

/* Plan chip */
.sd-plan-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem 1rem;
    border-radius: 99px;
    font-size: .82rem;
    font-weight: 700;
}

.sd-plan-chip--pro {
    background: #fef9c3;
    color: #854d0e;
}

.sd-plan-chip--free {
    background: rgba(255, 255, 255, .2);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, .3);
}

.sd-plan-expiry {
    font-size: .78rem;
    opacity: .75;
    color: #fff;
    text-align: right;
    margin: 0;
}

.sd-upgrade-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .4rem .9rem;
    background: rgba(255, 255, 255, .2);
    color: #fff;
    border-radius: 99px;
    font-size: .82rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, .35);
    transition: background var(--transition);
}

.sd-upgrade-btn:hover {
    background: rgba(255, 255, 255, .35);
}

/* ── Stat Cards ─────────────────────────────────────────────────────────── */
.sd-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.sd-stat {
    display: flex;
    align-items: center;
    gap: .9rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1rem 1.4rem;
    flex: 1 1 160px;
    box-shadow: var(--shadow-sm);
}

.sd-stat__icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.sd-stat__val {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    color: var(--clr-text);
    margin: 0;
}

.sd-stat__lbl {
    font-size: .75rem;
    color: var(--clr-muted);
    margin: .15rem 0 0;
}

/* ── Main Grid ──────────────────────────────────────────────────────────── */
.sd-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 860px) {
    .sd-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Widgets ────────────────────────────────────────────────────────────── */
.sd-widget {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}

/* Wide widget spans both columns */
.sd-widget--wide {
    grid-column: 1 / -1;
}

/* Full-width widget spans the whole grid row */
.sd-widget--full {
    grid-column: 1 / -1;
}

.sd-widget__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}

.sd-widget__head h2 {
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: .5rem;
    margin: 0;
    color: var(--clr-text);
}

.sd-widget__more {
    font-size: .82rem;
    color: var(--clr-primary);
    text-decoration: none;
    white-space: nowrap;
}

.sd-widget__more:hover {
    text-decoration: underline;
}

/* Empty state */
.sd-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    padding: 2rem 1rem;
    color: var(--clr-muted);
    text-align: center;
}

.sd-empty i {
    opacity: .4;
}

/* ── Upcoming Sessions ──────────────────────────────────────────────────── */
.sd-session-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

.sd-session-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .9rem 1rem;
    background: var(--clr-bg);
    border-radius: var(--radius-md);
    border: 1px solid var(--clr-border);
}

.sd-session-item__avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--clr-border);
    flex-shrink: 0;
}

.sd-session-item__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: .15rem;
    min-width: 0;
}

.sd-session-item__info strong {
    font-size: .95rem;
    color: var(--clr-text);
}

.sd-session-item__info span {
    font-size: .82rem;
    color: var(--clr-muted);
}

.sd-session-item__time {
    display: flex;
    align-items: center;
    gap: .3rem;
    color: var(--clr-muted) !important;
}

/* Status badges */
.sd-badge {
    display: inline-block;
    padding: .22rem .65rem;
    border-radius: 99px;
    font-size: .74rem;
    font-weight: 600;
    white-space: nowrap;
}

.sd-badge--warning   { background: #fef3c7; color: #92400e; }
.sd-badge--success   { background: #dcfce7; color: #166534; }
.sd-badge--danger    { background: #fee2e2; color: #991b1b; }
.sd-badge--info      { background: #dbeafe; color: #1e40af; }
.sd-badge--secondary { background: var(--clr-border); color: var(--clr-muted); }

/* ── Lesson Progress ────────────────────────────────────────────────────── */
.sd-ring-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    position: relative;
}

.sd-ring {
    width: 100px;
    height: 100px;
    transform: rotate(-90deg);
}

.sd-ring__label {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.sd-ring__pct {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--clr-primary);
}

.sd-ring__sub {
    font-size: .65rem;
    color: var(--clr-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
}

.sd-lesson-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .9rem;
}

.sd-lp-item {
    display: flex;
    align-items: center;
    gap: .75rem;
}

.sd-lp-item__icon {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    background: var(--clr-bg);
    color: var(--clr-primary);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    font-size: .95rem;
    text-decoration: none;
    flex-shrink: 0;
    transition: background var(--transition);
}

.sd-lp-item__icon:hover {
    background: var(--clr-border);
}

.sd-lp-item__info {
    flex: 1;
    min-width: 0;
}

.sd-lp-item__title {
    font-size: .85rem;
    font-weight: 500;
    display: block;
    margin-bottom: .3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--clr-text);
}

.sd-lp-track {
    height: 7px;
    background: var(--clr-border);
    border-radius: 99px;
    overflow: hidden;
}

.sd-lp-fill {
    height: 100%;
    border-radius: 99px;
    transition: width .4s ease;
}

.sd-lp-item__pct {
    font-size: .78rem;
    font-weight: 700;
    min-width: 32px;
    text-align: right;
}

/* ── Quiz Scores ────────────────────────────────────────────────────────── */
.sd-quiz-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .65rem;
}

.sd-qs-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem .9rem;
    background: var(--clr-bg);
    border-radius: var(--radius-md);
    border: 1px solid var(--clr-border);
}

.sd-qs-item__info {
    display: flex;
    flex-direction: column;
    gap: .1rem;
}

.sd-qs-item__name {
    font-size: .88rem;
    font-weight: 500;
    color: var(--clr-text);
}

.sd-qs-item__date {
    font-size: .75rem;
    color: var(--clr-muted);
}

.sd-qs-score {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 48px;
    font-size: .95rem;
    font-weight: 800;
    border-radius: var(--radius-md);
    padding: .3rem .6rem;
}

.sd-qs-score small {
    font-size: .7rem;
    font-weight: 500;
}

/* Colour-coded score badges — hex values used for accessible contrast */
.sd-qs-score--good { background: #dcfce7; color: #15803d; }
.sd-qs-score--ok   { background: #fef9c3; color: #a16207; }
.sd-qs-score--low  { background: #fee2e2; color: #b91c1c; }

/* ── Favourite Tutors ───────────────────────────────────────────────────── */
.sd-fav-row {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}

.sd-fav-chip {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .3rem;
    padding: .75rem .6rem;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    background: var(--clr-bg);
    text-decoration: none;
    color: var(--clr-text);
    transition: box-shadow var(--transition), transform var(--transition);
    min-width: 90px;
    text-align: center;
}

.sd-fav-chip:hover {
    box-shadow: var(--shadow-sm);
    transform: translateY(-2px);
}

.sd-fav-chip img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--clr-border);
}

.sd-fav-chip span {
    font-size: .82rem;
    font-weight: 600;
    color: var(--clr-text);
}

.sd-fav-chip small {
    font-size: .72rem;
    color: var(--clr-muted);
}

/* ── Notifications Feed ─────────────────────────────────────────────────── */
.sd-notif-count {
    display: inline-grid;
    place-items: center;
    background: #ef4444;
    color: #fff;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    vertical-align: middle;
}

.sd-notif-feed {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .65rem;
}

.sd-notif-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
}

.sd-notif-dot {
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--clr-primary);
    margin-top: .45rem;
}

.sd-notif-item p {
    font-size: .88rem;
    line-height: 1.5;
    margin: 0 0 .15rem;
    color: var(--clr-text);
}

.sd-notif-item time {
    font-size: .75rem;
    color: var(--clr-muted);
}

/* ── Quick Action Bar ───────────────────────────────────────────────────── */
.sd-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
}

.sd-action-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .65rem 1.25rem;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    font-size: .9rem;
    font-weight: 500;
    text-decoration: none;
    color: var(--clr-text);
    transition: background var(--transition), box-shadow var(--transition), transform var(--transition);
}

.sd-action-btn:hover {
    background: var(--clr-bg);
    color: var(--clr-primary);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}
</style>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
