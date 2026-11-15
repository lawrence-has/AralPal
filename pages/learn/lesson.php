<?php
/**
 * AralPal – Individual Lesson Page
 * Loads lesson metadata from the lessons table and shows content
 * from a PHP array keyed by slug. Marks lesson in_progress on visit.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$db      = get_db();
$user    = current_user();
$userId  = (int)$user['id'];

// Strict Student-only access — redirect all other roles
$_lesson_role = strtolower((string)($user['role_name'] ?? ''));
if ($_lesson_role === 'tutor') {
    header('Location: /AralPal/dashboard/tutor.php');
    exit;
}
if ($_lesson_role === 'admin') {
    header('Location: /AralPal/admin/users.php');
    exit;
}
if ($_lesson_role !== 'student') {
    // Unknown or missing role — reject
    http_response_code(403);
    exit('Access denied.');
}

$isStudent = true; // guaranteed by the gate above

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /AralPal/pages/learn/index.php');
    exit;
}

// Load lesson metadata from DB
$meta_stmt = $db->prepare("SELECT * FROM lessons WHERE slug = ? AND is_active = 1 LIMIT 1");
$meta_stmt->execute([$slug]);
$lesson = $meta_stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    $page_title = 'Lesson Not Found';
    require_once __DIR__ . '/../../includes/app-header.php';
    echo '<div style="padding:2rem"><h2>Lesson not found.</h2><p><a href="/AralPal/pages/learn/index.php">Back to Learning Center</a></p></div>';
    require_once __DIR__ . '/../../includes/app-footer.php';
    exit;
}

// Mark in_progress for students on visit
if ($isStudent) {
    $db->prepare("
        INSERT INTO lesson_progress (user_id, lesson_slug, status, visited_at)
        VALUES (?, ?, 'in_progress', NOW())
        ON DUPLICATE KEY UPDATE visited_at = NOW()
    ")->execute([$userId, $slug]);
}

// Get current progress
$prog_stmt = $db->prepare("SELECT status FROM lesson_progress WHERE user_id = ? AND lesson_slug = ? LIMIT 1");
$prog_stmt->execute([$userId, $slug]);
$progress = $prog_stmt->fetchColumn() ?: 'not_started';

// Check if slug has quiz questions.
// quiz_questions is created lazily by pages/quizzes.php — handle the case
// where the table does not yet exist by catching the PDOException.
$hasQuiz = false;
try {
    $quiz_stmt = $db->prepare("SELECT COUNT(*) FROM quiz_questions WHERE lesson_slug = ?");
    $quiz_stmt->execute([$slug]);
    $hasQuiz = (int)$quiz_stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    // Table doesn't exist yet — no quiz available for this lesson
    $hasQuiz = false;
}

// ── Lesson content ─────────────────────────────────────────────────────────────
// Each entry: objectives[], sections[]{title, body, code?}, related[]
$CONTENT = [

// ── Web Development ──────────────────────────────────────────────────────────

'html-css' => [
    'objectives' => ['Understand HTML document structure','Use semantic HTML elements','Apply CSS selectors and properties','Make layouts responsive with media queries'],
    'sections'   => [
        ['title' => '1. HTML Document Structure',
         'body'  => 'Every HTML page starts with a DOCTYPE declaration followed by an html element containing head and body. The head holds metadata such as the page title and CSS links; the body contains the visible content.',
         'code'  => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"UTF-8\">\n  <title>My Page</title>\n</head>\n<body>\n  <h1>Hello World</h1>\n  <p>Welcome to my website.</p>\n</body>\n</html>"],
        ['title' => '2. Semantic HTML',
         'body'  => 'Semantic elements like header, nav, main, section, article, aside, and footer tell browsers and screen readers what each part of the page means. Using them improves accessibility and SEO.',
         'code'  => "<header>\n  <nav><a href=\"/\">Home</a></nav>\n</header>\n<main>\n  <article>\n    <h2>Article Title</h2>\n    <p>Content goes here.</p>\n  </article>\n</main>\n<footer><p>&copy; 2026</p></footer>"],
        ['title' => '3. CSS Selectors and the Box Model',
         'body'  => 'CSS styles elements via selectors (tag, class, ID). Every element is a rectangular box with content, padding, border, and margin. Understanding the box model is essential for controlling spacing and layout.',
         'code'  => "/* Tag selector */\np { color: #333; font-size: 1rem; }\n\n/* Class selector */\n.card {\n  padding: 1rem;\n  border: 1px solid #ddd;\n  margin-bottom: 1rem;\n  border-radius: 8px;\n}\n\n/* ID selector */\n#hero { background: #f0f4ff; }"],
        ['title' => '4. Responsive Design with Media Queries',
         'body'  => 'Media queries let you apply different styles for different screen sizes. A mobile-first approach starts with styles for small screens and uses min-width queries to enhance wider layouts.',
         'code'  => ".grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }\n\n@media (min-width: 640px) {\n  .grid { grid-template-columns: 1fr 1fr; }\n}\n\n@media (min-width: 1024px) {\n  .grid { grid-template-columns: repeat(3, 1fr); }\n}"],
    ],
    'related' => ['html-fundamentals','css-fundamentals','responsive-design'],
],

'html-fundamentals' => [
    'objectives' => ['Write valid HTML5 documents','Use headings, paragraphs, lists, links, and images','Build accessible HTML forms','Understand block vs inline elements'],
    'sections'   => [
        ['title' => '1. Text Elements',
         'body'  => 'HTML provides six heading levels (h1–h6) for hierarchy, p for paragraphs, strong and em for emphasis, and span for inline styling. Headings should follow a logical order — do not skip from h1 to h4.',
         'code'  => "<h1>Main Title</h1>\n<h2>Section Title</h2>\n<p>A <strong>bold</strong> word and an <em>italic</em> word.</p>"],
        ['title' => '2. Lists, Links, and Images',
         'body'  => 'Ordered lists (ol) are numbered; unordered lists (ul) use bullets. Anchor tags create links; the href attribute holds the destination URL. Images use the img tag with a required alt attribute for accessibility.',
         'code'  => "<ul>\n  <li>HTML</li>\n  <li>CSS</li>\n  <li>JavaScript</li>\n</ul>\n\n<a href=\"https://example.com\" target=\"_blank\">Visit Example</a>\n\n<img src=\"photo.jpg\" alt=\"A descriptive caption\" width=\"400\">"],
        ['title' => '3. HTML Forms',
         'body'  => 'Forms collect user input using input, textarea, select, and button elements. Every form control should have a label associated via the for/id pair. Use appropriate input types (email, number, date) for better mobile keyboards and built-in validation.',
         'code'  => "<form action=\"/submit\" method=\"post\">\n  <label for=\"email\">Email</label>\n  <input type=\"email\" id=\"email\" name=\"email\" required>\n\n  <label for=\"message\">Message</label>\n  <textarea id=\"message\" name=\"message\" rows=\"4\"></textarea>\n\n  <button type=\"submit\">Send</button>\n</form>"],
    ],
    'related' => ['html-css','css-fundamentals','javascript'],
],

