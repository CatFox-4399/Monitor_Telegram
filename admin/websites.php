<?php
// admin/websites.php — Website CRUD management
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Handle POST: Add ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'list')) {
    $name     = trim($_POST['name'] ?? '');
    $url      = trim($_POST['url'] ?? '');
    $interval = (int)($_POST['interval_seconds'] ?? 15);

    if (empty($name) || empty($url)) {
        setFlash('error', 'Website name and URL are required.');
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Please enter a valid URL (include http:// or https://).');
    } else {
        // Add http:// prefix if missing
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO websites (name, url, interval_seconds, status, created_at)
             VALUES (:name, :url, :interval, 'UNKNOWN', NOW())"
        );
        $stmt->execute([':name' => $name, ':url' => $url, ':interval' => max(5, $interval)]);
        setFlash('success', "Website \"{$name}\" added successfully.");
        redirect(BASE_URL . '/admin/websites.php');
    }
}

// ── Handle POST: Edit ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $editId) {
    $name     = trim($_POST['name'] ?? '');
    $url      = trim($_POST['url'] ?? '');
    $interval = (int)($_POST['interval_seconds'] ?? 15);

    if (empty($name) || empty($url)) {
        setFlash('error', 'Name and URL cannot be empty.');
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Please enter a valid URL.');
    } else {
        $stmt = $pdo->prepare(
            "UPDATE websites SET name=:name, url=:url, interval_seconds=:interval WHERE id=:id"
        );
        $stmt->execute([':name' => $name, ':url' => $url, ':interval' => max(5, $interval), ':id' => $editId]);
        setFlash('success', "Website updated successfully.");
        redirect(BASE_URL . '/admin/websites.php');
    }
}

// ── Handle GET: Delete ─────────────────────────────────────
if ($action === 'delete' && $editId) {
    $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ?");
    $stmt->execute([$editId]);
    setFlash('success', 'Website deleted successfully.');
    redirect(BASE_URL . '/admin/websites.php');
}

// ── Fetch website for editing ──────────────────────────────
$editSite = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$editId]);
    $editSite = $stmt->fetch();
    if (!$editSite) {
        setFlash('error', 'Website not found.');
        redirect(BASE_URL . '/admin/websites.php');
    }
}

