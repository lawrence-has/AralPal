<?php
$pageTitle  = "HTML and CSS Basics – AralPal";
$lessonSlug = 'html-css';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Track visit: upsert in_progress (only when logged in)
if (is_logged_in()) {
    $userId = (int) current_user()['id'];
    get_db()->prepare("
        INSERT INTO lesson_progress (user_id, lesson_slug, status, visited_at)
        VALUES (?, ?, 'in_progress', NOW())
        ON DUPLICATE KEY UPDATE visited_at = NOW()
    ")->execute([$userId, $lessonSlug]);
}
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
    <a class="logo logo-brand" href="../index.php" aria-label="AralPal home">
        <img src="../assets/aralpal-logo.png" alt="" class="brand-logo" width="34" height="34">
        <span>AralPal</span>
    </a>
    <nav>
        <a href="../index.php">Home</a>
        <a href="../index.php#lessons">All Lessons</a>
        <a href="quizzes.php">Quizzes</a>
        <a href="progress.php">My Progress</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
    </nav>
</header>

<main class="lesson-page-wrap">

    <!-- Breadcrumb -->
    <div class="container lesson-breadcrumb">
        <a href="../index.php">Home</a>
        <span aria-hidden="true">›</span>
        <a href="../index.php#lessons">Lessons</a>
        <span aria-hidden="true">›</span>
        <span>HTML &amp; CSS Basics</span>
    </div>

    <div class="lesson-layout container">

        <!-- ── Main content ── -->
        <article class="lesson-content">

            <header class="lesson-header">
                <p class="lesson-category"><i class="fas fa-palette"></i> Web Design</p>
                <h1>HTML &amp; CSS Basics</h1>
                <p class="lesson-intro">Learn how HTML structures a page and CSS controls its appearance. By the end of this lesson you'll be able to build and style your own web pages from scratch.</p>

                <div class="lesson-meta">
                    <span><i class="fas fa-graduation-cap"></i> Beginner</span>
                    <span><i class="fas fa-clock"></i> 20 minutes</span>
                    <span><i class="fas fa-circle-check"></i> Free</span>
                    <span><i class="fas fa-book-open"></i> 3 topics</span>
                </div>
            </header>

            <nav class="lesson-toc" aria-label="Table of contents">
                <h3>In this lesson</h3>
                <ol>
                    <li><a href="#html-structure">HTML structure</a></li>
                    <li><a href="#css-selectors">CSS selectors</a></li>
                    <li><a href="#responsive-design">Responsive design</a></li>
                </ol>
            </nav>

            <!-- Topic 1 -->
            <section class="lesson-topic" id="html-structure">
                <h2>1. HTML structure</h2>
                <p>HTML uses elements such as headings, paragraphs, links, and sections to organize content. Every HTML page starts with a <code>&lt;!DOCTYPE html&gt;</code> declaration and wraps content in <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>, and <code>&lt;body&gt;</code> tags.</p>
                <div class="lesson-callout lesson-callout--info">
                    <span><i class="fas fa-lightbulb"></i></span>
                    <p><strong>Key point:</strong> HTML gives a page its <em>structure</em>. Think of it as the skeleton — CSS is what makes it look good.</p>
                </div>
            </section>

            <!-- Topic 2 -->
            <section class="lesson-topic" id="css-selectors">
                <h2>2. CSS selectors</h2>
                <p>CSS selectors choose which HTML elements receive styles. You can target elements by tag name, class, or ID.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>CSS</span></div>
<pre>/* tag selector */
h1 { color: navy; }

/* class selector */
.card { border-radius: 8px; padding: 16px; }

/* id selector */
#hero { background: #f0f4ff; }</pre>
                </div>
                <div class="lesson-callout lesson-callout--tip">
                    <span><i class="fas fa-circle-check"></i></span>
                    <p><strong>Tip:</strong> Prefer classes over IDs for styling — IDs have very high specificity and can be harder to override.</p>
                </div>
            </section>

            <!-- Topic 3 -->
            <section class="lesson-topic" id="responsive-design">
                <h2>3. Responsive design</h2>
                <p>Media queries help layouts adapt to phones, tablets, and desktop screens. Combine them with flexible units like <code>%</code>, <code>em</code>, and <code>rem</code> for fluid layouts.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>CSS – media query</span></div>
<pre>/* default: desktop */
.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }

