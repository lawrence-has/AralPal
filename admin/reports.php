<?php
/**
 * AralPal Admin – Reports & Analytics
 *
 * Summary stats, bookings-per-month chart (Chart.js), recent payments.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role('Admin');

$db = get_db();

// ── 1. User counts by role ────────────────────────────────────────────────────
$user_counts_stmt = $db->query(
    "SELECT r.role_name, COUNT(u.id) AS cnt
     FROM users u
     JOIN roles r ON r.id = u.role_id
     GROUP BY r.role_name"
);
$user_counts_raw  = $user_counts_stmt->fetchAll();

$user_counts      = [];
$total_users      = 0;
foreach ($user_counts_raw as $row) {
    $user_counts[$row['role_name']] = (int) $row['cnt'];
    $total_users += (int) $row['cnt'];
}

$total_tutors   = $user_counts['Tutor']   ?? 0;
$total_students = $user_counts['Student'] ?? 0;

// ── 2. Booking counts by status ───────────────────────────────────────────────
$booking_counts_stmt = $db->query(
    "SELECT status, COUNT(*) AS cnt FROM bookings GROUP BY status"
);
$booking_counts_raw  = $booking_counts_stmt->fetchAll();

$booking_counts  = [];
$total_bookings  = 0;
foreach ($booking_counts_raw as $row) {
    $booking_counts[$row['status']] = (int) $row['cnt'];
    $total_bookings += (int) $row['cnt'];
}

// ── 3. Total revenue ─────────────────────────────────────────────────────────
$rev_stmt    = $db->query(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'paid'"
);
$total_revenue = (float) $rev_stmt->fetchColumn();

// ── 4. Bookings per month (last 12 months) ────────────────────────────────────
$monthly_stmt = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
     FROM bookings
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY month
     ORDER BY month ASC"
);
$monthly_raw = $monthly_stmt->fetchAll();

$chart_labels = [];
$chart_data   = [];
foreach ($monthly_raw as $row) {
    $chart_labels[] = htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8');
    $chart_data[]   = (int) $row['cnt'];
}

// Encode safely as JSON for inline JS
$chart_labels_json = json_encode($chart_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$chart_data_json   = json_encode($chart_data,   JSON_HEX_TAG | JSON_HEX_AMP);

// ── 5. Recent payments (last 10) ──────────────────────────────────────────────
$payments_stmt = $db->query(
    "SELECT
        p.id,
        p.amount,
        p.status,
        p.created_at,
        u.full_name   AS user_name,
        sp.plan_name
     FROM payments p
     JOIN users u            ON u.id  = p.user_id
     JOIN subscription_plans sp ON sp.id = p.plan_id
     ORDER BY p.created_at DESC
     LIMIT 10"
);
$recent_payments = $payments_stmt->fetchAll();

$pageTitle = 'Reports & Analytics';
require __DIR__ . '/partials/header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">ADMIN / REPORTS</p>
        <h1>Reports &amp; Analytics</h1>
        <p class="muted">System-wide statistics and recent activity.</p>
    </div>
</div>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<section class="stats">
    <article class="stat-card">
        <span>Total Users</span>
        <strong><?= $total_users ?></strong>
    </article>
    <article class="stat-card">
        <span>Total Tutors</span>
        <strong><?= $total_tutors ?></strong>
    </article>
    <article class="stat-card">
        <span>Total Students</span>
        <strong><?= $total_students ?></strong>
    </article>
    <article class="stat-card">
        <span>Total Bookings</span>
        <strong><?= $total_bookings ?></strong>
    </article>
    <article class="stat-card">
        <span>Total Revenue</span>
        <strong>₱<?= htmlspecialchars(number_format($total_revenue, 2), ENT_QUOTES, 'UTF-8') ?></strong>
    </article>
</section>

<!-- ── Booking breakdown by status ───────────────────────────────────────── -->
<?php if (!empty($booking_counts)): ?>
<section class="panel">
    <h2>Bookings by Status</h2>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Status</th><th>Count</th></tr>
            </thead>
            <tbody>
            <?php foreach ($booking_counts as $status => $cnt): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $cnt ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<!-- ── Bookings per month chart ───────────────────────────────────────────── -->
<section class="panel">
    <h2>Bookings per Month (Last 12 Months)</h2>
    <?php if (empty($monthly_raw)): ?>
        <p class="empty">No booking data available.</p>
    <?php else: ?>
        <div style="max-width:800px">
            <canvas id="bookingsChart" height="120" aria-label="Bookings per month bar chart" role="img"></canvas>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            var labels = <?= $chart_labels_json ?>;
            var data   = <?= $chart_data_json ?>;

            var ctx = document.getElementById('bookingsChart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Bookings',
                        data: data,
                        backgroundColor: 'rgba(99, 102, 241, 0.7)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        })();
        </script>
    <?php endif; ?>
</section>

<!-- ── Recent payments ───────────────────────────────────────────────────── -->
<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">RECENT ACTIVITY</p>
            <h2>Recent Payments</h2>
        </div>
    </div>

    <?php if (empty($recent_payments)): ?>
        <p class="empty">No payments on record.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_payments as $payment): ?>
                    <tr>
                        <td><?= (int) $payment['id'] ?></td>
                        <td><?= htmlspecialchars($payment['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($payment['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>₱<?= htmlspecialchars(number_format((float) $payment['amount'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status <?= $payment['status'] === 'paid' ? 'published' : 'draft' ?>">
                                <?= htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($payment['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