// ── Search & Filter ────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filterStatus = strtoupper(trim($_GET['status'] ?? ''));
$filterDate   = trim($_GET['date'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE :search OR url LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if (in_array($filterStatus, ['UP', 'DOWN', 'UNKNOWN'])) {
    $where[]  = 'status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterDate === 'today') {
    $where[]  = 'DATE(last_checked) = CURDATE()';
} elseif ($filterDate === '7days') {
    $where[]  = 'last_checked >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt     = $pdo->prepare("SELECT * FROM websites {$whereSQL} ORDER BY name ASC");
$stmt->execute($params);
$websites = $stmt->fetchAll();

define('PAGE_TITLE', 'Manage Websites');
define('ACTIVE_NAV', 'websites');
require_once __DIR__ . '/../includes/layout.php';
?>

<?php renderFlash(); ?>

<div class="page-header">
  <div>
    <div class="page-title">🌐 Manage Websites</div>
    <div class="page-subtitle">Add, edit, and delete monitored websites.</div>
  </div>
</div>

<!-- Add Website Form -->
<div class="card">
  <div class="card-header">
    <div class="card-title">➕ Add New Website</div>
  </div>
  <div class="card-body">
    <form method="POST" action="websites.php?action=add" style="display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:12px;align-items:end;flex-wrap:wrap;">
      <div>
        <label class="form-label" for="name">Website Name</label>
        <input id="name" name="name" type="text" class="form-control" placeholder="e.g. Google" required>
      </div>
      <div>
        <label class="form-label" for="url">Website URL</label>
        <input id="url" name="url" type="url" class="form-control" placeholder="https://example.com" required>
      </div>
      <div>
        <label class="form-label" for="interval_seconds">Check Interval (sec)</label>
        <input id="interval_seconds" name="interval_seconds" type="number" min="5" max="86400" class="form-control" value="15">
      </div>
      <div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Add Website</button>
      </div>
    </form>
  </div>
</div>

<!-- Search & Filter -->
<form method="GET" action="websites.php" class="filter-bar">
  <input id="searchInput" name="search" type="text" class="form-control"
         placeholder="🔍 Search by name or URL..."
         value="<?= e($search) ?>">
  <select name="status" class="form-control auto-submit-select">
    <option value="">All Status</option>
    <option value="UP"      <?= $filterStatus === 'UP'      ? 'selected' : '' ?>>🟢 UP Only</option>
    <option value="DOWN"    <?= $filterStatus === 'DOWN'    ? 'selected' : '' ?>>🔴 DOWN Only</option>
    <option value="UNKNOWN" <?= $filterStatus === 'UNKNOWN' ? 'selected' : '' ?>>⚪ Unknown</option>
  </select>
  <select name="date" class="form-control auto-submit-select">
    <option value="">All Dates</option>
    <option value="today"  <?= $filterDate === 'today'  ? 'selected' : '' ?>>Today</option>
    <option value="7days"  <?= $filterDate === '7days'  ? 'selected' : '' ?>>Last 7 Days</option>
  </select>
  <button type="submit" class="btn btn-secondary">Filter</button>
  <a href="websites.php" class="btn btn-secondary">Reset</a>
</form>

<!-- Websites Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 All Websites (<?= count($websites) ?>)</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>URL</th>
          <th>Status</th>
          <th>Response</th>
          <th>Last Checked</th>
          <th>Interval</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($websites)): ?>
          <tr>
            <td colspan="8" class="empty-state">
              <div class="empty-icon">📭</div>
              No websites found. Add one above!
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($websites as $i => $w): ?>
            <!-- View row -->
            <tr id="view-row-<?= $w['id'] ?>" class="<?= $w['status'] === 'DOWN' ? 'row-down' : ($w['status'] === 'UP' ? 'row-up' : '') ?>">
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($w['name']) ?></strong></td>
              <td>
                <a href="<?= e($w['url']) ?>" target="_blank" class="url-text"><?= e($w['url']) ?></a>
              </td>
              <td><?= statusBadge($w['status']) ?></td>
              <td><?= formatMs($w['response_time']) ?></td>
              <td><?= formatDate($w['last_checked']) ?></td>
              <td><?= (int)$w['interval_seconds'] ?>s</td>
              <td>
                <button class="btn btn-secondary btn-xs btn-edit-toggle" data-id="<?= $w['id'] ?>">✏️ Edit</button>
                <button class="btn btn-danger btn-xs btn-delete-site" data-id="<?= $w['id'] ?>" data-name="<?= e($w['name']) ?>">🗑️ Delete</button>
              </td>
            </tr>

            <!-- Inline edit row -->
            <tr id="edit-row-<?= $w['id'] ?>" class="edit-row" style="display:none;">
              <td colspan="8">
                <form method="POST" action="websites.php?action=edit&id=<?= $w['id'] ?>"
                      style="display:grid;grid-template-columns:1fr 2fr 1fr auto auto;gap:10px;align-items:end;padding:4px 0;">
                  <div>
                    <label class="form-label">Name</label>
                    <input name="name" type="text" class="form-control" value="<?= e($w['name']) ?>" required>
                  </div>
                  <div>
                    <label class="form-label">URL</label>
                    <input name="url" type="url" class="form-control" value="<?= e($w['url']) ?>" required>
                  </div>
                  <div>
                    <label class="form-label">Interval (sec)</label>
                    <input name="interval_seconds" type="number" min="5" max="86400" class="form-control" value="<?= (int)$w['interval_seconds'] ?>">
                  </div>
                  <div>
                    <button type="submit" class="btn btn-success btn-sm">💾 Save</button>
                  </div>
                  <div>
                    <button type="button" class="btn btn-secondary btn-sm btn-edit-toggle" data-id="<?= $w['id'] ?>">✖ Cancel</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <div class="modal-title">🗑️ Confirm Delete</div>
    <p style="color:var(--text-label);font-size:.9rem;">
      Are you sure you want to delete <strong id="deleteSiteName"></strong>?<br>
      <span class="text-muted">All logs and alerts for this website will also be deleted.</span>
    </p>
    <div class="modal-footer">
      <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
      <form id="deleteForm" method="GET">
        <button type="submit" class="btn btn-danger">🗑️ Delete</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
