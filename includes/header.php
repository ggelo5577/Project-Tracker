<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? APP_TITLE) ?> | DOST PPMIS</title>
    <link rel="stylesheet" href="<?= ASSETS?>css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= h(assetPath('css/main.css')) ?>">
    <script>window.APP_BASE = '<?= h(APP_BASE) ?>';</script>
    <?= $extraHead ?? '' ?>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title">PPMIS</span>
                <span class="brand-sub">DOST Region VIII</span>
            </div>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="<?= h(appPath('index.php')) ?>" class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= h(appPath('modules/project/create.php')) ?>" class="nav-link <?= ($activePage ?? '') === 'start-project' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>Start Project</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= h(appPath('modules/progress/index.php')) ?>" class="nav-link <?= ($activePage ?? '') === 'progress' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-steps"></i>
                    <span>Progress</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= h(appPath('modules/proponents/create.php')) ?>" class="nav-link <?= ($activePage ?? '') === 'proponents' ? 'active' : '' ?>">
                    <i class="bi bi-building"></i>
                    <span>Proponents</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= h(currentUser()['full_name']) ?></span>
                    <span class="user-role"><?= h(currentUser()['role']) ?></span>
                </div>
            </div>
            <a href="<?= h(appPath('logout.php')) ?>" class="btn-logout" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-breadcrumb">
                <?= $breadcrumb ?? '' ?>
            </div>
            <div class="topbar-actions">
                <span class="dost-badge">DOST VIII</span>
            </div>
        </div>
