<?php
$pageTitle = "AralPal – Quizzes";

$quizSets = [
    "web-basics" => [
        "title"       => "Web Development Basics",
        "description" => "Test your knowledge of HTML, CSS, PHP, and JavaScript fundamentals.",
        "icon"        => "🌐",
        "questions"   => [
            ["question" => "What does HTML stand for?",
             "choices"  => ["Hyper Text Markup Language","High Tech Modern Language","Home Tool Markup Language","Hyperlinks Text Management Language"],
             "answer"   => 0, "explanation" => "HTML (Hyper Text Markup Language) is the standard language for creating web pages."],
            ["question" => "Which language is used to style web pages?",
             "choices"  => ["PHP","JavaScript","CSS","Python"],
             "answer"   => 2, "explanation" => "CSS (Cascading Style Sheets) controls the visual appearance of HTML elements."],
            ["question" => "Which symbol starts a PHP variable?",
             "choices"  => ["#","@","$","&"],
             "answer"   => 2, "explanation" => "PHP variables always start with a dollar sign, e.g. \$name = 'AralPal';"],
            ["question" => "Which language makes web pages interactive?",
             "choices"  => ["HTML","CSS","SQL","JavaScript"],
             "answer"   => 3, "explanation" => "JavaScript is the only programming language that runs natively in the browser."],
            ["question" => "Which HTML tag creates a hyperlink?",
             "choices"  => ["<link>","<href>","<a>","<url>"],
             "answer"   => 2, "explanation" => "The <a> (anchor) tag creates hyperlinks. The href attribute specifies the URL."],
            ["question" => "Where does PHP code run?",
             "choices"  => ["In the browser","On the server","In the database","In the CSS file"],
             "answer"   => 1, "explanation" => "PHP is a server-side language. The server runs it and sends HTML back to the browser."],
            ["question" => "Which CSS property changes text colour?",
             "choices"  => ["font-color","text-color","color","background-color"],
             "answer"   => 2, "explanation" => "The 'color' property sets the foreground (text) colour of an element."],
            ["question" => "How do you write a comment in JavaScript?",
             "choices"  => ["<!-- comment -->","# comment","// comment","/* comment */"],
             "answer"   => 2, "explanation" => "Single-line JavaScript comments start with //. Multi-line use /* ... */."],
        ],
    ],
    "php" => [
        "title"       => "PHP Deep Dive",
        "description" => "Push further with PHP arrays, functions, and form handling.",
        "icon"        => "💻",
        "questions"   => [
            ["question" => "Which function outputs data in PHP?",
             "choices"  => ["print_out()","display()","echo","write()"],
             "answer"   => 2, "explanation" => "echo is a language construct (not a function) used to output strings."],
            ["question" => "Which superglobal holds POST data in PHP?",
             "choices"  => ['$_GET','$_SESSION','$_POST','$_SERVER'],
             "answer"   => 2, "explanation" => '$_POST contains data submitted via an HTML form with method=post.'],
            ["question" => "What does the . operator do in PHP?",
             "choices"  => ["Divides numbers","Accesses object properties","Concatenates strings","Compares values"],
             "answer"   => 2, "explanation" => "The dot (.) operator joins strings together: 'Hello' . ' World' = 'Hello World'."],
            ["question" => "Which function counts array items in PHP?",
             "choices"  => ["length()","size()","count()","sizeof()"],
             "answer"   => 2, "explanation" => "count() returns the number of elements in an array or countable object."],
            ["question" => "What is a correct way to start a PHP code block?",
             "choices"  => ["<php>","<?php","<script php>","<%php%>"],
             "answer"   => 1, "explanation" => "PHP code blocks always open with <?php (the short tag <? is not recommended)."],
        ],
    ],
];

$activeQuiz = isset($_GET['quiz']) && isset($quizSets[$_GET['quiz']]) ? $_GET['quiz'] : 'web-basics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="../assets/aralpal-logo.png">
    <link rel="stylesheet" href="../assets/Style.css?v=quiz-refresh-20261104">
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
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">🌙</button>
    </div>
