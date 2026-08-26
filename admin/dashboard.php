<?php
// admin/dashboard.php — Main admin dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo   = getDB();
$stats = getDashboardStats();

// Recent alerts (last 10)
$recentAlerts = $pdo->query(
    "SELECT a.*, w.name AS site_name, w.url AS site_url
     FROM alerts a
     LEFT JOIN websites w ON a.website_id = w.id
     ORDER BY a.sent_at DESC
     LIMIT 10"
)->fetchAll();

// Latest log entries (last 15)
$latestLogs = $pdo->query(
    "SELECT l.*, w.name AS site_name, w.url AS site_url
     FROM logs l
     LEFT JOIN websites w ON l.website_id = w.id
     ORDER BY l.checked_at DESC
     LIMIT 15"
)->fetchAll();

// All websites for the dashboard table
$websites = $pdo->query(
    "SELECT * FROM websites ORDER BY status DESC, name ASC"
)->fetchAll();

define('PAGE_TITLE', 'Dashboard');
define('ACTIVE_NAV', 'dashboard');
require_once __DIR__ . '/../includes/layout.php';
?>

<?php renderFlash(); ?>

<!-- Stat Cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">🌐</div>
    <div class="stat-body">
      <div class="stat-number"><?= $stats['total'] ?></div>
      <div class="stat-label">Total Websites</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🟢</div>
    <div class="stat-body">
      <div class="stat-number" style="color:var(--up)"><?= $stats['up'] ?></div>
      <div class="stat-label">Websites UP</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">🔴</div>
    <div class="stat-body">
      <div class="stat-number" style="color:var(--down)"><?= $stats['down'] ?></div>
      <div class="stat-label">Websites DOWN</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow">⚪</div>
    <div class="stat-body">
      <div class="stat-number" style="color:var(--unknown)"><?= $stats['unknown'] ?></div>
      <div class="stat-label">Not Checked Yet</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">

<!-- Website Status Overview -->
<div class="card" style="grid-column: 1 / -1;">
  <div class="card-header">
    <div class="card-title">🌐 Website Status Overview</div>
    <a href="<?= BASE_URL ?>/admin/websites.php" class="btn btn-primary btn-sm">+ Add Website</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>URL</th>
          <th>Status</th>
          <th>Response Time</th>
          <th>Last Checked</th>
          <th>Interval</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($websites)): ?>
          <tr><td colspan="7" class="empty-state">
            <div class="empty-icon">📭</div>
            No websites added yet. <a href="<?= BASE_URL ?>/admin/websites.php">Add one now →</a>
          </td></tr>
        <?php else: ?>
          <?php foreach ($websites as $i => $w): ?>
            <tr class="<?= $w['status'] === 'DOWN' ? 'row-down' : ($w['status'] === 'UP' ? 'row-up' : '') ?>">
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($w['name']) ?></strong></td>
              <td><a href="<?= e($w['url']) ?>" target="_blank" class="url-text"><?= e($w['url']) ?></a></td>
              <td><?= statusBadge($w['status']) ?></td>
              <td><?= formatMs($w['response_time']) ?></td>
              <td><?= formatDate($w['last_checked']) ?></td>
              <td><?= (int)$w['interval_seconds'] ?>s</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Alerts -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🔔 Recent Alerts</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Site</th>
          <th>Message Preview</th>
          <th>Sent At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentAlerts)): ?>
          <tr><td colspan="3" class="empty-state" style="padding:24px;">
            <div class="empty-icon">✅</div> No alerts yet.
          </td></tr>
        <?php else: ?>
          <?php foreach ($recentAlerts as $alert): ?>
            <?php
              $isDown = strpos($alert['message'], 'DOWN') !== false;
              $isUp   = strpos($alert['message'], 'RECOVERY') !== false;
              $icon   = $isDown ? '🔴' : ($isUp ? '🟢' : '🟡');
              $preview = mb_substr(strip_tags($alert['message']), 0, 60) . '...';
            ?>
            <tr>
              <td><strong><?= e($alert['site_name'] ?? 'Unknown') ?></strong></td>
              <td><?= $icon ?> <?= e($preview) ?></td>
              <td style="white-space:nowrap;"><?= formatDate($alert['sent_at'], 'd M, H:i') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Latest Monitoring Activity -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Latest Monitoring Activity</div>
    <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-secondary btn-sm">View All Logs</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Site</th>
          <th>Status</th>
          <th>Response</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($latestLogs)): ?>
          <tr><td colspan="4" class="empty-state" style="padding:24px;">
            <div class="empty-icon">📭</div> No logs yet. Run the monitoring engine first.
          </td></tr>
        <?php else: ?>
          <?php foreach ($latestLogs as $log): ?>
            <tr>
              <td><strong><?= e($log['site_name'] ?? 'Deleted') ?></strong></td>
              <td><?= statusBadge($log['status']) ?></td>
              <td><?= formatMs($log['response_time']) ?></td>
              <td style="white-space:nowrap;"><?= formatDate($log['checked_at'], 'd M, H:i:s') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- end grid -->

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