'css-fundamentals' => [
    'objectives' => ['Apply styles with selectors and specificity','Use flexbox for one-dimensional layouts','Control typography, colours, and spacing','Understand inheritance and the cascade'],
    'sections'   => [
        ['title' => '1. The Cascade and Specificity',
         'body'  => 'When multiple rules target the same element the browser uses specificity to decide which wins. Inline styles beat IDs, which beat classes, which beat tag selectors. The cascade also considers rule order — later rules override earlier ones at equal specificity.',
         'code'  => "/* Specificity: 0,0,1 (one tag) */\np { color: black; }\n\n/* Specificity: 0,1,0 (one class) — wins */\n.intro { color: navy; }\n\n/* Specificity: 1,0,0 (one ID) — always wins */\n#hero-text { color: crimson; }"],
        ['title' => '2. Flexbox Layout',
         'body'  => 'Flexbox is a one-dimensional layout system. Setting display: flex on a container makes its direct children flex items. Use justify-content to align items on the main axis and align-items on the cross axis.',
         'code'  => ".navbar {\n  display: flex;\n  justify-content: space-between;\n  align-items: center;\n  padding: 1rem;\n}\n\n.nav-links {\n  display: flex;\n  gap: 1rem;\n  list-style: none;\n}"],
        ['title' => '3. Typography and Colours',
         'body'  => 'Set a base font on the body element. Use rem units for font sizes so they scale with the user\'s browser settings. CSS custom properties (variables) make it easy to maintain a consistent colour palette.',
         'code'  => ":root {\n  --clr-primary: #3b82f6;\n  --clr-text: #1a1d2e;\n  --font-base: 'Inter', sans-serif;\n}\n\nbody {\n  font-family: var(--font-base);\n  color: var(--clr-text);\n  font-size: 1rem;\n  line-height: 1.6;\n}"],
    ],
    'related' => ['html-fundamentals','responsive-design','dom-manipulation'],
],

'responsive-design' => [
    'objectives' => ['Apply a mobile-first CSS strategy','Use CSS Grid for two-dimensional layouts','Use viewport units and fluid typography','Test designs across screen sizes'],
    'sections'   => [
        ['title' => '1. Mobile-First Strategy',
         'body'  => 'Write base styles for mobile screens first, then use min-width media queries to enhance the layout as the viewport grows. This ensures your design works on all devices without overriding mobile styles.',
         'code'  => "/* Base: mobile */\n.container { padding: 1rem; }\n\n/* Tablet */\n@media (min-width: 768px) {\n  .container { padding: 1.5rem 2rem; }\n}\n\n/* Desktop */\n@media (min-width: 1024px) {\n  .container { max-width: 1200px; margin: 0 auto; }\n}"],
        ['title' => '2. CSS Grid',
         'body'  => 'CSS Grid is a two-dimensional layout system. Define rows and columns with grid-template-columns, and place items using line numbers or named areas. Auto-fill and minmax() create responsive grids without media queries.',
         'code'  => ".card-grid {\n  display: grid;\n  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));\n  gap: 1.5rem;\n}"],
        ['title' => '3. Fluid Typography with clamp()',
         'body'  => 'The clamp() function takes three values: a minimum, a preferred fluid value, and a maximum. Use it on font-size to have text scale smoothly between breakpoints.',
         'code'  => "h1 {\n  /* Min 1.5rem, fluid, max 2.5rem */\n  font-size: clamp(1.5rem, 4vw, 2.5rem);\n}"],
    ],
    'related' => ['css-fundamentals','html-css','dom-manipulation'],
],

'javascript' => [
    'objectives' => ['Declare variables with let and const','Write functions and understand scope','Handle user events','Understand arrays and objects'],
    'sections'   => [
        ['title' => '1. Variables and Data Types',
         'body'  => 'JavaScript has six primitive types: string, number, boolean, null, undefined, and symbol. Use const for values that won\'t change, let for those that will. Avoid var — it has confusing function scope.',
         'code'  => "const name = 'Alice';     // string\nlet age  = 25;            // number\nconst active = true;      // boolean\n\nconsole.log(\`Hello, \${name}! You are \${age}.\`);"],
        ['title' => '2. Functions and Arrow Functions',
         'body'  => 'Functions bundle reusable logic. ES6 arrow functions provide a shorter syntax and do not rebind this. Functions can accept parameters and return values.',
         'code'  => "// Traditional\nfunction greet(name) {\n  return \`Hello, \${name}!\`;\n}\n\n// Arrow function\nconst square = (n) => n * n;\n\nconsole.log(greet('Bob')); // Hello, Bob!\nconsole.log(square(4));    // 16"],
        ['title' => '3. Events and the DOM',
         'body'  => 'JavaScript can respond to user actions like clicks, form submissions, and keyboard input using event listeners. The DOM is the browser\'s object representation of the HTML document.',
         'code'  => "const btn = document.getElementById('myBtn');\n\nbtn.addEventListener('click', function () {\n  alert('Button clicked!');\n});\n\n// Or with an arrow function:\nbtn.addEventListener('click', () => {\n  document.querySelector('h1').textContent = 'Changed!';\n});"],
    ],
    'related' => ['dom-manipulation','html-fundamentals','css-fundamentals'],
],

'dom-manipulation' => [
    'objectives' => ['Select elements with querySelector','Create, modify, and remove DOM nodes','Handle events with addEventListener','Understand event bubbling and delegation'],
    'sections'   => [
        ['title' => '1. Selecting Elements',
         'body'  => 'querySelector returns the first element matching a CSS selector; querySelectorAll returns a NodeList of all matches. Use getElementById for fast ID lookups.',
         'code'  => "const title    = document.querySelector('h1');\nconst cards    = document.querySelectorAll('.card');\nconst loginBtn = document.getElementById('loginBtn');"],
        ['title' => '2. Creating and Modifying Elements',
         'body'  => 'Create new elements with createElement, set their content and attributes, then attach them to the document with appendChild or insertAdjacentElement.',
         'code'  => "const li = document.createElement('li');\nli.textContent = 'New item';\nli.classList.add('list-item');\ndocument.querySelector('ul').appendChild(li);"],
        ['title' => '3. Event Delegation',
         'body'  => 'Instead of attaching a listener to every child element, attach one listener to the parent and check event.target. This is more efficient and works for elements added dynamically.',
         'code'  => "document.querySelector('#todo-list').addEventListener('click', function (e) {\n  if (e.target.matches('.delete-btn')) {\n    e.target.closest('li').remove();\n  }\n});"],
    ],
    'related' => ['javascript','html-fundamentals','css-fundamentals'],
],

