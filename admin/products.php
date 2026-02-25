<?php
/**
 * NextGenShop – Admin Products Management
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo    = db();
$action = input_str('action');
$errors = [];

// ---- Handle form submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid form submission.');
        redirect('/admin/products.php');
    }

    $action_post = input_str('action');

    if (in_array($action_post, ['create', 'edit'], true)) {
        $name              = trim($_POST['name'] ?? '');
        $category_id       = input_int('category_id');
        $price             = (float)($_POST['price'] ?? 0);
        $sale_price        = $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
        $stock             = input_int('stock');
        $short_description = trim($_POST['short_description'] ?? '');
        $description       = trim($_POST['description'] ?? '');
        $sku               = trim($_POST['sku'] ?? '');
        $is_featured       = isset($_POST['is_featured']) ? 1 : 0;
        $status            = in_array($_POST['status'] ?? '', ['active','inactive','draft']) ? $_POST['status'] : 'active';
        $image             = trim($_POST['image'] ?? '');
        $product_id        = input_int('product_id');

        if (mb_strlen($name) < 2) $errors[] = 'Product name is required.';
        if ($category_id < 1)     $errors[] = 'Please select a category.';
        if ($price <= 0)           $errors[] = 'Price must be greater than 0.';

        if (empty($errors)) {
            $slug = slugify($name);

            // Ensure unique slug
            $check = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
            $check->execute([$slug, $product_id]);
            if ($check->fetchColumn()) {
                $slug .= '-' . substr(md5(uniqid()), 0, 6);
            }

            if ($action_post === 'create') {
                $pdo->prepare(
                    'INSERT INTO products
                     (category_id, name, slug, description, short_description, price, sale_price,
                      stock, image, sku, is_featured, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $category_id, $name, $slug, $description, $short_description,
                    $price, $sale_price, $stock, $image, $sku ?: null, $is_featured, $status
                ]);
                flash('success', "Product \"{$name}\" created successfully.");
            } else {
                $pdo->prepare(
                    'UPDATE products SET
                     category_id = ?, name = ?, slug = ?, description = ?, short_description = ?,
                     price = ?, sale_price = ?, stock = ?, image = ?, sku = ?, is_featured = ?, status = ?
                     WHERE id = ?'
                )->execute([
                    $category_id, $name, $slug, $description, $short_description,
                    $price, $sale_price, $stock, $image, $sku ?: null, $is_featured, $status,
                    $product_id
                ]);
                flash('success', "Product \"{$name}\" updated.");
            }
            redirect('/admin/products.php');
        }
    }

    if ($action_post === 'delete') {
        $product_id = input_int('product_id');
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$product_id]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$product_id]);
            flash('success', "Product \"{$name}\" deleted.");
        }
        redirect('/admin/products.php');
    }

    if ($action_post === 'bulk_status') {
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        $status = in_array($_POST['bulk_status'] ?? '', ['active','inactive','draft']) ? $_POST['bulk_status'] : null;
        if (!empty($ids) && $status) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$status], $ids);
            $pdo->prepare("UPDATE products SET status = ? WHERE id IN ($placeholders)")
                ->execute($params);
            flash('success', count($ids) . ' products updated.');
        }
        redirect('/admin/products.php');
    }
}

// ---- Fetch for view/edit ----
$edit_product = null;
if ($action === 'edit' || $action === 'view') {
    $pid = input_int('id');
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$pid]);
    $edit_product = $stmt->fetch();
}

// ---- List with filters ----
$search     = input_str('q');
$cat_filter = input_int('cat', 0);
$status_f   = input_str('status');
$stock_f    = input_str('stock');
$page       = max(1, input_int('page', 1));
$per_page   = 20;
$offset     = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $cat_filter;
}
if ($status_f !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $status_f;
}
if ($stock_f === 'out') {
    $where[] = 'p.stock = 0';
} elseif ($stock_f === 'low') {
    $where[] = 'p.stock > 0 AND p.stock <= 5';
}

$where_sql = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where_sql");
$stmt->execute($params);
$total_prods = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total_prods / $per_page);

$params_page = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $where_sql
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params_page);
$products = $stmt->fetchAll();

$categories = get_categories();

$page_title = 'Products';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-bold mb-0">Products</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" id="addProductBtn">
        <i class="bi bi-plus-lg me-1"></i>Add Product
    </button>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search products…"
                           value="<?= h($search) ?>">
                </div>
            </div>
            <div class="col-sm-2">
                <select name="cat" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $cat_filter === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active"   <?= $status_f === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_f === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="draft"    <?= $status_f === 'draft'    ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <div class="col-sm-2">
                <select name="stock" class="form-select form-select-sm">
                    <option value="">All Stock</option>
                    <option value="out" <?= $stock_f === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    <option value="low" <?= $stock_f === 'low' ? 'selected' : '' ?>>Low Stock (≤5)</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="/admin/products.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Bulk actions form -->
<form method="post" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_status">

    <div class="card border-0 shadow-sm">
        <!-- Bulk bar -->
        <div class="card-header bg-white border-0 d-flex align-items-center gap-3 py-2">
            <small class="text-muted"><?= number_format($total_prods) ?> product<?= $total_prods !== 1 ? 's' : '' ?></small>
            <div class="ms-auto d-flex gap-2">
                <select name="bulk_status" class="form-select form-select-sm" style="width:160px">
                    <option value="">Bulk Status…</option>
                    <option value="active">Set Active</option>
                    <option value="inactive">Set Inactive</option>
                    <option value="draft">Set Draft</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:40px">
                            <input type="checkbox" id="checkAll" class="form-check-input">
                        </th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th class="pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                    <tr class="border-bottom">
                        <td class="ps-4">
                            <input type="checkbox" name="ids[]" value="<?= (int)$prod['id'] ?>"
                                   class="form-check-input row-check">
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= h($prod['image'] ?: 'https://via.placeholder.com/50') ?>"
                                     style="width:45px;height:40px;object-fit:cover" class="rounded" alt="">
                                <div>
                                    <div class="fw-semibold small"><?= h(truncate($prod['name'], 45)) ?></div>
                                    <div class="text-muted" style="font-size:.75rem"><?= h($prod['sku'] ?? '–') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted small"><?= h($prod['category_name'] ?? '–') ?></td>
                        <td>
                            <?php if ($prod['sale_price'] !== null): ?>
                                <span class="text-danger fw-bold"><?= format_price((float)$prod['sale_price']) ?></span>
                                <br><small class="text-decoration-line-through text-muted"><?= format_price((float)$prod['price']) ?></small>
                            <?php else: ?>
                                <?= format_price((float)$prod['price']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?= (int)$prod['stock'] === 0 ? 'text-danger' : ((int)$prod['stock'] <= 5 ? 'text-warning' : 'text-success') ?> fw-bold">
                                <?= (int)$prod['stock'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $prod['status'] === 'active' ? 'success' : ($prod['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($prod['status']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= number_format((int)$prod['views']) ?></td>
                        <td class="pe-4">
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                        data-product='<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" class="m-0"
                                      onsubmit="return confirm('Delete this product?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                                <a href="/pages/product.php?slug=<?= h($prod['slug']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
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
</form>

<!-- Product Create/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="productForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="product_id" id="formProductId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Product Name *</label>
                            <input type="text" name="name" id="fieldName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category *</label>
                            <select name="category_id" id="fieldCategory" class="form-select" required>
                                <option value="">Select…</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" id="fieldPrice" class="form-control"
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Sale Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="sale_price" id="fieldSalePrice" class="form-control"
                                       step="0.01" min="0" placeholder="Leave blank for none">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Stock *</label>
                            <input type="number" name="stock" id="fieldStock" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SKU</label>
                            <input type="text" name="sku" id="fieldSku" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="fieldStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Image URL</label>
                            <input type="url" name="image" id="fieldImage" class="form-control"
                                   placeholder="https://…">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Short Description</label>
                            <input type="text" name="short_description" id="fieldShortDesc" class="form-control"
                                   maxlength="500">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Description</label>
                            <textarea name="description" id="fieldDesc" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_featured" id="fieldFeatured" class="form-check-input" value="1">
                                <label class="form-check-label fw-semibold" for="fieldFeatured">Featured Product</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Check all checkboxes
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});

// Edit button – populate modal
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const p = JSON.parse(btn.dataset.product);
        document.getElementById('modalTitle').textContent = 'Edit Product';
        document.getElementById('formAction').value    = 'edit';
        document.getElementById('formProductId').value = p.id;
        document.getElementById('fieldName').value     = p.name;
        document.getElementById('fieldPrice').value    = p.price;
        document.getElementById('fieldSalePrice').value= p.sale_price ?? '';
        document.getElementById('fieldStock').value    = p.stock;
        document.getElementById('fieldSku').value      = p.sku ?? '';
        document.getElementById('fieldStatus').value   = p.status;
        document.getElementById('fieldImage').value    = p.image ?? '';
        document.getElementById('fieldShortDesc').value= p.short_description ?? '';
        document.getElementById('fieldDesc').value     = p.description ?? '';
        document.getElementById('fieldFeatured').checked = p.is_featured == 1;
        // Set category
        const catSel = document.getElementById('fieldCategory');
        for (let opt of catSel.options) {
            if (opt.value == p.category_id) { opt.selected = true; break; }
        }
        document.getElementById('modalSubmitBtn').textContent = 'Update Product';
        new bootstrap.Modal(document.getElementById('productModal')).show();
    });
});

// Reset modal on add
document.getElementById('addProductBtn')?.addEventListener('click', () => {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value    = 'create';
    document.getElementById('formProductId').value = '0';
    document.getElementById('productForm').reset();
    document.getElementById('modalSubmitBtn').textContent = 'Save Product';
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
