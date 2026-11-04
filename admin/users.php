<?php
/**
 * AralPal Admin – User Management
 *
 * List, search, filter, paginate users; toggle active/inactive; change role.
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

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!verify_csrf($csrf)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $action  = (string) ($_POST['action'] ?? '');
    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $user_id > 0) {
        $stmt = $db->prepare('SELECT status FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $current_status = $stmt->fetchColumn();

        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        $upd = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $upd->execute([$new_status, $user_id]);
        $msg = 'User status updated to ' . htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8') . '.';
    }

    if ($action === 'change_role' && $user_id > 0) {
        $role_id = (int) ($_POST['role_id'] ?? 0);
        if ($role_id > 0) {
            $upd = $db->prepare('UPDATE users SET role_id = ? WHERE id = ?');
            $upd->execute([$role_id, $user_id]);
            $msg = 'User role updated.';
        } else {
            $err = 'Invalid role selected.';
        }
    }
}

// ── Roles list (for filter + change-role select) ──────────────────────────────
$roles_stmt = $db->query('SELECT id, role_name FROM roles ORDER BY role_name');
$all_roles  = $roles_stmt->fetchAll();

// ── Filters ───────────────────────────────────────────────────────────────────
$role_filter = (string) ($_GET['role_filter'] ?? '');
$q           = trim((string) ($_GET['q'] ?? ''));
$page        = max(1, (int) ($_GET['page'] ?? 1));
$per_page    = 20;
$offset      = ($page - 1) * $per_page;

// Build WHERE clauses
$where  = [];
$params = [];

if ($role_filter !== '') {
    $where[]  = 'r.role_name = ?';
    $params[] = $role_filter;
}
if ($q !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total rows
$count_stmt = $db->prepare(
    "SELECT COUNT(*) FROM users u
     JOIN roles r ON r.id = u.role_id
     $where_sql"
);
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();
$total_pages = (int) ceil($total_rows / $per_page);

// Fetch page
$list_stmt = $db->prepare(
    "SELECT u.id, u.full_name, u.email, u.status, r.role_name, r.id AS role_id
     FROM users u
     JOIN roles r ON r.id = u.role_id
     $where_sql
     ORDER BY u.id ASC
     LIMIT ? OFFSET ?"
);
$list_params = array_merge($params, [$per_page, $offset]);
$list_stmt->execute($list_params);
$users = $list_stmt->fetchAll();

// ── Build query string helper ─────────────────────────────────────────────────
function users_qs(array $overrides): string
{
    $base = [
        'role_filter' => $_GET['role_filter'] ?? '',
        'q'           => $_GET['q'] ?? '',
        'page'        => $_GET['page'] ?? '1',
    ];
    return http_build_query(array_merge($base, $overrides));
}

$csrf_token = generate_csrf();
$pageTitle  = 'User Management';
require __DIR__ . '/partials/header.php';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">ADMIN / USERS</p>
        <h1>User Management</h1>
        <p class="muted">View, search, and manage all registered users.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="get" action="users.php" class="filter-bar">
    <input
        type="text"
        name="q"
        placeholder="Search name or email…"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
    >
    <select name="role_filter">
        <option value="">All roles</option>
        <?php foreach ($all_roles as $role): ?>
            <option
                value="<?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?>"
                <?= $role_filter === $role['role_name'] ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="button" type="submit">Filter</button>
    <a class="button secondary" href="users.php">Reset</a>
</form>

<section class="panel">
    <p class="muted">
        Showing <?= count($users) ?> of <?= $total_rows ?> users
        (page <?= $page ?> of <?= max(1, $total_pages) ?>)
    </p>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="empty">No users found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status <?= $user['status'] === 'active' ? 'published' : 'draft' ?>">
                                <?= htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="actions">
                            <!-- Toggle active/inactive -->
                            <form method="post" action="users.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"  value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button class="text-link" type="submit">
                                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <!-- Change role -->
                            <form method="post" action="users.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action"  value="change_role">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <select name="role_id" style="margin-left:0.5rem">
                                    <?php foreach ($all_roles as $role): ?>
                                        <option
                                            value="<?= (int) $role['id'] ?>"
                                            <?= (int) $role['id'] === (int) $user['role_id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button" type="submit" style="padding:0.2rem 0.5rem">Save Role</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="User list pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= htmlspecialchars(users_qs(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">← Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
                <?php if ($p === $page): ?>
                    <strong><?= $p ?></strong>
                <?php else: ?>
                    <a href="?<?= htmlspecialchars(users_qs(['page' => $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?<?= htmlspecialchars(users_qs(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
