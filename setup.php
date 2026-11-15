<?php
/**
 * AralPal – One-time Database Setup Script
 * Run once at: http://localhost/AralPal/setup.php
 * DELETE this file immediately after setup is complete.
 */
declare(strict_types=1);

// Simple token guard so only you can run this
define('SETUP_TOKEN', 'aralpal_setup_2026');
if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    http_response_code(403);
    die('<h2>403 – Forbidden</h2><p>Append <code>?token=aralpal_setup_2026</code> to the URL.</p>');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$log   = [];
$error = null;

try {
    $db = get_db();

    // ── 1. Main schema ──────────────────────────────────────────────────
    $statements = [
        // Roles
        "CREATE TABLE IF NOT EXISTS `roles` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `role_name` VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT IGNORE INTO `roles` (`role_name`) VALUES ('Admin'), ('Tutor'), ('Student')",

        // Users
        "CREATE TABLE IF NOT EXISTS `users` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `role_id` INT UNSIGNED NOT NULL,
          `full_name` VARCHAR(150) NOT NULL,
          `email` VARCHAR(191) NOT NULL UNIQUE,
          `password_hash` VARCHAR(255) NOT NULL,
          `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
          `avatar` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Tutors
        "CREATE TABLE IF NOT EXISTS `tutors` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL UNIQUE,
          `bio` TEXT,
          `hourly_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `rating_average` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
          `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
          CONSTRAINT `fk_tutors_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Subjects
        "CREATE TABLE IF NOT EXISTS `subjects` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `subject_name` VARCHAR(100) NOT NULL UNIQUE,
          `description` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT IGNORE INTO `subjects` (`subject_name`, `description`) VALUES
          ('Mathematics', 'Algebra, Calculus, Statistics'),
          ('Science', 'Physics, Chemistry, Biology'),
          ('English', 'Reading, Writing, Grammar'),
          ('Filipino', 'Wikang Filipino, Panitikan'),
          ('Computer Science', 'Programming, Algorithms, Data Structures')",

        // Tutor subjects pivot
        "CREATE TABLE IF NOT EXISTS `tutor_subjects` (
          `tutor_id` INT UNSIGNED NOT NULL,
          `subject_id` INT UNSIGNED NOT NULL,
          PRIMARY KEY (`tutor_id`, `subject_id`),
          CONSTRAINT `fk_ts_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_ts_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Availability
        "CREATE TABLE IF NOT EXISTS `availability` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `tutor_id` INT UNSIGNED NOT NULL,
          `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
          `start_time` TIME NOT NULL,
          `end_time` TIME NOT NULL,
          CONSTRAINT `fk_avail_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Bookings
        "CREATE TABLE IF NOT EXISTS `bookings` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `student_id` INT UNSIGNED NOT NULL,
          `tutor_id` INT UNSIGNED NOT NULL,
          `subject_id` INT UNSIGNED NOT NULL,
          `session_date` DATE NOT NULL,
          `start_time` TIME NOT NULL,
          `end_time` TIME NOT NULL,
          `status` ENUM('pending','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_book_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`),
          CONSTRAINT `fk_book_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`),
          CONSTRAINT `fk_book_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Subscription plans
        "CREATE TABLE IF NOT EXISTS `subscription_plans` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `plan_name` VARCHAR(100) NOT NULL,
          `user_type` ENUM('Student','Tutor') NOT NULL,
          `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `booking_limit` INT NOT NULL DEFAULT 0,
          `features` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT IGNORE INTO `subscription_plans` (`plan_name`, `user_type`, `price`, `booking_limit`, `features`) VALUES
          ('Free Student',    'Student', 0.00,   3, '[\"3 bookings per month\",\"Browse tutors\",\"Basic search\"]'),
          ('Basic Student',   'Student', 199.00, 10,'[\"10 bookings per month\",\"Subject filters\",\"Email notifications\"]'),
          ('Premium Student', 'Student', 499.00, 0, '[\"Unlimited bookings\",\"Priority support\",\"Advanced filters\",\"Compare tutors\"]'),
          ('Tutor Starter',   'Tutor',   0.00,   5, '[\"Up to 5 active bookings\",\"Basic profile\"]'),
          ('Tutor Pro',       'Tutor',   299.00, 0, '[\"Unlimited bookings\",\"Featured listing\",\"Analytics\",\"Priority support\"]')",

        // Subscriptions
        "CREATE TABLE IF NOT EXISTS `subscriptions` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL,
          `plan_id` INT UNSIGNED NOT NULL,
          `start_date` DATE NOT NULL,
          `end_date` DATE NOT NULL,
          `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Payments
        "CREATE TABLE IF NOT EXISTS `payments` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL,
          `plan_id` INT UNSIGNED DEFAULT NULL,
          `subscription_id` INT UNSIGNED DEFAULT NULL,
          `booking_id` INT UNSIGNED DEFAULT NULL,
          `amount` DECIMAL(10,2) NOT NULL,
          `method` VARCHAR(50) NOT NULL DEFAULT 'paymongo',
          `reference_no` VARCHAR(191) DEFAULT NULL,
          `status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `paid_at` DATETIME DEFAULT NULL,
          UNIQUE KEY `ux_reference_no` (`reference_no`),
          CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Reviews
        "CREATE TABLE IF NOT EXISTS `reviews` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `booking_id` INT UNSIGNED NOT NULL UNIQUE,
          `student_id` INT UNSIGNED NOT NULL,
          `tutor_id` INT UNSIGNED NOT NULL,
          `rating` TINYINT UNSIGNED NOT NULL,
          `comment` TEXT,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_rev_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_rev_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`),
          CONSTRAINT `fk_rev_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Password resets
        "CREATE TABLE IF NOT EXISTS `password_resets` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `email` VARCHAR(191) NOT NULL,
          `token` VARCHAR(191) NOT NULL UNIQUE,
          `expires_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Favorites
        "CREATE TABLE IF NOT EXISTS `favorites` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL,
          `tutor_id` INT UNSIGNED NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `ux_fav` (`user_id`, `tutor_id`),
          CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_fav_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Notifications
        "CREATE TABLE IF NOT EXISTS `notifications` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL,
          `message` TEXT NOT NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ── 2. CMS schema ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `cms_admins` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `full_name` VARCHAR(120) NOT NULL,
          `email` VARCHAR(190) NOT NULL UNIQUE,
          `password_hash` VARCHAR(255) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `cms_content` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `section_key` VARCHAR(100) NOT NULL UNIQUE,
          `title` VARCHAR(190) NOT NULL,
          `body` TEXT NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `cms_announcements` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `title` VARCHAR(190) NOT NULL,
          `body` TEXT NOT NULL,
          `is_published` TINYINT(1) NOT NULL DEFAULT 0,
          `created_by` INT UNSIGNED NULL,
          `published_at` DATETIME NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          CONSTRAINT `fk_cms_ann_admin` FOREIGN KEY (`created_by`) REFERENCES `cms_admins`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($statements as $sql) {
        $db->exec($sql);
        $log[] = '✅ ' . trim(substr($sql, 0, 60)) . '…';
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Show existing table list
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $log[] = '';
    $log[] = '📋 Tables in database: ' . implode(', ', $tables);

    // Count users
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $log[] = '👥 Users: ' . $userCount;

} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AralPal Setup</title>
<style>
    body { font-family: system-ui, sans-serif; background: #f5f7fb; color: #1a1d2e; margin: 0; padding: 40px 20px; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px; max-width: 720px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    h1 { font-size: 1.6rem; margin: 0 0 8px; }
    .sub { color: #6b7280; margin: 0 0 24px; }
    pre { background: #f0f4ff; border: 1px solid #c7d5f8; border-radius: 10px; padding: 18px; font-size: .82rem; line-height: 1.8; overflow-x: auto; white-space: pre-wrap; }
    .error { background: #fff0ee; border: 1px solid #fca5a5; border-radius: 10px; padding: 16px; color: #b91c1c; font-size: .9rem; margin-bottom: 20px; }
    .success { background: #e7fef0; border: 1px solid #86efac; border-radius: 10px; padding: 16px; color: #15803d; margin-bottom: 20px; }
    .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
    a.btn { display: inline-block; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: .9rem; text-decoration: none; }
    .btn-primary { background: #0b3b80; color: #fff; }
    .btn-danger  { background: #ef4444; color: #fff; }
    .warning { background: #fefce8; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 16px; color: #92400e; font-size: .85rem; margin-top: 20px; }
</style>
</head>
<body>
<div class="card">
    <h1>🛠 AralPal Database Setup</h1>
    <p class="sub">This script creates all required tables and seeds initial data.</p>

    <?php if ($error): ?>
        <div class="error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php else: ?>
        <div class="success"><strong>✅ Setup complete!</strong> All tables created successfully.</div>
        <pre><?= htmlspecialchars(implode("\n", $log)) ?></pre>
    <?php endif; ?>

    <div class="actions">
        <a class="btn btn-primary" href="/AralPal/admin/setup-admin.php">→ Create CMS Admin Account</a>
        <a class="btn btn-primary" href="/AralPal/auth/register.php">→ Register a User</a>
        <a class="btn btn-primary" href="/AralPal/index.php">→ Go to Homepage</a>
    </div>

    <div class="warning">
        ⚠️ <strong>Security reminder:</strong> Delete <code>setup.php</code> from your server once setup is complete.
        It should never be accessible in production.
    </div>
</div>
</body>
</html>
