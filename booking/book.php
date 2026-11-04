<?php
/**
 * AralPal – Book a Session
 *
 * GET:  Show booking form for a given tutor.
 * POST: Validate and create a pending booking, then redirect to confirm.php.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$db          = get_db();
$current     = current_user();
$student_uid = (int) $current['id'];

// ── Resolve tutor_id ──────────────────────────────────────────────────────────
$tutor_id = (int) ($_GET['tutor_id'] ?? ($_POST['tutor_id'] ?? 0));
if ($tutor_id <= 0) {
    http_response_code(400);
    exit('Invalid tutor.');
}

// ── Fetch tutor ───────────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT
        t.id          AS tutor_id,
        t.user_id,
        t.hourly_rate,
        u.full_name,
        u.email       AS tutor_email,
        u.avatar
    FROM tutors t
    JOIN users u ON u.id = t.user_id
    WHERE t.id = ?
    LIMIT 1
');
$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    exit('Tutor not found.');
}

// ── Fetch availability ────────────────────────────────────────────────────────
$avail_stmt = $db->prepare('
    SELECT id, day_of_week, start_time, end_time
    FROM availability
    WHERE tutor_id = ?
    ORDER BY FIELD(day_of_week,
        \'Monday\',\'Tuesday\',\'Wednesday\',\'Thursday\',\'Friday\',\'Saturday\',\'Sunday\'
    )
');
$avail_stmt->execute([$tutor_id]);
$availability = $avail_stmt->fetchAll();

// ── Fetch subjects ────────────────────────────────────────────────────────────
$subj_stmt = $db->prepare('
    SELECT s.id, s.subject_name
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.tutor_id = ?
    ORDER BY s.subject_name
');
$subj_stmt->execute([$tutor_id]);
$subjects = $subj_stmt->fetchAll();

// ── Build availability map for JS ─────────────────────────────────────────────
// keyed by day_of_week => array of {start_time, end_time}
$avail_map = [];
foreach ($availability as $row) {
    $day = $row['day_of_week'];
    if (!isset($avail_map[$day])) {
        $avail_map[$day] = [];
    }
    $avail_map[$day][] = [
        'start' => $row['start_time'],
        'end'   => $row['end_time'],
    ];
}

// PHP day index (0=Sun,1=Mon,...) => day name matching the ENUM
$php_day_map = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];

// Collect available day-of-week indices for the date-picker disabler
$available_dow = []; // JS numeric days that ARE available
foreach ($availability as $row) {
    foreach ($php_day_map as $num => $name) {
        if ($name === $row['day_of_week'] && !in_array($num, $available_dow, true)) {
            $available_dow[] = $num;
        }
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $post_subject_id  = (int) ($_POST['subject_id']   ?? 0);
    $post_session_date = trim($_POST['session_date']  ?? '');
    $post_start_time  = trim($_POST['start_time']     ?? '');

    if (empty($errors)) {
        // Validate subject belongs to this tutor
        $valid_subject = false;
        foreach ($subjects as $s) {
            if ((int) $s['id'] === $post_subject_id) {
                $valid_subject = true;
                break;
            }
        }
        if (!$valid_subject) {
            $errors[] = 'Invalid subject selected.';
        }

        // Validate date format
        $date_ts = strtotime($post_session_date);
        if ($date_ts === false || date('Y-m-d', $date_ts) !== $post_session_date) {
            $errors[] = 'Invalid session date.';
        }

        // Validate date is not in the past
        if ($date_ts !== false && $date_ts < strtotime('today')) {
            $errors[] = 'Session date must be today or in the future.';
        }

        // Validate time format HH:MM or HH:MM:SS
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $post_start_time)) {
            $errors[] = 'Invalid start time format.';
        }

        // Normalise to HH:MM:SS
        if (strlen($post_start_time) === 5) {
            $post_start_time .= ':00';
        }

        // Validate start_time is within availability for that day
        if (empty($errors)) {
            $dow = $php_day_map[(int) date('w', $date_ts)] ?? '';
            $in_window = false;
            if (isset($avail_map[$dow])) {
                foreach ($avail_map[$dow] as $window) {
                    $slot_start = strtotime($window['start']);
                    $slot_end   = strtotime($window['end']);
                    $req_start  = strtotime($post_start_time);
                    $req_end    = $req_start + 3600; // +1 hour
                    if ($req_start >= $slot_start && $req_end <= $slot_end) {
                        $in_window = true;
                        break;
                    }
                }
            }
            if (!$in_window) {
                $errors[] = 'Selected time slot is outside the tutor\'s availability.';
            }
        }

        // Compute end_time
        if (empty($errors)) {
            $end_ts   = strtotime($post_start_time) + 3600;
            $end_time = date('H:i:s', $end_ts);

            // Check for conflicting bookings
            $conflict_stmt = $db->prepare('
                SELECT COUNT(*) FROM bookings
                WHERE tutor_id = ?
                  AND session_date = ?
                  AND status NOT IN (\'cancelled\', \'declined\')
                  AND start_time < ?
                  AND end_time   > ?
            ');
            $conflict_stmt->execute([
                $tutor_id,
                $post_session_date,
                $end_time,
                $post_start_time,
            ]);
            if ((int) $conflict_stmt->fetchColumn() > 0) {
                $errors[] = 'That time slot is already booked. Please choose another.';
            }
        }

        // Insert booking
        if (empty($errors)) {
            $insert = $db->prepare('
                INSERT INTO bookings
                    (student_id, tutor_id, subject_id, session_date, start_time, end_time, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, \'pending\')
            ');
            $insert->execute([
                $student_uid,
                $tutor_id,
                $post_subject_id,
                $post_session_date,
                $post_start_time,
                $end_time,
            ]);
            $new_id = (int) $db->lastInsertId();

            header('Location: /AralPal/booking/confirm.php?booking_id=' . $new_id);
            exit;
        }
    }
}

// ── Page setup ────────────────────────────────────────────────────────────────
$page_title  = 'Book a Session – ' . htmlspecialchars($tutor['full_name'], ENT_QUOTES, 'UTF-8');
$csrf_token  = generate_csrf();
$avatar      = htmlspecialchars(
    $tutor['avatar'] ?: '/AralPal/assets/avatar-default.png',
    ENT_QUOTES, 'UTF-8'
);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container booking-container">

    <!-- ── Tutor header ──────────────────────────────────────────────────── -->
    <div class="booking-tutor-header">
        <img
            class="booking-tutor-avatar"
            src="<?= $avatar ?>"
            alt="<?= e($tutor['full_name']) ?>"
            onerror="this.src='/AralPal/assets/avatar-default.png'"
        >
        <div class="booking-tutor-info">
            <h1>Book a session with <?= e($tutor['full_name']) ?></h1>
            <p class="booking-tutor-rate">₱<?= e(number_format((float) $tutor['hourly_rate'], 2)) ?>/hr &middot; 1-hour sessions</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($subjects)): ?>
        <p class="muted">This tutor has not listed any subjects yet.</p>
    <?php elseif (empty($availability)): ?>
        <p class="muted">This tutor has not set any availability yet.</p>
    <?php else: ?>

    <!-- ── Booking form ──────────────────────────────────────────────────── -->
    <form class="booking-form card" method="post" action="/AralPal/booking/book.php?tutor_id=<?= (int) $tutor_id ?>">
        <input type="hidden" name="csrf_token"  value="<?= e($csrf_token) ?>">
        <input type="hidden" name="tutor_id"    value="<?= (int) $tutor_id ?>">

        <!-- Subject -->
        <div class="form-group">
            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id" required>
                <option value="">— Select a subject —</option>
                <?php foreach ($subjects as $s): ?>
                    <option
                        value="<?= (int) $s['id'] ?>"
                        <?= isset($_POST['subject_id']) && (int) $_POST['subject_id'] === (int) $s['id'] ? 'selected' : '' ?>
                    >
                        <?= e($s['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date picker -->
        <div class="form-group">
            <label for="session_date">Date</label>
            <input
                type="date"
                id="session_date"
                name="session_date"
                min="<?= date('Y-m-d') ?>"
                value="<?= e($_POST['session_date'] ?? '') ?>"
                required
                autocomplete="off"
            >
            <p class="form-hint" id="date-hint">Only available days are selectable.</p>
        </div>

        <!-- Time slot grid -->
        <div class="form-group">
            <label>Time Slot</label>
            <div id="time-slots-grid" class="time-slots-grid" role="radiogroup" aria-label="Available time slots">
                <p class="muted" id="slot-placeholder">Pick a date to see available time slots.</p>
            </div>
            <!-- Hidden fallback for server-side re-render after error -->
            <?php if (!empty($_POST['start_time'])): ?>
                <input type="hidden" name="start_time" id="start_time_hidden" value="<?= e($_POST['start_time']) ?>">
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" id="submit-btn" disabled>
            Confirm Booking
        </button>
    </form>

    <?php endif; ?>
</main>

<script>
(function () {
    'use strict';

    // Availability data from PHP
    var availMap = <?= json_encode($avail_map, JSON_HEX_TAG) ?>;
    var availDow = <?= json_encode($available_dow) ?>; // JS numeric dow available

    // PHP day index matches JS: 0=Sun,1=Mon,...
    var phpDayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    var dateInput  = document.getElementById('session_date');
    var gridEl     = document.getElementById('time-slots-grid');
    var submitBtn  = document.getElementById('submit-btn');
    var placeholder = document.getElementById('slot-placeholder');

    // ── Disable unavailable dates in the date picker ──────────────────────
    // The native <input type="date"> min/max only covers a range; to disable
    // individual weekdays we validate on the 'change' event and show a message.
    dateInput.addEventListener('change', function () {
        var val = dateInput.value;
        if (!val) {
            renderGrid(null);
            return;
        }
        // Parse as local date (split to avoid UTC offset issues)
        var parts = val.split('-');
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var dow = d.getDay(); // 0=Sun

        if (availDow.indexOf(dow) === -1) {
            dateInput.setCustomValidity('Tutor is not available on ' + phpDayNames[dow] + 's. Please pick another day.');
            dateInput.reportValidity();
            renderGrid(null);
        } else {
            dateInput.setCustomValidity('');
            renderGrid(phpDayNames[dow]);
        }
    });

    // ── Render time-slot grid for a given day name ────────────────────────
    function renderGrid(dayName) {
        gridEl.innerHTML = '';
        submitBtn.disabled = true;

        if (!dayName || !availMap[dayName]) {
            var p = document.createElement('p');
            p.className = 'muted';
            p.textContent = dayName
                ? 'No availability for ' + dayName + '.'
                : 'Pick a date to see available time slots.';
            gridEl.appendChild(p);
            return;
        }

        var windows = availMap[dayName];
        var slots   = [];

        windows.forEach(function (w) {
            var startH = parseTime(w.start);
            var endH   = parseTime(w.end);
            for (var t = startH; t + 60 <= endH; t += 60) {
                slots.push(t);
            }
        });

        if (slots.length === 0) {
            var p = document.createElement('p');
            p.className = 'muted';
            p.textContent = 'No 1-hour slots available for this day.';
            gridEl.appendChild(p);
            return;
        }

        var prevVal = (document.querySelector('input[name="start_time"]:checked') || {}).value
                   || (document.getElementById('start_time_hidden') || {}).value || '';

        slots.forEach(function (minutes) {
            var label    = document.createElement('label');
            label.className = 'time-slot-label';

            var radio    = document.createElement('input');
            radio.type   = 'radio';
            radio.name   = 'start_time';
            radio.value  = minutesToTime(minutes);
            if (prevVal && prevVal === radio.value) {
                radio.checked = true;
                submitBtn.disabled = false;
            }
            radio.addEventListener('change', function () {
                submitBtn.disabled = false;
            });

            var span     = document.createElement('span');
            span.textContent = formatSlotLabel(minutes);

            label.appendChild(radio);
            label.appendChild(span);
            gridEl.appendChild(label);
        });
    }

    // Parse "HH:MM" or "HH:MM:SS" into total minutes
    function parseTime(str) {
        var parts = str.split(':');
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }

    // Minutes -> "HH:MM:SS"
    function minutesToTime(m) {
        var h   = Math.floor(m / 60);
        var min = m % 60;
        return pad(h) + ':' + pad(min) + ':00';
    }

    // Minutes -> "8:00 AM – 9:00 AM"
    function formatSlotLabel(m) {
        return formatHour(m) + ' – ' + formatHour(m + 60);
    }

    function formatHour(m) {
        var h   = Math.floor(m / 60) % 24;
        var min = m % 60;
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12  = h % 12 || 12;
        return h12 + ':' + pad(min) + ' ' + ampm;
    }

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    // If form was re-rendered after POST error, restore slots
    var existingDate = dateInput.value;
    if (existingDate) {
        var parts = existingDate.split('-');
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var dow = d.getDay();
        if (availDow.indexOf(dow) !== -1) {
            renderGrid(phpDayNames[dow]);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
