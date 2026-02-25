<?php
/**
 * NextGenShop – Wishlist
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// ----- Handle POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/pages/wishlist.php');
    }

    $action     = input_str('action');
    $product_id = input_int('product_id');
    $pdo        = db();

    if ($action === 'add' && $product_id > 0) {
        // Verify product exists
        $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$product_id]);
        if ($stmt->fetchColumn()) {
            try {
                $pdo->prepare(
                    'INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)'
                )->execute([$_SESSION['user_id'], $product_id]);
                flash('success', 'Product added to your wishlist!');
            } catch (PDOException $e) {
                flash('error', 'Could not add to wishlist.');
            }
        }
    } elseif ($action === 'remove' && $product_id > 0) {
        $pdo->prepare(
            'DELETE FROM wishlist WHERE user_id = ? AND product_id = ?'
        )->execute([$_SESSION['user_id'], $product_id]);
        flash('info', 'Product removed from wishlist.');
    } elseif ($action === 'move_to_cart' && $product_id > 0) {
        // Add to cart
        $stmt = $pdo->prepare(
            'SELECT id, stock FROM products WHERE id = ? AND status = "active" AND stock > 0 LIMIT 1'
        );
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product) {
            // Upsert to cart
            $pdo->prepare(
                'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
            )->execute([$_SESSION['user_id'], $product_id, (int)$product['stock']]);
            flash('success', 'Item moved to cart!');
        } else {
            flash('error', 'Product is no longer available.');
        }
    } elseif ($action === 'clear') {
        $pdo->prepare('DELETE FROM wishlist WHERE user_id = ?')
            ->execute([$_SESSION['user_id']]);
        flash('info', 'Wishlist cleared.');
    }

    $redirect = input_str('redirect') ?: '/pages/wishlist.php';
    redirect($redirect);
}

// ----- Fetch wishlist -----
$stmt = db()->prepare(
    'SELECT w.id AS wish_id, w.created_at AS wished_at,
            p.id, p.name, p.slug, p.image, p.price, p.sale_price, p.stock, p.status,
            c.name AS category_name
     FROM wishlist w
     JOIN products p ON p.id = w.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE w.user_id = ?
     ORDER BY w.created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$wish_items = $stmt->fetchAll();

$page_title = 'My Wishlist';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-heart-fill me-2 text-danger"></i>My Wishlist
            <?php if (!empty($wish_items)): ?>
                <span class="badge bg-secondary fs-6 ms-1"><?= count($wish_items) ?></span>
            <?php endif; ?>
        </h1>
        <?php if (!empty($wish_items)): ?>
        <form method="post" onsubmit="return confirm('Clear your entire wishlist?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Clear All
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($wish_items)): ?>
        <div class="text-center py-5">
            <i class="bi bi-heart display-3 text-muted opacity-50"></i>
            <h4 class="mt-3 text-muted">Your wishlist is empty</h4>
            <p class="text-muted">Save items you love to your wishlist and shop them later.</p>
            <a href="/index.php" class="btn btn-primary mt-2">
                <i class="bi bi-bag me-2"></i>Explore Products
            </a>
        </div>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($wish_items as $item):
                $unit_price = (float)($item['sale_price'] ?? $item['price']);
                $in_stock   = $item['status'] === 'active' && (int)$item['stock'] > 0;
            ?>
            <div class="col">
                <div class="card border-0 shadow-sm h-100 product-card">
                    <!-- Remove button -->
                    <div class="position-absolute top-0 end-0 p-2" style="z-index:2">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm"
                                    title="Remove from wishlist">
                                <i class="bi bi-x text-danger"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Image -->
                    <a href="/pages/product.php?slug=<?= h($item['slug']) ?>" class="overflow-hidden d-block product-img-wrap">
                        <img src="<?= h($item['image'] ?: 'https://via.placeholder.com/300x200?text=No+Image') ?>"
                             alt="<?= h($item['name']) ?>"
                             class="card-img-top product-img" loading="lazy">
                        <?php if (!$in_stock): ?>
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white bg-opacity-75">
                                <span class="badge bg-secondary fs-6">Out of Stock</span>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="card-body d-flex flex-column p-3">
                        <small class="text-muted mb-1"><?= h($item['category_name']) ?></small>
                        <h6 class="card-title mb-2 lh-sm">
                            <a href="/pages/product.php?slug=<?= h($item['slug']) ?>"
                               class="text-decoration-none text-dark">
                                <?= h(truncate($item['name'], 55)) ?>
                            </a>
                        </h6>

                        <!-- Price -->
                        <div class="mb-3 mt-auto">
                            <?php if ($item['sale_price'] !== null): ?>
                                <span class="fw-bold text-danger"><?= format_price((float)$item['sale_price']) ?></span>
                                <span class="text-decoration-line-through text-muted small ms-1">
                                    <?= format_price((float)$item['price']) ?>
                                </span>
                            <?php else: ?>
                                <span class="fw-bold"><?= format_price((float)$item['price']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <?php if ($in_stock): ?>
                        <form method="post" action="/pages/wishlist.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="move_to_cart">
                            <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-cart-plus me-1"></i>Move to Cart
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm w-100" disabled>
                            Out of Stock
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-transparent border-0 pt-0 px-3 pb-2">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>Added <?= date('M j, Y', strtotime($item['wished_at'])) ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
