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
            $event_id = $_POST['event_id'] ?? null;
            
            if ($type === 'rating') $placeholder = null;

            $stmt = $pdo->prepare("INSERT INTO questions (event_id, question_key, section, question, type, order_num, placeholder) VALUES (?, ?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$event_id, $key, $section, $question, $type, $order_num, $placeholder]);
                $_SESSION['success'] = "Pertanyaan berhasil ditambahkan!";
            } catch (PDOException $e) {
                die($e->getMessage());
            } // Handle duplicate keys silently for simplicity in this demo
            
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $key = $_POST['question_key'] ?? '';
            $section = $_POST['section'] ?? '';
            $question = $_POST['question'] ?? '';
            $type = $_POST['type'] ?? 'rating';
            $order_num = $_POST['order_num'] ?? 0;
            $placeholder = $_POST['placeholder'] ?? '';
            $event_id = $_POST['event_id'] ?? null;

            if ($type === 'rating') $placeholder = null;

            $stmt = $pdo->prepare("UPDATE questions SET event_id = ?, question_key = ?, section = ?, question = ?, type = ?, order_num = ?, placeholder = ? WHERE id = ?");
            try {
                $stmt->execute([$event_id, $key, $section, $question, $type, $order_num, $placeholder, $id]);
                $_SESSION['success'] = "Pertanyaan berhasil diperbarui!";
            } catch (PDOException $e) {
                die($e->getMessage());
            }
            
        } elseif ($_POST['action'] === 'toggle') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE questions SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Status pertanyaan diubah!";
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE questions SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Pertanyaan berhasil dihapus!";
        }
        
            // Redirect to avoid form resubmission and maintain filter context
            $redir_event_id = $_POST['event_id'] ?? '';
            header("Location: questions" . ($redir_event_id ? "?event_id=$redir_event_id" : ""));
            exit;
    }
}

// Fetch all questions with event names
$filter_event_id = $_GET['event_id'] ?? null;
$where_clause = $filter_event_id ? "WHERE q.event_id = ?" : "";
$params = $filter_event_id ? [$filter_event_id] : [];

