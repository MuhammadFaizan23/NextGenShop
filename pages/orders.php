<?php
/**
 * NextGenShop – My Orders
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$page     = max(1, input_int('page', 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

// Count
$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$total  = (int)$stmt->fetchColumn();
$pages  = (int)ceil($total / $per_page);

// Fetch orders (uses index on user_id + created_at)
$stmt = db()->prepare(
    'SELECT o.*,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$_SESSION['user_id'], $per_page, $offset]);
$orders = $stmt->fetchAll();

$status_colors = [
    'pending'    => 'warning',
    'processing' => 'info',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
    'refunded'   => 'secondary',
];

$page_title = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <h1 class="h3 fw-bold mb-4">
        <i class="bi bi-bag me-2 text-primary"></i>My Orders
    </h1>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bag-x display-3 text-muted opacity-50"></i>
            <h4 class="mt-3 text-muted">No orders yet</h4>
            <p class="text-muted">Start shopping and your orders will appear here.</p>
            <a href="/index.php" class="btn btn-primary mt-2">Shop Now</a>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Order</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr class="border-bottom">
                            <td class="ps-4 fw-semibold"><?= h($order['order_number']) ?></td>
                            <td class="text-muted small">
                                <?= date('M j, Y', strtotime($order['created_at'])) ?><br>
                                <span class="text-muted"><?= date('g:i A', strtotime($order['created_at'])) ?></span>
                            </td>
                            <td class="text-center"><?= (int)$order['item_count'] ?></td>
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
                                <a href="/pages/order_confirmation.php?order=<?= h($order['order_number']) ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav class="mt-4 d-flex justify-content-center">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
