```php
<?php
$pageTitle = "AralPal - Quizzes";

$questions = [
    [
        "question" => "What does HTML stand for?",
        "choices" => [
            "Hyper Text Markup Language",
            "High Tech Modern Language",
            "Home Tool Markup Language",
            "Hyperlinks Text Management Language"
        ],
        "answer" => 0
    ],
    [
        "question" => "Which language is used to style web pages?",
        "choices" => ["PHP", "CSS", "SQL", "Python"],
        "answer" => 1
    ],
    [
        "question" => "Which symbol starts a PHP variable?",
        "choices" => ["#", "@", "$", "&"],
        "answer" => 2
    ],
    [
        "question" => "Which language adds interactivity to web pages?",
        "choices" => ["JavaScript", "HTML", "CSS", "XML"],
        "answer" => 0
    ],
    [
        "question" => "Which HTML tag creates a hyperlink?",
        "choices" => ["<link>", "<a>", "<href>", "<url>"],
        "answer" => 1
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="../assets/Style.css">
    <style>
        .quiz-container {
            max-width: 760px;
            margin: 45px auto;
            padding: 24px;
        }

        .quiz-question {
            padding: 20px;
            margin: 18px 0;
            border: 1px solid #dce2ea;
            border-radius: 12px;
        }

        .quiz-question h3 {
            margin-top: 0;
        }

        .quiz-choice {
            display: block;
            padding: 10px;
            margin: 8px 0;
            cursor: pointer;
        }

        .quiz-choice input {
            margin-right: 8px;
        }

        .quiz-submit,
        .quiz-retake {
            padding: 12px 20px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
        }

        .quiz-result {
            margin-top: 22px;
            padding: 20px;
            border-radius: 10px;
            background: #eef5ff;
        }

        .quiz-result:empty {
            display: none;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <a class="logo" href="../index.php">AralPal</a>
        <nav>
            <a href="../index.php">Home</a>
            <a href="../index.php#lessons">Lessons</a>
        </nav>
    </header>

    <main class="quiz-container">
        <p class="eyebrow">TEST YOUR KNOWLEDGE</p>
        <h1>AralPal Quick Quiz</h1>
        <p>Answer all five questions and check your score.</p>

        <form id="quizForm">
            <?php foreach ($questions as $i => $question): ?>
                <section class="quiz-question">
                    <h3>
                        <?= $i + 1 ?>.
                        <?= htmlspecialchars($question["question"]) ?>
                    </h3>

                    <?php foreach ($question["choices"] as $j => $choice): ?>
                        <label class="quiz-choice">
                            <input
                                type="radio"
                                name="question<?= $i ?>"
                                value="<?= $j ?>"
                                required
                            >
                            <?= htmlspecialchars($choice) ?>
                        </label>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <button type="submit" class="primary-btn quiz-submit">
                Submit Answers
            </button>
            <button type="button" id="retakeButton" class="quiz-retake">
                Retake Quiz
            </button>
        </form>

        <section
            id="quizResult"
            class="quiz-result"
            aria-live="polite"
        ></section>
    </main>

    <script>
        const answers = <?= json_encode(
            array_column($questions, "answer")
        ) ?>;

        const quizForm = document.getElementById("quizForm");
        const quizResult = document.getElementById("quizResult");
        const retakeButton = document.getElementById("retakeButton");

        quizForm.addEventListener("submit", function (event) {
            event.preventDefault();

            let score = 0;

            answers.forEach(function (correctAnswer, index) {
                const selected = quizForm.querySelector(
                    'input[name="question' + index + '"]:checked'
                );

                if (selected && Number(selected.value) === correctAnswer) {
                    score++;
                }
            });

            quizResult.textContent =
                "Your score: " + score + " out of " + answers.length +
                " (" + Math.round(score / answers.length * 100) + "%). " +
                (score === answers.length
                    ? "Excellent work!"
                    : "Keep practicing and try again!");

            quizResult.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
        });

        retakeButton.addEventListener("click", function () {
            quizForm.reset();
            quizResult.textContent = "";
        });
    </script>
</body>
</html>
```