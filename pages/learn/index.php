<?php
/**
 * AralPal – Learning Center homepage
 * Student-only: lists all lessons from the lessons table, supports search
 * and category filtering, shows Continue Learning and Recommended sections.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Redirect non-Students away from the Learning Center
$_lc_user = current_user();
if (strtolower((string)($_lc_user['role_name'] ?? '')) === 'tutor') {
    header('Location: /AralPal/dashboard/tutor.php');
    exit;
}
if (strtolower((string)($_lc_user['role_name'] ?? '')) === 'admin') {
    header('Location: /AralPal/admin/users.php');
    exit;
}

$db      = get_db();
$user_id = (int)$_lc_user['id'];

// ── Filters ───────────────────────────────────────────────────────────────
$q   = trim($_GET['q']   ?? '');
$cat = trim($_GET['cat'] ?? '');

// ── All active lessons ────────────────────────────────────────────────────
$sql    = "SELECT * FROM lessons WHERE is_active = 1";
$params = [];
if ($q !== '') {
    $sql   .= " AND (title LIKE :q OR category LIKE :q2 OR description LIKE :q3)";
    $params[':q']  = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if ($cat !== '') {
    $sql   .= " AND category = :cat";
    $params[':cat'] = $cat;
}
$sql .= " ORDER BY sort_order ASC";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$allLessons = $stmt->fetchAll();

// ── User progress map ─────────────────────────────────────────────────────
$progStmt = $db->prepare("SELECT lesson_slug, status FROM lesson_progress WHERE user_id = ?");
$progStmt->execute([$user_id]);
$progressMap = array_column($progStmt->fetchAll(), 'status', 'lesson_slug');

// ── Continue learning (in_progress) ───────────────────────────────────────
$continueLessons = array_values(array_filter($allLessons, fn($l) =>
    ($progressMap[$l['slug']] ?? '') === 'in_progress'
));

// ── Recommended (not started, max 6) ─────────────────────────────────────
$recommended = array_slice(array_values(array_filter($allLessons, fn($l) =>
    !isset($progressMap[$l['slug']])
)), 0, 6);

// ── Categories ────────────────────────────────────────────────────────────
$catStmt = $db->query("SELECT category FROM lessons WHERE is_active = 1 GROUP BY category ORDER BY MIN(sort_order) ASC");
$categories = array_column($catStmt->fetchAll(), 'category');

$page_title = 'Learning Center';
require_once __DIR__ . '/../../includes/app-header.php';

function lc_difficulty_class(string $d): string {
    return match($d) {
        'Intermediate' => 'lc-badge--intermediate',
        'Advanced'     => 'lc-badge--advanced',
        default        => 'lc-badge--beginner',
    };
}
function lc_progress_label(string $status): string {
    return match($status) {
        'completed'   => '<span class="lc-prog lc-prog--done">✓ Completed</span>',
        'in_progress' => '<span class="lc-prog lc-prog--wip">▶ In Progress</span>',
        default       => '',
    };
}
?>
<style>
.lc-wrap { max-width: 1100px; }
.lc-hero { margin-bottom: 1.75rem; }
.lc-hero h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 .4rem; }
.lc-hero p  { color: var(--clr-muted); margin: 0; }
.lc-search-bar { display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.lc-search-bar input { flex: 1; min-width: 200px; padding: .55rem .9rem; border: 1px solid var(--clr-border); border-radius: var(--radius-md); background: var(--clr-surface); color: var(--clr-text); font-size: .9rem; }
.lc-search-bar select { padding: .55rem .9rem; border: 1px solid var(--clr-border); border-radius: var(--radius-md); background: var(--clr-surface); color: var(--clr-text); font-size: .9rem; }
.lc-search-bar button { padding: .55rem 1.1rem; background: var(--clr-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; }
.lc-search-bar a { padding: .55rem 1rem; border: 1px solid var(--clr-border); border-radius: var(--radius-md); color: var(--clr-muted); text-decoration: none; font-size: .88rem; }

.lc-section { margin-bottom: 2rem; }
.lc-section-title { font-size: 1.05rem; font-weight: 700; margin: 0 0 .9rem; display: flex; align-items: center; gap: .5rem; }

.lc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
.lc-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1.1rem 1.2rem; display: flex; flex-direction: column; gap: .5rem; box-shadow: var(--shadow-sm); transition: box-shadow var(--transition), transform var(--transition); text-decoration: none; color: inherit; }
.lc-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); transform: translateY(-2px); }
.lc-card__cat { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--clr-muted); }
.lc-card__title { font-size: .97rem; font-weight: 700; line-height: 1.4; }
.lc-card__desc { font-size: .82rem; color: var(--clr-muted); line-height: 1.5; flex: 1; }
.lc-card__meta { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-top: .25rem; }
.lc-badge { display: inline-block; padding: .15em .55em; border-radius: 99px; font-size: .68rem; font-weight: 600; }
.lc-badge--beginner     { background: #dcfce7; color: #166534; }
.lc-badge--intermediate { background: #fef9c3; color: #92400e; }
.lc-badge--advanced     { background: #fee2e2; color: #991b1b; }
.lc-dur { font-size: .75rem; color: var(--clr-muted); }
.lc-prog { font-size: .72rem; font-weight: 600; padding: .15em .5em; border-radius: 99px; }
.lc-prog--done { background: #dcfce7; color: #166534; }
.lc-prog--wip  { background: #dbeafe; color: #1e40af; }

.lc-cat-chips { display: flex; gap: .6rem; flex-wrap: wrap; }
.lc-cat-chip { padding: .4rem .9rem; border-radius: 99px; font-size: .82rem; font-weight: 500; text-decoration: none; border: 1px solid var(--clr-border); background: var(--clr-surface); color: var(--clr-text); transition: background var(--transition), color var(--transition); }
.lc-cat-chip:hover, .lc-cat-chip.active { background: var(--clr-primary); color: #fff; border-color: var(--clr-primary); }

.lc-empty { padding: 2rem 1rem; text-align: center; color: var(--clr-muted); font-size: .92rem; }
</style>

<div class="lc-wrap">

    <div class="lc-hero">
        <h1>📚 Learning Center</h1>
        <p>Browse lessons, track your progress, and build real skills.</p>
    </div>

    <!-- Search & filter -->
    <form class="lc-search-bar" method="get" action="/AralPal/pages/learn/index.php">
        <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search lessons…">
        <select name="cat">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $cat === $c ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Search</button>
        <?php if ($q !== '' || $cat !== ''): ?>
            <a href="/AralPal/pages/learn/index.php">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Category chips (only when not filtering) -->
    <?php if ($q === '' && $cat === ''): ?>
    <div class="lc-section">
        <p class="lc-section-title">📂 Browse by Category</p>
        <div class="lc-cat-chips">
            <?php foreach ($categories as $c): ?>
                <a href="/AralPal/pages/learn/index.php?cat=<?= urlencode($c) ?>"
                   class="lc-cat-chip">
                    <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Continue Learning -->
    <?php if (!empty($continueLessons) && $q === '' && $cat === ''): ?>
    <div class="lc-section">
        <p class="lc-section-title">▶ Continue Learning</p>
        <div class="lc-grid">
            <?php foreach ($continueLessons as $lesson): ?>
                <a class="lc-card" href="/AralPal/pages/learn/lesson.php?slug=<?= urlencode($lesson['slug']) ?>">
                    <span class="lc-card__cat"><?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="lc-card__title"><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="lc-card__meta">
                        <span class="lc-badge <?= lc_difficulty_class($lesson['difficulty']) ?>"><?= htmlspecialchars($lesson['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-dur">⏱ <?= (int)$lesson['duration_min'] ?> min</span>
                        <?= lc_progress_label($progressMap[$lesson['slug']] ?? '') ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recommended or search results -->
    <?php if ($q !== '' || $cat !== ''): ?>
        <!-- Search / filter results -->
        <div class="lc-section">
            <p class="lc-section-title">
                🔍 Results
                <small style="font-weight:400;color:var(--clr-muted)">(<?= count($allLessons) ?> found)</small>
            </p>
            <?php if (empty($allLessons)): ?>
                <p class="lc-empty">No lessons match your search. <a href="/AralPal/pages/learn/index.php">Clear filters</a>.</p>
            <?php else: ?>
                <div class="lc-grid">
                    <?php foreach ($allLessons as $lesson): ?>
                        <a class="lc-card" href="/AralPal/pages/learn/lesson.php?slug=<?= urlencode($lesson['slug']) ?>">
                            <span class="lc-card__cat"><?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="lc-card__title"><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="lc-card__desc"><?= htmlspecialchars($lesson['description'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="lc-card__meta">
                                <span class="lc-badge <?= lc_difficulty_class($lesson['difficulty']) ?>"><?= htmlspecialchars($lesson['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="lc-dur">⏱ <?= (int)$lesson['duration_min'] ?> min</span>
                                <?= lc_progress_label($progressMap[$lesson['slug']] ?? '') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Recommended lessons -->
        <?php if (!empty($recommended)): ?>
        <div class="lc-section">
            <p class="lc-section-title">⭐ Recommended — Start Here</p>
            <div class="lc-grid">
                <?php foreach ($recommended as $lesson): ?>
                    <a class="lc-card" href="/AralPal/pages/learn/lesson.php?slug=<?= urlencode($lesson['slug']) ?>">
                        <span class="lc-card__cat"><?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__title"><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__desc"><?= htmlspecialchars($lesson['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__meta">
                            <span class="lc-badge <?= lc_difficulty_class($lesson['difficulty']) ?>"><?= htmlspecialchars($lesson['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="lc-dur">⏱ <?= (int)$lesson['duration_min'] ?> min</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- All lessons by category -->
        <?php
        $byCategory = [];
        foreach ($allLessons as $l) {
            $byCategory[$l['category']][] = $l;
        }
        foreach ($byCategory as $catName => $catLessons): ?>
        <div class="lc-section">
            <p class="lc-section-title">
                <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <div class="lc-grid">
                <?php foreach ($catLessons as $lesson): ?>
                    <a class="lc-card" href="/AralPal/pages/learn/lesson.php?slug=<?= urlencode($lesson['slug']) ?>">
                        <span class="lc-card__cat"><?= htmlspecialchars($lesson['category'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__title"><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__desc"><?= htmlspecialchars($lesson['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="lc-card__meta">
                            <span class="lc-badge <?= lc_difficulty_class($lesson['difficulty']) ?>"><?= htmlspecialchars($lesson['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="lc-dur">⏱ <?= (int)$lesson['duration_min'] ?> min</span>
                            <?= lc_progress_label($progressMap[$lesson['slug']] ?? '') ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>