/* tablet and below */
@media (max-width: 768px) {
    .grid { grid-template-columns: 1fr; }
}</pre>
                </div>
                <div class="lesson-callout lesson-callout--success">
                    <span><i class="fas fa-star"></i></span>
                    <p><strong>Great work!</strong> You now know HTML structure, CSS selectors, and how to make layouts responsive.</p>
                </div>
            </section>

            <!-- Lesson navigation -->
            <div class="lesson-nav">
                <a class="outline-btn" href="lesson-php.php">← Introduction to PHP</a>
                <a class="primary-btn" href="lesson-javascript.php">Next: JavaScript Fundamentals →</a>
            </div>

            <!-- Mark as Complete -->
            <?php if (is_logged_in()): ?>
            <div class="lesson-complete-wrap" style="margin-top:32px;text-align:center" id="completeWrap">
                <button class="primary-btn" id="markCompleteBtn" onclick="markLessonComplete()">
                    <i class="fas fa-circle-check" style="margin-right:.4rem"></i>Mark as Complete
                </button>
                <p id="completeMsg" style="display:none;margin-top:12px;color:var(--color-success,#22a06b);font-weight:600">
                    <i class="fas fa-circle-check"></i> Lesson marked as complete!
                </p>
            </div>
            <?php endif; ?>

        </article>

        <!-- ── Sidebar ── -->
        <aside class="lesson-sidebar">
            <div class="lesson-sidebar__card">
                <h3><i class="fas fa-bullseye" style="margin-right:.4rem"></i>What you'll learn</h3>
                <ul>
                    <li>How to structure an HTML page</li>
                    <li>CSS selectors and how they work</li>
                    <li>Making layouts responsive with media queries</li>
                </ul>
            </div>
            <div class="lesson-sidebar__card">
                <h3><i class="fas fa-book" style="margin-right:.4rem"></i>Related Lessons</h3>
                <a class="sidebar-lesson-link" href="lesson-php.php"><i class="fa-brands fa-php" style="margin-right:.4rem"></i>Introduction to PHP</a>
                <a class="sidebar-lesson-link" href="lesson-javascript.php"><i class="fa-brands fa-js" style="margin-right:.4rem"></i>JavaScript Fundamentals</a>
            </div>
            <div class="lesson-sidebar__card">
                <h3><i class="fas fa-pen-to-square" style="margin-right:.4rem"></i>Test Yourself</h3>
                <p>Ready to check what you've learned?</p>
                <a class="primary-btn" href="quizzes.php" style="margin-top:12px">Take the Quiz</a>
            </div>
            <div class="lesson-sidebar__card">
                <h3><i class="fas fa-chalkboard-user" style="margin-right:.4rem"></i>Need Help?</h3>
                <p>Book a one-on-one session with an expert tutor.</p>
                <a class="outline-btn" href="../tutors/index.php" style="margin-top:12px">Find a Tutor</a>
            </div>
        </aside>

    </div>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> AralPal. All rights reserved.</p>
</footer>

<script>
/* Theme toggle */
const tt = document.getElementById('themeToggle');
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    if (tt) { tt.innerHTML = dark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>'; }
    localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
}
applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
if (tt) tt.addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark'));

/* Navbar scroll */
const nb = document.getElementById('navbar');
if (nb) window.addEventListener('scroll', () => nb.classList.toggle('scrolled', window.scrollY > 10), { passive: true });

/* Mark as Complete */
function markLessonComplete() {
    const btn = document.getElementById('markCompleteBtn');
    if (btn) btn.disabled = true;
    fetch('/AralPal/pages/mark-progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= htmlspecialchars(generate_csrf()) ?>'
        },
        body: JSON.stringify({ lesson_slug: 'html-css', status: 'completed' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const msg = document.getElementById('completeMsg');
            if (msg) msg.style.display = 'block';
            if (btn) btn.style.display = 'none';
        } else {
            if (btn) btn.disabled = false;
        }
    })
    .catch(() => { if (btn) btn.disabled = false; });
}
</script>
</body>
</html>
