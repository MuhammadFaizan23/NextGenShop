<?php
/**
 * NextGenShop – HTML Header/Navigation
 * @var string $page_title
 */
$_page_title = isset($page_title) ? h($page_title) . ' | ' . APP_NAME : APP_NAME;
$_flash = render_flash();
$_cart_count = cart_count();
$_wishlist_count = wishlist_count();
$_categories = get_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_page_title ?></title>
    <meta name="description" content="<?= APP_NAME ?> – Modern e-commerce for the next generation">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Top Bar -->
<div class="topbar py-1 d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <i class="bi bi-envelope-fill me-1"></i>
            support@nextgenshop.com &nbsp;|&nbsp;
            <i class="bi bi-telephone-fill me-1"></i>
            1-800-SHOP-NOW
        </small>
        <small>
            <?php if (is_logged_in()): ?>
                <span class="text-muted">Welcome, <strong><?= h($_SESSION['user_name']) ?></strong></span>
                <?php if (is_admin()): ?>
                    &nbsp;|&nbsp;<a href="/admin/index.php" class="text-decoration-none text-primary">
                        <i class="bi bi-shield-check me-1"></i>Admin
                    </a>
                <?php endif; ?>
                &nbsp;|&nbsp;<a href="/pages/profile.php" class="text-decoration-none text-muted">Profile</a>
                &nbsp;|&nbsp;<a href="/pages/logout.php" class="text-decoration-none text-muted">Logout</a>
            <?php else: ?>
                <a href="/pages/login.php" class="text-decoration-none text-muted me-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
                <a href="/pages/register.php" class="text-decoration-none text-muted">
                    <i class="bi bi-person-plus me-1"></i>Register
                </a>
            <?php endif; ?>
        </small>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="/index.php">
            <i class="bi bi-bag-heart-fill text-primary me-1"></i>
            <span class="text-primary"><?= APP_NAME ?></span>
        </a>

        <!-- Mobile icons -->
        <div class="d-flex d-lg-none align-items-center gap-2">
            <a href="/pages/cart.php" class="btn btn-outline-primary btn-sm position-relative">
                <i class="bi bi-cart3"></i>
                <?php if ($_cart_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $_cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Search form -->
            <form class="d-flex mx-auto my-2 my-lg-0" action="/index.php" method="get" style="min-width:260px;max-width:420px;width:100%">
                <div class="input-group">
                    <input type="text" class="form-control" name="q" placeholder="Search products…"
                           value="<?= h($_GET['q'] ?? '') ?>" autocomplete="off">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <!-- Categories dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-grid me-1"></i>Categories
                    </a>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li><a class="dropdown-item" href="/index.php">All Products</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($_categories as $cat): ?>
                            <li>
                                <a class="dropdown-item" href="/index.php?category=<?= h($cat['slug']) ?>">
                                    <?= h($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <!-- Wishlist -->
                <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="/pages/wishlist.php" title="Wishlist">
                        <i class="bi bi-heart fs-5"></i>
                        <?php if ($_wishlist_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem">
                                <?= $_wishlist_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Cart -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="/pages/cart.php" title="Cart">
                        <i class="bi bi-cart3 fs-5"></i>
                        <?php if ($_cart_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem">
                                <?= $_cart_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Auth -->
                <?php if (is_logged_in()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span class="d-none d-lg-inline"><?= h($_SESSION['user_name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item" href="/pages/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/pages/orders.php"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                        <li><a class="dropdown-item" href="/pages/wishlist.php"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
                        <?php if (is_admin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary" href="/admin/index.php"><i class="bi bi-shield-check me-2"></i>Admin Panel</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/pages/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="btn btn-primary btn-sm ms-2" href="/pages/login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash messages -->
<?php if ($_flash): ?>
<div class="container mt-3">
    <?= $_flash ?>
</div>
<?php endif; ?>

<!-- Page content begins below -->
