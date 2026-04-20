<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_event_id = $_GET['event_id'] ?? '';

// If no event filter is set, default to active event (owned by user if staff)
if (empty($filter_event_id)) {
    $sql_active = "SELECT id FROM events WHERE is_active = 1 AND is_deleted = 0";
    if (isStaff()) $sql_active .= " AND user_id = " . (int)getUserId();
    $sql_active .= " LIMIT 1";
    
    $active_id = $pdo->query($sql_active)->fetchColumn();
    if ($active_id) {
        $filter_event_id = $active_id;
    }
}

$where_clause_array = [];
$params = [];

if ($start_date && $end_date) {
    $where_clause_array[] = "DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($filter_event_id) {
    if (isStaff()) {
        // Double check ownership
        $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ? AND is_deleted = 0");
        $check->execute([$filter_event_id, getUserId()]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "Akses ditolak.";
            header("Location: dashboard"); exit;
        }
    }
    $where_clause_array[] = "s.event_id = ?";
    $params[] = $filter_event_id;
} elseif (isStaff()) {
    $where_clause_array[] = "s.event_id IN (SELECT id FROM events WHERE user_id = ?)";
    $params[] = getUserId();
}

$where_clause = count($where_clause_array) > 0 ? "WHERE " . implode(" AND ", $where_clause_array) : "";

// Fetch all questions mapped to history
$stmt = $pdo->prepare("
    SELECT s.id as session_id, s.created_at, q.question_key, a.question_text, a.answer_value, e.name as event_name
    FROM survey_sessions s 
    LEFT JOIN survey_answers a ON s.id = a.session_id 
    LEFT JOIN questions q ON a.question_id = q.id 
    LEFT JOIN events e ON s.event_id = e.id
    $where_clause 
    ORDER BY s.created_at DESC, s.id DESC
");
$stmt->execute($params);
$raw_data = $stmt->fetchAll();

// Pivot data
$unique_qs = [];
$sessions = [];

foreach($raw_data as $row) {
    $sid = $row['session_id'];
    $qkey = $row['question_key'] ?: 'q_hapus_' . md5($row['question_text']); // Fallback key if question deleted
    $qtext = $row['question_text'] ?: 'Pertanyaan Terhapus';
    
    if (!isset($sessions[$sid])) {
        $sessions[$sid] = [
            'id' => $sid,
            'time' => $row['created_at'],
            'event' => $row['event_name'] ?: 'Umum',
            'answers' => []
        ];
    }
    
    // Track unique keys to build dynamic CSV columns
    if (!isset($unique_qs[$qkey])) {
        $unique_qs[$qkey] = $qtext;
    }
    
    // Prettify answer value
    $ans = $row['answer_value'];
    $pretty_ans = str_replace('_', ' ', strtoupper($ans));
    if (strlen($pretty_ans) > 20) $pretty_ans = $ans; // Leave long text as is

    $sessions[$sid]['answers'][$qkey] = $pretty_ans;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Laporan_Survei_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
// Add BOM to fix UTF-8 in Excel
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write Headers
$headers = ['Kode Sesi', 'Event', 'Waktu Pengisian (Timestamp)'];
foreach($unique_qs as $key => $title) {
    $headers[] = $title;
}
fputcsv($output, $headers);

// Write Data Rows
foreach($sessions as $sid => $sess) {
    $row = ["S-" . str_pad($sid, 6, "0", STR_PAD_LEFT), $sess['event'], $sess['time']];
    foreach($unique_qs as $key => $title) {
        $row[] = isset($sess['answers'][$key]) ? $sess['answers'][$key] : '-';
    }
    fputcsv($output, $row);
}
fclose($output);
exit;
?>
