<?php
/**
 * AralPal – My Bookings
 *
 * Students : view their bookings, leave reviews on completed ones.
 * Tutors   : view bookings assigned to them; Accept / Decline / Complete via AJAX.
 *
 * AJAX endpoint (POST with action=accept|decline|complete):
 *   Returns JSON { success: true, status: 'accepted' } or { success: false, error: '...' }
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
$role    = $current['role_name'] ?? '';

// ── AJAX handler ──────────────────────────────────────────────────────────────
// Tutor actions: POST with action=accept|decline|complete, booking_id, csrf_token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action     = $_POST['action'] ?? '';
    $bk_id      = (int) ($_POST['booking_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($csrf_token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    // Role guard — only Tutor accounts may change booking status
    if (($current['role_name'] ?? '') !== 'Tutor') {
        echo json_encode(['success' => false, 'error' => 'Not authorised.']);
        exit;
    }

    if (!in_array($action, ['accept', 'decline', 'complete'], true)) {
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
    }

    // Fetch tutor record for the logged-in user
    $tutor_stmt = $db->prepare('SELECT id FROM tutors WHERE user_id = ? LIMIT 1');
    $tutor_stmt->execute([$user_id]);
    $tutor_row = $tutor_stmt->fetch();

    if (!$tutor_row) {
        echo json_encode(['success' => false, 'error' => 'Not a tutor account.']);
        exit;
    }

    $tutor_id = (int) $tutor_row['id'];

    // Fetch booking and verify ownership
    $bk_stmt = $db->prepare('
        SELECT b.id, b.status, b.session_date, b.start_time, b.end_time,
               b.student_id,
               su.email      AS student_email,
               su.full_name  AS student_name,
               s.subject_name
        FROM bookings b
        JOIN users    su ON su.id = b.student_id
        JOIN subjects s  ON s.id  = b.subject_id
        WHERE b.id = ? AND b.tutor_id = ?
        LIMIT 1
    ');
    $bk_stmt->execute([$bk_id, $tutor_id]);
    $bk = $bk_stmt->fetch();

    if (!$bk) {
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit;
    }

    // Status transition rules
    $allowed_transitions = [
        'accept'   => ['pending'],
        'decline'  => ['pending'],
        'complete' => ['accepted'],
    ];

    if (!in_array($bk['status'], $allowed_transitions[$action], true)) {
        echo json_encode(['success' => false, 'error' => 'Action not allowed for current status.']);
        exit;
    }

    $new_status = match ($action) {
        'accept'   => 'accepted',
        'decline'  => 'declined',
        'complete' => 'completed',
    };

    // On accept: wrap availability re-check, conflict check, and UPDATE in a
    // transaction with SELECT FOR UPDATE so concurrent accept attempts on the
    // same booking (or on two bookings for the same slot) are serialised.
    if ($action === 'accept') {
        try {
            $db->beginTransaction();

            // Re-fetch booking inside transaction with a row lock
            $lock_bk = $db->prepare('
                SELECT id, status, session_date, start_time, end_time
                FROM bookings
                WHERE id = ? AND tutor_id = ?
                FOR UPDATE
            ');
            $lock_bk->execute([$bk_id, $tutor_id]);
            $fresh_bk = $lock_bk->fetch();

            if (!$fresh_bk || $fresh_bk['status'] !== 'pending') {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Action not allowed for current status.']);
                exit;
            }

            // Re-validate availability window (tutor may have changed schedule)
            $avail_check = $db->prepare('
                SELECT COUNT(*) FROM availability
                WHERE tutor_id   = ?
                  AND day_of_week = DAYNAME(?)
                  AND start_time <= ?
                  AND end_time   >= ?
            ');
            $session_end = date('H:i:s', strtotime($fresh_bk['start_time']) + 3600);
            $avail_check->execute([
                $tutor_id,
                $fresh_bk['session_date'],
                $fresh_bk['start_time'],
                $session_end,
            ]);
            if ((int) $avail_check->fetchColumn() === 0) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Your availability for this time slot has changed. Update your availability before accepting.']);
                exit;
            }

            // Lock any active booking rows for the same tutor/date/time range
            // to prevent accepting a slot already held by another booking.
            $conflict = $db->prepare('
                SELECT COUNT(*) FROM bookings
                WHERE tutor_id    = ?
                  AND session_date = ?
                  AND status       = \'accepted\'
                  AND id           != ?
                  AND start_time   < ?
                  AND end_time     > ?
                FOR UPDATE
            ');
            $conflict->execute([
                $tutor_id,
                $fresh_bk['session_date'],
                $bk_id,
                $session_end,
                $fresh_bk['start_time'],
            ]);
            if ((int) $conflict->fetchColumn() > 0) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'This time slot is already accepted for another booking. Please decline this one.']);
                exit;
            }

            // Enforce Tutor Starter concurrent-booking limit (5 active at once).
            // Tutor Pro has booking_limit = 0 (unlimited) so this check passes.
            $tutor_limit = get_tutor_booking_limit($user_id);
            if ($tutor_limit !== BOOKING_LIMIT_UNLIMITED) {
                $active_count = count_tutor_active_bookings($tutor_id);
                if ($active_count >= $tutor_limit) {
                    $db->rollBack();
                    echo json_encode([
                        'success' => false,
                        'error'   => 'You have reached your plan\'s limit of ' . $tutor_limit
                                     . ' active booking' . ($tutor_limit === 1 ? '' : 's')
                                     . '. Upgrade to Tutor Pro for unlimited bookings.',
                    ]);
                    exit;
                }
            }

            $db->prepare('UPDATE bookings SET status = \'accepted\' WHERE id = ?')
               ->execute([$bk_id]);
            $db->commit();

        } catch (PDOException $ex) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('AralPal booking accept error: ' . $ex->getMessage());
            echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
            exit;
        }

        // $new_status is already 'accepted' — fall through to notifications below
    } else {
        // decline / complete — simple update, no slot conflict possible
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')
           ->execute([$new_status, $bk_id]);
    }

    // Notification & email for student
    $date_str    = date('F j, Y', strtotime($bk['session_date']));
    $time_str    = date('g:i A', strtotime($bk['start_time'])) . ' – ' . date('g:i A', strtotime($bk['end_time']));
    $tutor_name  = htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8');

    $notif_messages = [
        'accept'   => "Your booking with {$tutor_name} on {$date_str} ({$bk['subject_name']}) has been accepted.",
        'decline'  => "Your booking with {$tutor_name} on {$date_str} ({$bk['subject_name']}) was declined.",
        'complete' => "Your session with {$tutor_name} on {$date_str} ({$bk['subject_name']}) has been marked complete. Please leave a review!",
    ];

    $notif_text = $notif_messages[$action];
    $notif_ins  = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notif_ins->execute([(int) $bk['student_id'], $notif_text]);

    $email_subjects = [
        'accept'   => 'Your AralPal booking has been accepted',
        'decline'  => 'Your AralPal booking was declined',
        'complete' => 'Session complete – leave a review on AralPal',
    ];

    $review_url = ($action === 'complete')
        ? SITE_URL . '/reviews/submit.php?booking_id=' . (int) $bk_id
        : '';

    // Email to student
    send_mail(
        $bk['student_email'],
        $email_subjects[$action],
        email_booking_status_changed($bk, $new_status, $current['full_name'], $review_url)
    );

    // When tutor accepts, also send the tutor a confirmation email
    if ($action === 'accept') {
        // Fetch tutor's email for confirmation
        $tutor_user_stmt = $db->prepare('SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
        $tutor_user_stmt->execute([$user_id]);
        $tutor_user = $tutor_user_stmt->fetch();
        if ($tutor_user) {
            // Build a booking array with tutor_name for the template
            $bk_for_tutor = array_merge($bk, ['tutor_name' => $current['full_name']]);
            send_mail(
                $tutor_user['email'],
                'You have accepted a booking on AralPal',
                email_tutor_accept_confirmation($bk_for_tutor, $bk['student_name'])
            );
        }
    }

    echo json_encode(['success' => true, 'status' => $new_status]);
    exit;
}

// ── Fetch bookings ────────────────────────────────────────────────────────────
$bookings = [];

if ($role === 'Tutor' || $role === 'tutor') {
    // Find the tutor record
    $ts = $db->prepare('SELECT id FROM tutors WHERE user_id = ? LIMIT 1');
    $ts->execute([$user_id]);
    $tutor_row = $ts->fetch();
    $tutor_id  = $tutor_row ? (int) $tutor_row['id'] : 0;

    if ($tutor_id) {
        $bk_stmt = $db->prepare('
            SELECT
                b.id,
                b.student_id,
                b.session_date,
                b.start_time,
                b.end_time,
                b.status,
                b.created_at,
                su.full_name  AS student_name,
                su.avatar     AS student_avatar,
                s.subject_name,
                (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS has_review
            FROM bookings b
            JOIN users    su ON su.id = b.student_id
            JOIN subjects s  ON s.id  = b.subject_id
            WHERE b.tutor_id = ?
            ORDER BY b.session_date DESC, b.start_time DESC
        ');
        $bk_stmt->execute([$tutor_id]);
        $bookings = $bk_stmt->fetchAll();
    }
} else {
    // Student view
    $bk_stmt = $db->prepare('
        SELECT
            b.id,
            b.tutor_id,
            b.session_date,
            b.start_time,
            b.end_time,
            b.status,
            b.created_at,
            tu.full_name  AS tutor_name,
            tu.avatar     AS tutor_avatar,
            t.id          AS t_tutor_id,
            s.subject_name,
            (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS has_review
        FROM bookings b
        JOIN tutors  t  ON t.id   = b.tutor_id
        JOIN users   tu ON tu.id  = t.user_id
        JOIN subjects s  ON s.id  = b.subject_id
        WHERE b.student_id = ?
        ORDER BY b.session_date DESC, b.start_time DESC
    ');
    $bk_stmt->execute([$user_id]);
    $bookings = $bk_stmt->fetchAll();
}

// ── Categorise bookings ───────────────────────────────────────────────────────
$today     = date('Y-m-d');
$upcoming  = [];
$past      = [];
$cancelled = [];

foreach ($bookings as $bk) {
    if (in_array($bk['status'], ['cancelled', 'declined'], true)) {
        $cancelled[] = $bk;
    } elseif ($bk['status'] === 'completed' || $bk['session_date'] < $today) {
        $past[] = $bk;
    } else {
        $upcoming[] = $bk;
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────
$flash_confirmed = isset($_GET['confirmed']);
$flash_cancelled = isset($_GET['cancelled']);

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title = 'My Bookings';
$csrf_token = generate_csrf();

$status_labels = [
    'pending'   => ['label' => 'Pending',   'class' => 'badge-warning'],
    'accepted'  => ['label' => 'Accepted',  'class' => 'badge-success'],
    'declined'  => ['label' => 'Declined',  'class' => 'badge-danger'],
    'completed' => ['label' => 'Completed', 'class' => 'badge-info'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-secondary'],
];

$is_tutor = ($role === 'Tutor' || $role === 'tutor');

require_once __DIR__ . '/../includes/app-header.php';
?>

<main class="container booking-container">

    <h1 class="page-title">My Bookings</h1>

    <!-- ── Flash messages ────────────────────────────────────────────── -->
    <?php if ($flash_confirmed): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle" aria-hidden="true"></i> Your booking has been confirmed. Emails have been sent to you and the tutor.
        </div>
    <?php endif; ?>
    <?php if ($flash_cancelled): ?>
        <div class="alert alert-success" role="alert">
            Your booking has been cancelled and the tutor has been notified.
        </div>
    <?php endif; ?>

    <!-- ── Tabs ──────────────────────────────────────────────────────── -->
    <div class="tabs" role="tablist">
        <button
            class="tab-btn tab-btn--active"
            role="tab"
            aria-selected="true"
            aria-controls="tab-upcoming"
            id="tab-btn-upcoming"
            data-tab="upcoming"
        >
            Upcoming <span class="tab-count"><?= count($upcoming) ?></span>
        </button>
        <button
            class="tab-btn"
            role="tab"
            aria-selected="false"
            aria-controls="tab-past"
            id="tab-btn-past"
            data-tab="past"
        >
            Past <span class="tab-count"><?= count($past) ?></span>
        </button>
        <button
            class="tab-btn"
            role="tab"
            aria-selected="false"
            aria-controls="tab-cancelled"
            id="tab-btn-cancelled"
            data-tab="cancelled"
        >
            Cancelled <span class="tab-count"><?= count($cancelled) ?></span>
        </button>
    </div>

    <!-- ── Upcoming tab ───────────────────────────────────────────────── -->
    <section
        id="tab-upcoming"
        role="tabpanel"
        aria-labelledby="tab-btn-upcoming"
        class="tab-panel tab-panel--active"
    >
        <?php if (empty($upcoming)): ?>
            <p class="muted tab-empty">No upcoming bookings.</p>
        <?php else: ?>
            <div class="bookings-list">
                <?php foreach ($upcoming as $bk): ?>
                    <?php echo render_booking_row($bk, $status_labels, $is_tutor, $csrf_token); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── Past tab ───────────────────────────────────────────────────── -->
    <section
        id="tab-past"
        role="tabpanel"
        aria-labelledby="tab-btn-past"
        class="tab-panel"
        hidden
    >
        <?php if (empty($past)): ?>
            <p class="muted tab-empty">No past bookings.</p>
        <?php else: ?>
            <div class="bookings-list">
                <?php foreach ($past as $bk): ?>
                    <?php echo render_booking_row($bk, $status_labels, $is_tutor, $csrf_token); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── Cancelled tab ──────────────────────────────────────────────── -->
    <section
        id="tab-cancelled"
        role="tabpanel"
        aria-labelledby="tab-btn-cancelled"
        class="tab-panel"
        hidden
    >
        <?php if (empty($cancelled)): ?>
            <p class="muted tab-empty">No cancelled or declined bookings.</p>
        <?php else: ?>
            <div class="bookings-list">
                <?php foreach ($cancelled as $bk): ?>
                    <?php echo render_booking_row($bk, $status_labels, $is_tutor, $csrf_token); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php
/**
 * Renders a single booking card row.
 * Kept as a local function to avoid repeating markup across three tab panels.
 */