'php' => [
    'objectives' => ['Write PHP syntax and output HTML','Use variables, arrays, and control flow','Define and call functions','Handle form data securely'],
    'sections'   => [
        ['title' => '1. PHP Syntax Basics',
         'body'  => 'PHP code lives inside <?php ?> tags embedded in HTML. Use echo or print to output content. Variable names start with a dollar sign. PHP is loosely typed but supports strict type declarations.',
         'code'  => '<?php' . "\n" . '$name  = \'AralPal\';' . "\n" . '$year  = 2026;' . "\n" . '$price = 19.99;' . "\n\n" . 'echo "Welcome to $name!";' . "\n" . 'echo \'<p>Year: \' . $year . \'</p>\';' . "\n" . '?>'],
        ['title' => '2. Control Flow',
         'body'  => 'PHP supports if/elseif/else, switch, while, for, and foreach. The foreach loop is especially useful for iterating arrays.',
         'code'  => '<?php' . "\n" . '$fruits = [\'apple\', \'banana\', \'mango\'];' . "\n\n" . 'foreach ($fruits as $index => $fruit) {' . "\n" . '    echo "<li>$index: $fruit</li>";' . "\n" . '}' . "\n\n" . '$score = 85;' . "\n" . 'if ($score >= 90) {' . "\n" . '    echo \'Excellent!\';' . "\n" . '} elseif ($score >= 75) {' . "\n" . '    echo \'Good job!\';' . "\n" . '} else {' . "\n" . '    echo \'Keep practising.\';' . "\n" . '}' . "\n" . '?>'],
        ['title' => '3. Functions and Forms',
         'body'  => 'Functions in PHP are defined with the function keyword. When handling HTML form data, always validate and sanitise inputs. Use htmlspecialchars() to prevent XSS when echoing user-submitted values.',
         'code'  => '<?php' . "\n" . 'function greet(string $name): string {' . "\n" . '    return \'Hello, \' . htmlspecialchars($name, ENT_QUOTES, \'UTF-8\') . \'!\';' . "\n" . '}' . "\n\n" . '// Handle POST form' . "\n" . 'if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {' . "\n" . '    $email = trim($_POST[\'email\'] ?? \'\');' . "\n" . '    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {' . "\n" . '        echo greet($email);' . "\n" . '    } else {' . "\n" . '        echo \'Invalid email.\';' . "\n" . '    }' . "\n" . '}' . "\n" . '?>'],
    ],
    'related' => ['php-mysql','crud-applications','variables-types'],
],

'php-mysql' => [
    'objectives' => ['Connect PHP to MySQL using PDO','Execute SELECT, INSERT, UPDATE, DELETE queries safely','Use prepared statements to prevent SQL injection','Display database records in HTML'],
    'sections'   => [
        ['title' => '1. Connecting with PDO',
         'body'  => 'PDO (PHP Data Objects) provides a consistent interface to multiple database types. Always set ERRMODE_EXCEPTION to get useful error messages, and EMULATE_PREPARES to false to use real prepared statements.',
         'code'  => "<?php\n\$dsn = 'mysql:host=localhost;dbname=myapp;charset=utf8mb4';\n\n\$pdo = new PDO(\$dsn, 'root', '', [\n    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n    PDO::ATTR_EMULATE_PREPARES   => false,\n]);\n?>"],
        ['title' => '2. Prepared Statements',
         'body'  => 'Never concatenate user input into SQL strings — that leads to SQL injection. Use ? placeholders or :named placeholders in prepared statements. The driver handles escaping automatically.',
         'code'  => "<?php\n// Fetch one record safely\n\$stmt = \$pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');\n\$stmt->execute([\$email]);\n\$user = \$stmt->fetch();\n\n// Insert a record\n\$ins = \$pdo->prepare('INSERT INTO posts (title, body, user_id) VALUES (?, ?, ?)');\n\$ins->execute([\$title, \$body, \$userId]);\n?>"],
        ['title' => '3. Displaying Records',
         'body'  => 'Fetch all rows with fetchAll() and loop over them in HTML. Always escape output with htmlspecialchars() to prevent XSS.',
         'code'  => "<?php\n\$stmt = \$pdo->query('SELECT id, title FROM posts ORDER BY created_at DESC');\n\$posts = \$stmt->fetchAll();\n?>\n<ul>\n<?php foreach (\$posts as \$post): ?>\n  <li><a href=\"/post/<?= (int)\$post['id'] ?>\">\n    <?= htmlspecialchars(\$post['title'], ENT_QUOTES, 'UTF-8') ?>\n  </a></li>\n<?php endforeach; ?>\n</ul>"],
    ],
    'related' => ['php','crud-applications','mysql-fundamentals'],
],

'crud-applications' => [
    'objectives' => ['Build a full Create-Read-Update-Delete app','Implement server-side validation','Use POST/Redirect/GET pattern','Protect forms with CSRF tokens'],
    'sections'   => [
        ['title' => '1. CRUD Architecture',
         'body'  => 'A CRUD app has four operations: Create (INSERT), Read (SELECT), Update (UPDATE), Delete (DELETE). Structure your files so each operation has a clear entry point and separates data logic from presentation.',
         'code'  => "// Typical file structure for a notes app:\n// index.php      — list all notes (Read)\n// create.php     — form + INSERT handler (Create)\n// edit.php       — form + UPDATE handler (Update)\n// delete.php     — DELETE handler then redirect (Delete)"],
        ['title' => '2. The Post/Redirect/Get Pattern',
         'body'  => 'After a successful POST (insert/update/delete), always redirect with header(Location:). This prevents duplicate submissions when the user refreshes the page.',
         'code'  => "<?php\nif (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n    // validate...\n    // save to DB...\n    header('Location: /notes/index.php?saved=1');\n    exit;\n}\n// Otherwise render the form for GET\n?>"],
        ['title' => '3. CSRF Protection',
         'body'  => 'CSRF tokens prevent malicious sites from submitting forms on behalf of a logged-in user. Generate a random token, store it in the session, embed it in the form, and verify it on POST.',
         'code'  => "<?php\n// Generate token\nif (empty(\$_SESSION['csrf'])) {\n    \$_SESSION['csrf'] = bin2hex(random_bytes(32));\n}\n// In the form:\necho '<input type=\"hidden\" name=\"csrf\" value=\"' . \$_SESSION['csrf'] . '\">';\n// Verify on POST:\nif (!hash_equals(\$_SESSION['csrf'], \$_POST['csrf'] ?? '')) {\n    http_response_code(403); exit('Invalid token.');\n}\n?>"],
    ],
    'related' => ['php-mysql','php','database-design'],
],

