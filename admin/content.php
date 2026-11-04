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
        $sectionKey = trim((string)($_POST['section_key'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{2,100}$/', $sectionKey) || $title === '' || $body === '') {
            $error = 'Use a section key with lowercase letters, numbers, dashes or underscores (2–100 characters), and fill in title and content.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE cms_content SET section_key=?, title=?, body=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                    $stmt->execute([$sectionKey, $title, $body, $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO cms_content (section_key, title, body) VALUES (?, ?, ?)');
                    $stmt->execute([$sectionKey, $title, $body]);
                }
                redirect('content.php?saved=1');
            } catch (PDOException $ex) {
                $error = 'Could not save. Make sure each section key is unique.';
            }
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM cms_content WHERE id=?');
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        redirect('content.php?deleted=1');
    }
}
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM cms_content WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}
if (isset($_GET['saved'])) $success = 'Content section saved.';
if (isset($_GET['deleted'])) $success = 'Content section deleted.';
$rows = $pdo->query('SELECT * FROM cms_content ORDER BY updated_at DESC')->fetchAll();
$pageTitle = 'Website Content';
require __DIR__ . '/partials/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">CMS / CONTENT</p><h1>Website content</h1><p class="muted">Manage homepage sections, About text, FAQs, and plan descriptions.</p></div></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
<section class="panel">
  <h2><?= $editing ? 'Edit content section' : 'Add content section' ?></h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <label>Section key <span class="hint">Example: home_hero, about, faq_1</span><input name="section_key" required maxlength="100" pattern="[a-z0-9_-]{2,100}" value="<?= e($editing['section_key'] ?? '') ?>"></label>
    <label>Title<input name="title" required maxlength="190" value="<?= e($editing['title'] ?? '') ?>"></label>
    <label class="wide">Content<textarea name="body" rows="5" required><?= e($editing['body'] ?? '') ?></textarea></label>
    <div class="form-actions"><button class="button" type="submit"><?= $editing ? 'Save changes' : 'Add content' ?></button><?php if ($editing): ?><a class="button secondary" href="content.php">Cancel</a><?php endif; ?></div>
  </form>
</section>
<section class="panel">
  <div class="panel-heading"><div><p class="eyebrow">CONTENT LIBRARY</p><h2>All sections <span class="count"><?= count($rows) ?></span></h2></div></div>
  <?php if (!$rows): ?><p class="empty">No content sections yet. Add one using the form above.</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Key</th><th>Title</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $row): ?><tr><td><code><?= e($row['section_key']) ?></code></td><td><strong><?= e($row['title']) ?></strong><p class="table-sub"><?= e(mb_strimwidth($row['body'], 0, 100, '…')) ?></p></td><td><?= e($row['updated_at']) ?></td><td class="actions"><a class="text-link" href="content.php?edit=<?= (int)$row['id'] ?>">Edit</a><form method="post" data-confirm="Delete this content section?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="text-link danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
