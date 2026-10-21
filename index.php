
<?php
$pageTitle = "AralPal - Learn at Your Own Pace";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            </div>
        </section>

        <section class="features" id="lessons">
            <div class="section-heading">
                <p class="eyebrow">EXPLORE ARALPAL</p>
                <h2>Everything you need to learn</h2>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon">📖</div>
                    <h3>Lessons</h3>
                    <p>Read learning materials and study at your own pace.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">✍️</div>
                    <h3>Quizzes</h3>
                    <p>Answer questions and check what you have learned.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Progress Tracking</h3>
                    <p>Monitor your quiz scores and learning achievements.</p>
                </article>
            </div>
        </section>

        <section class="about" id="about">
            <h2>Learning is better with AralPal.</h2>
            <p>
                A student learning website designed to make studying
                more accessible, organized, and engaging.
            </p>
        </section>
    </main>

    <footer>
        <p>&copy; <?= date("Y") ?> AralPal. All rights reserved.</p>
    </footer>
</body>
</html>
