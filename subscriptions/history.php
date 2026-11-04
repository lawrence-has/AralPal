<?php
/**
 * AralPal – Payment History Page
 *
 * Lists all payments made by the logged-in user.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$page_title = 'Payment History – AralPal';

$db      = get_db();
$current = current_user();
$user_id = (int) $current['id'];

// ── Fetch payments joined with plan names ─────────────────────────────────────
$stmt = $db->prepare(
    "SELECT
        p.id              AS payment_id,
        p.amount,
        p.status,
        p.reference_no,
        p.payment_method,
        p.paid_at,
        p.created_at,
        sp.plan_name
     FROM payments p
     JOIN subscription_plans sp ON sp.id = p.plan_id
     WHERE p.user_id = ?
     ORDER BY p.paid_at DESC, p.created_at DESC"
);
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <div class="page-header">
        <h1>Payment History</h1>
        <p class="page-subtitle">All subscription payments linked to your account.</p>
    </div>

    <?php if (empty($payments)): ?>
        <p class="muted">No payment records found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Plan</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Method</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $row): ?>
                        <?php
                        $display_date = $row['paid_at'] ?? $row['created_at'];
                        $ts           = strtotime($display_date);
                        $date_str     = $ts ? date('M j, Y', $ts) : htmlspecialchars($display_date, ENT_QUOTES, 'UTF-8');
                        $status       = strtolower($row['status'] ?? 'pending');

                        $status_class = match ($status) {
                            'paid'     => 'badge--success',
                            'pending'  => 'badge--warning',
                            'failed'   => 'badge--error',
                            'refunded' => 'badge--info',
                            default    => 'badge--neutral',
                        };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($date_str, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>₱<?= htmlspecialchars(number_format((float) $row['amount'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <code class="reference-no">
                                    <?= htmlspecialchars($row['reference_no'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </code>
                            </td>
                            <td>
                                <span class="badge <?= htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- /.table-responsive -->
    <?php endif; ?>

    <div class="history-actions">
        <a class="btn btn-outline" href="/AralPal/subscriptions/plans.php">View Plans</a>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
