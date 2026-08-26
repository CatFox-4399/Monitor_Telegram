<?php
// admin/logs.php — Monitoring logs with search, filter, pagination
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getDB();

// ── Search & Filter ────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = strtoupper(trim($_GET['status'] ?? ''));
$filterDate   = trim($_GET['date'] ?? '');
$filterSiteId = (int)($_GET['site_id'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(w.name LIKE :search OR w.url LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if (in_array($filterStatus, ['UP', 'DOWN'])) {
    $where[]  = 'l.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterDate === 'today') {
    $where[]  = 'DATE(l.checked_at) = CURDATE()';
} elseif ($filterDate === '7days') {
    $where[]  = 'l.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}
if ($filterSiteId > 0) {
    $where[]  = 'l.website_id = :site_id';
    $params[':site_id'] = $filterSiteId;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Pagination ─────────────────────────────────────────────
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Count total rows
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM logs l LEFT JOIN websites w ON l.website_id = w.id {$whereSQL}"
);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Fetch logs
$stmt = $pdo->prepare(
    "SELECT l.*, w.name AS site_name, w.url AS site_url
     FROM logs l
     LEFT JOIN websites w ON l.website_id = w.id
     {$whereSQL}
     ORDER BY l.checked_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch website list for filter dropdown
$allSites = $pdo->query("SELECT id, name FROM websites ORDER BY name ASC")->fetchAll();

// Build query string for pagination links
function buildPageQuery(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    unset($q['action']);
    return '?' . http_build_query($q);
}

define('PAGE_TITLE', 'Monitoring Logs');
define('ACTIVE_NAV', 'logs');
require_once __DIR__ . '/../includes/layout.php';
?>

<?php renderFlash(); ?>

<div class="page-header">
  <div>
    <div class="page-title">📋 Monitoring Logs</div>
    <div class="page-subtitle">Full history of all website checks. Total: <strong><?= number_format($totalRows) ?></strong> records.</div>
  </div>
</div>

<!-- Search & Filter Bar -->
<form method="GET" action="logs.php" class="filter-bar">
  <input id="searchInput" name="search" type="text" class="form-control"
         placeholder="🔍 Search by name or URL..."
         value="<?= e($search) ?>">
  <select name="status" class="form-control auto-submit-select">
    <option value="">All Status</option>
    <option value="UP"   <?= $filterStatus === 'UP'   ? 'selected' : '' ?>>🟢 UP Only</option>
    <option value="DOWN" <?= $filterStatus === 'DOWN' ? 'selected' : '' ?>>🔴 DOWN Only</option>
  </select>
  <select name="date" class="form-control auto-submit-select">
    <option value="">All Dates</option>
    <option value="today"  <?= $filterDate === 'today'  ? 'selected' : '' ?>>Today</option>
    <option value="7days"  <?= $filterDate === '7days'  ? 'selected' : '' ?>>Last 7 Days</option>
  </select>
  <select name="site_id" class="form-control auto-submit-select">
    <option value="">All Sites</option>
    <?php foreach ($allSites as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $filterSiteId === (int)$s['id'] ? 'selected' : '' ?>>
        <?= e($s['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-secondary">Filter</button>
  <a href="logs.php" class="btn btn-secondary">Reset</a>
</form>

<!-- Logs Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📊 Log History (Page <?= $page ?> / <?= $totalPages ?>)</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Website</th>
          <th>URL</th>
          <th>Status</th>
          <th>Response Time</th>
          <th>Checked At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" class="empty-state">
              <div class="empty-icon">📭</div>
              No logs found for the selected filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $i => $log): ?>
            <tr class="<?= $log['status'] === 'DOWN' ? 'row-down' : 'row-up' ?>">
              <td><?= ($offset + $i + 1) ?></td>
              <td><strong><?= e($log['site_name'] ?? '— Deleted —') ?></strong></td>
              <td><span class="url-text"><?= e($log['site_url'] ?? '—') ?></span></td>
              <td><?= statusBadge($log['status']) ?></td>
              <td><?= formatMs($log['response_time']) ?></td>
              <td style="white-space:nowrap;"><?= formatDate($log['checked_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?= buildPageQuery(1) ?>">«</a>
      <a href="<?= buildPageQuery($page - 1) ?>">‹ Prev</a>
    <?php else: ?>
      <span class="disabled">«</span>
      <span class="disabled">‹ Prev</span>
    <?php endif; ?>

    <?php
      $start = max(1, $page - 2);
      $end   = min($totalPages, $page + 2);
      for ($p = $start; $p <= $end; $p++):
    ?>
      <?php if ($p === $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="<?= buildPageQuery($p) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="<?= buildPageQuery($page + 1) ?>">Next ›</a>
      <a href="<?= buildPageQuery($totalPages) ?>">»</a>
    <?php else: ?>
      <span class="disabled">Next ›</span>
      <span class="disabled">»</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
