<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/utils.php';

// Fetch event from URL (could be masked) or active event
$target_event_id = $_GET['event_id'] ?? null;

if ($target_event_id) {
    // Try unmasking first
    $unmasked = unmaskId($target_event_id);
    $actual_id = $unmasked ?: (is_numeric($target_event_id) ? $target_event_id : null);
    
    if ($actual_id) {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$actual_id]);
        $active_event = $stmt->fetch();
    }
}

if (!isset($active_event) || !$active_event) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $active_event = $stmt->fetch();
}

$active_event_id = $active_event ? $active_event['id'] : null;
$active_event_name = $active_event ? $active_event['name'] : 'Survei Umum';
$active_event_expiry = $active_event ? $active_event['expires_at'] : null;
$is_share_link = isset($_GET['event_id']);
$already_submitted = false;

if ($active_event_id && $is_share_link && isset($_COOKIE["survey_submitted_" . $active_event_id])) {
    $already_submitted = true;
}

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
            $question_id = $_POST['question_id'];
            $response = trim($_POST['response'] ?? '');
            
            // SERVER-SIDE VALIDATION: Reject truly empty answers
            if ($response === '') {
                header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
                exit;
            }

            // Save response to session temporarily
            $_SESSION['responses'][$question_id] = $response;

            // Advance to next question
            if ($_SESSION['current_question_index'] < count($questions) - 1) {
                $_SESSION['current_question_index']++;
            } else {
                // LAST QUESTION: Save to Database
                if (!isset($_SESSION['is_finished'])) {
                    // Safety check: ensure we have at least some data
                    if (empty($_SESSION['responses'])) {
                        header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
                        exit;
                    }

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
                
                // Set cookie if share link
                if ($is_share_link) {
                    setcookie("survey_submitted_" . $active_event_id, "1", time() + (86400 * 365), "/");
                }
            }

            // REDIRECT - Prevent skip on refresh (Post-Redirect-Get)
            header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
            exit;
        } elseif ($_POST['action'] === 'prev') {
            // Go back
            if ($_SESSION['current_question_index'] > 0) {
                $_SESSION['current_question_index']--;
            }
            
            // REDIRECT - Prevent re-submission on refresh
            header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
            exit;
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
        /* Custom PC/Touchscreen Square Grid */
        @media (min-width: 768px) {
            .option-card-square {
                aspect-ratio: 1 / 1 !important;
                justify-content: center !important;
                border-radius: 32px !important;
            }
            .kiosk-icon-size {
                width: 200px !important;
                height: 200px !important;
            }
        }
        /* Modal Animations */
        .modal-active {
            display: flex !important;
        }
        .modal-content-active {
            transform: scale(1) !important;
            opacity: 1 !important;
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
    <main class="flex-1 flex justify-center items-center py-6 md:py-12 px-4 md:px-8">
        <div class="w-full max-w-5xl">
            <?php if ($is_expired): ?>
                <!-- Event Expired Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div class="w-20 h-20 md:w-[120px] md:h-[120px] bg-slate-100/50 rounded-full flex items-center justify-center mx-auto mb-8 md:mb-12 text-slate-400 text-4xl md:text-6xl shadow-sm">
                        <i class="fa-solid fa-hourglass-end"></i>
                    </div>
                    <h1 class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">Acara Selesai</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        Maaf, periode pengisian survei untuk event <span class="text-amber-700 font-bold"><?php echo htmlspecialchars($active_event_name); ?></span> telah berakhir pada <?php echo date('d M Y, H:i', strtotime($active_event_expiry)); ?>.
                        <br><br>
                        Terima kasih atas partisipasi dan antusiasme Anda.
                    </p>
                    <div class="flex justify-center">
                        <div class="px-6 py-2 md:px-8 md:py-3 bg-white/40 border border-white/60 rounded-full text-slate-500 font-bold text-xs md:text-sm tracking-widest uppercase text-center">
                            DIREKTORAT INOVASI & LAYANAN
                        </div>
                    </div>
                </div>
            <?php elseif ($already_submitted): ?>
                <!-- Already Submitted Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div class="w-20 h-20 md:w-[120px] md:h-[120px] bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-8 md:mb-12 text-amber-500 text-4xl md:text-6xl shadow-sm">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h1 class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">Sudah Mengisi</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        Anda telah berpartisipasi dan mengisi survei ini sebelumnya. Terima kasih atas masukan yang telah diberikan.
                    </p>
                </div>
            <?php elseif ($is_finished): ?>
                <!-- Success Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div class="w-20 h-20 md:w-[120px] md:h-[120px] bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-8 md:mb-12 animate-[pulse_1s_infinite] text-emerald-500 text-4xl md:text-6xl shadow-sm">
                        <i class="fa-solid fa-heart-circle-check"></i>
                    </div>
                    <h1 class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">Sangat Berkesan!</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        Terima kasih telah berbagi pengalaman Anda. Masukan Anda akan langsung kami gunakan untuk menyempurnakan kualitas layanan kami.
                    </p>
                    
                    <?php if (!$is_share_link): ?>
                        <!-- Kiosk Mode: Allow Next User -->
                        <form method="POST">
                            <input type="hidden" name="action" value="restart">
                            <button type="submit" class="bg-amber-500 text-white font-bold py-3 md:py-4 px-8 md:px-12 rounded-full shadow-[0_10px_20px_rgba(245,158,11,0.3)] hover:shadow-[0_15px_30px_rgba(245,158,11,0.5)] hover:translate-x-2 transition-all duration-300 flex items-center justify-center gap-2 md:gap-3 mx-auto text-sm md:text-base w-full md:w-auto">
                                KIRIM RESPONS BARU
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Survey Component -->
                <form id="surveyForm" method="POST">
                    <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                    
                    <div id="surveyContainer" class="bg-transparent py-4 md:py-8 animate-[fadeInScale_0.6s_ease-out]">
                        <span class="text-amber-700 uppercase font-bold text-xs md:text-sm tracking-widest mb-2 md:mb-4 block opacity-80"><?php echo $current_question['section']; ?></span>
                        <h1 class="text-2xl md:text-[3.25rem] font-bold text-slate-900 mb-6 md:mb-8 leading-tight tracking-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.15)]"><?php echo $current_question['question']; ?></h1>
                        
                        <div class="flex items-center gap-3 md:gap-4 mb-8 md:mb-10 w-full max-w-2xl">
                            <span class="text-xs md:text-sm font-bold text-amber-700 uppercase tracking-widest shrink-0">Tahap <?php echo $current_index + 1; ?> / <?php echo $total_questions; ?></span>
                            <div class="flex-1 bg-amber-200/50 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-600 h-2 rounded-full transition-all duration-1000 ease-out" style="width: <?php echo (($current_index + 1) / $total_questions) * 100; ?>%"></div>
                            </div>
                        </div>

                        <?php if ($current_question['type'] === 'rating'): ?>
                            <!-- Rating Options Grid -->
                            <div class="survey-options-grid grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 mb-4 <?php echo isset($_SESSION['responses'][$current_question['id']]) ? 'has-selection' : ''; ?>">
                                <?php foreach ($current_question['options'] as $option): ?>
                                    <?php 
                                        $selected = (isset($_SESSION['responses'][$current_question['id']]) && $_SESSION['responses'][$current_question['id']] == $option['value']) ? 'selected' : '';
                                    ?>
                                    <label class="option-card option-card-square <?php echo $selected; ?> group bg-white/55 backdrop-blur-2xl border-2 border-white/80 rounded-[1rem] md:rounded-3xl p-2 md:p-6 text-center cursor-pointer transition-all duration-300 flex flex-col justify-center items-center gap-2 md:gap-4 hover:bg-white/70 shadow-[0_4px_16px_rgba(0,0,0,0.08),inset_0_2px_0_rgba(255,255,255,0.9),inset_0_-1px_0_rgba(255,255,255,0.4)] hover:shadow-[0_8px_20px_rgba(251,191,36,0.2),inset_0_2px_0_rgba(255,255,255,1)]">
                                        <input type="radio" name="response" value="<?php echo $option['value']; ?>" class="hidden" required <?php echo $selected ? 'checked' : ''; ?>>
                                        <!-- Image Wrapper for Perfect Circle Cropping -->
                                        <div class="w-16 h-16 kiosk-icon-size rounded-full overflow-hidden shadow-[0_5px_10px_rgba(0,0,0,0.1)] group-hover:shadow-[0_8px_15px_rgba(251,191,36,0.3)] transition-all duration-300 md:group-hover:-translate-y-2 group-[.selected]:ring-4 md:group-[.selected]:ring-8 group-[.selected]:ring-amber-400/70 group-[.selected]:shadow-[0_10px_20px_rgba(251,191,36,0.4)] flex justify-center items-center bg-transparent mt-1 md:mt-2">
                                            <img src="<?php echo $option['image']; ?>" class="w-full h-full object-cover scale-[1.7] group-hover:scale-[1.75] group-[.selected]:scale-[1.8] transition-transform duration-500 ease-out" alt="<?php echo $option['label']; ?>">
                                        </div>
                                        <span class="text-[10px] md:text-sm font-extrabold text-slate-600 uppercase group-[.selected]:text-amber-700 transition-colors duration-300"><?php echo $option['label']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($current_question['type'] === 'text'): ?>
                            <textarea name="response" class="feedback-textarea w-full min-h-[120px] md:min-h-[200px] p-4 md:p-8 rounded-xl md:rounded-2xl border-2 md:border-4 border-white/40 font-inherit text-base md:text-xl resize-none transition-all duration-300 bg-white/50 backdrop-blur-sm focus:outline-none focus:bg-white focus:border-amber-400 focus:ring-2 md:focus:ring-4 focus:ring-amber-400/20" placeholder="<?php echo $current_question['placeholder']; ?>" required><?php echo isset($_SESSION['responses'][$current_question['id']]) ? $_SESSION['responses'][$current_question['id']] : ''; ?></textarea>
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
                    <div class="flex flex-row flex-wrap sm:flex-nowrap justify-between items-center mt-8 md:mt-12 gap-4">
                        <?php if ($current_index > 0): ?>
                            <button type="submit" name="action" value="prev" class="flex-1 sm:flex-none justify-center items-center gap-2 md:gap-3 py-3 md:py-4 px-6 md:px-10 rounded-full font-bold cursor-pointer transition-all duration-300 text-sm md:text-base bg-white/60 backdrop-blur-sm text-slate-700 border border-white/60 shadow-sm hover:bg-white">
                                <i class="fa-solid fa-chevron-left"></i>
                                KEMBALI
                            </button>
                        <?php else: ?>
                            <div class="hidden sm:block flex-1 sm:flex-none"></div>
                        <?php endif; ?>
                        
                        <button type="submit" name="action" value="<?php echo ($current_index === $total_questions - 1) ? 'submit' : 'next'; ?>" class="btn-next flex-1 sm:flex-none justify-center flex items-center gap-2 md:gap-3 py-3 md:py-4 px-8 md:px-16 rounded-full font-bold cursor-pointer transition-all duration-300 text-sm md:text-base bg-amber-500 text-white shadow-[0_10px_20px_rgba(245,158,11,0.3)] disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed disabled:shadow-none hover:not-disabled:translate-x-2 hover:not-disabled:shadow-[0_15px_30px_rgba(245,158,11,0.5)]" <?php echo isset($_SESSION['responses'][$current_question['id']]) ? '' : 'disabled'; ?>>
                            <?php echo ($current_index === $total_questions - 1) ? 'KIRIM SURVEI' : 'LANJUTKAN'; ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                        
                        <div class="hidden md:block w-20"></div> <!-- Spacer for balance only on desktop -->
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
                <a href="javascript:void(0)" id="openHelpBtn" class="hover:text-amber-600 transition-colors hidden sm:block">Bantuan</a>
                <div class="flex gap-4 sm:ml-4 text-base">
                    <i class="fa-brands fa-facebook hover:text-blue-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-instagram hover:text-pink-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-twitter hover:text-sky-500 cursor-pointer transition-colors"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- Help Modal -->
    <div id="helpModal" style="position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1rem; transition: all 0.3s ease;">
        <!-- Backdrop -->
        <div id="helpModalBackdrop" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);"></div>
        
        <!-- Content Box -->
        <div id="helpModalContent" style="position: relative; z-index: 10; background: #ffffff; border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); width: 100%; max-width: 32rem; padding: 2.5rem; transform: scale(0.9); opacity: 0; transition: all 0.3s ease;">
            <!-- Close Icon Button -->
            <button id="closeHelpIconBtn" style="position: absolute; top: 1.5rem; right: 1.5rem; color: #94a3b8; border: none; background: transparent; cursor: pointer; padding: 0.5rem;">
                <i class="fa-solid fa-xmark text-2xl"></i>
            </button>

            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 5rem; height: 5rem; background: #fffbeb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: #d97706; font-size: 1.875rem; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <h2 style="font-size: 1.875rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin: 0;">Panduan Survei</h2>
                <p style="color: #64748b; font-weight: 500; margin-top: 0.5rem;">Cara mudah memberikan masukan Anda</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.5rem; text-align: left;">
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #fffbeb; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #f59e0b; flex-shrink: 0;">
                        <i class="fa-solid fa-face-smile"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1.125rem; margin-bottom: 0.25rem; line-height: 1.2;">Pilih Emoji</h4>
                        <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Klik salah satu gambar yang paling mewakili tingkat kepuasan Anda.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #fffbeb; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #f59e0b; flex-shrink: 0;">
                        <i class="fa-solid fa-keyboard"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1.125rem; margin-bottom: 0.25rem; line-height: 1.2;">Berikan Saran</h4>
                        <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Gunakan keyboard fisik atau virtual untuk mengetik masukan Anda di halaman terakhir.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #ecfdf5; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #10b981; flex-shrink: 0;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1.125rem; margin-bottom: 0.25rem; line-height: 1.2;">Kirim Survei</h4>
                        <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Klik tombol "KIRIM SURVEI" untuk menyimpan jawaban Anda secara permanen.</p>
                    </div>
                </div>
            </div>

            <button id="closeHelpBtn" style="width: 100%; margin-top: 2.5rem; background: #0f172a; color: #ffffff; font-weight: 700; padding: 1rem; border: none; border-radius: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                SAYA MENGERTI
                <i class="fa-solid fa-check-circle"></i>
            </button>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
