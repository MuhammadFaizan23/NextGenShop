<?php
/**
 * NextGenShop – Checkout
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo    = db();
$errors = [];

// Fetch cart
$stmt = $pdo->prepare(
    'SELECT c.id AS cart_id, c.quantity,
            p.id, p.name, p.slug, p.image, p.price, p.sale_price, p.stock
     FROM cart c
     JOIN products p ON p.id = c.product_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    flash('info', 'Your cart is empty. Add items before checking out.');
    redirect('/pages/cart.php');
}

// Calculate totals
$subtotal = 0.0;
foreach ($cart_items as $item) {
    $unit = (float)($item['sale_price'] ?? $item['price']);
    $subtotal += $unit * (int)$item['quantity'];
}
$shipping_cost = $subtotal >= 50 ? 0.0 : 9.99;
$tax           = $subtotal * 0.08;
$total         = $subtotal + $shipping_cost + $tax;

// Current user for pre-fill
$user = current_user();

// POST – process order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $ship_name    = trim($_POST['shipping_name'] ?? '');
        $ship_email   = strtolower(trim($_POST['shipping_email'] ?? ''));
        $ship_phone   = trim($_POST['shipping_phone'] ?? '');
        $ship_address = trim($_POST['shipping_address'] ?? '');
        $ship_city    = trim($_POST['shipping_city'] ?? '');
        $ship_state   = trim($_POST['shipping_state'] ?? '');
        $ship_zip     = trim($_POST['shipping_zip'] ?? '');
        $ship_country = trim($_POST['shipping_country'] ?? 'US');
        $payment      = trim($_POST['payment_method'] ?? 'credit_card');
        $notes        = trim($_POST['notes'] ?? '');

        if (mb_strlen($ship_name) < 2)  $errors[] = 'Please enter your full name.';
        if (!filter_var($ship_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if (mb_strlen($ship_address) < 5) $errors[] = 'Please enter your shipping address.';
        if (mb_strlen($ship_city) < 2)   $errors[] = 'Please enter your city.';
        if (mb_strlen($ship_country) < 2)$errors[] = 'Please select your country.';

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $order_number = generate_order_number();

                // Create order
                $stmt = $pdo->prepare(
                    'INSERT INTO orders
                     (user_id, order_number, status, total, subtotal, shipping_cost, tax,
                      shipping_name, shipping_email, shipping_phone, shipping_address,
                      shipping_city, shipping_state, shipping_zip, shipping_country,
                      payment_method, payment_status, notes)
                     VALUES (?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)'
                );
                $stmt->execute([
                    $_SESSION['user_id'], $order_number, $total, $subtotal,
                    $shipping_cost, $tax, $ship_name, $ship_email, $ship_phone,
                    $ship_address, $ship_city, $ship_state, $ship_zip, $ship_country,
                    $payment, $notes
                ]);
                $order_id = (int)$pdo->lastInsertId();

                // Create order items & deduct stock
                $ins_item  = $pdo->prepare(
                    'INSERT INTO order_items
                     (order_id, product_id, product_name, product_image, price, quantity, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $upd_stock = $pdo->prepare(
                    'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
                );

                foreach ($cart_items as $item) {
                    $unit = (float)($item['sale_price'] ?? $item['price']);
                    $qty  = (int)$item['quantity'];

                    $ins_item->execute([
                        $order_id, $item['id'], $item['name'], $item['image'],
                        $unit, $qty, $unit * $qty
                    ]);

                    $upd_stock->execute([$qty, $item['id'], $qty]);
                    if ($upd_stock->rowCount() === 0) {
                        throw new RuntimeException("Insufficient stock for: {$item['name']}");
                    }
                }

                // Clear cart
                $pdo->prepare('DELETE FROM cart WHERE user_id = ?')
                    ->execute([$_SESSION['user_id']]);

                $pdo->commit();

                flash('success', "Order #{$order_number} placed successfully! Thank you for your purchase.");
                redirect('/pages/order_confirmation.php?order=' . urlencode($order_number));

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Order could not be placed: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-credit-card me-2 text-primary"></i>Checkout</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-4">
            <!-- Shipping info -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2"></i>Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Full Name *</label>
                                <input type="text" name="shipping_name" class="form-control"
                                       value="<?= h($_POST['shipping_name'] ?? $user['name']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Email *</label>
                                <input type="email" name="shipping_email" class="form-control"
                                       value="<?= h($_POST['shipping_email'] ?? $user['email']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="tel" name="shipping_phone" class="form-control"
                                       value="<?= h($_POST['shipping_phone'] ?? $user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address *</label>
                                <input type="text" name="shipping_address" class="form-control"
                                       value="<?= h($_POST['shipping_address'] ?? '') ?>"
                                       placeholder="Street address, apartment, suite, etc." required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">City *</label>
                                <input type="text" name="shipping_city" class="form-control"
                                       value="<?= h($_POST['shipping_city'] ?? '') ?>" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">State</label>
                                <input type="text" name="shipping_state" class="form-control"
                                       value="<?= h($_POST['shipping_state'] ?? '') ?>">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">ZIP Code</label>
                                <input type="text" name="shipping_zip" class="form-control"
                                       value="<?= h($_POST['shipping_zip'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Country *</label>
                                <select name="shipping_country" class="form-select" required>
                                    <option value="US" <?= ($_POST['shipping_country'] ?? 'US') === 'US' ? 'selected' : '' ?>>United States</option>
                                    <option value="CA" <?= ($_POST['shipping_country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                                    <option value="GB" <?= ($_POST['shipping_country'] ?? '') === 'GB' ? 'selected' : '' ?>>United Kingdom</option>
                                    <option value="AU" <?= ($_POST['shipping_country'] ?? '') === 'AU' ? 'selected' : '' ?>>Australia</option>
                                    <option value="DE" <?= ($_POST['shipping_country'] ?? '') === 'DE' ? 'selected' : '' ?>>Germany</option>
                                    <option value="FR" <?= ($_POST['shipping_country'] ?? '') === 'FR' ? 'selected' : '' ?>>France</option>
                                    <option value="OTHER" <?= !in_array($_POST['shipping_country'] ?? 'US', ['US','CA','GB','AU','DE','FR']) ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Order Notes (optional)</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Special instructions for delivery…"><?= h($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment method -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="fw-bold mb-0"><i class="bi bi-credit-card me-2"></i>Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php
                            $payment_methods = [
                                'credit_card' => ['icon' => 'bi-credit-card-fill', 'label' => 'Credit / Debit Card'],
                                'paypal'      => ['icon' => 'bi-paypal',           'label' => 'PayPal'],
                                'bank'        => ['icon' => 'bi-bank',             'label' => 'Bank Transfer'],
                            ];
                            foreach ($payment_methods as $key => $pm):
                                $checked = ($_POST['payment_method'] ?? 'credit_card') === $key;
                            ?>
                            <div class="col-sm-4">
                                <div class="form-check border rounded p-3 <?= $checked ? 'border-primary bg-primary-subtle' : '' ?>">
                                    <input class="form-check-input" type="radio" name="payment_method"
                                           id="pay_<?= $key ?>" value="<?= $key ?>" <?= $checked ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="pay_<?= $key ?>">
                                        <i class="bi <?= $pm['icon'] ?> me-2 text-primary"></i><?= $pm['label'] ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-muted small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            This is a demo shop – no real payment is processed.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Order summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                    <div class="card-header bg-white border-0 pb-0">
                        <h5 class="fw-bold mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Items -->
                        <div class="mb-3">
                            <?php foreach ($cart_items as $item):
                                $unit = (float)($item['sale_price'] ?? $item['price']);
                            ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img src="<?= h($item['image'] ?: 'https://via.placeholder.com/50') ?>"
                                     style="width:45px;height:40px;object-fit:cover" class="rounded" alt="">
                                <div class="flex-grow-1 small">
                                    <div class="fw-semibold lh-sm"><?= h(truncate($item['name'], 40)) ?></div>
                                    <div class="text-muted">x<?= (int)$item['quantity'] ?></div>
                                </div>
                                <div class="fw-bold small"><?= format_price($unit * (int)$item['quantity']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Subtotal</span><span><?= format_price($subtotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Shipping</span>
                            <span class="<?= $shipping_cost === 0.0 ? 'text-success' : '' ?>">
                                <?= $shipping_cost === 0.0 ? 'FREE' : format_price($shipping_cost) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-muted">Tax (8%)</span><span><?= format_price($tax) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                            <span>Total</span>
                            <span class="text-primary"><?= format_price($total) ?></span>
                        </div>

                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-bag-check me-2"></i>Place Order
                        </button>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-lock me-1"></i>Secured by SSL encryption
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
