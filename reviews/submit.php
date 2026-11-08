<?php
/**
 * AralPal – Submit a Review
 *
 * GET:  Show the star-rating form for a completed booking.
 * POST: Validate and insert the review, update the tutor average, notify tutor.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('Student');

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Resolve booking_id ────────────────────────────────────────────────────────
$booking_id = (int) ($_GET['booking_id'] ?? ($_POST['booking_id'] ?? 0));

if ($booking_id <= 0) {
    http_response_code(400);
    redirect('/AralPal/booking/my-bookings.php');
}

// ── Fetch booking + tutor info ────────────────────────────────────────────────
$bk_stmt = $db->prepare('
    SELECT
        b.id,
        b.student_id,
        b.tutor_id,
        b.status,
        t.id          AS t_tutor_id,
        tu.full_name  AS tutor_name,
        tu.avatar     AS tutor_avatar,
        t.user_id     AS tutor_user_id
    FROM bookings b
    JOIN tutors t  ON t.id  = b.tutor_id
    JOIN users  tu ON tu.id = t.user_id
    WHERE b.id = ?
    LIMIT 1
');
$bk_stmt->execute([$booking_id]);
$booking = $bk_stmt->fetch();

// Verify booking exists, belongs to this student, and is completed
if (!$booking
    || (int) $booking['student_id'] !== $user_id
    || $booking['status'] !== 'completed'
) {
    redirect('/AralPal/booking/my-bookings.php');
}

// ── Check for existing review ─────────────────────────────────────────────────
$rev_stmt = $db->prepare('SELECT id FROM reviews WHERE booking_id = ? LIMIT 1');
$rev_stmt->execute([$booking_id]);
if ($rev_stmt->fetch()) {
    // Already reviewed
    redirect('/AralPal/booking/my-bookings.php');
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $rating  = (int) ($_POST['rating']  ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if (empty($errors)) {
        // 2. Re-verify booking ownership + status + no existing review
        $bk_chk = $db->prepare('
            SELECT id FROM bookings
            WHERE id = ? AND student_id = ? AND status = \'completed\'
            LIMIT 1
        ');
        $bk_chk->execute([$booking_id, $user_id]);
        if (!$bk_chk->fetch()) {
            $errors[] = 'Booking is no longer eligible for a review.';
        }

        $rev_chk = $db->prepare('SELECT id FROM reviews WHERE booking_id = ? LIMIT 1');
        $rev_chk->execute([$booking_id]);
        if ($rev_chk->fetch()) {
            $errors[] = 'You have already submitted a review for this booking.';
        }
    }

    if (empty($errors)) {
        // 3. Validate rating
        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please select a star rating between 1 and 5.';
        }
    }

    if (empty($errors)) {
        $tutor_id      = (int) $booking['t_tutor_id'];
        $tutor_user_id = (int) $booking['tutor_user_id'];

        // 4. INSERT review
        $ins = $db->prepare('
            INSERT INTO reviews (booking_id, student_id, tutor_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ');
        $ins->execute([$booking_id, $user_id, $tutor_id, $rating, $comment]);

        // 5. Recalculate tutor rating_average (COALESCE handles zero-review edge case)
        $avg_stmt = $db->prepare('
            UPDATE tutors
            SET rating_average = COALESCE(
                (SELECT AVG(rating) FROM reviews WHERE tutor_id = ?),
                0.00
            )
            WHERE id = ?
        ');
        $avg_stmt->execute([$tutor_id, $tutor_id]);

        // 6. Add notification for tutor
        $student_name = htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8');
        $notif_msg    = "{$student_name} left you a {$rating}-star review";
        $notif_ins    = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $notif_ins->execute([$tutor_user_id, $notif_msg]);

        // 7. Redirect
        redirect('/AralPal/booking/my-bookings.php?reviewed=1');
    }
}

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title  = 'Leave a Review';
$csrf_token  = generate_csrf();
$tutor_name  = htmlspecialchars($booking['tutor_name'], ENT_QUOTES, 'UTF-8');
$tutor_avatar = htmlspecialchars(
    $booking['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png',
    ENT_QUOTES, 'UTF-8'
);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container review-container">

    <div class="review-card card">

        <!-- ── Tutor info ──────────────────────────────────────────────── -->
        <div class="review-tutor-header">
            <img
                class="review-tutor-avatar"
                src="<?= $tutor_avatar ?>"
                alt="<?= $tutor_name ?>"
                loading="lazy"
                onerror="this.src='/AralPal/assets/avatar-default.png'"
            >
            <div>
                <p class="review-tutor-label">Reviewing your session with</p>
                <h1 class="review-tutor-name"><?= $tutor_name ?></h1>
            </div>
        </div>

        <!-- ── Errors ─────────────────────────────────────────────────── -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ── Review form ────────────────────────────────────────────── -->
        <form
            class="review-form"
            method="post"
            action="/AralPal/reviews/submit.php?booking_id=<?= $booking_id ?>"
            novalidate
        >
            <input type="hidden" name="csrf_token"  value="<?= e($csrf_token) ?>">
            <input type="hidden" name="booking_id"  value="<?= $booking_id ?>">
            <!-- Hidden field that stores the chosen star value -->
            <input type="hidden" name="rating" id="rating-input" value="<?= (int) ($_POST['rating'] ?? 0) ?>">

            <!-- Star rating widget -->
            <fieldset class="form-group star-fieldset">
                <legend>Your Rating <span aria-hidden="true" class="required">*</span></legend>
                <div
                    class="star-widget"
                    role="radiogroup"
                    aria-label="Star rating"
                    aria-required="true"
                    id="star-widget"
                >
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button
                            type="button"
                            class="star-btn"
                            data-value="<?= $i ?>"
                            aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"
                            aria-pressed="false"
                        ><i class="far fa-star"></i></button>
                    <?php endfor; ?>
                </div>
                <p class="star-label-text" id="star-label-text" aria-live="polite"></p>
            </fieldset>

            <!-- Comment -->
            <div class="form-group">
                <label for="comment">Comment <span class="muted">(optional)</span></label>
                <textarea
                    id="comment"
                    name="comment"
                    rows="4"
                    maxlength="1000"
                    placeholder="Share your experience with this tutor…"
                ><?= e($_POST['comment'] ?? '') ?></textarea>
            </div>

            <div class="review-form__actions">
                <a class="btn btn-outline" href="/AralPal/booking/my-bookings.php">Cancel</a>
                <button type="submit" class="btn btn-primary" id="submit-review-btn" disabled>
                    Submit Review
                </button>
            </div>
        </form>

    </div><!-- /.review-card -->

</main>

<script>
(function () {
    'use strict';

    var stars      = document.querySelectorAll('.star-btn');
    var ratingInput = document.getElementById('rating-input');
    var labelText  = document.getElementById('star-label-text');
    var submitBtn  = document.getElementById('submit-review-btn');

    var labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    // Restore value from server re-render (POST with errors)
    var savedValue = parseInt(ratingInput.value, 10) || 0;
    if (savedValue >= 1 && savedValue <= 5) {
        setRating(savedValue);
    }

    // Hover effect
    stars.forEach(function (btn) {
        btn.addEventListener('mouseenter', function () {
            var val = parseInt(btn.dataset.value, 10);
            highlightUpTo(val, 'hovered');
            labelText.textContent = labels[val] || '';
        });
        btn.addEventListener('mouseleave', function () {
            var current = parseInt(ratingInput.value, 10) || 0;
            setRating(current);
        });
    });

    // Click to select
    stars.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var val = parseInt(btn.dataset.value, 10);
            setRating(val);
        });
    });

    function setRating(val) {
        ratingInput.value = val;
        highlightUpTo(val, 'selected');
        labelText.textContent = labels[val] || '';
        submitBtn.disabled = val < 1;
        stars.forEach(function (b) {
            b.setAttribute('aria-pressed', parseInt(b.dataset.value, 10) === val ? 'true' : 'false');
        });
    }

    function highlightUpTo(val, cls) {
        stars.forEach(function (b) {
            b.classList.remove('selected', 'hovered');
            if (parseInt(b.dataset.value, 10) <= val) {
                b.classList.add(cls);
            }
            // Toggle Font Awesome solid vs. regular star
            var filled = b.classList.contains('selected') || b.classList.contains('hovered');
            b.innerHTML = filled
                ? '<i class="fas fa-star" aria-hidden="true"></i>'
                : '<i class="far fa-star" aria-hidden="true"></i>';
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
