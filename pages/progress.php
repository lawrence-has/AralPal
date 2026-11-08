<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ── Migration ────────────────────────────────────────────────────────────────
get_db()->exec("
CREATE TABLE IF NOT EXISTS `lesson_progress` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `lesson_slug` VARCHAR(100) NOT NULL,
  `status` ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `ux_user_lesson` (`user_id`, `lesson_slug`),
  CONSTRAINT `fk_lp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pageTitle = "My Learning Progress – AralPal";
$user      = current_user();
$userId    = (int) $user['id'];

// ── Lesson definitions ───────────────────────────────────────────────────────
$lessons = [
    'php'        => ['title' => 'Introduction to PHP',     'icon_class' => 'fa-brands fa-php',    'duration' => '15 min', 'category' => 'Programming', 'link' => 'lesson-php.php'],
    'html-css'   => ['title' => 'HTML & CSS Basics',        'icon_class' => 'fa-brands fa-html5',  'duration' => '20 min', 'category' => 'Web Design',  'link' => 'lesson-html-css.php'],
    'javascript' => ['title' => 'JavaScript Fundamentals', 'icon_class' => 'fa-brands fa-js',     'duration' => '25 min', 'category' => 'Programming', 'link' => 'lesson-javascript.php'],
];

// ── Fetch real progress per slug ─────────────────────────────────────────────
$progressRows = [];
$stmt = get_db()->prepare(
    'SELECT lesson_slug, status FROM lesson_progress WHERE user_id = ?'
);
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $progressRows[$row['lesson_slug']] = $row['status'];
}

// ── Fetch quiz stats (graceful if table absent) ───────────────────────────────
$quizStats = [];
try {
    $stmt = get_db()->prepare(
        'SELECT lesson_slug,
                MAX(score / total * 100) AS best_pct,
                COUNT(*) AS attempts
         FROM quiz_attempts
         WHERE user_id = ?
         GROUP BY lesson_slug'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $quizStats[$row['lesson_slug']] = [
            'best_pct' => round((float) $row['best_pct']),
            'attempts' => (int)  $row['attempts'],
        ];
    }
} catch (PDOException $e) {
    // quiz_attempts table not yet created — use empty stats
}

// ── Compute per-lesson progress values ───────────────────────────────────────
$activities  = [];
foreach ($lessons as $slug => $info) {
    $dbStatus = $progressRows[$slug] ?? 'not_started';
    $pct      = match ($dbStatus) {
        'completed'   => 100,
        'in_progress' => 50,
        default       => 0,
    };
    $activities[] = array_merge($info, [
        'slug'     => $slug,
        'status'   => $dbStatus,
        'progress' => $pct,
    ]);
}

// ── Summary counts ───────────────────────────────────────────────────────────
$completed      = count(array_filter($activities, fn($a) => $a['status'] === 'completed'));
$inProgress     = count(array_filter($activities, fn($a) => $a['status'] === 'in_progress'));
$notStarted     = count(array_filter($activities, fn($a) => $a['status'] === 'not_started'));
$overallProgress = count($activities) > 0
    ? round(array_sum(array_column($activities, 'progress')) / count($activities))
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="../assets/aralpal-logo.png">
    <link rel="stylesheet" href="../assets/Style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>(function(){var s=localStorage.getItem('ap-theme'),d=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.setAttribute('data-theme',(s?s==='dark':d)?'dark':'light')})();</script>
</head>
<body>

<header class="navbar" id="navbar">
    <a class="logo logo-brand" href="../index.php">
        <img src="../assets/aralpal-logo.png" alt="" class="brand-logo" width="34" height="34">
        <span>AralPal</span>
    </a>
    <nav>
        <a href="../index.php">Home</a>
        <a href="../index.php#lessons">Lessons</a>
        <a href="quizzes.php">Quizzes</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
    </nav>
</header>

<main class="container progress-page">

    <div class="progress-page__header">
        <div>
            <span class="eyebrow">Learning Dashboard</span>
            <h1><i class="fas fa-chart-line" style="margin-right:.4rem"></i>My Progress</h1>
            <p>Track your learning journey across all lessons and quizzes.</p>
        </div>
        <a class="primary-btn" href="quizzes.php"><i class="fas fa-pen-to-square" style="margin-right:.4rem"></i>Take a Quiz</a>
    </div>

    <!-- ── Overall Summary ── -->
    <div class="progress-summary-grid">
        <div class="progress-summary-card progress-summary-card--overall">
            <div class="progress-summary-card__label">Overall Progress</div>
            <div class="progress-summary-card__big"><?= $overallProgress ?>%</div>
            <div class="progress-bar-wrap">
                <div class="progress-bar" role="progressbar" aria-valuenow="<?= $overallProgress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-fill progress-fill--animated" style="width:<?= $overallProgress ?>%"></div>
                </div>
            </div>
            <div class="progress-bar-label"><?= $overallProgress ?>% of all lessons</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon"><i class="fas fa-circle-check"></i></div>
            <div class="progress-summary-card__value"><?= $completed ?></div>
            <div class="progress-summary-card__label">Completed</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon"><i class="fas fa-spinner"></i></div>
            <div class="progress-summary-card__value"><?= $inProgress ?></div>
            <div class="progress-summary-card__label">In Progress</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon"><i class="fas fa-book-open"></i></div>
            <div class="progress-summary-card__value"><?= $notStarted ?></div>
            <div class="progress-summary-card__label">Not Started</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon"><i class="fas fa-layer-group"></i></div>
            <div class="progress-summary-card__value"><?= count($activities) ?></div>
            <div class="progress-summary-card__label">Total Lessons</div>
        </div>
    </div>

    <!-- ── Lesson Cards ── -->
    <section class="progress-lessons">
        <h2>Lesson Breakdown</h2>
        <div class="progress-lessons-list">
            <?php foreach ($activities as $activity):
                $statusClass = match($activity['status']) {
                    'completed'   => 'status-completed',
                    'in_progress' => 'status-in-progress',
                    default       => 'status-not-started',
                };
                $statusIcon = match($activity['status']) {
                    'completed'   => '<i class="fas fa-circle-check"></i>',
                    'in_progress' => '<i class="fas fa-spinner"></i>',
                    default       => '<i class="fas fa-book-open"></i>',
                };
                $statusLabel = match($activity['status']) {
                    'completed'   => 'Completed',
                    'in_progress' => 'In Progress',
                    default       => 'Not Started',
                };
            ?>
            <article class="progress-lesson-card <?= $statusClass ?>">
                <div class="progress-lesson-card__icon"><i class="<?= htmlspecialchars($activity['icon_class']) ?>"></i></div>
                <div class="progress-lesson-card__body">
                    <div class="progress-lesson-card__top">
                        <div>
                            <span class="lesson-category"><?= htmlspecialchars($activity['category']) ?></span>
                            <h3><?= htmlspecialchars($activity['title']) ?></h3>
                            <div class="lesson-meta" style="margin-top:6px">
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($activity['duration']) ?></span>
                                <span><?= $statusIcon ?> <?= $statusLabel ?></span>
                            </div>
                        </div>
                        <div class="progress-lesson-card__pct"><?= $activity['progress'] ?>%</div>
                    </div>
                    <div class="progress-bar" role="progressbar" aria-valuenow="<?= $activity['progress'] ?>" aria-valuemin="0" aria-valuemax="100" style="margin-top:12px">
                        <div class="progress-fill progress-fill--animated" style="width:<?= $activity['progress'] ?>%"></div>
                    </div>
                </div>
                <div class="progress-lesson-card__action">
                    <?php if ($activity['status'] === 'completed'): ?>
                        <a class="btn btn-sm btn-success" href="<?= htmlspecialchars($activity['link']) ?>">Review</a>
                    <?php elseif ($activity['status'] === 'in_progress'): ?>
                        <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($activity['link']) ?>">Continue</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline" href="<?= htmlspecialchars($activity['link']) ?>">Start</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Quiz Stats ── -->
    <section class="progress-quizzes">
        <h2><i class="fas fa-pen-to-square" style="margin-right:.4rem"></i>Quiz Activity</h2>
        <?php if (!empty($quizStats)): ?>
        <div class="progress-lessons-list">
            <?php foreach ($lessons as $slug => $info):
                $qs = $quizStats[$slug] ?? null;
                if (!$qs) continue;
            ?>
            <article class="progress-lesson-card">
                <div class="progress-lesson-card__icon"><i class="<?= htmlspecialchars($info['icon_class']) ?>"></i></div>
                <div class="progress-lesson-card__body">
                    <div class="progress-lesson-card__top">
                        <div>
                            <span class="lesson-category"><?= htmlspecialchars($info['category']) ?></span>
                            <h3><?= htmlspecialchars($info['title']) ?></h3>
                            <div class="lesson-meta" style="margin-top:6px">
                                <span><i class="fas fa-star"></i> Best score: <?= $qs['best_pct'] ?>%</span>
                                <span><i class="fas fa-repeat"></i> Attempts: <?= $qs['attempts'] ?></span>
                            </div>
                        </div>
                        <div class="progress-lesson-card__pct"><?= $qs['best_pct'] ?>%</div>
                    </div>
                    <div class="progress-bar" role="progressbar" aria-valuenow="<?= $qs['best_pct'] ?>" aria-valuemin="0" aria-valuemax="100" style="margin-top:12px">
                        <div class="progress-fill progress-fill--animated" style="width:<?= $qs['best_pct'] ?>%"></div>
                    </div>
                </div>
                <div class="progress-lesson-card__action">
                    <a class="btn btn-sm btn-outline" href="quizzes.php">Retry</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="progress-quiz-banner">
            <div class="progress-quiz-banner__text">
                <span class="feature-icon"><i class="fas fa-pen-to-square"></i></span>
                <div>
                    <h3>Test your knowledge</h3>
                    <p>Take quizzes to reinforce what you've learned and track your understanding over time.</p>
                </div>
            </div>
            <a class="primary-btn" href="quizzes.php">Go to Quizzes →</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Achievements ── -->
    <section class="progress-achievements">
        <h2><i class="fas fa-trophy" style="margin-right:.4rem"></i>Achievements</h2>
        <div class="achievements-grid">
            <div class="achievement-card <?= $completed >= 1 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon"><i class="fas fa-medal"></i></div>
                <div class="achievement-card__name">First Lesson</div>
                <div class="achievement-card__desc">Complete your first lesson</div>
                <?php if ($completed >= 1): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $completed >= 3 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon"><i class="fas fa-book"></i></div>
                <div class="achievement-card__name">Bookworm</div>
                <div class="achievement-card__desc">Complete 3 lessons</div>
                <?php if ($completed >= 3): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $overallProgress >= 50 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon"><i class="fas fa-bolt"></i></div>
                <div class="achievement-card__name">Halfway There</div>
                <div class="achievement-card__desc">Reach 50% overall progress</div>
                <?php if ($overallProgress >= 50): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $overallProgress >= 100 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon"><i class="fas fa-trophy"></i></div>
                <div class="achievement-card__name">Graduate</div>
                <div class="achievement-card__desc">Complete all available lessons</div>
                <?php if ($overallProgress >= 100): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> AralPal. All rights reserved.</p>
</footer>

<script>
const tt = document.getElementById('themeToggle');
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    if (tt) {
        tt.innerHTML = dark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    }
    localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
}
applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
if (tt) tt.addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark'));
const nb = document.getElementById('navbar');
if (nb) window.addEventListener('scroll', () => nb.classList.toggle('scrolled', window.scrollY > 10), { passive: true });
</script>
</body>
</html>
