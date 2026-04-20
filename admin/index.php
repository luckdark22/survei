<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_event_id = $_GET['event_id'] ?? '';

// If no event filter is set, default to the active event (that the user owns if staff)
if (empty($filter_event_id)) {
    $sql_active = "SELECT id FROM events WHERE is_active = 1";
    if (isStaff()) $sql_active .= " AND user_id = " . (int)getUserId();
    $sql_active .= " LIMIT 1";
    
    $active_id = $pdo->query($sql_active)->fetchColumn();
    if ($active_id) {
        $filter_event_id = $active_id;
    }
}

$where_clause_sessions = [];
$where_clause_answers = [];
$params = [];

if ($start_date && $end_date) {
    $where_clause_sessions[] = "DATE(created_at) BETWEEN ? AND ?";
    $where_clause_answers[] = "DATE(a.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($filter_event_id) {
    $where_clause_sessions[] = "event_id = ?";
    $where_clause_answers[] = "s.event_id = ?";
    $params[] = $filter_event_id;
} elseif (isStaff()) {
    // If staff and no specific event selected, scope to ALL events owned by this staff
    $where_clause_sessions[] = "event_id IN (SELECT id FROM events WHERE user_id = ?)";
    $where_clause_answers[] = "s.event_id IN (SELECT id FROM events WHERE user_id = ?)";
    $params[] = getUserId();
}

$session_where = count($where_clause_sessions) > 0 ? "WHERE " . implode(" AND ", $where_clause_sessions) : "";
$answer_where = count($where_clause_answers) > 0 ? "AND " . implode(" AND ", $where_clause_answers) : "";

// Fetch summary stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM survey_sessions $session_where");
$stmt->execute($params);
$total_sessions = $stmt->fetchColumn();

// Fetch events for filter (staff only see their own)
$sql_ev = "SELECT id, name FROM events WHERE is_deleted = 0";
if (isStaff()) $sql_ev .= " AND user_id = " . (int)getUserId();
$sql_ev .= " ORDER BY name ASC";
$events_list = $pdo->query($sql_ev)->fetchAll();

