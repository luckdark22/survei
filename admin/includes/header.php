<?php
// admin/includes/header.php
$current_page = basename($_SERVER['PHP_SELF'], ".php");

function isActive($page, $current) {
    return $page === $current ? 'active' : '';
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
    <style>
        :root {
            --sidebar-width: 288px;
            --sidebar-collapsed-width: 80px;
            --sidebar-bg: #0f172a;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        
        /* Sidebar Link Styling */
        .sidebar-link {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            white-space: nowrap;
        }
        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%);
            color: #f59e0b;
            box-shadow: inset 4px 0 0 0 #f59e0b;
        }
        .sidebar-link.active i {
            color: #f59e0b;
            filter: drop-shadow(0 0 8px rgba(245, 158, 11, 0.4));
        }

        /* Collapsible Sidebar Logic */
        #sidebar { width: var(--sidebar-width); transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); background-color: var(--sidebar-bg) !important; }
        #sidebar.is-collapsed { width: var(--sidebar-collapsed-width); }
        #sidebar.is-collapsed .sidebar-text, 
        #sidebar.is-collapsed .sidebar-label,
        #sidebar.is-collapsed .branding-text { 
            display: none; 
        }
        #sidebar.is-collapsed .nav-section { padding-left: 0; padding-right: 0; display: flex; flex-direction: column; align-items: center; }
        #sidebar.is-collapsed .sidebar-link { justify-content: center; padding-left: 0; padding-right: 0; width: 48px; height: 48px; margin: 4px auto; }
        #sidebar.is-collapsed .sidebar-link i { width: auto; font-size: 1.1rem; }
        #sidebar.is-collapsed .branding-container { padding-left: 0; padding-right: 0; justify-content: center; }
        #sidebar.is-collapsed .profile-container { padding: 1rem; flex-direction: column; align-items: center; text-align: center; }
        #sidebar.is-collapsed .profile-info { display: none; }
        
        /* Layout Fixes */
        .content-area { 
            flex: 1; 
            min-width: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 10;
        }
        
        @media (min-width: 1024px) {
            .content-area { padding-left: var(--sidebar-width) !important; }
            .content-area.content-collapsed { padding-left: var(--sidebar-collapsed-width) !important; }
            #sidebar.is-collapsed { width: var(--sidebar-collapsed-width) !important; }
            #mobileSidebarClose { display: none !important; }
        }

        #sidebar { z-index: 100 !important; }
        #sidebarOverlay { z-index: 90 !important; }

        /* Ensure mobile toggle is hidden on desktop */
        @media (min-width: 1024px) {
            .mobile-only-toggle { display: none !important; }
        }
        
        /* Prevent links from being non-clickable */
        .sidebar-link { pointer-events: auto !important; cursor: pointer !important; z-index: 101 !important; }
    </style>
