<?php
/**
 * AralPal – Find a Tutor
 *
 * Lists all tutors with search/filter, favorite toggle (AJAX), and compare.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$page_title = 'Find a Tutor';

// ── Subscription tier for feature gating ──────────────────────────────────
// Resolved server-side only — never from session or GET params.
$_current      = current_user();
$_uid          = (int) ($_current['id'] ?? 0);
$_role         = (string) ($_current['role_name'] ?? '');
$_plan_tier    = get_student_plan_tier($_uid, $_role);    // 0=Free, 1=Basic, 2=Premium
$_has_basic    = $_plan_tier >= 1;   // Subject filters
$_has_premium  = $_plan_tier >= 2;   // Advanced filters, Compare tutors

// ── AJAX: toggle favorite ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'toggle_favorite'
) {
    header('Content-Type: application/json');

    $user = current_user();
    if (!$user || ($user['role_name'] ?? '') !== 'Student') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $tutor_id = (int) ($_POST['tutor_id'] ?? 0);
    if ($tutor_id <= 0) {
        echo json_encode(['error' => 'Invalid tutor']);
        exit;
    }

    $db  = get_db();
    $uid = (int) $user['id'];

    $check = $db->prepare('SELECT id FROM favorites WHERE user_id = ? AND tutor_id = ?');
    $check->execute([$uid, $tutor_id]);

    if ($check->fetch()) {
        $db->prepare('DELETE FROM favorites WHERE user_id = ? AND tutor_id = ?')
           ->execute([$uid, $tutor_id]);
        echo json_encode(['favorited' => false]);
    } else {
        $db->prepare('INSERT INTO favorites (user_id, tutor_id, created_at) VALUES (?, ?, NOW())')
           ->execute([$uid, $tutor_id]);
        echo json_encode(['favorited' => true]);
    }
    exit;
}

// ── Build search / filter query ───────────────────────────────────────────
$q          = trim($_GET['q']          ?? '');

// Subject filter — Basic plan and above only
$subject_id = $_has_basic  ? (int) ($_GET['subject_id'] ?? 0) : 0;

// Advanced filters — Premium only
$min_rate    = ($_has_premium && strlen($_GET['min_rate']   ?? '')) ? (float) $_GET['min_rate']   : null;
$max_rate    = ($_has_premium && strlen($_GET['max_rate']   ?? '')) ? (float) $_GET['max_rate']   : null;
$min_rating  = ($_has_premium && strlen($_GET['min_rating'] ?? '')) ? (float) $_GET['min_rating'] : null;
$day_of_week = $_has_premium ? trim($_GET['day_of_week'] ?? '') : '';

$db = get_db();

// Subjects for filter dropdown
$subjects_list = $db->query('SELECT id, subject_name FROM subjects ORDER BY subject_name')->fetchAll();

// Base query
$sql = '
    SELECT
        u.id          AS user_id,
        t.id          AS tutor_id,
        u.full_name,
        u.avatar,
        t.hourly_rate,
        t.rating_average,
        t.is_featured,
        GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR \',\') AS subjects
    FROM users u
    JOIN tutors t        ON u.id = t.user_id
    JOIN tutor_subjects ts ON t.id = ts.tutor_id
    JOIN subjects s      ON ts.subject_id = s.id
    LEFT JOIN availability av ON t.id = av.tutor_id
    WHERE 1=1
';

$params = [];

if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE :q OR s.subject_name LIKE :q2)';
    $params[':q']  = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

if ($subject_id > 0) {
    $sql .= ' AND ts.subject_id = :subject_id';
    $params[':subject_id'] = $subject_id;
}

if ($min_rate !== null) {
    $sql .= ' AND t.hourly_rate >= :min_rate';
    $params[':min_rate'] = $min_rate;
}

if ($max_rate !== null) {
    $sql .= ' AND t.hourly_rate <= :max_rate';
    $params[':max_rate'] = $max_rate;
}

if ($min_rating !== null) {
    $sql .= ' AND t.rating_average >= :min_rating';
    $params[':min_rating'] = $min_rating;
}

if ($day_of_week !== '') {
    $sql .= ' AND av.day_of_week = :day_of_week';
    $params[':day_of_week'] = $day_of_week;
}

$sql .= ' GROUP BY t.id ORDER BY t.is_featured DESC, t.rating_average DESC';

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    // Bind numeric params as appropriate type
    if (is_int($value)) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } elseif (is_float($value)) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR); // PDO stores floats as STR
    } else {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}
$stmt->execute();
$tutors = $stmt->fetchAll();

// Check which tutors the current user has favorited
$favorited_ids = [];
$current = current_user();
if ($current && ($current['role_name'] ?? '') === 'Student') {
    $fav_stmt = $db->prepare('SELECT tutor_id FROM favorites WHERE user_id = ?');
    $fav_stmt->execute([(int) $current['id']]);
    $favorited_ids = array_column($fav_stmt->fetchAll(), 'tutor_id');
}

$csrf = generate_csrf();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

require_once __DIR__ . '/../includes/app-header.php';
?>

<main class="container">

    <div class="page-heading">
        <h1>Find a Tutor</h1>
        <p>Browse qualified tutors and book a session.</p>
    </div>

    <!-- ── Search & Filter form ────────────────────────────────── -->
    <form class="filter-form" method="get" action="/AralPal/tutors/index.php">
        <div class="filter-row">
            <div class="filter-group filter-group--wide">
                <label for="q">Search</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Name or subject…"
                >
            </div>

            <div class="filter-group">
                <label for="subject_id">
                    Subject
                    <?php if (!$_has_basic): ?>
                        <span class="plan-lock" title="Requires Basic plan">🔒 <a href="/AralPal/subscriptions/plans.php">Basic+</a></span>
                    <?php endif; ?>
                </label>
                <?php if ($_has_basic): ?>
                    <select id="subject_id" name="subject_id">
                        <option value="0">All subjects</option>
                        <?php foreach ($subjects_list as $sub): ?>
                            <option
                                value="<?= (int) $sub['id'] ?>"
                                <?= $subject_id === (int) $sub['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($sub['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select disabled aria-disabled="true" class="filter-locked">
                        <option>Upgrade to filter by subject</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="filter-group">
                <label for="min_rate">
                    Min Rate (₱)
                    <?php if (!$_has_premium): ?>
                        <span class="plan-lock" title="Requires Premium plan">🔒 <a href="/AralPal/subscriptions/plans.php">Premium</a></span>
                    <?php endif; ?>
                </label>
                <input
                    type="number"
                    id="min_rate"
                    name="min_rate"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars((string) ($min_rate ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="0"
                    <?= !$_has_premium ? 'disabled aria-disabled="true" class="filter-locked"' : '' ?>
                >
            </div>

            <div class="filter-group">
                <label for="max_rate">
                    Max Rate (₱)
                    <?php if (!$_has_premium): ?>
                        <span class="plan-lock" title="Requires Premium plan">🔒 <a href="/AralPal/subscriptions/plans.php">Premium</a></span>
                    <?php endif; ?>
                </label>
                <input
                    type="number"
                    id="max_rate"
                    name="max_rate"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars((string) ($max_rate ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="No limit"
                    <?= !$_has_premium ? 'disabled aria-disabled="true" class="filter-locked"' : '' ?>
                >
            </div>

            <div class="filter-group">
                <label for="min_rating">
                    Min Rating
                    <?php if (!$_has_premium): ?>
                        <span class="plan-lock" title="Requires Premium plan">🔒 <a href="/AralPal/subscriptions/plans.php">Premium</a></span>
                    <?php endif; ?>
                </label>
                <?php if ($_has_premium): ?>
                    <select id="min_rating" name="min_rating">
                        <option value="">Any</option>
                        <?php for ($r = 1; $r <= 5; $r++): ?>
                            <option
                                value="<?= $r ?>"
                                <?= (string) $min_rating === (string) $r ? 'selected' : '' ?>
                            >
                                <?= $r ?>★+
                            </option>
                        <?php endfor; ?>
                    </select>
                <?php else: ?>
                    <select disabled aria-disabled="true" class="filter-locked">
                        <option>Upgrade to filter by rating</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="filter-group">
                <label for="day_of_week">
                    Day Available
                    <?php if (!$_has_premium): ?>
                        <span class="plan-lock" title="Requires Premium plan">🔒 <a href="/AralPal/subscriptions/plans.php">Premium</a></span>
                    <?php endif; ?>
                </label>
                <?php if ($_has_premium): ?>
                    <select id="day_of_week" name="day_of_week">
                        <option value="">Any day</option>
                        <?php foreach ($days as $day): ?>
                            <option
                                value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $day_of_week === $day ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select disabled aria-disabled="true" class="filter-locked">
                        <option>Upgrade to filter by day</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="filter-group filter-group--action">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="/AralPal/tutors/index.php" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    <!-- ── Tutor grid ──────────────────────────────────────────── -->
    <?php if (empty($tutors)): ?>
        <p class="no-results">No tutors found. Try adjusting your filters.</p>
    <?php else: ?>

        <form id="compareForm" action="/AralPal/tutors/compare.php" method="get">
            <div class="tutor-grid">
                <?php foreach ($tutors as $tutor): ?>
                    <?php
                    $subjects_arr = $tutor['subjects'] ? explode(',', $tutor['subjects']) : [];
                    $is_favorited = in_array((int) $tutor['tutor_id'], $favorited_ids, true);
                    $avatar_src   = htmlspecialchars($tutor['avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="tutor-card<?= $tutor['is_featured'] ? ' tutor-card--featured' : '' ?>">

                        <?php if ($tutor['is_featured']): ?>
                            <span class="tutor-badge tutor-badge--featured">⭐ Featured</span>
                        <?php endif; ?>

                        <div class="tutor-card__avatar">
                            <img
                                src="<?= $avatar_src ?>"
                                alt="<?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.src='/AralPal/assets/avatar-default.png'"
                            >
                        </div>

                        <div class="tutor-card__body">

                            <h3 class="tutor-card__name">
                                <a href="/AralPal/tutors/profile.php?id=<?= (int) $tutor['tutor_id'] ?>">
                                    <?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>

                            <?php if ($subjects_arr): ?>
                                <div class="subject-pills">
                                    <?php foreach ($subjects_arr as $subj): ?>
                                        <span class="subject-pill">
                                            <?= htmlspecialchars(trim($subj), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <p class="tutor-card__rate">
                                ₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?>/hr
                            </p>

                            <div class="tutor-card__rating">
                                <?php
                                $avg = (float) ($tutor['rating_average'] ?? 0);
                                for ($i = 1; $i <= 5; $i++):
                                ?>
                                    <span class="<?= $i <= $avg ? 'star-filled' : 'star-empty' ?>" aria-hidden="true">
                                        <?= $i <= $avg ? '★' : '☆' ?>
                                    </span>
                                <?php endfor; ?>
                                <span class="rating-value">
                                    <?= htmlspecialchars(number_format($avg, 1), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                        </div><!-- /.tutor-card__body -->

                        <div class="tutor-card__actions">

                            <?php if ($current && ($current['role_name'] ?? '') === 'Student'): ?>
                                <button
                                    type="button"
                                    class="btn-favorite<?= $is_favorited ? ' btn-favorite--active' : '' ?>"
                                    data-tutor-id="<?= (int) $tutor['tutor_id'] ?>"
                                    data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="<?= $is_favorited ? 'Remove from favorites' : 'Add to favorites' ?>"
                                    aria-pressed="<?= $is_favorited ? 'true' : 'false' ?>"
                                    title="<?= $is_favorited ? 'Remove from favorites' : 'Add to favorites' ?>"
                                >
                                    <?= $is_favorited ? '❤️' : '🤍' ?>
                                </button>
                            <?php endif; ?>

                            <a
                                class="btn btn-primary btn--sm"
                                href="/AralPal/booking/book.php?tutor_id=<?= (int) $tutor['tutor_id'] ?>"
                            >
                                Book
                            </a>

                            <?php if ($_has_premium): ?>
                                <label class="compare-label" title="Add to compare">
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="<?= (int) $tutor['tutor_id'] ?>"
                                        class="compare-checkbox"
                                        aria-label="Compare <?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                    <span>Compare</span>
                                </label>
                            <?php else: ?>
                                <span class="compare-label compare-label--locked" title="Compare tutors requires Premium plan">
                                    🔒 <a href="/AralPal/subscriptions/plans.php" tabindex="-1">Compare</a>
                                </span>
                            <?php endif; ?>

                        </div><!-- /.tutor-card__actions -->

                    </div><!-- /.tutor-card -->
                <?php endforeach; ?>
            </div><!-- /.tutor-grid -->

            <!-- ── Sticky compare bar ────────────────────────── -->
            <div class="compare-bar" id="compareBar" hidden aria-live="polite">
                <span class="compare-bar__count" id="compareCount">0 selected</span>
                <button type="submit" class="btn btn-primary" id="compareBtn">
                    Compare (<span id="compareBadge">0</span>)
                </button>
                <button type="button" class="btn btn-outline" id="clearCompare">Clear</button>
            </div>

        </form><!-- /#compareForm -->

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>

<script>
/* ──────────────────────────────────────────────────────────────────
   Favorite toggle (AJAX)
   ────────────────────────────────────────────────────────────────── */
