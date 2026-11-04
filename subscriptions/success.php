<?php
/**
 * AralPal – Payment Success Page
 *
 * Called after a user returns from the PayMongo hosted checkout.
 * Verifies the payment, activates the subscription, and emails a receipt.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

require_login();

$page_title = 'Payment Successful – AralPal';

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Resolve reference number ──────────────────────────────────────────────────
// PayMongo may append ?reference=... to the return URL, or we fall back to
// the session data stored before the redirect in checkout.php.
$reference = $_GET['reference']
    ?? $_SESSION['pending_payment']['reference_no']
    ?? null;

$subscription = null;
$error        = null;

if ($reference) {
    $reference = trim($reference);

    // ── Verify pending payment ────────────────────────────────────────────────
    $stmt = $db->prepare(
        "SELECT p.id AS payment_id, p.plan_id, p.amount
         FROM payments p
         WHERE p.reference_no = ? AND p.user_id = ? AND p.status = 'pending'
         LIMIT 1"
    );
    $stmt->execute([$reference, $user_id]);
    $payment = $stmt->fetch();

    if ($payment) {
        $payment_id = (int) $payment['payment_id'];
        $plan_id    = (int) $payment['plan_id'];

        // ── Begin transaction ─────────────────────────────────────────────────
        $db->beginTransaction();
        try {
            // Mark payment as paid
            $upd = $db->prepare(
                "UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?"
            );
            $upd->execute([$payment_id]);

            // Insert subscription (30-day access)
            $ins = $db->prepare(
                "INSERT INTO subscriptions
                    (user_id, plan_id, start_date, end_date, status, created_at)
                 VALUES
                    (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active', NOW())"
            );
            $ins->execute([$user_id, $plan_id]);

            $db->commit();

            // ── Fetch plan details for the receipt ───────────────────────────
            $plan_stmt = $db->prepare(
                'SELECT plan_name FROM subscription_plans WHERE id = ? LIMIT 1'
            );
            $plan_stmt->execute([$plan_id]);
            $plan = $plan_stmt->fetch();

            // ── Fetch end date for display ────────────────────────────────────
            $sub_stmt = $db->prepare(
                "SELECT start_date, end_date
                 FROM subscriptions
                 WHERE user_id = ? AND plan_id = ? AND status = 'active'
                 ORDER BY created_at DESC
                 LIMIT 1"
            );
            $sub_stmt->execute([$user_id, $plan_id]);
            $subscription = $sub_stmt->fetch();
            $subscription['plan_name'] = $plan['plan_name'] ?? 'Unknown';
            $subscription['amount']    = $payment['amount'];
            $subscription['reference'] = $reference;

            // ── Send receipt email ────────────────────────────────────────────
            $email_body = '
                <h2>Payment Receipt – AralPal</h2>
                <p>Dear ' . htmlspecialchars($current['full_name'], ENT_QUOTES, 'UTF-8') . ',</p>
                <p>Your subscription has been activated successfully.</p>
                <table cellpadding="8" cellspacing="0">
                    <tr><th align="left">Plan</th><td>' . htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th align="left">Amount Paid</th><td>₱' . htmlspecialchars(number_format((float) $subscription['amount'], 2), ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th align="left">Start Date</th><td>' . htmlspecialchars($subscription['start_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th align="left">End Date</th><td>' . htmlspecialchars($subscription['end_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th align="left">Reference</th><td>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</td></tr>
                </table>
                <p>Thank you for subscribing to AralPal!</p>
            ';
            send_mail(
                $current['email'],
                'AralPal – Subscription Receipt',
                $email_body
            );

            // ── Clear session payment data ────────────────────────────────────
            unset($_SESSION['pending_payment']);

        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('AralPal subscription activation error: ' . $e->getMessage());
            $error = 'An error occurred while activating your subscription. Please contact support.';
        }
    } else {
        $error = 'Payment could not be verified. It may have already been processed, or the reference is invalid.';
    }
} else {
    $error = 'No payment reference found. Please check your subscription history.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <?php if ($subscription): ?>

        <div class="success-banner" role="status">
            <span class="success-banner__icon" aria-hidden="true">✅</span>
            <h1>Payment Successful!</h1>
            <p>Your subscription is now active.</p>
        </div>

        <div class="receipt-card">
            <h2>Subscription Details</h2>
            <table class="receipt-table">
                <tr>
                    <th scope="row">Plan</th>
                    <td><?= htmlspecialchars($subscription['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th scope="row">Amount Paid</th>
                    <td>₱<?= htmlspecialchars(number_format((float) $subscription['amount'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th scope="row">Start Date</th>
                    <td><?= htmlspecialchars($subscription['start_date'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th scope="row">End Date</th>
                    <td><?= htmlspecialchars($subscription['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th scope="row">Reference No.</th>
                    <td><?= htmlspecialchars($subscription['reference'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            </table>

            <p class="receipt-email-note muted">A receipt has been sent to your email address.</p>

            <div class="receipt-actions">
                <a class="btn btn-primary" href="/AralPal/subscriptions/history.php">View Payment History</a>
                <a class="btn btn-outline"  href="/AralPal/subscriptions/plans.php">Back to Plans</a>
            </div>
        </div><!-- /.receipt-card -->

    <?php else: ?>

        <div class="alert alert--error" role="alert">
            <strong>Payment Verification Failed</strong><br>
            <?= htmlspecialchars($error ?? 'Unknown error.', ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="receipt-actions">
            <a class="btn btn-outline" href="/AralPal/subscriptions/history.php">View Payment History</a>
            <a class="btn btn-outline" href="/AralPal/subscriptions/plans.php">Back to Plans</a>
        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
