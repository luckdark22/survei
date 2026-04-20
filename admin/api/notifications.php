<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
checkAuth();

$response = [
    'unread_count' => 0,
    'latest' => []
];

try {
    // Count unread sessions
    $sql_count = "SELECT COUNT(*) FROM survey_sessions WHERE is_read = 0";
    if (isStaff()) {
        $sql_count .= " AND event_id IN (SELECT id FROM events WHERE user_id = " . (int)getUserId() . ")";
    }
    
    $stmt_count = $pdo->query($sql_count);
    $response['unread_count'] = (int)$stmt_count->fetchColumn();
    
    // Fetch 5 latest unread
    $sql_latest = "
        SELECT s.id, s.created_at, e.name as event_name 
        FROM survey_sessions s
        LEFT JOIN events e ON s.event_id = e.id
        WHERE s.is_read = 0
    ";
    if (isStaff()) {
        $sql_latest .= " AND s.event_id IN (SELECT id FROM events WHERE user_id = " . (int)getUserId() . ")";
    }
    $sql_latest .= " ORDER BY s.created_at DESC LIMIT 5";
    
    $stmt_latest = $pdo->query($sql_latest);
    $response['latest'] = $stmt_latest->fetchAll();
    
} catch (PDOException $e) {
    // Silently fail or log error
}

header('Content-Type: application/json');
echo json_encode($response);
?>
