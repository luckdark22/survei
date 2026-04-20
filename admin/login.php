<?php
require_once '../includes/db.php';
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ./");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role']; // e.g., 'admin' or 'staff'
        header("Location: ./");
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Survei Kiosk</title>
    <!-- Tailwind Generated CSS -->
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>
<body class="bg-gradient-to-br from-amber-50 to-amber-100 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white/60 backdrop-blur-xl border border-white/50 rounded-3xl p-10 shadow-[0_20px_40px_rgba(0,0,0,0.05)] relative overflow-hidden">
        
        <!-- Decorative Glow -->
        <div class="absolute -top-20 -right-20 w-48 h-48 bg-amber-400 rounded-full mix-blend-multiply filter blur-3xl opacity-30"></div>
        <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-yellow-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30"></div>

        <div class="relative z-10 text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-amber-400 to-amber-500 text-white rounded-2xl shadow-inner mb-6">
                <i class="fa-solid fa-user-shield text-3xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Admin Portal</h1>
            <p class="text-slate-500 font-medium mt-2">Masuk untuk mengelola hasil survei.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-3 border border-red-100">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="relative z-10 flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <label for="username" class="text-xs font-bold text-slate-500 uppercase tracking-widest pl-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <input type="text" id="username" name="username" class="w-full pl-11 pr-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-amber-400/20 focus:border-amber-400 transition-all text-slate-700 font-medium placeholder-slate-400" placeholder="admin" required>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-xs font-bold text-slate-500 uppercase tracking-widest pl-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <input type="password" id="password" name="password" class="w-full pl-11 pr-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-amber-400/20 focus:border-amber-400 transition-all text-slate-700 font-medium placeholder-slate-400" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="mt-4 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-4 rounded-xl shadow-[0_10px_20px_rgba(245,158,11,0.2)] hover:shadow-[0_15px_30px_rgba(245,158,11,0.4)] transition-all duration-300 transform hover:-translate-y-1">
                Masuk ke Dashboard
            </button>
        </form>

        <div class="mt-8 text-center relative z-10">
            <a href="../" class="text-sm font-semibold text-slate-400 hover:text-amber-600 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Layar Kiosk
            </a>
        </div>
    </div>

</body>
</html>