$stmt = $pdo->prepare("SELECT q.*, e.name as event_name FROM questions q LEFT JOIN events e ON q.event_id = e.id $where_clause ORDER BY q.event_id ASC, q.order_num ASC");
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Fetch events for dropdown
$stmt = $pdo->query("SELECT id, name FROM events ORDER BY name ASC");
$events = $stmt->fetchAll();

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
        
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- List of Questions -->
            <div class="flex-1 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center bg-slate-50/50 gap-4">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-amber-500"></i>
                         Daftar Pertanyaan <?php echo $active_filter_name ? ' - ' . htmlspecialchars($active_filter_name) : ''; ?>
                    </h2>
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
                                                onclick='editQuestion(<?php echo json_encode($q); ?>)'
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
            </div>

            <!-- Add Form -->
            <div id="formCard" class="lg:w-96 bg-white rounded-2xl border border-slate-100 shadow-sm self-start sticky top-24">
                <div id="formHeader" class="px-6 py-5 border-b border-slate-100 bg-amber-50 rounded-t-2xl transition-colors duration-300">
                    <h2 id="formTitle" class="text-lg font-bold text-amber-800 flex items-center gap-2 transition-colors duration-300">
                        <i class="fa-solid fa-plus-circle"></i> Tambah Pertanyaan
                    </h2>
                </div>
                <div class="p-6">
                    <form id="questionForm" method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="question_id" value="">
                        
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase">Gunakan di Event</label>
                                <?php if ($filter_event_id): ?>
                                    <a href="questions" class="text-[10px] font-bold text-amber-600 hover:underline">Tampilkan Semua</a>
                                <?php endif; ?>
                            </div>
                            <select name="event_id" id="field_event_id" onchange="location.href='questions?event_id=' + this.value" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500 bg-white cursor-pointer" required>
                                <option value="">-- Pilih Event & Filter --</option>
                                <?php foreach($events as $ev): ?>
                                    <option value="<?php echo $ev['id']; ?>" <?php echo $filter_event_id == $ev['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ev['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1 italic">*Memilih event akan otomatis memfilter daftar di samping.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Question Key (ID Unik)</label>
                            <input type="text" name="question_key" id="field_key" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm font-mono bg-slate-50 text-slate-500 focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="Otomatis terisi..." readonly required>
                            <p class="text-[10px] text-slate-400 mt-1">ID ini digenerate otomatis dari teks pertanyaan.</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bagian Header (Section)</label>
                            <input type="text" name="section" id="field_section" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="contoh: PELAYANAN STAF" required>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Teks Pertanyaan Lengkap</label>
                            <textarea name="question" id="field_question" oninput="autoGenerateKey(this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm h-24 focus:ring-2 focus:ring-amber-500 focus:outline-none resize-none" placeholder="Isi pertanyaan yang akan tampil di layar..." required></textarea>
                        </div>

                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipe</label>
                                <select name="type" id="field_type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 bg-white" required>
                                    <option value="rating">Emoji Rating</option>
                                    <option value="text">Teks Input / Saran</option>
                                </select>
                            </div>
                            <div class="w-20">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Urutan</label>
                                <input type="number" name="order_num" id="field_order_num" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500" value="5" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Placeholder (Khusus tipe Teks)</label>
                            <input type="text" name="placeholder" id="field_placeholder" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="Kosongkan jika tipe rating...">
                        </div>

                        <button type="submit" id="submitBtn" class="mt-2 w-full bg-amber-500 hover:bg-amber-600 text-white font-black py-3 rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] uppercase tracking-widest">
                            Simpan Pertanyaan
                        </button>
                        <button type="button" id="cancelBtn" onclick="resetForm()" class="hidden w-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-black py-3 rounded-xl transition-all uppercase tracking-widest border border-slate-200">
                            Batal Edit
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 animate-[fadeInScale_0.2s_ease-out]">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Hapus Pertanyaan?</h3>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                    Menghapus pertanyaan <span id="deleteItemName" class="font-bold text-slate-700"></span> akan menyembunyikannya dari kiosk dan laporan.
                </p>
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition-colors">
                        Batal
                    </button>
                    <form method="POST" id="deleteForm" class="flex-1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
                        <input type="hidden" name="event_id" value="<?php echo $filter_event_id; ?>">
                        <button type="submit" class="w-full px-4 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl transition-colors shadow-lg shadow-red-500/30">
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

        function editQuestion(data) {
            // Scroll to form
            document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
            
            // Switch form mode
            document.getElementById('formAction').value = 'edit';
            document.getElementById('question_id').value = data.id;
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Pertanyaan';
            document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
            document.getElementById('cancelBtn').classList.remove('hidden');
            document.getElementById('formHeader').classList.replace('bg-amber-50', 'bg-blue-50');
            document.getElementById('formTitle').classList.replace('text-amber-800', 'text-blue-800');

            // Populate fields
            document.getElementById('field_event_id').value = data.event_id;
            document.getElementById('field_key').value = data.question_key;
            document.getElementById('field_key').readOnly = true; // Still readonly in edit for safety, or false if you want
            document.getElementById('field_section').value = data.section;
            document.getElementById('field_question').value = data.question;
            document.getElementById('field_type').value = data.type;
            document.getElementById('field_order_num').value = data.order_num;
            document.getElementById('field_placeholder').value = data.placeholder || '';
        }

        function resetForm() {
            currentRandomSuffix = ''; // Reset random suffix for new entry
            document.getElementById('questionForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('question_id').value = '';
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle"></i> Tambah Pertanyaan';
            document.getElementById('submitBtn').innerText = 'Simpan Pertanyaan';
            document.getElementById('cancelBtn').classList.add('hidden');
            document.getElementById('formHeader').classList.replace('bg-blue-50', 'bg-amber-50');
            document.getElementById('formTitle').classList.replace('text-blue-800', 'text-amber-800');
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
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
<?php require_once 'includes/footer.php'; ?>
