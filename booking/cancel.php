<?php
/**
 * AralPal – Cancel a Booking
 *
 * GET:  Show confirmation prompt.
 * POST: Cancel the booking, email tutor, add notification, redirect.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

require_login();

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Resolve booking id ────────────────────────────────────────────────────────
$booking_id = (int) ($_GET['id'] ?? ($_POST['booking_id'] ?? 0));
if ($booking_id <= 0) {
    http_response_code(400);
    exit('Invalid booking.');
}

// ── Fetch booking ─────────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT
        b.id,
        b.student_id,
        b.tutor_id,
        b.session_date,
        b.start_time,
        b.end_time,
        b.status,
        tu.full_name  AS tutor_name,
        tu.email      AS tutor_email,
        t.user_id     AS tutor_user_id,
        su.full_name  AS student_name,
        s.subject_name
    FROM bookings b
    JOIN tutors  t  ON t.id   = b.tutor_id
    JOIN users   tu ON tu.id  = t.user_id
    JOIN users   su ON su.id  = b.student_id
    JOIN subjects s ON s.id   = b.subject_id
    WHERE b.id = ?
    LIMIT 1
');
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

// Ownership check
if ((int) $booking['student_id'] !== $user_id) {
    http_response_code(403);
    exit('Access denied.');
}

// Only pending bookings may be cancelled via this page
if ($booking['status'] !== 'pending') {
    flash('error', 'Only pending bookings can be cancelled.');
    header('Location: /AralPal/booking/my-bookings.php');
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Invalid security token. Please try again.';
    }

    // Re-verify ownership & status from DB (race-condition safety)
    if ($error === null) {
        $chk = $db->prepare('SELECT status, session_date FROM bookings WHERE id = ? AND student_id = ?');
        $chk->execute([$booking_id, $user_id]);
        $fresh = $chk->fetch();

        if (!$fresh) {
            $error = 'Booking not found.';
        } elseif ($fresh['status'] !== 'pending') {
            $error = 'This booking can no longer be cancelled.';
        } elseif (strtotime($fresh['session_date']) <= strtotime('now')) {
            $error = 'Cannot cancel a booking whose session date has already passed.';
        }
    }

    if ($error === null) {
        // Update status
        $upd = $db->prepare('UPDATE bookings SET status = \'cancelled\' WHERE id = ?');
        $upd->execute([$booking_id]);

        // Email tutor
        $tutor_subject = 'A booking has been cancelled – AralPal';
        $tutor_body    = '
            <p>Hi ' . htmlspecialchars($booking['tutor_name'], ENT_QUOTES, 'UTF-8') . ',</p>
            <p>A student has cancelled their booking:</p>
            <table cellpadding="8" cellspacing="0" border="0">
                <tr><td><strong>Student:</strong></td><td>' . htmlspecialchars($booking['student_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td><strong>Subject:</strong></td><td>' . htmlspecialchars($booking['subject_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td><strong>Date:</strong></td><td>' . htmlspecialchars(date('F j, Y', strtotime($booking['session_date'])), ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td><strong>Time:</strong></td><td>' . htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8') . ' – ' . htmlspecialchars(date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8') . '</td></tr>
            </table>
            <p>– The AralPal Team</p>
        ';
        send_mail($booking['tutor_email'], $tutor_subject, $tutor_body);

        // In-app notification for tutor
        $notif_msg = htmlspecialchars(
            $booking['student_name'] . ' cancelled their booking for '
            . date('F j, Y', strtotime($booking['session_date']))
            . ' (' . $booking['subject_name'] . ').',
            ENT_QUOTES, 'UTF-8'
        );
        $notif = $db->prepare('
            INSERT INTO notifications (user_id, message) VALUES (?, ?)
        ');
        $notif->execute([(int) $booking['tutor_user_id'], $notif_msg]);

        header('Location: /AralPal/booking/my-bookings.php?cancelled=1');
        exit;
    }
}

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title = 'Cancel Booking';
$csrf_token = generate_csrf();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container booking-container">

    <div class="card cancel-card">
        <div class="cancel-card__icon" aria-hidden="true">⚠️</div>
        <h1 class="cancel-card__title">Cancel Booking?</h1>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="booking-summary">
            <table class="booking-summary__table">
                <tbody>
                    <tr>
                        <th scope="row">Tutor</th>
                        <td><?= e($booking['tutor_name']) ?></td>
                    </tr>
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
                </tbody>
            </table>
        </div>

        <p class="cancel-card__warning">
            This action cannot be undone. The tutor will be notified of the cancellation.
        </p>

        <form method="post" action="/AralPal/booking/cancel.php?id=<?= (int) $booking_id ?>">
            <input type="hidden" name="csrf_token"  value="<?= e($csrf_token) ?>">
            <input type="hidden" name="booking_id"  value="<?= (int) $booking_id ?>">

            <div class="cancel-card__actions">
                <button type="submit" class="btn btn-danger">
                    Yes, Cancel Booking
                </button>
                <a href="/AralPal/booking/my-bookings.php" class="btn btn-outline">
                    Keep Booking
                </a>
            </div>
        </form>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
