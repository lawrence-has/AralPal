```php
<?php
$pageTitle = "My Learning Progress - AralPal";

$activities = [
    [
        "title" => "Introduction to PHP",
        "status" => "Completed",
        "progress" => 100
    ],
    [
        "title" => "HTML and CSS Basics",
        "status" => "In Progress",
        "progress" => 50
    ],
    [
        "title" => "JavaScript Fundamentals",
        "status" => "Not Started",
        "progress" => 0
    ]
];

$totalProgress = 0;

foreach ($activities as $activity) {
    $totalProgress += $activity["progress"];
}

$overallProgress = round($totalProgress / count($activities));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="../assets/Style.css">
    <style>
        .progress-container {
            max-width: 850px;
            margin: 50px auto;
            padding: 20px;
        }

        .progress-summary, .progress-card {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .progress-track {
            height: 12px;
            background: #e5e7eb;
            border-radius: 20px;
            overflow: hidden;
            margin: 12px 0;
        }

        .progress-fill {
            height: 100%;
            background: #38a169;
        }

        .status {
            font-weight: bold;
        }

        .progress-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <a class="logo" href="../index.php">AralPal</a>
        <nav>
            <a href="../index.php">Home</a>
            <a href="../pages/quizzes.php">Quizzes</a>
        </nav>
    </header>

    <main class="progress-container">
        <h1>My Learning Progress</h1>
        <p>Track your learning activities and see how far you have progressed.</p>

        <section class="progress-summary">
            <h2>Overall Progress: <?= $overallProgress ?>%</h2>
            <div class="progress-track">
                <div class="progress-fill"
                     style="width: <?= $overallProgress ?>%"></div>
            </div>
            <p><?= count($activities) ?> learning activities</p>
        </section>

        <?php foreach ($activities as $activity): ?>
            <article class="progress-card">
                <h3><?= htmlspecialchars($activity["title"]) ?></h3>
                <p class="status">
                    Status: <?= htmlspecialchars($activity["status"]) ?>
                </p>
                <div class="progress-track">
                    <div class="progress-fill"
                         style="width: <?= $activity["progress"] ?>%"></div>
                </div>
                <p><?= $activity["progress"] ?>% complete</p>
            </article>
        <?php endforeach; ?>

        <div class="progress-links">
            <a class="primary-btn" href="../index.php">Back to Lessons</a>
            <a class="primary-btn" href="quizzes.php">Take a Quiz</a>
        </div>
    </main>
</body>
</html>
```