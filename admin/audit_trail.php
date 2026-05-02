<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/utils.php';
checkAuth();

// Only admin can see audit logs
if (!isAdmin()) {
    header("Location: index");
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

$stmt_count = $pdo->query("SELECT COUNT(*) FROM audit_logs");
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$page_title = "Audit Trail";
require_once 'includes/header.php';
?>

<style>
    .audit-container { padding: 40px; max-width: 1400px; margin: 0 auto; }
    .table-card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); }
    .custom-table { width: 100%; border-collapse: collapse; }
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
    }
    .custom-table tbody td {
        padding: 18px 24px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }
    .badge-action {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-create { background: #ecfdf5; color: #10b981; }
    .badge-update { background: #eff6ff; color: #3b82f6; }
    .badge-delete { background: #fff1f2; color: #f43f5e; }
    .badge-default { background: #f1f5f9; color: #64748b; }
    
    @media (max-width: 768px) {
        .audit-container { padding: 20px; }
        .custom-table { min-width: 800px; }
    }
</style>

<main class="audit-container">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Audit Trail</h1>
            <p class="text-slate-500 font-medium mt-1">Log aktivitas administratif sistem.</p>
        </div>
        <div class="bg-amber-500/10 text-amber-600 px-4 py-2 rounded-xl border border-amber-500/20 text-[10px] font-black uppercase tracking-widest flex items-center self-start">
            <i class="fa-solid fa-shield-halved mr-2"></i> System Security Log
        </div>
    </div>

    <div class="table-card">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="font-black text-slate-800"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 mt-1"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-[11px] font-black text-slate-500 border border-slate-200">
                                            <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                        </div>
                                        <span class="font-bold text-slate-700"><?php echo htmlspecialchars($log['username']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-default';
                                        if(strpos($log['action'], 'CREATE') !== false) $badgeClass = 'badge-create';
                                        elseif(strpos($log['action'], 'DELETE') !== false) $badgeClass = 'badge-delete';
                                        elseif(strpos($log['action'], 'UPDATE') !== false) $badgeClass = 'badge-update';
                                    ?>
                                    <span class="badge-action <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <p class="text-xs text-slate-500 font-medium max-w-xs truncate" title="<?php echo htmlspecialchars($log['details']); ?>">
                                        <?php echo htmlspecialchars($log['details'] ?: '-'); ?>
                                    </p>
                                </td>
                                <td class="font-mono text-slate-400 font-bold text-[11px]">
                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-20 text-center">
                                <i class="fa-solid fa-inbox text-4xl text-slate-200 mb-3 block"></i>
                                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Belum ada log aktivitas</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row gap-4 justify-between items-center">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_items; ?> logs)
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black text-slate-600 hover:text-amber-500 hover:border-amber-200 transition-all shadow-sm">
                            <i class="fa-solid fa-chevron-left"></i> SEBELUMNYA
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black text-slate-600 hover:text-amber-500 hover:border-amber-200 transition-all shadow-sm">
                            BERIKUTNYA <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
