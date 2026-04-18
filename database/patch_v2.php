<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=db_survei_kiosk;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE survey_answers;");
    $pdo->exec("TRUNCATE TABLE survey_sessions;");

    // Drop and recreate to safely adjust constraints without querying dynamic FK names
    $pdo->exec("DROP TABLE IF EXISTS survey_answers;");

    $pdo->exec("CREATE TABLE survey_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NULL,
        question_text TEXT NULL,
        answer_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES survey_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
    )");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "Patch V2 Applied successfully.\n";

} catch(PDOException $e) {
    die("Patch failed: " . $e->getMessage() . "\n");
}
?>
