<?php
/**
 * AralPal – Student Dashboard (Enhanced)
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
    'pending'   => ['label' => 'Pending',   'class' => 'badge-warning'],
    'accepted'  => ['label' => 'Confirmed', 'class' => 'badge-success'],
    'declined'  => ['label' => 'Declined',  'class' => 'badge-danger'],
    'completed' => ['label' => 'Done',      'class' => 'badge-info'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-secondary'],
];

$page_title = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="dash">

    <!-- ── Top Banner ──────────────────────────────────────────────────── -->
    <div class="dash-banner">
        <div class="dash-banner__left">
            <p class="dash-banner__eyebrow">Student Dashboard</p>
            <h1 class="dash-banner__name">Hello, <?= htmlspecialchars(explode(' ', $current['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>! 👋</h1>
            <p class="dash-banner__sub">Here's your learning summary for today.</p>
        </div>
        <div class="dash-banner__right">
            <?php if ($is_pro): ?>
                <div class="plan-chip plan-chip--pro">
                    <i class="fa-solid fa-crown"></i>
                    <?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <p class="plan-chip__expiry">Expires <?= date('M j, Y', strtotime($subscription['end_date'])) ?></p>
            <?php else: ?>
                <div class="plan-chip plan-chip--free">
                    <i class="fa-solid fa-user"></i> Free Plan
                </div>
                <a class="dash-upgrade-btn" href="/AralPal/subscriptions/plans.php">
                    <i class="fa-solid fa-arrow-up"></i> Upgrade
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stat Cards ──────────────────────────────────────────────────── -->
    <div class="dash-stats">
        <div class="stat-pill">
            <span class="stat-pill__icon" style="background:#e0f2fe;color:#0369a1"><i class="fa-solid fa-calendar-check"></i></span>
            <div>
                <p class="stat-pill__val"><?= (int)($booking_stats['total_bookings'] ?? 0) ?></p>
                <p class="stat-pill__lbl">Total Bookings</p>
            </div>
        </div>
        <div class="stat-pill">
            <span class="stat-pill__icon" style="background:#dcfce7;color:#15803d"><i class="fa-solid fa-circle-check"></i></span>
            <div>
                <p class="stat-pill__val"><?= (int)($booking_stats['completed'] ?? 0) ?></p>
                <p class="stat-pill__lbl">Completed</p>
            </div>
        </div>
        <div class="stat-pill">
            <span class="stat-pill__icon" style="background:#fef9c3;color:#a16207"><i class="fa-solid fa-book-open"></i></span>
            <div>
                <p class="stat-pill__val"><?= $completed_lessons ?>/<?= $total_lessons ?></p>
                <p class="stat-pill__lbl">Lessons Done</p>
            </div>
        </div>
        <div class="stat-pill">
            <span class="stat-pill__icon" style="background:#fce7f3;color:#9d174d"><i class="fa-solid fa-pen-to-square"></i></span>
            <div>
                <p class="stat-pill__val"><?= count($quiz_attempts) ?></p>
                <p class="stat-pill__lbl">Quiz Attempts</p>
            </div>
        </div>
        <div class="stat-pill">
            <span class="stat-pill__icon" style="background:#ede9fe;color:#6d28d9"><i class="fa-solid fa-bell"></i></span>
            <div>
                <p class="stat-pill__val"><?= $unread_count ?></p>
                <p class="stat-pill__lbl">Notifications</p>
            </div>
        </div>
    </div>

    <!-- ── Main Grid ───────────────────────────────────────────────────── -->
    <div class="dash-grid">

        <!-- Upcoming Bookings -->
        <section class="dwidget dwidget--wide" aria-labelledby="dw-bookings">
            <div class="dwidget__head">
                <h2 id="dw-bookings"><i class="fa-regular fa-calendar-days"></i> Upcoming Sessions</h2>
                <a class="dwidget__more" href="/AralPal/booking/my-bookings.php">View all →</a>
            </div>
            <?php if (empty($upcoming_bookings)): ?>
                <div class="dwidget__empty">
                    <i class="fa-regular fa-calendar-xmark fa-2x"></i>
                    <p>No upcoming sessions. <a href="/AralPal/tutors/index.php">Find a tutor</a> to book one.</p>
                </div>
            <?php else: ?>
                <ul class="session-list">
                    <?php foreach ($upcoming_bookings as $bk):
                        $si   = $status_badges[$bk['status']] ?? ['label' => $bk['status'], 'class' => 'badge-secondary'];
                        $date = date('D, M j', strtotime($bk['session_date']));
                        $time = date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']));
                    ?>
                        <li class="session-item">
                            <img class="session-item__avatar"
                                 src="<?= htmlspecialchars($bk['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" loading="lazy" onerror="this.src='/AralPal/assets/avatar-default.png'">
                            <div class="session-item__info">
                                <strong><?= htmlspecialchars($bk['tutor_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars($bk['subject_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="session-item__time">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= htmlspecialchars($date . ' · ' . $time, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <span class="dbadge dbadge--<?= $si['class'] ?>"><?= $si['label'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Lesson Progress -->
        <section class="dwidget" aria-labelledby="dw-progress">
            <div class="dwidget__head">
                <h2 id="dw-progress"><i class="fa-solid fa-chart-line"></i> Lesson Progress</h2>
                <a class="dwidget__more" href="/AralPal/pages/progress.php">Details →</a>
            </div>

            <!-- Overall progress ring -->
            <div class="progress-ring-wrap">
                <svg class="progress-ring" viewBox="0 0 80 80" aria-hidden="true">
                    <circle cx="40" cy="40" r="34" fill="none" stroke="var(--clr-border)" stroke-width="8"/>
                    <circle cx="40" cy="40" r="34" fill="none" stroke="var(--clr-primary)" stroke-width="8"
                            stroke-dasharray="<?= round(213.6 * $overall_pct / 100) ?> 213.6"
                            stroke-dashoffset="53.4" stroke-linecap="round"/>
                </svg>
                <div class="progress-ring__label">
                    <span class="progress-ring__pct"><?= $overall_pct ?>%</span>
                    <span class="progress-ring__sub">Complete</span>
                </div>
            </div>

            <ul class="lesson-progress-list">
                <?php foreach ($lesson_slugs as $slug => $meta):
                    $status = $lesson_progress_map[$slug] ?? 'not_started';
                    $pct    = $status === 'completed' ? 100 : ($status === 'in_progress' ? 50 : 0);
                    $color  = $status === 'completed' ? '#16a34a' : ($status === 'in_progress' ? '#f59e0b' : '#e2e8f0');
                    $link   = '/AralPal/pages/lesson-' . $slug . '.php';
                ?>
                    <li class="lp-item">
                        <a href="<?= $link ?>" class="lp-item__icon"><i class="fa <?= $meta['icon'] ?>"></i></a>
                        <div class="lp-item__info">
                            <span class="lp-item__title"><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="lp-track">
                                <div class="lp-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                            </div>
                        </div>
                        <span class="lp-item__pct" style="color:<?= $color ?>"><?= $pct ?>%</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Quiz Scores -->
        <section class="dwidget" aria-labelledby="dw-quiz">
            <div class="dwidget__head">
                <h2 id="dw-quiz"><i class="fa-solid fa-trophy"></i> Quiz Scores</h2>
                <a class="dwidget__more" href="/AralPal/pages/quizzes.php">Take quiz →</a>
            </div>
            <?php if (empty($quiz_attempts)): ?>
                <div class="dwidget__empty">
                    <i class="fa-solid fa-pen-to-square fa-2x"></i>
                    <p>No quiz attempts yet. <a href="/AralPal/pages/quizzes.php">Take a quiz</a>.</p>
                </div>
            <?php else: ?>
                <ul class="quiz-score-list">
                    <?php foreach ($quiz_attempts as $att):
                        $pct   = $att['total'] > 0 ? round(($att['score'] / $att['total']) * 100) : 0;
                        $cls   = $pct >= 75 ? 'good' : ($pct >= 50 ? 'ok' : 'low');
                        $lbl   = ucwords(str_replace('-', ' ', $att['lesson_slug']));
                        $when  = date('M j', strtotime($att['created_at']));
                    ?>
                        <li class="qs-item">
                            <div class="qs-item__info">
                                <span class="qs-item__name"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="qs-item__date muted"><?= $when ?></span>
                            </div>
                            <div class="qs-score qs-score--<?= $cls ?>">
                                <?= (int)$att['score'] ?>/<?= (int)$att['total'] ?>
                                <small><?= $pct ?>%</small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Favorite Tutors -->
        <section class="dwidget" aria-labelledby="dw-favs">
            <div class="dwidget__head">
                <h2 id="dw-favs"><i class="fa-solid fa-heart"></i> Favourite Tutors</h2>
                <a class="dwidget__more" href="/AralPal/tutors/index.php">Browse →</a>
            </div>
            <?php if (empty($favorites)): ?>
                <div class="dwidget__empty">
                    <i class="fa-regular fa-heart fa-2x"></i>
                    <p>No favourites yet. <a href="/AralPal/tutors/index.php">Browse tutors</a>.</p>
                </div>
            <?php else: ?>
                <div class="fav-row">
                    <?php foreach ($favorites as $fav): ?>
                        <a class="fav-chip" href="/AralPal/tutors/profile.php?id=<?= (int)$fav['tutor_id'] ?>">
                            <img src="<?= htmlspecialchars($fav['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" onerror="this.src='/AralPal/assets/avatar-default.png'">
                            <span><?= htmlspecialchars(explode(' ', $fav['tutor_name'])[0], ENT_QUOTES, 'UTF-8') ?></span>
                            <small>₱<?= number_format((float)$fav['hourly_rate'], 0) ?>/hr</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Notifications -->
        <section class="dwidget" aria-labelledby="dw-notifs">
            <div class="dwidget__head">
                <h2 id="dw-notifs">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <?php if ($unread_count): ?>
                        <span class="notif-count"><?= $unread_count ?></span>
                    <?php endif; ?>
                </h2>
                <a class="dwidget__more" href="/AralPal/notifications/index.php">All →</a>
            </div>
            <?php if (empty($notifications)): ?>
                <div class="dwidget__empty">
                    <i class="fa-regular fa-bell-slash fa-2x"></i>
                    <p>No unread notifications.</p>
                </div>
            <?php else: ?>
                <ul class="notif-feed">
                    <?php foreach ($notifications as $n):
                        $diff = time() - strtotime($n['created_at']);
                        $ago  = $diff < 60 ? 'Just now' : ($diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : date('M j', strtotime($n['created_at']))));
                    ?>
                        <li class="notif-feed__item">
                            <span class="notif-dot"></span>
                            <div>
                                <p><?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                <time class="muted"><?= $ago ?></time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div><!-- /.dash-grid -->

    <!-- ── Quick Actions ───────────────────────────────────────────────── -->
    <div class="dash-actions">
        <a class="dash-action-btn" href="/AralPal/tutors/index.php">
            <i class="fa-solid fa-magnifying-glass"></i> Find a Tutor
        </a>
        <a class="dash-action-btn" href="/AralPal/booking/my-bookings.php">
            <i class="fa-solid fa-calendar-days"></i> My Bookings
        </a>
        <a class="dash-action-btn" href="/AralPal/pages/quizzes.php">
            <i class="fa-solid fa-pen-to-square"></i> Take a Quiz
        </a>
        <a class="dash-action-btn" href="/AralPal/pages/progress.php">
            <i class="fa-solid fa-chart-line"></i> My Progress
        </a>
        <a class="dash-action-btn" href="/AralPal/subscriptions/plans.php">
            <i class="fa-solid fa-crown"></i> Plans
        </a>
    </div>

</main>

<style>
/* ── Student Dashboard ─────────────────────────────────────────────────── */
.dash { max-width: 1200px; margin: 0 auto; padding: 2rem clamp(1rem,4vw,2.5rem); }

