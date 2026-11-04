<?php
/**
 * AralPal – Tutor Profile Page
 *
 * Displays a single tutor's full profile: bio, subjects, availability,
 * and reviews. The Book button is shown only to logged-in Students.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$tutor_id = (int) ($_GET['id'] ?? 0);
if ($tutor_id <= 0) {
    http_response_code(404);
    exit('Tutor not found.');
}

$db = get_db();

// ── Fetch core tutor data ─────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT
        u.id          AS user_id,
        u.full_name,
        u.avatar,
        u.email,
        t.id          AS tutor_id,
        t.bio,
        t.hourly_rate,
        t.rating_average,
        t.is_featured,
        (SELECT COUNT(*) FROM reviews r WHERE r.tutor_id = t.id) AS review_count
    FROM tutors t
    JOIN users u ON u.id = t.user_id
    WHERE t.id = ?
    LIMIT 1
');
$stmt->bindValue(1, $tutor_id, PDO::PARAM_INT);
$stmt->execute();
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    exit('Tutor not found.');
}

$page_title = htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') . ' – Tutor Profile';

// ── Fetch subjects ────────────────────────────────────────────────────────
$subj_stmt = $db->prepare('
    SELECT s.subject_name
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.tutor_id = ?
    ORDER BY s.subject_name
');
$subj_stmt->bindValue(1, $tutor_id, PDO::PARAM_INT);
$subj_stmt->execute();
$subjects = $subj_stmt->fetchAll();

// ── Fetch availability ────────────────────────────────────────────────────
$avail_stmt = $db->prepare('
    SELECT day_of_week, start_time, end_time
    FROM availability
    WHERE tutor_id = ?
    ORDER BY FIELD(day_of_week,
        \'Monday\',\'Tuesday\',\'Wednesday\',\'Thursday\',\'Friday\',\'Saturday\',\'Sunday\'
    )
');
$avail_stmt->bindValue(1, $tutor_id, PDO::PARAM_INT);
$avail_stmt->execute();
$availability = $avail_stmt->fetchAll();

// ── Fetch reviews ─────────────────────────────────────────────────────────
$rev_stmt = $db->prepare('
    SELECT
        r.id,
        r.rating,
        r.comment,
        r.created_at,
        u.full_name AS reviewer_name,
        u.avatar    AS reviewer_avatar
    FROM reviews r
    JOIN users u ON u.id = r.student_id
    WHERE r.tutor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 50
');
$rev_stmt->bindValue(1, $tutor_id, PDO::PARAM_INT);
$rev_stmt->execute();
$reviews = $rev_stmt->fetchAll();

$current    = current_user();
$is_student = $current && ($current['role_name'] ?? '') === 'Student';

$avg    = (float) ($tutor['rating_average'] ?? 0);
$avatar = htmlspecialchars(
    $tutor['avatar'] ?: '/AralPal/assets/avatar-default.png',
    ENT_QUOTES, 'UTF-8'
);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <!-- ── Profile hero ──────────────────────────────────────────── -->
    <section class="profile-hero">
        <div class="profile-hero__avatar">
            <img
                src="<?= $avatar ?>"
                alt="<?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                loading="eager"
                onerror="this.src='/AralPal/assets/avatar-default.png'"
            >
        </div>

        <div class="profile-hero__info">

            <h1 class="profile-hero__name">
                <?= htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <span class="role-badge">Tutor</span>
            <?php if ($tutor['is_featured']): ?>
                <span class="tutor-badge tutor-badge--featured">⭐ Featured</span>
            <?php endif; ?>

            <div class="profile-hero__rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="<?= $i <= $avg ? 'star-filled' : 'star-empty' ?>" aria-hidden="true">
                        <?= $i <= $avg ? '★' : '☆' ?>
                    </span>
                <?php endfor; ?>
                <span class="rating-value">
                    <?= htmlspecialchars(number_format($avg, 1), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="review-count">
                    (<?= (int) $tutor['review_count'] ?> review<?= (int) $tutor['review_count'] !== 1 ? 's' : '' ?>)
                </span>
            </div>

            <p class="profile-hero__rate">
                ₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?>/hr
            </p>

            <?php if ($is_student): ?>
                <a
                    class="btn btn-primary"
                    href="/AralPal/booking/book.php?tutor_id=<?= (int) $tutor['tutor_id'] ?>"
                >
                    📅 Book a Session
                </a>
            <?php endif; ?>

        </div><!-- /.profile-hero__info -->
    </section>

    <div class="profile-layout">

        <div class="profile-main">

            <!-- ── Bio ─────────────────────────────────────────── -->
            <?php if (!empty($tutor['bio'])): ?>
                <section class="profile-section">
                    <h2>About</h2>
                    <p><?= nl2br(htmlspecialchars($tutor['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                </section>
            <?php endif; ?>

            <!-- ── Subjects ─────────────────────────────────────── -->
            <?php if ($subjects): ?>
                <section class="profile-section">
                    <h2>Subjects</h2>
                    <div class="subject-pills">
                        <?php foreach ($subjects as $subj): ?>
                            <span class="subject-pill">
                                <?= htmlspecialchars($subj['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ── Reviews ──────────────────────────────────────── -->
            <section class="profile-section">
                <h2>Reviews (<?= (int) $tutor['review_count'] ?>)</h2>

                <?php if (empty($reviews)): ?>
                    <p class="muted">No reviews yet.</p>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $rev): ?>
                            <?php
                            $rev_avg = (int) $rev['rating'];
                            $rev_avatar = htmlspecialchars(
                                $rev['reviewer_avatar'] ?: '/AralPal/assets/avatar-default.png',
                                ENT_QUOTES, 'UTF-8'
                            );
                            ?>
                            <article class="review-card">
                                <div class="review-card__header">
                                    <img
                                        class="review-card__avatar"
                                        src="<?= $rev_avatar ?>"
                                        alt="<?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy"
                                        onerror="this.src='/AralPal/assets/avatar-default.png'"
                                    >
                                    <div>
                                        <strong><?= htmlspecialchars($rev['reviewer_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div class="review-card__stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="<?= $i <= $rev_avg ? 'star-filled' : 'star-empty' ?>" aria-hidden="true">
                                                    <?= $i <= $rev_avg ? '★' : '☆' ?>
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <time class="review-card__date" datetime="<?= htmlspecialchars($rev['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(format_date($rev['created_at']), ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                </div>
                                <?php if (!empty($rev['comment'])): ?>
                                    <p class="review-card__comment">
                                        <?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?>
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div><!-- /.profile-main -->

        <aside class="profile-sidebar">

            <!-- ── Rate ─────────────────────────────────────────── -->
            <div class="sidebar-card">
                <h3>Rate</h3>
                <p class="sidebar-rate">
                    ₱<?= htmlspecialchars(number_format((float) $tutor['hourly_rate'], 2), ENT_QUOTES, 'UTF-8') ?>/hr
                </p>
                <?php if ($is_student): ?>
                    <a
                        class="btn btn-primary btn--block"
                        href="/AralPal/booking/book.php?tutor_id=<?= (int) $tutor['tutor_id'] ?>"
                    >
                        📅 Book Now
                    </a>
                <?php endif; ?>
            </div>

            <!-- ── Availability ──────────────────────────────────── -->
            <?php if ($availability): ?>
                <div class="sidebar-card">
                    <h3>Availability</h3>
                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availability as $slot): ?>
                                <tr>
                                    <td><?= htmlspecialchars($slot['day_of_week'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?= htmlspecialchars($slot['start_time'], ENT_QUOTES, 'UTF-8') ?>
                                        –
                                        <?= htmlspecialchars($slot['end_time'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </aside><!-- /.profile-sidebar -->

    </div><!-- /.profile-layout -->

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
