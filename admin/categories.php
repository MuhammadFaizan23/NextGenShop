<?php
/**
 * NextGenShop – Admin Categories Management
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid form submission.');
        redirect('/admin/categories.php');
    }

    $action = input_str('action');
    $cat_id = input_int('cat_id');

    if (in_array($action, ['create', 'edit'], true)) {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($name) < 2) {
            $errors[] = 'Category name is required.';
        }

        if (empty($errors)) {
            $slug = slugify($name);
            $check = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
            $check->execute([$slug, $cat_id]);
            if ($check->fetchColumn()) {
                $slug .= '-' . substr(md5(uniqid()), 0, 5);
            }

            if ($action === 'create') {
                $pdo->prepare(
                    'INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)'
                )->execute([$name, $slug, $description]);
                flash('success', "Category \"{$name}\" created.");
            } else {
                $pdo->prepare(
                    'UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?'
                )->execute([$name, $slug, $description, $cat_id]);
                flash('success', "Category \"{$name}\" updated.");
            }
            redirect('/admin/categories.php');
        }
    }

    if ($action === 'delete') {
        // Check if category has products
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$cat_id]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash('error', 'Cannot delete a category that has products.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$cat_id]);
            flash('success', 'Category deleted.');
        }
        redirect('/admin/categories.php');
    }
}

$categories = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c
     ORDER BY c.sort_order, c.name'
)->fetchAll();

$page_title = 'Categories';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-bold mb-0">Categories</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" id="addCatBtn">
        <i class="bi bi-plus-lg me-1"></i>Add Category
    </button>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th class="text-center">Products</th>
                    <th class="pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No categories yet.</td></tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr class="border-bottom">
                    <td class="ps-4 fw-semibold"><?= h($cat['name']) ?></td>
                    <td class="text-muted small"><code><?= h($cat['slug']) ?></code></td>
                    <td class="text-muted small"><?= h(truncate($cat['description'] ?? '', 60)) ?></td>
                    <td class="text-center">
                        <a href="/admin/products.php?cat=<?= (int)$cat['id'] ?>" class="text-decoration-none">
                            <?= (int)$cat['product_count'] ?>
                        </a>
                    </td>
                    <td class="pe-4">
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-cat-btn"
                                    data-cat='<?= json_encode($cat, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ((int)$cat['product_count'] === 0): ?>
                            <form method="post" class="m-0" onsubmit="return confirm('Delete this category?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="catModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="catForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="catAction" value="create">
                <input type="hidden" name="cat_id" id="catId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name *</label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="catSubmitBtn">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-cat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const c = JSON.parse(btn.dataset.cat);
        document.getElementById('catModalTitle').textContent = 'Edit Category';
        document.getElementById('catAction').value = 'edit';
        document.getElementById('catId').value     = c.id;
        document.getElementById('catName').value   = c.name;
        document.getElementById('catDesc').value   = c.description ?? '';
        document.getElementById('catSubmitBtn').textContent = 'Update Category';
        new bootstrap.Modal(document.getElementById('catModal')).show();
    });
});
document.getElementById('addCatBtn')?.addEventListener('click', () => {
    document.getElementById('catModalTitle').textContent = 'Add Category';
    document.getElementById('catAction').value = 'create';
    document.getElementById('catId').value     = '0';
    document.getElementById('catForm').reset();
    document.getElementById('catSubmitBtn').textContent = 'Save Category';
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
