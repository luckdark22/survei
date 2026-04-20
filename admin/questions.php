<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $key = $_POST['question_key'] ?? '';
            $section = $_POST['section'] ?? '';
            $question = $_POST['question'] ?? '';
            $type = $_POST['type'] ?? 'rating';
            $order_num = $_POST['order_num'] ?? 0;
            $placeholder = $_POST['placeholder'] ?? '';
            $event_id = $_POST['event_id'] ?: null;

            // RBAC: Staff cannot create global questions
            if (isStaff() && !$event_id) {
                $_SESSION['error'] = "Staff hanya diperbolehkan membuat pertanyaan untuk event.";
                header("Location: questions"); exit;
            }

            // RBAC: Staff must own the event
            if (isStaff() && $event_id) {
                $check = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
                $check->execute([$event_id, getUserId()]);
                if (!$check->fetch()) {
                    $_SESSION['error'] = "Anda tidak memiliki akses ke event ini.";
                    header("Location: questions"); exit;
                }
            }
            
            if ($type === 'rating') $placeholder = null;

            $stmt = $pdo->prepare("INSERT INTO questions (event_id, question_key, section, question, type, order_num, placeholder) VALUES (?, ?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$event_id, $key, $section, $question, $type, $order_num, $placeholder]);
                $_SESSION['success'] = "Pertanyaan berhasil ditambahkan!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Gagal menambah: " . $e->getMessage();
            }
            
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $key = $_POST['question_key'] ?? '';
            $section = $_POST['section'] ?? '';
            $question = $_POST['question'] ?? '';
            $type = $_POST['type'] ?? 'rating';
            $order_num = $_POST['order_num'] ?? 0;
            $placeholder = $_POST['placeholder'] ?? '';
            $event_id = $_POST['event_id'] ?: null;

            // Security Check: Existing question ownership
            $stmt_old = $pdo->prepare("SELECT event_id FROM questions WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_q = $stmt_old->fetch();

            if (isStaff()) {
                // Cannot edit global question
                if (!$old_q['event_id']) {
                    $_SESSION['error'] = "Staff tidak diizinkan mengubah pertanyaan publik.";
                    header("Location: questions"); exit;
                }
                // Must own the original event
                $check_own = $pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
                $check_own->execute([$old_q['event_id'], getUserId()]);
                if (!$check_own->fetch()) {
                    $_SESSION['error'] = "Akses ditolak.";
                    header("Location: questions"); exit;
                }
            }

            if ($type === 'rating') $placeholder = null;

            $stmt = $pdo->prepare("UPDATE questions SET event_id = ?, question_key = ?, section = ?, question = ?, type = ?, order_num = ?, placeholder = ? WHERE id = ?");
            try {
                $stmt->execute([$event_id, $key, $section, $question, $type, $order_num, $placeholder, $id]);
                $_SESSION['success'] = "Pertanyaan berhasil diperbarui!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Gagal simpan: " . $e->getMessage();
            }
            
        } elseif ($_POST['action'] === 'toggle' || $_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            // Security Check
            if (isStaff()) {
                $stmt_check = $pdo->prepare("SELECT q.id FROM questions q JOIN events e ON q.event_id = e.id WHERE q.id = ? AND e.user_id = ?");
                $stmt_check->execute([$id, getUserId()]);
                if (!$stmt_check->fetch()) {
                    $_SESSION['error'] = "Akses ditolak.";
                    header("Location: questions"); exit;
                }
            }

            if ($_POST['action'] === 'toggle') {
                $stmt = $pdo->prepare("UPDATE questions SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Status pertanyaan diubah!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Pertanyaan berhasil dihapus!";
            }
        }
        
            // Redirect to avoid form resubmission and maintain filter context
            $redir_event_id = $_POST['event_id'] ?? '';
            header("Location: questions" . ($redir_event_id ? "?event_id=$redir_event_id" : ""));
            exit;
    }
}

// Base filtering
$where_clauses = [];
$params = [];

if (isStaff()) {
    $where_clauses[] = "q.event_id IN (SELECT id FROM events WHERE user_id = ?)";
    $params[] = getUserId();
}

