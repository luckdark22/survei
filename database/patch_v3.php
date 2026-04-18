<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=db_survei_kiosk;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Table 'settings' created.\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'running_text'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([
            'running_text',
            'Selamat datang di portal Survei Layanan Publik. Masukan Anda sangat berarti bagi peningkatan kualitas pelayanan kami. Kami berkomitmen untuk selalu memberikan pelayanan prima, transparan, dan akuntabel. Terima kasih atas partisipasi Anda.'
        ]);
        echo "Default running text seeded.\n";
    }

    echo "Patch V3 Applied.\n";
} catch(PDOException $e) {
    die("Patch failed: " . $e->getMessage() . "\n");
}
?>