// ── Programming Fundamentals ───────────────────────────────────────────────

'intro-programming' => [
    'objectives' => ['Explain what a program is','Describe how computers execute instructions','Identify the main programming paradigms','Write your first pseudocode algorithm'],
    'sections'   => [
        ['title' => '1. What Is a Program?',
         'body'  => 'A program is a sequence of instructions that a computer follows to perform a task. Instructions are written in a programming language, then translated into machine code that the CPU can execute.'],
        ['title' => '2. How Computers Execute Code',
         'body'  => 'The CPU fetches an instruction from memory, decodes it, executes it, and stores the result — the fetch-decode-execute cycle. High-level languages are compiled or interpreted into machine code before the CPU can run them.',
         'code'  => "// Pseudocode for adding two numbers:\nSTART\n  READ number1\n  READ number2\n  SET total = number1 + number2\n  PRINT total\nEND"],
        ['title' => '3. Programming Paradigms',
         'body'  => 'Procedural programming (PHP, C) organises code as step-by-step procedures. Object-oriented programming (Java, PHP) models the world as objects with data and behaviour. Functional programming (JavaScript, Haskell) treats computation as evaluating pure functions.'],
    ],
    'related' => ['variables-types','conditionals','loops'],
],

'variables-types' => [
    'objectives' => ['Declare and use variables','Identify primitive data types','Understand type coercion','Convert between types'],
    'sections'   => [
        ['title' => '1. Variables',
         'body'  => 'A variable is a named container for a value. In PHP variables start with $; in JavaScript use let or const. Variable names should be descriptive (e.g. $userAge rather than $x).',
         'code'  => "// PHP\n\$username  = 'alice';\n\$loginCount = 0;\n\$isActive   = true;\n\n// JavaScript\nconst MAX_RETRIES = 3;\nlet currentPage   = 1;"],
        ['title' => '2. Data Types',
         'body'  => 'Common types: integers (whole numbers), floats (decimals), strings (text), booleans (true/false), null/undefined (absence of value), and arrays/objects (collections). Choosing the right type avoids bugs.',
         'code'  => "// PHP types\n\$int    = 42;\n\$float  = 3.14;\n\$str    = 'hello';\n\$bool   = false;\n\$arr    = [1, 2, 3];\n\$obj    = new stdClass();\n\nvar_dump(\$int);   // int(42)\nvar_dump(\$float); // float(3.14)"],
        ['title' => '3. Type Conversion',
         'body'  => 'Languages may convert types automatically (coercion) or require explicit casts. PHP loosely compares "5" and 5 as equal with ==, but strict === comparison checks both value and type.',
         'code'  => "// PHP casting\n\$str = '42';\n\$num = (int) \$str;   // 42\n\$flt = (float) '3.9'; // 3.9\n\n// Loose vs strict equality\nvar_dump('5' == 5);  // true (coercion)\nvar_dump('5' === 5); // false (different types)"],
    ],
    'related' => ['conditionals','arrays','intro-programming'],
],

'conditionals' => [
    'objectives' => ['Use if/else to make decisions','Write switch statements','Apply comparison and logical operators','Understand truthy and falsy values'],
    'sections'   => [
        ['title' => '1. If / Else',
         'body'  => 'An if statement evaluates a condition. If it is true the first block runs; otherwise the else block runs. Chain multiple conditions with elseif (PHP) / else if (JS).',
         'code'  => "// PHP\n\$grade = 78;\nif (\$grade >= 90) {\n    echo 'A';\n} elseif (\$grade >= 80) {\n    echo 'B';\n} elseif (\$grade >= 70) {\n    echo 'C';\n} else {\n    echo 'F';\n}"],
        ['title' => '2. Comparison and Logical Operators',
         'body'  => 'Comparison operators: == (equal), != (not equal), >, <, >=, <=, === (identical). Logical operators: && (AND), || (OR), ! (NOT). Combine them to express complex conditions.',
         'code'  => "// JS: check age and country\nconst age     = 20;\nconst country = 'PH';\n\nif (age >= 18 && country === 'PH') {\n  console.log('Eligible to vote in the Philippines.');\n}"],
        ['title' => '3. Switch Statements',
         'body'  => 'A switch compares one value against multiple cases. It is cleaner than a long if/elseif chain when checking a single variable against fixed values.',
         'code'  => "// PHP\n\$day = 'Monday';\nswitch (\$day) {\n    case 'Saturday':\n    case 'Sunday':\n        echo 'Weekend!';\n        break;\n    default:\n        echo 'Weekday.';\n}"],
    ],
    'related' => ['variables-types','loops','functions'],
],

'loops' => [
    'objectives' => ['Use for loops to repeat tasks a known number of times','Use while loops for unknown iterations','Use foreach to iterate arrays','Break out of loops and skip iterations'],
    'sections'   => [
        ['title' => '1. The for Loop',
         'body'  => 'Use a for loop when you know in advance how many iterations you need. It has three parts: initialisation, condition check, and increment.',
         'code'  => "// Count 1 to 5\nfor (\$i = 1; \$i <= 5; \$i++) {\n    echo \$i . PHP_EOL;\n}\n// Output: 1 2 3 4 5"],
        ['title' => '2. The while Loop',
         'body'  => 'A while loop continues as long as its condition is true. Use it when you don\'t know the exact number of iterations ahead of time.',
         'code'  => "\$attempts = 0;\n\$maxAttempts = 3;\n\nwhile (\$attempts < \$maxAttempts) {\n    echo 'Attempt ' . (\$attempts + 1) . PHP_EOL;\n    \$attempts++;\n}"],
        ['title' => '3. foreach and Break/Continue',
         'body'  => 'foreach iterates over every element of an array. Use break to exit a loop early and continue to skip the current iteration and move to the next.',
         'code'  => "\$numbers = [1, 2, 3, 4, 5, 6];\n\nforeach (\$numbers as \$n) {\n    if (\$n % 2 === 0) continue; // skip evens\n    if (\$n > 4)       break;    // stop at 5\n    echo \$n . ' ';              // 1 3\n}"],
    ],
    'related' => ['conditionals','arrays','functions'],
],

