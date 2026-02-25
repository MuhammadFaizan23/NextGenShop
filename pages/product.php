<?php
/**
 * NextGenShop – Product Detail Page
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = input_str('slug');
if ($slug === '') {
    redirect('/index.php');
}

// Fetch product with category
$stmt = db()->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.status = "active"
     LIMIT 1'
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('/index.php');
}

// Increment view count (non-blocking, best-effort)
db()->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$product['id']]);

// Fetch approved reviews
$stmt = db()->prepare(
    'SELECT r.*, u.name AS user_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ? AND r.status = "approved"
     ORDER BY r.created_at DESC LIMIT 20'
);
$stmt->execute([$product['id']]);
$reviews = $stmt->fetchAll();

$avg_rating    = product_avg_rating((int)$product['id']);
$review_count  = count($reviews);

// Related products (same category, excluding current)
$stmt = db()->prepare(
    'SELECT id, name, slug, price, sale_price, image
     FROM products
     WHERE category_id = ? AND id != ? AND status = "active"
     ORDER BY is_featured DESC, views DESC
     LIMIT 4'
);
$stmt->execute([$product['category_id'], $product['id']]);
$related = $stmt->fetchAll();

// Handle review submission
$review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) {
        flash('error', 'Please log in to leave a review.');
        redirect('/pages/login.php');
    }
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $review_error = 'Invalid request.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $body   = trim($_POST['body'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $review_error = 'Please select a rating between 1 and 5.';
        } elseif (mb_strlen($body) < 10) {
            $review_error = 'Review must be at least 10 characters.';
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO reviews (product_id, user_id, rating, title, body, status)
                     VALUES (?, ?, ?, ?, ?, "pending")'
                );
                $stmt->execute([$product['id'], $_SESSION['user_id'], $rating, $title, $body]);
                flash('success', 'Your review has been submitted and is pending approval. Thank you!');
                redirect('/pages/product.php?slug=' . urlencode($slug));
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $review_error = 'You have already reviewed this product.';
                } else {
                    $review_error = 'Could not submit review. Please try again.';
                }
            }
        }
    }
}

$page_title = $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item">
                <a href="/index.php?category=<?= h($product['category_slug']) ?>" class="text-decoration-none">
                    <?= h($product['category_name']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active"><?= h(truncate($product['name'], 40)) ?></li>
        </ol>
    </nav>

    <!-- Product detail -->
    <div class="row g-4 mb-5">
        <!-- Image -->
        <div class="col-lg-5">
            <div class="rounded-3 overflow-hidden bg-light text-center p-3" style="min-height:350px">
                <img id="mainProductImage"
                     src="<?= h($product['image'] ?: 'https://via.placeholder.com/600x450?text=No+Image') ?>"
                     alt="<?= h($product['name']) ?>"
                     class="img-fluid rounded-2"
                     style="max-height:380px;object-fit:contain">
            </div>
            <?php
            $extra_images = [];
            if (!empty($product['images'])) {
                $extra_images = json_decode($product['images'], true) ?: [];
            }
            if (!empty($product['image'])) {
                array_unshift($extra_images, $product['image']);
            }
            if (count($extra_images) > 1): ?>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <?php foreach ($extra_images as $img): ?>
                    <img src="<?= h($img) ?>" alt="Thumbnail" class="img-thumbnail product-thumb"
                         style="width:70px;height:60px;object-fit:cover;cursor:pointer"
                         onclick="document.getElementById('mainProductImage').src=this.src">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="col-lg-7">
            <span class="badge bg-secondary-subtle text-secondary mb-2">
                <?= h($product['category_name']) ?>
            </span>
            <h1 class="h3 fw-bold mb-2"><?= h($product['name']) ?></h1>

            <!-- Rating -->
            <?php if ($avg_rating > 0): ?>
            <div class="mb-3 d-flex align-items-center gap-2">
                <?= star_rating($avg_rating) ?>
                <span class="text-muted small"><?= $avg_rating ?> / 5 (<?= $review_count ?> review<?= $review_count !== 1 ? 's' : '' ?>)</span>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-3">No reviews yet – be the first!</p>
            <?php endif; ?>

            <!-- Price -->
            <div class="mb-4">
                <?php if ($product['sale_price'] !== null): ?>
                    <span class="display-6 fw-bold text-danger"><?= format_price((float)$product['sale_price']) ?></span>
                    <span class="fs-5 text-muted text-decoration-line-through ms-2"><?= format_price((float)$product['price']) ?></span>
                    <span class="badge bg-danger ms-2">
                        <?= round((1 - $product['sale_price'] / $product['price']) * 100) ?>% OFF
                    </span>
                <?php else: ?>
                    <span class="display-6 fw-bold"><?= format_price((float)$product['price']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Short description -->
            <?php if (!empty($product['short_description'])): ?>
                <p class="text-muted mb-4"><?= h($product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Stock status -->
            <div class="mb-4">
                <?php if ((int)$product['stock'] > 10): ?>
                    <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>In Stock</span>
                <?php elseif ((int)$product['stock'] > 0): ?>
                    <span class="text-warning fw-semibold">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>Only <?= (int)$product['stock'] ?> left!
                    </span>
                <?php else: ?>
                    <span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Out of Stock</span>
                <?php endif; ?>
                <?php if (!empty($product['sku'])): ?>
                    <span class="text-muted small ms-3">SKU: <?= h($product['sku']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Add to cart form -->
            <?php if ((int)$product['stock'] > 0): ?>
            <form method="post" action="/pages/cart.php" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="redirect" value="/pages/product.php?slug=<?= urlencode($slug) ?>">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="input-group" style="max-width:140px">
                        <button type="button" class="btn btn-outline-secondary qty-btn" data-action="dec">−</button>
                        <input type="number" id="qty" name="quantity" value="1" min="1"
                               max="<?= min((int)$product['stock'], 99) ?>"
                               class="form-control text-center">
                        <button type="button" class="btn btn-outline-secondary qty-btn" data-action="inc">+</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-cart-plus me-2"></i>Add to Cart
                    </button>
                    <?php if (is_logged_in()): ?>
                    <button type="submit" name="buy_now" value="1" class="btn btn-success btn-lg px-4">
                        <i class="bi bi-lightning-fill me-2"></i>Buy Now
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            <?php else: ?>
            <button class="btn btn-secondary btn-lg px-4" disabled>
                <i class="bi bi-x-circle me-2"></i>Out of Stock
            </button>
            <?php endif; ?>

            <!-- Wishlist button -->
            <?php if (is_logged_in()): ?>
            <form method="post" action="/pages/wishlist.php" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= in_wishlist((int)$product['id']) ? 'remove' : 'add' ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="redirect" value="/pages/product.php?slug=<?= urlencode($slug) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-heart<?= in_wishlist((int)$product['id']) ? '-fill' : '' ?> me-1"></i>
                    <?= in_wishlist((int)$product['id']) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                </button>
            </form>
            <?php endif; ?>

            <!-- Trust badges -->
            <div class="d-flex flex-wrap gap-3 mt-4 pt-3 border-top">
                <small class="text-muted"><i class="bi bi-truck text-primary me-1"></i>Free shipping over $50</small>
                <small class="text-muted"><i class="bi bi-shield-check text-primary me-1"></i>Secure checkout</small>
                <small class="text-muted"><i class="bi bi-arrow-counterclockwise text-primary me-1"></i>30-day returns</small>
            </div>
        </div>
    </div>

    <!-- Description tabs -->
    <div class="mb-5">
        <ul class="nav nav-tabs" id="productTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#desc-tab">Description</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews-tab">
                    Reviews (<?= $review_count ?>)
                </button>
            </li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom p-4">
            <div class="tab-pane fade show active" id="desc-tab">
                <?php if (!empty($product['description'])): ?>
                    <div class="text-muted lh-lg"><?= nl2br(h($product['description'])) ?></div>
                <?php else: ?>
                    <p class="text-muted">No description available.</p>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="reviews-tab">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $rev): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <strong><?= h($rev['user_name']) ?></strong>
                                <span class="ms-2"><?= star_rating((float)$rev['rating']) ?></span>
                            </div>
                            <small class="text-muted"><?= date('M j, Y', strtotime($rev['created_at'])) ?></small>
                        </div>
                        <?php if ($rev['title']): ?>
                            <p class="fw-semibold mb-1"><?= h($rev['title']) ?></p>
                        <?php endif; ?>
                        <p class="text-muted mb-0 small"><?= nl2br(h($rev['body'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No reviews yet for this product.</p>
                <?php endif; ?>

                <!-- Review form -->
                <?php if (is_logged_in()): ?>
                <div class="mt-4 pt-3 border-top">
                    <h5 class="fw-bold mb-3">Write a Review</h5>
                    <?php if ($review_error): ?>
                        <div class="alert alert-danger"><?= h($review_error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rating *</label>
                            <div class="star-select d-flex gap-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="rating"
                                               id="star<?= $i ?>" value="<?= $i ?>" required>
                                        <label class="form-check-label" for="star<?= $i ?>"><?= $i ?>★</label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="rev_title" class="form-label fw-semibold">Title (optional)</label>
                            <input type="text" id="rev_title" name="title" class="form-control"
                                   placeholder="Summarize your review" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label for="rev_body" class="form-label fw-semibold">Review *</label>
                            <textarea id="rev_body" name="body" class="form-control" rows="4"
                                      placeholder="Share your experience with this product…" required></textarea>
                        </div>
                        <button type="submit" name="submit_review" value="1" class="btn btn-primary">
                            Submit Review
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <p class="mt-4 text-muted">
                    <a href="/pages/login.php">Log in</a> to write a review.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <section>
        <h3 class="h5 fw-bold mb-4">Related Products</h3>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($related as $rel): ?>
            <div class="col">
                <div class="card border-0 shadow-sm h-100 product-card">
                    <a href="/pages/product.php?slug=<?= h($rel['slug']) ?>">
                        <img src="<?= h($rel['image'] ?: 'https://via.placeholder.com/300x200?text=No+Image') ?>"
                             alt="<?= h($rel['name']) ?>"
                             class="card-img-top product-img" loading="lazy">
                    </a>
                    <div class="card-body p-2">
                        <h6 class="card-title small mb-1">
                            <a href="/pages/product.php?slug=<?= h($rel['slug']) ?>"
                               class="text-decoration-none text-dark">
                                <?= h(truncate($rel['name'], 50)) ?>
                            </a>
                        </h6>
                        <?php if ($rel['sale_price'] !== null): ?>
                            <span class="text-danger fw-bold small"><?= format_price((float)$rel['sale_price']) ?></span>
                            <span class="text-muted text-decoration-line-through" style="font-size:.75rem">
                                <?= format_price((float)$rel['price']) ?>
                            </span>
                        <?php else: ?>
                            <span class="fw-bold small"><?= format_price((float)$rel['price']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
// Quantity selector
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById('qty');
        const val   = parseInt(input.value) || 1;
        const max   = parseInt(input.max) || 99;
        if (btn.dataset.action === 'inc' && val < max) input.value = val + 1;
        if (btn.dataset.action === 'dec' && val > 1)   input.value = val - 1;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
