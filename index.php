<?php
session_start();
require_once 'includes/db.php';

// Fetch active event
$stmt = $pdo->prepare("SELECT * FROM events WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$active_event = $stmt->fetch();

$active_event_id = $active_event ? $active_event['id'] : null;
$active_event_name = $active_event ? $active_event['name'] : 'Survei Umum';
$active_event_expiry = $active_event ? $active_event['expires_at'] : null;

$is_expired = false;
if ($active_event_expiry && strtotime($active_event_expiry) < time()) {
    $is_expired = true;
}

// Fetch active questions for the active event
if ($active_event_id) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE is_active = 1 AND event_id = ? ORDER BY order_num ASC");
    $stmt->execute([$active_event_id]);
} else {
    // Fallback if no event is active (optional: load all active questions with no event_id)
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE is_active = 1 AND event_id IS NULL ORDER BY order_num ASC");
    $stmt->execute();
}
$db_questions = $stmt->fetchAll();

// Fetch settings
$stmt = $pdo->prepare("SELECT * FROM settings");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
$instansi_name = $settings['instansi_name'] ?? 'Direktorat Inovasi & Layanan';
$running_text = $settings['running_text'] ?? 'Selamat datang di portal Survei Layanan Publik.';

// We inject the static options for rating types
$rating_options = [
    ['value' => 'sangat_puas', 'label' => 'Sangat Puas', 'image' => 'assets/img/sangat_puas.png'],
    ['value' => 'puas', 'label' => 'Puas', 'image' => 'assets/img/puas.png'],
    ['value' => 'cukup_puas', 'label' => 'Cukup Puas', 'image' => 'assets/img/cukup_puas.png'],
    ['value' => 'tidak_puas', 'label' => 'Tidak Puas', 'image' => 'assets/img/tidak_puas.png']
];

$questions = [];
foreach ($db_questions as $q) {
    if ($q['type'] === 'rating') {
        $q['options'] = $rating_options;
    }
    $questions[] = $q;
}

// Initialize session variables
if (!isset($_SESSION['current_question_index'])) {
    $_SESSION['current_question_index'] = 0;
    $_SESSION['responses'] = [];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'next' || $_POST['action'] === 'submit') {
            // Save response to session temporarily
            $question_id = $_POST['question_id'];
            $_SESSION['responses'][$question_id] = $_POST['response'];

            // Advance to next question
            if ($_SESSION['current_question_index'] < count($questions) - 1) {
                $_SESSION['current_question_index']++;
            } else {
                // LAST QUESTION: Save to Database
                if (!isset($_SESSION['is_finished'])) {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("INSERT INTO survey_sessions (device_id, event_id) VALUES ('kiosk_main', ?)");
                        $stmt->execute([$active_event_id]);
                        $session_id = $pdo->lastInsertId();

                        $stmt = $pdo->prepare("INSERT INTO survey_answers (session_id, question_id, question_text, answer_value) VALUES (?, ?, ?, ?)");
                        foreach ($_SESSION['responses'] as $q_id => $ans) {
                            $q_text = 'Pertanyaan Tidak Diketahui';
                            foreach ($questions as $q) {
                                if ($q['id'] == $q_id) {
                                    $q_text = $q['question'];
                                    break;
                                }
                            }
                            $stmt->execute([$session_id, $q_id, $q_text, $ans]);
                        }
                        $pdo->commit();
                    } catch(PDOException $e) {
                        $pdo->rollBack();
                        die("Error saving results: " . $e->getMessage());
                    }
                }
                
                // Mark as finished for the view
                $_SESSION['is_finished'] = true;
            }
        } elseif ($_POST['action'] === 'prev') {
            // Go back
            if ($_SESSION['current_question_index'] > 0) {
                $_SESSION['current_question_index']--;
            }
        } elseif ($_POST['action'] === 'restart') {
            // Reset survey
            unset($_SESSION['current_question_index']);
            unset($_SESSION['responses']);
            unset($_SESSION['is_finished']);
            header("Location: ./");
            exit;
        }
    }
}

