<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkAuth();

// ONLY ADMIN can access this page
if (!isAdmin()) {
    header("Location: ./");
    exit;
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';

            if ($username && $password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                try {
                    $stmt->execute([$username, $hashed, $role]);
                    $_SESSION['success'] = "User $username berhasil ditambahkan!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Gagal menambah user: Username mungkin sudah ada.";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';

            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $hashed, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $id]);
            }
            $_SESSION['success'] = "User $username berhasil diperbarui!";
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            // Prevent deleting self
            if ($id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "User berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Anda tidak bisa menghapus akun Anda sendiri!";
            }
        }
        header("Location: users");
        exit;
    }
}

// Pagination & Search Logic
$search = trim($_GET['search'] ?? '');
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

$where_clause = "";
$params = [];
if ($search) {
    $where_clause = " WHERE username LIKE ?";
    $params[] = "%$search%";
}

// Count total users
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM users $where_clause");
$stmt_count->execute($params);
$total_count = $stmt_count->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// Fetch users with pagination and search
$sql = "SELECT * FROM users $where_clause ORDER BY role ASC, username ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();
?>

<?php
$page_title = "Manajemen User";
$page_icon = "fa-users-gear";
require_once 'includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-[fadeInScale_0.3s_ease-out]">
            <i class="fa-solid fa-circle-check text-xl"></i>
            <span class="font-bold"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-100 text-red-600 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-[fadeInScale_0.3s_ease-out]">
            <i class="fa-solid fa-circle-exclamation text-xl"></i>
            <span class="font-bold"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-10">
        <div class="px-6 py-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-slate-50/50 gap-4">
            <div class="flex items-center gap-4 w-full md:w-auto">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-users text-amber-500"></i>
                    Manajemen User
                </h2>
                <div style="position: relative; width: 100%; max-width: 256px;">
                    <form method="GET">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 11px; pointer-events: none;"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari username..." 
                               style="width: 100%; padding: 10px 16px 10px 40px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 12px; font-weight: 500; color: #1e293b; outline: none; transition: all 0.2s;"
                               onfocus="this.style.borderColor='#f59e0b'; this.style.boxShadow='0 0 0 3px rgba(245, 158, 11, 0.1)';"
                               onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </form>
                </div>
            </div>
            <button onclick="openAddModal()" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-[10px] transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2 uppercase tracking-widest">
                <i class="fa-solid fa-user-plus text-xs"></i> Tambah User
            </button>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 font-bold border-b border-slate-200">Username</th>
                            <th class="px-4 py-3 font-bold border-b border-slate-200">Role Akses</th>
                            <th class="px-4 py-3 font-bold border-b border-slate-200 text-center">Dibuat</th>
                            <th class="px-4 py-3 font-bold border-b border-slate-200 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach($users as $u): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 font-bold group-hover:scale-110 transition-transform">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 tracking-tight"><?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="text-[10px] text-slate-400 uppercase font-black">USER ID: <?php echo $u['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 text-[10px] font-black rounded-full uppercase tracking-widest <?php echo $u['role'] === 'admin' ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100'; ?>">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center text-slate-500 font-medium"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick='editUser(<?php echo json_encode($u); ?>)' class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-black text-[11px] transition-all flex items-center gap-1.5 uppercase tracking-wider">
                                            <i class="fa-solid fa-pen-to-square"></i> <span>EDIT</span>
                                        </button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="openDeleteModal(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg font-bold text-[11px] transition-all shadow-sm flex items-center gap-1.5 uppercase tracking-wider">
                                                <i class="fa-solid fa-trash-can"></i> <span>HAPUS</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-xs text-slate-500 font-medium">
                    Menampilkan <span class="text-slate-800"><?php echo count($users); ?></span> dari <span class="text-slate-800"><?php echo $total_count; ?></span> user
                </div>
                    <div class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $query_str; ?>" 
                               style="display: flex; align-items: center; justify-center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; text-decoration: none; transition: all 0.2s; font-size: 10px;">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo $query_str; ?>" 
                               style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid <?php echo $i === $page ? '#f59e0b' : '#e2e8f0'; ?>; background: <?php echo $i === $page ? '#f59e0b' : 'white'; ?>; color: <?php echo $i === $page ? 'white' : '#475569'; ?>; text-decoration: none; transition: all 0.2s; font-size: 13px; font-weight: <?php echo $i === $page ? '900' : '700'; ?>; box-shadow: <?php echo $i === $page ? '0 10px 15px -3px rgba(245, 158, 11, 0.3)' : 'none'; ?>;">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $query_str; ?>" 
                               style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; text-decoration: none; transition: all 0.2s; font-size: 10px;">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit User Modal -->