</header>
<div class="quiz-main-area">
<main class="container quiz-page">

    <section class="quiz-hero">
        <div class="quiz-hero__content">
            <span class="quiz-hero__badge"><span class="quiz-hero__spark">✦</span> LEARN • PRACTICE • IMPROVE</span>
            <p class="eyebrow">YOUR KNOWLEDGE CHECKPOINT</p>
            <h1>AralPal <span>Quizzes</span></h1>
            <p>Test your knowledge, track your progress, and become a better learner—one question at a time.</p>
            <a class="quiz-hero__cta" href="#quizWorkspace">▶ &nbsp; Start a Quiz <span>→</span></a>
            <div class="quiz-hero__facts">
                <span><strong><?= array_sum(array_map(fn($set) => count($set['questions']), $quizSets)) ?></strong> practice questions</span>
                <span><strong><?= count($quizSets) ?></strong> learning topics</span>
                <span><strong>∞</strong> chances to improve</span>
            </div>
        </div>
        <div class="quiz-hero__art" aria-hidden="true">
            <div class="quiz-hero__orb quiz-hero__orb--one"></div>
            <div class="quiz-hero__orb quiz-hero__orb--two"></div>
            <div class="quiz-hero__book">✎</div>
            <div class="quiz-hero__float quiz-hero__float--check">✓</div>
            <div class="quiz-hero__float quiz-hero__float--star">✦</div>
            <div class="quiz-hero__float quiz-hero__float--code">&lt;/&gt;</div>
        </div>
    </section>

    <!-- Quiz selector -->
    <div class="quiz-section-heading">
        <div><span class="eyebrow">PICK YOUR CHALLENGE</span><h2>Explore quiz topics</h2></div>
        <p>Select a topic to begin practicing.</p>
    </div>
    <div class="quiz-topic-heading"><h2>▦ &nbsp; Choose a Quiz Topic</h2><span>Pick a topic to get started</span></div>
    <div class="quiz-selector">
        <?php foreach ($quizSets as $key => $set): ?>
            <a
                href="?quiz=<?= htmlspecialchars($key) ?>"
                class="quiz-selector__btn <?= $key === $activeQuiz ? 'quiz-selector__btn--active' : '' ?>"
            >
                <span class="quiz-topic-icon"><?= $set['icon'] ?></span>
                <div class="quiz-topic-copy">
                    <strong><?= htmlspecialchars($set['title']) ?></strong>
                    <small><?= htmlspecialchars($set['description']) ?></small>
                    <em>● &nbsp; <?= count($set['questions']) ?> Questions</em>
                </div>
                <span class="quiz-topic-arrow">→</span>
            </a>
        <?php endforeach; ?>
        <div class="quiz-selector__btn quiz-topic-coming" aria-label="Computer Networks quiz coming soon">
            <span class="quiz-topic-icon">🌐</span>
            <div class="quiz-topic-copy"><strong>Computer Networks</strong><small>Explore connectivity, protocols, and network basics.</small><em>COMING SOON</em></div>
            <span class="quiz-topic-arrow">＋</span>
        </div>
        <div class="quiz-selector__btn quiz-topic-coming" aria-label="Database quiz coming soon">
            <span class="quiz-topic-icon">🗄️</span>
            <div class="quiz-topic-copy"><strong>Database Essentials</strong><small>Practice SQL, tables, and data relationships.</small><em>COMING SOON</em></div>
            <span class="quiz-topic-arrow">＋</span>
        </div>
    </div>

    <?php $quiz = $quizSets[$activeQuiz]; ?>

    <!-- Quiz workspace -->
    <div class="quiz-workspace" id="quizWorkspace">
    <div class="quiz-card">
        <div class="quiz-card__header">
            <span class="quiz-card__icon"><?= $quiz['icon'] ?></span>
            <div class="quiz-card__intro">
                <span class="quiz-card__tag">PRACTICE SESSION</span>
                <h2><?= htmlspecialchars($quiz['title']) ?></h2>
                <p><?= htmlspecialchars($quiz['description']) ?></p>
                <div class="quiz-card__meta"><span>◷ Self-paced</span><span>•</span><span><?= count($quiz['questions']) ?> questions</span><span>•</span><span>Instant feedback</span></div>
            </div>
            <div class="quiz-progress-ring" id="progressRing" aria-label="Quiz progress">
                <svg viewBox="0 0 44 44" width="52" height="52">
                    <circle cx="22" cy="22" r="18" fill="none" stroke="var(--clr-border)" stroke-width="4"/>
                    <circle id="progressArc" cx="22" cy="22" r="18" fill="none" stroke="var(--clr-primary)" stroke-width="4"
                        stroke-dasharray="113" stroke-dashoffset="113" stroke-linecap="round"
                        transform="rotate(-90 22 22)"/>
                </svg>
                <span id="progressText">0/<?= count($quiz['questions']) ?></span>
            </div>
        </div>

        <div class="quiz-progress-track" aria-hidden="true"><span id="quizProgressBar"></span></div>
        <div class="quiz-progress-caption"><span>YOUR PROGRESS</span><strong id="quizProgressLabel">0% completed</strong></div>
        <form id="quizForm" novalidate>
            <?php foreach ($quiz['questions'] as $i => $q): ?>
                <section class="quiz-question" id="qq-<?= $i ?>" data-question="<?= $i ?>">
                    <div class="quiz-question__topline"><div class="quiz-question__number"><span>Q<?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span> QUESTION <?= $i + 1 ?></div><span class="quiz-question__points">1 POINT</span></div>
                    <h3><?= htmlspecialchars($q['question']) ?></h3>
                    <p class="quiz-question__hint">Choose the best answer below.</p>
                    <div class="quiz-choices">
                        <?php foreach ($q['choices'] as $j => $choice): ?>
                            <label class="quiz-choice">
                                <input
                                    type="radio"
                                    name="q<?= $i ?>"
                                    value="<?= $j ?>"
                                    aria-label="<?= htmlspecialchars($choice) ?>"
                                >
                                <span class="quiz-choice__letter"><?= chr(65 + $j) ?></span>
                                <span class="quiz-choice__text"><?= htmlspecialchars($choice) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="quiz-explanation" id="exp-<?= $i ?>" hidden>
                        <span>💡</span>
                        <p><?= htmlspecialchars($q['explanation']) ?></p>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="quiz-actions">
                <button type="submit" class="primary-btn" id="submitBtn">
                    Submit Answers
                </button>
                <button type="button" class="outline-btn" id="retakeBtn" hidden>
                    🔄 Try Again
                </button>
            </div>
        </form>

        <!-- Result panel -->
        <div class="quiz-result" id="quizResult" hidden>
            <div class="quiz-result__score" id="resultScore"></div>
            <div class="quiz-result__message" id="resultMessage"></div>
            <div class="quiz-result__breakdown" id="resultBreakdown"></div>
        </div>
    </div>
    <aside class="quiz-sidebar">
        <section class="quiz-side-card quiz-overview">
            <h3><span>▥</span> Quiz Overview</h3>
            <div class="quiz-overview-row"><span><i class="overview-dot overview-dot--green">✓</i> Answered</span><strong id="overviewAnswered">0 / <?= count($quiz['questions']) ?></strong></div>
            <div class="quiz-overview-row"><span><i class="overview-dot overview-dot--blue">◷</i> Remaining</span><strong id="overviewRemaining"><?= count($quiz['questions']) ?></strong></div>
            <div class="quiz-overview-row"><span><i class="overview-dot overview-dot--gold">★</i> Score</span><strong id="overviewScore">—</strong></div>
        </section>
        <section class="quiz-side-card quiz-tip-card">
            <div class="quiz-tip-title"><span>💡</span><h3>Quick Tip</h3></div>
            <p>Take your time and read each question carefully. You can review the correct answers and explanations after submitting.</p>
            <span class="quiz-tip-decoration">✦</span>
        </section>
        <section class="quiz-side-card quiz-encouragement">
            <span>🎯</span><strong>Keep learning!</strong>
            <p>Every attempt helps you build confidence and improve your skills.</p>
        </section>
    </aside>
    </div>

    <!-- Other quizzes -->
    <div class="quiz-other">
        <h3>More quizzes</h3>
        <div class="feature-grid">
            <?php foreach ($quizSets as $key => $set): if ($key === $activeQuiz) continue; ?>
                <a href="?quiz=<?= htmlspecialchars($key) ?>" class="feature-card quiz-promo-card">
                    <div class="feature-icon"><?= $set['icon'] ?></div>
                    <h3><?= htmlspecialchars($set['title']) ?></h3>
                    <p><?= htmlspecialchars($set['description']) ?></p>
                    <span class="lesson-meta"><span><?= count($set['questions']) ?> questions</span></span>
                </a>
            <?php endforeach; ?>
            <div class="feature-card quiz-promo-card">
                <div class="feature-icon">🎯</div>
                <h3>More Quizzes Coming</h3>
                <p>We're adding quizzes on databases, CSS layouts, and JavaScript advanced topics.</p>
                <a class="outline-btn" href="../auth/register.php" style="margin-top:auto">Get notified →</a>
            </div>
        </div>
    </div>