$current_index = $_SESSION['current_question_index'];
$is_finished = isset($_SESSION['is_finished']) && $_SESSION['is_finished'];
$total_questions = count($questions);
$current_question = $is_finished ? null : $questions[$current_index];
$progress_percent = (($current_index + 1) / $total_questions) * 100;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survei Layanan Publik - Direktorat Inovasi & Layanan</title>
    <!-- Font Awesome 6.4.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Confetti Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <!-- Tailwind Generated CSS -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <style>
        @keyframes bounce-x {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }
        .animate-bounce-x {
            animation: bounce-x 1s infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-amber-50 to-amber-100 text-slate-800 font-sans min-h-screen flex flex-col leading-relaxed">

    <div class="sticky top-0 z-50">
        <header class="bg-white/40 backdrop-blur-xl py-2 px-8 sm:px-12 flex flex-col sm:flex-row justify-between items-center shadow-sm border-b border-white/60">
            <div class="flex items-center gap-3 font-extrabold text-amber-700 text-lg tracking-wide drop-shadow-sm">
                <div class="bg-gradient-to-br from-amber-400 to-yellow-500 p-1.5 flex items-center justify-center rounded-lg shadow-inner text-white transition-transform hover:scale-105">
                    <i class="fa-solid fa-landmark-flag text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <span class="leading-none"><?php echo strtoupper($active_event_name); ?></span>
                    <span class="text-[10px] text-amber-600/60 tracking-[0.2em] font-black mt-1">PORTAL SURVEI</span>
                </div>
            </div>
            <div class="text-amber-900/70 font-bold text-xs mt-1 sm:mt-0 uppercase tracking-widest hidden md:block">
                <?php echo htmlspecialchars($instansi_name); ?>
            </div>
        </header>

        <div class="bg-amber-600/90 backdrop-blur-md text-white text-sm font-semibold py-1.5 overflow-hidden shadow-inner flex items-center relative border-b border-amber-700/30">
            <div class="animate-[marquee_25s_linear_infinite] whitespace-nowrap inline-block">
                <?php echo htmlspecialchars($running_text); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 flex justify-center items-center py-12 px-8">
        <div class="w-full max-w-5xl">
            <?php if ($is_expired): ?>
                <!-- Event Expired Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div class="w-[120px] h-[120px] bg-slate-100/50 rounded-full flex items-center justify-center mx-auto mb-12 text-slate-400 text-6xl shadow-sm">
                        <i class="fa-solid fa-hourglass-end"></i>
                    </div>
                    <h1 class="text-[3.2rem] font-bold text-slate-900 mb-5 leading-tight tracking-tight">Acara Selesai</h1>
                    <p class="text-slate-600 text-xl mb-14 font-medium px-4">
                        Maaf, periode pengisian survei untuk event <span class="text-amber-700 font-bold"><?php echo htmlspecialchars($active_event_name); ?></span> telah berakhir pada <?php echo date('d M Y, H:i', strtotime($active_event_expiry)); ?>.
                        <br><br>
                        Terima kasih atas partisipasi dan antusiasme Anda.
                    </p>
                    <div class="flex justify-center">
                        <div class="px-8 py-3 bg-white/40 border border-white/60 rounded-full text-slate-500 font-bold text-sm tracking-widest uppercase">
                            DIREKTORAT INOVASI & LAYANAN
                        </div>
                    </div>
                </div>
            <?php elseif ($is_finished): ?>
                <!-- Success Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div class="w-[120px] h-[120px] bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-12 animate-[pulse_1s_infinite] text-emerald-500 text-6xl shadow-sm">
                        <i class="fa-solid fa-heart-circle-check"></i>
                    </div>
                    <h1 class="text-[3.2rem] font-bold text-slate-900 mb-5 leading-tight tracking-tight">Sangat Berkesan!</h1>
                    <p class="text-slate-600 text-xl mb-14 font-medium px-4">
                        Terima kasih telah berbagi pengalaman Anda. Masukan Anda akan langsung kami gunakan untuk menyempurnakan kualitas layanan kami.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="restart">
                        <button type="submit" class="bg-amber-500 text-white font-bold py-4 px-12 rounded-full shadow-[0_10px_20px_rgba(245,158,11,0.3)] hover:shadow-[0_15px_30px_rgba(245,158,11,0.5)] hover:translate-x-2 transition-all duration-300 flex items-center gap-3 mx-auto">
                            KIRIM RESPONS BARU
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Survey Component -->
                <form id="surveyForm" method="POST">
                    <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                    
                    <div id="surveyContainer" class="bg-transparent py-8 animate-[fadeInScale_0.6s_ease-out]">
                        <span class="text-amber-700 uppercase font-bold text-sm tracking-widest mb-4 block opacity-80"><?php echo $current_question['section']; ?></span>
                        <h1 class="text-4xl md:text-[3.25rem] font-bold text-slate-900 mb-8 leading-tight tracking-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.15)]"><?php echo $current_question['question']; ?></h1>
                        
                        <div class="flex items-center gap-4 mb-10 w-full max-w-2xl">
                            <span class="text-sm font-bold text-amber-700 uppercase tracking-widest shrink-0">Tahap <?php echo $current_index + 1; ?> / <?php echo $total_questions; ?></span>
                            <div class="flex-1 bg-amber-200/50 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-600 h-2 rounded-full transition-all duration-1000 ease-out" style="width: <?php echo (($current_index + 1) / $total_questions) * 100; ?>%"></div>
                            </div>
                        </div>

                        <?php if ($current_question['type'] === 'rating'): ?>
                            <!-- Rating Options Grid -->
                            <div class="survey-options-grid grid grid-cols-2 md:grid-cols-4 gap-8 mb-4 <?php echo isset($_SESSION['responses'][$current_question['id']]) ? 'has-selection' : ''; ?>">
                                <?php foreach ($current_question['options'] as $option): ?>
                                    <?php 
                                        $selected = (isset($_SESSION['responses'][$current_question['id']]) && $_SESSION['responses'][$current_question['id']] == $option['value']) ? 'selected' : '';
                                    ?>
                                    <label class="option-card <?php echo $selected; ?> group bg-white/55 backdrop-blur-2xl border-2 border-white/80 rounded-3xl p-6 text-center cursor-pointer transition-all duration-300 flex flex-col items-center gap-6 hover:bg-white/70 shadow-[0_8px_32px_rgba(0,0,0,0.08),inset_0_2px_0_rgba(255,255,255,0.9),inset_0_-1px_0_rgba(255,255,255,0.4)] hover:shadow-[0_12px_40px_rgba(251,191,36,0.2),inset_0_2px_0_rgba(255,255,255,1)]">
                                        <input type="radio" name="response" value="<?php echo $option['value']; ?>" class="hidden" required <?php echo $selected ? 'checked' : ''; ?>>
                                        <!-- Image Wrapper for Perfect Circle Cropping -->
                                        <div class="w-[140px] h-[140px] rounded-full overflow-hidden shadow-[0_10px_20px_rgba(0,0,0,0.1)] group-hover:shadow-[0_15px_30px_rgba(251,191,36,0.3)] transition-all duration-300 group-hover:-translate-y-2 group-[.selected]:ring-8 group-[.selected]:ring-amber-400/70 group-[.selected]:shadow-[0_20px_40px_rgba(251,191,36,0.4)] flex justify-center items-center bg-transparent mt-2">
                                            <img src="<?php echo $option['image']; ?>" class="w-full h-full object-cover scale-[1.7] group-hover:scale-[1.75] group-[.selected]:scale-[1.8] transition-transform duration-500 ease-out" alt="<?php echo $option['label']; ?>">
                                        </div>
                                        <span class="text-sm font-extrabold text-slate-600 uppercase group-[.selected]:text-amber-700 transition-colors duration-300"><?php echo $option['label']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($current_question['type'] === 'text'): ?>
                            <textarea name="response" class="feedback-textarea w-full min-h-[200px] p-8 rounded-2xl border-4 border-white/40 font-inherit text-xl resize-none transition-all duration-300 bg-white/50 backdrop-blur-sm focus:outline-none focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20" placeholder="<?php echo $current_question['placeholder']; ?>" required><?php echo isset($_SESSION['responses'][$current_question['id']]) ? $_SESSION['responses'][$current_question['id']] : ''; ?></textarea>
                            <!-- Virtual Keyboard Toggle -->
                            <div class="flex justify-end mt-3">
                                <button type="button" id="toggleKeyboardBtn" class="flex items-center gap-2 px-5 py-2.5 bg-white/60 backdrop-blur-lg border-2 border-white/70 rounded-full text-sm font-bold text-slate-600 hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300 transition-all duration-300 shadow-sm">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <span id="toggleKeyboardLabel">Gunakan Keyboard Virtual</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Navigation Bar -->
                    <div class="flex justify-between items-center mt-12">
                        <?php if ($current_index > 0): ?>
                            <button type="submit" name="action" value="prev" class="flex items-center gap-3 py-4 px-10 rounded-full font-bold cursor-pointer transition-all duration-300 text-base bg-white/40 backdrop-blur-sm text-slate-700 border border-white/40 hover:bg-white">
                                <i class="fa-solid fa-chevron-left"></i>
                                Kembali
                            </button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <button type="submit" name="action" value="<?php echo ($current_index === $total_questions - 1) ? 'submit' : 'next'; ?>" class="btn-next flex items-center gap-3 py-4 px-16 rounded-full font-bold cursor-pointer transition-all duration-300 text-base bg-amber-500 text-white shadow-[0_10px_20px_rgba(245,158,11,0.3)] disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed disabled:shadow-none hover:not-disabled:translate-x-2 hover:not-disabled:shadow-[0_15px_30px_rgba(245,158,11,0.5)]" <?php echo isset($_SESSION['responses'][$current_question['id']]) ? '' : 'disabled'; ?>>
                            <?php echo ($current_index === $total_questions - 1) ? 'KIRIM SURVEI' : 'LANJUTKAN'; ?>
                            <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="w-20"></div> <!-- Spacer for balance -->
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white/60 backdrop-blur-md border-t border-black/5 py-4 mt-auto">
        <div class="max-w-6xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center text-xs md:text-sm text-slate-500 font-medium">
            <div>© <?php echo date('Y'); ?> Survei Layanan Publik - <?php echo htmlspecialchars($instansi_name); ?>.</div>
            <div class="flex items-center gap-6 mt-3 md:mt-0">
                <a href="#" class="hover:text-amber-600 transition-colors hidden sm:block">Beranda</a>
                <a href="#" class="hover:text-amber-600 transition-colors hidden sm:block">Bantuan</a>
                <div class="flex gap-4 sm:ml-4 text-base">
                    <i class="fa-brands fa-facebook hover:text-blue-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-instagram hover:text-pink-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-twitter hover:text-sky-500 cursor-pointer transition-colors"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- Virtual Keyboard -->
    <div id="virtualKeyboard" class="bg-white/95 backdrop-blur-md border border-black/10 shadow-[0_-20px_50px_rgba(0,0,0,0.1)] rounded-t-[20px] fixed -bottom-[100%] left-0 w-full p-6 z-[1000] transition-all duration-500 ease-[cubic-bezier(0.68,-0.55,0.265,1.55)]">
        <!-- Keyboard rows will be generated by JS -->
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
