<?php
$pageTitle = "AralPal – Learn at Your Own Pace";

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/strapi.php';

/* ── CMS: try Strapi first, fall back to local DB ── */
$cmsContent       = [];
$cmsAnnouncements = [];

// Try Strapi
$strapiHomepage = strapi_get_homepage();
$strapiLessons  = strapi_get_lessons();
$strapiAnnouncements = strapi_get_announcements();

// Fall back to local CMS DB if Strapi returns nothing
if (empty($strapiHomepage['hero_title']) && is_file(__DIR__ . '/admin/cms-config.php')) {
    try {
        require_once __DIR__ . '/admin/cms-config.php';
        $cmsRows = db()->query('SELECT section_key, title, body FROM cms_content')->fetchAll();
        foreach ($cmsRows as $cmsRow) {
            $cmsContent[$cmsRow['section_key']] = $cmsRow;
        }
        $cmsAnnouncements = db()->query(
            'SELECT title, body, published_at FROM cms_announcements
             WHERE is_published = 1
             ORDER BY published_at DESC, created_at DESC LIMIT 5'
        )->fetchAll();
    } catch (Throwable $e) {}
} elseif (!empty($strapiAnnouncements)) {
    $cmsAnnouncements = $strapiAnnouncements;
}

$homeHeroTitle       = $strapiHomepage['hero_title']       ?: ($cmsContent['home_hero_title']['title']      ?? 'Learn something new,');
$homeHeroDescription = $strapiHomepage['hero_description'] ?: ($cmsContent['home_hero_description']['body'] ?? 'Welcome to AralPal! Explore lessons, test your knowledge with quizzes, connect with expert tutors, and track your learning progress — all in one place.');
$aboutTitle          = $strapiHomepage['about_title']      ?: ($cmsContent['about_title']['title']          ?? 'Learning is better with AralPal.');
$aboutBody           = $strapiHomepage['about_body']       ?: ($cmsContent['about_body']['body']            ?? 'A student learning platform designed to make studying more accessible, organized, and engaging — whether you\'re a beginner or brushing up on the basics.');

// Use Strapi lessons if available, otherwise fall back to hardcoded lessons
$lessons = !empty($strapiLessons) ? $strapiLessons : [
    [
        "icon"        => "💻",
        "category"    => "Programming",
        "title"       => "Introduction to PHP",
        "description" => "Learn how PHP works on the server, how to write dynamic pages, and the core syntax every developer needs.",
        "level"       => "Beginner",
        "duration"    => "15 min",
        "link"        => "pages/lesson-php.php",
        "color"       => "#6366f1",
    ],
    [
        "icon"        => "🎨",
        "category"    => "Web Design",
        "title"       => "HTML & CSS Basics",
        "description" => "Discover how HTML structures a webpage and how CSS controls layout, colour, and typography.",
        "level"       => "Beginner",
        "duration"    => "20 min",
        "link"        => "pages/lesson-html-css.php",
        "color"       => "#f59e0b",
    ],
    [
        "icon"        => "⚡",
        "category"    => "Programming",
        "title"       => "JavaScript Fundamentals",
        "description" => "Understand variables, functions, events, and how JavaScript makes web pages interactive.",
        "level"       => "Beginner",
        "duration"    => "25 min",
        "link"        => "pages/lesson-javascript.php",
        "color"       => "#10b981",
    ],
];

$categories = array_unique(array_column($lessons, 'category'));

/* Live stats from DB if available */
$totalTutors   = 0;
$totalStudents = 0;
try {
    $totalTutors   = (int) get_db()->query("SELECT COUNT(*) FROM tutors")->fetchColumn();
    $totalStudents = (int) get_db()->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.role_name='Student'")->fetchColumn();
} catch (Throwable $e) {}

