<?php
/**
 * AralPal – Booking Confirmation
 *
 * GET:  Show booking summary and a "Confirm" button.
 * POST: Send confirmation emails to student and tutor, redirect to my-bookings.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-templates.php';

require_login();

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Resolve booking_id ────────────────────────────────────────────────────────
$booking_id = (int) ($_GET['booking_id'] ?? ($_POST['booking_id'] ?? 0));
if ($booking_id <= 0) {
    http_response_code(400);
    exit('Invalid booking.');
}

// ── Fetch booking with joined data ────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT
        b.id,
        b.student_id,
        b.tutor_id,
        b.subject_id,
        b.session_date,
        b.start_time,
        b.end_time,
        b.status,
        b.created_at,
        tu.full_name  AS tutor_name,
        tu.email      AS tutor_email,
        tu.avatar     AS tutor_avatar,
        su.full_name  AS student_name,
        su.email      AS student_email,
        s.subject_name
    FROM bookings b
    JOIN tutors  t  ON t.id       = b.tutor_id
    JOIN users   tu ON tu.id      = t.user_id
    JOIN users   su ON su.id      = b.student_id
    JOIN subjects s ON s.id       = b.subject_id
    WHERE b.id = ?
    LIMIT 1
');
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

// Ownership check – only the student who made this booking may view it
if ((int) $booking['student_id'] !== $user_id) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    // Re-verify ownership
    $post_booking_id = (int) ($_POST['booking_id'] ?? 0);
    if ($post_booking_id !== $booking_id) {
        http_response_code(400);
        exit('Booking mismatch.');
    }

    // ── Email: student confirmation ───────────────────────────────────────
    $student_subject = 'Your AralPal booking request has been sent';
    send_mail($booking['student_email'], $student_subject, email_booking_confirmed_student($booking, $booking['tutor_name']));

    // ── Email: tutor new request ──────────────────────────────────────────
    $tutor_subject = 'New booking request on AralPal';
    send_mail($booking['tutor_email'], $tutor_subject, email_booking_confirmed_tutor($booking, $booking['student_name']));

    header('Location: /AralPal/booking/my-bookings.php?confirmed=1');
    exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title  = 'Booking Confirmation';
$csrf_token  = generate_csrf();
$avatar      = htmlspecialchars(
    $booking['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png',
    ENT_QUOTES, 'UTF-8'
);

$status_labels = [
    'pending'   => ['label' => 'Pending',   'class' => 'badge-warning'],
    'accepted'  => ['label' => 'Accepted',  'class' => 'badge-success'],
    'declined'  => ['label' => 'Declined',  'class' => 'badge-danger'],
    'completed' => ['label' => 'Completed', 'class' => 'badge-info'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-secondary'],
];
$status_info = $status_labels[$booking['status']] ?? ['label' => e($booking['status']), 'class' => 'badge-secondary'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container booking-container">

    <div class="card confirm-card">
        <div class="confirm-card__icon" aria-hidden="true"><i class="fas fa-check-circle" style="color:#16a34a;font-size:2.5rem;" aria-hidden="true"></i></div>
        <h1 class="confirm-card__title">Booking Submitted!</h1>
        <p class="confirm-card__sub">Review the details below and click <strong>Confirm</strong> to notify both parties.</p>

        <!-- ── Summary ────────────────────────────────────────────────── -->
        <div class="booking-summary">
            <div class="booking-summary__tutor">
                <img
                    class="booking-summary__avatar"
                    src="<?= $avatar ?>"
                    alt="<?= e($booking['tutor_name']) ?>"
                    onerror="this.src='/AralPal/assets/avatar-default.png'"
                >
                <div>
                    <strong><?= e($booking['tutor_name']) ?></strong>
                    <span class="role-badge">Tutor</span>
                </div>
            </div>

            <table class="booking-summary__table">
                <tbody>
                    <tr>
                        <th scope="row">Subject</th>
                        <td><?= e($booking['subject_name']) ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Date</th>
                        <td><?= e(date('F j, Y', strtotime($booking['session_date']))) ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Time</th>
                        <td>
                            <?= e(date('g:i A', strtotime($booking['start_time']))) ?>
                            –
                            <?= e(date('g:i A', strtotime($booking['end_time']))) ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <span class="badge <?= e($status_info['class']) ?>">
                                <?= e($status_info['label']) ?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Confirm button ─────────────────────────────────────────── -->
        <form method="post" action="/AralPal/booking/confirm.php?booking_id=<?= (int) $booking_id ?>">
            <input type="hidden" name="csrf_token"  value="<?= e($csrf_token) ?>">
            <input type="hidden" name="booking_id"  value="<?= (int) $booking_id ?>">

            <div class="confirm-card__actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    Confirm &amp; Notify
                </button>
                <a href="/AralPal/booking/my-bookings.php" class="btn btn-outline">
                    My Bookings
                </a>
            </div>
        </form>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
