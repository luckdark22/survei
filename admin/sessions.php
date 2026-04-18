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
} elseif ($start_date) {
    $where_clauses[] = "DATE(s.created_at) >= ?";
    $params[] = $start_date;
} elseif ($end_date) {
    $where_clauses[] = "DATE(s.created_at) <= ?";
    $params[] = $end_date;
}

if ($filter_event_id) {
    $where_clauses[] = "s.event_id = ?";
    $params[] = $filter_event_id;
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

// Fetch events for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM events ORDER BY name ASC");
$events_list = $stmt->fetchAll();

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
                <?php if($start_date || $end_date): ?>
                    <a href="sessions" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-lg transition-colors text-sm">RESET</a>
                <?php endif; ?>
            </form>
            
            <a href="export?<?php echo $query_string; ?>" class="w-full md:w-auto text-center px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl transition-colors shadow-[0_4px_14px_rgba(16,185,129,0.3)] text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Unduh Tabel (.CSV)
            </a>
        </div>

        <!-- Summary Statistics / Chart Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Stats Card 1: Total -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-center">
                <span class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">Total Responden</span>
                <div class="text-5xl font-black text-slate-800"><?php echo number_format($total_count); ?></div>
                <div class="mt-2 text-[10px] text-emerald-500 font-bold uppercase tracking-tight flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up"></i> Terkumpul dari sistem
                </div>
            </div>

            <!-- Stats Card 2: Chart Container -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-chart-simple text-amber-500"></i> Distribusi Kepuasan
                    </h3>
                    <span class="text-[10px] text-slate-400 font-bold uppercase italic">Berdasarkan Pertanyaan Rating</span>
                </div>
                <div class="h-48">
                    <canvas id="satisfactionChart"></canvas>
                </div>
            </div>
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
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
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
        });
    </script>
    <?php require_once 'includes/footer.php'; ?>
