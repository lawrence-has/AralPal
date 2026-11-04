<?php
declare(strict_types=1);
require_once __DIR__ . '/cms-config.php';
require_admin();

$contentCount = (int) db()->query('SELECT COUNT(*) FROM cms_content')->fetchColumn();
$announcementCount = (int) db()->query('SELECT COUNT(*) FROM cms_announcements')->fetchColumn();
$publishedCount = (int) db()->query('SELECT COUNT(*) FROM cms_announcements WHERE is_published = 1')->fetchColumn();
$recent = db()->query('SELECT id, title, is_published, updated_at FROM cms_announcements ORDER BY updated_at DESC LIMIT 5')->fetchAll();
$pageTitle = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>
<section class="welcome">
  <p class="eyebrow">ARALPAL CONTENT MANAGEMENT</p>
  <h1>Welcome, <?= e($_SESSION['cms_admin_name'] ?? 'Administrator') ?>.</h1>
  <p>Manage the public website content and announcements from one place.</p>
</section>
<section class="stats">
  <article class="stat-card"><span>Content sections</span><strong><?= $contentCount ?></strong><a href="content.php">Manage content →</a></article>
  <article class="stat-card"><span>Announcements</span><strong><?= $announcementCount ?></strong><a href="announcements.php">Manage announcements →</a></article>
  <article class="stat-card"><span>Published announcements</span><strong><?= $publishedCount ?></strong><a href="announcements.php">View publishing status →</a></article>
</section>
<section class="panel">
  <div class="panel-heading"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>Recently updated announcements</h2></div><a class="button" href="announcements.php">Manage announcements</a></div>
  <?php if (!$recent): ?>
    <p class="empty">No announcements yet. Create your first announcement to get started.</p>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Title</th><th>Status</th><th>Last updated</th></tr></thead><tbody>
    <?php foreach ($recent as $item): ?><tr><td><?= e($item['title']) ?></td><td><span class="status <?= $item['is_published'] ? 'published' : 'draft' ?>"><?= $item['is_published'] ? 'Published' : 'Draft' ?></span></td><td><?= e($item['updated_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
