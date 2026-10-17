<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Kcart Rentals</title>
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('kcart-theme') === 'dark' ? 'dark' : 'light';
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
</head>
<body class="<?= $page === 'login' ? 'login-page' : '' ?>">
<?php if ($page !== 'login'): ?>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php"><img class="brand-logo" src="assets/images/logo.svg" alt=""><span>Kcart<span class="brand-sub">FLEET & RENTALS</span></span></a>
        <div class="nav-caption">WORKSPACE</div>
        <nav class="primary-nav" aria-label="Main navigation">
            <?php
            $navigation = [
                'dashboard' => ['Overview', '⌂'],
                'customers' => ['Customers', '◎'],
                'rentals'   => ['Rentals', '↗'],
                'returns'   => ['Returns', '↙'],
                'vehicles'  => ['Vehicle fleet', '▰'],
                'billing'   => ['Billing', '₱'],
                'reports'   => ['Reports', '▤'],
            ];
            foreach ($navigation as $key => [$label, $icon]):
            ?>
                <a class="nav-link <?= $page === $key ? 'is-active' : '' ?>" href="index.php?page=<?= e($key) ?>"><span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if (current_user()['role'] === 'Admin'): ?>
            <div class="nav-caption nav-caption-spaced">MANAGEMENT</div>
            <nav class="primary-nav" aria-label="Management navigation">
                <a class="nav-link <?= $page === 'users' ? 'is-active' : '' ?>" href="index.php?page=users"><span class="nav-icon">♙</span>Team & access</a>
                <a class="nav-link <?= $page === 'settings' ? 'is-active' : '' ?>" href="index.php?page=settings"><span class="nav-icon">⚙</span>Settings</a>
            </nav>
        <?php endif; ?>
        <div class="sidebar-bottom">
            <div class="account-row"><span class="avatar"><?= e(strtoupper(substr(current_user()['first_name'], 0, 1))) ?></span><span class="account-meta"><strong><?= e(full_name(current_user())) ?></strong><span><?= e(current_user()['role']) ?></span></span>
                <form method="post" class="logout-form"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="logout-button" type="submit"><span aria-hidden="true">↪</span> Log out</button></form>
            </div>
        </div>
    </aside>
    <main class="main-panel">
        <header class="topbar">
            <button class="mobile-menu" type="button" data-menu-toggle aria-label="Toggle navigation">☰</button>
            <div class="breadcrumb">Workspace <span>/</span> <strong><?= e($title) ?></strong></div>
            <div class="topbar-right"><span class="location-chip"><span aria-hidden="true">⌖</span><span class="location-label">Bataan, Philippines</span></span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode"><span aria-hidden="true">☾</span><span class="theme-label">Dark mode</span></button><span class="topbar-date"><?= e(date('D, M j, Y')) ?></span><span class="avatar avatar-small"><?= e(strtoupper(substr(current_user()['first_name'], 0, 1))) ?></span></div>
        </header>
        <section class="page-content">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?><button type="button" data-dismiss aria-label="Dismiss message">×</button></div>
            <?php endif; ?>
<?php else: ?>
    <button class="theme-toggle login-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode"><span aria-hidden="true">☾</span><span class="theme-label">Dark mode</span></button>
    <?php if ($flashMessage): ?>
        <div class="login-alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>
