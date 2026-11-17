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

// Hitung statistik otomatis dari database
$total_buku      = $conn->query("SELECT COUNT(*) AS c FROM buku")->fetch_assoc()['c'];
$total_anggota   = $conn->query("SELECT COUNT(*) AS c FROM user WHERE level = 'anggota'")->fetch_assoc()['c'];
$sedang_dipinjam = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam'")->fetch_assoc()['c'];
$terlambat       = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #e7e7e7d2;
        }

        /* Header */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(135deg, #2d56b6 0%, #4172c1 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .brand img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .search-box {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-input {
            background: rgba(238, 238, 238, 0.24);
            border: 1px solid rgba(177, 177, 177, 0.53);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
            width: 280px;
            transition: all 0.3s;
        }

        .search-input::placeholder {
            color: rgba(230, 230, 230, 0.7);
        }

        .search-input:focus {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
            outline: none;
            width: 320px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(233, 233, 233, 0.8);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        /* Layout */
        .layout-wrapper {
            display: flex;
            padding-top: 60px;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            border-right: 1px solid rgba(255, 64, 64, 0.65);
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section {
            padding: 1.5rem 0;
        }

        .sidebar-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(245, 245, 245, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        .sidebar-user {
            margin-top: auto;
            padding: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(0,0,0,0.2);
        }

        .sidebar-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(227, 227, 227, 0.8);
        }

        .sidebar-user-info {
            flex: 1;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.73);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #4879c8;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .welcome-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        .divider {
            height: 1px;
            background: #509cff;
            margin: 1.5rem 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #bababa00;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(116, 116, 116, 0.44);
            transition: transform 0.2s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(219, 219, 219, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.blue .stat-icon {
            background: rgba(136, 172, 255, 0.26);
            color: #256afe;
        }

        .stat-card.green .stat-icon {
            background: rgba(145, 222, 187, 0.33);
            color: #10b981;
        }

        .stat-card.orange .stat-icon {
            background: rgba(245,158,11,0.1);
            color: #f59e0b;
        }

        .stat-card.purple .stat-icon {
            background: rgba(139,92,246,0.1);
            color: #8b5cf6;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #171616b8;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
        }

        @media (max-width: 991px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .search-input {
                width: 200px;
            }

            .search-input:focus {
                width: 220px;
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
        
        <a href="index.php" class="brand">
            <img src="logo 40.jpeg" alt="Logo">
            <span>PerpusOnline</span>
        </a>

        <div class="search-box">
            <input type="text" class="search-input" placeholder="Cari buku...">
            <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Profil</a></li>
                <li><a class="dropdown-item" href="#">Pengaturan</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Keluar</a></li>
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
        <a href="persetujuan_admin.php" class="nav-link">
            <i class="bi bi-check2-square"></i>
            <span>Persetujuan</span>
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
            <a href="logout.php" class="nav-link">
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
            <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['level']); ?></div>
        </div>
    </div>
</aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1 class="welcome-title">
                    <span style="font-size: 2rem;">Selamat Datang</span><?php echo htmlspecialchars($_SESSION['nama']);?>!
                </h1>
                <p class="welcome-subtitle">Kamu Adalah Admin</p>
                
                <div class="divider"></div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-label">Total Buku</div>
                        <div class="stat-value"><?= $total_buku ?></div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-label">Total Anggota</div>
                        <div class="stat-value"><?= $total_anggota ?></div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-label">Sedang Dipinjam</div>
                        <div class="stat-value"><?= $sedang_dipinjam ?></div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-label">Terlambat</div>
                        <div class="stat-value"><?= $terlambat ?></div>
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