</main>
<footer class="site-footer quiz-dashboard-footer"><p>&copy; <?= date('Y') ?> AralPal. All rights reserved.</p></footer>
</div><!-- /.quiz-main-area -->
</div><!-- /.quiz-app-shell -->

<script>
const answers     = <?= json_encode(array_column($quiz['questions'], 'answer')) ?>;
const totalQ      = answers.length;
const form        = document.getElementById('quizForm');
const submitBtn   = document.getElementById('submitBtn');
const retakeBtn   = document.getElementById('retakeBtn');
const resultPanel = document.getElementById('quizResult');
const arc         = document.getElementById('progressArc');
const progText    = document.getElementById('progressText');
const circumference = 2 * Math.PI * 18; // ≈ 113

/* Track answered questions for progress ring */
let answered = new Set();
form.querySelectorAll('input[type="radio"]').forEach(input => {
    input.addEventListener('change', () => {
        const match = input.name.match(/\d+/);
        if (match) answered.add(parseInt(match[0]));
        const ratio = answered.size / totalQ;
        arc.style.strokeDashoffset = circumference * (1 - ratio);
        progText.textContent = answered.size + '/' + totalQ;
        const percent = Math.round(ratio * 100);
        const progressBar = document.getElementById('quizProgressBar');
        const progressLabel = document.getElementById('quizProgressLabel');
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressLabel) progressLabel.textContent = percent + '% completed';
        const answeredEl = document.getElementById('overviewAnswered');
        const remainingEl = document.getElementById('overviewRemaining');
        if (answeredEl) answeredEl.textContent = answered.size + ' / ' + totalQ;
        if (remainingEl) remainingEl.textContent = totalQ - answered.size;
    });
});

