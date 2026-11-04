<?php
declare(strict_types=1);
require_once __DIR__ . '/cms-config.php';
require_admin();
$pdo = db();
$error = '';
$success = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $published = isset($_POST['is_published']) ? 1 : 0;
        if ($title === '' || $body === '') {
            $error = 'Title and announcement content are required.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE cms_announcements SET title=?, body=?, is_published=?, updated_at=CURRENT_TIMESTAMP, published_at=IF(?, COALESCE(published_at, CURRENT_TIMESTAMP), NULL) WHERE id=?');
                $stmt->execute([$title, $body, $published, $published, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO cms_announcements (title, body, is_published, published_at, created_by) VALUES (?, ?, ?, IF(?, CURRENT_TIMESTAMP, NULL), ?)');
                $stmt->execute([$title, $body, $published, $published, (int)$_SESSION['cms_admin_id']]);
            }
            redirect('announcements.php?saved=1');
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM cms_announcements WHERE id=?');
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        redirect('announcements.php?deleted=1');
    }
}
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM cms_announcements WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}
if (isset($_GET['saved'])) $success = 'Announcement saved.';
if (isset($_GET['deleted'])) $success = 'Announcement deleted.';
$rows = $pdo->query('SELECT * FROM cms_announcements ORDER BY updated_at DESC')->fetchAll();
$pageTitle = 'Announcements';
require __DIR__ . '/partials/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">CMS / ANNOUNCEMENTS</p><h1>Announcements</h1><p class="muted">Create announcements and choose whether they are published.</p></div></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
<section class="panel">
  <h2><?= $editing ? 'Edit announcement' : 'Create announcement' ?></h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <label class="wide">Announcement title<input name="title" required maxlength="190" value="<?= e($editing['title'] ?? '') ?>"></label>
    <label class="wide">Announcement content<textarea name="body" rows="5" required><?= e($editing['body'] ?? '') ?></textarea></label>
    <label class="check-label"><input type="checkbox" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>> Publish this announcement</label>
    <div class="form-actions"><button class="button" type="submit"><?= $editing ? 'Save changes' : 'Create announcement' ?></button><?php if ($editing): ?><a class="button secondary" href="announcements.php">Cancel</a><?php endif; ?></div>
  </form>
</section>
<section class="panel">
  <div class="panel-heading"><div><p class="eyebrow">ANNOUNCEMENT LIBRARY</p><h2>All announcements <span class="count"><?= count($rows) ?></span></h2></div></div>
  <?php if (!$rows): ?><p class="empty">No announcements yet.</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Announcement</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['title']) ?></strong><p class="table-sub"><?= e(mb_strimwidth($row['body'], 0, 100, '…')) ?></p></td><td><span class="status <?= $row['is_published'] ? 'published' : 'draft' ?>"><?= $row['is_published'] ? 'Published' : 'Draft' ?></span></td><td><?= e($row['updated_at']) ?></td><td class="actions"><a class="text-link" href="announcements.php?edit=<?= (int)$row['id'] ?>">Edit</a><form method="post" data-confirm="Delete this announcement?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="text-link danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