document.querySelectorAll('.btn-favorite').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var tutorId = btn.dataset.tutorId;
        var csrf    = btn.dataset.csrf;

        var body = new URLSearchParams();
        body.append('action',     'toggle_favorite');
        body.append('tutor_id',   tutorId);
        body.append('csrf_token', csrf);

        fetch('/AralPal/tutors/index.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString()
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.error) { return; }
            var favorited = data.favorited;
            btn.textContent = favorited ? '❤️' : '🤍';
            btn.classList.toggle('btn-favorite--active', favorited);
            btn.setAttribute('aria-pressed', favorited ? 'true' : 'false');
            btn.setAttribute('aria-label',  favorited ? 'Remove from favorites' : 'Add to favorites');
            btn.setAttribute('title',       favorited ? 'Remove from favorites' : 'Add to favorites');
        })
        .catch(function () { /* silent fail */ });
    });
});

/* ──────────────────────────────────────────────────────────────────
   Compare bar
   ────────────────────────────────────────────────────────────────── */
var compareBar      = document.getElementById('compareBar');
var compareBadge    = document.getElementById('compareBadge');
var compareCountEl  = document.getElementById('compareCount');
var compareBtn      = document.getElementById('compareBtn');
var clearCompareBtn = document.getElementById('clearCompare');
var compareForm     = document.getElementById('compareForm');

