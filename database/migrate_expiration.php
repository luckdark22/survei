<?php
// database/migrate_expiration.php
require_once 'includes/db.php';

try {
    echo "Starting migration: adding 'expires_at' to events table...\n";

    // Add expires_at column if it doesn't exist
    $pdo->exec("ALTER TABLE events ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER description");
    
    echo "Migration complete: 'expires_at' column added successfully.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
