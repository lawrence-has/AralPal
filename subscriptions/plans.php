<?php
/**
 * AralPal – Subscription Plans Page
 *
 * Shows plans filtered by the logged-in user's role.
 * Students see Student plans, Tutors see Tutor plans, Admins see all.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$page_title = 'Subscription Plans – AralPal';

$db      = get_db();
$current = current_user();
$role    = $current['role_name'] ?? '';
$user_id = (int) $current['id'];

// ── Fetch plans filtered by role ─────────────────────────────────────────────
if ($role === 'Admin') {
    $stmt = $db->query('SELECT * FROM subscription_plans ORDER BY price ASC');
} else {
    $stmt = $db->prepare(
        "SELECT * FROM subscription_plans WHERE user_type = ? ORDER BY price ASC"
    );
    $stmt->execute([$role]);
}
$plans = $stmt->fetchAll();

// ── Decode features JSON ──────────────────────────────────────────────────────
foreach ($plans as &$plan) {
    $plan['features_array'] = [];
    if (!empty($plan['features'])) {
        $decoded = json_decode($plan['features'], true);
        if (is_array($decoded)) {
            $plan['features_array'] = $decoded;
        }
    }
}
unset($plan);

// ── Fetch the user's active subscription plan IDs ────────────────────────────
$sub_stmt = $db->prepare(
    "SELECT plan_id FROM subscriptions
     WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()"
);
$sub_stmt->execute([$user_id]);
$active_plan_ids = $sub_stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
$active_plan_ids = array_map('intval', $active_plan_ids);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <div class="page-header">
        <h1>Subscription Plans</h1>
        <p class="page-subtitle">Choose the plan that fits your learning journey.</p>
    </div>

    <?php if (empty($plans)): ?>
        <p class="muted">No plans available for your account type.</p>
    <?php else: ?>
        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <?php
                $plan_id    = (int) $plan['id'];
                $price      = (float) $plan['price'];
                $is_free    = $price <= 0;
                $is_active  = in_array($plan_id, $active_plan_ids, true);
                ?>
                <div class="pricing-card <?= $is_active ? 'pricing-card--active' : '' ?>">

                    <?php if ($is_active): ?>
                        <span class="badge badge--active">Active</span>
                    <?php endif; ?>

                    <h2 class="pricing-card__name">
                        <?= htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <div class="pricing-card__price">
                        <?php if ($is_free): ?>
                            <span class="price-amount">Free</span>
                        <?php else: ?>
                            <span class="price-amount">
                                ₱<?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="price-period">/month</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($plan['features_array'])): ?>
                        <ul class="pricing-card__features">
                            <?php foreach ($plan['features_array'] as $feature): ?>
                                <li>
                                    <span class="feature-check" aria-hidden="true">✓</span>
                                    <?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="pricing-card__action">
                        <?php if ($is_free): ?>
                            <?php if ($is_active): ?>
                                <span class="btn btn-disabled" aria-disabled="true">Current Free Plan</span>
                            <?php else: ?>
                                <span class="btn btn-disabled" aria-disabled="true">Current Free Plan</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($is_active): ?>
                                <span class="btn btn-disabled" aria-disabled="true">Already Subscribed</span>
                            <?php else: ?>
                                <a
                                    class="btn btn-primary"
                                    href="/AralPal/subscriptions/checkout.php?plan_id=<?= $plan_id ?>"
                                >
                                    Subscribe
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                </div><!-- /.pricing-card -->
            <?php endforeach; ?>
        </div><!-- /.pricing-grid -->
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
