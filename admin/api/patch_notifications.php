<?php
require_once __DIR__ . '/../../includes/db.php';
// require_once __DIR__ . '/../../includes/auth.php';
// checkAuth();

// if (!isAdmin()) {
//     die("Unauthorized.");
// }

try {
    // Check if column exists first
    $check = $pdo->query("SHOW COLUMNS FROM survey_sessions LIKE 'is_read'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE survey_sessions ADD COLUMN is_read TINYINT(1) DEFAULT 0");
        echo "Column 'is_read' added successfully.";
    } else {
        echo "Column 'is_read' already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
