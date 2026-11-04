<?php
$pageTitle = "Introduction to PHP – AralPal";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$_page_title = $pageTitle;
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
    <a class="logo logo-brand" href="../index.php" aria-label="AralPal home">
        <img src="../assets/aralpal-logo.png" alt="" class="brand-logo" width="34" height="34">
        <span>AralPal</span>
    </a>
    <nav>
        <a href="../index.php">Home</a>
        <a href="../index.php#lessons">All Lessons</a>
        <a href="quizzes.php">Quizzes</a>
        <a href="progress.php">My Progress</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙</button>
    </nav>
</header>

<main class="lesson-page-wrap">

    <!-- Breadcrumb -->
    <div class="container lesson-breadcrumb">
        <a href="../index.php">Home</a>
        <span aria-hidden="true">›</span>
        <a href="../index.php#lessons">Lessons</a>
        <span aria-hidden="true">›</span>
        <span>Introduction to PHP</span>
    </div>

    <div class="lesson-layout container">

        <!-- ── Main content ── -->
        <article class="lesson-content">

            <header class="lesson-header">
                <p class="lesson-category">💻 Programming</p>
                <h1>Introduction to PHP</h1>
                <p class="lesson-intro">Learn how PHP powers the web — from simple output to handling forms and talking to databases. By the end of this lesson you'll understand what PHP does and how to write your first dynamic page.</p>

                <div class="lesson-meta">
                    <span>🎓 Beginner</span>
                    <span>⏱ 15 minutes</span>
                    <span>✅ Free</span>
                    <span>📖 5 topics</span>
                </div>
            </header>

            <nav class="lesson-toc" aria-label="Table of contents">
                <h3>In this lesson</h3>
                <ol>
                    <li><a href="#what-is-php">What is PHP?</a></li>
                    <li><a href="#how-php-works">How PHP works</a></li>
                    <li><a href="#basic-output">Basic output</a></li>
                    <li><a href="#variables">Variables &amp; data types</a></li>
                    <li><a href="#control-flow">Control flow</a></li>
                    <li><a href="#functions">Functions</a></li>
                </ol>
            </nav>

            <!-- Topic 1 -->
            <section class="lesson-topic" id="what-is-php">
                <h2>1. What is PHP?</h2>
                <p>PHP (PHP: Hypertext Preprocessor) is a server-side scripting language designed for web development. It runs on the web server — not in the browser — and generates HTML that is sent to the client.</p>
                <p>PHP is used by major platforms like WordPress, Facebook (in its early days), and Wikipedia. It powers over 75% of all websites that use a server-side language.</p>
                <div class="lesson-callout lesson-callout--info">
                    <span>💡</span>
                    <p><strong>Key point:</strong> PHP code is processed on the <em>server</em>. The user only ever sees the resulting HTML — never your PHP source code.</p>
                </div>
            </section>

            <!-- Topic 2 -->
            <section class="lesson-topic" id="how-php-works">
                <h2>2. How PHP works</h2>
                <p>When a browser requests a <code>.php</code> file, the server runs the PHP interpreter, executes the code, and returns plain HTML to the browser.</p>
                <div class="lesson-flow">
                    <div class="lesson-flow__step">🌐 Browser sends request</div>
                    <div class="lesson-flow__arrow">→</div>
                    <div class="lesson-flow__step">🖥 Server runs PHP</div>
                    <div class="lesson-flow__arrow">→</div>
                    <div class="lesson-flow__step">📄 HTML sent back</div>
                    <div class="lesson-flow__arrow">→</div>
                    <div class="lesson-flow__step">🖥 Browser renders page</div>
                </div>
                <p>PHP files start with <code>&lt;?php</code> and end with <code>?&gt;</code>. You can mix PHP and HTML freely in the same file.</p>
            </section>

            <!-- Topic 3 -->
            <section class="lesson-topic" id="basic-output">
                <h2>3. Basic output</h2>
                <p>Use <code>echo</code> or <code>print</code> to send text to the browser.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>PHP</span><button class="code-copy-btn" data-code="echo_example">Copy</button></div>
<pre id="echo_example">&lt;?php
echo "Hello, World!";
echo "&lt;p&gt;Welcome to AralPal!&lt;/p&gt;";

// print works the same way
print "Learning PHP is fun.";
?&gt;</pre>
                </div>
                <div class="lesson-callout lesson-callout--tip">
                    <span>✅</span>
                    <p><strong>Tip:</strong> Use double quotes <code>"..."</code> when you need to embed variables. Use single quotes <code>'...'</code> for plain strings — they're slightly faster.</p>
                </div>
            </section>

            <!-- Topic 4 -->
            <section class="lesson-topic" id="variables">
                <h2>4. Variables &amp; data types</h2>
                <p>PHP variables start with a dollar sign <code>$</code>. You don't need to declare the type — PHP figures it out automatically.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>PHP</span><button class="code-copy-btn" data-code="vars_example">Copy</button></div>
