<?php
// database/setup.php
// Run this script via CLI: php database/setup.php

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect without a specific DB first to create it
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS db_survei_kiosk");
    echo "Database 'db_survei_kiosk' created successfully.\n";

    // Re-connect to the new database
    $pdo = new PDO("mysql:host=$host;dbname=db_survei_kiosk;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'users' created successfully.\n";

    // Create Questions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_key VARCHAR(50) UNIQUE NOT NULL,
        section VARCHAR(50) NOT NULL,
        question TEXT NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'rating',
        placeholder TEXT NULL,
        order_num INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'questions' created successfully.\n";

    // Create Survey Sessions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(100) DEFAULT 'kiosk_main',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'survey_sessions' created successfully.\n";

    // Create Survey Answers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        answer_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES survey_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )");
    echo "Table 'survey_answers' created successfully.\n";

    // Seed Admin User (username: admin, password: admin)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute(['admin', $hashed_password]);
        echo "Default admin user created successfully (admin / admin).\n";
    }

    // Seed Initial Questions
    $stmt = $pdo->query("SELECT COUNT(*) FROM questions");
    if ($stmt->fetchColumn() == 0) {
        $questions = [
            [
                'key' => 'q1_loket',
                'section' => 'LAYANAN LOKET / FRONT DESK',
                'question' => 'Bagaimana Anda menilai kejelasan informasi produk layanan dan keramahan petugas yang kami berikan?',
                'type' => 'rating',
                'order_num' => 1,
                'placeholder' => null
            ],
            [
                'key' => 'q2_fasilitas',
                'section' => 'FASILITAS & KENYAMANAN',
                'question' => 'Seberapa baikkah tingkat kebersihan, kenyamanan ruang tunggu, serta kelengkapan sarana yang tersedia?',
                'type' => 'rating',
                'order_num' => 2,
                'placeholder' => null
            ],
            [
                'key' => 'q3_sistem',
                'section' => 'KEMUDAHAN SISTEM',
                'question' => 'Secara keseluruhan, seberapa mudah prosedur dan persyaratan layanan yang telah Anda lalui hari ini?',
                'type' => 'rating',
                'order_num' => 3,
                'placeholder' => null
            ],
            [
                'key' => 'q_saran',
                'section' => 'SARAN & MASUKAN',
                'question' => 'Aspirasi Anda Adalah Prioritas Kami',
                'type' => 'text',
                'order_num' => 4,
                'placeholder' => 'Silakan ketik saran, masukan, atau kritik yang membangun untuk evaluasi dan perbaikan kualitas layanan kami...'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO questions (question_key, section, question, type, order_num, placeholder) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($questions as $q) {
            $stmt->execute([
                $q['key'],
                $q['section'],
                $q['question'],
                $q['type'],
                $q['order_num'],
                $q['placeholder']
            ]);
        }
        echo "Default questions seeded successfully.\n";
    }

    echo "Setup Complete!\n";

} catch(PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
?>