/* Banner */
.dash-banner {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 1.25rem;
    background: linear-gradient(135deg, var(--clr-primary) 0%, #1a56db 100%);
    color: #fff; border-radius: var(--radius-lg);
    padding: 2rem 2.5rem; margin-bottom: 2rem;
}
.dash-banner__eyebrow { font-size: .75rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; opacity: .75; margin-bottom: .25rem; }
.dash-banner__name    { font-size: 1.75rem; font-weight: 800; margin-bottom: .25rem; }
.dash-banner__sub     { opacity: .8; font-size: .95rem; }
.dash-banner__right   { display: flex; flex-direction: column; align-items: flex-end; gap: .5rem; }
.plan-chip            { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem 1rem; border-radius: 99px; font-size: .82rem; font-weight: 700; }
.plan-chip--pro       { background: #fef9c3; color: #854d0e; }
.plan-chip--free      { background: rgba(255,255,255,.2); color: #fff; }
.plan-chip__expiry    { font-size: .78rem; opacity: .75; color: #fff; text-align: right; }
.dash-upgrade-btn     { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .9rem; background: rgba(255,255,255,.2); color: #fff; border-radius: 99px; font-size: .82rem; font-weight: 600; text-decoration: none; border: 1px solid rgba(255,255,255,.3); transition: background .2s; }
.dash-upgrade-btn:hover { background: rgba(255,255,255,.35); }

/* Stat pills */
.dash-stats { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
.stat-pill  { display: flex; align-items: center; gap: .9rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1rem 1.4rem; flex: 1 1 160px; box-shadow: var(--shadow-sm); }
.stat-pill__icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 10px; font-size: 1.1rem; flex-shrink: 0; }
.stat-pill__val  { font-size: 1.4rem; font-weight: 800; line-height: 1; color: var(--clr-text); }
.stat-pill__lbl  { font-size: .75rem; color: var(--clr-muted); margin-top: .15rem; }

/* Main grid */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 860px) { .dash-grid { grid-template-columns: 1fr; } }

/* Widgets */
.dwidget { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1.5rem; box-shadow: var(--shadow-sm); }
.dwidget--wide { grid-column: 1 / -1; }
.dwidget__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.dwidget__head h2 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
.dwidget__more { font-size: .82rem; color: var(--clr-primary); text-decoration: none; white-space: nowrap; }
.dwidget__more:hover { text-decoration: underline; }
.dwidget__empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .75rem; padding: 2rem 1rem; color: var(--clr-muted); text-align: center; }
.dwidget__empty i { opacity: .4; }

/* Session list */
.session-list { list-style: none; display: flex; flex-direction: column; gap: .75rem; }
.session-item { display: flex; align-items: center; gap: 1rem; padding: .9rem 1rem; background: var(--clr-bg); border-radius: var(--radius-sm); border: 1px solid var(--clr-border); }
.session-item__avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--clr-primary-light); flex-shrink: 0; }
.session-item__info { flex: 1; display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
.session-item__info strong { font-size: .95rem; }
.session-item__info span  { font-size: .82rem; color: var(--clr-muted); }
.session-item__time { display: flex; align-items: center; gap: .3rem; color: var(--clr-muted) !important; }

/* Progress ring */
.progress-ring-wrap { display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; position: relative; }
.progress-ring { width: 100px; height: 100px; transform: rotate(-90deg); }
.progress-ring__label { position: absolute; display: flex; flex-direction: column; align-items: center; }
.progress-ring__pct  { font-size: 1.3rem; font-weight: 800; color: var(--clr-primary); }
.progress-ring__sub  { font-size: .65rem; color: var(--clr-muted); text-transform: uppercase; letter-spacing: .05em; }

/* Lesson progress list */
.lesson-progress-list { list-style: none; display: flex; flex-direction: column; gap: .9rem; }
.lp-item { display: flex; align-items: center; gap: .75rem; }
.lp-item__icon { width: 32px; height: 32px; display: grid; place-items: center; background: var(--clr-primary-light); color: var(--clr-primary); border-radius: 8px; font-size: .95rem; text-decoration: none; flex-shrink: 0; }
.lp-item__info { flex: 1; min-width: 0; }
.lp-item__title { font-size: .85rem; font-weight: 500; display: block; margin-bottom: .3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lp-track { height: 7px; background: var(--clr-border); border-radius: 99px; overflow: hidden; }
.lp-fill  { height: 100%; border-radius: 99px; transition: width .4s ease; }
.lp-item__pct { font-size: .78rem; font-weight: 700; min-width: 32px; text-align: right; }

/* Quiz scores */
.quiz-score-list { list-style: none; display: flex; flex-direction: column; gap: .65rem; }
.qs-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem .9rem; background: var(--clr-bg); border-radius: var(--radius-sm); border: 1px solid var(--clr-border); }
.qs-item__info { display: flex; flex-direction: column; }
.qs-item__name { font-size: .88rem; font-weight: 500; }
.qs-item__date { font-size: .75rem; }
.qs-score { display: flex; flex-direction: column; align-items: center; min-width: 48px; font-size: .95rem; font-weight: 800; border-radius: 8px; padding: .3rem .6rem; }
.qs-score small { font-size: .7rem; font-weight: 500; }
.qs-score--good { background: #dcfce7; color: #15803d; }
.qs-score--ok   { background: #fef9c3; color: #a16207; }
.qs-score--low  { background: #fee2e2; color: #b91c1c; }

/* Favourites */
.fav-row { display: flex; flex-wrap: wrap; gap: .75rem; }
.fav-chip { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: .75rem .6rem; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); background: var(--clr-bg); text-decoration: none; color: inherit; transition: box-shadow .2s, transform .2s; min-width: 90px; text-align: center; }
.fav-chip:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.fav-chip img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--clr-primary-light); }
.fav-chip span { font-size: .82rem; font-weight: 600; }
.fav-chip small { font-size: .72rem; color: var(--clr-muted); }

/* Notifications */
.notif-count { display: inline-grid; place-items: center; background: var(--clr-danger); color: #fff; border-radius: 99px; font-size: .68rem; font-weight: 800; min-width: 18px; height: 18px; padding: 0 4px; }
.notif-feed { list-style: none; display: flex; flex-direction: column; gap: .65rem; }
.notif-feed__item { display: flex; align-items: flex-start; gap: .75rem; }
.notif-dot { flex-shrink: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--clr-primary); margin-top: .45rem; }
.notif-feed__item p { font-size: .88rem; line-height: 1.5; margin: 0 0 .15rem; }
.notif-feed__item time { font-size: .75rem; color: var(--clr-muted); }

/* Quick actions */
.dash-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
.dash-action-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .65rem 1.25rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-size: .9rem; font-weight: 500; text-decoration: none; color: var(--clr-text); transition: background .2s, box-shadow .2s, transform .2s; }
.dash-action-btn:hover { background: var(--clr-primary-light); color: var(--clr-primary); box-shadow: var(--shadow-sm); transform: translateY(-1px); }

/* Badge helpers */
.dbadge { display: inline-block; padding: .22rem .6rem; border-radius: 99px; font-size: .74rem; font-weight: 600; white-space: nowrap; }
.dbadge--badge-warning   { background: #fef3c7; color: #92400e; }
.dbadge--badge-success   { background: #dcfce7; color: #166534; }
.dbadge--badge-danger    { background: #fee2e2; color: #991b1b; }
.dbadge--badge-info      { background: #dbeafe; color: #1e40af; }
.dbadge--badge-secondary { background: var(--clr-border); color: var(--clr-muted); }

.muted { color: var(--clr-muted); font-size: .82rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