'functions' => [
    'objectives' => ['Define and call functions','Pass arguments and return values','Understand variable scope','Write pure functions'],
    'sections'   => [
        ['title' => '1. Defining Functions',
         'body'  => 'A function groups related code under a name so you can call it repeatedly. PHP uses the function keyword; type declarations make the contract explicit.',
         'code'  => "<?php\nfunction add(int \$a, int \$b): int {\n    return \$a + \$b;\n}\n\necho add(3, 4); // 7\n?>"],
        ['title' => '2. Parameters and Return Values',
         'body'  => 'Parameters are placeholders in the function definition; arguments are the values you pass when calling. A function without a return statement implicitly returns null in PHP and undefined in JS.',
         'code'  => "function fullName(string \$first, string \$last): string {\n    return trim(\"\$first \$last\");\n}\n\necho fullName('Maria', 'Santos'); // Maria Santos"],
        ['title' => '3. Scope',
         'body'  => 'Variables declared inside a function are local to that function. In PHP, to use an outer variable inside a function you must declare it global or pass it as a parameter. Preferring parameters over globals makes functions easier to test.',
         'code'  => "\$counter = 0;\n\nfunction increment(int \$n): int {\n    return \$n + 1; // pure — no side effects\n}\n\n\$counter = increment(\$counter); // 1"],
    ],
    'related' => ['variables-types','arrays','loops'],
],

'arrays' => [
    'objectives' => ['Create indexed and associative arrays','Add, remove, and search array elements','Sort arrays','Use array functions like map, filter, and reduce'],
    'sections'   => [
        ['title' => '1. Creating Arrays',
         'body'  => 'An indexed array stores values by numeric position. An associative array uses named keys. PHP supports both; JavaScript uses plain objects for key-value pairs and arrays for lists.',
         'code'  => "// Indexed\n\$fruits = ['apple', 'banana', 'mango'];\necho \$fruits[1]; // banana\n\n// Associative\n\$user = ['name' => 'Alice', 'age' => 25, 'active' => true];\necho \$user['name']; // Alice"],
        ['title' => '2. Common Array Functions',
         'body'  => 'PHP has hundreds of built-in array functions. Essential ones: count(), array_push(), array_pop(), array_shift(), in_array(), array_search(), array_merge(), sort(), usort().',
         'code'  => "\$scores = [85, 92, 78, 95, 60];\nsort(\$scores);        // [60, 78, 85, 92, 95]\n\$max   = max(\$scores); // 95\n\$total = array_sum(\$scores); // 410\n\$avg   = \$total / count(\$scores); // 82"],
        ['title' => '3. Array Mapping and Filtering',
         'body'  => 'array_map() applies a callback to every element and returns a new array. array_filter() keeps only elements for which the callback returns true.',
         'code'  => "\$prices   = [10, 25, 5, 40, 15];\n\$doubled  = array_map(fn(\$p) => \$p * 2, \$prices);\n// [20, 50, 10, 80, 30]\n\n\$expensive = array_filter(\$prices, fn(\$p) => \$p > 15);\n// [25, 40]"],
    ],
    'related' => ['loops','functions','variables-types'],
],

'problem-solving' => [
    'objectives' => ['Break a problem into sub-problems','Design algorithms before coding','Trace through code mentally','Recognise common patterns (search, sort, accumulate)'],
    'sections'   => [
        ['title' => '1. Understand the Problem First',
         'body'  => 'Before writing any code, make sure you understand the problem completely. Identify: inputs, expected outputs, edge cases, and constraints. Restate the problem in your own words.',
         'code'  => "// Problem: find the largest number in a list\n// Input:  [3, 7, 2, 9, 4]\n// Output: 9\n// Edge cases: empty list, single element, negatives"],
        ['title' => '2. Design Before You Code',
         'body'  => 'Write pseudocode or draw a flowchart before touching the keyboard. Break the solution into small, verifiable steps. Each step should do exactly one thing.',
         'code'  => "// Pseudocode — find max\nSET max = first element\nFOR each element in list:\n    IF element > max THEN\n        SET max = element\nRETURN max"],
        ['title' => '3. Test and Debug',
         'body'  => 'Run your solution with normal input, then with edge cases (empty list, one element, all equal). Use echo or console.log to trace variable values at key points.',
         'code'  => "function findMax(array \$nums): ?int {\n    if (empty(\$nums)) return null;\n    \$max = \$nums[0];\n    foreach (\$nums as \$n) {\n        if (\$n > \$max) \$max = \$n;\n    }\n    return \$max;\n}\n\nvar_dump(findMax([3,7,2,9,4])); // int(9)"],
    ],
    'related' => ['functions','arrays','conditionals'],
],

// ── Database Management ────────────────────────────────────────────────────

'intro-databases' => [
    'objectives' => ['Define what a database is and why it is used','Distinguish between relational and non-relational databases','Identify when to use a database vs a file','Explain DBMS concepts: tables, rows, columns'],
    'sections'   => [
        ['title' => '1. What Is a Database?',
         'body'  => 'A database is an organised collection of structured data stored electronically. It allows multiple users and applications to store, retrieve, update, and delete data efficiently and consistently.'],
        ['title' => '2. Relational vs Non-Relational',
         'body'  => 'Relational databases (MySQL, PostgreSQL) store data in tables with rows and columns, and use SQL to query them. Non-relational databases (MongoDB, Redis) store data as documents, key-value pairs, or graphs — good for flexible schemas and horizontal scaling.'],
        ['title' => '3. Tables, Rows, and Columns',
         'body'  => 'In a relational database, data lives in tables. Each table has columns (attributes) and rows (records). A primary key uniquely identifies each row.',
         'code'  => "-- users table\n+----+----------+---------------------+\n| id | username | email               |\n+----+----------+---------------------+\n|  1 | alice    | alice@example.com   |\n|  2 | bob      | bob@example.com     |\n+----+----------+---------------------+"],
    ],
    'related' => ['mysql-fundamentals','database-design','sql-queries'],
],

'mysql-fundamentals' => [
    'objectives' => ['Create databases and tables in MySQL','Insert, select, update, and delete records','Use data types correctly','Add indexes for performance'],
    'sections'   => [
        ['title' => '1. Creating Databases and Tables',
         'body'  => 'Use CREATE DATABASE to create a new database and CREATE TABLE to define its structure. Choose appropriate data types: INT for integers, VARCHAR for short text, TEXT for long text, DATETIME for timestamps.',
         'code'  => "CREATE DATABASE blog;\nUSE blog;\n\nCREATE TABLE posts (\n  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  title      VARCHAR(200) NOT NULL,\n  body       TEXT NOT NULL,\n  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"],
        ['title' => '2. Basic CRUD Queries',
         'body'  => 'INSERT adds a row; SELECT retrieves rows; UPDATE changes existing rows; DELETE removes rows. Always use a WHERE clause with UPDATE and DELETE to avoid affecting all rows.',
         'code'  => "-- Create\nINSERT INTO posts (title, body) VALUES ('My First Post', 'Hello world!');\n\n-- Read\nSELECT id, title FROM posts ORDER BY created_at DESC;\n\n-- Update\nUPDATE posts SET title = 'Updated Title' WHERE id = 1;\n\n-- Delete\nDELETE FROM posts WHERE id = 1;"],
        ['title' => '3. Indexes',
         'body'  => 'Indexes speed up SELECT queries on large tables by allowing MySQL to find rows without scanning the whole table. Add an index on columns you frequently use in WHERE, JOIN, or ORDER BY clauses.',
         'code'  => "-- Add index on email for fast login lookups\nCREATE INDEX idx_email ON users (email);\n\n-- Or at table creation time\nCREATE TABLE users (\n  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  email VARCHAR(191) NOT NULL UNIQUE,\n  ...\n);"],
    ],
    'related' => ['intro-databases','sql-queries','database-design'],
],

