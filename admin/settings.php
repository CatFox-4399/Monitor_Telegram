<?php
// admin/settings.php — Telegram and system configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/telegram.php';

requireLogin();

$pdo = getDB();

// ── Handle Save Settings ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $fields = ['telegram_bot_token', 'telegram_chat_id', 'slow_threshold_ms'];
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_val)
             VALUES (:key, :val)
             ON DUPLICATE KEY UPDATE setting_val = :val2"
        );
        $stmt->execute([':key' => $key, ':val' => $val, ':val2' => $val]);
    }
    setFlash('success', 'Settings saved successfully.');
    redirect(BASE_URL . '/admin/settings.php');
}

// ── Handle Change Admin Password ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPwd  = $_POST['current_password'] ?? '';
    $newPwd      = $_POST['new_password'] ?? '';
    $confirmPwd  = $_POST['confirm_password'] ?? '';

    $stmt  = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($currentPwd, $admin['password'])) {
        setFlash('error', 'Current password is incorrect.');
    } elseif (strlen($newPwd) < 6) {
        setFlash('error', 'New password must be at least 6 characters.');
    } elseif ($newPwd !== $confirmPwd) {
        setFlash('error', 'New passwords do not match.');
    } else {
        $hash = password_hash($newPwd, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?")->execute([$hash, $admin['id']]);
        setFlash('success', 'Password changed successfully. Please log in again.');
        logoutAdmin();
        redirect(BASE_URL . '/login.php');
    }
}

// ── Handle Test Telegram ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_telegram') {
    $testMsg = "🔔 <b>Test Alert from " . APP_NAME . "</b>\n\n" .
               "✅ Your Telegram integration is working correctly!\n" .
               "🕒 Time: " . date('d M Y H:i:s');
    $sent = sendTelegramAlert($testMsg);
    setFlash($sent ? 'success' : 'error',
             $sent ? 'Test message sent to Telegram successfully!' : 'Failed to send. Check your Bot Token and Chat ID.');
    redirect(BASE_URL . '/admin/settings.php');
}

// Load current settings
$botToken     = getSetting('telegram_bot_token', TELEGRAM_BOT_TOKEN);
$chatId       = getSetting('telegram_chat_id',   TELEGRAM_CHAT_ID);
$slowMs       = getSetting('slow_threshold_ms',  (string)SLOW_THRESHOLD_MS);

define('PAGE_TITLE', 'Settings');
define('ACTIVE_NAV', 'settings');
require_once __DIR__ . '/../includes/layout.php';
?>

<?php renderFlash(); ?>

<div class="page-header">
  <div>
    <div class="page-title">⚙️ System Settings</div>
    <div class="page-subtitle">Configure Telegram alerts and system preferences.</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Telegram Settings -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📲 Telegram Bot Configuration</div>
  </div>
  <div class="card-body">
    <form method="POST" action="settings.php">
      <input type="hidden" name="action" value="save_settings">

      <div class="form-group">
        <label class="form-label" for="telegram_bot_token">Bot Token</label>
        <div class="input-group">
          <input id="telegram_bot_token" name="telegram_bot_token" type="password"
                 class="form-control"
                 value="<?= e($botToken) ?>"
                 placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
          <span class="input-icon toggle-password" data-target="#telegram_bot_token">👁️</span>
        </div>
        <small class="text-muted">Get from <strong>@BotFather</strong> on Telegram.</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="telegram_chat_id">Chat ID</label>
        <input id="telegram_chat_id" name="telegram_chat_id" type="text"
               class="form-control"
               value="<?= e($chatId) ?>"
               placeholder="e.g. -100123456789 or 123456789">
        <small class="text-muted">Your personal Chat ID or Group/Channel ID.</small>
      </div>

      <div class="form-group">
        <label class="form-label" for="slow_threshold_ms">Slow Response Threshold (ms)</label>
        <input id="slow_threshold_ms" name="slow_threshold_ms" type="number"
               min="500" max="30000" step="100"
               class="form-control"
               value="<?= e($slowMs) ?>">
        <small class="text-muted">Alert when response time exceeds this value (default: 3000 ms).</small>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
      </div>
    </form>

    <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

    <!-- Test Telegram -->
    <form method="POST" action="settings.php">
      <input type="hidden" name="action" value="test_telegram">
      <button type="submit" class="btn btn-success">📤 Send Test Message</button>
      <small class="text-muted" style="display:block;margin-top:8px;">
        Sends a test alert to verify your Telegram configuration.
      </small>
    </form>
  </div>
