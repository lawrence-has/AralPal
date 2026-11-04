<?php
/**
 * AralPal – Compare Tutors
 *
 * Shows a side-by-side comparison table of up to 3 tutors.
 * Accepts ?ids=1,2,3 (comma-separated tutor IDs).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Compare Tutors';

// ── Parse and validate IDs ────────────────────────────────────────────────
$raw_ids = trim($_GET['ids'] ?? '');
$id_parts = array_filter(array_map('trim', explode(',', $raw_ids)));

$tutor_ids = [];
foreach ($id_parts as $part) {
    if (ctype_digit($part) && (int) $part > 0) {
        $tutor_ids[] = (int) $part;
    }
}

// Enforce max 3
$tutor_ids = array_slice(array_unique($tutor_ids), 0, 3);

if (count($tutor_ids) < 2) {
    http_response_code(400);
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="container"><p class="muted">Please select at least 2 tutors to compare. <a href="/AralPal/tutors/index.php">Back to tutors</a></p></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$db = get_db();

// ── Fetch each tutor ──────────────────────────────────────────────────────
$tutors = [];

foreach ($tutor_ids as $tid) {
    $stmt = $db->prepare('
        SELECT
            u.full_name,
            u.avatar,
            t.id          AS tutor_id,
            t.bio,
            t.hourly_rate,
            t.rating_average,
            GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR \', \') AS subjects,
            GROUP_CONCAT(DISTINCT av.day_of_week ORDER BY FIELD(av.day_of_week,
                \'Monday\',\'Tuesday\',\'Wednesday\',\'Thursday\',\'Friday\',\'Saturday\',\'Sunday\'
            ) SEPARATOR \', \') AS available_days
        FROM tutors t
        JOIN users u           ON u.id = t.user_id
        LEFT JOIN tutor_subjects ts ON t.id = ts.tutor_id
        LEFT JOIN subjects s       ON ts.subject_id = s.id
        LEFT JOIN availability av  ON t.id = av.tutor_id
        WHERE t.id = ?
        GROUP BY t.id
        LIMIT 1
    ');
    $stmt->bindValue(1, $tid, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    if ($row) {
        $tutors[] = $row;
    }
}

if (count($tutors) < 2) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="container"><p class="muted">One or more tutors could not be found. <a href="/AralPal/tutors/index.php">Back to tutors</a></p></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <div class="page-heading">
        <h1>Compare Tutors</h1>
        <p>
            <a href="/AralPal/tutors/index.php" class="back-link">← Back to Find a Tutor</a>
        </p>
    </div>

    <div class="compare-table-wrap">
        <table class="compare-table">
            <thead>
                <tr>
                    <th class="compare-table__attr" scope="col">Attribute</th>
                    <?php foreach ($tutors as $t): ?>
                        <th scope="col">
                            <?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>

                <!-- Photo row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Photo</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell compare-table__cell--center">
                            <img
                                class="compare-avatar"
                                src="<?= htmlspecialchars($t['avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.src='/AralPal/assets/avatar-default.png'"
                            >
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Name row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Name</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell">
                            <a href="/AralPal/tutors/profile.php?id=<?= (int) $t['tutor_id'] ?>">
                                <?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Subjects row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Subjects</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell">
                            <?php if ($t['subjects']): ?>
                                <?php foreach (explode(', ', $t['subjects']) as $subj): ?>
                                    <span class="subject-pill">
                                        <?= htmlspecialchars(trim($subj), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Rate row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Rate</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell">
                            ₱<?= htmlspecialchars(number_format((float) $t['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?>/hr
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Rating row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Rating</th>
                    <?php foreach ($tutors as $t): ?>
                        <?php $avg = (float) ($t['rating_average'] ?? 0); ?>
                        <td class="compare-table__cell">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?= $i <= $avg ? 'star-filled' : 'star-empty' ?>" aria-hidden="true">
                                    <?= $i <= $avg ? '★' : '☆' ?>
                                </span>
                            <?php endfor; ?>
                            <span class="rating-value">
                                <?= htmlspecialchars(number_format($avg, 1), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Bio row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Bio</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell compare-table__cell--bio">
                            <?php if ($t['bio'] !== '' && $t['bio'] !== null): ?>
                                <?= nl2br(htmlspecialchars($t['bio'], ENT_QUOTES, 'UTF-8')) ?>
                            <?php else: ?>
                                <span class="muted">No bio provided.</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Availability row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Available Days</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell">
                            <?= $t['available_days']
                                ? htmlspecialchars($t['available_days'], ENT_QUOTES, 'UTF-8')
                                : '<span class="muted">Not listed</span>'
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Book row -->
                <tr>
                    <th scope="row" class="compare-table__attr">Book</th>
                    <?php foreach ($tutors as $t): ?>
                        <td class="compare-table__cell compare-table__cell--center">
                            <a
                                class="btn btn-primary btn--sm"
                                href="/AralPal/booking/book.php?tutor_id=<?= (int) $t['tutor_id'] ?>"
                            >
                                Book
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>

            </tbody>
        </table>
    </div><!-- /.compare-table-wrap -->

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
