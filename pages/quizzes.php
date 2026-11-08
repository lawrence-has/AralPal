<?php
// ── Migration: create quiz tables and seed data ───────────────────────────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = get_db();

$db->exec("
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `lesson_slug` VARCHAR(100) NOT NULL,
  `question_text` TEXT NOT NULL,
  `correct_option` TINYINT UNSIGNED NOT NULL COMMENT '0-based index of correct option',
  `explanation` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_options` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT UNSIGNED NOT NULL,
  `option_index` TINYINT UNSIGNED NOT NULL,
  `option_text` VARCHAR(500) NOT NULL,
  CONSTRAINT `fk_opt_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `lesson_slug` VARCHAR(100) NOT NULL,
  `score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `chosen_option` TINYINT UNSIGNED NOT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_ans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ans_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Seed PHP questions
$phpSeeds = [
    ['lesson_slug' => 'php', 'question_text' => 'Which symbol starts a PHP variable?', 'correct_option' => 2, 'explanation' => 'PHP variables always start with a dollar sign.',
     'options' => ['#', '@', '$', '&']],
    ['lesson_slug' => 'php', 'question_text' => 'Which superglobal holds POST data in PHP?', 'correct_option' => 2, 'explanation' => '$_POST contains data submitted via method=post.',
     'options' => ['$_GET', '$_SESSION', '$_POST', '$_SERVER']],
    ['lesson_slug' => 'php', 'question_text' => 'Which function counts array items in PHP?', 'correct_option' => 2, 'explanation' => 'count() returns the number of elements in an array.',
     'options' => ['length()', 'size()', 'count()', 'sizeof()']],
    ['lesson_slug' => 'php', 'question_text' => 'What does the . operator do in PHP?', 'correct_option' => 2, 'explanation' => 'The dot (.) operator joins strings together.',
     'options' => ['Divides numbers', 'Accesses object properties', 'Concatenates strings', 'Compares values']],
    ['lesson_slug' => 'php', 'question_text' => 'Where does PHP code run?', 'correct_option' => 1, 'explanation' => 'PHP is a server-side language.',
     'options' => ['In the browser', 'On the server', 'In the database', 'In the CSS file']],
];

// Seed HTML/CSS questions
$htmlSeeds = [
    ['lesson_slug' => 'html-css', 'question_text' => 'What does HTML stand for?', 'correct_option' => 0, 'explanation' => 'HTML is the standard language for creating web pages.',
     'options' => ['Hyper Text Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyperlinks Text Management Language']],
    ['lesson_slug' => 'html-css', 'question_text' => 'Which CSS property changes text colour?', 'correct_option' => 2, 'explanation' => "The 'color' property sets the foreground text colour.",
     'options' => ['font-color', 'text-color', 'color', 'background-color']],
    ['lesson_slug' => 'html-css', 'question_text' => 'Which HTML tag creates a hyperlink?', 'correct_option' => 2, 'explanation' => 'The <a> (anchor) tag creates hyperlinks.',
     'options' => ['<link>', '<href>', '<a>', '<url>']],
    ['lesson_slug' => 'html-css', 'question_text' => 'What does CSS stand for?', 'correct_option' => 1, 'explanation' => 'CSS = Cascading Style Sheets.',
     'options' => ['Creative Style Sheets', 'Cascading Style Sheets', 'Computer Style Sheets', 'Colorful Style Sheets']],
    ['lesson_slug' => 'html-css', 'question_text' => 'Which attribute specifies the URL of a link?', 'correct_option' => 2, 'explanation' => 'The href attribute defines the hyperlink destination.',
     'options' => ['src', 'link', 'href', 'url']],
];

// Seed JavaScript questions
$jsSeeds = [
    ['lesson_slug' => 'javascript', 'question_text' => 'Which language makes web pages interactive?', 'correct_option' => 3, 'explanation' => 'JavaScript runs natively in the browser.',
     'options' => ['HTML', 'CSS', 'SQL', 'JavaScript']],
    ['lesson_slug' => 'javascript', 'question_text' => 'How do you write a single-line comment in JavaScript?', 'correct_option' => 2, 'explanation' => 'Single-line JS comments start with //.',
     'options' => ['<!-- comment -->', '# comment', '// comment', '/* comment */']],
    ['lesson_slug' => 'javascript', 'question_text' => 'Which keyword declares a constant in JavaScript?', 'correct_option' => 2, 'explanation' => 'const declares a block-scoped constant.',
     'options' => ['var', 'let', 'const', 'def']],
    ['lesson_slug' => 'javascript', 'question_text' => 'Which method adds an item to the end of an array?', 'correct_option' => 0, 'explanation' => 'array.push() appends one or more elements to the end.',
     'options' => ['push()', 'pop()', 'shift()', 'append()']],
    ['lesson_slug' => 'javascript', 'question_text' => 'What does === check in JavaScript?', 'correct_option' => 2, 'explanation' => '=== is strict equality, checking both value and type.',
     'options' => ['Only value equality', 'Only type equality', 'Both value and type equality', 'Reference equality']],
];

$allSeeds = array_merge($phpSeeds, $htmlSeeds, $jsSeeds);

$insertQuestion = $db->prepare(
    'INSERT IGNORE INTO quiz_questions (lesson_slug, question_text, correct_option, explanation)
     SELECT :slug, :qtext, :correct, :expl
     FROM DUAL
     WHERE NOT EXISTS (
         SELECT 1 FROM quiz_questions WHERE lesson_slug = :slug2 AND question_text = :qtext2
     )'
);
$insertOption = $db->prepare(
    'INSERT IGNORE INTO quiz_options (question_id, option_index, option_text)
     SELECT :qid, :idx, :otext
     FROM DUAL
     WHERE NOT EXISTS (
         SELECT 1 FROM quiz_options WHERE question_id = :qid2 AND option_index = :idx2
     )'
);

foreach ($allSeeds as $seed) {
    $insertQuestion->execute([
        ':slug'   => $seed['lesson_slug'],
        ':qtext'  => $seed['question_text'],
        ':correct'=> $seed['correct_option'],
        ':expl'   => $seed['explanation'],
        ':slug2'  => $seed['lesson_slug'],
        ':qtext2' => $seed['question_text'],
    ]);
    // Find the question id (whether just inserted or already existed)
    $qidStmt = $db->prepare(
        'SELECT id FROM quiz_questions WHERE lesson_slug = ? AND question_text = ? LIMIT 1'
    );
    $qidStmt->execute([$seed['lesson_slug'], $seed['question_text']]);
    $qid = (int) $qidStmt->fetchColumn();
    if ($qid) {
        foreach ($seed['options'] as $idx => $optText) {
            $insertOption->execute([
                ':qid'  => $qid,
                ':idx'  => $idx,
                ':otext'=> $optText,
                ':qid2' => $qid,
                ':idx2' => $idx,
            ]);
        }
    }
}

// ── AJAX POST handler ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Validate CSRF from header
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrfHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || !isset($body['quiz'], $body['answers']) || !is_array($body['answers'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body']);
        exit;
    }

    $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$body['quiz']));
    $answers = $body['answers']; // array of chosen_option indices (int)

    // Load questions for this slug (ordered by id)
    $qStmt = $db->prepare(
        'SELECT id, correct_option, explanation FROM quiz_questions WHERE lesson_slug = ? ORDER BY id ASC'
    );
    $qStmt->execute([$slug]);
    $questions = $qStmt->fetchAll();

    if (empty($questions)) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz not found']);
        exit;
    }

    $total   = count($questions);
    $score   = 0;
    $results = [];

    foreach ($questions as $i => $q) {
        $chosen    = isset($answers[$i]) ? (int)$answers[$i] : -1;
        $correct   = (int)$q['correct_option'];
        $is_correct = ($chosen === $correct) ? 1 : 0;
        if ($is_correct) $score++;
        $results[] = [
            'correct_option' => $correct,
            'chosen_option'  => $chosen,
            'is_correct'     => (bool)$is_correct,
            'explanation'    => $q['explanation'],
        ];
    }

    $pct = $total > 0 ? round($score / $total * 100) : 0;

    // Save attempt if logged in
    $user = current_user();
    if ($user) {
        $db->prepare(
            'INSERT INTO quiz_attempts (user_id, lesson_slug, score, total) VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], $slug, $score, $total]);

        $attemptId = (int)$db->lastInsertId();

        $saveAns = $db->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, chosen_option, is_correct) VALUES (?, ?, ?, ?)'
        );
        foreach ($questions as $i => $q) {
            $chosen     = isset($answers[$i]) ? (int)$answers[$i] : 0;
            $is_correct = ($chosen === (int)$q['correct_option']) ? 1 : 0;
            $saveAns->execute([$attemptId, $q['id'], $chosen, $is_correct]);
        }
    }

    echo json_encode([
        'score'   => $score,
        'total'   => $total,
        'pct'     => $pct,
        'saved'   => (bool)($user ?? false),
        'results' => $results,
    ]);
    exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle = "AralPal – Quizzes";

// Slug → display metadata
$topicMeta = [
    'php'        => ['title' => 'PHP Deep Dive',           'icon' => 'fa-brands fa-php'],
    'html-css'   => ['title' => 'HTML & CSS Basics',        'icon' => 'fa-brands fa-html5'],
    'javascript' => ['title' => 'JavaScript Fundamentals',  'icon' => 'fa-brands fa-js'],
];

// Load distinct slugs that actually have questions
$slugStmt = $db->query('SELECT DISTINCT lesson_slug FROM quiz_questions ORDER BY lesson_slug');
$slugs    = $slugStmt->fetchAll(PDO::FETCH_COLUMN);

// Load questions + options for the active quiz
$activeSlug = isset($_GET['quiz']) && in_array($_GET['quiz'], $slugs, true)
    ? $_GET['quiz']
    : ($slugs[0] ?? 'php');

$qStmt = $db->prepare(
    'SELECT id, question_text, correct_option, explanation
     FROM quiz_questions WHERE lesson_slug = ? ORDER BY id ASC'
);
$qStmt->execute([$activeSlug]);
$questions = $qStmt->fetchAll();

foreach ($questions as &$q) {
    $oStmt = $db->prepare(
        'SELECT option_text FROM quiz_options WHERE question_id = ? ORDER BY option_index ASC'
    );
    $oStmt->execute([$q['id']]);
    $q['options'] = $oStmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($q);

$totalQ = count($questions);
$activeMeta = $topicMeta[$activeSlug] ?? ['title' => ucfirst($activeSlug), 'icon' => 'fas fa-question-circle'];

// Total question count across all quizzes (for hero stats)
$totalAllQ = (int)$db->query('SELECT COUNT(*) FROM quiz_questions')->fetchColumn();

// CSRF token for embedding
$csrfToken = generate_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link rel="icon" type="image/png" href="../assets/aralpal-logo.png">
    <link rel="stylesheet" href="../assets/Style.css?v=quiz-db-20261104">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>(function(){var s=localStorage.getItem('ap-theme'),d=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.setAttribute('data-theme',(s?s==='dark':d)?'dark':'light')})();</script>
</head>
<body class="aralpal-quiz-dashboard">
<div class="quiz-app-shell quiz-app-shell--topnav">
<header class="quiz-reference-navbar" id="navbar" aria-label="Main navigation">
    <nav class="quiz-reference-links">
        <a href="../index.php#lessons">Lessons</a>
        <a href="../tutors/index.php">Tutors</a>
        <a href="../index.php#features">Features</a>
        <a href="../subscriptions/plans.php">Plans</a>
        <a href="../index.php#about">About</a>
    </nav>
    <div class="quiz-reference-actions">
        <a class="quiz-reference-login" href="../auth/login.php">Login</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="fas fa-moon"></i>
        </button>
    </div>
</header>
<div class="quiz-main-area">
<main class="container quiz-page">

    <section class="quiz-hero">
        <div class="quiz-hero__content">
            <span class="quiz-hero__badge"><i class="fas fa-star quiz-hero__spark"></i> LEARN &bull; PRACTICE &bull; IMPROVE</span>
            <p class="eyebrow">YOUR KNOWLEDGE CHECKPOINT</p>
            <h1>AralPal <span>Quizzes</span></h1>
            <p>Test your knowledge, track your progress, and become a better learner&mdash;one question at a time.</p>
            <a class="quiz-hero__cta" href="#quizWorkspace"><i class="fas fa-play"></i> &nbsp; Start a Quiz <span>&rarr;</span></a>
            <div class="quiz-hero__facts">
                <span><strong><?= $totalAllQ ?></strong> practice questions</span>
                <span><strong><?= count($slugs) ?></strong> learning topics</span>
                <span><strong>&infin;</strong> chances to improve</span>
            </div>
        </div>
        <div class="quiz-hero__art" aria-hidden="true">
            <div class="quiz-hero__orb quiz-hero__orb--one"></div>
            <div class="quiz-hero__orb quiz-hero__orb--two"></div>
            <div class="quiz-hero__book"><i class="fas fa-pencil"></i></div>
            <div class="quiz-hero__float quiz-hero__float--check"><i class="fas fa-check"></i></div>
            <div class="quiz-hero__float quiz-hero__float--star"><i class="fas fa-star"></i></div>
            <div class="quiz-hero__float quiz-hero__float--code"><i class="fas fa-code"></i></div>
        </div>
    </section>

    <!-- Quiz selector -->
    <div class="quiz-section-heading">
        <div><span class="eyebrow">PICK YOUR CHALLENGE</span><h2>Explore quiz topics</h2></div>
        <p>Select a topic to begin practicing.</p>
    </div>
    <div class="quiz-topic-heading"><h2><i class="fas fa-th-large"></i> &nbsp; Choose a Quiz Topic</h2><span>Pick a topic to get started</span></div>
    <div class="quiz-selector">
        <?php foreach ($slugs as $slug):
            $meta  = $topicMeta[$slug] ?? ['title' => ucfirst($slug), 'icon' => 'fas fa-question-circle'];
            $qcStmt = $db->prepare('SELECT COUNT(*) FROM quiz_questions WHERE lesson_slug = ?');
            $qcStmt->execute([$slug]);
            $qCount = (int)$qcStmt->fetchColumn();
        ?>
            <a
                href="?quiz=<?= htmlspecialchars($slug) ?>"
                class="quiz-selector__btn <?= $slug === $activeSlug ? 'quiz-selector__btn--active' : '' ?>"
            >
                <span class="quiz-topic-icon"><i class="<?= htmlspecialchars($meta['icon']) ?>"></i></span>
                <div class="quiz-topic-copy">
                    <strong><?= htmlspecialchars($meta['title']) ?></strong>
                    <em><i class="fas fa-circle-dot"></i> &nbsp; <?= $qCount ?> Questions</em>
                </div>
                <span class="quiz-topic-arrow"><i class="fas fa-arrow-right"></i></span>
            </a>
        <?php endforeach; ?>
        <div class="quiz-selector__btn quiz-topic-coming" aria-label="Computer Networks quiz coming soon">
            <span class="quiz-topic-icon"><i class="fas fa-network-wired"></i></span>
            <div class="quiz-topic-copy"><strong>Computer Networks</strong><small>Explore connectivity, protocols, and network basics.</small><em>COMING SOON</em></div>
            <span class="quiz-topic-arrow"><i class="fas fa-plus"></i></span>
        </div>
        <div class="quiz-selector__btn quiz-topic-coming" aria-label="Database quiz coming soon">
            <span class="quiz-topic-icon"><i class="fas fa-database"></i></span>
            <div class="quiz-topic-copy"><strong>Database Essentials</strong><small>Practice SQL, tables, and data relationships.</small><em>COMING SOON</em></div>
            <span class="quiz-topic-arrow"><i class="fas fa-plus"></i></span>
        </div>
    </div>

    <!-- Quiz workspace -->
    <div class="quiz-workspace" id="quizWorkspace">
    <div class="quiz-card">
        <div class="quiz-card__header">
            <span class="quiz-card__icon"><i class="<?= htmlspecialchars($activeMeta['icon']) ?> fa-2x"></i></span>
            <div class="quiz-card__intro">
                <span class="quiz-card__tag">PRACTICE SESSION</span>
                <h2><?= htmlspecialchars($activeMeta['title']) ?></h2>
                <div class="quiz-card__meta">
                    <span><i class="fas fa-clock"></i> Self-paced</span>
                    <span>&bull;</span>
                    <span><?= $totalQ ?> questions</span>
                    <span>&bull;</span>
                    <span>Instant feedback</span>
                </div>
            </div>
            <div class="quiz-progress-ring" id="progressRing" aria-label="Quiz progress">
                <svg viewBox="0 0 44 44" width="52" height="52">
                    <circle cx="22" cy="22" r="18" fill="none" stroke="var(--clr-border)" stroke-width="4"/>
                    <circle id="progressArc" cx="22" cy="22" r="18" fill="none" stroke="var(--clr-primary)" stroke-width="4"
                        stroke-dasharray="113" stroke-dashoffset="113" stroke-linecap="round"
                        transform="rotate(-90 22 22)"/>
                </svg>
                <span id="progressText">0/<?= $totalQ ?></span>
            </div>
        </div>

        <div class="quiz-progress-track" aria-hidden="true"><span id="quizProgressBar"></span></div>
        <div class="quiz-progress-caption"><span>YOUR PROGRESS</span><strong id="quizProgressLabel">0% completed</strong></div>
        <form id="quizForm" novalidate>
            <?php foreach ($questions as $i => $q): ?>
                <section class="quiz-question" id="qq-<?= $i ?>" data-question="<?= $i ?>">
                    <div class="quiz-question__topline">
                        <div class="quiz-question__number">
                            <span>Q<?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span> QUESTION <?= $i + 1 ?>
                        </div>
                        <span class="quiz-question__points">1 POINT</span>
                    </div>
                    <h3><?= htmlspecialchars($q['question_text']) ?></h3>
                    <p class="quiz-question__hint">Choose the best answer below.</p>
                    <div class="quiz-choices">
                        <?php foreach ($q['options'] as $j => $optText): ?>
                            <label class="quiz-choice">
                                <input
                                    type="radio"
                                    name="q<?= $i ?>"
                                    value="<?= $j ?>"
                                    aria-label="<?= htmlspecialchars($optText) ?>"
                                >
                                <span class="quiz-choice__letter"><?= chr(65 + $j) ?></span>
                                <span class="quiz-choice__text"><?= htmlspecialchars($optText) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="quiz-explanation" id="exp-<?= $i ?>" hidden>
                        <i class="fas fa-lightbulb"></i>
                        <p><?= htmlspecialchars($q['explanation'] ?? '') ?></p>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="quiz-actions">
                <button type="submit" class="primary-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Answers
                </button>
                <button type="button" class="outline-btn" id="retakeBtn" hidden>
                    <i class="fas fa-rotate-right"></i> Try Again
                </button>
            </div>
        </form>

        <!-- Result panel -->
        <div class="quiz-result" id="quizResult" hidden>
            <div class="quiz-result__score" id="resultScore"></div>
            <div class="quiz-result__message" id="resultMessage"></div>
            <div class="quiz-result__breakdown" id="resultBreakdown"></div>
            <!-- Sign-in nudge shown by JS when user is not logged in -->
            <div class="quiz-result__save-nudge" id="saveNudge" hidden>
                <i class="fas fa-floppy-disk"></i>
                <span>Sign in to save your score&nbsp;&mdash;&nbsp;<a href="/AralPal/auth/login.php">Sign in</a></span>
            </div>
        </div>
    </div>
    <aside class="quiz-sidebar">
        <section class="quiz-side-card quiz-overview">
            <h3><i class="fas fa-table-cells"></i> Quiz Overview</h3>
            <div class="quiz-overview-row">
                <span><i class="fas fa-check overview-dot overview-dot--green"></i> Answered</span>
                <strong id="overviewAnswered">0 / <?= $totalQ ?></strong>
            </div>
            <div class="quiz-overview-row">
                <span><i class="fas fa-clock overview-dot overview-dot--blue"></i> Remaining</span>
                <strong id="overviewRemaining"><?= $totalQ ?></strong>
            </div>
            <div class="quiz-overview-row">
                <span><i class="fas fa-star overview-dot overview-dot--gold"></i> Score</span>
                <strong id="overviewScore">&mdash;</strong>
            </div>
        </section>
        <section class="quiz-side-card quiz-tip-card">
            <div class="quiz-tip-title"><i class="fas fa-lightbulb"></i><h3>Quick Tip</h3></div>
            <p>Take your time and read each question carefully. You can review the correct answers and explanations after submitting.</p>
        </section>
        <section class="quiz-side-card quiz-encouragement">
            <i class="fas fa-bullseye fa-2x"></i><strong>Keep learning!</strong>
            <p>Every attempt helps you build confidence and improve your skills.</p>
        </section>
    </aside>
    </div>

    <!-- Other quizzes -->
    <div class="quiz-other">
        <h3>More quizzes</h3>
        <div class="feature-grid">
            <?php foreach ($slugs as $slug):
                if ($slug === $activeSlug) continue;
                $meta   = $topicMeta[$slug] ?? ['title' => ucfirst($slug), 'icon' => 'fas fa-question-circle'];
                $qcStmt = $db->prepare('SELECT COUNT(*) FROM quiz_questions WHERE lesson_slug = ?');
                $qcStmt->execute([$slug]);
                $qCount = (int)$qcStmt->fetchColumn();
            ?>
                <a href="?quiz=<?= htmlspecialchars($slug) ?>" class="feature-card quiz-promo-card">
                    <div class="feature-icon"><i class="<?= htmlspecialchars($meta['icon']) ?> fa-2x"></i></div>
                    <h3><?= htmlspecialchars($meta['title']) ?></h3>
                    <span class="lesson-meta"><span><?= $qCount ?> questions</span></span>
                </a>
            <?php endforeach; ?>
            <div class="feature-card quiz-promo-card">
                <div class="feature-icon"><i class="fas fa-bullseye fa-2x"></i></div>
                <h3>More Quizzes Coming</h3>
                <p>We&rsquo;re adding quizzes on databases, CSS layouts, and JavaScript advanced topics.</p>
                <a class="outline-btn" href="../auth/register.php" style="margin-top:auto">Get notified &rarr;</a>
            </div>
        </div>
    </div>

</main>
<footer class="site-footer quiz-dashboard-footer"><p>&copy; <?= date('Y') ?> AralPal. All rights reserved.</p></footer>
</div><!-- /.quiz-main-area -->
</div><!-- /.quiz-app-shell -->

<script>
(function () {
    const QUIZ_SLUG     = <?= json_encode($activeSlug) ?>;
    const totalQ        = <?= $totalQ ?>;
    const circumference = 2 * Math.PI * 18; // ≈ 113
    const csrfToken     = document.querySelector('meta[name="csrf-token"]').content;

    const form        = document.getElementById('quizForm');
    const submitBtn   = document.getElementById('submitBtn');
    const retakeBtn   = document.getElementById('retakeBtn');
    const resultPanel = document.getElementById('quizResult');
    const arc         = document.getElementById('progressArc');
    const progText    = document.getElementById('progressText');

    let answered = new Set();

    /* ── Progress ring ──────────────────────────────────────────────────────── */
    form.querySelectorAll('input[type="radio"]').forEach(input => {
        input.addEventListener('change', () => {
            const match = input.name.match(/\d+/);
            if (match) answered.add(parseInt(match[0]));
            const ratio   = answered.size / totalQ;
            arc.style.strokeDashoffset = circumference * (1 - ratio);
            progText.textContent = answered.size + '/' + totalQ;
            const pct = Math.round(ratio * 100);
            const progressBar   = document.getElementById('quizProgressBar');
            const progressLabel = document.getElementById('quizProgressLabel');
            if (progressBar)   progressBar.style.width = pct + '%';
            if (progressLabel) progressLabel.textContent = pct + '% completed';
            const answeredEl  = document.getElementById('overviewAnswered');
            const remainingEl = document.getElementById('overviewRemaining');
            if (answeredEl)  answeredEl.textContent  = answered.size + ' / ' + totalQ;
            if (remainingEl) remainingEl.textContent = totalQ - answered.size;
        });
    });

    /* ── Submit (AJAX) ──────────────────────────────────────────────────────── */
    form.addEventListener('submit', e => {
        e.preventDefault();

        // Collect chosen option indices in question order
        const chosenAnswers = [];
        for (let i = 0; i < totalQ; i++) {
            const sel = form.querySelector(`input[name="q${i}"]:checked`);
            chosenAnswers.push(sel ? parseInt(sel.value) : -1);
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking&hellip;';

        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ quiz: QUIZ_SLUG, answers: chosenAnswers }),
        })
        .then(r => {
            if (!r.ok) throw new Error('Server error ' + r.status);
            return r.json();
        })
        .then(data => {
            applyResults(data, chosenAnswers);
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Answers';
            alert('Could not submit quiz. Please try again.\n' + err.message);
        });
    });

    /* ── Apply results from server response ─────────────────────────────────── */
    function applyResults(data, chosenAnswers) {
        const score = data.score;
        const total = data.total;
        const pct   = data.pct;

        data.results.forEach((res, i) => {
            const expEl  = document.getElementById('exp-' + i);
            const choices = form.querySelectorAll(`input[name="q${i}"]`);

            choices.forEach(c => { c.disabled = true; });

            const chosen  = res.chosen_option;
            const correct = res.correct_option;

            choices.forEach(c => {
                const label = c.closest('label');
                if (parseInt(c.value) === correct) {
                    label.classList.add('quiz-choice--correct');
                } else if (parseInt(c.value) === chosen && !res.is_correct) {
                    label.classList.add('quiz-choice--wrong');
                }
            });

            if (expEl) expEl.hidden = false;
        });

        /* Score display */
        const msgs = [
            [100, '<i class="fas fa-trophy"></i> Perfect score! Outstanding work!'],
            [80,  '<i class="fas fa-star"></i> Excellent! You really know your stuff.'],
            [60,  '<i class="fas fa-thumbs-up"></i> Good job! Review the highlighted answers to improve.'],
            [40,  '<i class="fas fa-book-open"></i> Not bad — keep studying and try again.'],
            [0,   '<i class="fas fa-dumbbell"></i> Keep practising! Every attempt makes you stronger.'],
        ];
        const msg = msgs.find(([min]) => pct >= min)[1];

        document.getElementById('resultScore').innerHTML =
            `<span class="quiz-score-number">${score}</span><span class="quiz-score-of">/ ${total}</span><span class="quiz-score-pct">${pct}%</span>`;
        document.getElementById('resultMessage').innerHTML = msg;

        // Show sign-in nudge when score was NOT saved
        const saveNudge = document.getElementById('saveNudge');
        if (saveNudge) saveNudge.hidden = !!data.saved;

        resultPanel.hidden = false;
        const overviewScore = document.getElementById('overviewScore');
        if (overviewScore) overviewScore.textContent = pct + '%';
        retakeBtn.hidden = false;
        submitBtn.hidden = true;
        resultPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });

        arc.style.strokeDashoffset = 0;
        progText.textContent = total + '/' + total;
        const progressBar   = document.getElementById('quizProgressBar');
        const progressLabel = document.getElementById('quizProgressLabel');
        if (progressBar)   progressBar.style.width = '100%';
        if (progressLabel) progressLabel.textContent = '100% completed';
    }

    /* ── Retake ──────────────────────────────────────────────────────────────── */
    retakeBtn.addEventListener('click', () => {
        form.reset();
        resultPanel.hidden = true;
        retakeBtn.hidden   = true;
        submitBtn.hidden   = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Answers';
        answered.clear();
        arc.style.strokeDashoffset = circumference;
        progText.textContent = '0/' + totalQ;
        const progressBar   = document.getElementById('quizProgressBar');
        const progressLabel = document.getElementById('quizProgressLabel');
        if (progressBar)   progressBar.style.width = '0%';
        if (progressLabel) progressLabel.textContent = '0% completed';
        const answeredEl  = document.getElementById('overviewAnswered');
        const remainingEl = document.getElementById('overviewRemaining');
        const overviewScore = document.getElementById('overviewScore');
        if (answeredEl)    answeredEl.textContent  = '0 / ' + totalQ;
        if (remainingEl)   remainingEl.textContent = totalQ;
        if (overviewScore) overviewScore.textContent = '\u2014';
        form.querySelectorAll('label').forEach(l => {
            l.classList.remove('quiz-choice--correct', 'quiz-choice--wrong');
        });
        form.querySelectorAll('input[type="radio"]').forEach(c => { c.disabled = false; });
        document.querySelectorAll('.quiz-explanation').forEach(e => { e.hidden = true; });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ── Theme toggle ────────────────────────────────────────────────────────── */
    const tt = document.getElementById('themeToggle');
    function applyTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        if (tt) tt.innerHTML = dark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
    }
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
    if (tt) tt.addEventListener('click', () =>
        applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark'));

    /* ── Navbar scroll shadow ────────────────────────────────────────────────── */
    const nb = document.getElementById('navbar');
    if (nb) window.addEventListener('scroll', () =>
        nb.classList.toggle('scrolled', window.scrollY > 10), { passive: true });
})();
</script>
</body>
</html>
