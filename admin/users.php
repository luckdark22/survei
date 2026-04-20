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

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, username ASC");
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

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-10">
        <div class="px-8 py-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center bg-slate-50/50 gap-4">
            <h2 class="text-xl font-black text-slate-800 flex items-center gap-3 uppercase tracking-tight">
                <i class="fa-solid fa-users text-amber-500"></i>
                Daftar Pengelola Survei
            </h2>
            <button onclick="openAddModal()" class="w-full sm:w-auto px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-xs transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] flex justify-center items-center gap-2 uppercase tracking-widest">
                <i class="fa-solid fa-user-plus text-sm"></i> Tambah User Baru
            </button>
        </div>

        <div class="p-8">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-slate-400 text-[10px] uppercase tracking-[0.2em] font-black border-b border-slate-100">
                            <th class="px-4 py-4">Username</th>
                            <th class="px-4 py-4">Role</th>
                            <th class="px-4 py-4">Dibuat</th>
                            <th class="px-4 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($users as $u): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-4 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 font-bold group-hover:scale-110 transition-transform">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 tracking-tight"><?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="text-[10px] text-slate-400 uppercase font-black">USER ID: <?php echo $u['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-5">
                                    <span class="px-3 py-1 text-[10px] font-black rounded-full uppercase tracking-widest <?php echo $u['role'] === 'admin' ? 'bg-indigo-50 text-indigo-600' : 'bg-emerald-50 text-emerald-600'; ?>">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-5 text-sm text-slate-500 font-medium"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                                <td class="px-4 py-5 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick='editUser(<?php echo json_encode($u); ?>)' class="px-3 py-1.5 bg-slate-100 hover:bg-amber-500 hover:text-white text-slate-600 rounded-lg font-black text-[10px] uppercase tracking-widest transition-all">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="openDeleteModal(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')" class="px-3 py-1.5 bg-slate-100 hover:bg-rose-500 hover:text-white text-slate-600 rounded-lg font-black text-[10px] uppercase tracking-widest transition-all">
                                                <i class="fa-solid fa-trash-can"></i>
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
    </div>
</main>

<!-- Add/Edit User Modal -->
<div id="userModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="relative bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden animate-[fadeInScale_0.3s_ease-out]">
        <div id="modalHeader" class="px-8 py-6 bg-amber-50 border-b border-slate-100 flex justify-between items-center">
            <h3 id="modalTitle" class="text-lg font-black text-amber-800 uppercase tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Tambah User
            </h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fa-solid fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-8 flex flex-col gap-6 font-sans">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="user_id">

            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Username</label>
                <input type="text" name="username" id="field_username" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 transition-all" required>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Password</label>
                <input type="password" name="password" id="field_password" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 transition-all" placeholder="Kosongkan jika tidak ingin diubah (saat edit)">
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest pl-1">Role Akses</label>
                <select name="role" id="field_role" class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none font-bold text-slate-700 transition-all appearance-none cursor-pointer">
                    <option value="staff">Staff (Hanya kelola event sendiri)</option>
                    <option value="admin">Admin (Kelola semua & user)</option>
                </select>
            </div>

            <div class="flex gap-4 mt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-black rounded-2xl transition-all uppercase tracking-widest text-xs border border-slate-200">
                    Batal
                </button>
                <button type="submit" id="submitBtn" class="flex-[2] bg-amber-500 hover:bg-amber-600 text-white font-black py-4 rounded-2xl transition-all shadow-[0_10px_20px_rgba(245,158,11,0.2)] uppercase tracking-widest text-xs">
                    Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
    <div class="relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl p-10 text-center animate-[fadeInScale_0.3s_ease-out]">
        <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-900 mb-2 tracking-tight">Hapus User?</h3>
        <p class="text-slate-500 text-sm mb-10 font-medium">Username <span id="deleteItemName" class="font-bold text-slate-800"></span> tidak akan bisa login lagi.</p>
        <div class="flex flex-col gap-3">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteItemId">
                <button type="submit" class="w-full py-4 bg-rose-500 hover:bg-rose-600 text-white font-black rounded-2xl transition-all shadow-[0_8px_20px_rgba(244,63,94,0.3)] uppercase tracking-widest text-xs">
                    Ya, Hapus Permanen
                </button>
            </form>
            <button type="button" onclick="closeDeleteModal()" class="w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-2xl transition-all uppercase tracking-widest text-xs">
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
        document.getElementById('userModal').classList.remove('hidden');
        document.getElementById('userModal').classList.add('flex');
    }

    function editUser(user) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('user_id').value = user.id;
        document.getElementById('field_username').value = user.username;
        document.getElementById('field_role').value = user.role;
        document.getElementById('field_password').required = false;
        document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-user-gear"></i> Edit User';
        document.getElementById('submitBtn').innerText = 'Simpan Perubahan';
        document.getElementById('userModal').classList.remove('hidden');
        document.getElementById('userModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('userModal').classList.add('hidden');
        document.getElementById('userModal').classList.remove('flex');
    }

    function openDeleteModal(id, name) {
        document.getElementById('deleteItemId').value = id;
        document.getElementById('deleteItemName').innerText = name;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }
</script>

<style>
    @keyframes fadeInScale {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
