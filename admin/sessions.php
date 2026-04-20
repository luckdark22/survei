<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_event_id = $_GET['event_id'] ?? '';

// If no event filter is set, default to active event (owned by user if staff)
if (empty($filter_event_id)) {
    $sql_active = "SELECT id FROM events WHERE is_active = 1";
    if (isStaff()) $sql_active .= " AND user_id = " . (int)getUserId();
    $sql_active .= " LIMIT 1";
    
    $active_id = $pdo->query($sql_active)->fetchColumn();
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
} elseif ($start_date) {
    $where_clauses[] = "DATE(s.created_at) >= ?";
    $params[] = $start_date;
} elseif ($end_date) {
    $where_clauses[] = "DATE(s.created_at) <= ?";
    $params[] = $end_date;
}

if ($filter_event_id) {
    if (isStaff()) {
        // Double check ownership
        $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
        $check->execute([$filter_event_id, getUserId()]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "Akses ditolak.";
            header("Location: sessions"); exit;
        }
    }
    $where_clauses[] = "s.event_id = ?";
    $params[] = $filter_event_id;
} elseif (isStaff()) {
    $where_clauses[] = "s.event_id IN (SELECT id FROM events WHERE user_id = ?)";
    $params[] = getUserId();
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Pagination Logic
$items_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Count total unique sessions
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_sessions s $where_sql");
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// Fetch only IDs for the current page
$id_stmt = $pdo->prepare("SELECT s.id FROM survey_sessions s $where_sql ORDER BY s.created_at DESC, s.id DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $val) {
    $id_stmt->bindValue($i + 1, $val);
}
$id_stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$id_stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$id_stmt->execute();
$session_ids = $id_stmt->fetchAll(PDO::FETCH_COLUMN);

$raw_data = [];
if (!empty($session_ids)) {
    $placeholders = implode(',', array_fill(0, count($session_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id as session_id, s.created_at, q.question_key, a.question_text, a.answer_value, e.name as event_name
        FROM survey_sessions s 
        LEFT JOIN survey_answers a ON s.id = a.session_id 
        LEFT JOIN questions q ON a.question_id = q.id 
        LEFT JOIN events e ON s.event_id = e.id
        WHERE s.id IN ($placeholders)
        ORDER BY s.created_at DESC, s.id DESC
    ");
    $stmt->execute($session_ids);
    $raw_data = $stmt->fetchAll();
}

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

// Fetch events for filter dropdown (staff only see their own)
$sql_ev = "SELECT id, name FROM events WHERE is_deleted = 0";
if (isStaff()) $sql_ev .= " AND user_id = " . (int)getUserId();
$sql_ev .= " ORDER BY name ASC";
$events_list = $pdo->query($sql_ev)->fetchAll();

// Aggregation for Charts (Total satisfaction breakdown)
$agg_stmt = $pdo->prepare("
    SELECT a.answer_value, COUNT(*) as count 
    FROM survey_answers a 
    JOIN survey_sessions s ON a.session_id = s.id 
    JOIN questions q ON a.question_id = q.id
    $where_sql AND q.type = 'rating'
    GROUP BY a.answer_value
");
$agg_stmt->execute($params);
$chart_raw = $agg_stmt->fetchAll();

$chart_data = [
    'SANGAT_PUAS' => 0,
    'PUAS' => 0,
    'CUKUP_PUAS' => 0,
    'TIDAK_PUAS' => 0
];
foreach($chart_raw as $cr) {
    $key = strtoupper($cr['answer_value']);
    if(isset($chart_data[$key])) {
        $chart_data[$key] = (int)$cr['count'];
    }
}

// Aggregation for Average Score per Question
$avg_stmt = $pdo->prepare("
    SELECT q.id, q.question, 
           AVG(CASE 
               WHEN UPPER(a.answer_value) = 'SANGAT_PUAS' THEN 4
               WHEN UPPER(a.answer_value) = 'PUAS' THEN 3
               WHEN UPPER(a.answer_value) = 'CUKUP_PUAS' THEN 2
               WHEN UPPER(a.answer_value) = 'TIDAK_PUAS' THEN 1
               ELSE 0 
           END) as average
    FROM survey_answers a
    JOIN survey_sessions s ON a.session_id = s.id
    JOIN questions q ON a.question_id = q.id
    $where_sql AND q.type = 'rating'
    GROUP BY q.id, q.question
    HAVING average > 0
    ORDER BY average DESC
");
$avg_stmt->execute($params);
$avg_data = $avg_stmt->fetchAll();

$query_string = http_build_query($_GET);

// Setup PDF Report Headers
$active_event_name = "Semua Event (Global)";
if ($filter_event_id && !empty($events_list)) {
    foreach($events_list as $ev) {
        if ($ev['id'] == $filter_event_id) {
            $active_event_name = $ev['name'];
            break;
        }
    }
}
$periode_text = "Semua Waktu";
if ($start_date && $end_date) {
    if ($start_date === $end_date) {
        $periode_text = date('d M Y', strtotime($start_date));
    } else {
        $periode_text = date('d M Y', strtotime($start_date)) . " s/d " . date('d M Y', strtotime($end_date));
    }
} elseif ($start_date) {
    $periode_text = "Sejak " . date('d M Y', strtotime($start_date));
} elseif ($end_date) {
    $periode_text = "Hingga " . date('d M Y', strtotime($end_date));
}
?>
<?php
$page_title = "Data Responden";
$page_icon = "fa-table-list";
require_once 'includes/header.php';
?>

    <main class="max-w-[95%] mx-auto px-4 py-10">

        <!-- Print PDF Header (Hidden on Screen) -->
        <div class="hidden print-only mb-6 border-b-2 border-slate-800 pb-4">
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                    <h1 style="font-size: 20pt; font-weight: 900; color: #0f172a; margin: 0; line-height: 1.2;">Laporan Hasil Kepuasan</h1>
                    <h2 style="font-size: 14pt; font-weight: 900; color: #d97706; margin: 0; text-transform: uppercase; margin-top: 4px;"><?php echo htmlspecialchars($active_event_name); ?></h2>
                </div>
                <div style="text-align: right; font-size: 9pt; color: #475569; line-height: 1.5;">
                    <div><strong>Periode:</strong> <?php echo $periode_text; ?></div>
                    <div><strong>Total Responden:</strong> <?php echo number_format($total_count); ?> orang</div>
                    <div><strong>Waktu Cetak:</strong> <?php echo date('d M Y, H:i'); ?></div>
                </div>
            </div>
        </div>

        <!-- Filters & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-100 shadow-sm mb-6 gap-4 print-hidden">
            <form method="GET" class="flex items-end gap-3 w-full md:w-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mulai</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                </div>
                <div class="text-slate-400 font-bold mb-2">s/d</div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hingga</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filter Event</label>
                    <select name="event_id" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                        <option value=""><?php echo isStaff() ? 'Semua Event Saya' : 'Semua Event'; ?></option>
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
                <?php if($start_date || $end_date): ?>
                    <a href="sessions" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-lg transition-colors text-sm">RESET</a>
                <?php endif; ?>
            </form>
            <div class="flex items-center gap-2 w-full md:w-auto">
                <a href="export?<?php echo $query_string; ?>" class="flex-1 text-center px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl transition-colors shadow-[0_4px_14px_rgba(16,185,129,0.3)] text-xs md:text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-csv text-base"></i> <span class="hidden md:inline">Ekspor CSV</span>
                </a>
                <button onclick="window.print()" style="background:#f43f5e; color:#ffffff;" class="flex-1 text-center px-4 py-2.5 font-bold rounded-xl transition-colors shadow-[0_4px_14px_rgba(244,63,94,0.3)] text-xs md:text-sm flex items-center justify-center gap-2" title="Cetak / Simpan PDF">
                    <i class="fa-solid fa-file-pdf text-base"></i> <span class="hidden md:inline">Ekspor PDF</span>
                </button>
            </div>
        </div>

        <!-- Row 1: Key Stats Summary -->
        <div class="mb-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-center max-w-[280px]">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Total Responden</span>
                <div class="text-5xl font-black text-slate-800"><?php echo number_format($total_count); ?></div>
                <div class="mt-2 text-[10px] text-emerald-500 font-bold uppercase tracking-tight flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up"></i> Terkumpul dari sistem
                </div>
            </div>
        </div>

        <!-- Row 2: Charts Side-by-Side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10 print-chart-row">
            <!-- Global Distribution Chart -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm print-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-chart-simple text-amber-500"></i> Distribusi Kepuasan
                    </h3>
                    <span class="text-[10px] text-slate-400 font-bold uppercase italic">Global</span>
                </div>
                <div class="h-64">
                    <canvas id="satisfactionChart"></canvas>
                </div>
            </div>

            <!-- Trend per Pertanyaan Chart -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm print-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-emerald-500"></i> Trend per Pertanyaan
                    </h3>
                    <span class="text-[10px] text-slate-400 font-bold uppercase italic">Skala 1.0 - 4.0</span>
                </div>
                <div class="w-full overflow-y-auto" style="max-height: 400px; min-height: <?php echo min(400, count($avg_data) * 60 + 50); ?>px;">
                    <canvas id="averageScoreChart"></canvas>
                </div>
            </div>
        </div>


        <!-- Data Table (Screen Only) -->
        <div class="screen-only bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
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
        </div>

        <!-- Data List: Cards Flow (PDF Only) -->
        <div class="pdf-only space-y-6">
            <?php if (count($sessions) > 0): ?>
                <?php foreach($sessions as $sid => $sess): ?>
                    <!-- Session Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden print-session-card">
                        <!-- Session Header -->
                        <div class="bg-slate-50 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center p-5 gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-amber-100 flex justify-center items-center text-amber-600 shadow-inner">
                                    <i class="fa-solid fa-user-check text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Kode Sesi</div>
                                    <div class="text-xl font-black text-slate-800">#<?php echo str_pad($sid, 5, '0', STR_PAD_LEFT); ?></div>
                                </div>
                            </div>
                            <div class="flex flex-col md:items-end gap-2 text-left md:text-right">
                                <div class="px-3 py-1 bg-amber-50 rounded-lg border border-amber-100 text-amber-600 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                                    <i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($sess['event']); ?>
                                </div>
                                <div class="text-xs font-bold text-slate-500">
                                    <i class="fa-regular fa-clock"></i> <?php echo date('d M Y, H:i', strtotime($sess['time'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Questions & Answers Wrapper -->
                        <div class="p-6 bg-white">
                            <div class="print-qa-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                                <?php foreach($unique_qs as $key => $title): ?>
                                    <!-- Inner QA Card -->
                                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100 flex flex-col justify-between hover:border-amber-200 hover:shadow-md transition-all group print-qa-card" style="page-break-inside: avoid;">
                                        <div class="text-[11px] text-slate-600 font-bold mb-3 uppercase tracking-wide leading-relaxed group-hover:text-amber-700 transition-colors">
                                            <span class="text-amber-500 font-black mr-1">Q.</span> <?php echo htmlspecialchars($title); ?>
                                        </div>
                                        <div class="text-sm border-t border-slate-100 pt-3 mt-auto font-black text-slate-900">
                                            <?php 
                                            $ans = isset($sess['answers'][$key]) ? $sess['answers'][$key] : '-';
                                            if ($ans === 'SANGAT PUAS' || $ans === 'PUAS') {
                                                echo '<span class="text-emerald-600 flex items-center gap-2"><i class="fa-solid fa-face-smile text-emerald-500 text-lg"></i> ' . $ans . '</span>';
                                            } elseif ($ans === 'CUKUP PUAS') {
                                                echo '<span class="text-amber-600 flex items-center gap-2"><i class="fa-solid fa-face-meh text-amber-500 text-lg"></i> ' . $ans . '</span>';
                                            } elseif ($ans === 'TIDAK PUAS') {
                                                echo '<span class="text-rose-600 flex items-center gap-2"><i class="fa-solid fa-face-frown text-rose-500 text-lg"></i> ' . $ans . '</span>';
                                            } else {
                                                echo '<div class="italic text-slate-700 font-medium line-clamp-5 leading-relaxed">' . htmlspecialchars($ans) . '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-2xl border border-slate-100 p-12 flex flex-col items-center justify-center text-center">
                    <i class="fa-solid fa-folder-open text-5xl mb-4 text-slate-300"></i>
                    <h3 class="text-lg font-bold text-slate-500 mb-1">Data Kosong</h3>
                    <p class="text-slate-400 text-sm">Tidak ada respons untuk dirender pada periode filter ini.</p>
                </div>
            <?php endif; ?>
        </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-xs text-slate-500 font-medium tracking-wide">
                    Menampilkan <span class="text-slate-900 font-black"><?php echo count($sessions); ?></span> dari <span class="text-slate-900 font-black"><?php echo $total_count; ?></span> responden
                </div>

                <!-- Pagination Nav -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex items-center gap-1">
                        <?php 
                        $filter_params = $_GET;
                        unset($filter_params['page']);
                        $base_params = http_build_query($filter_params);
                        $base_params = $base_params ? '&' . $base_params : '';
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $base_params; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 transition-all">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo $base_params; ?>" 
                               class="w-9 h-9 flex items-center justify-center rounded-xl border <?php echo $i === $page ? 'bg-amber-500 border-amber-500 text-white font-black shadow-lg shadow-amber-500/30' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?> transition-all text-xs">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $base_params; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 transition-all">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('satisfactionChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['SANGAT PUAS', 'PUAS', 'CUKUP PUAS', 'TIDAK PUAS'],
                    datasets: [{
                        label: 'Jumlah Respon',
                        data: [
                            <?php echo $chart_data['SANGAT_PUAS']; ?>,
                            <?php echo $chart_data['PUAS']; ?>,
                            <?php echo $chart_data['CUKUP_PUAS']; ?>,
                            <?php echo $chart_data['TIDAK_PUAS']; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)', // Emerald 500
                            'rgba(34, 197, 94, 0.8)',  // Green 500
                            'rgba(245, 158, 11, 0.8)', // Amber 500
                            'rgba(239, 68, 68, 0.8)'   // Red 500
                        ],
                        borderRadius: 12,
                        barThickness: 40
                    }]
                },
                plugins: [{
                    id: 'datalabels',
                    afterDatasetsDraw(chart) {
                        const { ctx, data } = chart;
                        ctx.save();
                        chart.data.datasets.forEach((dataset, i) => {
                            chart.getDatasetMeta(i).data.forEach((bar, index) => {
                                const val = dataset.data[index];
                                if (val > 0) {
                                    ctx.fillStyle = '#334155'; // Slate 700
                                    ctx.font = 'bold 12px Inter, sans-serif';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'bottom';
                                    ctx.fillText(val, bar.x, bar.y - 8);
                                }
                            });
                        });
                        ctx.restore();
                    }
                }],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grace: '15%',
                            grid: {
                                display: true,
                                color: 'rgba(0,0,0,0.03)'
                            },
                            ticks: {
                                stepSize: 1,
                                font: { size: 10, weight: 'bold' }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 9, weight: 'bold' }
                            }
                        }
                    }
                }
            });

            // NEW: Average Score Chart (Horizontal Bar)
            const avgCtx = document.getElementById('averageScoreChart').getContext('2d');
            new Chart(avgCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach($avg_data as $ad): ?>
                            "<?php echo addslashes($ad['question']); ?>",
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Skala Kepuasan',
                        data: [
                            <?php foreach($avg_data as $ad): ?>
                                <?php echo number_format($ad['average'], 2); ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: function(context) {
                            const val = context.dataset.data[context.dataIndex];
                            if (val >= 3.5) return 'rgba(16, 185, 129, 0.8)'; // Emerald
                            if (val >= 3.0) return 'rgba(34, 197, 94, 0.8)';  // Green
                            if (val >= 2.5) return 'rgba(234, 179, 8, 0.8)';  // Yellow
                            return 'rgba(239, 68, 68, 0.8)'; // Red
                        },
                        borderRadius: 8,
                        barThickness: 25
                    }]
                },
                plugins: [{
                    id: 'avgLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx } = chart;
                        ctx.save();
                        chart.data.datasets.forEach((dataset, i) => {
                            chart.getDatasetMeta(i).data.forEach((bar, index) => {
                                const val = dataset.data[index];
                                ctx.fillStyle = '#1e293b';
                                ctx.font = 'bold 11px Inter, sans-serif';
                                ctx.textAlign = 'left';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(val, bar.x + 8, bar.y);
                            });
                        });
                        ctx.restore();
                    }
                }],
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: false,
                            min: 1,
                            max: 4.5, // room for labels
                            grid: { color: 'rgba(0,0,0,0.03)' },
                            ticks: { font: { weight: 'bold' } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 10, weight: 'bold' },
                                callback: function(value) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 50 ? label.substr(0, 47) + '...' : label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <style>
        .screen-only { display: block; }
        .pdf-only { display: none; }
        
        /* Print Stylesheet for Export PDF */
        @media print {
            .screen-only { display: none !important; }
            .pdf-only { display: block !important; }

            /* Show print-only elements */
            .hidden.print-only, .print-only { display: block !important; }
            
            /* Hide unnecessary UI elements */
            aside, header, nav, form, .pagination-container, .print-hidden, button[title="Cetak / Simpan PDF"], a[href^="export"] {
                display: none !important;
            }
            
            /* Expand the main container to fill the page */
            main.max-w-\\[95\\%\\] {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Page Print Settings */
            @page {
                size: portrait; /* switch back to portrait since cards adapt well */
                margin: 10mm;
            }

            /* Adjust chart visibility and page breaks */
            .print-chart-row { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 20px !important; margin-bottom: 2rem !important; }
            .print-chart-grid, .print-col { 
                margin-bottom: 0 !important; 
                page-break-inside: avoid !important;
                border: 2px solid #e2e8f0 !important; 
                background-color: #ffffff !important;
                padding: 15px !important;
                border-radius: 12px !important;
                display: block !important;
            }
            .lg\\:col-span-2 { width: 100% !important; margin-top: 0 !important; }

            /* Fix nested chart height in PDF horizontal mode */
            .print-col .h-64 { height: 250px !important; }
            .print-col div[style*="max-height"] { max-height: none !important; height: auto !important; }
            
            /* Typography refinement for Print */
            h3.text-sm { font-size: 14px !important; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px !important; }
            .text-5xl { font-size: 32px !important; color: #0f172a !important; }
            
            /* Chart Canvas Fix for Bleeding */
            .h-48, .h-48 canvas { max-height: 250px !important; }
            canvas { max-width: 100% !important; width: 100% !important; height: auto !important; }
            
            /* Card and Grid formatting */
            .space-y-6 > :not([hidden]) ~ :not([hidden]) { margin-top: 15px !important; }
            .print-session-card { 
                border: 2px solid #cbd5e1 !important; 
                margin-bottom: 15px !important; 
                box-shadow: none !important; 
                background-color: #ffffff !important;
            }
            .print-qa-grid { display: block !important; }
            .print-qa-card { 
                display: block !important; 
                width: 100% !important; 
                margin-bottom: 10px !important; 
                border: 1px solid #e2e8f0 !important; 
                break-inside: avoid; 
                page-break-inside: avoid;
                background-color: #f8fafc !important;
            }
            
            /* Background colors */
            .bg-emerald-50, .bg-amber-50, .bg-slate-50, .bg-white {
                background-color: #ffffff !important; 
                -webkit-print-color-adjust: exact !important;
            }
            .text-emerald-500, .text-emerald-600 { color: #10b981 !important; }
            .text-amber-500, .text-amber-600 { color: #d97706 !important; }
            .text-rose-500, .text-rose-600, .text-red-500 { color: #e11d48 !important; }
            
            /* Remove shadows */
            .shadow-sm, .shadow-inner, .shadow-\\[.*\\] { box-shadow: none !important; }
        }
    </style>
    <?php require_once 'includes/footer.php'; ?>
