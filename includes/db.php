<?php
/**
 * AralPal – PDO Singleton
 */

require_once __DIR__ . '/config.php';

/**
 * Returns the shared PDO connection, creating it on first call.
 *
 * @throws PDOException on connection failure
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * Ensures application tables that are created after the initial schema
 * deployment exist before any page tries to use them.
 * Safe to call on every request — IF NOT EXISTS is a no-op once created.
 */
function ensure_tables(): void
{
    $db = get_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS `lesson_progress` (
          `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id`      INT UNSIGNED NOT NULL,
          `lesson_slug`  VARCHAR(100) NOT NULL,
          `status`       ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
          `visited_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `completed_at` DATETIME DEFAULT NULL,
          UNIQUE KEY `ux_user_lesson` (`user_id`, `lesson_slug`),
          CONSTRAINT `fk_lp_user` FOREIGN KEY (`user_id`)
            REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Lessons catalogue table — drives the Learning Center.
    // Seeded with INSERT IGNORE so existing slugs (php, html-css, javascript)
    // keep their existing lesson_progress and quiz_attempts records intact.
    $db->exec("
        CREATE TABLE IF NOT EXISTS `lessons` (
          `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `slug`         VARCHAR(100) NOT NULL UNIQUE,
          `category`     VARCHAR(100) NOT NULL,
          `title`        VARCHAR(200) NOT NULL,
          `description`  TEXT NOT NULL,
          `difficulty`   ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
          `duration_min` INT UNSIGNED NOT NULL DEFAULT 15,
          `sort_order`   INT NOT NULL DEFAULT 0,
          `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
          `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        INSERT IGNORE INTO `lessons`
            (`slug`,`category`,`title`,`description`,`difficulty`,`duration_min`,`sort_order`)
        VALUES
        -- Web Development
        ('html-css',           'Web Development', 'HTML & CSS Basics',           'Learn how HTML structures a page and CSS controls its appearance.',                        'Beginner',     20, 10),
        ('html-fundamentals',  'Web Development', 'HTML Fundamentals',           'Master semantic HTML elements, attributes, forms, and document structure.',               'Beginner',     20, 11),
        ('css-fundamentals',   'Web Development', 'CSS Fundamentals',            'Understand selectors, the box model, flexbox, and core styling techniques.',              'Beginner',     25, 12),
        ('responsive-design',  'Web Development', 'Responsive Web Design',       'Build layouts that work on phones, tablets, and desktops using media queries.',           'Intermediate', 30, 13),
        ('javascript',         'Web Development', 'JavaScript Fundamentals',     'Variables, functions, events, and how JavaScript makes web pages interactive.',           'Beginner',     25, 14),
        ('dom-manipulation',   'Web Development', 'DOM Manipulation',            'Select, modify, create, and remove HTML elements dynamically with JavaScript.',           'Intermediate', 30, 15),
        ('php',                'Web Development', 'PHP Fundamentals',            'Server-side scripting with PHP: syntax, variables, control flow, and functions.',         'Beginner',     15, 16),
        ('php-mysql',          'Web Development', 'PHP and MySQL',               'Connect PHP to MySQL, execute queries safely, and display dynamic data.',                 'Intermediate', 35, 17),
        ('crud-applications',  'Web Development', 'Building CRUD Applications',  'Create a complete Create-Read-Update-Delete app using PHP, MySQL, and HTML forms.',      'Advanced',     45, 18),
        -- Programming Fundamentals
        ('intro-programming',  'Programming Fundamentals', 'Introduction to Programming', 'What programming is, how computers follow instructions, and core concepts.',   'Beginner',     20, 20),
        ('variables-types',    'Programming Fundamentals', 'Variables and Data Types',    'Understand integers, floats, strings, booleans, and how data is stored.',       'Beginner',     20, 21),
        ('conditionals',       'Programming Fundamentals', 'Conditional Statements',      'Make decisions in code using if, else, switch, and comparison operators.',      'Beginner',     20, 22),
        ('loops',              'Programming Fundamentals', 'Loops',                       'Repeat actions efficiently with for, while, and foreach loops.',                 'Beginner',     20, 23),
        ('functions',          'Programming Fundamentals', 'Functions',                   'Write reusable blocks of code, pass arguments, and return values.',              'Beginner',     25, 24),
        ('arrays',             'Programming Fundamentals', 'Arrays',                      'Store and manipulate lists of data, sort, filter, and iterate collections.',    'Beginner',     25, 25),
        ('problem-solving',    'Programming Fundamentals', 'Problem Solving',             'Break down problems, design algorithms, and think like a programmer.',           'Intermediate', 30, 26),
        -- Database Management
        ('intro-databases',    'Database Management', 'Introduction to Databases', 'What databases are, why they matter, and types: relational vs non-relational.',       'Beginner',     20, 30),
        ('mysql-fundamentals', 'Database Management', 'MySQL Fundamentals',        'Install MySQL, create databases and tables, and run basic CRUD queries.',              'Beginner',     25, 31),
        ('database-design',    'Database Management', 'Database Design',            'Model data with ER diagrams, choose primary keys, and normalise tables.',             'Intermediate', 30, 32),
        ('sql-queries',        'Database Management', 'SQL Queries',               'Write SELECT, INSERT, UPDATE, DELETE, JOIN, GROUP BY, and subqueries.',                'Intermediate', 35, 33),
        ('db-relationships',   'Database Management', 'Database Relationships',    'One-to-many, many-to-many, foreign keys, and referential integrity.',                  'Intermediate', 30, 34),
        -- Computer and IT Fundamentals
        ('computer-fundamentals','Computer and IT Fundamentals','Computer Fundamentals',  'Hardware, software, the CPU, memory, storage, and how a computer boots.',       'Beginner',     20, 40),
        ('operating-systems',  'Computer and IT Fundamentals', 'Operating Systems',       'What an OS does, processes, file systems, and comparing Windows/Linux.',        'Beginner',     25, 41),
        ('computer-networks',  'Computer and IT Fundamentals', 'Computer Networks',       'IP addresses, DNS, HTTP, TCP/IP, and how the internet works.',                  'Beginner',     25, 42),
        ('cybersecurity',      'Computer and IT Fundamentals', 'Cybersecurity Basics',    'Common threats, passwords, encryption, HTTPS, and safe browsing practices.',   'Beginner',     20, 43)
    ");

    // Add payments.reference_no unique index if missing
    try {
        $idx_exists = (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'payments'
               AND INDEX_NAME   = 'ux_reference_no'"
        )->fetchColumn();
        if ($idx_exists === 0) {
            $db->exec("ALTER TABLE `payments` ADD UNIQUE KEY `ux_reference_no` (`reference_no`)");
        }
    } catch (PDOException $e) {
        error_log('AralPal: Could not add ux_reference_no index: ' . $e->getMessage());
    }

    // Drop the broken ux_tutor_slot unique index if it was previously applied
    try {
        $slot_idx = (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'bookings'
               AND INDEX_NAME   = 'ux_tutor_slot'"
        )->fetchColumn();
        if ($slot_idx > 0) {
            $db->exec("ALTER TABLE `bookings` DROP INDEX `ux_tutor_slot`");
        }
    } catch (PDOException $e) {
        error_log('AralPal: Could not drop ux_tutor_slot index: ' . $e->getMessage());
    }
}

// Run on every include so the table exists before any page queries it.
ensure_tables();
