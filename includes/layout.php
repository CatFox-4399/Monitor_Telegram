<?php
// includes/layout.php
// Shared sidebar + topbar partial — included by every admin page

if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Dashboard');
if (!defined('ACTIVE_NAV')) define('ACTIVE_NAV', 'dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= PAGE_TITLE ?> — <?= APP_NAME ?></title>
  <meta name="description" content="Website Monitoring System Admin Panel — <?= PAGE_TITLE ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">📡</div>
      <div class="logo-text">
        <?= APP_NAME ?>
        <span>Monitoring System</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>

      <a href="<?= BASE_URL ?>/admin/dashboard.php"
         class="nav-link <?= ACTIVE_NAV === 'dashboard' ? 'active' : '' ?>">
        <span class="nav-icon">📊</span> Dashboard
      </a>

      <a href="<?= BASE_URL ?>/admin/websites.php"
         class="nav-link <?= ACTIVE_NAV === 'websites' ? 'active' : '' ?>">
        <span class="nav-icon">🌐</span> Websites
      </a>

      <div class="nav-section">Monitoring</div>

      <a href="<?= BASE_URL ?>/admin/logs.php"
         class="nav-link <?= ACTIVE_NAV === 'logs' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Logs
      </a>

      <a href="<?= BASE_URL ?>/cron/monitor.php"
         id="btnRunNow"
         class="nav-link">
        <span class="nav-icon">▶️</span> Run Check Now
      </a>

      <div class="nav-section">Configuration</div>

      <a href="<?= BASE_URL ?>/admin/settings.php"
         class="nav-link <?= ACTIVE_NAV === 'settings' ? 'active' : '' ?>">
        <span class="nav-icon">⚙️</span> Settings
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="admin-name">👤 <?= htmlspecialchars(currentAdmin()) ?></div>
      <a class="logout-btn" href="<?= BASE_URL ?>/logout.php">🚪 Logout</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main-wrap">
    <header class="topbar">
      <div class="topbar-title"><?= PAGE_TITLE ?></div>
      <div class="topbar-right">
        <span class="topbar-badge">🔐 Admin</span>
        <span class="text-muted" style="font-size:.75rem;"><?= date('d M Y, H:i') ?></span>
      </div>
    </header>

    <div class="content">
<?php // Content injected here — closed in layout_end.php ?>