// Fetch rating breakdowns for charts
$stmt = $pdo->prepare("
    SELECT q.id, q.question, a.answer_value, COUNT(a.id) as total 
    FROM questions q 
    JOIN survey_answers a ON q.id = a.question_id 
    JOIN survey_sessions s ON a.session_id = s.id
    WHERE q.type = 'rating' $answer_where
    GROUP BY q.id, q.question, a.answer_value
");
$stmt->execute($params);
$rating_raw = $stmt->fetchAll();

$charts_data = [];
foreach ($rating_raw as $row) {
    $q_id = $row['id'];
    if (!isset($charts_data[$q_id])) {
        $charts_data[$q_id] = [
            'question' => $row['question'],
            'data' => [
                'sangat_puas' => 0,
                'puas' => 0,
                'cukup_puas' => 0,
                'tidak_puas' => 0
            ]
        ];
    }
    $charts_data[$q_id]['data'][$row['answer_value']] = (int)$row['total'];
}

// Fetch recent textual feedback
$stmt = $pdo->prepare("
    SELECT s.created_at, a.answer_value, a.question_text
    FROM survey_answers a 
    JOIN survey_sessions s ON a.session_id = s.id 
    JOIN questions q ON a.question_id = q.id 
    WHERE q.type = 'text' AND a.answer_value != '' $answer_where
    ORDER BY s.created_at DESC 
    LIMIT 50
");
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Fetch daily trend data for line chart
$trend_where = $start_date && $end_date ? "WHERE DATE(created_at) BETWEEN ? AND ?" : "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$trend_params = $start_date && $end_date ? $params : [];
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as day, COUNT(*) as total 
    FROM survey_sessions 
    $session_where
    GROUP BY DATE(created_at) 
    ORDER BY day ASC
");
$stmt->execute($params);
$trend_raw = $stmt->fetchAll();
$trend_labels = [];
$trend_values = [];
foreach($trend_raw as $t) {
    $trend_labels[] = date('d M', strtotime($t['day']));
    $trend_values[] = (int)$t['total'];
}

$query_string = http_build_query($_GET);
?>
<?php
// Fetch current filter event name
$active_filter_name = "Semua Event";
if ($filter_event_id) {
    foreach($events_list as $ev) {
        if ($ev['id'] == $filter_event_id) {
            $active_filter_name = $ev['name'];
            break;
        }
    }
}

$page_title = "Admin Dashboard";
$page_icon = "fa-chart-pie";
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>';

require_once 'includes/header.php';
?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- Filters & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-100 shadow-sm mb-8 gap-4">
            <form method="GET" class="flex items-end gap-3 w-full md:w-auto">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cari Kapan</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                </div>
                <div class="text-slate-400 font-bold mb-2">-</div>
                <div>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
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
                <button type="submit" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition-colors shadow-sm text-sm">
                    Filter Data
                </button>
                <?php if($start_date): ?>
                    <a href="./" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-lg transition-colors text-sm">Reset</a>
                <?php endif; ?>
            </form>
            
            <a href="export?<?php echo $query_string; ?>" class="w-full md:w-auto text-center px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl transition-colors shadow-[0_4px_14px_rgba(16,185,129,0.3)] text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Export CSV
            </a>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Total Responden</p>
                    <h3 class="text-4xl font-extrabold text-slate-800"><?php echo number_format($total_sessions); ?></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-2xl">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Kepuasan Rata-rata</p>
                    <h3 class="text-4xl font-extrabold text-slate-800">Tinggi</h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-2xl">
                    <i class="fa-solid fa-face-smile-beam"></i>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Total Feedback</p>
                    <h3 class="text-4xl font-extrabold text-slate-800"><?php echo number_format(count($feedbacks)); ?></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="mb-10">
            <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-amber-500"></i> Distribusi Pilihan Ganda
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php $chart_count = 0; foreach($charts_data as $q_id => $c_data): ?>
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-600 mb-4 h-10 line-clamp-2"><?php echo htmlspecialchars($c_data['question']); ?></h3>
                        <div class="relative h-64 w-full">
                            <canvas id="chart_<?php echo $q_id; ?>"></canvas>
                        </div>
                    </div>
                <?php 
                $chart_count++;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Line Chart: Daily Trend -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mb-10">
            <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-blue-500"></i> Tren Responden Harian
            </h2>
            <div class="relative h-72 w-full">
                <canvas id="lineChart"></canvas>
            </div>
        </div>

        <!-- Feedback Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-10">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-comments text-amber-500"></i> Kotak Saran & Masukan (<?php echo count($feedbacks); ?> Terbaru)
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold border-b border-slate-200">Waktu Validasi</th>
                            <th class="px-6 py-4 font-bold border-b border-slate-200">Isi Masukan / Saran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (count($feedbacks) > 0): ?>
                            <?php foreach($feedbacks as $fb): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-medium">
                                        <?php echo date('d M Y, H:i', strtotime($fb['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700 italic">
                                        <div class="text-xs text-slate-400 font-bold mb-1 uppercase tracking-widest"><?php echo htmlspecialchars($fb['question_text']); ?></div>
                                        "<?php echo nl2br(htmlspecialchars($fb['answer_value'])); ?>"
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-slate-400 font-medium">
                                    Belum ada saran yang tercatat.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        Chart.register(ChartDataLabels);

        const chartColors = {
            'sangat_puas': '#10b981',
            'puas': '#3b82f6',
            'cukup_puas': '#f59e0b',
            'tidak_puas': '#ef4444'
        };

        <?php foreach($charts_data as $q_id => $c_data): ?>
            var ctx_<?php echo $q_id; ?> = document.getElementById('chart_<?php echo $q_id; ?>').getContext('2d');
            new Chart(ctx_<?php echo $q_id; ?>, {
                type: 'doughnut',
                data: {
                    labels: ['Sangat Puas', 'Puas', 'Cukup Puas', 'Tidak Puas'],
                    datasets: [{
                        data: [
                            <?php echo $c_data['data']['sangat_puas']; ?>,
                            <?php echo $c_data['data']['puas']; ?>,
                            <?php echo $c_data['data']['cukup_puas']; ?>,
                            <?php echo $c_data['data']['tidak_puas']; ?>
                        ],
                        backgroundColor: [
                            chartColors['sangat_puas'],
                            chartColors['puas'],
                            chartColors['cukup_puas'],
                            chartColors['tidak_puas']
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { family: "'Inter', sans-serif", weight: 'bold', size: 12 }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: 14 },
                            formatter: (value, ctx) => {
                                let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                if (total === 0 || value === 0) return '';
                                let pct = Math.round((value / total) * 100);
                                return pct + '%';
                            },
                            textShadowBlur: 4,
                            textShadowColor: 'rgba(0,0,0,0.3)'
                        }
                    },
                    cutout: '65%'
                }
            });
        <?php endforeach; ?>

        // Line Chart: Daily Trend
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [{
                    label: 'Jumlah Responden',
                    data: <?php echo json_encode($trend_values); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#64748b',
                        font: { weight: 'bold', size: 12 },
                        formatter: (value) => value > 0 ? value : ''
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: "'Inter', sans-serif" } },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        ticks: { font: { family: "'Inter', sans-serif", size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
