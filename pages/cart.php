<?php
/**
 * NextGenShop – Shopping Cart
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ----- Handle POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = input_str('action');
    $allowed = ['add', 'update', 'remove', 'clear'];

    if (!in_array($action, $allowed, true) || !csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/pages/cart.php');
    }

    $pdo        = db();
    $product_id = input_int('product_id');
    $quantity   = max(1, input_int('quantity', 1));

    // Build WHERE clause based on logged-in state
    $user_where = is_logged_in()
        ? ['user_id = ?', [$_SESSION['user_id']]]
        : ['session_id = ?', [session_id()]];

    if ($action === 'add') {
        // Validate product exists and has stock
        $stmt = $pdo->prepare(
            'SELECT id, stock FROM products WHERE id = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            flash('error', 'Product not found.');
        } elseif ((int)$product['stock'] < 1) {
            flash('error', 'This product is out of stock.');
        } else {
            // Cap quantity at stock
            $quantity = min($quantity, (int)$product['stock']);

            // Check current cart quantity
            $stmt = $pdo->prepare(
                "SELECT id, quantity FROM cart WHERE {$user_where[0]} AND product_id = ? LIMIT 1"
            );
            $stmt->execute(array_merge($user_where[1], [$product_id]));
            $existing = $stmt->fetch();

            if ($existing) {
                $new_qty = min($existing['quantity'] + $quantity, (int)$product['stock']);
                $pdo->prepare('UPDATE cart SET quantity = ? WHERE id = ?')
                    ->execute([$new_qty, $existing['id']]);
            } else {
                if (is_logged_in()) {
                    $pdo->prepare(
                        'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)'
                    )->execute([$_SESSION['user_id'], $product_id, $quantity]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)'
                    )->execute([session_id(), $product_id, $quantity]);
                }
            }
            flash('success', 'Item added to your cart!');
        }
        // Redirect to origin or cart
        $redirect = input_str('redirect') ?: '/pages/cart.php';
        if (isset($_POST['buy_now'])) {
            redirect('/pages/checkout.php');
        }
        redirect($redirect);
    }

    if ($action === 'update') {
        $cart_id = input_int('cart_id');
        if ($quantity < 1) {
            $action = 'remove';
        } else {
            $pdo->prepare(
                "UPDATE cart SET quantity = ? WHERE id = ? AND {$user_where[0]}"
            )->execute(array_merge([$quantity, $cart_id], $user_where[1]));
        }
    }

    if ($action === 'remove') {
        $cart_id = input_int('cart_id');
        $pdo->prepare(
            "DELETE FROM cart WHERE id = ? AND {$user_where[0]}"
        )->execute(array_merge([$cart_id], $user_where[1]));
        flash('success', 'Item removed from cart.');
    }

    if ($action === 'clear') {
        $pdo->prepare("DELETE FROM cart WHERE {$user_where[0]}")
            ->execute($user_where[1]);
        flash('info', 'Your cart has been cleared.');
    }

    redirect('/pages/cart.php');
}

// ----- Fetch cart items -----
if (is_logged_in()) {
    $stmt = db()->prepare(
        'SELECT c.id AS cart_id, c.quantity,
                p.id, p.name, p.slug, p.image, p.price, p.sale_price, p.stock
         FROM cart c
         JOIN products p ON p.id = c.product_id
         WHERE c.user_id = ?
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt = db()->prepare(
        'SELECT c.id AS cart_id, c.quantity,
                p.id, p.name, p.slug, p.image, p.price, p.sale_price, p.stock
         FROM cart c
         JOIN products p ON p.id = c.product_id
         WHERE c.session_id = ?
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([session_id()]);
}
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal      = 0.0;
$shipping_cost = 0.0;
foreach ($cart_items as $item) {
    $unit_price = (float)($item['sale_price'] ?? $item['price']);
    $subtotal  += $unit_price * (int)$item['quantity'];
}
$shipping_cost = $subtotal >= 50 ? 0.0 : 9.99;
$tax           = $subtotal * 0.08; // 8% tax
$total         = $subtotal + $shipping_cost + $tax;

$page_title = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-cart3 me-2 text-primary"></i>Shopping Cart</h1>
        <a href="/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Continue Shopping
        </a>
    </div>

    <?php if (empty($cart_items)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x display-3 text-muted opacity-50"></i>
            <h4 class="mt-3 text-muted">Your cart is empty</h4>
            <p class="text-muted">Add some products to get started!</p>
            <a href="/index.php" class="btn btn-primary mt-2">
                <i class="bi bi-bag me-2"></i>Shop Now
            </a>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <!-- Cart items -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-borderless align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-center">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cart_items as $item):
                            $unit_price = (float)($item['sale_price'] ?? $item['price']);
                            $line_total = $unit_price * (int)$item['quantity'];
                        ?>
                            <tr class="border-bottom">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <a href="/pages/product.php?slug=<?= h($item['slug']) ?>">
                                            <img src="<?= h($item['image'] ?: 'https://via.placeholder.com/80') ?>"
                                                 alt="<?= h($item['name']) ?>"
                                                 style="width:70px;height:60px;object-fit:cover" class="rounded">
                                        </a>
                                        <div>
                                            <a href="/pages/product.php?slug=<?= h($item['slug']) ?>"
                                               class="text-decoration-none text-dark fw-semibold small lh-sm">
                                                <?= h(truncate($item['name'], 60)) ?>
                                            </a>
                                            <?php if ((int)$item['stock'] === 0): ?>
                                                <div class="badge bg-danger mt-1">Out of stock</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['sale_price'] !== null): ?>
                                        <span class="text-danger fw-bold"><?= format_price((float)$item['sale_price']) ?></span>
                                        <br><small class="text-decoration-line-through text-muted"><?= format_price((float)$item['price']) ?></small>
                                    <?php else: ?>
                                        <?= format_price((float)$item['price']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="post" class="d-inline-flex align-items-center gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                                        <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>"
                                               min="1" max="<?= min((int)$item['stock'], 99) ?>"
                                               class="form-control form-control-sm text-center"
                                               style="width:65px" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td class="text-center fw-bold"><?= format_price($line_total) ?></td>
                                <td class="pe-3">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                                        <button type="submit" class="btn btn-link text-danger p-1" title="Remove">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Clear your entire cart?')">
                            <i class="bi bi-trash me-1"></i>Clear Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order summary -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span><?= format_price($subtotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping</span>
                        <span class="<?= $shipping_cost === 0.0 ? 'text-success' : '' ?>">
                            <?= $shipping_cost === 0.0 ? 'FREE' : format_price($shipping_cost) ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Tax (8%)</span>
                        <span><?= format_price($tax) ?></span>
                    </div>
                    <?php if ($shipping_cost > 0): ?>
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-truck me-1"></i>
                        Add <?= format_price(50 - $subtotal) ?> more for free shipping!
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                        <span>Total</span>
                        <span class="text-primary"><?= format_price($total) ?></span>
                    </div>

                    <?php if (is_logged_in()): ?>
                        <a href="/pages/checkout.php" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <a href="/pages/login.php" class="btn btn-primary w-100 btn-lg mb-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Checkout
                        </a>
                        <a href="/pages/register.php" class="btn btn-outline-secondary w-100">
                            Create an Account
                        </a>
                    <?php endif; ?>

                    <div class="mt-3 d-flex justify-content-center gap-3 text-muted">
                        <small><i class="bi bi-shield-lock me-1"></i>Secure checkout</small>
                        <small><i class="bi bi-credit-card me-1"></i>All cards accepted</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
