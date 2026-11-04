<?php
/**
 * AralPal Admin – Tutor Management
 *
 * List all tutors, toggle is_featured, show featured first.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('Admin');

$db  = get_db();
$msg = '';
$err = '';

// ── Handle POST (toggle featured) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrf)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $action   = (string) ($_POST['action'] ?? '');
    $tutor_id = (int) ($_POST['tutor_id'] ?? 0);

    if ($action === 'toggle_featured' && $tutor_id > 0) {
        // Read current value then flip
        $sel = $db->prepare('SELECT is_featured FROM tutors WHERE id = ?');
        $sel->execute([$tutor_id]);
        $current = (int) $sel->fetchColumn();
        $new_val = $current ? 0 : 1;

        $upd = $db->prepare('UPDATE tutors SET is_featured = ? WHERE id = ?');
        $upd->execute([$new_val, $tutor_id]);
        $msg = 'Tutor featured status updated.';
    }
}

// ── Fetch tutors, featured first ─────────────────────────────────────────────
$stmt = $db->query(
    "SELECT
        t.id          AS tutor_id,
        u.full_name,
        u.email,
        t.hourly_rate,
        t.rating_average,
        t.is_featured
     FROM tutors t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.is_featured DESC, u.full_name ASC"
);
$tutors = $stmt->fetchAll();

$csrf_token = generate_csrf();
$pageTitle  = 'Tutor Management';
require __DIR__ . '/partials/header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">ADMIN / TUTORS</p>
        <h1>Tutor Management</h1>
        <p class="muted">Manage tutors and their featured status. Featured tutors appear first.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Rate (₱/hr)</th>
                    <th>Rating</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tutors)): ?>
                <tr>
                    <td colspan="6" class="empty">No tutors found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tutors as $tutor): ?>
                    <tr>
                        <td><?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($tutor['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(number_format((float) $tutor['rating_average'], 1), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($tutor['is_featured']): ?>
                                <span class="status published">⭐ Featured</span>
                            <?php else: ?>
                                <span class="status draft">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="post" action="tutors-manage.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"   value="toggle_featured">
                                <input type="hidden" name="tutor_id" value="<?= (int) $tutor['tutor_id'] ?>">
                                <button class="text-link" type="submit">
                                    <?= $tutor['is_featured'] ? 'Unfeature' : 'Feature' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
