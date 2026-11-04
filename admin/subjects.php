<?php
/**
 * AralPal Admin – Subject Management
 *
 * Add / edit / delete subjects. Deletion blocked when referenced by tutor_subjects.
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

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrf)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $action     = (string) ($_POST['action'] ?? '');
    $subject_id = (int) ($_POST['subject_id'] ?? 0);

    if ($action === 'add') {
        $name = trim((string) ($_POST['subject_name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $err = 'Subject name is required.';
        } else {
            $ins = $db->prepare(
                'INSERT INTO subjects (subject_name, description) VALUES (?, ?)'
            );
            $ins->execute([$name, $desc]);
            $msg = 'Subject added.';
        }
    }

    if ($action === 'edit' && $subject_id > 0) {
        $name = trim((string) ($_POST['subject_name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $err = 'Subject name is required.';
        } else {
            $upd = $db->prepare(
                'UPDATE subjects SET subject_name = ?, description = ? WHERE id = ?'
            );
            $upd->execute([$name, $desc, $subject_id]);
            $msg = 'Subject updated.';
        }
    }

    if ($action === 'delete' && $subject_id > 0) {
        // Check for tutor_subjects references
        $ref = $db->prepare('SELECT COUNT(*) FROM tutor_subjects WHERE subject_id = ?');
        $ref->execute([$subject_id]);
        $ref_count = (int) $ref->fetchColumn();

        if ($ref_count > 0) {
            $err = 'Cannot delete: this subject is assigned to ' . $ref_count . ' tutor(s).';
        } else {
            $del = $db->prepare('DELETE FROM subjects WHERE id = ?');
            $del->execute([$subject_id]);
            $msg = 'Subject deleted.';
        }
    }
}

// ── Fetch all subjects ────────────────────────────────────────────────────────
$stmt    = $db->query('SELECT id, subject_name, description FROM subjects ORDER BY subject_name ASC');
$subjects = $stmt->fetchAll();

$csrf_token = generate_csrf();
$pageTitle  = 'Subject Management';
require __DIR__ . '/partials/header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">ADMIN / SUBJECTS</p>
        <h1>Subject Management</h1>
        <p class="muted">Add, edit, or remove subjects offered on AralPal.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Add Subject Form -->
<section class="panel">
    <h2>Add New Subject</h2>
    <form method="post" action="subjects.php" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add">
        <label class="wide">
            Subject Name
            <input type="text" name="subject_name" required maxlength="120" placeholder="e.g. Mathematics">
        </label>
        <label class="wide">
            Description
            <textarea name="description" rows="2" placeholder="Brief description (optional)"></textarea>
        </label>
        <div class="form-actions">
            <button class="button" type="submit">Add Subject</button>
        </div>
    </form>
</section>

<!-- Subject List -->
<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">SUBJECT LIBRARY</p>
            <h2>All Subjects <span class="count"><?= count($subjects) ?></span></h2>
        </div>
    </div>

    <?php if (empty($subjects)): ?>
        <p class="empty">No subjects yet. Add one above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subjects as $subj): ?>
                    <tr>
                        <td><?= (int) $subj['id'] ?></td>
                        <td>
                            <!-- Inline edit form -->
                            <form method="post" action="subjects.php" style="display:flex;gap:0.4rem;align-items:center">
                                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"      value="edit">
                                <input type="hidden" name="subject_id"  value="<?= (int) $subj['id'] ?>">
                                <input
                                    type="text"
                                    name="subject_name"
                                    required
                                    maxlength="120"
                                    value="<?= htmlspecialchars($subj['subject_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    style="width:180px"
                                >
                                <textarea
                                    name="description"
                                    rows="1"
                                    style="width:220px"
                                ><?= htmlspecialchars($subj['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <button class="button" type="submit">Save</button>
                            </form>
                        </td>
                        <td><!-- description shown inline in edit form above --></td>
                        <td class="actions">
                            <!-- Delete form -->
                            <form
                                method="post"
                                action="subjects.php"
                                onsubmit="return confirm('Delete this subject? This cannot be undone.');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"     value="delete">
                                <input type="hidden" name="subject_id" value="<?= (int) $subj['id'] ?>">
                                <button class="text-link danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
