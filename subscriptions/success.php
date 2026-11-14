<?php
/**
 * AralPal – Payment Success Page
 *
 * Called after a user returns from the PayMongo hosted checkout.
 * Verifies the payment, activates the subscription, and emails a receipt.
 * Supports mock=1 for local testing with placeholder API keys.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paymongo.php';
require_once __DIR__ . '/../includes/mailer.php';

require_login();

$page_title = 'Payment Successful – AralPal';

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

$subscription = null;
$error        = null;
$payment      = null;
$reference    = null;

// Mock mode is only valid when a placeholder API key is in use (local dev).
// A real secret key with ?mock=1 appended must NOT skip payment verification.
$is_mock = isset($_GET['mock'])
    && in_array(PAYMONGO_SECRET_KEY, PAYMONGO_PLACEHOLDER_KEYS, true);

// ── Resolve the pending payment row ──────────────────────────────────────────
// Priority 1: signed URL token (survives session loss on mobile / new tab)
// Priority 2: session fallback (belt-and-suspenders for same-browser flows)
// Priority 3: ?reference= query param (legacy / manual testing only)

$token_payment_id = null;
if (!empty($_GET['token'])) {
    $token_payment_id = verify_payment_token((string) $_GET['token']);
    if ($token_payment_id === null) {
        $error = 'Payment link has expired or is invalid. Please check your subscription history.';
    }
}

if (!$error) {
    if ($token_payment_id !== null) {
        // Token path — session-independent
        $stmt = $db->prepare(
            "SELECT p.id AS payment_id, p.plan_id, p.amount, p.reference_no
             FROM payments p
             WHERE p.id = ? AND p.user_id = ? AND p.status IN ('pending','paid')
             LIMIT 1"
        );
        $stmt->execute([$token_payment_id, $user_id]);
        $payment = $stmt->fetch();
        if ($payment) {
            $reference = $payment['reference_no'] ?? '';
        }
    } elseif ($is_mock) {
        // Mock path without token: fall back to session
        $pending = $_SESSION['pending_payment'] ?? null;
        if ($pending) {
            $reference = $pending['reference_no'];
            $stmt = $db->prepare(
                "SELECT p.id AS payment_id, p.plan_id, p.amount, p.reference_no
                 FROM payments p
                 WHERE p.id = ? AND p.user_id = ? AND p.status IN ('pending','paid')
                 LIMIT 1"
            );
            $stmt->execute([(int) $pending['payment_id'], $user_id]);
            $payment = $stmt->fetch();
        }
        if (!$payment) {
            $error = 'Mock payment record not found. Please try again.';
        }
    } else {
        // Real path without token: try session, then ?reference= param
        $reference = trim(
            $_SESSION['pending_payment']['reference_no']
            ?? $_GET['reference']
            ?? ''
        );
        if ($reference !== '') {
            $stmt = $db->prepare(
                "SELECT p.id AS payment_id, p.plan_id, p.amount, p.reference_no
                 FROM payments p
                 WHERE p.reference_no = ? AND p.user_id = ? AND p.status IN ('pending','paid')
                 LIMIT 1"
            );
            $stmt->execute([$reference, $user_id]);
            $payment = $stmt->fetch();
            if (!$payment) {
                $error = 'Payment could not be verified. It may have already been processed, or the reference is invalid.';
            }
        } else {
            $error = 'No payment reference found. Please check your subscription history.';
        }
    }
}

// ── Server-side PayMongo API verification ────────────────────────────────────
// For non-mock flows with a real reference, confirm PayMongo reports 'paid'
// and that the amount matches what we recorded locally.
if ($payment && !$error && !$is_mock && $reference !== '') {
    $link_data = retrieve_payment_link($reference);

    if (!$link_data) {
        $error = 'Could not reach the payment gateway to verify your payment. Please try again or contact support.';
    } else {
        $pm_status = $link_data['data']['attributes']['status'] ?? '';
        $pm_amount = (int) ($link_data['data']['attributes']['amount'] ?? 0); // centavos

        if ($pm_status === 'expired') {
            // Mark local row as failed so it doesn't clog up history as 'pending'
            $db->prepare("UPDATE payments SET status = 'failed' WHERE id = ? AND status = 'pending'")
               ->execute([(int) $payment['payment_id']]);
            $error = 'This payment link has expired. Please start a new checkout.';
        } elseif ($pm_status !== 'paid') {
            $error = 'Payment has not been completed. Status: ' . htmlspecialchars($pm_status, ENT_QUOTES, 'UTF-8') . '. Please complete your payment or try again.';
        } else {
            // Verify the amount matches — prevents plan-switching attacks
            $expected_centavos = (int) round((float) $payment['amount'] * 100);
            if ($pm_amount !== $expected_centavos) {
                error_log(sprintf(
                    'AralPal: Amount mismatch for payment %d. Expected %d centavos, PayMongo reports %d.',
                    (int) $payment['payment_id'], $expected_centavos, $pm_amount
                ));
                $error = 'Payment amount mismatch. Please contact support.';
            }
        }
    }
}

// ── Activate subscription (shared by both paths) ──────────────────────────────
if ($payment && !$error) {
    $payment_id = (int) $payment['payment_id'];
    $plan_id    = (int) $payment['plan_id'];

    $db->beginTransaction();
    try {
        // Re-check status inside the transaction with a row lock to prevent
        // double-activation from a race condition (double-click / browser retry).
        $lock_stmt = $db->prepare(
            "SELECT id FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE"
        );
        $lock_stmt->execute([$payment_id]);
        if (!$lock_stmt->fetch()) {
            // Another request already processed this payment — show success quietly.
            $db->rollBack();
            $sub_stmt = $db->prepare(
                "SELECT s.start_date, s.end_date, sp.plan_name
                 FROM subscriptions s
                 JOIN subscription_plans sp ON sp.id = s.plan_id
                 WHERE s.user_id = ? AND s.plan_id = ? AND s.status = 'active'
                 ORDER BY s.created_at DESC LIMIT 1"
            );
            $sub_stmt->execute([$user_id, $plan_id]);
            $subscription = $sub_stmt->fetch() ?: null;
            if ($subscription) {
                $subscription['amount']    = $payment['amount'];
                $subscription['reference'] = $reference;
            }
            unset($_SESSION['pending_payment']);
            goto render;
        }

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
        $new_sub_id = (int) $db->lastInsertId();

        // Link the payment row back to the subscription it created
        $db->prepare("UPDATE payments SET subscription_id = ? WHERE id = ?")
           ->execute([$new_sub_id, $payment_id]);

        $db->commit();

        // ── Fetch plan details for the receipt ───────────────────────────────
        $plan_stmt = $db->prepare(
            'SELECT plan_name FROM subscription_plans WHERE id = ? LIMIT 1'
        );
        $plan_stmt->execute([$plan_id]);
        $plan = $plan_stmt->fetch();

        // ── Fetch subscription dates for display ─────────────────────────────
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

        // ── Send receipt email ────────────────────────────────────────────────
        $email_body = '
            <h2>Payment Receipt – AralPal</h2>
            <p>Dear ' . htmlspecialchars($current['full_name'] ?? $current['email'], ENT_QUOTES, 'UTF-8') . ',</p>
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

        // ── Clear session payment data ────────────────────────────────────────
        unset($_SESSION['pending_payment']);

    } catch (\Throwable $e) {
        $db->rollBack();
        error_log('AralPal subscription activation error: ' . $e->getMessage());
        $error = 'An error occurred while activating your subscription. Please contact support.';
    }
}

render:
require_once __DIR__ . '/../includes/app-header.php';
?>

<main class="container">

    <?php if ($subscription): ?>

        <div class="success-banner" role="status">
            <span class="success-banner__icon" aria-hidden="true">
                <i class="fas fa-circle-check"></i>
            </span>
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

<?php require_once __DIR__ . '/../includes/app-footer.php'; ?>
