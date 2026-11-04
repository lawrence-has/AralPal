<?php
$pageTitle = "My Learning Progress – AralPal";

$activities = [
    ["title"=>"Introduction to PHP",       "status"=>"Completed",   "progress"=>100, "icon"=>"💻", "link"=>"lesson-php.php",        "duration"=>"15 min", "category"=>"Programming"],
    ["title"=>"HTML & CSS Basics",          "status"=>"In Progress", "progress"=>60,  "icon"=>"🎨", "link"=>"lesson-html-css.php",   "duration"=>"20 min", "category"=>"Web Design"],
    ["title"=>"JavaScript Fundamentals",   "status"=>"Not Started", "progress"=>0,   "icon"=>"⚡", "link"=>"lesson-javascript.php", "duration"=>"25 min", "category"=>"Programming"],
];

$totalProgress   = array_sum(array_column($activities, 'progress'));
$overallProgress = round($totalProgress / count($activities));
$completed       = count(array_filter($activities, fn($a) => $a['status'] === 'Completed'));
$inProgress      = count(array_filter($activities, fn($a) => $a['status'] === 'In Progress'));
$notStarted      = count(array_filter($activities, fn($a) => $a['status'] === 'Not Started'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="../assets/aralpal-logo.png">
    <link rel="stylesheet" href="../assets/Style.css">
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
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙</button>
    </nav>
</header>

<main class="container progress-page">

    <div class="progress-page__header">
        <div>
            <span class="eyebrow">Learning Dashboard</span>
            <h1>My Progress</h1>
            <p>Track your learning journey across all lessons and quizzes.</p>
        </div>
        <a class="primary-btn" href="quizzes.php">🎯 Take a Quiz</a>
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
            <div class="progress-summary-card__icon">✅</div>
            <div class="progress-summary-card__value"><?= $completed ?></div>
            <div class="progress-summary-card__label">Completed</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon">🔄</div>
            <div class="progress-summary-card__value"><?= $inProgress ?></div>
            <div class="progress-summary-card__label">In Progress</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon">📖</div>
            <div class="progress-summary-card__value"><?= $notStarted ?></div>
            <div class="progress-summary-card__label">Not Started</div>
        </div>
        <div class="progress-summary-card">
            <div class="progress-summary-card__icon">📚</div>
            <div class="progress-summary-card__value"><?= count($activities) ?></div>
            <div class="progress-summary-card__label">Total Lessons</div>
        </div>
    </div>

    <!-- ── Lesson Cards ── -->
    <section class="progress-lessons">
        <h2>Lesson Breakdown</h2>
        <div class="progress-lessons-list">
            <?php foreach ($activities as $i => $activity):
                $statusClass = match($activity['status']) {
                    'Completed'   => 'status-completed',
                    'In Progress' => 'status-in-progress',
                    default       => 'status-not-started',
                };
                $statusIcon = match($activity['status']) {
                    'Completed'   => '✅',
                    'In Progress' => '🔄',
                    default       => '📖',
                };
            ?>
            <article class="progress-lesson-card <?= $statusClass ?>">
                <div class="progress-lesson-card__icon"><?= $activity['icon'] ?></div>
                <div class="progress-lesson-card__body">
                    <div class="progress-lesson-card__top">
                        <div>
                            <span class="lesson-category"><?= htmlspecialchars($activity['category']) ?></span>
                            <h3><?= htmlspecialchars($activity['title']) ?></h3>
                            <div class="lesson-meta" style="margin-top:6px">
                                <span>⏱ <?= $activity['duration'] ?></span>
                                <span><?= $statusIcon ?> <?= htmlspecialchars($activity['status']) ?></span>
                            </div>
                        </div>
                        <div class="progress-lesson-card__pct"><?= $activity['progress'] ?>%</div>
                    </div>
                    <div class="progress-bar" role="progressbar" aria-valuenow="<?= $activity['progress'] ?>" aria-valuemin="0" aria-valuemax="100" style="margin-top:12px">
                        <div class="progress-fill progress-fill--animated" style="width:<?= $activity['progress'] ?>%"></div>
                    </div>
                </div>
                <div class="progress-lesson-card__action">
                    <?php if ($activity['status'] === 'Completed'): ?>
                        <a class="btn btn-sm btn-success" href="<?= htmlspecialchars($activity['link']) ?>">Review</a>
                    <?php elseif ($activity['status'] === 'In Progress'): ?>
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
        <h2>Quiz Activity</h2>
        <div class="progress-quiz-banner">
            <div class="progress-quiz-banner__text">
                <span class="feature-icon">✍️</span>
                <div>
                    <h3>Test your knowledge</h3>
                    <p>Take quizzes to reinforce what you've learned and track your understanding over time.</p>
                </div>
            </div>
            <a class="primary-btn" href="quizzes.php">Go to Quizzes →</a>
        </div>
    </section>

    <!-- ── Achievements ── -->
    <section class="progress-achievements">
        <h2>Achievements</h2>
        <div class="achievements-grid">
            <div class="achievement-card <?= $completed >= 1 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon">🥇</div>
                <div class="achievement-card__name">First Lesson</div>
                <div class="achievement-card__desc">Complete your first lesson</div>
                <?php if ($completed >= 1): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $completed >= 3 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon">📚</div>
                <div class="achievement-card__name">Bookworm</div>
                <div class="achievement-card__desc">Complete 3 lessons</div>
                <?php if ($completed >= 3): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $overallProgress >= 50 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon">⚡</div>
                <div class="achievement-card__name">Halfway There</div>
                <div class="achievement-card__desc">Reach 50% overall progress</div>
                <?php if ($overallProgress >= 50): ?><div class="achievement-card__earned">Earned!</div><?php endif; ?>
            </div>
            <div class="achievement-card <?= $overallProgress >= 100 ? 'achievement-card--earned' : '' ?>">
                <div class="achievement-card__icon">🏆</div>
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
    if (tt) tt.textContent = dark ? '☀️' : '🌙';
    localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
}
applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
if (tt) tt.addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark'));
const nb = document.getElementById('navbar');
if (nb) window.addEventListener('scroll', () => nb.classList.toggle('scrolled', window.scrollY > 10), { passive: true });
</script>
</body>
</html>
