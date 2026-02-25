<?php
/**
 * NextGenShop – Admin Orders Management
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = db();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid form submission.');
    } else {
        $order_id  = input_int('order_id');
        $new_status = input_str('status');
        $allowed   = ['pending','processing','shipped','delivered','cancelled','refunded'];
        if (in_array($new_status, $allowed, true)) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')
                ->execute([$new_status, $order_id]);
            flash('success', 'Order status updated.');
        }
    }
    redirect('/admin/orders.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

// Filters
$status_f = input_str('status');
$search   = input_str('q');
$page     = max(1, input_int('page', 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($status_f !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $status_f;
}
if ($search !== '') {
    $where[]  = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE $where_sql");
$stmt->execute($params);
$total_orders = (int)$stmt->fetchColumn();
$total_pages  = (int)ceil($total_orders / $per_page);

$params_page = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE $where_sql
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params_page);
$orders = $stmt->fetchAll();

// View single order
$view_order = null;
$view_items = [];
if (input_int('view') > 0) {
    $vid = input_int('view');
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS customer_name, u.email AS customer_email
         FROM orders o JOIN users u ON u.id = o.user_id
         WHERE o.id = ? LIMIT 1'
    );
    $stmt->execute([$vid]);
    $view_order = $stmt->fetch();
    if ($view_order) {
        $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt->execute([$vid]);
        $view_items = $stmt->fetchAll();
    }
}

$status_colors = [
    'pending'    => 'warning',
    'processing' => 'info',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
    'refunded'   => 'secondary',
];

$page_title = 'Orders';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-bold mb-0">Orders</h1>
    <span class="text-muted small"><?= number_format($total_orders) ?> order<?= $total_orders !== 1 ? 's' : '' ?></span>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Order #, customer…"
                           value="<?= h($search) ?>">
                </div>
            </div>
            <div class="col-sm-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (array_keys($status_colors) as $s): ?>
                        <option value="<?= $s ?>" <?= $status_f === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="/admin/orders.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($view_order): ?>
<!-- Single order detail -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold mb-0">Order <?= h($view_order['order_number']) ?></h5>
        <a href="/admin/orders.php" class="btn btn-sm btn-outline-secondary">← Back</a>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-bold">Customer</h6>
                <p class="text-muted small mb-0">
                    <?= h($view_order['customer_name']) ?><br>
                    <?= h($view_order['customer_email']) ?>
                </p>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">Shipping</h6>
                <p class="text-muted small mb-0">
                    <?= h($view_order['shipping_address']) ?>, <?= h($view_order['shipping_city']) ?>
                    <?= h($view_order['shipping_zip']) ?>, <?= h($view_order['shipping_country']) ?>
                </p>
            </div>
        </div>
        <div class="table-responsive mt-4">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($view_items as $vi): ?>
                <tr>
                    <td><?= h($vi['product_name']) ?></td>
                    <td><?= format_price((float)$vi['price']) ?></td>
                    <td><?= (int)$vi['quantity'] ?></td>
                    <td><?= format_price((float)$vi['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-end fw-bold">Subtotal:</td><td><?= format_price((float)$view_order['subtotal']) ?></td></tr>
                    <tr><td colspan="3" class="text-end fw-bold">Shipping:</td><td><?= (float)$view_order['shipping_cost'] === 0.0 ? 'FREE' : format_price((float)$view_order['shipping_cost']) ?></td></tr>
                    <tr><td colspan="3" class="text-end fw-bold">Tax:</td><td><?= format_price((float)$view_order['tax']) ?></td></tr>
                    <tr class="table-light"><td colspan="3" class="text-end fw-bold">Total:</td><td class="fw-bold text-primary"><?= format_price((float)$view_order['total']) ?></td></tr>
                </tfoot>
            </table>
        </div>
        <!-- Update status -->
        <form method="post" class="d-flex align-items-center gap-3 mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="order_id" value="<?= (int)$view_order['id'] ?>">
            <label class="fw-semibold mb-0">Update Status:</label>
            <select name="status" class="form-select form-select-sm" style="width:180px">
                <?php foreach (array_keys($status_colors) as $s): ?>
                    <option value="<?= $s ?>" <?= $view_order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Update</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Orders table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th class="pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr class="border-bottom">
                    <td class="ps-4 fw-semibold"><?= h($order['order_number']) ?></td>
                    <td>
                        <div class="fw-semibold small"><?= h($order['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= h($order['customer_email']) ?></div>
                    </td>
                    <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                    <td class="fw-bold"><?= format_price((float)$order['total']) ?></td>
                    <td>
                        <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($order['payment_status']) ?>
                        </span>
                    </td>
                    <td class="pe-4">
                        <a href="/admin/orders.php?view=<?= (int)$order['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
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

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
