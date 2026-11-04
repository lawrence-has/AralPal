<?php
$pageTitle = "JavaScript Fundamentals - AralPal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="../assets/Style.css">
<style>
.lesson-page{max-width:850px;margin:42px auto;padding:24px}
.lesson-topic{background:#fff;border-radius:12px;padding:22px;margin:18px 0;box-shadow:0 3px 12px rgba(0,0,0,.07)}
.lesson-topic h2{margin-top:0}.back-link{display:inline-block;margin:8px 0 20px;color:#315de8;font-weight:700}
</style>
</head>
<body>
<header class="navbar"><a class="logo" href="../index.php">AralPal</a><nav><a href="../index.php#lessons">All lessons</a><a href="quizzes.php">Quizzes</a><a href="progress.php">Progress</a></nav></header>
<main class="lesson-page">
<a class="back-link" href="../index.php#lessons">← Back to lessons</a>
<p class="eyebrow">ARALPAL LEARNING LIBRARY</p>
<h1>JavaScript Fundamentals</h1>
<p>Learn the basics of JavaScript for interactive web pages.</p>
<section class="lesson-topic"><h2>Variables</h2><p>Use const and let to store values.</p></section>
<section class="lesson-topic"><h2>Functions</h2><p>Functions group reusable actions.</p></section>
<section class="lesson-topic"><h2>Events</h2><p>Event listeners run code when a user clicks, types, or interacts with an element.</p></section>
<a class="primary-btn" href="quizzes.php">Practice with a quiz →</a>
</main>
</body>
</html>
