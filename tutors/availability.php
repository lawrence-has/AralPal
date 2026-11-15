<?php
/**
 * AralPal – Tutor Manage Availability
 *
 * Allows the logged-in Tutor to add, edit, and remove their own weekly
 * availability slots. Validates for time-order and intra-day overlaps.
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

// Load tutor row
$stmt = $db->prepare('SELECT id FROM tutors WHERE user_id = ? LIMIT 1');
$stmt->execute([$user_id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(403);
    exit('Tutor profile not found.');
}

$tutor_id = (int) $tutor['id'];

$days   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$errors = [];
$saved  = false;

// ── Load existing slots ───────────────────────────────────────────────────
function load_slots(PDO $db, int $tutor_id): array {
    $stmt = $db->prepare('
        SELECT day_of_week, start_time, end_time
        FROM availability
        WHERE tutor_id = ?
        ORDER BY FIELD(day_of_week,
            \'Monday\',\'Tuesday\',\'Wednesday\',\'Thursday\',\'Friday\',\'Saturday\',\'Sunday\'
        ), start_time
    ');
    $stmt->execute([$tutor_id]);
    return $stmt->fetchAll();
}

$availability = load_slots($db, $tutor_id);

// ── POST handler ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid security token. Please reload and try again.';
    } else {
        $slot_days   = is_array($_POST['slot_day']   ?? null) ? $_POST['slot_day']   : [];
        $slot_starts = is_array($_POST['slot_start'] ?? null) ? $_POST['slot_start'] : [];
        $slot_ends   = is_array($_POST['slot_end']   ?? null) ? $_POST['slot_end']   : [];

        $clean = [];
        foreach ($slot_days as $i => $raw_day) {
            $day   = trim($raw_day);
            $start = trim($slot_starts[$i] ?? '');
            $end   = trim($slot_ends[$i]   ?? '');

            if ($day === '' && $start === '' && $end === '') continue; // blank row

            if (!in_array($day, $days, true)) {
                $errors[] = "Row " . ($i + 1) . ": invalid day.";
                continue;
            }
            if ($start === '' || $end === '') {
                $errors[] = "Row " . ($i + 1) . " ($day): start and end times are required.";
                continue;
            }
            if ($start >= $end) {
                $errors[] = "Row " . ($i + 1) . " ($day): start time must be before end time.";
                continue;
            }
            $clean[] = compact('day', 'start', 'end');
        }

        // Check for overlaps on the same day among submitted slots
        if (empty($errors)) {
            $byDay = [];
            foreach ($clean as $s) {
                $byDay[$s['day']][] = $s;
            }
            foreach ($byDay as $dayName => $slots) {
                usort($slots, fn($a, $b) => strcmp($a['start'], $b['start']));
                for ($x = 0; $x < count($slots) - 1; $x++) {
                    if ($slots[$x]['end'] > $slots[$x + 1]['start']) {
                        $errors[] = "Two slots on $dayName overlap: "
                            . $slots[$x]['start'] . "–" . $slots[$x]['end']
                            . " and "
                            . $slots[$x + 1]['start'] . "–" . $slots[$x + 1]['end'] . ".";
                    }
                }
            }
        }

        if (empty($errors)) {
            // Replace all slots for this tutor atomically.
            // Transaction ensures existing slots are preserved if any insert fails.
            try {
                $db->beginTransaction();

                $db->prepare('DELETE FROM availability WHERE tutor_id = ?')->execute([$tutor_id]);

                if ($clean) {
                    $ins = $db->prepare('INSERT INTO availability (tutor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
                    foreach ($clean as $slot) {
                        $ins->execute([$tutor_id, $slot['day'], $slot['start'], $slot['end']]);
                    }
                }

                $db->commit();
                header('Location: /AralPal/tutors/availability.php?saved=1');
                exit;

            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('AralPal: Availability save failed for tutor ' . $tutor_id . ': ' . $e->getMessage());
                $errors[] = 'A database error occurred while saving. Your schedule was not changed.';
            }
        }

        // On error keep submitted values
        $submitted = [];
        foreach ($slot_days as $i => $d) {
            $submitted[] = ['day_of_week' => $d, 'start_time' => $slot_starts[$i] ?? '', 'end_time' => $slot_ends[$i] ?? ''];
        }
        $availability = $submitted;
    }
}

$show_saved = isset($_GET['saved']);
$page_title = 'Manage Availability';
$csrf       = generate_csrf();
require_once __DIR__ . '/../includes/app-header.php';
?>
<style>
.avail-wrap { max-width: 760px; }
.avail-wrap h1 { font-size: 1.4rem; font-weight: 800; margin: 0 0 .4rem; }
.avail-wrap .avail-sub { color: var(--clr-muted); font-size: .9rem; margin: 0 0 1.5rem; }

.avail-table-wrap { overflow-x: auto; margin-bottom: 1rem; }
.avail-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.avail-table th { text-align: left; padding: .55rem .75rem; font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--clr-muted); border-bottom: 2px solid var(--clr-border); }
.avail-table td { padding: .5rem .75rem; border-bottom: 1px solid var(--clr-border); }
.avail-table tr:last-child td { border-bottom: none; }
.avail-table select, .avail-table input[type="time"] { padding: .4rem .6rem; border: 1px solid var(--clr-border); border-radius: var(--radius-md); background: var(--clr-surface); color: var(--clr-text); font-size: .88rem; }
.avail-table select { min-width: 120px; }
.btn-remove { background: none; border: 1px solid #fca5a5; color: #b91c1c; border-radius: var(--radius-md); padding: .3rem .7rem; font-size: .8rem; cursor: pointer; }
.btn-remove:hover { background: #fee2e2; }
.btn-add { background: none; border: 1px dashed var(--clr-border); color: var(--clr-primary); border-radius: var(--radius-md); padding: .5rem 1rem; font-size: .88rem; cursor: pointer; margin-bottom: 1rem; }
.btn-add:hover { background: var(--clr-bg); }

.avail-actions { display: flex; gap: .75rem; align-items: center; margin-top: 1rem; }
.btn-save { background: var(--clr-primary); color: #fff; border: none; border-radius: var(--radius-md); padding: .6rem 1.5rem; font-weight: 600; cursor: pointer; }
.btn-save:hover { opacity: .9; }

.avail-empty { padding: 2rem; text-align: center; color: var(--clr-muted); border: 1px dashed var(--clr-border); border-radius: var(--radius-md); margin-bottom: 1rem; }

.alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: var(--radius-md); padding: .75rem 1rem; margin-bottom: 1rem; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: var(--radius-md); padding: .75rem 1rem; margin-bottom: 1rem; }
.alert-error ul { margin: .4rem 0 0; padding-left: 1.25rem; font-size: .88rem; }
</style>

<div class="avail-wrap">
    <h1>Manage Availability</h1>
    <p class="avail-sub">Set the days and times when students can book sessions with you.</p>

    <?php if ($show_saved): ?>
        <div class="alert-success">✅ Availability saved successfully.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            Please fix the following errors:
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/AralPal/tutors/availability.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <?php if (empty($availability) && empty($errors)): ?>
            <div class="avail-empty" id="emptyState">
                No availability set yet. Click <strong>+ Add Slot</strong> to get started.
            </div>
        <?php endif; ?>

        <div class="avail-table-wrap">
            <table class="avail-table" id="availTable">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="availBody">
                    <?php foreach ($availability as $slot): ?>
                    <tr class="avail-row">
                        <td>
                            <select name="slot_day[]" required>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($slot['day_of_week'] === $d) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="time" name="slot_start[]" value="<?= htmlspecialchars(substr($slot['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>" required></td>
                        <td><input type="time" name="slot_end[]"   value="<?= htmlspecialchars(substr($slot['end_time'],   0, 5), ENT_QUOTES, 'UTF-8') ?>" required></td>
                        <td><button type="button" class="btn-remove" aria-label="Remove slot">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="button" class="btn-add" id="addSlotBtn">+ Add Slot</button>

        <div class="avail-actions">
            <button type="submit" class="btn-save">Save Availability</button>
            <a href="/AralPal/dashboard/tutor.php" style="font-size:.88rem;color:var(--clr-muted);text-decoration:none">Cancel</a>
        </div>
    </form>
</div>

<template id="slotRowTpl">
    <tr class="avail-row">
        <td>
            <select name="slot_day[]" required>
                <?php foreach ($days as $d): ?>
                    <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="time" name="slot_start[]" required></td>
        <td><input type="time" name="slot_end[]"   required></td>
        <td><button type="button" class="btn-remove" aria-label="Remove slot">✕</button></td>
    </tr>
</template>

<script>
(function () {
    var tbody   = document.getElementById('availBody');
    var addBtn  = document.getElementById('addSlotBtn');
    var tpl     = document.getElementById('slotRowTpl');
    var empty   = document.getElementById('emptyState');

    function attachRemove(row) {
        row.querySelector('.btn-remove').addEventListener('click', function () {
            row.remove();
            if (empty && tbody.querySelectorAll('.avail-row').length === 0) {
                empty.style.display = '';
            }
        });
    }

    document.querySelectorAll('.avail-row').forEach(attachRemove);

    addBtn.addEventListener('click', function () {
        if (empty) empty.style.display = 'none';
        var clone = tpl.content.cloneNode(true);
        var row   = clone.querySelector('tr');
        attachRemove(row);
        tbody.appendChild(row);
        row.querySelector('select').focus();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
