<?php
// ============================================================
// cron/monitor.php
// Core monitoring engine — run via cron job or Task Scheduler
// Usage:  php /path/to/Telegram/cron/monitor.php
// ============================================================

// Allow execution from CLI and web
if (php_sapi_name() !== 'cli') {
    $allowedIPs  = ['127.0.0.1', '::1'];
    $secretToken = 'webmonitor_cron_s3cr3t_2026'; // change this!
    $providedToken = $_GET['token'] ?? '';

    $isLocalhost   = in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs);
    $isValidToken  = hash_equals($secretToken, $providedToken);

    if (!$isLocalhost && !$isValidToken) {
        http_response_code(403);
        die('Access denied. Run via CLI or provide a valid token.');
    }
}

// Bootstrap
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/includes/db.php';
require_once $baseDir . '/includes/functions.php';
require_once $baseDir . '/includes/telegram.php';

$pdo = getDB();

// -------------------------------------------------------
// 1. Fetch all websites that are due for a check
// -------------------------------------------------------
$websites = $pdo->query("SELECT * FROM websites")->fetchAll();

$checked = 0;
$alerted = 0;

foreach ($websites as $site) {
    $siteId       = (int) $site['id'];
    $intervalSecs = (int) $site['interval_seconds'];
    $lastChecked  = $site['last_checked'];

    // Skip if checked too recently
    if ($lastChecked !== null) {
        $nextCheck = strtotime($lastChecked) + $intervalSecs;
        if (time() < $nextCheck) {
            echo "[SKIP] {$site['name']} — next check at " . date('H:i:s', $nextCheck) . "\n";
            continue;
        }
    }

    echo "[CHECK] {$site['name']} ({$site['url']}) ... ";

    // -------------------------------------------------------
    // 2. Perform cURL request
    // -------------------------------------------------------
    $ch = curl_init($site['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => CURL_TIMEOUT_SEC,
        CURLOPT_CONNECTTIMEOUT => CURL_TIMEOUT_SEC,
        CURLOPT_USERAGENT      => 'WebMonitor/' . APP_VERSION . ' (uptime bot)',
        CURLOPT_NOBODY         => false,
        CURLOPT_SSL_VERIFYPEER => false, // local/self-signed cert support
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $startMs   = microtime(true);
    $response  = curl_exec($ch);
    $endMs     = microtime(true);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $responseTimeMs = round(($endMs - $startMs) * 1000, 2);
    $now            = date('Y-m-d H:i:s');

    // -------------------------------------------------------
    // 3. Determine new status
    // -------------------------------------------------------
    $newStatus = 'DOWN';
    if (!$curlError && $httpCode >= 200 && $httpCode < 600 && $httpCode !== 0) {
        // Any real HTTP response = site is reachable
        // 5xx counts as UP (server responded)
        $newStatus = 'UP';
    }

    $isSlow = ($newStatus === 'UP' && $responseTimeMs > (float) getSetting('slow_threshold_ms', (string) SLOW_THRESHOLD_MS));

    echo "{$newStatus} ({$responseTimeMs} ms)\n";

    // -------------------------------------------------------
    // 4. Insert into logs
    // -------------------------------------------------------
    $stmt = $pdo->prepare(
        "INSERT INTO logs (website_id, status, response_time, checked_at)
         VALUES (:wid, :status, :rt, :ts)"
    );
    $stmt->execute([
        ':wid'    => $siteId,
        ':status' => $newStatus,
        ':rt'     => $newStatus === 'UP' ? $responseTimeMs : null,
        ':ts'     => $now,
    ]);

    // -------------------------------------------------------
    // 5. Update websites table
    // -------------------------------------------------------
    $updateStmt = $pdo->prepare(
        "UPDATE websites
         SET status        = :status,
             response_time = :rt,
             last_checked  = :ts
         WHERE id = :id"
    );
    $updateStmt->execute([
        ':status' => $newStatus,
        ':rt'     => $newStatus === 'UP' ? $responseTimeMs : null,
        ':ts'     => $now,
        ':id'     => $siteId,
    ]);

    // -------------------------------------------------------
    // 6. Compare with previous status — send alert only on change
    // -------------------------------------------------------
    $previousStatus = strtoupper($site['status'] ?? 'UNKNOWN');
    $siteData = array_merge($site, [
        'response_time' => $newStatus === 'UP' ? $responseTimeMs : null,
    ]);

    $shouldAlert = false;
    $alertMsg    = '';

    if ($newStatus === 'DOWN' && $previousStatus !== 'DOWN') {
        $shouldAlert = true;
        $alertMsg    = buildDownAlert($siteData);
        echo "  → Status changed: {$previousStatus} → DOWN — sending Telegram alert\n";
    } elseif ($newStatus === 'UP' && $previousStatus === 'DOWN') {
        $shouldAlert = true;
        $alertMsg    = buildUpAlert($siteData);
        echo "  → Status changed: DOWN → UP — sending recovery alert\n";
    } elseif ($newStatus === 'UP' && $isSlow && $previousStatus !== 'DOWN') {
        // Only send slow alert if not already DOWN (avoid double alert)
        $shouldAlert = true;
        $alertMsg    = buildSlowAlert($siteData);
        echo "  → Slow response detected — sending warning\n";
    }

    if ($shouldAlert && !empty($alertMsg)) {
        $sent = sendTelegramAlert($alertMsg);

        // Log the alert
        $alertStmt = $pdo->prepare(
            "INSERT INTO alerts (website_id, message, sent_at) VALUES (:wid, :msg, :ts)"
        );
        $alertStmt->execute([
            ':wid' => $siteId,
            ':msg' => $alertMsg,
            ':ts'  => $now,
        ]);

        echo '  → Telegram alert ' . ($sent ? 'sent ✓' : 'failed ✗') . "\n";
        $alerted++;
    }

    $checked++;
}

echo "\n✅ Monitoring complete. Checked: {$checked}, Alerts sent: {$alerted}\n";