form.addEventListener('submit', e => {
    e.preventDefault();
    let score = 0;
    let breakdown = '';

    answers.forEach((correct, i) => {
        const selected = form.querySelector(`input[name="q${i}"]:checked`);
        const expEl    = document.getElementById('exp-' + i);
        const qqEl     = document.getElementById('qq-' + i);
        const choices  = form.querySelectorAll(`input[name="q${i}"]`);

        /* Disable all inputs */
        choices.forEach(c => { c.disabled = true; });

        if (selected) {
            const isRight = Number(selected.value) === correct;
            if (isRight) score++;
            /* Style choice labels */
            choices.forEach(c => {
                const label = c.closest('label');
                if (parseInt(c.value) === correct) label.classList.add('quiz-choice--correct');
                else if (c.checked && !isRight) label.classList.add('quiz-choice--wrong');
            });
        } else {
            /* No answer — highlight correct */
            choices.forEach(c => {
                if (parseInt(c.value) === correct) c.closest('label').classList.add('quiz-choice--correct');
            });
        }

        /* Show explanation */
        if (expEl) expEl.hidden = false;
    });

    /* Score display */
    const pct = Math.round(score / totalQ * 100);
    const msgs = [
        [100, '🏆 Perfect score! Outstanding work!'],
        [80,  '🎉 Excellent! You really know your stuff.'],
        [60,  '👍 Good job! Review the highlighted answers to improve.'],
        [40,  '📖 Not bad — keep studying and try again.'],
        [0,   '💪 Keep practising! Every attempt makes you stronger.'],
    ];
    const msg = msgs.find(([min]) => pct >= min)[1];

    document.getElementById('resultScore').innerHTML =
        `<span class="quiz-score-number">${score}</span><span class="quiz-score-of">/ ${totalQ}</span><span class="quiz-score-pct">${pct}%</span>`;
    document.getElementById('resultMessage').textContent = msg;
    resultPanel.hidden = false;
    const overviewScore = document.getElementById('overviewScore');
    if (overviewScore) overviewScore.textContent = pct + '%';
    retakeBtn.hidden = false;
    submitBtn.hidden = true;
    resultPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });

    /* Update ring to 100% */
    arc.style.strokeDashoffset = 0;
    progText.textContent = totalQ + '/' + totalQ;
    const progressBar = document.getElementById('quizProgressBar');
    const progressLabel = document.getElementById('quizProgressLabel');
    if (progressBar) progressBar.style.width = '100%';
    if (progressLabel) progressLabel.textContent = '100% completed';
});

retakeBtn.addEventListener('click', () => {
    form.reset();
    resultPanel.hidden = true;
    retakeBtn.hidden = true;
    submitBtn.hidden = false;
    answered.clear();
    arc.style.strokeDashoffset = circumference;
    progText.textContent = '0/' + totalQ;
    const progressBar = document.getElementById('quizProgressBar');
    const progressLabel = document.getElementById('quizProgressLabel');
    if (progressBar) progressBar.style.width = '0%';
    if (progressLabel) progressLabel.textContent = '0% completed';
    const answeredEl = document.getElementById('overviewAnswered');
    const remainingEl = document.getElementById('overviewRemaining');
    const overviewScore = document.getElementById('overviewScore');
    if (answeredEl) answeredEl.textContent = '0 / ' + totalQ;
    if (remainingEl) remainingEl.textContent = totalQ;
    if (overviewScore) overviewScore.textContent = '—';
    form.querySelectorAll('label').forEach(l => {
        l.classList.remove('quiz-choice--correct', 'quiz-choice--wrong');
    });
    form.querySelectorAll('input[type="radio"]').forEach(c => { c.disabled = false; });
    document.querySelectorAll('.quiz-explanation').forEach(e => { e.hidden = true; });
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* Theme toggle */
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
