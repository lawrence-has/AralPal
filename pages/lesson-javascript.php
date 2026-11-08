<?php
$pageTitle  = "JavaScript Fundamentals – AralPal";
$lessonSlug = 'javascript';
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
        <span>JavaScript Fundamentals</span>
    </div>

    <div class="lesson-layout container">

        <!-- ── Main content ── -->
        <article class="lesson-content">

            <header class="lesson-header">
                <p class="lesson-category"><i class="fas fa-laptop-code"></i> Programming</p>
                <h1>JavaScript Fundamentals</h1>
                <p class="lesson-intro">Learn the basics of JavaScript to make web pages interactive. By the end of this lesson you'll understand variables, functions, and how to respond to user events.</p>

                <div class="lesson-meta">
                    <span><i class="fas fa-graduation-cap"></i> Beginner</span>
                    <span><i class="fas fa-clock"></i> 25 minutes</span>
                    <span><i class="fas fa-circle-check"></i> Free</span>
                    <span><i class="fas fa-book-open"></i> 3 topics</span>
                </div>
            </header>

            <nav class="lesson-toc" aria-label="Table of contents">
                <h3>In this lesson</h3>
                <ol>
                    <li><a href="#variables">Variables</a></li>
                    <li><a href="#functions">Functions</a></li>
                    <li><a href="#events">Events</a></li>
                </ol>
            </nav>

            <!-- Topic 1 -->
            <section class="lesson-topic" id="variables">
                <h2>1. Variables</h2>
                <p>Use <code>const</code> and <code>let</code> to store values. Prefer <code>const</code> when the value won't change; use <code>let</code> when it will.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>JavaScript</span></div>
<pre>const siteName = 'AralPal'; // cannot be reassigned
let score = 0;              // can change

score += 10;
console.log(score); // 10</pre>
                </div>
                <div class="lesson-callout lesson-callout--info">
                    <span><i class="fas fa-lightbulb"></i></span>
                    <p><strong>Key point:</strong> Avoid <code>var</code> — it has function scope and can cause subtle bugs. Stick to <code>const</code> and <code>let</code>.</p>
                </div>
            </section>

            <!-- Topic 2 -->
            <section class="lesson-topic" id="functions">
                <h2>2. Functions</h2>
                <p>Functions group reusable actions. You can define them as declarations or as arrow functions.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>JavaScript</span></div>
<pre>// Function declaration
function greet(name) {
    return 'Hello, ' + name + '!';
}

// Arrow function
const double = (n) => n * 2;

console.log(greet('Maria')); // Hello, Maria!
console.log(double(5));      // 10</pre>
                </div>
                <div class="lesson-callout lesson-callout--tip">
                    <span><i class="fas fa-circle-check"></i></span>
                    <p><strong>Tip:</strong> Arrow functions are concise and don't bind their own <code>this</code>, making them great for callbacks.</p>
                </div>
            </section>

            <!-- Topic 3 -->
            <section class="lesson-topic" id="events">
                <h2>3. Events</h2>
                <p>Event listeners run code when a user clicks, types, or interacts with an element.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>JavaScript</span></div>
<pre>const btn = document.getElementById('myBtn');

btn.addEventListener('click', () => {
    alert('Button clicked!');
});</pre>
                </div>
                <div class="lesson-callout lesson-callout--success">
                    <span><i class="fas fa-star"></i></span>
                    <p><strong>Well done!</strong> You now know how to use variables, write functions, and respond to user events in JavaScript.</p>
                </div>
            </section>

            <!-- Lesson navigation -->
            <div class="lesson-nav">
                <a class="outline-btn" href="lesson-html-css.php">← HTML &amp; CSS Basics</a>
                <a class="primary-btn" href="quizzes.php">Take a Quiz →</a>
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
                    <li>Declaring variables with const and let</li>
                    <li>Writing function declarations and arrow functions</li>
                    <li>Handling user events with addEventListener</li>
                </ul>
            </div>
            <div class="lesson-sidebar__card">
                <h3><i class="fas fa-book" style="margin-right:.4rem"></i>Related Lessons</h3>
                <a class="sidebar-lesson-link" href="lesson-php.php"><i class="fa-brands fa-php" style="margin-right:.4rem"></i>Introduction to PHP</a>
                <a class="sidebar-lesson-link" href="lesson-html-css.php"><i class="fa-brands fa-html5" style="margin-right:.4rem"></i>HTML &amp; CSS Basics</a>
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
        body: JSON.stringify({ lesson_slug: 'javascript', status: 'completed' })
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
