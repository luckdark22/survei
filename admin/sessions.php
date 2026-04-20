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
        $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ? AND is_deleted = 0");
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

// MARK AS READ: When admin/staff views the sessions, mark them as read
try {
    $update_sql = "UPDATE survey_sessions s SET s.is_read = 1 $where_sql";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute($params);
} catch (PDOException $e) {
    // Silently fail
}

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

<style>
    /* CUSTOM SESSION STYLES - Enterprise Look */
    .session-container {
        padding: 40px;
        background-color: #f8fafc;
        min-height: 100vh;
    }

    /* Filter Bar Styling */
    .custom-filter-bar {
        background: #ffffff;
        padding: 24px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-bottom: 32px;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 16px;
    }
    .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 180px; }
    .filter-label { font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; }
    .filter-input { 
        height: 42px; 
        padding: 0 16px; 
        background: #f8fafc; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        font-size: 13px; 
        font-weight: 600; 
        color: #1e293b;
        transition: all 0.2s;
        width: 100%;
    }
    .filter-input:focus { border-color: #f59e0b; outline: none; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1); }
    .btn-filter { 
        height: 44px; 
        padding: 0 24px; 
        background: #0f172a; 
        color: white; 
        font-weight: 800; 
        font-size: 11px; 
        text-transform: uppercase; 
        letter-spacing: 1px;
        border-radius: 12px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        width: auto;
    }
    
    @media (max-width: 1023px) {
        .custom-filter-bar { padding: 16px; margin-bottom: 24px; flex-direction: column; align-items: stretch; }
        .filter-group { width: 100%; flex: none !important; }
        .btn-filter, .btn-group-responsive { width: 100% !important; justify-content: center; }
        .btn-group-responsive { display: flex; gap: 12px; }
        .session-container { padding: 20px; }
    }
    
    .btn-filter:hover { background: #000000; transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }

    /* Stat Cards */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    @media (max-width: 640px) {
        .stat-grid { grid-template-columns: 1fr; }
    }.custom-stat-card {
        background: #ffffff;
        padding: 20px 24px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .custom-stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.05); }
    .stat-label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px; display: block; }
    .stat-val { font-size: 32px; font-weight: 950; color: #1e293b; line-height: 1; letter-spacing: -1px; }
    .stat-icon { position: absolute; right: -10px; bottom: -10px; font-size: 72px; color: #f1f5f9; opacity: 0.5; transform: rotate(-15deg); transition: all 0.3s; }
    .custom-stat-card:hover .stat-icon { color: #f59e0b; opacity: 0.15; transform: rotate(0deg) scale(1.1); }

    /* Data Table Styling */
    .table-card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); }
    .custom-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
    .custom-table thead th {
        background: #f8fafc;
        padding: 20px 24px;
        text-align: left;
        font-size: 10px;
        font-weight: 900;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .custom-table tbody td {
        padding: 18px 24px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }
    .custom-table tbody tr:hover td { background: #fdfaf5; }
    
    /* Sticky Column Fix */
    .sticky-col { 
        position: sticky; 
        left: 0; 
        background: white; 
        z-index: 5;
        box-shadow: 2px 0 10px rgba(0,0,0,0.03); 
    }
    .custom-table thead th.sticky-col { background: #f8fafc; z-index: 11; }
    
    .rating-badge { padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; }
    .rating-high { background: #ecfdf5; color: #059669; }
    .rating-med { background: #fffbeb; color: #d97706; }
    .rating-low { background: #fef2f2; color: #dc2626; }

    /* Charts Row */
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 32px; margin-bottom: 48px; }
    .chart-card { background: white; padding: 24px; border-radius: 24px; border: 1px solid #e2e8f0; }
    .chart-title { font-size: 13px; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }

    @media print {
        .session-container { padding: 0; background: white; }
        .custom-filter-bar, .btn-filter, aside, header { display: none !important; }
        .table-card { border: none; box-shadow: none; }
        .custom-table thead th { background: white !important; color: black !important; border-bottom: 2px solid black !important; }
    }
</style>

<div class="session-container">
    
    <!-- Filter Bar -->
    <div class="custom-filter-bar">
        <form method="GET" class="flex flex-wrap items-end gap-4 w-full">
            <div class="filter-group">
                <label class="filter-label">Tanggal Mulai</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="filter-input">
            </div>
            <div class="filter-group">
                <label class="filter-label">Tanggal Akhir</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="filter-input">
            </div>
            <div class="filter-group" style="flex: 1.5;">
                <label class="filter-label">Berdasarkan Event</label>
                <select name="event_id" class="filter-input">
                    <option value=""><?php echo isStaff() ? 'Semua Event Saya' : 'Seluruh Event'; ?></option>
                    <?php foreach($events_list as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>" <?php echo $filter_event_id == $ev['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ev['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-filter"></i> Tampilkan Data
            </button>
            <div class="btn-group-responsive flex gap-2">
                <a href="export?<?php echo $query_string; ?>" class="btn-filter flex-1" style="background: #10b981;">
                    <i class="fa-solid fa-file-excel"></i> <span class="lg:hidden">Excel</span>
                </a>
                <button type="button" onclick="window.print()" class="btn-filter flex-1" style="background: #f43f5e;">
                    <i class="fa-solid fa-file-pdf"></i> <span class="lg:hidden">PDF</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="stat-grid">
        <div class="custom-stat-card" style="border-left: 4px solid #f59e0b;">
            <i class="fa-solid fa-users stat-icon"></i>
            <span class="stat-label">Total Responden</span>
            <div class="stat-val"><?php echo number_format($total_count); ?></div>
        </div>
        <div class="custom-stat-card" style="border-left: 4px solid #10b981;">
            <i class="fa-solid fa-chart-line stat-icon"></i>
            <span class="stat-label">IKM Rata-Rata</span>
            <div class="stat-val" style="color: #10b981;">
                <?php 
                    $total_avg = 0;
                    if(count($avg_data) > 0) {
                        foreach($avg_data as $ad) $total_avg += $ad['average'];
                        echo number_format($total_avg / count($avg_data), 2);
                    } else echo "0.00";
                ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3 class="chart-title"><i class="fa-solid fa-chart-pie text-amber-500"></i> Distribusi Kepuasan</h3>
            <div style="height: 300px;">
                <canvas id="satisfactionChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3 class="chart-title"><i class="fa-solid fa-chart-bar text-emerald-500"></i> Performa per Pertanyaan</h3>
            <div style="height: 350px; overflow-y: auto;" class="custom-scrollbar">
                <canvas id="averageScoreChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th class="sticky-col">KODE SESI</th>
                        <th>NAMA EVENT</th>
                        <th>WAKTU PENGISIAN</th>
                        <?php foreach($unique_qs as $key => $title): ?>
                            <th style="min-width: 250px;">
                                <?php echo htmlspecialchars($title); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sessions) > 0): ?>
                        <?php foreach($sessions as $sid => $sess): ?>
                            <tr>
                                <td class="sticky-col font-black" style="color: #f59e0b;">#<?php echo str_pad($sid, 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($sess['event']); ?></td>
                                <td style="color: #94a3b8;"><?php echo date('d M Y, H:i', strtotime($sess['time'])); ?></td>
                                <?php foreach($unique_qs as $key => $title): ?>
                                    <td>
                                        <?php 
                                        $ans = isset($sess['answers'][$key]) ? $sess['answers'][$key] : '-';
                                        if ($ans === 'SANGAT PUAS' || $ans === 'PUAS') {
                                            echo '<span class="rating-badge rating-high"><i class="fa-solid fa-face-smile"></i> ' . $ans . '</span>';
                                        } elseif ($ans === 'CUKUP PUAS') {
                                            echo '<span class="rating-badge rating-med"><i class="fa-solid fa-face-meh"></i> ' . $ans . '</span>';
                                        } elseif ($ans === 'TIDAK PUAS') {
                                            echo '<span class="rating-badge rating-low"><i class="fa-solid fa-face-frown"></i> ' . $ans . '</span>';
                                        } else {
                                            echo htmlspecialchars($ans);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="100" style="text-align:center; padding: 100px 0; color: #94a3b8;">Tidak ada data ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination UI -->
        <div style="padding: 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">
                Menampilkan <?php echo count($sessions); ?> dari <?php echo $total_count; ?> entries
            </div>
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; gap: 8px;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>" 
                           style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; text-decoration: none; transition: all 0.2s; <?php echo $i === $page ? 'background: #f59e0b; color: white;' : 'background: white; color: #64748b; border: 1px solid #e2e8f0;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
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
<?php require_once 'includes/footer.php'; ?>
