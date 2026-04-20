<?php
require_once __DIR__ . '/../includes/db.php';

echo "--- Starting Unified Database Migration ---\n";

/**
 * Helper to add column if it doesn't exist
 */
function addColumn($pdo, $table, $column, $definition) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "[+] Added column '$column' to '$table'.\n";
        return true;
    }
    return false;
}

/**
 * Helper to add constraint if it doesn't exist
 */
function addConstraint($pdo, $table, $constraintName, $definition) {
    try {
        $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` $definition");
        echo "[+] Added constraint '$constraintName' to '$table'.\n";
    } catch (PDOException $e) {
        // Usually means it already exists
    }
}

try {
    // 1. Create Tables (Non-destructive)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `role` VARCHAR(20) DEFAULT 'staff',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `events` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT DEFAULT NULL,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `expires_at` DATETIME NULL,
        `is_active` TINYINT(1) DEFAULT 0,
        `is_deleted` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `questions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_id` INT DEFAULT NULL,
        `question_key` VARCHAR(50) UNIQUE NOT NULL,
        `section` VARCHAR(50) NOT NULL,
        `question` TEXT NOT NULL,
        `type` VARCHAR(20) DEFAULT 'rating',
        `placeholder` TEXT NULL,
        `order_num` INT NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `survey_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_id` INT DEFAULT NULL,
        `device_id` VARCHAR(100) DEFAULT 'kiosk_main',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `survey_answers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `question_id` INT DEFAULT NULL,
        `question_text` TEXT,
        `answer_value` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(50) UNIQUE NOT NULL,
        `setting_value` TEXT
    ) ENGINE=InnoDB");

    echo "[*] Base tables verified/created.\n";

    // 2. Incremental Column Updates
    addColumn($pdo, 'users', 'role', "VARCHAR(20) DEFAULT 'staff' AFTER password");
    addColumn($pdo, 'events', 'user_id', "INT DEFAULT NULL AFTER id");
    addColumn($pdo, 'events', 'expires_at', "DATETIME NULL AFTER description");
    addColumn($pdo, 'events', 'is_deleted', "TINYINT(1) DEFAULT 0 AFTER is_active");
    addColumn($pdo, 'questions', 'event_id', "INT DEFAULT NULL AFTER id");
    addColumn($pdo, 'survey_sessions', 'event_id', "INT DEFAULT NULL AFTER id");
    addColumn($pdo, 'survey_answers', 'question_text', "TEXT NULL AFTER question_id");

    // 3. Foreign Keys (Safe check)
    addConstraint($pdo, 'events', 'fk_event_user', "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    addConstraint($pdo, 'questions', 'fk_question_event', "FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    addConstraint($pdo, 'survey_sessions', 'fk_session_event', "FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    addConstraint($pdo, 'survey_answers', 'fk_answer_session', "FOREIGN KEY (session_id) REFERENCES survey_sessions(id) ON DELETE CASCADE");
    addConstraint($pdo, 'survey_answers', 'fk_answer_question', "FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL");

    // 4. Default Seed Data (IGNORED if exists)
    $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
        ('instansi_name', 'Direktorat Inovasi & Layanan'),
        ('running_text', 'Selamat Datang di Layanan Survei Kepuasan Masyarakat!')
    ");

    // Seed Admin if none
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $pass = password_hash('admin', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')")->execute([$pass]);
        echo "[!] Default admin user created (admin / admin).\n";
    }

    // Assign orphaned events to the first admin
    $admin_id = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
    if ($admin_id) {
        $pdo->prepare("UPDATE events SET user_id = ? WHERE user_id IS NULL")->execute([$admin_id]);
    }

    echo "--- Migration Successfully Completed! ---\n";

} catch (Exception $e) {
    die("Migration Error: " . $e->getMessage() . "\n");
}
?>
