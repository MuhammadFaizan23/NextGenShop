<?php
/**
 * NextGenShop – Admin Reviews Management
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $action    = input_str('action');
        $review_id = input_int('review_id');

        if ($action === 'approve') {
            $pdo->prepare('UPDATE reviews SET status = "approved" WHERE id = ?')->execute([$review_id]);
            flash('success', 'Review approved.');
        } elseif ($action === 'reject') {
            $pdo->prepare('UPDATE reviews SET status = "rejected" WHERE id = ?')->execute([$review_id]);
            flash('info', 'Review rejected.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$review_id]);
            flash('success', 'Review deleted.');
        }
    }
    redirect('/admin/reviews.php');
}

$status_f = input_str('status', 'pending');
$page     = max(1, input_int('page', 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
if ($status_f !== '') {
    $where[]  = 'r.status = ?';
    $params[] = $status_f;
}
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $where_sql");
$stmt->execute($params);
$total  = (int)$stmt->fetchColumn();
$pages  = (int)ceil($total / $per_page);

$stmt = $pdo->prepare(
    "SELECT r.*, p.name AS product_name, p.slug AS product_slug, u.name AS user_name
     FROM reviews r
     JOIN products p ON p.id = r.product_id
     JOIN users u ON u.id = r.user_id
     WHERE $where_sql
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$per_page, $offset]));
$reviews = $stmt->fetchAll();

$page_title = 'Reviews';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h4 fw-bold mb-0">Reviews</h1>
    <div class="d-flex gap-2">
        <?php foreach (['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', '' => 'secondary'] as $s => $c): ?>
        <a href="/admin/reviews.php?status=<?= $s ?>"
           class="btn btn-sm btn-<?= $status_f === $s ? '' : 'outline-' ?><?= $c ?>">
            <?= $s === '' ? 'All' : ucfirst($s) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Review</th>
                    <th>Product</th>
                    <th>Rating</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No reviews found.</td></tr>
            <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
            <tr class="border-bottom">
                <td class="ps-4" style="max-width:300px">
                    <div class="fw-semibold small"><?= h($rev['user_name']) ?></div>
                    <?php if ($rev['title']): ?>
                        <div class="text-muted small fw-semibold"><?= h($rev['title']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted small"><?= h(truncate($rev['body'] ?? '', 80)) ?></div>
                </td>
                <td>
                    <a href="/pages/product.php?slug=<?= h($rev['product_slug']) ?>"
                       target="_blank" class="text-decoration-none small">
                        <?= h(truncate($rev['product_name'], 40)) ?>
                    </a>
                </td>
                <td><?= star_rating((float)$rev['rating']) ?></td>
                <td class="text-muted small"><?= date('M j, Y', strtotime($rev['created_at'])) ?></td>
                <td>
                    <span class="badge bg-<?= $rev['status'] === 'approved' ? 'success' : ($rev['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= ucfirst($rev['status']) ?>
                    </span>
                </td>
                <td class="pe-4">
                    <div class="d-flex gap-1">
                        <?php if ($rev['status'] !== 'approved'): ?>
                        <form method="post" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($rev['status'] !== 'rejected'): ?>
                        <form method="post" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="Reject">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="m-0" onsubmit="return confirm('Delete this review?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-center">
        <ul class="pagination pagination-sm mb-0">
            <?php for ($p = max(1, $page-2); $p <= min($pages, $page+2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
