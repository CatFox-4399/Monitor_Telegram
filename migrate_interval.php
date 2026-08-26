<?php
// migrate_interval.php — One-time migration: interval_minutes → interval_seconds (15s default)
// DELETE THIS FILE after running!
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDB();

    // Check if column already migrated
    $cols = $pdo->query("SHOW COLUMNS FROM websites LIKE 'interval_seconds'")->fetchAll();
    if (!empty($cols)) {
        echo "<p style='color:orange'>⚠️ Column <code>interval_seconds</code> already exists. No changes made.</p>";
    } else {
        // Rename column and set default to 15 seconds (previously was minutes, so multiply by 60)
        $pdo->exec("ALTER TABLE websites 
                    CHANGE COLUMN interval_minutes 
                    interval_seconds INT(11) NOT NULL DEFAULT 15");

        // Convert existing values: old value was minutes, convert to seconds
        $pdo->exec("UPDATE websites SET interval_seconds = interval_seconds * 60");

        // Then cap everything to 15 seconds as requested
        $pdo->exec("UPDATE websites SET interval_seconds = 15");

        echo "<p style='color:green'>✅ Migration successful!</p>";
        echo "<p>Column renamed: <code>interval_minutes</code> → <code>interval_seconds</code></p>";
        echo "<p>All existing websites updated to <strong>15 seconds</strong> interval.</p>";
    }

    echo "<p><a href='admin/dashboard.php'>→ Go to Dashboard</a></p>";
    echo "<p style='color:red'><strong>⚠️ Delete this file (migrate_interval.php) after use!</strong></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
