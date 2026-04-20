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
                    <?php if (isAdmin()): ?>
                        <a href="users" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('users', $current_page); ?>">User</a>
                    <?php endif; ?>
                    <a href="settings" class="px-3 py-2 text-sm font-bold transition-colors rounded-lg <?php echo isActive('settings', $current_page); ?>">Pengaturan</a>
                    
                    <div class="h-6 w-px bg-slate-200 mx-2"></div>
                    
                    <span class="text-xs font-bold text-slate-400 hidden lg:block">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'User'); ?> 
                        <span class="bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full ml-1 text-[10px] uppercase"><?php echo $_SESSION['user_role'] ?? 'staff'; ?></span>
                    </span>
                    <a href="logout" class="text-sm font-bold text-red-500 hover:text-red-700 transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notification Container -->
    <div id="toastContainer" style="position:fixed; top:2rem; right:2rem; z-index:120; display:flex; flex-direction:column; gap:0.75rem; align-items:flex-end; pointer-events:none;"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            
            const colors = {
                success: { border: '#10b981', icon: 'fa-circle-check', iconColor: '#34d399' },
                error:   { border: '#f43f5e', icon: 'fa-triangle-exclamation', iconColor: '#fb7185' }
            };
            
            const s = colors[type] || colors.success;
            
            toast.style.cssText = 'background:#0f172a; border:1px solid ' + s.border + '; padding:1rem 1.5rem; border-radius:9999px; display:flex; align-items:center; gap:1rem; min-width:320px; pointer-events:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); font-size:12px;';
            toast.className = 'toast-animate-in';
            toast.innerHTML = '<div style="width:1.75rem; height:1.75rem; background:rgba(255,255,255,0.1); border-radius:9999px; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:' + s.iconColor + ';"><i class="fa-solid ' + s.icon + '"></i></div><div style="flex:1; text-align:center; color:white; font-weight:900; text-transform:uppercase; letter-spacing:0.1em; line-height:1;">' + message + '</div>';
            
            container.appendChild(toast);
            
            setTimeout(function() {
                toast.classList.add('toast-animate-out');
                setTimeout(function() { toast.remove(); }, 400);
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