$filter_event_id = $_GET['event_id'] ?? null;
if ($filter_event_id) {
    if (isStaff()) {
        $where_clauses[] = "q.event_id = ?";
        $params[] = $filter_event_id;
    } else {
        $where_clauses[] = "q.event_id = ?";
        $params[] = $filter_event_id;
    }
} elseif (isStaff()) {
    // If staff but no specific filter, results are already scoped by the first where_clause
} else {
    // Admin with no filter: show ALL including global
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Pagination Logic
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Count total questions with filters
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions q $where_sql");
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// Fetch questions with events and pagination
$stmt = $pdo->prepare("SELECT q.*, e.name as event_name FROM questions q LEFT JOIN events e ON q.event_id = e.id $where_sql ORDER BY q.event_id ASC, q.order_num ASC LIMIT ? OFFSET ?");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$questions = $stmt->fetchAll();

// Fetch events for dropdown (filtered for staff)
$sql_ev = "SELECT id, name FROM events WHERE is_deleted = 0";
if (isStaff()) $sql_ev .= " AND user_id = " . (int)getUserId();
$sql_ev .= " ORDER BY name ASC";
$events = $pdo->query($sql_ev)->fetchAll();

// Fetch current filter event name
$active_filter_name = "";
if ($filter_event_id) {
    foreach($events as $ev) {
        if ($ev['id'] == $filter_event_id) {
            $active_filter_name = $ev['name'];
            break;
        }
    }
}
?>
<?php
$page_title = "Kelola Pertanyaan";
$page_icon = "fa-list-check";
require_once 'includes/header.php';
?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- List of Questions -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-10">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center bg-slate-50/50 gap-4">
                <div class="flex flex-col sm:flex-row items-center gap-3 w-full">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2 mr-auto">
                        <i class="fa-solid fa-list-check text-amber-500"></i>
                        Daftar Pertanyaan
                    </h2>
                    
                    <form method="GET" class="w-full sm:w-auto mt-2 sm:mt-0">
                        <select name="event_id" onchange="this.form.submit()" class="w-full sm:w-auto px-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white text-slate-700 font-bold shadow-sm cursor-pointer">
                            <?php if (isAdmin()): ?>
                                <option value="">Semua Event (Global)</option>
                            <?php else: ?>
                                <option value="">Semua Event Saya</option>
                            <?php endif; ?>
                            <?php foreach($events as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>" <?php echo $filter_event_id == $ev['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ev['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <button type="button" onclick="openAddModal()" class="w-full sm:w-auto px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-xs transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] flex justify-center items-center gap-2 uppercase tracking-widest mt-2 sm:mt-0">
                        <i class="fa-solid fa-plus-circle text-sm"></i> Tambah Pertanyaan
                    </button>
                </div>
            </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                                <th class="px-4 py-3 font-bold border-b border-slate-200 rounded-tl-lg">Event</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Urutan</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Tipe</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Bagian (Section)</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Pertanyaan</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Status</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200 text-right rounded-tr-lg">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach($questions as $q): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-4 truncate max-w-[150px]">
                                        <span class="text-xs font-bold text-slate-500 uppercase"><?php echo htmlspecialchars($q['event_name'] ?: 'Tanpa Event'); ?></span>
                                    </td>
                                    <td class="px-4 py-4 font-bold text-amber-600 text-center"><?php echo $q['order_num']; ?></td>
                                    <td class="px-4 py-4">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-md <?php echo $q['type'] == 'rating' ? 'bg-indigo-50 text-indigo-600' : 'bg-fuchsia-50 text-fuchsia-600'; ?>">
                                            <?php echo strtoupper($q['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 font-semibold text-slate-700"><?php echo htmlspecialchars($q['section']); ?></td>
                                    <td class="px-4 py-4 text-slate-600 max-w-[250px] truncate" title="<?php echo htmlspecialchars($q['question']); ?>"><?php echo htmlspecialchars($q['question']); ?></td>
                                    <td class="px-4 py-4">
                                        <?php if($q['is_active']): ?>
                                            <span class="text-emerald-500 font-bold text-xs"><i class="fa-solid fa-circle text-[10px]"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="text-slate-400 font-bold text-xs"><i class="fa-regular fa-circle text-[10px]"></i> Non-aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right flex justify-end gap-2">
                                        <button type="button" 
                                                onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q), ENT_QUOTES, 'UTF-8'); ?>)"
                                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-black text-[11px] transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider" title="Edit Pertanyaan">
                                            <i class="fa-solid fa-pen-to-square"></i> <span>EDIT</span>
                                        </button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                            <input type="hidden" name="event_id" value="<?php echo $filter_event_id; ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-500 border border-slate-200 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Toggle Aktif/Non-aktif">
                                                <i class="fa-solid fa-power-off text-amber-500"></i> <span>STATUS</span>
                                            </button>
                                        </form>
                                        <button type="button" onclick="openDeleteModal('<?php echo $q['id']; ?>', '<?php echo addslashes($q['question_key']); ?>')" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Hapus Pertanyaan">
                                            <i class="fa-solid fa-trash-can"></i> <span>HAPUS</span>
                                        </button>
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
                            Menampilkan <span class="text-slate-800"><?php echo count($questions); ?></span> dari <span class="text-slate-800"><?php echo $total_count; ?></span> pertanyaan
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $filter_event_id ? '&event_id='.$filter_event_id : ''; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filter_event_id ? '&event_id='.$filter_event_id : ''; ?>" 
                                   class="w-9 h-9 flex items-center justify-center rounded-xl border <?php echo $i === $page ? 'bg-amber-500 border-amber-500 text-white font-black shadow-lg shadow-amber-500/20' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'; ?> transition-all text-xs">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $filter_event_id ? '&event_id='.$filter_event_id : ''; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
    </main>

    <!-- Add/Edit Question Modal -->
    <div id="questionModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:110; justify-content:center; align-items:center; padding:1rem;">
        <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.4); backdrop-filter:blur(4px);" onclick="closeModal()"></div>
        <div style="position:relative; z-index:10; width:100%; max-width:36rem; background:white; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
            <div id="formHeader" class="px-6 py-5 border-b border-slate-100 bg-amber-50 flex justify-between items-center transition-colors">
                <h2 id="formTitle" class="text-lg font-bold text-amber-800 flex items-center gap-2 uppercase tracking-wider">
                    <i class="fa-solid fa-plus-circle"></i> Tambah Pertanyaan
                </h2>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-8">
                <form id="questionForm" method="POST" class="flex flex-col gap-5">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="question_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Gunakan di Event</label>
                            </div>
                            <select name="event_id" id="field_event_id" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 bg-white cursor-pointer" required>
                                <option value="">-- Pilih Event --</option>
                                <?php foreach($events as $ev): ?>
                                    <option value="<?php echo $ev['id']; ?>" <?php echo $filter_event_id == $ev['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ev['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ID Unik (Key)</label>
                            <input type="text" name="question_key" id="field_key" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-mono bg-slate-50 text-slate-500 focus:outline-none" placeholder="Otomatis..." readonly required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Bagian Header (Section)</label>
                        <input type="text" name="section" id="field_section" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-bold text-slate-700" placeholder="contoh: PELAYANAN STAF / FASILITAS" required>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Teks Pertanyaan</label>
                        <textarea name="question" id="field_question" oninput="autoGenerateKey(this.value)" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm h-24 focus:ring-2 focus:ring-amber-500 focus:outline-none resize-none font-bold text-slate-700" placeholder="Apa yang ingin Anda tanyakan?" required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tipe Input</label>
                            <select name="type" id="field_type" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 bg-white" required>
                                <option value="rating">Emoji Rating</option>
                                <option value="text">Teks / Saran</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">No. Urut</label>
                            <input type="number" name="order_num" id="field_order_num" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 font-bold text-slate-700" value="5" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Placeholder (Khusus Teks)</label>
                        <input type="text" name="placeholder" id="field_placeholder" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium text-slate-600" placeholder="Tulis masukan Anda di sini...">
                    </div>

                    <div class="flex gap-3 mt-2">
                        <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition-all uppercase tracking-widest text-xs border border-slate-200">
                            Batal
                        </button>
                        <button type="submit" id="submitBtn" class="flex-[2] bg-amber-500 hover:bg-amber-600 text-white font-black py-3 rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] uppercase tracking-widest text-xs">
                            Simpan Pertanyaan
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
                <h3 class="text-xl font-bold text-slate-800 mb-2">Hapus Pertanyaan?</h3>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                    Menghapus pertanyaan <span id="deleteItemName" class="font-bold text-slate-700"></span> akan menyembunyikannya dari kiosk dan laporan.
                </p>
                <div style="display:flex; gap:0.75rem;">
                    <button type="button" onclick="closeDeleteModal()" style="flex:1; padding:0.75rem 1rem; background:#f1f5f9; color:#475569; font-weight:700; border-radius:0.75rem; border:1px solid #e2e8f0; cursor:pointer; font-size:14px;">
                        Batal
                    </button>
                    <form method="POST" id="deleteForm" style="flex:1;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
                        <input type="hidden" name="event_id" value="<?php echo $filter_event_id; ?>">
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
        let currentRandomSuffix = '';

        function generateRandomSuffix() {
            return Math.random().toString(36).substring(2, 6);
        }

        function autoGenerateKey(text) {
            // Only auto-generate if we are in ADD mode (readonly is active)
            const keyField = document.getElementById('field_key');
            if (!keyField.readOnly) return; 

            if (!currentRandomSuffix && text.trim().length > 0) {
                currentRandomSuffix = generateRandomSuffix();
            }

            const slug = text.toLowerCase()
                .replace(/[^\w\s-]/g, '') // Remove non-word characters
                .replace(/\s+/g, '_')      // Replace spaces with underscores
                .replace(/^-+|-+$/g, '')   // Trim leading/trailing underscores
                .substring(0, 35);         // Limit length to leave room for suffix
            
            keyField.value = slug ? 'q_' + slug + '_' + currentRandomSuffix : '';
        }

        function openAddModal() {
            resetForm();
            document.getElementById('questionModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('questionModal').style.display = 'none';
        }

        function editQuestion(data) {
            // Switch form mode
            document.getElementById('formAction').value = 'edit';
            document.getElementById('question_id').value = data.id;
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Pertanyaan';
            document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
            
            const header = document.getElementById('formHeader');
            header.classList.remove('bg-amber-50');
            header.classList.add('bg-blue-50');
            
            const title = document.getElementById('formTitle');
            title.classList.remove('text-amber-800');
            title.classList.add('text-blue-800');

            // Populate fields
            document.getElementById('field_event_id').value = data.event_id;
            document.getElementById('field_key').value = data.question_key;
            document.getElementById('field_key').readOnly = true; 
            document.getElementById('field_section').value = data.section;
            document.getElementById('field_question').value = data.question;
            document.getElementById('field_type').value = data.type;
            document.getElementById('field_order_num').value = data.order_num;
            document.getElementById('field_placeholder').value = data.placeholder || '';

            // Show modal
            document.getElementById('questionModal').style.display = 'flex';
        }

        function resetForm() {
            currentRandomSuffix = ''; // Reset random suffix for new entry
            document.getElementById('questionForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('question_id').value = '';
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle"></i> Tambah Pertanyaan';
            document.getElementById('submitBtn').innerText = 'Simpan Pertanyaan';
            
            const header = document.getElementById('formHeader');
            header.classList.remove('bg-blue-50');
            header.classList.add('bg-amber-50');
            
            const title = document.getElementById('formTitle');
            title.classList.remove('text-blue-800');
            title.classList.add('text-amber-800');
            
            document.getElementById('field_key').readOnly = true; 
            
            // Keep current event filter if any
            const currentEventId = "<?php echo $filter_event_id; ?>";
            if (currentEventId) {
                document.getElementById('field_event_id').value = currentEventId;
            }
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