</div>

<!-- Change Password -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🔐 Change Admin Password</div>
  </div>
  <div class="card-body">
    <form method="POST" action="settings.php">
      <input type="hidden" name="action" value="change_password">

      <div class="form-group">
        <label class="form-label" for="current_password">Current Password</label>
        <div class="input-group">
          <input id="current_password" name="current_password" type="password"
                 class="form-control" placeholder="Enter current password" required>
          <span class="input-icon toggle-password" data-target="#current_password">👁️</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <div class="input-group">
          <input id="new_password" name="new_password" type="password"
                 class="form-control" placeholder="Min. 6 characters" required>
          <span class="input-icon toggle-password" data-target="#new_password">👁️</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <div class="input-group">
          <input id="confirm_password" name="confirm_password" type="password"
                 class="form-control" placeholder="Repeat new password" required>
          <span class="input-icon toggle-password" data-target="#confirm_password">👁️</span>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">🔑 Change Password</button>
    </form>
  </div>
</div>

<!-- How to Get Telegram Info -->
<div class="card" style="grid-column: 1 / -1;">
  <div class="card-header">
    <div class="card-title">📖 How to Set Up Telegram Bot</div>
  </div>
  <div class="card-body">
    <ol style="color:var(--text-label);line-height:2;font-size:.88rem;">
      <li>Open Telegram and search for <strong>@BotFather</strong>.</li>
      <li>Send <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;">/newbot</code> and follow the instructions.</li>
      <li>Copy the <strong>Bot Token</strong> and paste it in the field above.</li>
      <li>To get your <strong>Chat ID</strong>: message your bot, then visit:<br>
          <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;word-break:break-all;">
            https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates
          </code>
      </li>
      <li>Find <code>chat.id</code> in the JSON response and paste it above.</li>
      <li>Click <strong>Save Settings</strong>, then <strong>Send Test Message</strong> to verify.</li>
    </ol>

    <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">

    <h3 style="font-size:.9rem;color:var(--text-primary);margin-bottom:10px;">⏱️ Cron Job Setup</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:10px;">Add this line to your crontab (Linux/Mac) to run every minute:</p>
    <code style="display:block;background:var(--bg-surface);padding:10px 14px;border-radius:8px;border:1px solid var(--border);font-size:.82rem;word-break:break-all;">
      * * * * * php <?= e(str_replace('\\','/', realpath(__DIR__ . '/..') . '/cron/monitor.php')) ?> >> /var/log/webmonitor.log 2>&1
    </code>

    <p style="color:var(--text-muted);font-size:.85rem;margin-top:14px;margin-bottom:6px;"><strong>Windows (XAMPP) Task Scheduler:</strong></p>
    <code style="display:block;background:var(--bg-surface);padding:10px 14px;border-radius:8px;border:1px solid var(--border);font-size:.82rem;word-break:break-all;">
      "C:\xampp\php\php.exe" "<?= e(str_replace('/', '\\', realpath(__DIR__ . '/..') . '\cron\monitor.php')) ?>"
    </code>
    <p style="color:var(--text-muted);font-size:.8rem;margin-top:6px;">Schedule to run every 1 minute in Task Scheduler.</p>

    <p style="color:var(--text-muted);font-size:.85rem;margin-top:14px;margin-bottom:6px;"><strong>Or trigger manually via browser (localhost only):</strong></p>
    <a href="<?= BASE_URL ?>/cron/monitor.php" target="_blank" class="btn btn-secondary btn-sm">▶️ Run Now</a>
  </div>
</div>

</div><!-- end grid -->

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
