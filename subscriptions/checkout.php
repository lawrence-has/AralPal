<?php
/**
 * AralPal – Subscription Checkout Page
 *
 * GET  ?plan_id=N  – show the plan summary and a pay button.
 * POST             – create a PayMongo payment link and redirect the user.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paymongo.php';

require_login();

$page_title = 'Checkout – AralPal';

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Resolve plan_id from GET or POST ─────────────────────────────────────────
$plan_id = (int) ($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);

if ($plan_id <= 0) {
    http_response_code(400);
    exit('Invalid plan.');
}

// ── Fetch plan ────────────────────────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM subscription_plans WHERE id = ? LIMIT 1');
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    http_response_code(404);
    exit('Plan not found.');
}

$price = (float) $plan['price'];
if ($price <= 0) {
    // Free plans don't go through checkout
    header('Location: /AralPal/subscriptions/plans.php');
    exit;
}

// ── POST: initiate payment ────────────────────────────────────────────────────
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    // Validate plan_id
    $post_plan_id = (int) ($_POST['plan_id'] ?? 0);
    if ($post_plan_id !== $plan_id) {
        $error = 'Plan mismatch. Please try again.';
    } else {
        // Create PayMongo payment link
        $response = create_payment_link(
            $price,
            'AralPal - ' . $plan['plan_name'],
            ['user_id' => $user_id, 'plan_id' => $plan_id]
        );

        if ($response && isset($response['data']['attributes']['checkout_url'])) {
            $checkout_url  = $response['data']['attributes']['checkout_url'];
            $reference_no  = $response['data']['id'];

            // INSERT pending payment record
            $ins = $db->prepare(
                "INSERT INTO payments
                    (user_id, plan_id, amount, status, reference_no, created_at)
                 VALUES
                    (?, ?, ?, 'pending', ?, NOW())"
            );
            $ins->execute([$user_id, $plan_id, $price, $reference_no]);
            $payment_id = (int) $db->lastInsertId();

            // Store in session for success.php retrieval
            $_SESSION['pending_payment'] = [
                'payment_id'   => $payment_id,
                'plan_id'      => $plan_id,
                'reference_no' => $reference_no,
            ];

            // Redirect to PayMongo hosted checkout
            header('Location: ' . $checkout_url);
            exit;
        } else {
            $error = 'Payment gateway error. Please try again later.';
            error_log('AralPal checkout: PayMongo response: ' . json_encode($response));
        }
    }
}

// ── GET / error fallthrough: render page ─────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <div class="plans-page-header" style="text-align:left;padding-bottom:var(--sp-6)">
        <h1>Checkout</h1>
        <p>Review your order before proceeding to payment.</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert--error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="checkout-card">

        <h2 class="checkout-card__title">Order Summary</h2>

        <table class="checkout-summary">
            <tr>
                <th scope="row">Plan</th>
                <td><?= htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th scope="row">Price</th>
                <td>₱<?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?>/month</td>
            </tr>
        </table>

        <form method="POST" action="/AralPal/subscriptions/checkout.php?plan_id=<?= $plan_id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="plan_id"    value="<?= $plan_id ?>">

            <button type="submit" class="btn btn-primary btn--block">
                Pay ₱<?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?> via PayMongo
            </button>
        </form>

        <a class="btn btn-outline btn--block checkout-back" href="/AralPal/subscriptions/plans.php">
            ← Back to Plans
        </a>

    </div><!-- /.checkout-card -->

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