'database-design' => [
    'objectives' => ['Draw entity-relationship (ER) diagrams','Choose appropriate primary and foreign keys','Normalise tables to reduce redundancy','Identify one-to-many relationships'],
    'sections'   => [
        ['title' => '1. Entity-Relationship Modelling',
         'body'  => 'An ER diagram visualises entities (tables), their attributes (columns), and the relationships between them. Identify your entities first, then map relationships: a user has many posts; a post belongs to one user.'],
        ['title' => '2. Primary and Foreign Keys',
         'body'  => 'A primary key uniquely identifies each row. A foreign key stores the primary key of a related row in another table, enforcing referential integrity.',
         'code'  => "CREATE TABLE posts (\n  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  user_id INT UNSIGNED NOT NULL,\n  title   VARCHAR(200) NOT NULL,\n  CONSTRAINT fk_posts_user FOREIGN KEY (user_id)\n    REFERENCES users(id) ON DELETE CASCADE\n);"],
        ['title' => '3. Normalisation',
         'body'  => 'Normalisation removes data redundancy by splitting data into related tables. First Normal Form (1NF): each cell holds one value. Second (2NF): no partial dependencies. Third (3NF): no transitive dependencies. Most applications aim for 3NF.'],
    ],
    'related' => ['mysql-fundamentals','db-relationships','sql-queries'],
],

'sql-queries' => [
    'objectives' => ['Write SELECT queries with WHERE, ORDER BY, LIMIT','Aggregate data with GROUP BY and HAVING','Join tables with INNER JOIN and LEFT JOIN','Write subqueries'],
    'sections'   => [
        ['title' => '1. Filtering and Sorting',
         'body'  => 'WHERE filters rows; ORDER BY sorts them; LIMIT restricts how many rows are returned. Combine them for paginated results.',
         'code'  => "SELECT id, title, created_at\nFROM posts\nWHERE user_id = 5\n  AND created_at >= '2026-01-01'\nORDER BY created_at DESC\nLIMIT 10 OFFSET 0;"],
        ['title' => '2. Aggregates and GROUP BY',
         'body'  => 'COUNT(), SUM(), AVG(), MIN(), MAX() perform calculations on groups of rows. GROUP BY groups rows that share a value; HAVING filters the resulting groups.',
         'code'  => "-- Count bookings per student\nSELECT student_id,\n       COUNT(*) AS total,\n       COUNT(CASE WHEN status='completed' THEN 1 END) AS done\nFROM bookings\nGROUP BY student_id\nHAVING total > 2;"],
        ['title' => '3. JOINs',
         'body'  => 'INNER JOIN returns rows that match in both tables. LEFT JOIN returns all rows from the left table plus matching rows from the right (NULLs where there is no match).',
         'code'  => "-- Get each booking with student and tutor names\nSELECT b.id, s.full_name AS student, t.full_name AS tutor,\n       b.session_date, b.status\nFROM bookings b\nINNER JOIN users s ON s.id = b.student_id\nINNER JOIN tutors tr ON tr.id = b.tutor_id\nINNER JOIN users t  ON t.id  = tr.user_id\nORDER BY b.session_date DESC;"],
    ],
    'related' => ['mysql-fundamentals','database-design','db-relationships'],
],

'db-relationships' => [
    'objectives' => ['Implement one-to-many relationships','Model many-to-many using a junction table','Understand cascade delete and update','Query across related tables'],
    'sections'   => [
        ['title' => '1. One-to-Many',
         'body'  => 'The most common relationship: one user can have many posts; one post belongs to one user. The "many" side stores the foreign key. Use an index on the foreign key column for fast lookups.',
         'code'  => "-- users (one) ←→ posts (many)\nCREATE TABLE posts (\n  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  user_id INT UNSIGNED NOT NULL,\n  INDEX idx_user (user_id),\n  FOREIGN KEY (user_id) REFERENCES users(id)\n);"],
        ['title' => '2. Many-to-Many via Junction Table',
         'body'  => 'Students can study many subjects; subjects can have many students. Model this with a junction table (e.g. student_subjects) that holds a foreign key to each side.',
         'code'  => "CREATE TABLE tutor_subjects (\n  tutor_id   INT UNSIGNED NOT NULL,\n  subject_id INT UNSIGNED NOT NULL,\n  PRIMARY KEY (tutor_id, subject_id),\n  FOREIGN KEY (tutor_id)   REFERENCES tutors(id)   ON DELETE CASCADE,\n  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE\n);"],
        ['title' => '3. Cascade Actions',
         'body'  => 'ON DELETE CASCADE automatically deletes child rows when the parent is deleted. ON DELETE SET NULL instead sets the foreign key to NULL. Choose based on your data requirements.',
         'code'  => "-- ON DELETE CASCADE: delete all bookings if user is deleted\nFOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE\n\n-- ON DELETE SET NULL: keep the payment record but clear the reference\nFOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE SET NULL"],
    ],
    'related' => ['database-design','sql-queries','mysql-fundamentals'],
],

// ── Computer and IT Fundamentals ──────────────────────────────────────────

'computer-fundamentals' => [
    'objectives' => ['Identify main hardware components and their roles','Understand the difference between hardware and software','Explain how a CPU executes instructions','Describe common storage types'],
    'sections'   => [
        ['title' => '1. Core Hardware Components',
         'body'  => 'A computer is made of CPU (processor), RAM (temporary memory), storage (HDD/SSD), motherboard, power supply, and I/O devices (keyboard, screen). The CPU performs calculations; RAM holds data currently in use; storage holds data permanently.'],
        ['title' => '2. The CPU and Memory',
         'body'  => 'The CPU executes instructions using the fetch-decode-execute cycle at billions of cycles per second (measured in GHz). RAM is fast but volatile — data is lost on power-off. The CPU reads program data from RAM into its own tiny cache for even faster access.'],
        ['title' => '3. Storage: HDD vs SSD',
         'body'  => 'Hard disk drives (HDD) store data on spinning magnetic platters — cheaper but slower. Solid-state drives (SSD) store data on flash chips — much faster and more durable but more expensive per gigabyte. Servers increasingly use SSDs for databases.'],
    ],
    'related' => ['operating-systems','computer-networks','intro-programming'],
],

