<?php
/**
 * NextGenShop – Admin Dashboard
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = db();

// Stats – efficient single queries with indexes
$stats = [];

$stmt = $pdo->query('SELECT COUNT(*) FROM orders');
$stats['total_orders'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != "cancelled"');
$stats['total_revenue'] = (float)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"');
$stats['total_customers'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE status = "active"');
$stats['total_products'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "pending"');
$stats['pending_orders'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE stock = 0');
$stats['out_of_stock'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5');
$stats['low_stock'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM reviews WHERE status = "pending"');
$stats['pending_reviews'] = (int)$stmt->fetchColumn();

// Recent orders (uses composite index on user_id + created_at)
$recent_orders = $pdo->query(
    'SELECT o.id, o.order_number, o.status, o.total, o.created_at, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

// Top products by revenue (uses order_items + products)
$top_products = $pdo->query(
    'SELECT p.name, p.image, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     GROUP BY oi.product_id, p.name, p.image
     ORDER BY revenue DESC LIMIT 5'
)->fetchAll();

// Monthly revenue (last 6 months, uses index on created_at)
$monthly_revenue = $pdo->query(
    'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month,
            COALESCE(SUM(total), 0) AS revenue,
            COUNT(*) AS orders
     FROM orders
     WHERE status != "cancelled"
       AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month
     ORDER BY month'
)->fetchAll();

$status_colors = [
    'pending'    => 'warning',
    'processing' => 'info',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
    'refunded'   => 'secondary',
];

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Stat cards -->
<div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <?php
    $stat_cards = [
        ['label' => 'Total Revenue',   'value' => format_price($stats['total_revenue']),  'icon' => 'bi-currency-dollar', 'color' => 'primary'],
        ['label' => 'Total Orders',    'value' => number_format($stats['total_orders']),   'icon' => 'bi-bag-check',       'color' => 'success'],
        ['label' => 'Customers',       'value' => number_format($stats['total_customers']),'icon' => 'bi-people',          'color' => 'info'],
        ['label' => 'Active Products', 'value' => number_format($stats['total_products']), 'icon' => 'bi-box-seam',        'color' => 'warning'],
    ];
    foreach ($stat_cards as $card):
    ?>
    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-<?= $card['color'] ?>-subtle">
                    <i class="bi <?= $card['icon'] ?> fs-3 text-<?= $card['color'] ?>"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= $card['value'] ?></div>
                    <div class="text-muted small"><?= $card['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Alert badges -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php if ($stats['pending_orders'] > 0): ?>
        <a href="/admin/orders.php?status=pending" class="badge bg-warning text-dark text-decoration-none fs-6 py-2 px-3">
            <i class="bi bi-clock me-1"></i><?= $stats['pending_orders'] ?> Pending Orders
        </a>
    <?php endif; ?>
    <?php if ($stats['out_of_stock'] > 0): ?>
        <a href="/admin/products.php?stock=out" class="badge bg-danger text-decoration-none fs-6 py-2 px-3">
            <i class="bi bi-x-circle me-1"></i><?= $stats['out_of_stock'] ?> Out of Stock
        </a>
    <?php endif; ?>
    <?php if ($stats['low_stock'] > 0): ?>
        <a href="/admin/products.php?stock=low" class="badge bg-secondary text-decoration-none fs-6 py-2 px-3">
            <i class="bi bi-exclamation-circle me-1"></i><?= $stats['low_stock'] ?> Low Stock
        </a>
    <?php endif; ?>
    <?php if ($stats['pending_reviews'] > 0): ?>
        <a href="/admin/reviews.php?status=pending" class="badge bg-info text-decoration-none fs-6 py-2 px-3">
            <i class="bi bi-star me-1"></i><?= $stats['pending_reviews'] ?> Pending Reviews
        </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Recent Orders</h5>
                <a href="/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_orders)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr class="border-bottom">
                            <td class="ps-4 fw-semibold"><?= h($order['order_number']) ?></td>
                            <td><?= h($order['customer_name']) ?></td>
                            <td class="text-muted small"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                            <td class="fw-bold"><?= format_price((float)$order['total']) ?></td>
                            <td>
                                <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td class="pe-4">
                                <a href="/admin/orders.php?view=<?= (int)$order['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Top Products</h5>
                <a href="/admin/products.php" class="btn btn-sm btn-outline-primary">All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_products)): ?>
                    <p class="text-center text-muted p-4">No sales data yet.</p>
                <?php else: ?>
                <?php foreach ($top_products as $tp): ?>
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                    <img src="<?= h($tp['image'] ?: 'https://via.placeholder.com/40') ?>"
                         style="width:40px;height:35px;object-fit:cover" class="rounded" alt="">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-semibold small text-truncate"><?= h($tp['name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= (int)$tp['units_sold'] ?> sold</div>
                    </div>
                    <div class="fw-bold small text-nowrap"><?= format_price((float)$tp['revenue']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Revenue -->
<?php if (!empty($monthly_revenue)): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0">
        <h5 class="fw-bold mb-0">Revenue (Last 6 Months)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th class="text-center">Orders</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end" style="width:200px">Bar</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $max_rev = max(array_column($monthly_revenue, 'revenue')) ?: 1;
                foreach ($monthly_revenue as $m):
                    $pct = round(($m['revenue'] / $max_rev) * 100);
                ?>
                <tr>
                    <td><?= date('M Y', strtotime($m['month'] . '-01')) ?></td>
                    <td class="text-center"><?= (int)$m['orders'] ?></td>
                    <td class="text-end fw-bold"><?= format_price((float)$m['revenue']) ?></td>
                    <td class="pe-3">
                        <div class="progress" style="height:10px">
                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
