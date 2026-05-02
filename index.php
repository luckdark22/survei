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
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$actual_id]);
        $active_event = $stmt->fetch();
    }
}

if (!isset($active_event) || !$active_event) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE is_active = 1 AND is_deleted = 0 LIMIT 1");
    $stmt->execute();
    $active_event = $stmt->fetch();
}

$active_event_id = $active_event ? $active_event['id'] : null;
$active_event_name = $active_event ? $active_event['name'] : 'Survei Umum';
$active_event_expiry = $active_event ? $active_event['expires_at'] : null;
$is_share_link = isset($_GET['event_id']);
$already_submitted = false;

if ($active_event_id && $is_share_link && isset($_COOKIE["survey_submitted_" . $active_event_id]) && !isset($_GET['preview'])) {
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
if (!isset($_SESSION['current_question_index']) || isset($_GET['preview'])) {
    // If it's a preview, we force reset to question 1 so they can test multiple times easily
    if (isset($_GET['preview'])) {
        // Only reset if we haven't already started this preview session or if it's a fresh hit
        // We detect "fresh hit" by checking if we just came from the builder or if we're at the end
        if (!isset($_SESSION['is_preview_mode']) || isset($_SESSION['is_finished'])) {
            $_SESSION['current_question_index'] = 0;
            $_SESSION['responses'] = [];
            unset($_SESSION['is_finished']);
            unset($_SESSION['preview_notice']);
        }
        $_SESSION['is_preview_mode'] = true;
    } else {
        unset($_SESSION['is_preview_mode']);
        if (!isset($_SESSION['current_question_index'])) {
            $_SESSION['current_question_index'] = 0;
            $_SESSION['responses'] = [];
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'next' || $_POST['action'] === 'submit') {
            $question_id = $_POST['question_id'];
            $response_raw = $_POST['response'] ?? '';
            
            // Handle array response (checkboxes)
            if (is_array($response_raw)) {
                $response = implode(', ', array_map('trim', $response_raw));
            } else {
                $response = trim((string)$response_raw);
            }

            // SERVER-SIDE VALIDATION: Reject empty or too short answers
            $is_text_type = false;
            foreach ($questions as $q) {
                if ($q['id'] == $question_id && $q['type'] === 'text') {
                    $is_text_type = true;
                    break;
                }
            }

            if ($response === '' || ($is_text_type && strlen($response) < 4)) {
                header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
                exit;
            }

            // Save response to session temporarily
            $_SESSION['responses'][$question_id] = $response;

            // Advance to next question
            if ($_SESSION['current_question_index'] < count($questions) - 1) {
                $_SESSION['current_question_index']++;
            } else {
                // LAST QUESTION: Save to Database (Skip if Preview)
                $is_preview = isset($_GET['preview']) || isset($_SESSION['is_preview_mode']);
                
                if ($is_preview) {
                    $_SESSION['is_finished'] = true;
                    $_SESSION['preview_notice'] = "Jawaban tidak disimpan karena sedang dalam Mode Pratinjau.";
                } elseif (!isset($_SESSION['is_finished'])) {
                    // Safety check: ensure we have at least some data
                    if (empty($_SESSION['responses'])) {
                        header("Location: ./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : ""));
                        exit;
                    }

                    try {
                        $pdo->beginTransaction();
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        $stmt = $pdo->prepare("INSERT INTO survey_sessions (device_id, event_id, user_agent, ip_address) VALUES ('kiosk_main', ?, ?, ?)");
                        $stmt->execute([$active_event_id, $user_agent, $ip_address]);
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

                            // Safety cast to string to prevent NULL integrity violation
                            $final_ans = (string) ($ans ?? '');
                            $stmt->execute([$session_id, (int) $q_id, $q_text, $final_ans]);
                        }
                        $pdo->commit();
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        die("Error saving results: " . $e->getMessage());
                    }
                }

                // Mark as finished for the view
                $_SESSION['is_finished'] = true;

                // Set cookie if share link (only if not preview)
                if ($is_share_link && !$is_preview) {
                    setcookie("survey_submitted_" . $active_event_id, "1", time() + (86400 * 365), "/");
                }
            }

            // REDIRECT - Prevent skip on refresh (Post-Redirect-Get)
            $redirect_url = "./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : "");
            if (isset($_GET['preview'])) $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "preview=1";
            header("Location: " . $redirect_url);
            exit;
        } elseif ($_POST['action'] === 'prev') {
            // Go back
            if ($_SESSION['current_question_index'] > 0) {
                $_SESSION['current_question_index']--;
            }

            // REDIRECT - Prevent re-submission on refresh
            $redirect_url = "./" . (isset($_GET['event_id']) ? "?event_id=" . $_GET['event_id'] : "");
            if (isset($_GET['preview'])) $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "preview=1";
            header("Location: " . $redirect_url);
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
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo time(); ?>">
    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        @keyframes bounce-x {

            0%,
            100% {
                transform: translateX(0);
            }

            50% {
                transform: translateX(5px);
            }
        }

        .animate-bounce-x {
            animation: bounce-x 1s infinite;
        }

        @keyframes heartbeat-soft {

            0%,
            100% {
                transform: scale(1);
            }

            15% {
                transform: scale(1.15);
            }

            30% {
                transform: scale(1);
            }

            45% {
                transform: scale(1.15);
            }

            60% {
                transform: scale(1);
            }
        }

        .animate-heartbeat-soft {
            animation: heartbeat-soft 2.5s infinite ease-in-out;
            transform-origin: center;
        }

        @keyframes pop-bounce {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            60% {
                transform: scale(1.1);
                opacity: 1;
            }

            80% {
                transform: scale(0.95);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .animate-pop-bounce {
            animation: pop-bounce 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
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

        /* Virtual Keyboard Premium Styling */
        .keyboard-container {
            position: fixed;
            bottom: -100%;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 20px 0;
            z-index: 1000;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.1), 0 -2px 0 rgba(255, 255, 255, 0.5);
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .keyboard-container.keyboard-active {
            bottom: 0;
        }

        .keyboard-row {
            display: flex;
            gap: 6px;
            justify-content: center;
            width: 100%;
            max-width: 900px;
            padding: 0 10px;
        }

        .keyboard-key {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 0;
            min-width: 45px;
            flex: 1;
            font-weight: 700;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 0 #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            text-transform: lowercase;
            font-size: 14px;
        }

        .keyboard-key:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #cbd5e1;
            background: #f8fafc;
        }

        .keyboard-key.key-wide {
            flex: 2.5;
        }

        .keyboard-key.key-enter {
            background: #f59e0b;
            color: white;
            border-color: #d97706;
            box-shadow: 0 4px 0 #b45309;
            flex: 2;
            text-transform: uppercase;
        }

        .keyboard-key.key-space {
            flex: 5;
            text-transform: uppercase;
        }

        .keyboard-key.key-special {
            background: #f1f5f9;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .keyboard-key {
                min-width: 30px;
                padding: 10px 0;
                font-size: 12px;
                border-radius: 6px;
            }

            .keyboard-container {
                padding: 12px 0;
                gap: 5px;
            }
        }
    </style>
</head>

<body
    class="bg-gradient-to-br from-amber-50 to-amber-100 text-slate-800 font-sans min-h-screen flex flex-col leading-relaxed">

    <div class="sticky top-0 z-50">
        <header
            class="bg-white/40 backdrop-blur-xl py-2 px-8 sm:px-12 flex flex-col sm:flex-row justify-between items-center shadow-sm border-b border-white/60">
            <div class="flex items-center gap-3 font-extrabold text-amber-700 text-lg tracking-wide drop-shadow-sm">
                <div
                    class="bg-gradient-to-br from-amber-400 to-yellow-500 p-1.5 flex items-center justify-center rounded-lg shadow-inner text-white transition-transform hover:scale-105">
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

        <div
            class="bg-amber-600/90 backdrop-blur-md text-white text-sm font-semibold py-1.5 overflow-hidden shadow-inner flex items-center relative border-b border-amber-700/30">
            <div class="animate-[marquee_25s_linear_infinite] whitespace-nowrap inline-block">
                <?php echo htmlspecialchars($running_text); ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($_GET['preview']) || isset($_SESSION['preview_notice'])): ?>
    <div class="bg-indigo-600 text-white text-[10px] font-black py-2 px-4 flex items-center justify-center gap-3 uppercase tracking-[0.2em] shadow-lg relative z-[60] animate-pulse">
        <i class="fa-solid fa-eye text-sm"></i>
        <span>Mode Pratinjau: Data Tidak Akan Disimpan</span>
        <a href="admin/questions_builder?event_id=<?php echo $active_event_id; ?>" class="ml-4 bg-white/20 hover:bg-white/40 px-3 py-1 rounded-full transition-all">Keluar Pratinjau</a>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-1 flex justify-center items-center py-6 md:py-12 px-4 md:px-8">
        <div class="w-full max-w-5xl">
            <?php if ($is_expired): ?>
                <!-- Event Expired Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8 animate-[fadeInScale_0.6s_ease-out]">
                    <div
                        class="w-20 h-20 md:w-[120px] md:h-[120px] bg-slate-100/50 rounded-full flex items-center justify-center mx-auto mb-8 md:mb-12 text-slate-400 text-4xl md:text-6xl shadow-sm">
                        <i class="fa-solid fa-hourglass-end"></i>
                    </div>
                    <h1
                        class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">
                        Acara Selesai</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        Maaf, periode pengisian survei untuk event <span
                            class="text-amber-700 font-bold"><?php echo htmlspecialchars($active_event_name); ?></span>
                        telah berakhir pada <?php echo date('d M Y, H:i', strtotime($active_event_expiry)); ?>.
                        <br><br>
                        Terima kasih atas partisipasi dan antusiasme Anda.
                    </p>
                    <div class="flex justify-center">
                        <div
                            class="px-6 py-2 md:px-8 md:py-3 bg-white/40 border border-white/60 rounded-full text-slate-500 font-bold text-xs md:text-sm tracking-widest uppercase text-center">
                            DIREKTORAT INOVASI & LAYANAN
                        </div>
                    </div>
                </div>
            <?php elseif ($already_submitted): ?>
                <!-- Already Submitted Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8">
                    <div
                        class="inline-flex items-center justify-center px-16 py-5 bg-amber-50/90 backdrop-blur-sm rounded-full mb-6 md:mb-10 text-amber-500 text-6xl md:text-[4.5rem] shadow-sm ring-4 ring-white/60 animate-pop-bounce">
                        <i class="fa-solid fa-heart-circle-check animate-heartbeat-soft"></i>
                    </div>
                    <h1
                        class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">
                        Sudah Mengisi</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        Anda telah berpartisipasi dan mengisi survei ini sebelumnya. Terima kasih atas masukan yang telah
                        diberikan.
                    </p>
                </div>
            <?php elseif ($is_finished): ?>
                <!-- Success Screen -->
                <div class="bg-transparent text-center max-w-2xl mx-auto py-8">
                    <div
                        class="inline-flex items-center justify-center px-16 py-5 bg-emerald-50/90 backdrop-blur-sm rounded-full mb-6 md:mb-10 text-emerald-500 text-6xl md:text-[4.5rem] shadow-sm ring-4 ring-white/60 animate-pop-bounce">
                        <i class="fa-solid fa-heart-circle-check animate-heartbeat-soft"></i>
                    </div>
                    <h1
                        class="text-3xl md:text-[3.2rem] font-bold text-slate-900 mb-4 md:mb-5 leading-tight tracking-tight">
                        Sangat Berkesan!</h1>
                    <p class="text-base md:text-xl text-slate-600 mb-10 md:mb-14 font-medium px-4">
                        <?php 
                        if (isset($_SESSION['preview_notice'])) {
                            echo '<span class="text-indigo-600 font-black underline decoration-2 underline-offset-4">' . $_SESSION['preview_notice'] . '</span>';
                            unset($_SESSION['preview_notice']);
                        } else {
                            echo 'Terima kasih telah berbagi pengalaman Anda. Masukan Anda akan langsung kami gunakan untuk menyempurnakan kualitas layanan kami.';
                        }
                        ?>
                    </p>

                    <?php if (!$is_share_link): ?>
                        <!-- Kiosk Mode: Allow Next User -->
                        <form method="POST">
                            <input type="hidden" name="action" value="restart">
                            <button type="submit"
                                class="bg-amber-500 text-white font-bold py-3 md:py-4 px-8 md:px-12 rounded-full shadow-[0_10px_20px_rgba(245,158,11,0.3)] hover:shadow-[0_15px_30px_rgba(245,158,11,0.5)] hover:translate-x-2 transition-all duration-300 flex items-center justify-center gap-2 md:gap-3 mx-auto text-sm md:text-base w-full md:w-auto">
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
                        <span
                            class="text-amber-700 uppercase font-bold text-xs md:text-sm tracking-widest mb-2 md:mb-4 block opacity-80"><?php echo $current_question['section']; ?></span>
                        <h1
                            class="text-2xl md:text-[3.25rem] font-bold text-slate-900 <?php echo $current_question['type'] === 'checkbox' ? 'mb-2' : 'mb-6 md:mb-8'; ?> leading-tight tracking-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.15)]">
                            <?php echo $current_question['question']; ?></h1>
                        <?php if ($current_question['type'] === 'checkbox'): ?>
                            <p class="text-sm md:text-lg font-bold text-amber-600/80 mb-6 md:mb-8 tracking-wide italic animate-pulse">
                                <i class="fa-solid fa-circle-info mr-1.5 opacity-70"></i> (Pilih salah satu atau lebih)
                            </p>
                        <?php endif; ?>

                        <div class="flex items-center gap-3 md:gap-4 mb-8 md:mb-10 w-full max-w-2xl">
                            <span
                                class="text-xs md:text-sm font-bold text-amber-700 uppercase tracking-widest shrink-0">Tahap
                                <?php echo $current_index + 1; ?> / <?php echo $total_questions; ?></span>
                            <div class="flex-1 bg-amber-200/50 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-600 h-2 rounded-full transition-all duration-1000 ease-out"
                                    style="width: <?php echo (($current_index + 1) / $total_questions) * 100; ?>%"></div>
                            </div>
                        </div>

                        <?php if ($current_question['type'] === 'rating'): ?>
                            <!-- Rating Options Grid -->
                            <div
                                class="survey-options-grid grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 mb-4 <?php echo isset($_SESSION['responses'][$current_question['id']]) ? 'has-selection' : ''; ?>">
                                <?php foreach ($current_question['options'] as $option): ?>
                                    <?php
                                    $selected = (isset($_SESSION['responses'][$current_question['id']]) && $_SESSION['responses'][$current_question['id']] == $option['value']) ? 'selected' : '';
                                    ?>
                                    <label
                                        class="option-card option-card-square <?php echo $selected; ?> group bg-white/55 backdrop-blur-2xl border-2 border-white/80 rounded-[1rem] md:rounded-3xl p-2 md:p-6 text-center cursor-pointer transition-all duration-300 flex flex-col justify-center items-center gap-2 md:gap-4 hover:bg-white/70 shadow-[0_4px_16px_rgba(0,0,0,0.08),inset_0_2px_0_rgba(255,255,255,0.9),inset_0_-1px_0_rgba(255,255,255,0.4)] hover:shadow-[0_8px_20px_rgba(251,191,36,0.2),inset_0_2px_0_rgba(255,255,255,1)]">
                                        <input type="radio" name="response" value="<?php echo $option['value']; ?>" class="hidden"
                                            required <?php echo $selected ? 'checked' : ''; ?>>
                                        <!-- Image Wrapper for Perfect Circle Cropping -->
                                        <div
                                            class="w-16 h-16 kiosk-icon-size rounded-full overflow-hidden shadow-[0_5px_10px_rgba(0,0,0,0.1)] group-hover:shadow-[0_8px_15px_rgba(251,191,36,0.3)] transition-all duration-300 md:group-hover:-translate-y-2 group-[.selected]:ring-4 md:group-[.selected]:ring-8 group-[.selected]:ring-amber-400/70 group-[.selected]:shadow-[0_10px_20px_rgba(251,191,36,0.4)] flex justify-center items-center bg-transparent mt-1 md:mt-2">
                                            <img src="<?php echo $option['image']; ?>"
                                                class="w-full h-full object-cover scale-[1.7] group-hover:scale-[1.75] group-[.selected]:scale-[1.8] transition-transform duration-500 ease-out"
                                                alt="<?php echo $option['label']; ?>">
                                        </div>
                                        <span
                                            class="text-[10px] md:text-sm font-extrabold text-slate-600 uppercase group-[.selected]:text-amber-700 transition-colors duration-300"><?php echo $option['label']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($current_question['type'] === 'text'): ?>
                            <textarea name="response"
                                class="feedback-textarea w-full min-h-[150px] md:min-h-[220px] p-6 md:p-8 rounded-2xl md:rounded-3xl border-2 border-white/50 font-medium text-base md:text-xl text-slate-800 resize-none transition-all duration-300 bg-white/40 backdrop-blur-md shadow-[inner_0_2px_4px_rgba(0,0,0,0.05)] placeholder:text-slate-400 focus:outline-none focus:bg-white/90 focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 focus:shadow-[0_15px_30px_-10px_rgba(245,158,11,0.2)]"
                                placeholder="<?php echo $current_question['placeholder']; ?>"
                                required><?php echo isset($_SESSION['responses'][$current_question['id']]) ? $_SESSION['responses'][$current_question['id']] : ''; ?></textarea>
                        <?php elseif (in_array($current_question['type'], ['number', 'email', 'date'])): ?>
                            <div class="w-full">
                                <input type="<?php echo $current_question['type']; ?>" name="response"
                                    class="w-full p-6 md:p-8 rounded-2xl md:rounded-3xl border-2 border-white/50 font-medium text-base md:text-xl text-slate-800 transition-all duration-300 bg-white/40 backdrop-blur-md shadow-[inner_0_2px_4px_rgba(0,0,0,0.05)] placeholder:text-slate-400 focus:outline-none focus:bg-white/90 focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 focus:shadow-[0_15px_30px_-10px_rgba(245,158,11,0.2)]"
                                    placeholder="<?php echo $current_question['placeholder']; ?>"
                                    value="<?php echo isset($_SESSION['responses'][$current_question['id']]) ? htmlspecialchars($_SESSION['responses'][$current_question['id']]) : ''; ?>"
                                    oninput="const btn = document.querySelector('.btn-next'); if(btn) { btn.disabled = (this.value.trim().length === 0); if(!btn.disabled) btn.classList.add('pulse'); else btn.classList.remove('pulse'); }"
                                    onchange="const btn = document.querySelector('.btn-next'); if(btn) { btn.disabled = (this.value.trim().length === 0); if(!btn.disabled) btn.classList.add('pulse'); else btn.classList.remove('pulse'); }"
                                    required>
                            </div>
                        <?php elseif ($current_question['type'] === 'select'): ?>
                            <div class="w-full">
                                <select name="response" 
                                    class="w-full p-6 md:p-8 rounded-2xl md:rounded-3xl border-2 border-white/50 font-medium text-base md:text-xl text-slate-800 transition-all duration-300 bg-white/40 backdrop-blur-md appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1.5rem_center] bg-[length:1.5em_1.5em] pr-12 focus:outline-none focus:bg-white/90 focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 shadow-sm" 
                                    onchange="const btn = document.querySelector('.btn-next'); if(btn) { btn.disabled = (this.value === ''); if(!btn.disabled) btn.classList.add('pulse'); else btn.classList.remove('pulse'); }"
                                    required>
                                    <option value=""><?php echo $current_question['placeholder'] ?: '-- Pilih Opsi --'; ?></option>
                                    <?php 
                                    $opts = explode(',', $current_question['options'] ?? '');
                                    foreach($opts as $o): 
                                        $o = trim($o);
                                        if($o === '') continue;
                                    ?>
                                        <option value="<?php echo htmlspecialchars($o); ?>" <?php echo (isset($_SESSION['responses'][$current_question['id']]) && $_SESSION['responses'][$current_question['id']] == $o) ? 'selected' : ''; ?>><?php echo htmlspecialchars($o); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($current_question['type'] === 'combobox'): ?>
                            <div class="w-full">
                                <select name="response" 
                                    class="select2-combobox w-full p-6 md:p-8 rounded-2xl md:rounded-3xl border-2 border-white/50 font-medium text-base md:text-xl text-slate-800 transition-all duration-300 bg-white/40 backdrop-blur-md focus:outline-none focus:bg-white/90 focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 shadow-sm" 
                                    onchange="const btn = document.querySelector('.btn-next'); if(btn) { btn.disabled = (this.value === ''); if(!btn.disabled) btn.classList.add('pulse'); else btn.classList.remove('pulse'); }"
                                    required>
                                    <option value=""><?php echo $current_question['placeholder'] ?: '-- Cari & Pilih Opsi --'; ?></option>
                                    <?php 
                                    $opts = explode(',', $current_question['options'] ?? '');
                                    foreach($opts as $o): 
                                        $o = trim($o);
                                        if($o === '') continue;
                                    ?>
                                        <option value="<?php echo htmlspecialchars($o); ?>" <?php echo (isset($_SESSION['responses'][$current_question['id']]) && $_SESSION['responses'][$current_question['id']] == $o) ? 'selected' : ''; ?>><?php echo htmlspecialchars($o); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($current_question['type'] === 'checkbox'): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                                <?php 
                                $opts = explode(',', $current_question['options'] ?? '');
                                $saved_responses = isset($_SESSION['responses'][$current_question['id']]) ? explode(', ', $_SESSION['responses'][$current_question['id']]) : [];
                                foreach($opts as $o): 
                                    $o = trim($o);
                                    if($o === '') continue;
                                    $is_checked = in_array($o, $saved_responses);
                                ?>
                                    <label class="group flex items-center gap-4 p-5 rounded-2xl border-2 border-white/50 bg-white/40 backdrop-blur-md cursor-pointer transition-all duration-300 hover:bg-white/60 hover:border-amber-300 <?php echo $is_checked ? 'border-amber-400 bg-white/80 shadow-md ring-2 ring-amber-400/20' : ''; ?>">
                                        <input type="checkbox" name="response[]" value="<?php echo htmlspecialchars($o); ?>" 
                                            class="w-6 h-6 rounded border-slate-300 text-amber-500 focus:ring-amber-500" 
                                            onchange="const checks = this.closest('.grid').querySelectorAll('input:checked'); const btn = document.querySelector('.btn-next'); if(btn) { btn.disabled = (checks.length === 0); if(!btn.disabled) btn.classList.add('pulse'); else btn.classList.remove('pulse'); }"
                                            <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <span class="text-lg font-bold text-slate-700 group-hover:text-amber-700 transition-colors"><?php echo htmlspecialchars($o); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($current_question['type'] !== 'rating'): ?>
                            <!-- Virtual Keyboard Toggle -->
                            <div class="flex justify-end mt-3">
                                <button type="button" id="toggleKeyboardBtn"
                                    class="flex items-center gap-2 px-5 py-2.5 bg-white/60 backdrop-blur-lg border-2 border-white/70 rounded-full text-sm font-bold text-slate-600 hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300 transition-all duration-300 shadow-sm">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <span id="toggleKeyboardLabel">Gunakan Keyboard Virtual</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Navigation Bar -->
                    <div class="flex flex-row flex-wrap sm:flex-nowrap justify-between items-center mt-8 md:mt-12 gap-4">
                        <?php if ($current_index > 0): ?>
                            <button type="submit" name="action" value="prev" formnovalidate
                                class="flex-1 sm:flex-none justify-center items-center gap-2 md:gap-3 py-3 md:py-4 px-6 md:px-10 rounded-full font-bold cursor-pointer transition-all duration-300 text-sm md:text-base bg-white/60 backdrop-blur-sm text-slate-700 border border-white/60 shadow-sm hover:bg-white">
                                <i class="fa-solid fa-chevron-left"></i>
                                KEMBALI
                            </button>
                        <?php else: ?>
                            <div class="hidden sm:block flex-1 sm:flex-none"></div>
                        <?php endif; ?>

                        <button type="submit" name="action"
                            value="<?php echo ($current_index === $total_questions - 1) ? 'submit' : 'next'; ?>"
                            class="btn-next flex-1 sm:flex-none justify-center flex items-center gap-2 md:gap-3 py-3 md:py-4 px-8 md:px-10 rounded-full font-bold cursor-pointer transition-all duration-300 text-sm md:text-base bg-amber-500 text-white shadow-[0_10px_20px_rgba(245,158,11,0.3)] disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed disabled:shadow-none hover:not-disabled:translate-x-2 hover:not-disabled:shadow-[0_15px_30px_rgba(245,158,11,0.5)]"
                            <?php echo isset($_SESSION['responses'][$current_question['id']]) ? '' : 'disabled'; ?>>
                            <?php echo ($current_index === $total_questions - 1) ? 'KIRIM SURVEI' : 'LANJUTKAN'; ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white/60 backdrop-blur-md border-t border-black/5 py-4 mt-auto">
        <div
            class="max-w-6xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center text-xs md:text-sm text-slate-500 font-medium">
            <div>© <?php echo date('Y'); ?> Survei Layanan Publik - <?php echo htmlspecialchars($instansi_name); ?>.
            </div>
            <div class="flex items-center gap-6 mt-3 md:mt-0">
                <a href="#" class="hover:text-amber-600 transition-colors hidden sm:block">Beranda</a>
                <a href="javascript:void(0)" id="openHelpBtn"
                    class="hover:text-amber-600 transition-colors hidden sm:block">Bantuan</a>
                <div class="flex gap-4 sm:ml-4 text-base">
                    <i class="fa-brands fa-facebook hover:text-blue-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-instagram hover:text-pink-600 cursor-pointer transition-colors"></i>
                    <i class="fa-brands fa-twitter hover:text-sky-500 cursor-pointer transition-colors"></i>
                </div>
            </div>
        </div>
    </footer>

    <!-- Help Modal -->
    <div id="helpModal"
        style="position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1rem; transition: all 0.3s ease;">
        <!-- Backdrop -->
        <div id="helpModalBackdrop"
            style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
        </div>

        <!-- Content Box -->
        <div id="helpModalContent"
            style="position: relative; z-index: 10; background: #ffffff; border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); width: 100%; max-width: 32rem; padding: 2.5rem; transform: scale(0.9); opacity: 0; transition: all 0.3s ease;">
            <!-- Close Icon Button -->
            <button id="closeHelpIconBtn"
                style="position: absolute; top: 1.5rem; right: 1.5rem; color: #94a3b8; border: none; background: transparent; cursor: pointer; padding: 0.5rem;">
                <i class="fa-solid fa-xmark text-2xl"></i>
            </button>

            <div style="text-align: center; margin-bottom: 2rem;">
                <div
                    style="width: 5rem; height: 5rem; background: #fffbeb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: #d97706; font-size: 1.875rem; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <h2 style="font-size: 1.875rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin: 0;">Panduan
                    Survei</h2>
                <p style="color: #64748b; font-weight: 500; margin-top: 0.5rem;">Cara mudah memberikan masukan Anda</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.5rem; text-align: left;">
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #fff7ed; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #f59e0b; flex-shrink: 0;">
                        <i class="fa-solid fa-face-smile"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem; line-height: 1.2;">Pilih Emoji Rating</h4>
                        <p style="color: #64748b; font-size: 0.8rem; margin: 0;">Klik salah satu gambar yang paling mewakili tingkat kepuasan Anda terhadap layanan kami.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #eff6ff; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #3b82f6; flex-shrink: 0;">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem; line-height: 1.2;">Pencarian & Pilihan</h4>
                        <p style="color: #64748b; font-size: 0.8rem; margin: 0;">Gunakan kotak pencarian untuk menemukan opsi yang sesuai pada pertanyaan daftar pilihan.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #f0fdf4; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #22c55e; flex-shrink: 0;">
                        <i class="fa-solid fa-square-check"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem; line-height: 1.2;">Pilihan Banyak</h4>
                        <p style="color: #64748b; font-size: 0.8rem; margin: 0;">Anda dapat memilih satu atau lebih jawaban yang tersedia sesuai dengan pengalaman Anda.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #fef2f2; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #ef4444; flex-shrink: 0;">
                        <i class="fa-solid fa-keyboard"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 0.25rem; line-height: 1.2;">Berikan Masukan</h4>
                        <p style="color: #64748b; font-size: 0.8rem; margin: 0;">Tuliskan saran atau keluhan Anda secara detail menggunakan keyboard fisik atau virtual.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1.25rem; align-items: flex-start; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0;">
                    <div style="width: 2.5rem; height: 2.5rem; background: #fafafa; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: #94a3b8; flex-shrink: 0;">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 700; color: #475569; font-size: 0.9rem; margin-bottom: 0.15rem; line-height: 1.2;">Kerahasiaan Terjamin</h4>
                        <p style="color: #94a3b8; font-size: 0.75rem; margin: 0;">Seluruh jawaban bersifat anonim dan hanya digunakan untuk perbaikan layanan kami.</p>
                    </div>
                </div>
            </div>

            <button id="closeHelpBtn"
                style="width: 100%; margin-top: 2.5rem; background: #0f172a; color: #ffffff; font-weight: 700; padding: 1rem; border: none; border-radius: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                SAYA MENGERTI
                <i class="fa-solid fa-check-circle"></i>
            </button>
        </div>
    </div>

    <!-- Virtual Keyboard -->
    <div id="virtualKeyboard" class="keyboard-container"></div>

    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>

</html>