</head>
<body class="bg-slate-50 font-sans">

    <div class="flex min-h-screen relative overflow-hidden">
        
        <!-- Sidebar Overlay (Mobile Only) -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[40] hidden transition-opacity duration-300 opacity-0"></div>

        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-[50] text-slate-300 flex flex-col transform -translate-x-full transition-all duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto shadow-2xl lg:shadow-none shrink-0" style="height: 100vh;">
            
            <!-- Branding & Toggle -->
            <div class="px-6 py-8 flex items-center justify-between border-b border-slate-800/50 branding-container shrink-0">
                <div class="flex items-center gap-3 branding-content">
                    <div class="bg-amber-500 p-2.5 rounded-xl shrink-0 shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                        <i class="fa-solid fa-square-poll-vertical text-xl text-slate-900"></i>
                    </div>
                    <div class="branding-text truncate">
                        <h1 class="font-black text-white text-lg leading-none tracking-tight uppercase">Survei <span class="text-amber-500">Kiosk</span></h1>
                        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-1">Admin V2</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Mobile Close Button -->
                    <button id="mobileSidebarClose" class="lg:hidden p-2 text-slate-500 hover:text-rose-500 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                    <!-- Collapse Toggle (Desktop only) -->
                    <button id="collapseToggle" class="hidden lg:flex items-center justify-center w-8 h-8 text-slate-500 hover:text-amber-500 hover:bg-slate-800 rounded-lg transition-all border border-slate-800/50">
                        <i id="collapseIcon" class="fa-solid fa-angles-left text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1 custom-scrollbar nav-section bg-slate-900/50">
                <p class="px-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4 opacity-50 sidebar-label">Menu Utama</p>
                
                <a href="./" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('index', $current_page); ?>" title="Dashboard">
                    <i class="fa-solid fa-chart-pie" style="width: 20px; text-align: center;"></i> 
                    <span class="sidebar-text">Dashboard</span>
                </a>
                
                <a href="events" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('events', $current_page); ?>" title="Kelola Event">
                    <i class="fa-solid fa-calendar-days" style="width: 20px; text-align: center;"></i> 
                    <span class="sidebar-text">Kelola Event</span>
                </a>
                
                <a href="questions" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('questions', $current_page); ?>" title="Pertanyaan">
                    <i class="fa-solid fa-list-check" style="width: 20px; text-align: center;"></i> 
                    <span class="sidebar-text">Pertanyaan</span>
                </a>
                
                <a href="sessions" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('sessions', $current_page); ?>" title="Data Responden">
                    <i class="fa-solid fa-users" style="width: 20px; text-align: center;"></i> 
                    <span class="sidebar-text">Data Responden</span>
                </a>

                <?php if (isAdmin()): ?>
                    <div class="pt-6 pb-2 nav-section">
                        <p class="px-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4 opacity-50 sidebar-label">Administrasi</p>
                        <a href="users" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('users', $current_page); ?>" title="Manajemen User">
                            <i class="fa-solid fa-users-gear" style="width: 20px; text-align: center;"></i> 
                            <span class="sidebar-text">Manajemen User</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="pt-6 pb-2 nav-section">
                    <p class="px-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4 opacity-50 sidebar-label">Konfigurasi</p>
                    <a href="settings" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl font-bold <?php echo isActive('settings', $current_page); ?>" title="Setelan Sistem">
                        <i class="fa-solid fa-gears" style="width: 20px; text-align: center;"></i> 
                        <span class="sidebar-text">Setelan Sistem</span>
                    </a>
                </div>
            </nav>

            <!-- Bottom Section -->
            <div class="p-4 bg-slate-950 border-t border-slate-800/50 shrink-0">
                <!-- Profile -->
                <div class="flex items-center gap-3 profile-container bg-slate-900/50 p-2 rounded-xl border border-slate-800/50">
                    <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-amber-500 font-black shrink-0 shadow-inner">
                        <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0 profile-info">
                        <p class="text-xs font-black text-white truncate"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'User'); ?></p>
                        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter"><?php echo $_SESSION['user_role'] ?? 'staff'; ?></p>
                    </div>
                </div>
                
                <a href="logout" class="mt-4 flex items-center justify-center gap-2 w-full py-3 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl text-[10px] font-black transition-all uppercase tracking-[0.2em] border border-red-500/20" title="Logout">
                    <i class="fa-solid fa-power-off"></i> <span class="sidebar-text">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div id="mainContentArea" class="content-area flex flex-col h-screen overflow-hidden">
            
            <!-- Top Utility Bar -->
            <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 lg:px-8 z-[60] shrink-0">
                <div class="flex items-center gap-4">
                    <button id="mobileSidebarToggle" class="mobile-only-toggle p-2 text-slate-500 hover:text-amber-500 transition-colors bg-slate-100 rounded-lg">
                        <i class="fa-solid fa-bars-staggered text-xl"></i>
                    </button>
                    <div class="flex items-center gap-2 text-slate-400 text-[10px] font-black uppercase tracking-wider">
                        <span class="hidden sm:inline">Admin</span>
                        <i class="fa-solid fa-chevron-right text-[8px] opacity-40 hidden sm:inline"></i>
                        <span class="text-slate-800"><?php echo $page_title ?? 'Dashboard'; ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Notification Bell -->
                    <div class="relative" id="notifDropdownWrapper">
                        <button id="notifBellBtn" class="p-2.5 text-slate-400 hover:text-amber-500 transition-all relative outline-none bg-slate-50 rounded-xl border border-slate-100 hover:border-amber-200">
                            <i class="fa-solid fa-bell"></i>
                            <span id="notifBadge" class="hidden absolute top-0 right-0 w-4 h-4 bg-red-500 text-white text-[8px] font-black flex items-center justify-center rounded-full border-2 border-white shadow-sm">0</span>
                        </button>
                        <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-slate-200 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] z-[100] overflow-hidden animate-[slideDown_0.2s_ease-out]">
                            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                                <span class="text-[11px] font-black text-slate-800 uppercase tracking-widest">Survei Terbaru</span>
                                <span id="notifCountText" class="text-[9px] font-black text-amber-600 bg-amber-50 px-2 py-1 rounded-full">0 Baru</span>
                            </div>
                            <div id="notifList" class="max-h-96 overflow-y-auto custom-scrollbar">
                                <div class="p-10 text-center text-slate-400">
                                    <i class="fa-solid fa-inbox text-3xl mb-3 opacity-20"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-tight">Kosong</p>
                                </div>
                            </div>
                            <a href="sessions" class="block py-4 text-center text-[10px] font-black text-slate-500 hover:text-amber-600 uppercase tracking-widest bg-slate-50/50 hover:bg-slate-100 transition-colors border-t border-slate-100">
                                Lihat Semua <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 mx-1 hidden sm:block"></div>
                    <div class="hidden sm:flex items-center gap-3 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-right">
                            <p class="text-[11px] font-black text-slate-800 leading-none"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'User'); ?></p>
                            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-1"><?php echo $_SESSION['user_role'] ?? 'staff'; ?></p>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-amber-500 text-slate-900 flex items-center justify-center shadow-lg shadow-amber-500/20">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area (Scrollable Wrapper) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                <main class="flex-1">

    <div id="toastContainer" style="position:fixed; top:2rem; right:2rem; z-index:999; display:flex; flex-direction:column; gap:0.75rem; align-items:flex-end; pointer-events:none;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // SIDEBAR LOGIC
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileToggle = document.getElementById('mobileSidebarToggle');
            const mobileClose = document.getElementById('mobileSidebarClose');
            const collapseToggle = document.getElementById('collapseToggle');
            const collapseIcon = document.getElementById('collapseIcon');
            const contentArea = document.getElementById('mainContentArea');

            // Apply saved state on load
            if (localStorage.getItem('sidebar_collapsed') === 'true') {
                sidebar.classList.add('is-collapsed');
                if (contentArea) contentArea.classList.add('content-collapsed');
                if (collapseIcon) {
                    collapseIcon.classList.remove('fa-angles-left');
                    collapseIcon.classList.add('fa-angles-right');
                }
            }

            function toggleCollapse() {
                const isCollapsed = sidebar.classList.toggle('is-collapsed');
                if (contentArea) contentArea.classList.toggle('content-collapsed', isCollapsed);
                
                localStorage.setItem('sidebar_collapsed', isCollapsed);
                
                if (collapseIcon) {
                    if (isCollapsed) {
                        collapseIcon.classList.remove('fa-angles-left');
                        collapseIcon.classList.add('fa-angles-right');
                    } else {
                        collapseIcon.classList.remove('fa-angles-right');
                        collapseIcon.classList.add('fa-angles-left');
                    }
                }
            }

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => {
                    overlay.style.opacity = '1';
                }, 10);
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 300);
            }

            if (mobileToggle) mobileToggle.addEventListener('click', (e) => {
                e.preventDefault();
                openSidebar();
            });
            if (mobileClose) mobileClose.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);
            if (collapseToggle) collapseToggle.addEventListener('click', toggleCollapse);
        });

        // NOTIFICATIONS Logic
        const notifBellBtn = document.getElementById('notifBellBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifBellBtn) {
            notifBellBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => notifDropdown.classList.add('hidden'));
            notifDropdown.addEventListener('click', (e) => e.stopPropagation());
        }

        function checkNotifications() {
            fetch('api/notifications.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    const countText = document.getElementById('notifCountText');
                    const list = document.getElementById('notifList');
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.classList.remove('hidden');
                        countText.textContent = data.unread_count + ' Baru';
                        if (data.latest.length > 0) {
                            list.innerHTML = data.latest.map(n => `
                                <a href="sessions" class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 transition-colors border-b border-slate-50 group">
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 group-hover:bg-amber-100 flex items-center justify-center text-slate-400 group-hover:text-amber-600 shrink-0 transition-all border border-slate-100">
                                        <i class="fa-solid fa-file-invoice text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[11px] font-black text-slate-800 leading-tight mb-1 truncate">${n.event_name || 'Umum'}</p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-tighter">${n.created_at}</span>
                                        </div>
                                    </div>
                                </a>
                            `).join('');
                        }
                    } else {
                        badge.classList.add('hidden');
                        countText.textContent = '0 Baru';
                        list.innerHTML = `<div class="p-10 text-center text-slate-400 italic text-[10px] uppercase font-bold tracking-widest opacity-30">Kosong</div>`;
                    }
                })
                .catch(err => console.error("Notif Error:", err));
        }
        checkNotifications();
        setInterval(checkNotifications, 30000);

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const colors = { success: '#10b981', error: '#f43f5e' };
            toast.style.cssText = `background:#0f172a; border-left:4px solid ${colors[type]}; padding:1rem; border-radius:0.75rem; color:white; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:0.05em; min-width:280px; pointer-events:auto; box-shadow:0 10px 40px rgba(0,0,0,0.3); animation: toastIn 0.4s ease forwards;`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'toastOut 0.4s ease forwards';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }
        const st = document.createElement('style'); st.textContent = `@keyframes toastIn { from { transform: translateX(120%); } to { transform: translateX(0); } } @keyframes toastOut { from { transform: translateX(0); } to { transform: translateX(120%); } }`;
        document.head.appendChild(st);
        <?php if (isset($_SESSION['success'])): ?> window.addEventListener('load', () => showToast("<?php echo $_SESSION['success']; ?>", 'success')); <?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?> window.addEventListener('load', () => showToast("<?php echo $_SESSION['error']; ?>", 'error')); <?php unset($_SESSION['error']); endif; ?>
    </script>
