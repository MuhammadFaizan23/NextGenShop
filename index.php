<?php
/**
 * NextGenShop – Homepage / Product Listing
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// ----- Filters & Pagination -----
$search      = input_str('q');
$category    = input_str('category');
$sort        = input_str('sort', 'newest');
$page        = max(1, input_int('page', 1));
$per_page    = ITEMS_PER_PAGE;
$offset      = ($page - 1) * $per_page;

// Build base query
$where  = ['p.status = "active"'];
$params = [];

if ($search !== '') {
    $where[]  = 'MATCH(p.name, p.description, p.short_description) AGAINST(? IN BOOLEAN MODE)';
    $params[] = $search . '*';
}

if ($category !== '') {
    $where[]  = 'c.slug = ?';
    $params[] = $category;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$order_map = [
    'newest'    => 'p.created_at DESC',
    'oldest'    => 'p.created_at ASC',
    'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc'=> 'COALESCE(p.sale_price, p.price) DESC',
    'popular'   => 'p.views DESC',
    'name_asc'  => 'p.name ASC',
];
$order_sql = $order_map[$sort] ?? 'p.created_at DESC';

// Count query (uses index)
$count_sql = "SELECT COUNT(*) FROM products p
              JOIN categories c ON c.id = p.category_id
              $where_sql";
$stmt = db()->prepare($count_sql);
$stmt->execute($params);
$total_products = (int) $stmt->fetchColumn();
$total_pages    = (int) ceil($total_products / $per_page);

// Product query – fetch only what's needed
$sql = "SELECT p.id, p.name, p.slug, p.short_description, p.price, p.sale_price,
               p.image, p.stock, p.is_featured, p.views,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.id = p.category_id
        $where_sql
        ORDER BY $order_sql
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Featured products (for hero section – only on first page without filters)
$featured_products = [];
if ($page === 1 && $search === '' && $category === '') {
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.short_description, p.price, p.sale_price,
                p.image, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.status = "active" AND p.is_featured = 1
         ORDER BY p.created_at DESC LIMIT 4'
    );
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
}

$categories   = get_categories();
$active_category = null;
if ($category !== '') {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category) {
            $active_category = $cat;
            break;
        }
    }
}

$page_title = $active_category
    ? h($active_category['name'])
    : ($search ? 'Search: ' . h($search) : 'Shop All Products');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section (shown only on homepage without filters) -->
<?php if ($page === 1 && $search === '' && $category === '' && !empty($featured_products)): ?>
<section class="hero-section py-5 mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <span class="badge bg-primary-subtle text-primary fw-semibold mb-3 px-3 py-2">
                    New Arrivals
                </span>
                <h1 class="display-5 fw-bold mb-3 lh-sm">
                    Discover the <span class="text-primary">Next Generation</span> of Shopping
                </h1>
                <p class="lead text-muted mb-4">
                    Premium products, unbeatable prices, and lightning-fast delivery.
                    Shop smarter with NextGenShop.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#products" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-bag-fill me-2"></i>Shop Now
                    </a>
                    <?php if (!is_logged_in()): ?>
                    <a href="/pages/register.php" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="bi bi-person-plus me-2"></i>Join Free
                    </a>
                    <?php endif; ?>
                </div>
                <!-- Stats -->
                <div class="row row-cols-3 g-3 mt-4">
                    <div class="col text-center">
                        <div class="fw-bold fs-4 text-primary">10K+</div>
                        <div class="small text-muted">Products</div>
                    </div>
                    <div class="col text-center">
                        <div class="fw-bold fs-4 text-primary">50K+</div>
                        <div class="small text-muted">Customers</div>
                    </div>
                    <div class="col text-center">
                        <div class="fw-bold fs-4 text-primary">4.8★</div>
                        <div class="small text-muted">Rating</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <?php foreach (array_slice($featured_products, 0, 4) as $i => $fp): ?>
                    <div class="col-6">
                        <a href="/pages/product.php?slug=<?= h($fp['slug']) ?>" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 hero-product-card">
                                <img src="<?= h($fp['image']) ?>" class="card-img-top"
                                     alt="<?= h($fp['name']) ?>"
                                     style="height:140px;object-fit:cover">
                                <div class="card-body p-2">
                                    <p class="card-text small fw-semibold text-dark mb-1 lh-sm">
                                        <?= h(truncate($fp['name'], 40)) ?>
                                    </p>
                                    <span class="text-primary fw-bold small">
                                        <?= format_price((float)($fp['sale_price'] ?: $fp['price'])) ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Category Pills -->
<section class="mb-4">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="/index.php" class="btn btn-sm <?= $category === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-grid me-1"></i>All
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="/index.php?category=<?= h($cat['slug']) ?>"
                   class="btn btn-sm <?= $category === $cat['slug'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= h($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Products Section -->
<section id="products" class="py-4">
    <div class="container">
        <!-- Section header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <h2 class="h4 fw-bold mb-1">
                    <?php if ($search !== ''): ?>
                        Search Results for "<em><?= h($search) ?></em>"
                    <?php elseif ($active_category): ?>
                        <?= h($active_category['name']) ?>
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h2>
                <p class="text-muted small mb-0">
                    <?= number_format($total_products) ?> product<?= $total_products !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <!-- Sort -->
            <form method="get" class="d-flex align-items-center gap-2">
                <?php if ($search): ?><input type="hidden" name="q" value="<?= h($search) ?>"><?php endif; ?>
                <?php if ($category): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
                <label for="sort" class="text-muted small fw-semibold mb-0 text-nowrap">Sort by:</label>
                <select id="sort" name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="newest"    <?= $sort === 'newest'    ? 'selected' : '' ?>>Newest</option>
                    <option value="popular"   <?= $sort === 'popular'   ? 'selected' : '' ?>>Most Popular</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
                    <option value="price_desc"<?= $sort === 'price_desc'? 'selected' : '' ?>>Price: High → Low</option>
                    <option value="name_asc"  <?= $sort === 'name_asc'  ? 'selected' : '' ?>>Name A–Z</option>
                </select>
            </form>
        </div>

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-search display-4 text-muted opacity-50"></i>
                <p class="mt-3 text-muted fs-5">No products found.</p>
                <a href="/index.php" class="btn btn-primary mt-2">Browse All Products</a>
            </div>
        <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
            <?php foreach ($products as $product): ?>
            <div class="col">
                <?php
                $sale   = $product['sale_price'] !== null;
                $price  = $sale ? (float)$product['sale_price'] : (float)$product['price'];
                $rating = product_avg_rating((int)$product['id']);
                ?>
                <div class="card product-card h-100 border-0 shadow-sm">
                    <!-- Image -->
                    <div class="position-relative overflow-hidden product-img-wrap">
                        <a href="/pages/product.php?slug=<?= h($product['slug']) ?>">
                            <img src="<?= h($product['image'] ?: 'https://via.placeholder.com/300x200?text=No+Image') ?>"
                                 alt="<?= h($product['name']) ?>"
                                 class="card-img-top product-img"
                                 loading="lazy">
                        </a>
                        <!-- Badges -->
                        <div class="position-absolute top-0 start-0 p-2 d-flex flex-column gap-1">
                            <?php if ($sale): ?>
                                <span class="badge bg-danger">SALE</span>
                            <?php endif; ?>
                            <?php if ($product['is_featured']): ?>
                                <span class="badge bg-warning text-dark">Featured</span>
                            <?php endif; ?>
                            <?php if ((int)$product['stock'] === 0): ?>
                                <span class="badge bg-secondary">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <!-- Quick actions overlay -->
                        <div class="product-actions position-absolute bottom-0 start-0 end-0 p-2 d-flex gap-2 justify-content-center">
                            <?php if ((int)$product['stock'] > 0): ?>
                            <form method="post" action="/pages/cart.php" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (is_logged_in()): ?>
                            <form method="post" action="/pages/wishlist.php" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="<?= in_wishlist((int)$product['id']) ? 'remove' : 'add' ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <button type="submit" class="btn btn-outline-light btn-sm"
                                        title="<?= in_wishlist((int)$product['id']) ? 'Remove from Wishlist' : 'Add to Wishlist' ?>">
                                    <i class="bi bi-heart<?= in_wishlist((int)$product['id']) ? '-fill text-danger' : '' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Card body -->
                    <div class="card-body d-flex flex-column p-3">
                        <p class="text-muted small mb-1"><?= h($product['category_name']) ?></p>
                        <h6 class="card-title mb-2 lh-sm">
                            <a href="/pages/product.php?slug=<?= h($product['slug']) ?>"
                               class="text-decoration-none text-dark stretched-link-inner">
                                <?= h(truncate($product['name'], 55)) ?>
                            </a>
                        </h6>
                        <!-- Stars -->
                        <?php if ($rating > 0): ?>
                        <div class="mb-1"><?= star_rating($rating) ?> <small class="text-muted">(<?= $rating ?>)</small></div>
                        <?php endif; ?>
                        <!-- Price -->
                        <div class="mt-auto">
                            <?php if ($sale): ?>
                                <span class="fw-bold text-danger fs-6"><?= format_price((float)$product['sale_price']) ?></span>
                                <span class="text-muted text-decoration-line-through small ms-1">
                                    <?= format_price((float)$product['price']) ?>
                                </span>
                            <?php else: ?>
                                <span class="fw-bold fs-6"><?= format_price((float)$product['price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-5 d-flex justify-content-center" aria-label="Product pages">
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Why Choose Us -->
<?php if ($page === 1 && $search === '' && $category === ''): ?>
<section class="bg-light py-5 mt-4">
    <div class="container">
        <div class="row row-cols-2 row-cols-md-4 g-4 text-center">
            <div class="col">
                <div class="p-3">
                    <i class="bi bi-truck display-5 text-primary mb-3 d-block"></i>
                    <h6 class="fw-bold">Free Shipping</h6>
                    <p class="text-muted small mb-0">On orders over $50</p>
                </div>
            </div>
            <div class="col">
                <div class="p-3">
                    <i class="bi bi-shield-check display-5 text-primary mb-3 d-block"></i>
                    <h6 class="fw-bold">Secure Payment</h6>
                    <p class="text-muted small mb-0">100% secure transactions</p>
                </div>
            </div>
            <div class="col">
                <div class="p-3">
                    <i class="bi bi-arrow-counterclockwise display-5 text-primary mb-3 d-block"></i>
                    <h6 class="fw-bold">Easy Returns</h6>
                    <p class="text-muted small mb-0">30-day return policy</p>
                </div>
            </div>
            <div class="col">
                <div class="p-3">
                    <i class="bi bi-headset display-5 text-primary mb-3 d-block"></i>
                    <h6 class="fw-bold">24/7 Support</h6>
                    <p class="text-muted small mb-0">Always here to help</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
