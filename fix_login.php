<?php
// fix_login.php — Force fix admin account + auto login
// DELETE AFTER USE!
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

echo "<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; background: #0f0f0f; color: #eee; padding: 40px; }
  h2 { margin-bottom: 20px; color: #fff; }
  .step { background: #1a1a1a; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
  .ok  { color: #2ecc71; }
  .err { color: #e74c3c; }
  .warn{ color: #f1c40f; }
  code { background: #2a2a2a; padding: 2px 8px; border-radius: 4px; font-size: 13px; }
  a.btn { display: inline-block; margin-top: 20px; background: #5865f2; color: #fff;
          padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: bold; }
  pre { background: #1a1a1a; padding: 12px; border-radius: 6px; overflow: auto; font-size: 12px; margin-top: 8px; }
</style>
<h2>🔧 WebMonitor — Login Fix</h2>";

// STEP 1: DB Connection
echo "<div class='step'><strong>Step 1: Database Connection</strong><br>";
try {
    $pdo = getDB();
    echo "<span class='ok'>✅ Connected to " . DB_NAME . " on " . DB_HOST . ":" . DB_PORT . "</span>";
} catch (Exception $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "</div>"; exit;
}
echo "</div>";

// STEP 2: Check admin table
echo "<div class='step'><strong>Step 2: Admin Table</strong><br>";
try {
    $rows = $pdo->query("SELECT id, username, LEFT(password,30) as hash_preview FROM admin")->fetchAll();
    if (empty($rows)) {
        echo "<span class='warn'>⚠️ Table is empty</span>";
    } else {
        foreach ($rows as $r) {
            $ok = password_verify('admin123', $pdo->query("SELECT password FROM admin WHERE id={$r['id']}")->fetchColumn());
            echo "ID {$r['id']}: <code>{$r['username']}</code> — password_verify('admin123') = " .
                 ($ok ? "<span class='ok'>✅ TRUE</span>" : "<span class='err'>❌ FALSE</span>") . "<br>";
        }
    }
} catch (Exception $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

// STEP 3: Force reset
echo "<div class='step'><strong>Step 3: Force Reset to admin / admin123</strong><br>";
try {
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $n = (int)$pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($n === 0) {
        $pdo->prepare("INSERT INTO admin (username, password) VALUES ('admin', ?)")->execute([$hash]);
        echo "<span class='ok'>✅ Inserted new admin row</span>";
    } else {
        $pdo->prepare("UPDATE admin SET username='admin', password=? WHERE id=(SELECT MIN(id) FROM (SELECT id FROM admin) x)")->execute([$hash]);
        echo "<span class='ok'>✅ Password updated</span>";
    }

    // Verify
    $stored = $pdo->query("SELECT password FROM admin WHERE username='admin' LIMIT 1")->fetchColumn();
    $pass = password_verify('admin123', $stored);
    echo "<br>Verification after reset: " . ($pass ? "<span class='ok'>✅ PASS</span>" : "<span class='err'>❌ FAIL</span>");
} catch (Exception $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

// STEP 4: Set session
echo "<div class='step'><strong>Step 4: Session Login</strong><br>";
$admin = $pdo->query("SELECT * FROM admin WHERE username='admin' LIMIT 1")->fetch();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id']        = $admin['id'] ?? 1;
$_SESSION['admin_username']  = 'admin';
echo "<span class='ok'>✅ Session set — you are now logged in</span>";
echo "</div>";

echo "<a class='btn' href='" . BASE_URL . "/admin/dashboard.php'>🚀 Go to Dashboard</a>";
echo "<p style='color:#e74c3c;margin-top:20px;font-size:13px;'>⚠️ DELETE this file (fix_login.php) after you're in!</p>";
?>
