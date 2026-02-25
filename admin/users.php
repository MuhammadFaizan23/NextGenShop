<?php
/**
 * NextGenShop – Admin Users Management
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = db();

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $action  = input_str('action');
        $user_id = input_int('user_id');

        if ($user_id === (int)$_SESSION['user_id']) {
            flash('error', 'You cannot modify your own admin account this way.');
        } elseif ($action === 'set_role') {
            $role = in_array($_POST['role'] ?? '', ['customer','admin']) ? $_POST['role'] : 'customer';
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $user_id]);
            flash('success', 'User role updated.');
        } elseif ($action === 'delete') {
            // Prevent deleting a user with orders
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
            $stmt->execute([$user_id]);
            if ((int)$stmt->fetchColumn() > 0) {
                flash('error', 'Cannot delete user with existing orders.');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
                flash('success', 'User deleted.');
            }
        } elseif ($action === 'reset_password') {
            $new_pw = trim($_POST['new_password'] ?? '');
            if (mb_strlen($new_pw) < 8) {
                flash('error', 'Password must be at least 8 characters.');
            } else {
                $hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user_id]);
                flash('success', 'Password reset successfully.');
            }
        }
    }
    redirect('/admin/users.php');
}

// Filters & pagination
$search  = input_str('q');
$role_f  = input_str('role');
$page    = max(1, input_int('page', 1));
$per_page = 20;
$offset  = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_f !== '') {
    $where[]  = 'role = ?';
    $params[] = $role_f;
}

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where_sql");
$stmt->execute($params);
$total_users = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total_users / $per_page);

$params_page = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
            (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = u.id) AS wish_count
     FROM users u
     WHERE $where_sql
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params_page);
$users = $stmt->fetchAll();

$page_title = 'Users';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-bold mb-0">Users</h1>
    <span class="text-muted small"><?= number_format($total_users) ?> user<?= $total_users !== 1 ? 's' : '' ?></span>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Name, email…"
                           value="<?= h($search) ?>">
                </div>
            </div>
            <div class="col-sm-2">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="customer" <?= $role_f === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="admin"    <?= $role_f === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">User</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th class="text-center">Orders</th>
                    <th class="text-center">Wishlist</th>
                    <th class="pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr class="border-bottom">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white"
                                 style="width:38px;height:38px;font-size:.85rem;flex-shrink:0">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?= h($u['name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem"><?= h($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-center"><?= (int)$u['order_count'] ?></td>
                    <td class="text-center"><?= (int)$u['wish_count'] ?></td>
                    <td class="pe-4">
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <div class="d-flex gap-1">
                            <!-- Toggle role -->
                            <form method="post" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'customer' : 'admin' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $u['role'] === 'admin' ? 'secondary' : 'warning' ?>"
                                        title="<?= $u['role'] === 'admin' ? 'Revoke Admin' : 'Make Admin' ?>">
                                    <i class="bi bi-shield<?= $u['role'] === 'admin' ? '-slash' : '-check' ?>"></i>
                                </button>
                            </form>
                            <!-- Reset password -->
                            <button type="button" class="btn btn-sm btn-outline-info reset-pw-btn"
                                    data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= h($u['name']) ?>"
                                    title="Reset Password">
                                <i class="bi bi-key"></i>
                            </button>
                            <!-- Delete -->
                            <form method="post" class="m-0" onsubmit="return confirm('Delete user <?= h(addslashes($u['name'])) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-center">
        <ul class="pagination pagination-sm mb-0">
            <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetPwUserId">
                <div class="modal-body">
                    <p class="text-muted small">Setting new password for: <strong id="resetPwUserName"></strong></p>
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.reset-pw-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('resetPwUserId').value = btn.dataset.userId;
        document.getElementById('resetPwUserName').textContent = btn.dataset.userName;
        new bootstrap.Modal(document.getElementById('resetPwModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