<pre id="vars_example">&lt;?php
$name    = "AralPal";          // string
$version = 1.0;                // float
$isReady = true;               // boolean
$lessons = 3;                  // integer
$tags    = ["PHP","CSS","JS"]; // array

echo "Welcome to " . $name;
echo "&lt;br&gt;Lessons available: " . $lessons;
?&gt;</pre>
                </div>
                <div class="lesson-types-table">
                    <table>
                        <thead><tr><th>Type</th><th>Example</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>string</code></td><td><code>"Hello"</code></td><td>Text</td></tr>
                            <tr><td><code>int</code></td><td><code>42</code></td><td>Whole number</td></tr>
                            <tr><td><code>float</code></td><td><code>3.14</code></td><td>Decimal number</td></tr>
                            <tr><td><code>bool</code></td><td><code>true</code></td><td>True or false</td></tr>
                            <tr><td><code>array</code></td><td><code>[1, 2, 3]</code></td><td>List of values</td></tr>
                            <tr><td><code>null</code></td><td><code>null</code></td><td>No value</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Topic 5 -->
            <section class="lesson-topic" id="control-flow">
                <h2>5. Control flow</h2>
                <p>Control flow lets your code make decisions and repeat actions.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>PHP – if / else</span></div>
<pre>$score = 85;

if ($score >= 90) {
    echo "Excellent!";
} elseif ($score >= 75) {
    echo "Good job!";
} else {
    echo "Keep practising.";
}</pre>
                </div>
                <div class="code-block" style="margin-top:16px">
                    <div class="code-block__header"><span>PHP – foreach loop</span></div>
<pre>$lessons = ["PHP", "HTML", "CSS", "JavaScript"];

foreach ($lessons as $lesson) {
    echo "&lt;li&gt;" . $lesson . "&lt;/li&gt;";
}</pre>
                </div>
            </section>

            <!-- Topic 6 -->
            <section class="lesson-topic" id="functions">
                <h2>6. Functions</h2>
                <p>Functions group reusable code. Define with <code>function</code>, call it by name.</p>
                <div class="code-block">
                    <div class="code-block__header"><span>PHP</span></div>
<pre>function greet(string $name): string {
    return "Hello, " . $name . "!";
}

echo greet("Maria");  // Hello, Maria!
echo greet("Juan");   // Hello, Juan!</pre>
                </div>
                <div class="lesson-callout lesson-callout--success">
                    <span>🎉</span>
                    <p><strong>You've covered the basics!</strong> PHP variables, output, control flow, and functions are the foundation of everything else you'll build.</p>
                </div>
            </section>

            <!-- Lesson navigation -->
            <div class="lesson-nav">
                <div></div>
                <a class="primary-btn" href="lesson-html-css.php">Next: HTML & CSS Basics →</a>
            </div>

        </article>

        <!-- ── Sidebar ── -->
        <aside class="lesson-sidebar">
            <div class="lesson-sidebar__card">
                <h3>🎯 What you'll learn</h3>
                <ul>
                    <li>What PHP is and how it works</li>
                    <li>How to output text to the browser</li>
                    <li>Variables and data types</li>
                    <li>If/else and loops</li>
                    <li>Writing and calling functions</li>
                </ul>
            </div>
            <div class="lesson-sidebar__card">
                <h3>📚 Related Lessons</h3>
                <a class="sidebar-lesson-link" href="lesson-html-css.php">🎨 HTML & CSS Basics</a>
                <a class="sidebar-lesson-link" href="lesson-javascript.php">⚡ JavaScript Fundamentals</a>
            </div>
            <div class="lesson-sidebar__card">
                <h3>✍️ Test Yourself</h3>
                <p>Ready to check what you've learned?</p>
                <a class="primary-btn" href="quizzes.php" style="margin-top:12px">Take the Quiz</a>
            </div>
            <div class="lesson-sidebar__card">
                <h3>👨‍🏫 Need Help?</h3>
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
    if (tt) { tt.textContent = dark ? '☀️' : '🌙'; }
    localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
}
applyTheme(document.documentElement.getAttribute('data-theme') === 'dark');
if (tt) tt.addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark'));

/* Navbar scroll */
const nb = document.getElementById('navbar');
if (nb) window.addEventListener('scroll', () => nb.classList.toggle('scrolled', window.scrollY > 10), { passive: true });

/* Copy button */
document.querySelectorAll('.code-copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const pre = document.getElementById(btn.dataset.code);
        if (pre) {
            navigator.clipboard.writeText(pre.textContent).then(() => {
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = 'Copy', 2000);
            });
        }
    });
});
</script>
</body>
</html>
