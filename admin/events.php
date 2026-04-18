<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
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
        }
        
        header("Location: events");
        exit;
    }
}

// Fetch all events
$stmt = $pdo->query("SELECT * FROM events ORDER BY created_at DESC");
$events = $stmt->fetchAll();
?>
<?php
$page_title = "Kelola Event";
$page_icon = "fa-calendar-check";
require_once 'includes/header.php';
?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- List of Events -->
            <div class="flex-1 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h2 class="text-xl font-bold text-slate-800">Daftar Acara / Event</h2>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Nama Event</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Deskripsi</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200">Berakhir Pada</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200 text-center">Status</th>
                                <th class="px-4 py-3 font-bold border-b border-slate-200 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach($events as $e): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors <?php echo $e['is_active'] ? 'bg-amber-50/30' : ''; ?>">
                                    <td class="px-4 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($e['name']); ?></td>
                                    <td class="px-4 py-4 text-slate-600 text-xs max-w-[200px] truncate" title="<?php echo htmlspecialchars($e['description']); ?>">
                                        <?php echo htmlspecialchars($e['description']); ?>
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
                                    <td class="px-4 py-4 text-right flex justify-end gap-2">
                                        <a href="questions?event_id=<?php echo $e['id']; ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-bold text-[11px] transition-all shadow-sm flex items-center gap-1.5" title="Kelola Pertanyaan untuk Event Ini">
                                            <i class="fa-solid fa-list-check"></i> <span>PERTANYAAN</span>
                                        </a>
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
                                            <i class="fa-solid fa-trash-can"></i> <span>HAPUS</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Event Form -->
            <div class="lg:w-80 bg-white rounded-2xl border border-slate-100 shadow-sm self-start">
                <div class="px-6 py-5 border-b border-slate-100 bg-amber-50 rounded-t-2xl">
                    <h2 class="text-lg font-bold text-amber-800 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-plus"></i> Tambah Event
                    </h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nama Event</label>
                            <input type="text" name="name" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="contoh: GIIAS 2026" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Batas Waktu (Opsional)</label>
                            <input type="datetime-local" name="expires_at" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <p class="text-[10px] text-slate-400 mt-1 italic leading-tight">Biarkan kosong jika ingin event aktif selamanya.</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Deskripsi Singkat</label>
                            <textarea name="description" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm h-20 focus:ring-2 focus:ring-amber-500 focus:outline-none resize-none" placeholder="Tulis catatan di sini..."></textarea>
                        </div>

                        <button type="submit" class="mt-2 w-full bg-amber-500 hover:bg-amber-600 text-white font-black py-3 rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] uppercase tracking-widest">
                            Simpan Event
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