<div id="userModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;">
    <div style="position: relative; background: white; width: 100%; max-width: 500px; border-radius: 30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; animation: fadeInScale 0.3s ease-out;">
        <div id="modalHeader" style="padding: 24px 32px; background: #fffbeb; border-bottom: 1px solid #fef3c7; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalTitle" style="margin: 0; font-size: 18px; font-weight: 900; color: #92400e; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-user-plus"></i> Tambah User
            </h3>
            <button onclick="closeModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; transition: color 0.2s;">
                <i class="fa-solid fa-times style='font-size: 20px;'"></i>
            </button>
        </div>
        <form method="POST" style="padding: 32px; display: flex; flex-direction: column; gap: 24px;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="user_id">

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; padding-left: 4px;">Username</label>
                <input type="text" name="username" id="field_username" style="width: 100%; padding: 14px 20px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 16px; font-weight: 700; color: #1e293b; outline: none; transition: all 0.2s;" required>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; padding-left: 4px;">Password</label>
                <input type="password" name="password" id="field_password" style="width: 100%; padding: 14px 20px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 16px; font-weight: 700; color: #1e293b; outline: none; transition: all 0.2s;" placeholder="Kosongkan jika tidak ingin diubah">
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; padding-left: 4px;">Role Akses</label>
                <select name="role" id="field_role" style="width: 100%; padding: 14px 20px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 16px; font-weight: 700; color: #1e293b; outline: none; transition: all 0.2s; cursor: pointer;">
                    <option value="staff">Staff (Hanya kelola event sendiri)</option>
                    <option value="admin">Admin (Kelola semua & user)</option>
                </select>
            </div>

            <div style="display: flex; gap: 16px; margin-top: 16px;">
                <button type="button" onclick="closeModal()" style="flex: 1; padding: 16px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; font-weight: 900; border-radius: 16px; text-transform: uppercase; letter-spacing: 0.1em; font-size: 12px; cursor: pointer;">
                    Batal
                </button>
                <button type="submit" id="submitBtn" style="flex: 2; padding: 16px; background: #f59e0b; color: white; border: none; font-weight: 900; border-radius: 16px; text-transform: uppercase; letter-spacing: 0.1em; font-size: 12px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.3);">
                    Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; width: 100%; max-width: 400px; border-radius: 40px; padding: 40px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: fadeInScale 0.3s ease-out;">
        <div style="width: 80px; height: 80px; background: #fff1f2; color: #f43f5e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 32px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 style="font-size: 24px; font-weight: 900; color: #0f172a; margin-bottom: 8px; letter-spacing: -0.02em;">Hapus User?</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 40px; font-weight: 500;">Username <span id="deleteItemName" style="font-weight: 900; color: #0f172a;"></span> tidak akan bisa login lagi.</p>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteItemId">
                <button type="submit" style="width: 100%; padding: 16px; background: #f43f5e; color: white; border: none; font-weight: 900; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.1em; font-size: 12px; cursor: pointer; box-shadow: 0 8px 20px rgba(244, 63, 94, 0.3);">
                    Ya, Hapus Permanen
                </button>
            </form>
            <button type="button" onclick="closeDeleteModal()" style="width: 100%; padding: 16px; background: #f1f5f9; border: none; color: #475569; font-weight: 900; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.1em; font-size: 12px; cursor: pointer;">
                Batal
            </button>
        </div>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-plus"></i> Tambah User';
        document.getElementById('submitBtn').innerText = 'Simpan User Baru';
        document.getElementById('field_password').required = true;
        document.getElementById('userModal').style.display = 'flex';
    }

    function editUser(user) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('user_id').value = user.id;
        document.getElementById('field_username').value = user.username;
        document.getElementById('field_role').value = user.role;
        document.getElementById('field_password').required = false;
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-gear"></i> Edit User';
        document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
        document.getElementById('userModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    function openDeleteModal(id, name) {
        document.getElementById('deleteItemId').value = id;
        document.getElementById('deleteItemName').innerText = name;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
</script>

<style>
    @keyframes fadeInScale {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