function updateCompareBar() {
    var checked = document.querySelectorAll('.compare-checkbox:checked');
    var n = checked.length;

    if (n >= 2) {
        compareBar.hidden = false;
        compareBadge.textContent    = n;
        compareCountEl.textContent  = n + ' selected';
    } else {
        compareBar.hidden = true;
    }

    // Disable checkboxes beyond 3
    document.querySelectorAll('.compare-checkbox').forEach(function (cb) {
        if (!cb.checked) {
            cb.disabled = (n >= 3);
        }
    });
}

document.querySelectorAll('.compare-checkbox').forEach(function (cb) {
    cb.addEventListener('change', updateCompareBar);
});

clearCompareBtn.addEventListener('click', function () {
    document.querySelectorAll('.compare-checkbox:checked').forEach(function (cb) {
        cb.checked = false;
    });
    updateCompareBar();
});

// Redirect to compare.php?ids=1,2,3 instead of the default form POST
compareForm.addEventListener('submit', function (e) {
    var checked = document.querySelectorAll('.compare-checkbox:checked');
    if (checked.length < 2) {
        e.preventDefault();
        return;
    }
    e.preventDefault();
    var ids = Array.from(checked).map(function (cb) { return cb.value; }).join(',');
    window.location.href = '/AralPal/tutors/compare.php?ids=' + encodeURIComponent(ids);
});
</script>
