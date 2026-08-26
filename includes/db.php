<?php
// ============================================================
// includes/db.php
// PDO database connection singleton
// ============================================================

require_once __DIR__ . '/../config/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;color:#c0392b;padding:40px;">
                 <h2>Database Connection Error</h2>
                 <p>' . htmlspecialchars($e->getMessage()) . '</p>
                 <p>Please check <code>config/config.php</code> settings.</p>
                 </div>');
        }
    }
    return $pdo;
}
