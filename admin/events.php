<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/utils.php';
checkAuth();

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $expires_at = $_POST['expires_at'] ?: null;
            $user_id = getUserId();
            
            $stmt = $pdo->prepare("INSERT INTO events (name, description, expires_at, is_active, user_id) VALUES (?, ?, ?, 0, ?)");
            $stmt->execute([$name, $description, $expires_at, $user_id]);
            $_SESSION['success'] = "Event '$name' berhasil ditambahkan!";
            
        } elseif ($_POST['action'] === 'activate') {
            $id = $_POST['id'];
            
            // Check ownership for staff
            if (isStaff()) {
                $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
                $check->execute([$id, getUserId()]);
                if (!$check->fetch()) {
                    $_SESSION['error'] = "Anda tidak memiliki akses ke event ini.";
                    header("Location: events"); exit;
                }
            }

            // Deactivate all first (for this user if staff, or all if admin)
            if (isAdmin()) {
                $pdo->exec("UPDATE events SET is_active = 0");
            } else {
                $pdo->prepare("UPDATE events SET is_active = 0 WHERE user_id = ?")->execute([getUserId()]);
            }
            
            // Activate selected
            $stmt = $pdo->prepare("UPDATE events SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Event berhasil diaktifkan!";
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            // Check ownership for staff
            if (isStaff()) {
                $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
                $check->execute([$id, getUserId()]);
                if (!$check->fetch()) {
                    $_SESSION['error'] = "Anda tidak memiliki akses untuk menghapus event ini.";
                    header("Location: events"); exit;
                }
            }

            // Soft delete event
            $stmt = $pdo->prepare("UPDATE events SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Event berhasil dihapus!";
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $expires_at = $_POST['expires_at'] ?: null;

            // Check ownership for staff
            if (isStaff()) {
                $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
                $check->execute([$id, getUserId()]);
                if (!$check->fetch()) {
                    $_SESSION['error'] = "Anda tidak memiliki akses untuk mengedit event ini.";
                    header("Location: events"); exit;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE events SET name = ?, description = ?, expires_at = ? WHERE id = ?");
            $stmt->execute([$name, $description, $expires_at, $id]);
            $_SESSION['success'] = "Event '$name' berhasil diperbarui!";
        }
        
        header("Location: events");
        exit;
    }
}

// Pagination & Search Logic
$search = trim($_GET['search'] ?? '');
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Base query for counting and fetching
$where_clause = "WHERE is_deleted = 0";
$query_params = [];

if (isStaff()) {
    $where_clause .= " AND user_id = ?";
    $query_params[] = getUserId();
}

if ($search) {
    $where_clause .= " AND (name LIKE ? OR description LIKE ?)";
    $query_params[] = "%$search%";
    $query_params[] = "%$search%";
}

// Count total events
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM events $where_clause");
$stmt_count->execute($query_params);
$total_count = $stmt_count->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// Fetch events with pagination and search
$sql = "SELECT * FROM events $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($query_params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($query_params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($query_params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();
?>
<?php
$page_title = "Kelola Event";
$page_icon = "fa-calendar-check";
require_once 'includes/header.php';
?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- List of Events -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-10">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-slate-50/50 gap-4">
                <div class="flex items-center gap-4 w-full md:w-auto">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2 whitespace-nowrap">
                        <i class="fa-solid fa-calendar-check text-amber-500"></i> Event
                    </h2>
                    <div style="position: relative; width: 100%; max-width: 256px;">
                        <form method="GET">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 11px; pointer-events: none;"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama event..." 
                                   style="width: 100%; padding: 10px 16px 10px 40px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 12px; font-weight: 500; color: #1e293b; outline: none; transition: all 0.2s;"
                                   onfocus="this.style.borderColor='#f59e0b'; this.style.boxShadow='0 0 0 3px rgba(245, 158, 11, 0.1)';"
                                   onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </form>
                    </div>
                </div>
                <button type="button" onclick="openAddModal()" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-xs transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2 uppercase tracking-widest">
                    <i class="fa-solid fa-plus-circle text-sm"></i> Tambah Event
                </button>
            </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Nama Event</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Batas Waktu</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200 text-center">Status</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php 
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                                $base_url = $protocol . "://" . $host . rtrim(str_replace('/admin', '', $dir), '/') . '/';
                            ?>
                            <?php foreach($events as $e): ?>
                                <?php $share_link = $base_url . "?event_id=" . maskId($e['id']); ?>
                                <tr class="hover:bg-slate-50/50 transition-colors <?php echo $e['is_active'] ? 'bg-amber-50/30' : ''; ?>">
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-slate-800"><?php echo htmlspecialchars($e['name']); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[150px]" title="<?php echo htmlspecialchars($e['description']); ?>">
                                            <?php echo htmlspecialchars($e['description']) ?: 'Tidak ada deskripsi'; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600 text-xs">
                                        <?php if($e['expires_at']): ?>
                                            <?php 
                                                $expired = strtotime($e['expires_at']) < time();
                                                echo date('d M Y, H:i', strtotime($e['expires_at'])); 
                                            ?>
                                            <?php if($expired): ?>
                                                <div class="text-red-500 font-bold mt-1 uppercase text-[9px]"><i class="fa-solid fa-clock"></i> Kadaluarsa</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic">Tanpa Batas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if($e['is_active']): ?>
                                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-black uppercase tracking-widest"><i class="fa-solid fa-check-circle mr-1"></i> AKTIF</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-slate-100 text-slate-400 rounded-full text-[10px] font-black uppercase tracking-widest">NON-AKTIF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex justify-end gap-2 items-center">
                                        <button onclick="copyToClipboard('<?php echo $share_link; ?>')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Salin Link Survei">
                                            <i class="fa-regular fa-copy text-amber-500"></i> <span>LINK</span>
                                        </button>
                                        <a href="sessions?event_id=<?php echo $e['id']; ?>" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-black text-[11px] transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider" title="Lihat Rekap Data Responden">
                                            <i class="fa-solid fa-chart-line"></i> <span>REKAP</span>
                                        </a>
                                        <button type="button" onclick="editEvent(<?php echo htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8'); ?>)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Edit Detail Event">
                                            <i class="fa-solid fa-pen-to-square"></i> <span>EDIT</span>
                                        </button>
                                        <?php if(!$e['is_active']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                                <button type="submit" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-black text-[11px] transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider">
                                                    <i class="fa-solid fa-bolt"></i> <span>AKTIFKAN</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" onclick="openDeleteModal('<?php echo $e['id']; ?>', '<?php echo addslashes($e['name']); ?>')" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg font-bold text-[11px] transition-all shadow-sm flex items-center gap-1.5" title="Hapus Event">
                                            <i class="fa-solid fa-trash-can text-red-400"></i>
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="text-xs text-slate-500 font-medium">
                            Menampilkan <span class="text-slate-800 font-bold"><?php echo count($events); ?></span> dari <span class="text-slate-800 font-bold"><?php echo $total_count; ?></span> event
                        </div>
                            <?php 
                            $page_params = $_GET;
                            unset($page_params['page']);
                            $query_str = http_build_query($page_params);
                            $query_str = $query_str ? '&' . $query_str : '';
                            ?>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo $query_str; ?>" 
                                       style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; text-decoration: none; transition: all 0.2s; font-size: 10px;">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php 
                                $start_p = max(1, $page - 2);
                                $end_p = min($total_pages, $page + 2);
                                for ($i = $start_p; $i <= $end_p; $i++): 
                                ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $query_str; ?>" 
                                       style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid <?php echo $i === $page ? '#f59e0b' : '#e2e8f0'; ?>; background: <?php echo $i === $page ? '#f59e0b' : 'white'; ?>; color: <?php echo $i === $page ? 'white' : '#475569'; ?>; text-decoration: none; transition: all 0.2s; font-size: 13px; font-weight: <?php echo $i === $page ? '900' : '700'; ?>; box-shadow: <?php echo $i === $page ? '0 10px 15px -3px rgba(245, 158, 11, 0.3)' : 'none'; ?>;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo $query_str; ?>" 
                                       style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; text-decoration: none; transition: all 0.2s; font-size: 10px;">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
    </main>

    <!-- Add/Edit Event Modal -->
    <div id="eventModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:110; justify-content:center; align-items:center; padding:1rem;">
        <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.4); backdrop-filter:blur(4px);" onclick="closeModal()"></div>
        <div style="position:relative; z-index:10; width:100%; max-width:32rem; background:white; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
            <div id="formHeader" class="px-6 py-5 border-b border-slate-100 bg-amber-50 flex justify-between items-center transition-colors">
                <h2 id="formTitle" class="text-lg font-bold text-amber-800 flex items-center gap-2 uppercase tracking-wider">
                    <i class="fa-solid fa-plus-circle"></i> Tambah Event
                </h2>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-8">
                <form id="eventForm" method="POST" class="flex flex-col gap-6">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="event_id" value="">
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Nama Acara / Layanan</label>
                        <input type="text" name="name" id="field_name" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all" placeholder="contoh: GIIAS 2026 / Layanan Booth" required>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Deskripsi Singkat</label>
                        <textarea name="description" id="field_description" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm h-28 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all resize-none" placeholder="Tuliskan tujuan atau catatan survei..."></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Batas Waktu (Opsional)</label>
                        <input type="datetime-local" name="expires_at" id="field_expires_at" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all">
                        <p class="text-[10px] text-slate-400 mt-2 font-medium italic">*Event akan otomatis tidak aktif setelah waktu ini.</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition-all uppercase tracking-widest text-xs border border-slate-200">
                            Batal
                        </button>
                        <button type="submit" id="submitBtn" class="flex-[2] bg-amber-500 hover:bg-amber-600 text-white font-black py-3 rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] uppercase tracking-widest text-xs">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:100; justify-content:center; align-items:center; padding:1rem;">
        <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.4); backdrop-filter:blur(4px);" onclick="closeDeleteModal()"></div>
        <div style="position:relative; z-index:10; width:100%; max-width:28rem; background:white; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                    Apakah Anda yakin ingin menghapus <span id="deleteItemName" class="font-bold text-slate-700"></span>?<br>
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <div style="display:flex; gap:0.75rem;">
                    <button type="button" onclick="closeDeleteModal()" style="flex:1; padding:0.75rem 1rem; background:#f1f5f9; color:#475569; font-weight:700; border-radius:0.75rem; border:1px solid #e2e8f0; cursor:pointer; font-size:14px;">
                        Batal
                    </button>
                    <form method="POST" id="deleteForm" style="flex:1;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
                        <button type="submit" style="width:100%; padding:0.75rem 1rem; background:#ef4444; color:white; font-weight:700; border-radius:0.75rem; border:none; cursor:pointer; font-size:14px; box-shadow:0 4px 14px rgba(239,68,68,0.3);">
                            Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Link berhasil disalin ke clipboard!', 'success');
            });
        }

        function openAddModal() {
            resetForm();
            document.getElementById('eventModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('eventModal').style.display = 'none';
        }

        function editEvent(data) {
            // Switch form mode
            document.getElementById('formAction').value = 'edit';
            document.getElementById('event_id').value = data.id;
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Event';
            document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
            
            const header = document.getElementById('formHeader');
            header.classList.remove('bg-amber-50');
            header.classList.add('bg-blue-50');
            
            const title = document.getElementById('formTitle');
            title.classList.remove('text-amber-800');
            title.classList.add('text-blue-800');

            // Populate fields
            document.getElementById('field_name').value = data.name;
            document.getElementById('field_description').value = data.description || '';
            
            if (data.expires_at) {
                // Formatting for datetime-local (YYYY-MM-DDTHH:MM)
                let date = new Date(data.expires_at);
                let localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                document.getElementById('field_expires_at').value = localDate;
            } else {
                document.getElementById('field_expires_at').value = '';
            }

            // Show Modal
            document.getElementById('eventModal').style.display = 'flex';
        }

        function resetForm() {
            document.getElementById('eventForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('event_id').value = '';
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-calendar-plus"></i> Tambah Event';
            document.getElementById('submitBtn').innerText = 'Simpan Event';
            
            const header = document.getElementById('formHeader');
            header.classList.remove('bg-blue-50');
            header.classList.add('bg-amber-50');
            
            const title = document.getElementById('formTitle');
            title.classList.remove('text-blue-800');
            title.classList.add('text-amber-800');
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteItemId').value = id;
            document.getElementById('deleteItemName').innerText = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>
<?php require_once 'includes/footer.php'; ?>
