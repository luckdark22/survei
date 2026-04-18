<?php
// database/add_indexes.php
require_once 'includes/db.php';

try {
    echo "Starting index creation...\n";

    // Index for event_id on questions
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_questions_event ON questions(event_id)");
    echo "Index 'idx_questions_event' created successfully.\n";

    // Index for event_id on survey_sessions
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_event ON survey_sessions(event_id)");
    echo "Index 'idx_sessions_event' created successfully.\n";

    // Index for created_at on survey_sessions (sorting and date filtering)
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_created ON survey_sessions(created_at)");
    echo "Index 'idx_sessions_created' created successfully.\n";

    // Index for type on questions (filtering chart data)
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_questions_type ON questions(type)");
    echo "Index 'idx_questions_type' created successfully.\n";

    echo "Indexing migration complete!\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "Some indexes already exist. Migration finished with warnings.\n";
    } else {
        die("Index creation failed: " . $e->getMessage() . "\n");
    }
}
