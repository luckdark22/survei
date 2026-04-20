# Sistem Survei Kiosk Multi-Tenant

Sistem survei modern berbasis web yang dirancang untuk penggunaan di kiosk layanan publik atau event. Memungkinkan administrator untuk mengelola banyak event survei dengan isolasi data yang aman (Multi-Tenancy).

## 🚀 Fitur Unggulan

- **Multi-Tenant / Multi-Event**: Pengelola dapat mendaftarkan banyak event (misal: GIIAS, Layanan PTSP, dsb) secara bersamaan.
- **Role-Based Access Control (RBAC)**:
  - **Admin**: Akses penuh ke semua data dan manajemen user.
  - **Staff**: Hanya dapat mengelola event dan pertanyaan yang mereka miliki.
- **Dashboard Analitik**: Visualisasi data real-time menggunakan Chart.js (Doughnut & Line Chart).
- **Kiosk Mode**: Antarmuka survei yang bersih, responsif, dan ramah sentuhan (touch-friendly).
- **Smart Migration**: Sistem database yang otomatis melakukan upgrade skema tanpa menghapus data lama.
- **Export Data**: Ekspor hasil survei ke format CSV (Excel) dan laporan premium dalam format PDF.
- **Pagination & Search**: Navigasi data yang cepat dan pencarian pintar di seluruh modul admin.

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 8.x (Native) dengan PDO (MySQL/MariaDB).
- **Frontend**: HTML5, Vanilla JavaScript, TailwindCSS (via CDN).
- **Charts**: Chart.js untuk visualisasi data.
- **Icon**: FontAwesome 6 Pro.
- **Styling**: Custom CSS untuk efek Glassmorphism dan Premium UI.

## 📦 Instalasi

1. **Clone Repository**:
   ```bash
   git clone <repository-url>
   ```

2. **Konfigurasi Database**:
   - Salin file `.env.example` menjadi `.env`.
   - Sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.

3. **Inisialisasi Database**:
   Jalankan skrip migrasi melalui terminal atau akses via browser:
   ```bash
   php database/migrate.php
   ```

4. **Login Default**:
   - **Username**: `admin`
   - **Password**: `admin123` (Disarankan segera diubah setelah login).

## 📄 Lisensi
Sistem ini dikembangkan secara eksklusif untuk kebutuhan survei layanan pelanggan yang efisien dan profesional.
