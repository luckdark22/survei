<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();

    // 1. Add user_id column to events table
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'user_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE events ADD COLUMN user_id INT AFTER id");
        echo "Column 'user_id' added to 'events'.\n";
    }

    // 2. Ensure users.role is correct (if not already)
    // The setup.php already has a role column, but let's make sure it handles names like 'admin' and 'staff'
    // No change needed to table schema if role is already VARCHAR.

    // 3. Promote the first user to 'admin' and others to 'staff' if they aren't already
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $admin_id = $stmt->fetchColumn();

    if ($admin_id) {
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$admin_id]);
        $pdo->prepare("UPDATE users SET role = 'staff' WHERE id != ? AND (role IS NULL OR role = '')")->execute([$admin_id]);
        
        // 4. Assign current events to the primary admin
        $pdo->prepare("UPDATE events SET user_id = ? WHERE user_id IS NULL")->execute([$admin_id]);
        echo "All current events assigned to the primary admin (User ID: $admin_id).\n";
    }

    // 5. Add foreign key constraint for data integrity
    try {
        $pdo->exec("ALTER TABLE events ADD CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "Foreign key constraint 'fk_event_user' added.\n";
    } catch (PDOException $e) {
        // Might already exist
        echo "Foreign key constraint check done (already exists or error handled).\n";
    }

    $pdo->commit();
    echo "User management database patch applied successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
