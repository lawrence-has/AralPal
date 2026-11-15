<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Must be logged in
require_login();

// Verify CSRF from header
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($headerToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
$slug   = trim((string) ($body['lesson_slug'] ?? ''));
$status = trim((string) ($body['status'] ?? ''));

// Validate inputs — slugs loaded from DB so new lessons are accepted automatically
$validSlugStmt = get_db()->query("SELECT slug FROM lessons WHERE is_active = 1");
$allowedSlugs  = $validSlugStmt ? array_column($validSlugStmt->fetchAll(), 'slug') : ['php', 'html-css', 'javascript'];
$allowedStatuses = ['in_progress', 'completed'];

if (!in_array($slug, $allowedSlugs, true) || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid lesson_slug or status']);
    exit;
}

$userId      = (int) current_user()['id'];
$completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

$db = get_db();
$db->prepare("
    INSERT INTO lesson_progress (user_id, lesson_slug, status, visited_at, completed_at)
    VALUES (?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        status       = VALUES(status),
        visited_at   = NOW(),
        completed_at = IF(VALUES(status) = 'completed', COALESCE(completed_at, NOW()), completed_at)
")->execute([$userId, $slug, $status, $completedAt]);

echo json_encode(['success' => true]);
