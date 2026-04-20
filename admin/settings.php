<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

// ONLY ADMIN can access global settings
if (!isAdmin()) {
    $_SESSION['error'] = "Hanya Admin yang dapat merubah pengaturan global.";
    header("Location: ./");
    exit;
}

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_running_text') {
            $text = $_POST['running_text'] ?? '';
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'running_text'");
            $stmt->execute([$text]);
            $_SESSION['success'] = 'Running text berhasil diperbarui!';
            header("Location: settings");
            exit;
        } elseif ($_POST['action'] === 'update_instansi') {
            $name = $_POST['instansi_name'] ?? '';
            // Fix: Added missing VALUES (?, ?) clause
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute(['instansi_name', $name]);
            $_SESSION['success'] = 'Nama instansi berhasil diperbarui!';
            header("Location: settings");
            exit;
        }
    }
}

// Fetch current settings
$stmt = $pdo->prepare("SELECT * FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
$running_text = $settings['running_text'] ?? '';
?>
<?php
$page_title = "Pengaturan";
$page_icon = "fa-gear";
require_once 'includes/header.php';
?>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">



        <!-- Organization Settings -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 bg-amber-50 rounded-t-2xl">
                <h2 class="text-lg font-bold text-amber-800 flex items-center gap-2">
                    <i class="fa-solid fa-building"></i> Informasi Instansi
                </h2>
                <p class="text-xs text-amber-600 mt-1 font-medium">Nama ini akan muncul di header dan footer seluruh aplikasi.</p>
            </div>
            <div class="p-6">
                <form method="POST" class="flex flex-col md:flex-row gap-4 md:items-end">
                    <input type="hidden" name="action" value="update_instansi">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nama Instansi / Perusahaan</label>
                        <input type="text" name="instansi_name" value="<?php echo htmlspecialchars($settings['instansi_name'] ?? 'Direktorat Inovasi & Layanan'); ?>" class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-amber-400/20 focus:border-amber-400 focus:outline-none transition-all" placeholder="Masukkan nama instansi...">
                    </div>
                    <button type="submit" class="w-full md:w-auto px-6 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fa-solid fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>

        <!-- Running Text Settings -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 bg-amber-50 rounded-t-2xl">
                <h2 class="text-lg font-bold text-amber-800 flex items-center gap-2">
                    <i class="fa-solid fa-text-width"></i> Running Text (Teks Berjalan)
                </h2>
                <p class="text-xs text-amber-600 mt-1 font-medium">Teks ini akan ditampilkan secara berulang di bagian bawah header pada layar Kiosk.</p>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="update_running_text">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Isi Teks Berjalan</label>
                        <textarea id="runningTextInput" name="running_text" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-amber-400/20 focus:border-amber-400 focus:outline-none resize-none h-32 transition-all" placeholder="Ketik teks berjalan di sini..."><?php echo htmlspecialchars($running_text); ?></textarea>
                    </div>

                    <!-- Emoji Picker -->
                    <div class="mb-5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Sisipkan Emoji</label>
                            <button type="button" id="toggleEmojiBtn" class="text-xs font-bold text-amber-600 hover:text-amber-800 transition-colors flex items-center gap-1">
                                <i class="fa-solid fa-face-smile"></i> Tampilkan / Sembunyikan
                            </button>
                        </div>
                        <div id="emojiPanel" class="bg-slate-50 border border-slate-200 rounded-xl p-4 hidden">
                            <!-- Category Tabs -->
                            <div class="flex gap-2 mb-3 flex-wrap" id="emojiTabs">
                                <button type="button" class="emoji-tab active px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 text-white transition-colors" data-cat="senyum">😊 Senyuman</button>
                                <button type="button" class="emoji-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 text-slate-600 hover:bg-amber-100 transition-colors" data-cat="tangan">👋 Tangan</button>
                                <button type="button" class="emoji-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 text-slate-600 hover:bg-amber-100 transition-colors" data-cat="hati">❤️ Hati</button>
                                <button type="button" class="emoji-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 text-slate-600 hover:bg-amber-100 transition-colors" data-cat="gedung">🏢 Gedung</button>
                                <button type="button" class="emoji-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 text-slate-600 hover:bg-amber-100 transition-colors" data-cat="simbol">⭐ Simbol</button>
                            </div>
                            <!-- Emoji Grid -->
                            <div id="emojiGrid" class="flex flex-wrap gap-1.5"></div>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Pratinjau Langsung</label>
                        <div class="bg-amber-600 text-white text-sm font-semibold py-2 rounded-xl overflow-hidden shadow-inner">
                            <div id="previewMarquee" class="whitespace-nowrap inline-block" style="animation: marquee 25s linear infinite;">
                                <?php echo htmlspecialchars($running_text); ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center md:justify-end">
                        <button type="submit" class="w-full md:w-auto px-8 py-3 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] hover:shadow-[0_8px_20px_rgba(245,158,11,0.4)] flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <style>
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
    </style>

    <script>
        const textInput = document.getElementById('runningTextInput');
        const previewEl = document.getElementById('previewMarquee');
        const emojiPanel = document.getElementById('emojiPanel');
        const emojiGrid = document.getElementById('emojiGrid');
        const toggleBtn = document.getElementById('toggleEmojiBtn');
        const tabs = document.querySelectorAll('.emoji-tab');

        const emojiSets = {
            senyum: ['😊','😃','😄','😁','😆','🥹','😅','🤣','😂','🙂','😉','😍','🥰','😘','😗','😙','😚','😋','😛','🤩','🥳','😎','🤗','🤔','😌','😇','🙏','😺'],
            tangan: ['👋','🤚','✋','🖐️','👌','🤌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝️','👍','👎','✊','👏','🤝','🫶','🙌','👐','🤲','💪'],
            hati: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💕','💞','💓','💗','💖','💘','💝','❣️','💟','♥️','🩷','🩵','🩶'],
            gedung: ['🏢','🏛️','🏥','🏦','🏪','🏫','🏬','🏭','🏗️','🏘️','📮','📬','📭','📪','🚪','🛎️','📋','📊','📈','📉','🗂️','📁','📂','🗃️'],
            simbol: ['⭐','🌟','✨','💫','🔥','💯','✅','❌','⚠️','ℹ️','🔔','📢','📣','🎯','🏆','🎖️','🥇','🥈','🥉','🎉','🎊','🎈','🎁','💡','🔑']
        };

        // Live preview
        textInput.addEventListener('input', () => {
            previewEl.textContent = textInput.value || 'Ketik teks berjalan di sini...';
        });

        // Toggle emoji panel
        toggleBtn.addEventListener('click', () => {
            emojiPanel.classList.toggle('hidden');
        });

        // Render emojis for a category
        function renderEmojis(cat) {
            emojiGrid.innerHTML = '';
            (emojiSets[cat] || []).forEach(em => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = em;
                btn.className = 'text-2xl w-10 h-10 flex items-center justify-center rounded-lg hover:bg-amber-100 transition-colors cursor-pointer';
                btn.addEventListener('click', () => {
                    const start = textInput.selectionStart;
                    const end = textInput.selectionEnd;
                    const text = textInput.value;
                    textInput.value = text.substring(0, start) + em + text.substring(end);
                    textInput.selectionStart = textInput.selectionEnd = start + em.length;
                    textInput.focus();
                    previewEl.textContent = textInput.value;
                });
                emojiGrid.appendChild(btn);
            });
        }

        // Tab switching
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => { t.classList.remove('bg-amber-500','text-white'); t.classList.add('bg-slate-200','text-slate-600'); });
                tab.classList.remove('bg-slate-200','text-slate-600');
                tab.classList.add('bg-amber-500','text-white');
                renderEmojis(tab.dataset.cat);
            });
        });

        // Init with first category
        renderEmojis('senyum');
    </script>
<?php require_once 'includes/footer.php'; ?>
