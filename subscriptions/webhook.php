<?php
/**
 * AralPal – PayMongo Webhook Handler
 *
 * Receives server-to-server event notifications from PayMongo.
 * This endpoint handles subscription activation reliably regardless of
 * whether the user's browser successfully returned to success.php.
 *
 * Register this URL in your PayMongo dashboard:
 *   https://yourdomain.com/AralPal/subscriptions/webhook.php
 *
 * Events handled:
 *   link.payment.paid  – a hosted payment link was paid
 *
 * Security:
 *   Every request is validated using HMAC-SHA256 (validate_webhook_signature).
 *   Requests without a valid signature are rejected with HTTP 401.
 *   Replay attacks older than 300 seconds are rejected.
 *
 * IMPORTANT: Do NOT require auth.php here — PayMongo calls this server-to-server
 * with no user session. Session functions must not be started.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paymongo.php';
require_once __DIR__ . '/../includes/mailer.php';

// Webhooks are POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── Read and validate signature ───────────────────────────────────────────────
$raw_body  = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (!validate_webhook_signature((string) $raw_body, $sig_header)) {
    http_response_code(401);
    error_log('AralPal: PayMongo webhook signature validation failed.');
    exit;
}

// ── Parse event ───────────────────────────────────────────────────────────────
$event = json_decode((string) $raw_body, true);
if (!is_array($event)) {
    http_response_code(400);
    exit;
}

$event_type = $event['data']['attributes']['type'] ?? '';

// ── Handle link.payment.paid ──────────────────────────────────────────────────
if ($event_type === 'link.payment.paid') {
    $link_data   = $event['data']['attributes']['data'] ?? [];
    $link_id     = $link_data['id'] ?? '';
    $pm_status   = $link_data['attributes']['status'] ?? '';
    $pm_amount   = (int) ($link_data['attributes']['amount'] ?? 0); // centavos

    if ($pm_status !== 'paid' || $link_id === '') {
        // Not paid yet or malformed — acknowledge receipt so PayMongo stops retrying
        http_response_code(200);
        exit;
    }

    $db = get_db();

    // Look up the pending payment by PayMongo link ID (stored as reference_no)
    $stmt = $db->prepare(
        "SELECT id AS payment_id, user_id, plan_id, amount
         FROM payments
         WHERE reference_no = ? AND status = 'pending'
         LIMIT 1"
    );
    $stmt->execute([$link_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        // Already processed or not found — idempotent, return 200
        http_response_code(200);
        exit;
    }

    $payment_id = (int) $payment['payment_id'];
    $user_id    = (int) $payment['user_id'];
    $plan_id    = (int) $payment['plan_id'];

    // Verify amount matches what we recorded
    $expected_centavos = (int) round((float) $payment['amount'] * 100);
    if ($pm_amount !== $expected_centavos) {
        error_log(sprintf(
            'AralPal webhook: Amount mismatch for payment %d. Expected %d centavos, got %d.',
            $payment_id, $expected_centavos, $pm_amount
        ));
        // Do not activate — alert support; return 200 to stop PayMongo retries
        http_response_code(200);
        exit;
    }

    // ── Activate subscription (same logic as success.php, deduplication via lock) ──
    $db->beginTransaction();
    try {
        $lock_stmt = $db->prepare(
            "SELECT id FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE"
        );
        $lock_stmt->execute([$payment_id]);
        if (!$lock_stmt->fetch()) {
            // Already processed by success.php or a previous webhook delivery
            $db->rollBack();
            http_response_code(200);
            exit;
        }

        $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?")
           ->execute([$payment_id]);

        $ins = $db->prepare(
            "INSERT INTO subscriptions
                (user_id, plan_id, start_date, end_date, status, created_at)
             VALUES
                (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active', NOW())"
        );
        $ins->execute([$user_id, $plan_id]);
        $new_sub_id = (int) $db->lastInsertId();

        $db->prepare("UPDATE payments SET subscription_id = ? WHERE id = ?")
           ->execute([$new_sub_id, $payment_id]);

        $db->commit();

        // Send receipt email
        $plan_stmt = $db->prepare('SELECT plan_name FROM subscription_plans WHERE id = ? LIMIT 1');
        $plan_stmt->execute([$plan_id]);
        $plan_row = $plan_stmt->fetch();

        $user_stmt = $db->prepare('SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
        $user_stmt->execute([$user_id]);
        $user_row = $user_stmt->fetch();

        if ($user_row && $plan_row) {
            $email_body = '
                <h2>Payment Receipt – AralPal</h2>
                <p>Dear ' . htmlspecialchars($user_row['full_name'], ENT_QUOTES, 'UTF-8') . ',</p>
                <p>Your subscription has been activated successfully.</p>
                <table cellpadding="8" cellspacing="0">
                    <tr><th align="left">Plan</th><td>' . htmlspecialchars($plan_row['plan_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th align="left">Amount Paid</th><td>₱' . number_format((float) $payment['amount'], 2) . '</td></tr>
                    <tr><th align="left">Reference</th><td>' . htmlspecialchars($link_id, ENT_QUOTES, 'UTF-8') . '</td></tr>
                </table>
                <p>Thank you for subscribing to AralPal!</p>
            ';
            send_mail($user_row['email'], 'AralPal – Subscription Receipt', $email_body);
        }

    } catch (\Throwable $e) {
        $db->rollBack();
        error_log('AralPal webhook activation error: ' . $e->getMessage());
        // Return 500 so PayMongo retries delivery
        http_response_code(500);
        exit;
    }
}

// Always return 200 for recognised events so PayMongo stops retrying
http_response_code(200);
exit;