'operating-systems' => [
    'objectives' => ['Explain the role of an operating system','Understand processes, threads, and scheduling','Manage files and directories from the command line','Compare Windows, Linux, and macOS for web development'],
    'sections'   => [
        ['title' => '1. What an OS Does',
         'body'  => 'The operating system (OS) manages hardware resources on behalf of applications. It handles CPU scheduling (which program runs when), memory allocation, file I/O, and device drivers so software does not need to talk to hardware directly.'],
        ['title' => '2. Processes and Files',
         'body'  => 'A process is a running program with its own memory space. Modern OSes run many processes concurrently via time-slicing. The file system organises data into files and directories with read/write/execute permissions.',
         'code'  => "# Linux/macOS terminal basics\nls -la            # list files with details\ncd /var/www       # change directory\nmkdir my-project  # create directory\nchmod 755 file.sh # set permissions\npwd               # print working directory"],
        ['title' => '3. Choosing an OS for Web Development',
         'body'  => 'Linux (Ubuntu, Debian) powers most web servers — it is free, stable, and has excellent PHP/MySQL support. macOS is Unix-based and popular with developers. Windows works well with Laragon for local PHP development.'],
    ],
    'related' => ['computer-fundamentals','computer-networks','cybersecurity'],
],

'computer-networks' => [
    'objectives' => ['Explain IP addresses, DNS, and routing','Understand HTTP and HTTPS request-response cycles','Identify common network protocols','Describe how web browsers load a page'],
    'sections'   => [
        ['title' => '1. IP Addresses and DNS',
         'body'  => 'Every device on the internet has an IP address (e.g. 192.168.1.1 for IPv4). DNS (Domain Name System) translates human-readable domain names like google.com into IP addresses — it is the internet\'s phone book.',
         'code'  => "// When you type https://aralpal.com:\n// 1. Browser checks local DNS cache\n// 2. Asks DNS resolver for IP of aralpal.com\n// 3. DNS returns e.g. 123.45.67.89\n// 4. Browser opens TCP connection to that IP on port 443\n// 5. TLS handshake (HTTPS security)\n// 6. Browser sends HTTP GET request\n// 7. Server sends back HTML response"],
        ['title' => '2. HTTP and HTTPS',
         'body'  => 'HTTP is the protocol web browsers use to communicate with servers. It is stateless — each request is independent. HTTPS wraps HTTP in TLS encryption so data cannot be read by third parties in transit. All modern websites should use HTTPS.',
         'code'  => "// HTTP GET request (simplified)\nGET /index.php HTTP/1.1\nHost: aralpal.com\nAccept: text/html\n\n// HTTP response\nHTTP/1.1 200 OK\nContent-Type: text/html; charset=UTF-8\n\n<!DOCTYPE html>..."],
        ['title' => '3. Common Protocols',
         'body'  => 'TCP ensures reliable, ordered delivery. UDP is faster but unreliable — used for video streaming and DNS queries. SMTP/IMAP handle email. SSH provides secure remote server access. FTP transfers files (use SFTP for security).'],
    ],
    'related' => ['operating-systems','cybersecurity','computer-fundamentals'],
],

'cybersecurity' => [
    'objectives' => ['Identify common web and network threats','Apply secure password practices','Understand HTTPS and TLS','Recognise phishing and social engineering'],
    'sections'   => [
        ['title' => '1. Common Threats',
         'body'  => 'SQL injection: attackers insert SQL code through forms to manipulate the database. Cross-site scripting (XSS): injecting JavaScript into pages to steal session tokens. Phishing: fake websites or emails tricking users into entering credentials.',
         'code'  => "// SQL injection — NEVER do this:\n\$sql = \"SELECT * FROM users WHERE email = '\" . \$_POST['email'] . \"'\";\n// Attacker enters: ' OR '1'='1 — returns all rows!\n\n// Safe: use prepared statements\n\$stmt = \$pdo->prepare('SELECT * FROM users WHERE email = ?');\n\$stmt->execute([\$_POST['email']]);"],
        ['title' => '2. Passwords and Authentication',
         'body'  => 'Never store plain-text passwords. Use a strong hashing algorithm like bcrypt (PHP\'s password_hash()). Require strong passwords, rate-limit login attempts, and consider two-factor authentication for sensitive accounts.',
         'code'  => "// PHP — hash and verify passwords\n\$hash = password_hash('MyP@ss!', PASSWORD_BCRYPT);\n// \$hash is safe to store in DB\n\n// On login:\nif (password_verify(\$submitted, \$hash)) {\n    // authenticated\n}"],
        ['title' => '3. HTTPS and Secure Headers',
         'body'  => 'Enable HTTPS on all pages. Set HTTP security headers: Content-Security-Policy restricts where scripts can load from; Strict-Transport-Security forces HTTPS; X-Frame-Options prevents clickjacking. Use httponly and samesite on session cookies.',
         'code'  => "// PHP security headers\nheader('X-Frame-Options: DENY');\nheader('X-Content-Type-Options: nosniff');\nheader('Referrer-Policy: strict-origin-when-cross-origin');"],
    ],
    'related' => ['computer-networks','operating-systems','php'],
],

]; // end $CONTENT

// Get this lesson's content (or a stub for lessons not yet detailed)
$content = $CONTENT[$slug] ?? [
    'objectives' => ['Learn the core concepts of ' . $lesson['title']],
    'sections'   => [
        ['title' => 'Introduction',
         'body'  => $lesson['description']],
    ],
    'related' => [],
];

// Resolve related lessons from DB
$relatedLessons = [];
if (!empty($content['related'])) {
    $phs = implode(',', array_fill(0, count($content['related']), '?'));
    $rel_stmt = $db->prepare("SELECT slug, title, category, difficulty FROM lessons WHERE slug IN ($phs) AND is_active = 1");
    $rel_stmt->execute($content['related']);
    $relatedLessons = $rel_stmt->fetchAll();
}

