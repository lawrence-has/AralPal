<?php
/**
 * AralPal Admin – Subscription Plan Management
 *
 * Add new plans; inline-edit existing plans (name, user_type, price,
 * booking_limit, features). Validates price >= 0, booking_limit >= 0.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('Admin');

$db  = get_db();
$msg = '';
$err = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrf)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $action  = (string) ($_POST['action'] ?? '');
    $plan_id = (int) ($_POST['plan_id'] ?? 0);

    // Shared input parsing
    $plan_name     = trim((string) ($_POST['plan_name'] ?? ''));
    $user_type     = trim((string) ($_POST['user_type'] ?? ''));
    $price_raw     = trim((string) ($_POST['price'] ?? '0'));
    $limit_raw     = trim((string) ($_POST['booking_limit'] ?? '0'));
    $features_raw  = trim((string) ($_POST['features'] ?? ''));

    $price         = (float) $price_raw;
    $booking_limit = (int) $limit_raw;

    // Validate
    $valid_types = ['Student', 'Tutor', 'Admin'];

    if (in_array($action, ['add', 'edit'], true)) {
        if ($plan_name === '') {
            $err = 'Plan name is required.';
        } elseif (!in_array($user_type, $valid_types, true)) {
            $err = 'User type must be Student, Tutor, or Admin.';
        } elseif ($price < 0) {
            $err = 'Price must be 0 or greater.';
        } elseif ($booking_limit < 0) {
            $err = 'Booking limit must be 0 or greater.';
        }
    }

    if ($err === '') {
        if ($action === 'add') {
            $ins = $db->prepare(
                'INSERT INTO subscription_plans
                    (plan_name, user_type, price, booking_limit, features)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$plan_name, $user_type, $price, $booking_limit, $features_raw]);
            $msg = 'Plan added.';
        }

        if ($action === 'edit' && $plan_id > 0) {
            $upd = $db->prepare(
                'UPDATE subscription_plans
                 SET plan_name = ?, user_type = ?, price = ?, booking_limit = ?, features = ?
                 WHERE id = ?'
            );
            $upd->execute([$plan_name, $user_type, $price, $booking_limit, $features_raw, $plan_id]);
            $msg = 'Plan updated.';
        }
    }
}

// ── Fetch plans ───────────────────────────────────────────────────────────────
$stmt  = $db->query(
    'SELECT id, plan_name, user_type, price, booking_limit, features
     FROM subscription_plans
     ORDER BY user_type ASC, price ASC'
);
$plans = $stmt->fetchAll();

$csrf_token = generate_csrf();
$pageTitle  = 'Plan Management';
require __DIR__ . '/partials/header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">ADMIN / PLANS</p>
        <h1>Subscription Plan Management</h1>
        <p class="muted">Create and manage the subscription plans available to users.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Add New Plan -->
<section class="panel">
    <h2>Add New Plan</h2>
    <form method="post" action="plans-manage.php" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add">

        <label>
            Plan Name
            <input type="text" name="plan_name" required maxlength="120" placeholder="e.g. Basic">
        </label>

        <label>
            User Type
            <select name="user_type" required>
                <option value="">Select…</option>
                <option value="Student">Student</option>
                <option value="Tutor">Tutor</option>
                <option value="Admin">Admin</option>
            </select>
        </label>

        <label>
            Price (₱)
            <input type="number" name="price" required min="0" step="0.01" value="0">
        </label>

        <label>
            Booking Limit
            <input type="number" name="booking_limit" required min="0" step="1" value="0">
        </label>

        <label class="wide">
            Features (one per line or JSON array)
            <textarea name="features" rows="3" placeholder='e.g. ["Unlimited bookings","Priority support"]'></textarea>
        </label>

        <div class="form-actions">
            <button class="button" type="submit">Add Plan</button>
        </div>
    </form>
</section>

<!-- Existing Plans -->
<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">PLAN LIBRARY</p>
            <h2>All Plans <span class="count"><?= count($plans) ?></span></h2>
        </div>
    </div>

    <?php if (empty($plans)): ?>
        <p class="empty">No subscription plans yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Plan Name</th>
                        <th>User Type</th>
                        <th>Price (₱)</th>
                        <th>Booking Limit</th>
                        <th>Features</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><?= (int) $plan['id'] ?></td>
                        <td colspan="6">
                            <form method="post" action="plans-manage.php" class="inline-edit-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"  value="edit">
                                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">

                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-start">
                                    <input
                                        type="text"
                                        name="plan_name"
                                        required
                                        maxlength="120"
                                        value="<?= htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        style="width:140px"
                                        placeholder="Plan name"
                                    >
                                    <select name="user_type" required style="width:100px">
                                        <?php foreach (['Student', 'Tutor', 'Admin'] as $ut): ?>
                                            <option
                                                value="<?= htmlspecialchars($ut, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $plan['user_type'] === $ut ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($ut, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input
                                        type="number"
                                        name="price"
                                        required
                                        min="0"
                                        step="0.01"
                                        value="<?= htmlspecialchars(number_format((float) $plan['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                        style="width:90px"
                                        placeholder="Price"
                                    >
                                    <input
                                        type="number"
                                        name="booking_limit"
                                        required
                                        min="0"
                                        step="1"
                                        value="<?= (int) $plan['booking_limit'] ?>"
                                        style="width:90px"
                                        placeholder="Limit"
                                    >
                                    <textarea
                                        name="features"
                                        rows="2"
                                        style="width:200px"
                                        placeholder="JSON or one per line"
                                    ><?= htmlspecialchars($plan['features'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <button class="button" type="submit">Save</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
