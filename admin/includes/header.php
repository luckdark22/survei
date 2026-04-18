<?php
// admin/includes/header.php
$current_page = basename($_SERVER['PHP_SELF'], ".php");

function isActive($page, $current) {
    return $page === $current ? 'text-amber-600 bg-amber-50' : 'text-slate-500 hover:text-amber-600';
}

// Fetch Global Settings
$stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'instansi_name'");
$stmt_settings->execute();
$global_instansi_name = $stmt_settings->fetchColumn() ?: 'Direktorat Inovasi & Layanan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - Survei Kiosk</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body class="bg-slate-50 font-sans min-h-screen">

    <!-- Top Navigation -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-100 p-2 rounded-lg text-amber-600">
                        <i class="fa-solid <?php echo $page_icon ?? 'fa-chart-pie'; ?>"></i>
                    </div>
                    <span class="font-bold text-xl text-slate-800"><?php echo $page_title ?? 'Admin Dashboard'; ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="./" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('index', $current_page); ?>">Dashboard</a>
                    <a href="events" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('events', $current_page); ?>">Event</a>
                    <a href="questions" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('questions', $current_page); ?>">Pertanyaan</a>
                    <a href="sessions" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('sessions', $current_page); ?>">Responden</a>
                    <a href="settings" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('settings', $current_page); ?>">Pengaturan</a>
                    
                    <div class="h-6 w-px bg-slate-200 mx-2"></div>
                    
                    <span class="text-xs font-bold text-slate-400 hidden lg:block">Admin: <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'User'); ?></span>
                    <a href="logout" class="text-sm font-bold text-red-500 hover:text-red-700 transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-8 right-8 z-[110] flex flex-col gap-3 items-end pointer-events-none" style="left: auto !important; right: 2rem !important;"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            
            const config = {
                success: {
                    bg: 'bg-slate-900',
                    border: 'border-emerald-500/50',
                    icon: 'fa-circle-check',
                    iconColor: 'text-emerald-400',
                    shadow: 'shadow-2xl shadow-emerald-500/20'
                },
                error: {
                    bg: 'bg-slate-900',
                    border: 'border-rose-500/50',
                    icon: 'fa-triangle-exclamation',
                    iconColor: 'text-rose-400',
                    shadow: 'shadow-2xl shadow-rose-500/20'
                }
            };
            
            const s = config[type] || config.success;
            
            toast.className = `${s.bg} ${s.border} ${s.shadow} px-6 py-4 rounded-full border flex items-center gap-4 toast-animate-in font-black text-xs min-w-[320px] pointer-events-auto`;
            toast.innerHTML = `
                <div class="w-7 h-7 bg-white/10 rounded-full flex items-center justify-center text-sm shrink-0 ${s.iconColor}">
                    <i class="fa-solid ${s.icon}"></i>
                </div>
                <div class="flex-1 text-center text-white font-black uppercase tracking-[0.1em] leading-none">${message}</div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('toast-animate-out');
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }

        // Animation Styles
        const style = document.createElement('style');
        style.textContent = `
            .toast-animate-in {
                animation: toastIn 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards;
            }
            .toast-animate-out {
                animation: toastOut 0.4s ease-in forwards;
            }
            @keyframes toastIn { 
                from { transform: translateY(-100%); opacity: 0; } 
                to { transform: translateY(0); opacity: 1; } 
            }
            @keyframes toastOut { 
                from { transform: translateY(0) scale(1); opacity: 1; } 
                to { transform: translateY(-20px) scale(0.9); opacity: 0; } 
            }
        `;
        document.head.appendChild(style);

        <?php if (isset($_SESSION['success'])): ?>
            window.addEventListener('load', () => showToast("<?php echo $_SESSION['success']; ?>", 'success'));
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            window.addEventListener('load', () => showToast("<?php echo $_SESSION['error']; ?>", 'error'));
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
