<?php
/**
 * NextGenShop – Admin Header
 */
$_page_title = isset($page_title) ? h($page_title) . ' | Admin – ' . APP_NAME : 'Admin – ' . APP_NAME;
$_flash = render_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .admin-sidebar { min-height: 100vh; background: #1e2a3a; }
        .admin-sidebar .nav-link { color: rgba(255,255,255,.7); border-radius: 8px; margin-bottom: 2px; }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.12); }
        .admin-sidebar .nav-link i { width: 20px; }
        .admin-sidebar .brand { color: #fff; font-weight: 700; font-size: 1.15rem; }
        .admin-content { background: #f8f9fa; min-height: 100vh; }
        @media (max-width: 767px) { .admin-sidebar { min-height: auto; } }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="admin-sidebar d-flex flex-column p-3" style="width:240px;min-width:240px" id="adminSidebar">
        <a href="/admin/index.php" class="brand text-decoration-none mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-bag-heart-fill text-primary fs-4"></i>
            <span><?= APP_NAME ?></span>
        </a>
        <span class="text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.08em">Main</span>
        <ul class="nav flex-column mt-1 mb-3">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"
                   href="/admin/index.php">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
        </ul>
        <span class="text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.08em">Catalog</span>
        <ul class="nav flex-column mt-1 mb-3">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>"
                   href="/admin/products.php">
                    <i class="bi bi-box-seam me-2"></i>Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>"
                   href="/admin/categories.php">
                    <i class="bi bi-tags me-2"></i>Categories
                </a>
            </li>
        </ul>
        <span class="text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.08em">Sales</span>
        <ul class="nav flex-column mt-1 mb-3">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>"
                   href="/admin/orders.php">
                    <i class="bi bi-bag-check me-2"></i>Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : '' ?>"
                   href="/admin/reviews.php">
                    <i class="bi bi-star me-2"></i>Reviews
                </a>
            </li>
        </ul>
        <span class="text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.08em">Users</span>
        <ul class="nav flex-column mt-1 mb-4">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>"
                   href="/admin/users.php">
                    <i class="bi bi-people me-2"></i>Users
                </a>
            </li>
        </ul>
        <div class="mt-auto">
            <hr class="border-secondary">
            <a href="/index.php" class="nav-link text-muted small mb-1">
                <i class="bi bi-arrow-left me-2"></i>Back to Store
            </a>
            <a href="/pages/logout.php" class="nav-link text-danger small">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </div>
    </nav>

    <!-- Main content -->
    <div class="admin-content flex-grow-1">
        <!-- Top bar -->
        <div class="bg-white border-bottom px-4 py-2 d-flex align-items-center justify-content-between">
            <span class="fw-semibold text-muted small"><?= isset($page_title) ? h($page_title) : 'Admin Panel' ?></span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= h($_SESSION['user_name'] ?? 'Admin') ?>
                </span>
            </div>
        </div>

        <!-- Flash messages -->
        <?php if ($_flash): ?>
        <div class="px-4 pt-3">
            <?= $_flash ?>
        </div>
        <?php endif; ?>

        <div class="p-4">
