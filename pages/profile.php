<?php
/**
 * NextGenShop – User Profile
 */
define('NEXTGENSHOP', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$errors  = [];
$success = false;
$user    = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $new_pw  = $_POST['new_password'] ?? '';
        $cur_pw  = $_POST['current_password'] ?? '';

        if (mb_strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }

        if ($new_pw !== '') {
            if (mb_strlen($new_pw) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } else {
                // Re-fetch password hash for verification
                $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$_SESSION['user_id']]);
                $hash = $stmt->fetchColumn();
                if (!password_verify($cur_pw, $hash)) {
                    $errors[] = 'Current password is incorrect.';
                }
            }
        }

        if (empty($errors)) {
            $params = [$name, $phone, $address, $_SESSION['user_id']];
            $sql    = 'UPDATE users SET name = ?, phone = ?, address = ?';

            if ($new_pw !== '') {
                $sql    .= ', password = ?';
                $params  = [$name, $phone, $address, password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]), $_SESSION['user_id']];
            }
            $sql .= ' WHERE id = ?';

            db()->prepare($sql)->execute($params);
            $_SESSION['user_name'] = $name;
            flash('success', 'Profile updated successfully.');
            redirect('/pages/profile.php');
        }
    }
}

// Stats
$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$order_count = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$wish_count = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$cart_total = (int)$stmt->fetchColumn();

$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm text-center p-4 mb-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center"
                         style="width:80px;height:80px">
                        <i class="bi bi-person-fill text-white" style="font-size:2rem"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-0"><?= h($user['name']) ?></h5>
                <p class="text-muted small mb-3"><?= h($user['email']) ?></p>
                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
            </div>

            <!-- Stats -->
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded bg-primary-subtle p-2"><i class="bi bi-bag text-primary fs-5"></i></div>
                    <div>
                        <div class="fw-bold"><?= $order_count ?></div>
                        <div class="small text-muted">Orders</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded bg-danger-subtle p-2"><i class="bi bi-heart text-danger fs-5"></i></div>
                    <div>
                        <div class="fw-bold"><?= $wish_count ?></div>
                        <div class="small text-muted">Wishlist Items</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded bg-success-subtle p-2"><i class="bi bi-cart text-success fs-5"></i></div>
                    <div>
                        <div class="fw-bold"><?= $cart_total ?></div>
                        <div class="small text-muted">Cart Items</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-lg-9">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-tab">
                                <i class="bi bi-person me-1"></i>Profile
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#security-tab">
                                <i class="bi bi-shield-lock me-1"></i>Security
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- Profile info -->
                        <div class="tab-pane fade show active" id="profile-tab">
                            <form method="post">
                                <?= csrf_field() ?>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Full Name</label>
                                        <input type="text" name="name" class="form-control"
                                               value="<?= h($user['name']) ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Email Address</label>
                                        <input type="email" class="form-control"
                                               value="<?= h($user['email']) ?>" disabled>
                                        <div class="form-text">Email cannot be changed.</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control"
                                               value="<?= h($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Address</label>
                                        <textarea name="address" class="form-control" rows="3"><?= h($user['address'] ?? '') ?></textarea>
                                    </div>
                                    <!-- Hidden fields to satisfy password fields (not changing) -->
                                    <input type="hidden" name="current_password" value="">
                                    <input type="hidden" name="new_password" value="">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check2 me-1"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Security -->
                        <div class="tab-pane fade" id="security-tab">
                            <form method="post">
                                <?= csrf_field() ?>
                                <!-- Profile fields hidden -->
                                <input type="hidden" name="name" value="<?= h($user['name']) ?>">
                                <input type="hidden" name="phone" value="<?= h($user['phone'] ?? '') ?>">
                                <input type="hidden" name="address" value="<?= h($user['address'] ?? '') ?>">
                                <div class="row g-3" style="max-width:420px">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">New Password</label>
                                        <input type="password" name="new_password" class="form-control"
                                               minlength="8" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-shield-lock me-1"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Member since -->
            <p class="text-muted small mt-3">
                <i class="bi bi-calendar me-1"></i>
                Member since <?= date('F Y', strtotime($user['created_at'])) ?>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
