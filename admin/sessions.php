<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_event_id = $_GET['event_id'] ?? '';

// If no event filter is set, default to active event
if (empty($filter_event_id)) {
    $stmt = $pdo->query("SELECT id FROM events WHERE is_active = 1 LIMIT 1");
    $active_id = $stmt->fetchColumn();
    if ($active_id) {
        $filter_event_id = $active_id;
    }
}

$where_clauses = [];
$params = [];

if ($start_date && $end_date) {
    $where_clauses[] = "DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($filter_event_id) {
    $where_clauses[] = "s.event_id = ?";
    $params[] = $filter_event_id;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$stmt = $pdo->prepare("
    SELECT s.id as session_id, s.created_at, q.question_key, a.question_text, a.answer_value, e.name as event_name
    FROM survey_sessions s 
    LEFT JOIN survey_answers a ON s.id = a.session_id 
    LEFT JOIN questions q ON a.question_id = q.id 
    LEFT JOIN events e ON s.event_id = e.id
    $where_sql 
    ORDER BY s.created_at DESC, s.id DESC
    LIMIT 1000
");
$stmt->execute($params);
$raw_data = $stmt->fetchAll();

$unique_qs = [];
$sessions = [];

foreach($raw_data as $row) {
    $sid = $row['session_id'];
    $qkey = $row['question_key'] ?: 'q_hapus_' . md5($row['question_text']);
    $qtext = $row['question_text'] ?: 'Pertanyaan Terhapus';
    
    if (!isset($sessions[$sid])) {
        $sessions[$sid] = [
            'id' => $sid,
            'time' => $row['created_at'],
            'event' => $row['event_name'] ?: 'Umum',
            'answers' => []
        ];
    }
    
    if ($qkey && !isset($unique_qs[$qkey])) {
        $unique_qs[$qkey] = $qtext;
    }
    
    $ans = $row['answer_value'];
    $pretty_ans = str_replace('_', ' ', strtoupper($ans));

    $sessions[$sid]['answers'][$qkey] = $pretty_ans;
}

// Fetch events for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM events ORDER BY name ASC");
$events_list = $stmt->fetchAll();

$query_string = http_build_query($_GET);
?>
<?php
$page_title = "Data Responden";
$page_icon = "fa-table-list";
require_once 'includes/header.php';
?>

    <main class="max-w-[95%] mx-auto px-4 py-10">

        <!-- Filters & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-100 shadow-sm mb-6 gap-4">
            <form method="GET" class="flex items-end gap-3 w-full md:w-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cari Kapan</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                </div>
                <div class="text-slate-400 font-bold mb-2">-</div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filter Event</label>
                    <select name="event_id" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                        <option value="">Semua Event</option>
                        <?php foreach($events_list as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo $filter_event_id == $ev['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white font-black rounded-lg transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] text-sm">
                    FILTER DATA
                </button>
                <?php if($start_date): ?>
                    <a href="sessions" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-lg transition-colors text-sm">RESET</a>
                <?php endif; ?>
            </form>
            
            <a href="export?<?php echo $query_string; ?>" class="w-full md:w-auto text-center px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl transition-colors shadow-[0_4px_14px_rgba(16,185,129,0.3)] text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Unduh Tabel (.CSV)
            </a>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1200px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs tracking-wider">
                            <th class="sticky left-0 bg-slate-50 px-4 py-4 font-black border-b border-slate-200 z-10 w-[120px]">KODE SESI</th>
                            <th class="px-4 py-4 font-black border-b border-slate-200">EVENT</th>
                            <th class="px-4 py-4 font-black border-b border-slate-200 min-w-[150px]">WAKTU PENGISIAN</th>
                            <?php foreach($unique_qs as $key => $title): ?>
                                <th class="px-4 py-4 font-black border-b border-slate-200 min-w-[220px] align-bottom">
                                    <span class="block text-amber-600 text-[9px] uppercase mb-1 tracking-widest font-black">Pertanyaan</span>
                                    <span class="line-clamp-2 leading-tight uppercase font-black text-[11px]" title="<?php echo htmlspecialchars($title); ?>"><?php echo htmlspecialchars($title); ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (count($sessions) > 0): ?>
                            <?php foreach($sessions as $sid => $sess): ?>
                                <tr class="hover:bg-amber-50/20 transition-colors group">
                                    <td class="sticky left-0 bg-white group-hover:bg-amber-50/20 px-4 py-4 font-black text-slate-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                        #<?php echo str_pad($sid, 5, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="px-2 py-1 bg-slate-100 text-slate-500 rounded text-[10px] font-black uppercase tracking-widest"><?php echo htmlspecialchars($sess['event']); ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-500 font-medium">
                                        <?php echo date('d M Y, H:i', strtotime($sess['time'])); ?>
                                    </td>
                                    <?php foreach($unique_qs as $key => $title): ?>
                                        <td class="px-4 py-4 text-slate-700">
                                            <?php 
                                            $ans = isset($sess['answers'][$key]) ? $sess['answers'][$key] : '-';
                                            if ($ans === 'SANGAT PUAS' || $ans === 'PUAS') {
                                                echo '<span class="text-emerald-600 font-bold"><i class="fa-solid fa-face-smile text-emerald-500 mr-1"></i> ' . $ans . '</span>';
                                            } elseif ($ans === 'CUKUP PUAS') {
                                                echo '<span class="text-amber-600 font-bold"><i class="fa-solid fa-face-meh text-amber-500 mr-1"></i> ' . $ans . '</span>';
                                            } elseif ($ans === 'TIDAK PUAS') {
                                                echo '<span class="text-red-600 font-bold"><i class="fa-solid fa-face-frown text-red-500 mr-1"></i> ' . $ans . '</span>';
                                            } else {
                                                echo '<div class="line-clamp-3 text-slate-600 italic" title="' . htmlspecialchars($ans) . '">' . htmlspecialchars($ans) . '</div>';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo count($unique_qs) + 2; ?>" class="px-6 py-12 text-center text-slate-400 font-medium text-lg">
                                    <i class="fa-solid fa-folder-open text-4xl mb-3 block text-slate-300"></i>
                                    Tidak ada data untuk rentang waktu yang dipilih.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 text-xs text-slate-400 font-medium">
                Menampilkan <?php echo count($sessions); ?> respon pengunjung (*maksimal 1000 ditayangkan).
            </div>
        </div>
    <?php require_once 'includes/footer.php'; ?>
