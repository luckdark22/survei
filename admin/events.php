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
            
            $stmt = $pdo->prepare("INSERT INTO events (name, description, expires_at, is_active) VALUES (?, ?, ?, 0)");
            $stmt->execute([$name, $description, $expires_at]);
            $_SESSION['success'] = "Event '$name' berhasil ditambahkan!";
            
        } elseif ($_POST['action'] === 'activate') {
            $id = $_POST['id'];
            // Deactivate all first
            $pdo->exec("UPDATE events SET is_active = 0");
            // Activate selected
            $stmt = $pdo->prepare("UPDATE events SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Event berhasil diaktifkan!";
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            // Don't allow deleting the only active event if possible, but for simplicity:
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Event berhasil dihapus!";
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $expires_at = $_POST['expires_at'] ?: null;
            
            $stmt = $pdo->prepare("UPDATE events SET name = ?, description = ?, expires_at = ? WHERE id = ?");
            $stmt->execute([$name, $description, $expires_at, $id]);
            $_SESSION['success'] = "Event '$name' berhasil diperbarui!";
        }
        
        header("Location: events");
        exit;
    }
}

// Pagination Logic
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// Count total events
$total_count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// Fetch events with pagination
$stmt = $pdo->prepare("SELECT * FROM events ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
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
            <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center bg-slate-50/50 gap-4">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-amber-500"></i> Daftar Acara / Event
                </h2>
                <button type="button" onclick="openAddModal()" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-xs transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2 uppercase tracking-widest">
                    <i class="fa-solid fa-plus-circle"></i> Tambah Event Baru
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
                                $base_url = "$protocol://$host/Survei/";
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
                                    <td class="px-4 py-4 text-right flex justify-end gap-2 items-center">
                                        <button onclick="copyToClipboard('<?php echo $share_link; ?>')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Salin Link Survei">
                                            <i class="fa-regular fa-copy text-amber-500"></i> <span>LINK</span>
                                        </button>
                                        <a href="sessions?event_id=<?php echo $e['id']; ?>" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-black text-[11px] transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider" title="Lihat Rekap Data Responden">
                                            <i class="fa-solid fa-chart-line"></i> <span>REKAP</span>
                                        </a>
                                        <button type="button" onclick='editEvent(<?php echo json_encode($e); ?>)' class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider" title="Edit Detail Event">
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
                            Menampilkan <span class="text-slate-800"><?php echo count($events); ?></span> dari <span class="text-slate-800"><?php echo $total_count; ?></span> event
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="w-9 h-9 flex items-center justify-center rounded-xl border <?php echo $i === $page ? 'bg-amber-500 border-amber-500 text-white font-black shadow-lg shadow-amber-500/20' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'; ?> transition-all text-xs">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
    </main>

    <!-- Add/Edit Event Modal -->
    <div id="eventModal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 animate-[fadeInScale_0.2s_ease-out] overflow-hidden">
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
    <div id="deleteModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 animate-[fadeInScale_0.2s_ease-out]">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                    Apakah Anda yakin ingin menghapus <span id="deleteItemName" class="font-bold text-slate-700"></span>?<br>
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition-colors">
                        Batal
                    </button>
                    <form method="POST" id="deleteForm" class="flex-1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
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
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Link berhasil disalin ke clipboard!', 'success');
            });
        }

        function editEvent(data) {
            // Scroll to form
            document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
            
            // Switch form mode
            document.getElementById('formAction').value = 'edit';
            document.getElementById('event_id').value = data.id;
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Event';
            document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
            document.getElementById('cancelBtn').classList.remove('hidden');
            document.getElementById('formHeader').classList.replace('bg-amber-50', 'bg-blue-50');
            document.getElementById('formTitle').classList.replace('text-amber-800', 'text-blue-800');

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
        }

        function resetForm() {
            document.getElementById('eventForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('event_id').value = '';
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-calendar-plus"></i> Tambah Event';
            document.getElementById('submitBtn').innerText = 'Simpan Event';
            document.getElementById('cancelBtn').classList.add('hidden');
            document.getElementById('formHeader').classList.replace('bg-blue-50', 'bg-amber-50');
            document.getElementById('formTitle').classList.replace('text-blue-800', 'text-amber-800');
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
