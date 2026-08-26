<?php
// ============================================================
// includes/functions.php
// Shared helper / utility functions
// ============================================================

require_once __DIR__ . '/db.php';

/**
 * Format datetime for display.
 */
function formatDate(?string $dt, string $format = 'd M Y, H:i:s'): string {
    if (!$dt) return '—';
    return date($format, strtotime($dt));
}

/**
 * Format response time nicely.
 */
function formatMs(?float $ms): string {
    if ($ms === null) return '—';
    if ($ms < 1000) return round($ms) . ' ms';
    return round($ms / 1000, 2) . ' s';
}

/**
 * Return HTML badge for status.
 */
function statusBadge(string $status): string {
    return match (strtoupper($status)) {
        'UP'      => '<span class="badge badge-up">🟢 UP</span>',
        'DOWN'    => '<span class="badge badge-down">🔴 DOWN</span>',
        'SLOW'    => '<span class="badge badge-slow">🟡 SLOW</span>',
        default   => '<span class="badge badge-unknown">⚪ UNKNOWN</span>',
    };
}

/**
 * Escape HTML output.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Get a setting value from the `settings` DB table.
 * Falls back to the constant defined in config.php.
 */
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT setting_val FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row  = $stmt->fetch();
        $val  = $row ? $row['setting_val'] : $default;
        $cache[$key] = $val;
        return $val;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Redirect helper.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Flash message helpers (store in session, display once).
 */
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function renderFlash(): void {
    $f = getFlash();
    if (!$f) return;
    $cls = match ($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    echo '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
}

/**
 * Get dashboard summary stats.
 */
function getDashboardStats(): array {
    $pdo = getDB();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn();
    $up    = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE status='UP'")->fetchColumn();
    $down  = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE status='DOWN'")->fetchColumn();
    $unknown = $total - $up - $down;
    return compact('total', 'up', 'down', 'unknown');
}
