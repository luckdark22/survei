<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$order = $_POST['order'] ?? [];
$event_id = $_POST['event_id'] ?? null;

if (empty($order) || !$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// Security: Staff can only reorder their own event's questions
if (isStaff()) {
    $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
    $check->execute([$event_id, getUserId()]);
    if (!$check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE questions SET order_num = ? WHERE id = ? AND event_id = ?");
    foreach ($order as $index => $question_id) {
        $stmt->execute([$index + 1, (int)$question_id, (int)$event_id]);
    }
    $pdo->commit();

    logActivity($pdo, 'REORDER_QUESTIONS', "Reordered questions for event ID: $event_id");

    echo json_encode(['success' => true, 'message' => 'Order updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