function render_booking_row(array $bk, array $status_labels, bool $is_tutor, string $csrf_token): string
{
    $si    = $status_labels[$bk['status']] ?? ['label' => e($bk['status']), 'class' => 'badge-secondary'];
    $date  = e(date('F j, Y', strtotime($bk['session_date'])));
    $time  = e(date('g:i A', strtotime($bk['start_time']))) . ' – ' . e(date('g:i A', strtotime($bk['end_time'])));
    $bk_id = (int) $bk['id'];

    if ($is_tutor) {
        $person_name   = e($bk['student_name']);
        $person_avatar = htmlspecialchars($bk['student_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8');
        $person_label  = 'Student';
    } else {
        $person_name   = e($bk['tutor_name']);
        $person_avatar = htmlspecialchars($bk['tutor_avatar'] ?: '/AralPal/assets/avatar-default.png', ENT_QUOTES, 'UTF-8');
        $person_label  = 'Tutor';
    }

    $html  = '<article class="booking-card" id="booking-row-' . $bk_id . '" data-booking-id="' . $bk_id . '" data-status="' . e($bk['status']) . '">';
    $html .= '  <div class="booking-card__person">';
    $html .= '    <img class="booking-card__avatar" src="' . $person_avatar . '" alt="' . $person_name . '" loading="lazy" onerror="this.src=\'/AralPal/assets/avatar-default.png\'">';
    $html .= '    <div>';
    $html .= '      <strong>' . $person_name . '</strong>';
    $html .= '      <span class="role-badge">' . $person_label . '</span>';
    $html .= '    </div>';
    $html .= '  </div>';

    $html .= '  <div class="booking-card__details">';
    $html .= '    <p class="booking-card__subject">' . e($bk['subject_name']) . '</p>';
    $html .= '    <p class="booking-card__datetime"><i class="fas fa-calendar-alt" aria-hidden="true"></i> ' . $date . ' &nbsp; <i class="fas fa-clock" aria-hidden="true"></i> ' . $time . '</p>';
    $html .= '  </div>';

    $html .= '  <div class="booking-card__meta">';
    $html .= '    <span class="badge ' . e($si['class']) . ' booking-status-badge">' . e($si['label']) . '</span>';
    $html .= '  </div>';

    $html .= '  <div class="booking-card__actions">';

    // Student: cancel button for pending or accepted bookings
    if (!$is_tutor && in_array($bk['status'], ['pending', 'accepted'], true)) {
        $html .= '    <a href="/AralPal/booking/cancel.php?id=' . $bk_id . '" class="btn btn-sm btn-outline-danger">Cancel</a>';
    }

    // Student: leave review for completed without review
    if (!$is_tutor && $bk['status'] === 'completed' && !(int) $bk['has_review']) {
        $html .= '    <a href="/AralPal/reviews/submit.php?booking_id=' . $bk_id . '" class="btn btn-sm btn-primary">Leave Review</a>';
    }

    // Tutor action buttons (AJAX)
    if ($is_tutor) {
        if ($bk['status'] === 'pending') {
            $html .= '    <button class="btn btn-sm btn-success tutor-action-btn" data-action="accept" data-booking="' . $bk_id . '" data-csrf="' . e($csrf_token) . '">Accept</button>';
            $html .= '    <button class="btn btn-sm btn-outline-danger tutor-action-btn" data-action="decline" data-booking="' . $bk_id . '" data-csrf="' . e($csrf_token) . '">Decline</button>';
        }
        if ($bk['status'] === 'accepted') {
            $html .= '    <button class="btn btn-sm btn-info tutor-action-btn" data-action="complete" data-booking="' . $bk_id . '" data-csrf="' . e($csrf_token) . '">Mark Complete</button>';
        }
    }

    $html .= '  </div>';
    $html .= '</article>';

    return $html;
}
?>

<script>
(function () {
    'use strict';

    // ── Tab switching ─────────────────────────────────────────────────────
    var tabBtns   = document.querySelectorAll('.tab-btn');
    var tabPanels = document.querySelectorAll('.tab-panel');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-tab');

            tabBtns.forEach(function (b) {
                b.classList.remove('tab-btn--active');
                b.setAttribute('aria-selected', 'false');
            });
            tabPanels.forEach(function (p) {
                p.classList.remove('tab-panel--active');
                p.hidden = true;
            });

            btn.classList.add('tab-btn--active');
            btn.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('tab-' + target);
            if (panel) {
                panel.classList.add('tab-panel--active');
                panel.hidden = false;
            }
        });
    });

    // ── Tutor AJAX actions ────────────────────────────────────────────────
    var statusLabels = {
        accepted:  { label: 'Accepted',  cls: 'badge-success'   },
        declined:  { label: 'Declined',  cls: 'badge-danger'    },
        completed: { label: 'Completed', cls: 'badge-info'      },
    };

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tutor-action-btn');
        if (!btn) return;

        var action    = btn.getAttribute('data-action');
        var bookingId = btn.getAttribute('data-booking');
        var csrf      = btn.getAttribute('data-csrf');
        var row       = document.getElementById('booking-row-' + bookingId);

        btn.disabled = true;
        btn.textContent = 'Saving…';

        var formData = new URLSearchParams();
        formData.append('action',     action);
        formData.append('booking_id', bookingId);
        formData.append('csrf_token', csrf);

        fetch('/AralPal/booking/my-bookings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) {
                alert('Error: ' + (data.error || 'Unknown error.'));
                btn.disabled = false;
                btn.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                return;
            }

            var newStatus = data.status;
            var info      = statusLabels[newStatus] || { label: newStatus, cls: 'badge-secondary' };

            // Update status badge
            var badge = row.querySelector('.booking-status-badge');
            if (badge) {
                badge.className = 'badge ' + info.cls + ' booking-status-badge';
                badge.textContent = info.label;
            }

            // Update data-status
            row.setAttribute('data-status', newStatus);

            // Remove all action buttons from this row
            var actionsDiv = row.querySelector('.booking-card__actions');
            if (actionsDiv) {
                actionsDiv.querySelectorAll('.tutor-action-btn').forEach(function (b) { b.remove(); });
            }

            // If completed, add a review link for the student (displayed on student side)
            // On tutor side we just leave the completed badge.
        })
        .catch(function () {
            alert('Network error. Please try again.');
            btn.disabled = false;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