$page_title = htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') . ' – AralPal';
$csrf = generate_csrf();
require_once __DIR__ . '/../../includes/app-header.php';
?>
<style>
.lesson-wrap { max-width: 820px; }
.lesson-breadcrumb { font-size: .82rem; color: var(--clr-muted); margin-bottom: 1.25rem; }
.lesson-breadcrumb a { color: var(--clr-primary); text-decoration: none; }
.lesson-breadcrumb a:hover { text-decoration: underline; }
.lesson-header { margin-bottom: 1.5rem; }
.lesson-header h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 .5rem; }
.lesson-meta-row { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
.l-badge { display:inline-block; padding:.18em .6em; border-radius:99px; font-size:.72rem; font-weight:600; }
.l-badge--beginner     { background:#dcfce7; color:#166534; }
.l-badge--intermediate { background:#fef9c3; color:#92400e; }
.l-badge--advanced     { background:#fee2e2; color:#991b1b; }
.l-badge--cat { background:var(--clr-bg); color:var(--clr-text); border:1px solid var(--clr-border); }
.l-dur { font-size:.82rem; color:var(--clr-muted); }

.lesson-objectives { background:var(--clr-surface); border:1px solid var(--clr-border); border-left:4px solid var(--clr-primary); border-radius:var(--radius-md); padding:1rem 1.25rem; margin-bottom:1.5rem; }
.lesson-objectives h3 { font-size:.88rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin:0 0 .6rem; color:var(--clr-primary); }
.lesson-objectives ul { margin:0; padding-left:1.2rem; }
.lesson-objectives li { font-size:.9rem; margin-bottom:.3rem; }

.lesson-section { margin-bottom:1.75rem; }
.lesson-section h2 { font-size:1.1rem; font-weight:700; margin:0 0 .75rem; padding-bottom:.5rem; border-bottom:1px solid var(--clr-border); }
.lesson-section p  { font-size:.93rem; line-height:1.7; margin:0 0 .75rem; }
.lesson-code-block { background:#1e1e2e; color:#cdd6f4; border-radius:var(--radius-md); padding:1rem 1.25rem; overflow-x:auto; font-size:.82rem; line-height:1.6; margin:.75rem 0; }
.lesson-code-block pre { margin:0; white-space:pre; }

.lesson-actions { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin-top:2rem; padding-top:1.25rem; border-top:1px solid var(--clr-border); }
.btn-complete { background:var(--clr-primary); color:#fff; border:none; border-radius:var(--radius-md); padding:.6rem 1.3rem; font-weight:600; font-size:.92rem; cursor:pointer; transition:background var(--transition); }
.btn-complete:hover { opacity:.9; }
.btn-complete:disabled { opacity:.6; cursor:not-allowed; }
.btn-quiz { background:var(--clr-surface); color:var(--clr-primary); border:1px solid var(--clr-primary); border-radius:var(--radius-md); padding:.6rem 1.3rem; font-weight:600; font-size:.92rem; text-decoration:none; }
.btn-back { color:var(--clr-muted); font-size:.88rem; text-decoration:none; }
.btn-back:hover { color:var(--clr-text); }
.complete-msg { color:#16a34a; font-weight:600; font-size:.9rem; display:none; }

.related-section { margin-top:2.5rem; padding-top:1.25rem; border-top:1px solid var(--clr-border); }
.related-section h3 { font-size:.95rem; font-weight:700; margin:0 0 .75rem; }
.related-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.75rem; }
.related-card { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:.85rem 1rem; text-decoration:none; color:inherit; transition:box-shadow var(--transition); display:flex; flex-direction:column; gap:.25rem; }
.related-card:hover { box-shadow:var(--shadow-sm); }
.related-card__cat { font-size:.7rem; color:var(--clr-muted); font-weight:600; text-transform:uppercase; }
.related-card__title { font-size:.9rem; font-weight:600; }
</style>

<div class="lesson-wrap">

    <div class="lesson-breadcrumb">
        <a href="/AralPal/pages/learn/index.php">Learning Center</a>
        &rsaquo;
        <a href="/AralPal/pages/learn/index.php?cat=<?= urlencode($lesson['category']) ?>">
            <?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?>
        </a>
        &rsaquo;
        <?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?>
    </div>

    <header class="lesson-header">
        <h1><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="lesson-meta-row">
            <span class="l-badge l-badge--cat"><?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php
            $diffClass = match($lesson['difficulty']) {
                'Intermediate' => 'l-badge--intermediate',
                'Advanced'     => 'l-badge--advanced',
                default        => 'l-badge--beginner',
            };
            ?>
            <span class="l-badge <?= $diffClass ?>"><?= htmlspecialchars($lesson['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="l-dur">⏱ <?= (int)$lesson['duration_min'] ?> min</span>
            <?php if ($progress === 'completed'): ?>
                <span class="l-badge" style="background:#dcfce7;color:#166534">✓ Completed</span>
            <?php elseif ($progress === 'in_progress'): ?>
                <span class="l-badge" style="background:#dbeafe;color:#1e40af">▶ In Progress</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Learning Objectives -->
    <?php if (!empty($content['objectives'])): ?>
    <div class="lesson-objectives">
        <h3>What you'll learn</h3>
        <ul>
            <?php foreach ($content['objectives'] as $obj): ?>
                <li><?= htmlspecialchars($obj, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Lesson Sections -->
    <?php foreach ($content['sections'] as $sec): ?>
    <div class="lesson-section">
        <h2><?= htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= nl2br(htmlspecialchars($sec['body'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php if (!empty($sec['code'])): ?>
        <div class="lesson-code-block">
            <pre><?= htmlspecialchars($sec['code'], ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Actions -->
    <?php if ($isStudent): ?>
    <div class="lesson-actions">
        <?php if ($progress !== 'completed'): ?>
            <button class="btn-complete" id="markCompleteBtn">
                ✓ Mark as Complete
            </button>
            <span class="complete-msg" id="completeMsg">🎉 Lesson completed!</span>
        <?php else: ?>
            <span class="l-badge" style="background:#dcfce7;color:#166534;padding:.45em 1em;font-size:.88rem">✓ Already Completed</span>
        <?php endif; ?>
        <?php if ($hasQuiz): ?>
            <a class="btn-quiz" href="/AralPal/pages/quizzes.php?lesson=<?= urlencode($slug) ?>">Take Quiz →</a>
        <?php endif; ?>
        <a class="btn-back" href="/AralPal/pages/learn/index.php">← Back to Learning Center</a>
    </div>
    <?php endif; ?>

    <!-- Related Lessons -->
    <?php if (!empty($relatedLessons)): ?>
    <div class="related-section">
        <h3>Related Lessons</h3>
        <div class="related-grid">
            <?php foreach ($relatedLessons as $rel): ?>
                <a class="related-card" href="/AralPal/pages/learn/lesson.php?slug=<?= urlencode($rel['slug']) ?>">
                    <span class="related-card__cat"><?= htmlspecialchars($rel['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="related-card__title"><?= htmlspecialchars($rel['title'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if ($isStudent && $progress !== 'completed'): ?>
<script>
(function () {
    var btn  = document.getElementById('markCompleteBtn');
    var msg  = document.getElementById('completeMsg');
    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Saving…';

        fetch('/AralPal/pages/mark-progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>'
            },
            body: JSON.stringify({ lesson_slug: '<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>', status: 'completed' })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                btn.style.display = 'none';
                if (msg) msg.style.display = 'inline';
            } else {
                btn.disabled = false;
                btn.textContent = '✓ Mark as Complete';
                alert(d.error || 'Could not save progress.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = '✓ Mark as Complete';
        });
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>
