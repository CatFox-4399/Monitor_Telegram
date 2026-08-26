<?php
// ============================================================
// includes/telegram.php
// Telegram Bot API wrapper
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Send a Telegram alert message via Bot API.
 *
 * @param  string $message   The plain-text (or HTML) message to send.
 * @return bool              True if sent successfully, false otherwise.
 */
function sendTelegramAlert(string $message): bool {
    $token  = getSetting('telegram_bot_token', TELEGRAM_BOT_TOKEN);
    $chatId = getSetting('telegram_chat_id',   TELEGRAM_CHAT_ID);

    if (empty($token) || $token === 'YOUR_BOT_TOKEN_HERE' ||
        empty($chatId) || $chatId === 'YOUR_CHAT_ID_HERE') {
        // Telegram not configured — skip silently
        return false;
    }

    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'                  => $chatId,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false, // for local XAMPP compatibility
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('[Telegram] cURL error: ' . $err);
        return false;
    }

    $decoded = json_decode($response, true);
    if (!($decoded['ok'] ?? false)) {
        error_log('[Telegram] API error: ' . $response);
        return false;
    }
    return true;
}

/**
 * Build a formatted DOWN alert message.
 */
function buildDownAlert(array $site): string {
    $time = date('d M Y H:i:s');
    return "🔴 <b>ALERT: Website DOWN</b>\n\n" .
           "📌 <b>Name:</b> {$site['name']}\n" .
           "🔗 <b>URL:</b> {$site['url']}\n" .
           "📊 <b>Status:</b> DOWN ❌\n" .
           "⚡ <b>Response:</b> " . (isset($site['response_time']) ? formatMs($site['response_time']) : 'No response') . "\n" .
           "🕒 <b>Time:</b> {$time}\n\n" .
           "⚠️ Please check the website immediately!";
}

/**
 * Build a formatted UP / Recovery alert message.
 */
function buildUpAlert(array $site): string {
    $time = date('d M Y H:i:s');
    return "🟢 <b>RECOVERY: Website Back UP</b>\n\n" .
           "📌 <b>Name:</b> {$site['name']}\n" .
           "🔗 <b>URL:</b> {$site['url']}\n" .
           "📊 <b>Status:</b> UP ✅\n" .
           "⚡ <b>Response:</b> " . formatMs($site['response_time']) . "\n" .
           "🕒 <b>Time:</b> {$time}\n\n" .
           "✅ Website is now reachable.";
}

/**
 * Build a SLOW response warning message.
 */
function buildSlowAlert(array $site): string {
    $time      = date('d M Y H:i:s');
    $threshold = getSetting('slow_threshold_ms', (string) SLOW_THRESHOLD_MS);
    return "🟡 <b>WARNING: Slow Response Detected</b>\n\n" .
           "📌 <b>Name:</b> {$site['name']}\n" .
           "🔗 <b>URL:</b> {$site['url']}\n" .
           "📊 <b>Status:</b> UP (but SLOW)\n" .
           "⚡ <b>Response:</b> " . formatMs($site['response_time']) . "\n" .
           "🚧 <b>Threshold:</b> " . formatMs((float)$threshold) . "\n" .
           "🕒 <b>Time:</b> {$time}\n\n" .
           "⚠️ Response time exceeds acceptable limit.";
}
