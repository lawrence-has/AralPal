```php
<?php
$pageTitle = "AralPal - Learn at Your Own Pace";

$lessons = [
    [
        "icon" => "💻",
        "category" => "Programming",
        "title" => "Introduction to PHP",
        "description" => "Learn the basics of PHP and how it works in web development.",
        "level" => "Beginner",
        "duration" => "15 minutes",
        "link" => "pages/lesson-php.php"
    ],
    [
        "icon" => "🎨",
        "category" => "Web Design",
        "title" => "HTML and CSS Basics",
        "description" => "Discover how HTML structures a webpage and CSS styles it.",
        "level" => "Beginner",
        "duration" => "20 minutes",
        "link" => "pages/lesson-html-css.php"
    ],
    [
        "icon" => "⚡",
        "category" => "Programming",
        "title" => "JavaScript Fundamentals",
        "description" => "Understand variables, functions, and interactive web pages.",
        "level" => "Beginner",
        "duration" => "25 minutes",
        "link" => "pages/lesson-javascript.php"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/Style.css">
</head>
<body>
    <header class="navbar">
        <a class="logo" href="index.php">AralPal</a>
        <nav>
            <a href="#lessons">Lessons</a>
            <a href="#about">About</a>
            <a class="login-btn" href="pages/login.php">Login</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <p class="eyebrow">YOUR LEARNING COMPANION</p>
                <h1>Learn something new, <span>every day.</span></h1>
                <p class="description">
                    Welcome to AralPal! Explore lessons, test your knowledge
                    through quizzes, and track your learning progress.
                </p>
                <a class="primary-btn" href="#lessons">Start Learning</a>
            </div>

            <div class="hero-card">
                <div class="book-icon">📚</div>
                <h2>Your journey starts here.</h2>
                <p>Learn. Practice. Improve.</p>
                <div class="learning-stats">
                    <span>📖 3 Sample Lessons</span>
                    <span>🎯 Learn at your own pace</span>
                </div>
            </div>
        </section>

        <section class="features" id="lessons">
            <div class="section-heading">
                <p class="eyebrow">YOUR LEARNING LIBRARY</p>
                <h2>Explore Our Lessons</h2>
                <p>Choose a topic and start building your skills.</p>
            </div>

            <div class="lesson-toolbar">
                <label for="lessonSearch">Find a lesson</label>
                <input
                    type="search"
                    id="lessonSearch"
                    placeholder="Search lessons or topics..."
                    aria-label="Search lessons"
                >
            </div>

            <div class="feature-grid" id="lessonGrid">
                <?php foreach ($lessons as $lesson): ?>
                    <article
                        class="feature-card lesson-card"
                        data-search="<?= htmlspecialchars(strtolower(
                            $lesson["title"] . " " .
                            $lesson["category"] . " " .
                            $lesson["description"]
                        )) ?>"
                    >
                        <div class="feature-icon">
                            <?= htmlspecialchars($lesson["icon"]) ?>
                        </div>

                        <p class="lesson-category">
                            <?= htmlspecialchars($lesson["category"]) ?>
                        </p>

                        <h3><?= htmlspecialchars($lesson["title"]) ?></h3>

                        <p>
                            <?= htmlspecialchars($lesson["description"]) ?>
                        </p>

                        <div class="lesson-meta">
                            <span><?= htmlspecialchars($lesson["level"]) ?></span>
                            <span>⏱ <?= htmlspecialchars($lesson["duration"]) ?></span>
                        </div>

                        <a
                            class="primary-btn lesson-btn"
                            href="<?= htmlspecialchars($lesson["link"]) ?>"
                        >
                            View Lesson →
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>

            <p id="noLessons" class="no-lessons" hidden>
                No lessons found. Try another keyword.
            </p>
        </section>

        <section class="features" id="learning-features">
            <div class="section-heading">
                <p class="eyebrow">LEARN YOUR WAY</p>
                <h2>Make Every Study Session Count</h2>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon">📖</div>
                    <h3>Easy-to-Follow Lessons</h3>
                    <p>Study learning materials at your own pace.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">✍️</div>
                    <h3>Practice with Quizzes</h3>
                    <p>Test your knowledge after reviewing each topic.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Track Your Progress</h3>
                    <p>Build good study habits and monitor your achievements.</p>
                </article>
            </div>
        </section>

        <section class="about" id="about">
            <h2>Learning is better with AralPal.</h2>
            <p>
                A student learning website designed to make studying
                more accessible, organized, and engaging.
            </p>
            <a class="primary-btn" href="#lessons">Explore Lessons</a>
        </section>
    </main>

    <footer>
        <p>&copy; <?= date("Y") ?> AralPal. All rights reserved.</p>
    </footer>

    <script>
        const lessonSearch = document.getElementById("lessonSearch");
        const lessonCards = document.querySelectorAll(".lesson-card");
        const noLessons = document.getElementById("noLessons");

        lessonSearch.addEventListener("input", function () {
            const keyword = this.value.toLowerCase().trim();
            let visibleCount = 0;

            lessonCards.forEach(function (card) {
                const searchableText = card.dataset.search;
                const matches = searchableText.includes(keyword);

                card.hidden = !matches;

                if (matches) {
                    visibleCount++;
                }
            });

            noLessons.hidden = visibleCount !== 0;
        });
    </script>
</body>
</html>
```