$subscriptionPlans = [];
try {
    $stmt = get_db()->query("SELECT * FROM subscription_plans WHERE user_type='Student' ORDER BY price ASC LIMIT 3");
    $subscriptionPlans = $stmt->fetchAll();
} catch (Throwable $e) {
    $subscriptionPlans = [
        ['plan_name'=>'Free',    'price'=>0,   'features'=>'["3 bookings/month","Browse tutors","Basic search"]'],
        ['plan_name'=>'Basic',   'price'=>199, 'features'=>'["10 bookings/month","Subject filters","Email notifications"]'],
        ['plan_name'=>'Premium', 'price'=>499, 'features'=>'["Unlimited bookings","Priority support","Compare tutors","Advanced filters"]'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="AralPal – a free student learning platform with lessons, quizzes, progress tracking, and expert tutors.">
    <link rel="icon" type="image/png" href="assets/aralpal-logo.png">
    <link rel="stylesheet" href="assets/Style.css?v=20261104-realfix">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>
    (function(){var s=localStorage.getItem('ap-theme'),d=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;document.documentElement.setAttribute('data-theme',(s?s==='dark':d)?'dark':'light')})();
    </script>
</head>
<body>

<!-- ── NAVBAR ────────────────────────────────────────────────────── -->
<header class="navbar" id="navbar">
    <a class="logo logo-brand" href="index.php" aria-label="AralPal home">
        <img src="assets/aralpal-logo.png" alt="" class="brand-logo" width="34" height="34">
        <span>AralPal</span>
    </a>
    <nav>
        <a href="#lessons">Lessons</a>
        <a href="#tutors-cta">Tutors</a>
        <a href="#features">Features</a>
        <a href="#plans">Plans</a>
        <a href="#about">About</a>
        <a href="auth/login.php" class="nav-login-btn">Login</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙</button>
    </nav>
</header>

<main>

<!-- ── HERO ──────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">YOUR LEARNING COMPANION</p>
        <h1>
            <?= htmlspecialchars($homeHeroTitle) ?>
            <span><?= isset($cmsContent['home_hero_title']) ? '' : 'every day.' ?></span>
        </h1>
        <p class="description"><?= htmlspecialchars($homeHeroDescription) ?></p>
        <div class="hero-actions">
            <a class="primary-btn" href="#lessons">🚀 Start Learning</a>
            <a class="outline-btn" href="auth/register.php">Create Free Account</a>
            <a class="outline-btn" href="tutors/index.php">Find a Tutor</a>
        </div>
        <div class="hero-trust">
            <span>✅ 100% Free to start</span>
            <span>📚 <?= count($lessons) ?> lessons available</span>
            <span>🎯 Self-paced learning</span>
        </div>
    </div>

    <div class="hero-card">
        <div class="book-icon">📚</div>
        <h2>Your journey starts here.</h2>
        <p>Learn. Practice. Improve.</p>

        <div class="learning-stats">
            <div class="stat-row">
                <span class="stat-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                <div class="stat-info">
                    <span class="stat-label">Available Lessons</span>
                    <span class="stat-value"><?= count($lessons) ?> Lessons Ready</span>
                </div>
            </div>
            <div class="stat-row">
                <span class="stat-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                <div class="stat-info">
                    <span class="stat-label">Expert Tutors</span>
                    <span class="stat-value"><?= $totalTutors > 0 ? $totalTutors . ' tutors online' : 'Book a session' ?></span>
                </div>
            </div>
            <div class="stat-row">
                <span class="stat-icon"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span>
                <div class="stat-info">
                    <span class="stat-label">Quizzes</span>
                    <span class="stat-value">Test your knowledge</span>
                </div>
            </div>
            <div class="stat-row">
                <span class="stat-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                <div class="stat-info">
                    <span class="stat-label">Progress Tracking</span>
                    <span class="stat-value">See your growth</span>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ── STATS ROW ─────────────────────────────────────────────────── -->
<div class="features fade-in-section" id="statsSection">
    <div class="stats-row">
        <div class="stat-card">
            <span class="counter" data-target="<?= count($lessons) ?>">0</span>
            <p>Free Lessons</p>
        </div>
        <div class="stat-card">
            <span class="counter" data-target="<?= max($totalTutors, 10) ?>">0</span>
            <p>Expert Tutors</p>
        </div>
        <div class="stat-card">
            <span class="counter" data-target="5">0</span>
            <p>Quizzes</p>
        </div>
        <div class="stat-card">
            <span class="counter" data-target="100">0</span>
            <p>% Free to Start</p>
        </div>
        <div class="stat-card">
            <span class="counter" data-target="<?= count($categories) ?>">0</span>
            <p>Subject Areas</p>
        </div>
    </div>
</div>

<?php if ($cmsAnnouncements): ?>
<!-- ── ANNOUNCEMENTS ──────────────────────────────────────────────── -->
<section class="features announcements-public fade-in-section">
    <div class="section-heading">
        <p class="eyebrow">LATEST UPDATES</p>
        <h2>Announcements</h2>
        <p>Stay up to date with what's new on AralPal.</p>
    </div>
    <div class="feature-grid">
        <?php foreach ($cmsAnnouncements as $ann): ?>
            <article class="feature-card">
                <div class="feature-icon">📢</div>
                <h3><?= htmlspecialchars($ann['title']) ?></h3>
                <p><?= nl2br(htmlspecialchars($ann['body'])) ?></p>
                <?php if (!empty($ann['published_at'])): ?>
                    <p class="lesson-meta"><span>📅 <?= date('M j, Y', strtotime($ann['published_at'])) ?></span></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── LESSONS ───────────────────────────────────────────────────── -->
<section class="features fade-in-section learning-library-section" id="lessons">
    <div class="lessons-header">
        <div class="lessons-header__text">
            <p class="eyebrow">YOUR LEARNING LIBRARY</p>
            <h2>Explore Our Lessons</h2>
            <p>Free, self-paced lessons built for beginners. Pick a topic and start right now.</p>
        </div>
        <div class="lessons-header__badges">
            <span class="lessons-badge">✅ 100% Free</span>
            <span class="lessons-badge">🎓 Beginner-friendly</span>
            <span class="lessons-badge">⏱ Under 30 min each</span>
        </div>
    </div>

    <div class="lesson-toolbar">
        <div class="search-wrap">
            <span class="search-icon" aria-hidden="true">🔍</span>
            <input
                type="search"
                id="lessonSearch"
                placeholder="Search lessons or topics…"
                aria-label="Search lessons"
                autocomplete="off"
            >
        </div>
        <div class="filter-tabs" role="group" aria-label="Filter by category">
            <button class="filter-tab active" data-filter="all">All</button>
            <?php foreach ($categories as $cat): ?>
                <button class="filter-tab" data-filter="<?= htmlspecialchars(strtolower($cat)) ?>">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lessons-grid" id="lessonGrid">
        <?php foreach ($lessons as $index => $lesson): ?>
            <article
                class="lesson-tile fade-in-section"
                data-search="<?= htmlspecialchars(strtolower($lesson['title'].' '.$lesson['category'].' '.$lesson['description'])) ?>"
                data-category="<?= htmlspecialchars(strtolower($lesson['category'])) ?>"
                style="--accent: <?= $lesson['color'] ?>; animation-delay: <?= $index * 0.1 ?>s"
            >
                <div class="lesson-tile__top">
                    <span class="lesson-tile__icon"><?= htmlspecialchars($lesson['icon']) ?></span>
                    <span class="lesson-tile__cat"><?= htmlspecialchars($lesson['category']) ?></span>
                </div>
                <h3 class="lesson-tile__title"><?= htmlspecialchars($lesson['title']) ?></h3>
                <p class="lesson-tile__desc"><?= htmlspecialchars($lesson['description']) ?></p>
                <div class="lesson-tile__meta">
                    <span class="lesson-tile__tag">🎓 <?= htmlspecialchars($lesson['level']) ?></span>
                    <span class="lesson-tile__tag">⏱ <?= htmlspecialchars($lesson['duration']) ?></span>
                    <span class="lesson-tile__tag lesson-tile__tag--free">✅ Free</span>
                </div>
                <a class="lesson-tile__btn" href="<?= htmlspecialchars($lesson['link']) ?>">
                    Start Lesson <span aria-hidden="true">→</span>
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <p id="noLessons" class="no-lessons" hidden>
        <span>🔎</span>No lessons found. Try a different keyword or filter.
    </p>

    <div class="lessons-more">
        <p>More lessons are on the way.</p>
        <a class="outline-btn" href="auth/register.php">Get notified when new lessons drop</a>
    </div>
</section>

<!-- ── FEATURES ──────────────────────────────────────────────────── -->
<section class="features fade-in-section" id="features">
    <div class="section-heading">
        <p class="eyebrow">WHY ARALPAL</p>
        <h2>Everything You Need to Learn</h2>
        <p>Built for students who want to study smarter, not harder.</p>
    </div>

    <div class="feature-grid">
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></div>
            <h3>Self-Paced Lessons</h3>
            <p>Study on your own schedule. No deadlines, no pressure — just focused learning at a pace that works for you.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-pencil" aria-hidden="true"></i></div>
            <h3>Interactive Quizzes</h3>
            <p>Test what you've learned after each topic. Get instant feedback so you know exactly where to focus next.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
            <h3>Progress Tracking</h3>
            <p>Watch your progress grow lesson by lesson. Visual indicators keep you motivated and on track.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></div>
            <h3>Expert Tutors</h3>
            <p>Book one-on-one sessions with qualified tutors. Get personalised help when you need it most.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></div>
            <h3>Smart Tutor Search</h3>
            <p>Filter tutors by subject, rating, availability, and price. Find the perfect match in seconds.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></div>
            <h3>Real-Time Notifications</h3>
            <p>Stay updated with booking confirmations, session reminders, and platform announcements.</p>
        </article>
    </div>
</section>

<!-- ── HOW IT WORKS ───────────────────────────────────────────────── -->
<section class="features fade-in-section steps-section" id="how-it-works">
    <div class="section-heading">
        <p class="eyebrow">GET STARTED IN MINUTES</p>
        <h2>How AralPal Works</h2>
        <p>Three simple steps from zero to learning.</p>
    </div>
    <div class="hiw-grid">
        <div class="hiw-card">
            <div class="hiw-card__step">01</div>
            <div class="hiw-card__icon-wrap">
                <span class="hiw-card__icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span>
            </div>
            <h3>Create a Free Account</h3>
            <p>Sign up as a Student or Tutor in under a minute. No credit card, no commitment.</p>
            <a class="hiw-card__link" href="auth/register.php">Register now →</a>
        </div>
        <div class="hiw-card">
            <div class="hiw-card__step">02</div>
            <div class="hiw-card__icon-wrap">
                <span class="hiw-card__icon"><i class="fa-solid fa-book" aria-hidden="true"></i></span>
            </div>
            <h3>Explore &amp; Learn</h3>
            <p>Browse free lessons, take interactive quizzes, and track your progress — all at your own pace.</p>
            <a class="hiw-card__link" href="#lessons">Start learning →</a>
        </div>
        <div class="hiw-card">
            <div class="hiw-card__step">03</div>
            <div class="hiw-card__icon-wrap">
                <span class="hiw-card__icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
            </div>
            <h3>Book a Tutor</h3>
            <p>When you need personal guidance, find a qualified tutor, pick a time, and book instantly.</p>
            <a class="hiw-card__link" href="tutors/index.php">Find a tutor →</a>
        </div>
    </div>
</section>

<!-- ── TUTORS CTA ─────────────────────────────────────────────────── -->
<section class="features fade-in-section" id="tutors-cta">
    <div class="tutors-banner">
        <div class="tutors-banner__content">
            <p class="eyebrow">LEARN WITH AN EXPERT</p>
            <h2>Book a 1-on-1 Tutor Session</h2>
            <p>Our tutors cover Mathematics, Science, English, Filipino, Computer Science, and more. Browse profiles, check ratings, and book a time that suits your schedule.</p>
            <div class="hero-actions" style="margin-top: 24px;">
                <a class="primary-btn" href="tutors/index.php">🔍 Browse Tutors</a>
                <a class="outline-btn" href="auth/register.php">Sign Up Free</a>
            </div>
        </div>
        <div class="tutors-banner__subjects">
            <div class="subject-tag">📐 Mathematics</div>
            <div class="subject-tag">🔬 Science</div>
            <div class="subject-tag">📝 English</div>
            <div class="subject-tag">🇵🇭 Filipino</div>
            <div class="subject-tag">💻 Computer Science</div>
            <div class="subject-tag">🎨 Web Design</div>
        </div>
    </div>
</section>

<!-- ── PLANS ──────────────────────────────────────────────────────── -->
<section class="pricing-section features fade-in-section enhanced-pricing-section" id="plans">
    <div class="section-heading">
        <p class="eyebrow">SIMPLE PRICING</p>
        <h2>Choose Your Plan</h2>
        <p>Start free. Upgrade any time for more bookings and premium features.</p>
    </div>
    <div class="pricing-cards-grid">
        <?php foreach ($subscriptionPlans as $i => $plan): ?>
            <?php
            $features   = json_decode($plan['features'] ?? '[]', true) ?: [];
            $price      = (float)($plan['price'] ?? 0);
            $planName   = $plan['plan_name'] ?? $plan['name'] ?? 'Plan';
            $isFeatured = $i === 1;
            ?>
            <div class="pricing-tile">

                <div class="pricing-tile__top">
                    <span class="pricing-tile__name"><?= htmlspecialchars($planName) ?></span>
                    <div class="pricing-tile__price">
                        <?php if ($price <= 0): ?>
                            <span class="pricing-tile__amount">Free</span>
                        <?php else: ?>
                            <span class="pricing-tile__currency">₱</span>
                            <span class="pricing-tile__amount"><?= number_format($price, 0) ?></span>
                            <span class="pricing-tile__period">/mo</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($features): ?>
                    <ul class="pricing-tile__features">
                        <?php foreach ($features as $feat): ?>
                            <li>
                                <span class="pricing-tile__check" aria-hidden="true">✓</span>
                                <?= htmlspecialchars($feat) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a class="outline-btn pricing-tile__cta"
                   href="auth/register.php">
                    <?= $price <= 0 ? 'Get Started Free' : 'Get Started' ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="pricing-note">No hidden fees. Cancel any time. Questions? Check our <a href="#faq">FAQ</a>.</p>
</section>

<!-- ── FAQ ────────────────────────────────────────────────────────── -->
<section class="features fade-in-section enhanced-faq-section" id="faq">
    <div class="section-heading">
        <p class="eyebrow">FREQUENTLY ASKED QUESTIONS</p>
        <h2>Got Questions?</h2>
    </div>
    <div class="faq-list">
        <details class="faq-item">
            <summary>Is AralPal really free?</summary>
            <p>Yes! All lessons, quizzes, and progress tracking are 100% free. You only need a paid plan if you want more than 3 tutor bookings per month.</p>
        </details>
        <details class="faq-item">
            <summary>How do I book a tutor?</summary>
            <p>Create a free account, go to Find a Tutor, search by subject or name, and pick an available time slot. It takes less than 2 minutes.</p>
        </details>
        <details class="faq-item">
            <summary>Can I become a tutor on AralPal?</summary>
            <p>Absolutely. Register as a Tutor, fill in your profile, set your subjects and availability, and students can start booking you right away.</p>
        </details>
        <details class="faq-item">
            <summary>What subjects are covered?</summary>
            <p>We currently have lessons on PHP, HTML & CSS, and JavaScript, with more topics coming soon. Tutors cover Mathematics, Science, English, Filipino, and Computer Science.</p>
        </details>
        <details class="faq-item">
            <summary>Is my data safe?</summary>
            <p>Yes. We use secure sessions, hashed passwords, and CSRF protection across all forms. We never share your data with third parties.</p>
        </details>
    </div>
</section>

<!-- ── ABOUT / CTA ────────────────────────────────────────────────── -->
<section class="about fade-in-section enhanced-about-section" id="about">
    <div class="about-inner">
        <p class="eyebrow" style="-webkit-text-fill-color:#fff;opacity:.8">ABOUT ARALPAL</p>
        <h2><?= htmlspecialchars($aboutTitle) ?></h2>
        <p><?= nl2br(htmlspecialchars($aboutBody)) ?></p>
        <div class="about-highlights" aria-label="AralPal highlights">
            <div><span>🎓</span><strong>Student-focused</strong><small>Learning at your pace</small></div>
            <div><span>👨‍🏫</span><strong>Find a tutor</strong><small>Guidance when you need it</small></div>
            <div><span>📈</span><strong>Track progress</strong><small>See your growth over time</small></div>
        </div>
        <div class="hero-actions" style="justify-content:center">
            <a class="primary-btn about-btn-primary" href="auth/register.php">Join Free Today</a>
            <a class="outline-btn about-btn-outline" href="#lessons">Explore Lessons</a>
        </div>
    </div>
</section>

</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a class="logo logo-brand footer-logo" href="index.php">
                <img src="assets/aralpal-logo.png" alt="" class="brand-logo" width="28" height="28">
                <span>AralPal</span>
            </a>
            <p>A free student learning platform for everyone.</p>
        </div>
        <div class="footer-links">
            <div class="footer-col">
                <h4>Learn</h4>
                <a href="#lessons">Lessons</a>
                <a href="pages/quizzes.php">Quizzes</a>
                <a href="pages/progress.php">My Progress</a>
            </div>
            <div class="footer-col">
                <h4>Tutors</h4>
                <a href="tutors/index.php">Find a Tutor</a>
                <a href="auth/register.php">Become a Tutor</a>
                <a href="subscriptions/plans.php">Plans</a>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <a href="auth/login.php">Login</a>
                <a href="auth/register.php">Register</a>
                <a href="auth/forgot-password.php">Forgot Password</a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> AralPal. All rights reserved.</p>
        <p>Built with ❤️ for Filipino learners.</p>
    </div>
</footer>

<button class="back-to-top" id="backToTop" aria-label="Back to top">↑</button>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
/* Dark mode */
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;
function applyTheme(dark) {
    html.setAttribute('data-theme', dark ? 'dark' : 'light');
    if (themeToggle) {
        themeToggle.textContent = dark ? '☀️' : '🌙';
        themeToggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    localStorage.setItem('ap-theme', dark ? 'dark' : 'light');
}
applyTheme(html.getAttribute('data-theme') === 'dark');
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const isDark = html.getAttribute('data-theme') !== 'dark';
        applyTheme(isDark);
        showToast(isDark ? '🌙 Dark mode on' : '☀️ Light mode on');
    });
}

/* Navbar scroll shadow + back to top */
const navbar  = document.getElementById('navbar');
const backTop = document.getElementById('backToTop');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 10);
    backTop.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });
backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* Toast */
const toast = document.getElementById('toast');
let toastTimer;
function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
}

/* Fade-in observer */
new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: 0.1 }).observe && document.querySelectorAll('.fade-in-section').forEach(el =>
    new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
    }, { threshold: 0.1 }).observe(el)
);

/* Stagger lesson cards */
const cardObs = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
        if (e.isIntersecting) {
            setTimeout(() => { e.target.classList.add('visible'); cardObs.unobserve(e.target); }, i * 80);
        }
    });
}, { threshold: 0.1 });
document.querySelectorAll('.lesson-tile').forEach(c => cardObs.observe(c));

/* Counters */
function animateCounter(el) {
    const target = parseInt(el.dataset.target, 10);
    const start  = performance.now();
    const step   = now => {
        const p = Math.min((now - start) / 1200, 1);
        el.textContent = Math.round((1 - Math.pow(1 - p, 3)) * target);
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}
new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); } });
}, { threshold: 0.5 }).observe && document.querySelectorAll('.counter').forEach(el =>
    new IntersectionObserver((e) => { if (e[0].isIntersecting) { animateCounter(el); } }, { threshold: 0.5 }).observe(el)
);

/* Progress bar demo */
window.addEventListener('load', () => {
    setTimeout(() => {
        const fill  = document.getElementById('progressFill');
        const label = document.getElementById('progressLabel');
        if (fill && label) { fill.style.width = '42%'; label.textContent = '42%'; }
    }, 800);
});

/* Lesson search & filter */
const lessonSearch = document.getElementById('lessonSearch');
const lessonCards  = document.querySelectorAll('.lesson-tile');
const noLessons    = document.getElementById('noLessons');
const filterTabs   = document.querySelectorAll('.filter-tab');
let activeFilter   = 'all';

function applyFilters() {
    const kw = lessonSearch.value.toLowerCase().trim();
    let visible = 0;
    lessonCards.forEach(card => {
        const show = (kw === '' || card.dataset.search.includes(kw))
                  && (activeFilter === 'all' || card.dataset.category === activeFilter);
        card.hidden = !show;
        if (show) visible++;
    });
    noLessons.hidden = visible > 0;
}
lessonSearch.addEventListener('input', applyFilters);
filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        filterTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        activeFilter = tab.dataset.filter;
        applyFilters();
        showToast(activeFilter === 'all' ? '📚 Showing all' : `🔖 ${tab.textContent.trim()}`);
    });
});
</script>
</body>
</html>
