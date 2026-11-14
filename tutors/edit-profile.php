<?php
/**
 * AralPal – Tutor Edit Profile
 *
 * Allows a logged-in Tutor to update their bio, hourly rate,
 * subjects, and weekly availability slots.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
require_role('Tutor');

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Load current tutor row ─────────────────────────────────────────────────
$stmt = $db->prepare('SELECT id, bio, hourly_rate FROM tutors WHERE user_id = ? LIMIT 1');
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->execute();
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(403);
    exit('Tutor profile not found. Please contact support.');
}

$tutor_id = (int) $tutor['id'];

// ── Load all subjects ──────────────────────────────────────────────────────
$all_subjects = $db->query('SELECT id, subject_name FROM subjects ORDER BY subject_name')->fetchAll();

// ── Load currently selected subjects ──────────────────────────────────────
$sel_stmt = $db->prepare('SELECT subject_id FROM tutor_subjects WHERE tutor_id = ?');
$sel_stmt->bindValue(1, $tutor_id, PDO::PARAM_INT);
$sel_stmt->execute();
$selected_subjects = array_column($sel_stmt->fetchAll(), 'subject_id');

// ── Load existing availability slots ──────────────────────────────────────
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

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// ── Handle POST ────────────────────────────────────────────────────────────
$errors   = [];
$saved    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token. Please reload and try again.';
    }

    if (empty($errors)) {

        $bio         = trim($_POST['bio'] ?? '');
        $hourly_rate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);
        $subj_ids    = isset($_POST['subjects']) && is_array($_POST['subjects'])
                       ? array_map('intval', $_POST['subjects'])
                       : [];

        $slot_days   = isset($_POST['slot_day'])   && is_array($_POST['slot_day'])   ? $_POST['slot_day']   : [];
        $slot_starts = isset($_POST['slot_start']) && is_array($_POST['slot_start']) ? $_POST['slot_start'] : [];
        $slot_ends   = isset($_POST['slot_end'])   && is_array($_POST['slot_end'])   ? $_POST['slot_end']   : [];

        if ($hourly_rate === false || $hourly_rate < 0) {
            $errors[] = 'Hourly rate must be a non-negative number.';
        }

        // Validate availability slots
        $clean_slots = [];
        foreach ($slot_days as $idx => $raw_day) {
            $day   = trim($raw_day);
            $start = trim($slot_starts[$idx] ?? '');
            $end   = trim($slot_ends[$idx]   ?? '');

            if ($day === '' || $start === '' || $end === '') {
                continue; // skip blank rows
            }

            if (!in_array($day, $days, true)) {
                $errors[] = 'Invalid day: ' . htmlspecialchars($day, ENT_QUOTES, 'UTF-8');
                continue;
            }

            if ($start >= $end) {
                $errors[] = 'Start time must be before end time for ' . htmlspecialchars($day, ENT_QUOTES, 'UTF-8') . '.';
                continue;
            }

            $clean_slots[] = ['day' => $day, 'start' => $start, 'end' => $end];
        }

        if (empty($errors)) {

            // 2. Update tutors row
            $upd = $db->prepare('UPDATE tutors SET bio = ?, hourly_rate = ? WHERE user_id = ?');
            $upd->bindValue(1, $bio,         PDO::PARAM_STR);
            $upd->bindValue(2, $hourly_rate, PDO::PARAM_STR);
            $upd->bindValue(3, $user_id,     PDO::PARAM_INT);
            $upd->execute();

            // 3. Replace subjects
            $db->prepare('DELETE FROM tutor_subjects WHERE tutor_id = ?')
               ->execute([$tutor_id]);

            if ($subj_ids) {
                $ins_subj = $db->prepare('INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)');
                foreach ($subj_ids as $sid) {
                    if ($sid > 0) {
                        $ins_subj->bindValue(1, $tutor_id, PDO::PARAM_INT);
                        $ins_subj->bindValue(2, $sid,      PDO::PARAM_INT);
                        $ins_subj->execute();
                    }
                }
            }

            // 4. Replace availability slots
            $db->prepare('DELETE FROM availability WHERE tutor_id = ?')
               ->execute([$tutor_id]);

            if ($clean_slots) {
                $ins_av = $db->prepare('
                    INSERT INTO availability (tutor_id, day_of_week, start_time, end_time)
                    VALUES (?, ?, ?, ?)
                ');
                foreach ($clean_slots as $slot) {
                    $ins_av->bindValue(1, $tutor_id,    PDO::PARAM_INT);
                    $ins_av->bindValue(2, $slot['day'],  PDO::PARAM_STR);
                    $ins_av->bindValue(3, $slot['start'],PDO::PARAM_STR);
                    $ins_av->bindValue(4, $slot['end'],  PDO::PARAM_STR);
                    $ins_av->execute();
                }
            }

            // 5. Redirect with flash
            header('Location: /AralPal/tutors/edit-profile.php?saved=1');
            exit;
        }
    }

    // Re-load values from POST so the form is sticky on error
    $tutor['bio']         = trim($_POST['bio'] ?? '');
    $tutor['hourly_rate'] = $_POST['hourly_rate'] ?? '';
    $selected_subjects    = $subj_ids ?? [];
}

$show_saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$page_title = 'Edit Profile';
$csrf       = generate_csrf();

require_once __DIR__ . '/../includes/app-header.php';
?>

<main class="container">

    <div class="page-heading">
        <h1>Edit Your Profile</h1>
        <p>Keep your information up to date so students can find you.</p>
    </div>

    <?php if ($show_saved): ?>
        <div class="alert alert-success" role="alert">
            ✅ Profile saved successfully.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="profile-form" method="post" action="/AralPal/tutors/edit-profile.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <!-- ── Bio ─────────────────────────────────────────────── -->
        <div class="form-group">
            <label for="bio">Bio</label>
            <textarea
                id="bio"
                name="bio"
                rows="6"
                placeholder="Tell students about your background, teaching style, and experience…"
            ><?= htmlspecialchars($tutor['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- ── Hourly Rate ──────────────────────────────────────── -->
        <div class="form-group">
            <label for="hourly_rate">Hourly Rate (₱)</label>
            <input
                type="number"
                id="hourly_rate"
                name="hourly_rate"
                min="0"
                step="0.01"
                required
                value="<?= htmlspecialchars((string) ($tutor['hourly_rate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <!-- ── Subjects ─────────────────────────────────────────── -->
        <fieldset class="form-fieldset">
            <legend>Subjects You Teach</legend>
            <div class="checkbox-grid">
                <?php foreach ($all_subjects as $subj): ?>
                    <label class="checkbox-label">
                        <input
                            type="checkbox"
                            name="subjects[]"
                            value="<?= (int) $subj['id'] ?>"
                            <?= in_array((int) $subj['id'], array_map('intval', $selected_subjects), true) ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars($subj['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- ── Availability ─────────────────────────────────────── -->
        <fieldset class="form-fieldset">
            <legend>Weekly Availability</legend>

            <table class="availability-edit-table" id="availabilityTable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody id="availabilityBody">
                    <?php if ($availability): ?>
                        <?php foreach ($availability as $slot): ?>
                            <tr class="avail-row">
                                <td>
                                    <select name="slot_day[]" required>
                                        <?php foreach ($days as $day): ?>
                                            <option
                                                value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $slot['day_of_week'] === $day ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        type="time"
                                        name="slot_start[]"
                                        value="<?= htmlspecialchars($slot['start_time'], ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </td>
                                <td>
                                    <input
                                        type="time"
                                        name="slot_end[]"
                                        value="<?= htmlspecialchars($slot['end_time'], ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-danger btn--sm remove-slot"
                                        aria-label="Remove this slot"
                                    >✕</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="btn btn-outline btn--sm" id="addSlotBtn">
                + Add Availability Slot
            </button>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Profile</button>
            <a href="/AralPal/tutors/profile.php?id=<?= $tutor_id ?>" class="btn btn-outline">
                View Profile
            </a>
        </div>
    </form>

</main>

<!-- Availability row template (hidden) -->
<template id="availRowTemplate">
    <tr class="avail-row">
        <td>
            <select name="slot_day[]" required>
                <?php foreach ($days as $day): ?>
                    <option value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="time" name="slot_start[]" required>
        </td>
        <td>
            <input type="time" name="slot_end[]" required>
        </td>
        <td>
            <button
                type="button"
                class="btn btn-danger btn--sm remove-slot"
                aria-label="Remove this slot"
            >✕</button>
        </td>
    </tr>
</template>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>

<script>
/* ──────────────────────────────────────────────────────────────────
   Dynamic availability row management
   ────────────────────────────────────────────────────────────────── */
var tbody       = document.getElementById('availabilityBody');
var addSlotBtn  = document.getElementById('addSlotBtn');
var rowTemplate = document.getElementById('availRowTemplate');

function attachRemoveHandler(row) {
    row.querySelector('.remove-slot').addEventListener('click', function () {
        row.remove();
    });
}

// Attach to existing rows
document.querySelectorAll('.avail-row').forEach(attachRemoveHandler);

// Add new row
addSlotBtn.addEventListener('click', function () {
    var clone = rowTemplate.content.cloneNode(true);
    var row   = clone.querySelector('tr');
    attachRemoveHandler(row);
    tbody.appendChild(row);
    // Focus the day select of the new row
    row.querySelector('select').focus();
});
</script>
