<?php
session_start();
// Cek sesi login
if (!isset($_SESSION['username']) || ($_SESSION['level']) != 'admin') {
    header("Location: index.php");
    exit();
}

// Koneksi database
$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
require_once 'helper_katalog.php';

// Hitung statistik otomatis dari database
$total_buku      = $conn->query("SELECT COUNT(*) AS c FROM buku")->fetch_assoc()['c'] ?? 0;
$total_tersedia  = $conn->query("SELECT SUM(stok) AS s FROM buku")->fetch_assoc()['s'] ?? 0;
$total_anggota   = $conn->query("SELECT COUNT(*) AS c FROM user WHERE level = 'anggota'")->fetch_assoc()['c'] ?? 0;
$sedang_dipinjam = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam'")->fetch_assoc()['c'] ?? 0;
$terlambat       = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'] ?? 0;
$menunggu_acc    = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status = 'menunggu'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        /* Header - Solid Slate */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #f8fafc;
            text-decoration: none;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .brand img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #334155;
        }

        .search-box {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-input {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 0.45rem 1rem;
            color: #f8fafc;
            width: 260px;
            font-size: 0.88rem;
            transition: all 0.2s;
            outline: none;
        }

        .search-input::placeholder {
            color: #94a3b8;
        }

        .search-input:focus {
            background: #0f172a;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            width: 300px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #334155;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .user-avatar:hover {
            border-color: #60a5fa;
        }

        /* Layout */
        .layout-wrapper {
            display: flex;
            padding-top: 60px;
            min-height: 100vh;
        }

        /* Sidebar - Solid Deep Slate */
        .sidebar {
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section {
            padding: 1.25rem 0;
        }

        .sidebar-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0 1.5rem;
            margin-bottom: 0.65rem;
        }

        .nav-item {
            margin: 0.2rem 0.85rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.95rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.15s, color 0.15s;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #1e293b;
            color: #ffffff;
        }

        .nav-link.active {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            box-shadow: none;
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        .sidebar-user {
            margin-top: auto;
            padding: 1.1rem 1.25rem;
            border-top: 1px solid #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #0b1120;
        }

        .sidebar-user img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid #334155;
            object-fit: cover;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: #f8fafc;
            font-size: 0.88rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: capitalize;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem 2.25rem;
        }

        .welcome-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            letter-spacing: -0.3px;
        }

        .welcome-subtitle {
            color: #64748b;
            font-size: 0.92rem;
        }

        .divider {
            height: 1px;
            background: #f1f5f9;
            margin: 1.5rem 0;
        }

        /* Stats Cards - Clean Solid Styling */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.35rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 0.9rem;
        }

        .stat-card.blue .stat-icon {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }

        .stat-card.green .stat-icon {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #dcfce7;
        }

        .stat-card.orange .stat-icon {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fef3c7;
        }

        .stat-card.purple .stat-icon {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fee2e2;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 0.35rem;
        }

        .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: #f8fafc;
            font-size: 1.4rem;
            cursor: pointer;
            margin-right: 1rem;
        }

        @media (max-width: 991px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1.5rem 1rem;
            }

            .search-input {
                width: 180px;
            }

            .search-input:focus {
                width: 210px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <button class="mobile-toggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        
        <a href="beranda.php" class="brand">
            <img src="logo 40.jpeg" alt="Logo">
            <span>PerpusOnline</span>
        </a>

        <div class="search-box">
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex" style="font-size:0.8rem;">
                <i class="bi bi-globe me-1"></i>Katalog Publik
            </a>
            <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                <li><a class="dropdown-item" href="profile_admin.php"><i class="bi bi-person me-2"></i>Profil Admin</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </header>

    <!-- Layout Wrapper -->
    <div class="layout-wrapper">
        <!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item">
            <a href="beranda.php" class="nav-link active">
                <i class="bi bi-house-door-fill"></i>
                <span>Beranda</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="buku.php" class="nav-link">
                <i class="bi bi-book"></i>
                <span>Buku</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="anggota.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Anggota</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="peminjaman.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                <span>Peminjaman</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="pengembalian.php" class="nav-link">
                <i class="bi bi-arrow-return-left"></i>
                <span>Pengembalian</span>
            </a>
        </div>
    <div class="nav-item">
        <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check2-square"></i>
                <span>Persetujuan</span>
            </div>
            <?php if ($menunggu_acc > 0): ?>
                <span class="sidebar-notif-badge"><?= $menunggu_acc ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Lainnya</div>
        <div class="nav-item">
            <a href="profile_admin.php" class="nav-link">
                <i class="bi bi-person-circle"></i>
                <span>Profile Admin</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="tambah_kategori.php" class="nav-link">
                <i class="bi bi-tags-fill"></i>
                <span>Kategori</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="index.php" class="nav-link">
                <i class="bi bi-globe"></i>
                <span>Katalog Publik</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
    <!-- User Info -->
    <div class="sidebar-user">
        <img src="logo 40.jpeg" alt="User">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['nama']); ?></div>
            <div class="sidebar-user-role">Administrator PerpusOnline</div>
        </div>
    </div>
</aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h1 class="welcome-title fs-3 fw-bold m-0">
                            Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama']);?>! 👋
                        </h1>
                        <p class="welcome-subtitle m-0 mt-1">Panel Kendali Sirkulasi &amp; Manajemen <?= NAMA_PERPUS ?></p>
                    </div>
                    <?php if ($menunggu_acc > 0): ?>
                        <a href="persetujuan_admin.php" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-2 shadow-sm">
                            <span class="badge bg-white text-danger"><?= $menunggu_acc ?></span>
                            <span>Pengajuan Menunggu Persetujuan →</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="divider"></div>

                <!-- 5.3 Ringkasan Statistik Cepat di Dashboard Admin -->
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon" style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div>
                            <div class="stat-label">Buku Tersedia (Stok)</div>
                            <div class="stat-value text-success"><?= $total_tersedia ?> <span style="font-size:0.85rem;font-weight:normal;color:#64748b;">(<?= $total_buku ?> judul)</span></div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-icon" style="background:#fffbeb;color:#b45309;border:1px solid #fef3c7;">
                            <i class="bi bi-journal-arrow-up"></i>
                        </div>
                        <div>
                            <div class="stat-label">Sedang Dipinjam</div>
                            <div class="stat-value text-warning"><?= $sedang_dipinjam ?></div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-icon" style="background:#eff6ff;color:#1d4ed8;border:1px solid #dbeafe;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="stat-label">Total Siswa Aktif</div>
                            <div class="stat-value text-primary"><?= $total_anggota ?></div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-icon" style="background:#fef2f2;color:#b91c1c;border:1px solid #fee2e2;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="stat-label">Menunggu Acc</div>
                            <div class="stat-value text-danger"><?= $menunggu_acc ?></div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="stat-label">Terlambat</div>
                            <div class="stat-value"><?= $terlambat ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 991) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>