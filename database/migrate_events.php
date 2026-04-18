<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();

    // 1. Create events table
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'events' created or already exists.\n";

    // 2. Add 'Default' event if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO events (name, description, is_active) VALUES ('Survei Umum', 'Event default untuk survei harian', 1)");
        echo "Default event created.\n";
    }

    $default_event_id = $pdo->query("SELECT id FROM events WHERE name = 'Survei Umum' LIMIT 1")->fetchColumn();

    // 3. Add event_id to questions table
    $stmt = $pdo->query("SHOW COLUMNS FROM questions LIKE 'event_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN event_id INT AFTER id");
        $pdo->exec("UPDATE questions SET event_id = $default_event_id WHERE event_id IS NULL");
        $pdo->exec("ALTER TABLE questions ADD CONSTRAINT fk_question_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
        echo "Column 'event_id' added to 'questions' and linked to default event.\n";
    }

    // 4. Add event_id to survey_sessions table
    $stmt = $pdo->query("SHOW COLUMNS FROM survey_sessions LIKE 'event_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE survey_sessions ADD COLUMN event_id INT AFTER id");
        $pdo->exec("UPDATE survey_sessions SET event_id = $default_event_id WHERE event_id IS NULL");
        $pdo->exec("ALTER TABLE survey_sessions ADD CONSTRAINT fk_session_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
        echo "Column 'event_id' added to 'survey_sessions' and linked to default event.\n";
    }

    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
