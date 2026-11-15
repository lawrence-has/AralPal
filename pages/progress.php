<?php
/**
 * AralPal – My Learning Progress
 *
 * Displays all active lessons from the lessons table with the student's
 * actual progress (not started / in progress / completed) and quiz stats.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Only Students should be here; redirect others
$_p_user = current_user();
$_p_role = strtolower((string)($_p_user['role_name'] ?? ''));
if ($_p_role === 'tutor') { header('Location: /AralPal/dashboard/tutor.php'); exit; }
if ($_p_role === 'admin') { header('Location: /AralPal/admin/users.php'); exit; }
if ($_p_role !== 'student') {
    http_response_code(403);
    exit('Access denied.');
}

$db      = get_db();
$userId  = (int)$_p_user['id'];

$pageTitle = "My Learning Progress – AralPal";

// ── Load all active lessons from DB ──────────────────────────────────────
$lessonStmt = $db->query("SELECT slug, category, title, description, difficulty, duration_min, sort_order FROM lessons WHERE is_active = 1 ORDER BY sort_order ASC");
$lessons   = $lessonStmt->fetchAll();

// ── Fetch real progress per slug ──────────────────────────────────────────
$progressRows = [];
$progStmt = $db->prepare('SELECT lesson_slug, status FROM lesson_progress WHERE user_id = ?');
$progStmt->execute([$userId]);
foreach ($progStmt->fetchAll() as $row) {
    $progressRows[$row['lesson_slug']] = $row['status'];
}

// ── Fetch quiz stats (graceful if table absent) ────────────────────────────
$quizStats = [];
try {
    $qStmt = $db->prepare(
        'SELECT lesson_slug,
                MAX(score / total * 100) AS best_pct,
                COUNT(*) AS attempts
         FROM quiz_attempts
         WHERE user_id = ?
         GROUP BY lesson_slug'
    );
    $qStmt->execute([$userId]);
    foreach ($qStmt->fetchAll() as $row) {
        $quizStats[$row['lesson_slug']] = [
            'best_pct' => round((float) $row['best_pct']),
            'attempts' => (int)  $row['attempts'],
        ];
    }
} catch (PDOException $e) {
    // quiz_attempts table not yet created — use empty stats
}

// ── Build activities array ─────────────────────────────────────────────────
$activities = [];
foreach ($lessons as $l) {
    $slug     = $l['slug'];
    $dbStatus = $progressRows[$slug] ?? 'not_started';
    $pct      = match ($dbStatus) {
        'completed'   => 100,
        'in_progress' => 50,
        default       => 0,
    };
    $activities[] = [
        'slug'     => $slug,
        'title'    => $l['title'],
        'category' => $l['category'],
        'difficulty' => $l['difficulty'],
        'duration' => (int)$l['duration_min'] . ' min',
        'status'   => $dbStatus,
        'progress' => $pct,
        'link'     => '/AralPal/pages/learn/lesson.php?slug=' . urlencode($slug),
    ];
}

// ── Summary counts ─────────────────────────────────────────────────────────
$completed       = count(array_filter($activities, fn($a) => $a['status'] === 'completed'));
$inProgress      = count(array_filter($activities, fn($a) => $a['status'] === 'in_progress'));
$notStarted      = count(array_filter($activities, fn($a) => $a['status'] === 'not_started'));
$overallProgress = count($activities) > 0
    ? round(array_sum(array_column($activities, 'progress')) / count($activities))
    : 0;

$page_title = $pageTitle;
require_once __DIR__ . '/../includes/app-header.php';
?>

<style>
.pr-wrap { max-width: 1000px; }
.pr-header { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.pr-header h1 { font-size:1.5rem; font-weight:800; margin:0 0 .25rem; }
.pr-header p { color: var(--clr-muted); margin:0; font-size:.9rem; }

.pr-summary { display:grid; grid-template-columns: 2fr repeat(4, 1fr); gap:1rem; margin-bottom:1.75rem; }
@media (max-width:768px) { .pr-summary { grid-template-columns: 1fr 1fr; } }
.pr-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:1rem 1.25rem; box-shadow:var(--shadow-sm); display:flex; flex-direction:column; gap:.3rem; }
.pr-card--overall { grid-row: span 1; }
.pr-card__label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-muted); }
.pr-card__big { font-size:1.6rem; font-weight:800; color:var(--clr-primary); }
.pr-bar-wrap { height:8px; background:var(--clr-border); border-radius:99px; overflow:hidden; margin-top:.5rem; }
.pr-bar-fill { height:100%; background:var(--clr-primary); border-radius:99px; transition:width .4s ease; }
.pr-card__icon { font-size:1.4rem; margin-bottom:.25rem; }

.pr-section { margin-bottom:1.75rem; }
.pr-section h2 { font-size:1.05rem; font-weight:700; margin:0 0 .9rem; display:flex; align-items:center; gap:.5rem; }
.pr-list { display:flex; flex-direction:column; gap:.65rem; }
.pr-item { display:flex; align-items:center; gap:1rem; padding:.9rem 1.1rem; background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-md); box-shadow:var(--shadow-sm); }
.pr-item--completed { border-left:3px solid #16a34a; }
.pr-item--in-progress { border-left:3px solid #f59e0b; }
.pr-item__icon { width:40px; height:40px; display:grid; place-items:center; background:var(--clr-bg); border:1px solid var(--clr-border); border-radius:var(--radius-md); flex-shrink:0; }
.pr-item__body { flex:1; min-width:0; }
.pr-item__cat { font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-muted); }
.pr-item__title { font-size:.92rem; font-weight:600; margin:.15rem 0; }
.pr-item__meta { display:flex; gap:1rem; font-size:.78rem; color:var(--clr-muted); }
.pr-item__pct { font-size:.85rem; font-weight:700; min-width:36px; text-align:right; }
.pr-item__bar { height:6px; background:var(--clr-border); border-radius:99px; overflow:hidden; margin-top:.4rem; }
.pr-item__bar-fill { height:100%; border-radius:99px; }

.pr-badge { display:inline-block; padding:.15em .55em; border-radius:99px; font-size:.68rem; font-weight:600; }
.pr-badge--completed { background:#dcfce7; color:#166534; }
.pr-badge--in-progress { background:#fef9c3; color:#92400e; }
.pr-badge--not-started { background:var(--clr-bg); color:var(--clr-muted); border:1px solid var(--clr-border); }

.pr-quiz-banner { display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-md); }
.pr-quiz-banner__icon { font-size:2rem; color:var(--clr-primary); }
.pr-actions { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.btn { display:inline-block; padding:.4rem .9rem; border-radius:var(--radius-md); font-weight:600; font-size:.82rem; text-decoration:none; border:2px solid transparent; cursor:pointer; }
.btn-sm { padding:.3rem .7rem; font-size:.78rem; }
.btn-primary { background:var(--clr-primary); color:#fff; }
.btn-outline { background:transparent; border-color:var(--clr-primary); color:var(--clr-primary); }
.btn-success { background:#16a34a; color:#fff; }
</style>

<div class="pr-wrap">

    <div class="pr-header">
        <div>
            <span class="pr-badge pr-badge--not-started" style="font-size:.72rem">LEARNING DASHBOARD</span>
            <h1>My Learning Progress</h1>
            <p>Track your learning journey across all lessons and quizzes.</p>
        </div>
        <a class="btn btn-primary" href="/AralPal/pages/quizzes.php">Take a Quiz →</a>
    </div>

    <!-- ── Overall Summary ── -->
    <div class="pr-summary">
        <div class="pr-card pr-card--overall">
            <span class="pr-card__label">Overall Progress</span>
            <span class="pr-card__big"><?= $overallProgress ?>%</span>
            <div class="pr-bar-wrap">
                <div class="pr-bar-fill" style="width:<?= $overallProgress ?>%"></div>
            </div>
        </div>
        <div class="pr-card">
            <span class="pr-card__icon" style="color:#16a34a"><i class="fa-solid fa-circle-check"></i></span>
            <span class="pr-card__big" style="font-size:1.3rem"><?= $completed ?></span>
            <span class="pr-card__label">Completed</span>
        </div>
        <div class="pr-card">
            <span class="pr-card__icon" style="color:#f59e0b"><i class="fa-solid fa-spinner"></i></span>
            <span class="pr-card__big" style="font-size:1.3rem"><?= $inProgress ?></span>
            <span class="pr-card__label">In Progress</span>
        </div>
        <div class="pr-card">
            <span class="pr-card__icon" style="color:var(--clr-muted)"><i class="fa-solid fa-book-open"></i></span>
            <span class="pr-card__big" style="font-size:1.3rem"><?= $notStarted ?></span>
            <span class="pr-card__label">Not Started</span>
        </div>
        <div class="pr-card">
            <span class="pr-card__icon" style="color:var(--clr-primary)"><i class="fa-solid fa-layer-group"></i></span>
            <span class="pr-card__big" style="font-size:1.3rem"><?= count($activities) ?></span>
            <span class="pr-card__label">Total Lessons</span>
        </div>
    </div>

    <!-- ── Lesson Breakdown ── -->
    <section class="pr-section">
        <h2><i class="fa-solid fa-list-check"></i> Lesson Breakdown</h2>
        <div class="pr-list">
            <?php foreach ($activities as $a):
                $statusClass = match($a['status']) {
                    'completed'   => 'pr-item--completed',
                    'in_progress' => 'pr-item--in-progress',
                    default       => '',
                };
                $barColor = match($a['status']) {
                    'completed'   => '#16a34a',
                    'in_progress' => '#f59e0b',
                    default       => '#e2e8f0',
                };
                $badgeClass = match($a['status']) {
                    'completed'   => 'pr-badge--completed',
                    'in_progress' => 'pr-badge--in-progress',
                    default       => 'pr-badge--not-started',
                };
                $badgeLabel = match($a['status']) {
                    'completed'   => 'Completed',
                    'in_progress' => 'In Progress',
                    default       => 'Not Started',
                };
                $actionLabel = match($a['status']) {
                    'completed'   => 'Review',
                    'in_progress' => 'Continue',
                    default       => 'Start',
                };
                $actionClass = match($a['status']) {
                    'completed'   => 'btn-success',
                    'in_progress' => 'btn-primary',
                    default       => 'btn-outline',
                };
            ?>
            <article class="pr-item <?= $statusClass ?>">
                <div class="pr-item__icon"><i class="fa-solid fa-book-open"></i></div>
                <div class="pr-item__body">
                    <span class="pr-item__cat"><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="pr-item__title"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="pr-item__meta">
                        <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($a['duration'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span><i class="fa-solid fa-signal"></i> <?= htmlspecialchars($a['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="pr-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                    </div>
                    <div class="pr-item__bar">
                        <div class="pr-item__bar-fill" style="width:<?= $a['progress'] ?>%;background:<?= $barColor ?>"></div>
                    </div>
                </div>
                <span class="pr-item__pct" style="color:<?= $barColor ?>"><?= $a['progress'] ?>%</span>
                <div class="pr-actions">
                    <a class="btn btn-sm <?= $actionClass ?>" href="<?= htmlspecialchars($a['link'], ENT_QUOTES, 'UTF-8') ?>"><?= $actionLabel ?></a>
                    <?php
                    $qs = $quizStats[$a['slug']] ?? null;
                    if ($qs):
                    ?>
                    <a class="btn btn-sm btn-outline" href="/AralPal/pages/quizzes.php?quiz=<?= urlencode($a['slug']) ?>">Quiz: <?= $qs['best_pct'] ?>%</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Quiz Activity ── -->
    <section class="pr-section">
        <h2><i class="fa-solid fa-pen-to-square"></i> Quiz Activity</h2>
        <?php if (!empty($quizStats)): ?>
        <div class="pr-list">
            <?php foreach ($quizStats as $qslug => $qs):
                // Find the lesson title
                $qTitle = 'Unknown';
                foreach ($activities as $a) {
                    if ($a['slug'] === $qslug) { $qTitle = $a['title']; break; }
                }
            ?>
            <article class="pr-item">
                <div class="pr-item__icon"><i class="fa-solid fa-star"></i></div>
                <div class="pr-item__body">
                    <span class="pr-item__cat">Quiz</span>
                    <div class="pr-item__title"><?= htmlspecialchars($qTitle, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="pr-item__meta">
                        <span>Best score: <?= $qs['best_pct'] ?>%</span>
                        <span>Attempts: <?= $qs['attempts'] ?></span>
                    </div>
                    <div class="pr-item__bar">
                        <div class="pr-item__bar-fill" style="width:<?= $qs['best_pct'] ?>%;background:var(--clr-primary)"></div>
                    </div>
                </div>
                <span class="pr-item__pct" style="color:var(--clr-primary)"><?= $qs['best_pct'] ?>%</span>
                <div class="pr-actions">
                    <a class="btn btn-sm btn-outline" href="/AralPal/pages/quizzes.php?quiz=<?= urlencode($qslug) ?>">Retry</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pr-quiz-banner">
            <span class="pr-quiz-banner__icon"><i class="fa-solid fa-pen-to-square"></i></span>
            <div style="flex:1">
                <strong>Test your knowledge</strong>
                <p style="margin:.25rem 0 0;color:var(--clr-muted);font-size:.88rem">Take quizzes to reinforce what you've learned and track your understanding over time.</p>
            </div>
            <a class="btn btn-primary" href="/AralPal/pages/quizzes.php">Go to Quizzes →</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Achievements ── -->
    <section class="pr-section">
        <h2><i class="fa-solid fa-trophy"></i> Achievements</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.75rem">
            <?php
            $achievements = [
                ['icon'=>'fa-medal','name'=>'First Lesson','desc'=>'Complete your first lesson','earned'=>$completed>=1],
                ['icon'=>'fa-book','name'=>'Bookworm','desc'=>'Complete 3 lessons','earned'=>$completed>=3],
                ['icon'=>'fa-bolt','name'=>'Halfway There','desc'=>'Reach 50% overall progress','earned'=>$overallProgress>=50],
                ['icon'=>'fa-trophy','name'=>'Graduate','desc'=>'Complete all available lessons','earned'=>$overallProgress>=100],
            ];
            foreach ($achievements as $ach):
            ?>
            <div style="background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:1rem; text-align:center; <?= $ach['earned'] ? 'border-color:var(--clr-primary);box-shadow:var(--shadow-sm)' : 'opacity:.6' ?>">
                <i class="fa-solid <?= $ach['icon'] ?>" style="font-size:1.8rem;color:<?= $ach['earned'] ? 'var(--clr-primary)' : 'var(--clr-muted)' ?>"></i>
                <div style="font-weight:600;font-size:.85rem;margin-top:.4rem"><?= $ach['name'] ?></div>
                <div style="font-size:.75rem;color:var(--clr-muted)"><?= $ach['desc'] ?></div>
                <?php if ($ach['earned']): ?><div style="font-size:.72rem;color:#16a34a;font-weight:600;margin-top:.3rem">Earned!</div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
