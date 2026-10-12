<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'Nova Shop ';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop me-2"></i>Nova Shop </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <form class="search-wrap position-relative mx-lg-auto my-2 my-lg-0" action="products.php" method="get" role="search" autocomplete="off">
                <div class="input-group">
                    <input type="search" id="searchInput" name="q" class="form-control" placeholder="Search products..." aria-label="Search products" maxlength="100">
                    <button class="btn btn-light" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <ul class="navbar-nav ms-lg-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php"><i class="bi bi-cart3"></i> Cart
                        <span class="badge bg-warning text-dark" id="cartCount">0</span></a>
                </li>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php
                        $__notifCount = 0;
                        if (isset($conn) && isset($_SESSION['user_id'])) {
                            $__uid = (int)$_SESSION['user_id'];
                            $__r = @$conn->query("SELECT COUNT(*) c FROM customer_notifications WHERE user_id=$__uid AND is_read=0");
                            if ($__r) $__notifCount = (int)$__r->fetch_assoc()['c'];
                        }
                        ?>
                        <?php if ($__notifCount > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle"
                                  style="font-size:10px;">
                                <?= $__notifCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= h($_SESSION['user_name'] ?? 'Account') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="orders.php"><i class="bi bi-box-seam me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2" href="register.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container my-4">
