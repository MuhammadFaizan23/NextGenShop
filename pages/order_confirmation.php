<?php
/**
 * NextGenShop – Order Confirmation
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$order_number = input_str('order');
if ($order_number === '') {
    redirect('/pages/orders.php');
}

// Fetch order (belonging to this user)
$stmt = db()->prepare(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('/pages/orders.php');
}

// Fetch order items
$stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();

$page_title = 'Order Confirmed – ' . h($order_number);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:680px">
    <div class="text-center mb-5">
        <div class="display-1 text-success mb-3">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1 class="h3 fw-bold">Order Confirmed!</h1>
        <p class="text-muted lead">
            Thank you for your purchase, <?= h($_SESSION['user_name']) ?>!
            Your order has been received and is being processed.
        </p>
        <div class="badge bg-primary-subtle text-primary fs-6 px-3 py-2">
            Order #<?= h($order['order_number']) ?>
        </div>
    </div>

    <!-- Order details -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h5 class="fw-bold mb-0">Order Details</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-borderless mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Product</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end pe-4">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="border-bottom">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($item['product_image']): ?>
                                    <img src="<?= h($item['product_image']) ?>" alt=""
                                         style="width:50px;height:45px;object-fit:cover" class="rounded">
                                <?php endif; ?>
                                <span class="fw-semibold small"><?= h($item['product_name']) ?></span>
                            </div>
                        </td>
                        <td class="text-center"><?= (int)$item['quantity'] ?></td>
                        <td class="text-end pe-4"><?= format_price((float)$item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <div class="row justify-content-end">
                <div class="col-sm-5">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Subtotal</span><span><?= format_price((float)$order['subtotal']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Shipping</span>
                        <span><?= (float)$order['shipping_cost'] === 0.0 ? 'FREE' : format_price((float)$order['shipping_cost']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Tax</span><span><?= format_price((float)$order['tax']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-6">
                        <span>Total</span>
                        <span class="text-primary"><?= format_price((float)$order['total']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping & Payment -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt me-2 text-primary"></i>Shipping To</h6>
                    <p class="text-muted small mb-0">
                        <?= h($order['shipping_name']) ?><br>
                        <?= h($order['shipping_address']) ?><br>
                        <?= h($order['shipping_city']) ?>
                        <?= $order['shipping_state'] ? ', ' . h($order['shipping_state']) : '' ?>
                        <?= $order['shipping_zip'] ? ' ' . h($order['shipping_zip']) : '' ?><br>
                        <?= h($order['shipping_country']) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-credit-card me-2 text-primary"></i>Payment</h6>
                    <p class="text-muted small mb-0">
                        Method: <?= h(str_replace('_', ' ', ucfirst($order['payment_method'] ?? 'N/A'))) ?><br>
                        Status:
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                            <?= ucfirst($order['payment_status']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center d-flex flex-wrap justify-content-center gap-3">
        <a href="/pages/orders.php" class="btn btn-outline-primary">
            <i class="bi bi-bag me-2"></i>View All Orders
        </a>
        <a href="/index.php" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Continue Shopping
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
