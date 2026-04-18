<?php
$pdo = new PDO('mysql:host=localhost;dbname=db_survei_kiosk;charset=utf8mb4','root','');
$pdo->exec('ALTER DATABASE db_survei_kiosk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE questions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('ALTER TABLE survey_answers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "All tables converted to utf8mb4.\n";
?>
