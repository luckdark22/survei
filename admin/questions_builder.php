<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/utils.php';
checkAuth();

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) { header("Location: events"); exit; }

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND is_deleted = 0");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) { header("Location: events"); exit; }

if (isStaff() && $event['user_id'] != getUserId()) {
    $_SESSION['error'] = "Akses ditolak.";
    header("Location: events"); exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $maxOrd = $pdo->prepare("SELECT COALESCE(MAX(order_num),0)+1 FROM questions WHERE event_id = ?");
        $maxOrd->execute([$event_id]);
        $key = 'q_' . substr(md5(uniqid()), 0, 8);
        $type = $_POST['type'] ?? 'rating';
        $section = $_POST['section'] ?? '';
        $question = $_POST['question'] ?? '';
        $placeholder = $_POST['placeholder'] ?? '';
        $options = $_POST['options'] ?? '';
        if ($type === 'rating') $placeholder = null;
        $pdo->prepare("INSERT INTO questions (event_id, question_key, section, question, type, order_num, placeholder, options) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$event_id, $key, $section, $question, $type, $maxOrd->fetchColumn(), $placeholder, $options]);
        $_SESSION['success'] = "Pertanyaan berhasil ditambahkan!";

    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE questions SET section=?, question=?, type=?, placeholder=?, options=? WHERE id=? AND event_id=?")
            ->execute([$_POST['section']??'', $_POST['question']??'', $_POST['type']??'rating', $_POST['placeholder']??'', $_POST['options']??'', $_POST['id'], $event_id]);
        $_SESSION['success'] = "Pertanyaan berhasil diperbarui!";

    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM questions WHERE id=? AND event_id=?")->execute([$_POST['id'], $event_id]);
        $_SESSION['success'] = "Pertanyaan berhasil dihapus!";

    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE questions SET is_active = NOT is_active WHERE id=? AND event_id=?")->execute([$_POST['id'], $event_id]);

    } elseif ($action === 'duplicate') {
        $src = $pdo->prepare("SELECT * FROM questions WHERE id=? AND event_id=?");
        $src->execute([$_POST['id'], $event_id]);
        $q = $src->fetch();
        if ($q) {
            $maxOrd = $pdo->prepare("SELECT COALESCE(MAX(order_num),0)+1 FROM questions WHERE event_id=?");
            $maxOrd->execute([$event_id]);
            $pdo->prepare("INSERT INTO questions (event_id, question_key, section, question, type, order_num, placeholder, options) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$event_id, 'q_'.substr(md5(uniqid()),0,8), $q['section'], $q['question'].' (Salinan)', $q['type'], $maxOrd->fetchColumn(), $q['placeholder'], $q['options']]);
            $_SESSION['success'] = "Pertanyaan berhasil diduplikat!";
        }
    }
    header("Location: questions_builder?event_id=$event_id"); exit;
}

$stmt = $pdo->prepare("SELECT * FROM questions WHERE event_id = ? ORDER BY order_num ASC");
$stmt->execute([$event_id]);
$questions = $stmt->fetchAll();

$share_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(str_replace('/admin', '', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']))), '/') . "/?event_id=" . maskId($event['id']);

$editing_id = $_GET['edit'] ?? null;
$is_adding = isset($_GET['add']);

$type_icons = ['rating'=>'fa-face-smile text-amber-500','text'=>'fa-align-left text-blue-500','number'=>'fa-hashtag text-emerald-500','email'=>'fa-envelope text-violet-500','date'=>'fa-calendar-day text-rose-500','select'=>'fa-list text-cyan-500','checkbox'=>'fa-square-check text-teal-500','combobox'=>'fa-magnifying-glass-list text-orange-500'];
$type_labels = ['rating'=>'Emoji Rating','text'=>'Teks / Saran','number'=>'Input Angka','email'=>'Input Email','date'=>'Input Tanggal','select'=>'Pilihan Dropdown','checkbox'=>'Pilihan Banyak','combobox'=>'Combo Box (Search)'];

$page_title = "Form Builder";
$page_icon = "fa-wand-magic-sparkles";
require_once 'includes/header.php';
?>

<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Event Header -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 h-3"></div>
        <div class="px-6 py-5">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <a href="events" class="text-slate-400 hover:text-amber-600 text-xs font-bold uppercase tracking-widest flex items-center gap-1.5 mb-2 transition-colors">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Event
                    </a>
                    <h1 class="text-xl md:text-2xl font-black text-slate-800"><?php echo htmlspecialchars($event['name']); ?></h1>
                    <p class="text-slate-400 text-xs mt-1 font-medium"><?php echo count($questions); ?> Pertanyaan &bull; <?php echo $event['is_active'] ? '🟢 Aktif' : '⚪ Non-aktif'; ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="<?php echo $share_link; ?>&preview=1" target="_blank" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                        <i class="fa-solid fa-eye"></i> Pratinjau
                    </a>
                    <button onclick="copyToClipboard('<?php echo $share_link; ?>')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                        <i class="fa-regular fa-copy"></i> Salin Link
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Cards -->
    <div id="questionsList" class="flex flex-col gap-5 mb-6">
        <?php foreach($questions as $q):
            $icon = $type_icons[$q['type']] ?? 'fa-circle-question text-slate-400';
            $label = $type_labels[$q['type']] ?? $q['type'];
            $opts = $q['options'] ? array_filter(array_map('trim', explode(',', $q['options']))) : [];
            $is_editing = ($editing_id == $q['id']);
        ?>
        <div class="question-card bg-white rounded-2xl border-2 <?php echo $is_editing ? 'border-amber-500 shadow-xl ring-4 ring-amber-500/10' : ($q['is_active'] ? 'border-slate-100 hover:border-amber-200' : 'border-dashed border-slate-200 opacity-60'); ?> shadow-sm transition-all duration-300 group" data-id="<?php echo $q['id']; ?>">

            <?php if($is_editing): ?>
            <!-- INLINE EDIT FORM -->
            <form method="POST" class="p-6 md:p-8">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">

                <div class="flex flex-col gap-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Pertanyaan</label>
                        <input type="text" name="question" value="<?php echo htmlspecialchars($q['question']); ?>" 
                            class="w-full text-xl font-bold text-slate-800 border-b-2 border-slate-100 focus:border-amber-500 focus:outline-none pb-3 bg-slate-50/50 px-4 rounded-t-xl placeholder:text-slate-300 transition-all" 
                            placeholder="Tulis pertanyaan di sini..." required autofocus>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Bagian (Section)</label>
                            <input type="text" name="section" value="<?php echo htmlspecialchars($q['section']); ?>" 
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Tipe Input</label>
                            <div class="relative">
                                <select name="type" onchange="toggleInlineOpts(this)" class="no-select2 appearance-none w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none cursor-pointer transition-all pr-10">
                                    <?php foreach($type_labels as $val => $lbl): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $q['type']==$val?'selected':''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Placeholder</label>
                            <input type="text" name="placeholder" value="<?php echo htmlspecialchars($q['placeholder'] ?? ''); ?>" 
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all" placeholder="Contoh: Tulis masukan Anda...">
                        </div>
                    </div>

                    <div class="inline-opts-wrapper" style="<?php echo in_array($q['type'],['select','checkbox','combobox'])?'':'display:none;'; ?>">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Opsi Pilihan (Pisahkan dengan koma)</label>
                        <textarea name="options" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all h-24 resize-none" placeholder="Opsi 1, Opsi 2, Opsi 3"><?php echo htmlspecialchars($q['options'] ?? ''); ?></textarea>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button type="button" onclick="confirmDelete('<?php echo $q['id']; ?>','<?php echo addslashes($q['question']); ?>')" class="text-red-400 hover:text-red-600 text-xs font-bold flex items-center gap-2 transition-colors">
                            <i class="fa-solid fa-trash-can"></i> Hapus Pertanyaan
                        </button>
                        <div class="flex gap-3">
                            <a href="questions_builder?event_id=<?php echo $event_id; ?>" class="px-6 py-2.5 text-slate-500 hover:bg-slate-100 rounded-xl text-xs font-black uppercase tracking-widest transition-all">Batal</a>
                            <button type="submit" class="px-8 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-amber-500/20">Simpan Perubahan</button>
                        </div>
                    </div>
                </div>
            </form>

            <?php else: ?>
            <!-- READ-ONLY CARD -->
            <div class="flex items-stretch min-h-[100px]">
                <div class="drag-handle flex items-center justify-center w-16 cursor-grab active:cursor-grabbing text-slate-200 hover:text-amber-500 transition-colors flex-shrink-0 bg-slate-50/50 rounded-l-2xl border-r border-slate-100 group-hover:bg-amber-50/30 group-hover:text-amber-300 px-2">
                    <i class="fa-solid fa-grip-vertical text-lg"></i>
                </div>
                <div class="flex-1 p-5 md:p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                        <a href="questions_builder?event_id=<?php echo $event_id; ?>&edit=<?php echo $q['id']; ?>" class="flex-1 min-w-0 block">
                            <div class="flex items-center gap-3 mb-3 flex-wrap">
                                <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-black uppercase tracking-[0.1em]"><?php echo htmlspecialchars($q['section']); ?></span>
                                <span class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest <?php echo $q['is_active'] ? 'text-emerald-500' : 'text-slate-300'; ?>">
                                    <i class="fa-solid fa-circle text-[8px]"></i> <?php echo $q['is_active'] ? 'Aktif' : 'Non-aktif'; ?>
                                </span>
                            </div>
                            <h3 class="text-base md:text-lg font-bold text-slate-800 mb-2 leading-tight group-hover:text-amber-600 transition-colors"><?php echo htmlspecialchars($q['question']); ?></h3>
                            <div class="flex items-center gap-4 text-xs text-slate-400 font-medium">
                                <span class="flex items-center gap-2 px-2.5 py-1 bg-slate-50 rounded-md">
                                    <i class="fa-solid <?php echo $icon; ?> text-sm"></i> 
                                    <?php echo $label; ?>
                                </span>
                                <?php if($q['placeholder']): ?>
                                    <span class="italic text-slate-300">"<?php echo htmlspecialchars($q['placeholder']); ?>"</span>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($opts)): ?>
                            <div class="flex flex-wrap gap-1.5 mt-4">
                                <?php foreach(array_slice($opts,0,6) as $o): ?>
                                    <span class="px-3 py-1 bg-amber-50 text-amber-700 rounded-lg text-[11px] font-bold border border-amber-100"><?php echo htmlspecialchars($o); ?></span>
                                <?php endforeach; ?>
                                <?php if(count($opts)>6): ?><span class="px-3 py-1 bg-slate-50 text-slate-400 rounded-lg text-[11px] font-bold border border-slate-100">+<?php echo count($opts)-6; ?></span><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </a>

                        <!-- Actions (visible on hover) -->
                        <div class="flex items-center gap-2 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-all duration-300 translate-x-2 group-hover:translate-x-0">
                            <a href="questions_builder?event_id=<?php echo $event_id; ?>&edit=<?php echo $q['id']; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-amber-500 text-slate-400 hover:text-white transition-all shadow-sm" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="duplicate">
                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <button type="submit" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-blue-500 text-slate-400 hover:text-white transition-all shadow-sm" title="Duplikat">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <button type="submit" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-emerald-500 text-slate-400 hover:text-white transition-all shadow-sm" title="<?php echo $q['is_active'] ? 'Non-aktifkan' : 'Aktifkan'; ?>">
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </form>
                            <button type="button" onclick="confirmDelete('<?php echo $q['id']; ?>','<?php echo addslashes($q['question']); ?>')" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-red-500 text-slate-400 hover:text-white transition-all shadow-sm" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if(empty($questions) && !$is_adding): ?>
    <div class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200 mb-6">
        <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-clipboard-question text-3xl text-amber-400"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-700 mb-2">Belum Ada Pertanyaan</h3>
        <p class="text-sm text-slate-400 max-w-xs mx-auto">Mulai bangun survei Anda dengan menambahkan pertanyaan pertama menggunakan tombol di bawah.</p>
    </div>
    <?php endif; ?>

    <?php if($is_adding): ?>
    <!-- INLINE ADD FORM -->
    <div class="bg-white rounded-3xl border-2 border-amber-500 shadow-2xl ring-4 ring-amber-500/5 mb-8 overflow-hidden">
        <div class="bg-amber-500 h-2 w-full"></div>
        <form method="POST" class="p-6 md:p-8">
            <input type="hidden" name="action" value="add">
            <div class="flex flex-col gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Pertanyaan Baru</label>
                    <input type="text" name="question" 
                        class="w-full text-xl font-bold text-slate-800 border-b-2 border-slate-100 focus:border-amber-500 focus:outline-none pb-3 bg-slate-50/50 px-4 rounded-t-xl placeholder:text-slate-300 transition-all" 
                        placeholder="Apa yang ingin Anda tanyakan?" required autofocus>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Bagian (Section)</label>
                        <input type="text" name="section" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all" placeholder="Contoh: UMUM" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Tipe Input</label>
                        <div class="relative">
                            <select name="type" onchange="toggleInlineOpts(this)" class="no-select2 appearance-none w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none cursor-pointer transition-all pr-10">
                                <?php foreach($type_labels as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Placeholder</label>
                        <input type="text" name="placeholder" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all" placeholder="Opsional...">
                    </div>
                </div>
                <div class="inline-opts-wrapper" style="display:none;">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Opsi Pilihan (Pisahkan dengan koma)</label>
                    <textarea name="options" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all h-24 resize-none" placeholder="Opsi 1, Opsi 2, Opsi 3"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="questions_builder?event_id=<?php echo $event_id; ?>" class="px-6 py-3 text-slate-500 hover:bg-slate-100 rounded-xl text-xs font-black uppercase tracking-widest transition-all">Batal</a>
                    <button type="submit" class="px-10 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-xl shadow-amber-500/20">Tambah Pertanyaan</button>
                </div>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- Add Button -->
    <a href="questions_builder?event_id=<?php echo $event_id; ?>&add=1" class="w-full py-5 border-2 border-dashed border-slate-200 hover:border-amber-400 rounded-2xl text-slate-400 hover:text-amber-600 font-black text-xs uppercase tracking-[0.2em] transition-all duration-300 flex items-center justify-center gap-3 hover:bg-amber-50/50 block group">
        <i class="fa-solid fa-plus-circle text-lg group-hover:scale-110 transition-transform"></i> Tambah Pertanyaan Baru
    </a>
    <?php endif; ?>
</main>

<!-- Delete Confirmation (minimal inline) -->
<div id="delOverlay" style="display:none; position:fixed; inset:0; z-index:120; justify-content:center; align-items:center; padding:1rem;">
    <div style="position:absolute; inset:0; background:rgba(15,23,42,0.4); backdrop-filter:blur(4px);" onclick="closeDel()"></div>
    <div style="position:relative; z-index:10; width:100%; max-width:24rem; background:white; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div class="p-6 text-center">
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-xl"><i class="fa-solid fa-trash-can"></i></div>
            <h3 class="text-base font-bold text-slate-800 mb-1">Hapus Pertanyaan?</h3>
            <p class="text-slate-500 text-xs mb-5" id="delText"></p>
            <div class="flex gap-2">
                <button onclick="closeDel()" class="flex-1 py-2 bg-slate-100 text-slate-600 font-bold rounded-lg text-sm">Batal</button>
                <form method="POST" id="delForm" class="flex-1"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId">
                    <button type="submit" class="w-full py-2 bg-red-500 text-white font-bold rounded-lg text-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
    const list = document.getElementById('questionsList');
    if (list && list.children.length > 1) {
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 200,
            ghostClass: 'opacity-30',
            onEnd: function() {
                const order = [...list.querySelectorAll('.question-card')].map(c => c.dataset.id);
                $.ajax({
                    url: 'api/reorder_questions.php',
                    method: 'POST',
                    data: {
                        event_id: '<?php echo $event_id; ?>',
                        order: order
                    },
                    success: function(d) {
                        if (d.success) {
                            if (typeof showToast === 'function') showToast('Urutan diperbarui!', 'success');
                        } else {
                            if (typeof showToast === 'function') showToast('Gagal: ' + d.message, 'error');
                        }
                    },
                    error: function() {
                        if (typeof showToast === 'function') showToast('Gagal memperbarui urutan', 'error');
                    }
                });
            }
        });
    }

    function toggleInlineOpts(sel) {
        const w = sel.closest('form').querySelector('.inline-opts-wrapper');
        w.style.display = (sel.value === 'select' || sel.value === 'checkbox' || sel.value === 'combobox') ? '' : 'none';
    }

    function confirmDelete(id, name) {
        document.getElementById('delId').value = id;
        document.getElementById('delText').innerText = '"' + name + '" akan dihapus permanen.';
        document.getElementById('delOverlay').style.display = 'flex';
    }
    function closeDel() { document.getElementById('delOverlay').style.display = 'none'; }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => { if(typeof showToast==='function') showToast('Link disalin!','success'); });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
