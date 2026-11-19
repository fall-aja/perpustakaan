<?php
session_start();
require_once 'koneksi.php';
require_once 'helper_katalog.php';

// Cek status sesi login
$isLoggedIn = isset($_SESSION['username']);
$userLevel  = $isLoggedIn ? trim($_SESSION['level'] ?? '') : '';
$userName   = $isLoggedIn ? ($_SESSION['nama'] ?? '') : '';
$userId     = $isLoggedIn ? intval($_SESSION['id_user'] ?? 0) : 0;

// Filter & Pencarian
$kategori_filter = isset($_GET['kategori']) ? $koneksi->real_escape_string(trim($_GET['kategori'])) : '';
$search          = isset($_GET['search'])   ? $koneksi->real_escape_string(trim($_GET['search']))   : '';

$where = "WHERE 1=1";
if ($kategori_filter !== '') {
    $where .= " AND LOWER(k.nama_kategori) = LOWER('$kategori_filter')";
}
if ($search !== '') {
    $where .= " AND (b.judul_buku LIKE '%$search%' OR b.penulis LIKE '%$search%' OR b.isbn LIKE '%$search%')";
}

// 1. Ambil daftar kategori
$kategoriList = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");

// 2. Ambil Buku Populer / Rekomendasi Pekan Ini (paling sering dipinjam)
$popularQuery = "
    SELECT b.*, k.nama_kategori, COUNT(p.id_peminjaman) AS total_dipinjam
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
    GROUP BY b.id_buku
    ORDER BY total_dipinjam DESC, b.stok DESC, b.id_buku DESC
    LIMIT 4
";
$popularRes = $koneksi->query($popularQuery);

// 3. Ambil Koleksi Katalog Buku
$catalogQuery = "
    SELECT b.*, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    $where
    ORDER BY b.id_buku DESC
";
$catalogRes = $koneksi->query($catalogQuery);

// 4. Statistik Singkat
$totalJudul    = $koneksi->query("SELECT COUNT(*) AS c FROM buku")->fetch_assoc()['c'] ?? 0;
$totalTersedia = $koneksi->query("SELECT SUM(stok) AS s FROM buku")->fetch_assoc()['s'] ?? 0;
$totalAnggota  = $koneksi->query("SELECT COUNT(*) AS c FROM user WHERE level='anggota'")->fetch_assoc()['c'] ?? 0;

// Cek jam buka perpustakaan
$isBuka = isPerpusBuka();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= NAMA_PERPUS ?> | Katalog Publik Digital</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom Style Katalog -->
    <link rel="stylesheet" href="katalog_styles.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            overflow-x: hidden;
        }

        /* Top Navbar */
        .public-navbar {
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            padding: 0.85rem 0;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.12rem;
            letter-spacing: -0.2px;
        }

        .nav-brand img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid #334155;
            object-fit: cover;
            background: #ffffff;
        }

        .nav-brand-sub {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
            margin-top: -2px;
        }

        .btn-login-nav {
            background-color: #2563eb;
            color: #ffffff;
            border: 1px solid #3b82f6;
            font-weight: 700;
            font-size: 0.88rem;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-login-nav:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .user-dropdown-btn {
            background: #1e293b;
            border: 1px solid #334155;
            color: #ffffff;
            border-radius: 50px;
            padding: 4px 14px 4px 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .user-dropdown-btn:hover {
            background: #334155;
            color: #ffffff;
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Hero Welcome Banner */
        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 65%, #1d4ed8 100%);
            color: #ffffff;
            padding: 3.5rem 0 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at 80% 20%, rgba(59, 130, 246, 0.2) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #93c5fd;
            margin-bottom: 1.25rem;
            backdrop-filter: blur(4px);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.25;
            margin-bottom: 1rem;
        }

        .hero-desc {
            font-size: 1.05rem;
            color: #cbd5e1;
            line-height: 1.65;
            max-width: 620px;
            margin-bottom: 2rem;
        }

        /* Quote Box */
        .quote-box {
            background: rgba(15, 23, 42, 0.45);
            border-left: 4px solid #60a5fa;
            border-radius: 0 10px 10px 0;
            padding: 12px 18px;
            margin-bottom: 2rem;
            backdrop-filter: blur(4px);
            max-width: 580px;
        }

        .quote-text {
            font-size: 0.92rem;
            font-style: italic;
            color: #e2e8f0;
            margin-bottom: 2px;
        }

        .quote-author {
            font-size: 0.76rem;
            color: #93c5fd;
            font-weight: 600;
        }

        /* Hero Search Input Box */
        .hero-search-box {
            background: #ffffff;
            border-radius: 14px;
            padding: 8px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.25);
            max-width: 640px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-search-box i.search-icon {
            font-size: 1.25rem;
            color: #94a3b8;
            margin-left: 12px;
        }

        .hero-search-input {
            border: none;
            outline: none;
            padding: 10px 8px;
            font-size: 1rem;
            color: #0f172a;
            flex: 1;
            font-family: inherit;
        }

        .hero-search-btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 10px 24px;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .hero-search-btn:hover {
            background: var(--primary-hover);
        }

        /* Stats Pill Grid in Hero */
        .hero-stat-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 1.25rem;
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            gap: 14px;
            color: #ffffff;
        }

        .hero-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: #93c5fd;
        }

        /* Section Headings */
        .section-header {
            margin-bottom: 1.75rem;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-subtitle {
            font-size: 0.92rem;
            color: #64748b;
            margin: 0;
        }

        /* Book Card Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .book-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
        }

        .book-card:hover {
            transform: translateY(-4px);
            border-color: #cbd5e1;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .book-card-top {
            padding: 12px 12px 0 12px;
            position: relative;
        }

        .book-card-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .bc-category-tag {
            font-size: 0.7rem;
            font-weight: 700;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .bc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.6em;
        }

        .bc-author {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bc-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.78rem;
        }

        .btn-pinjam-action {
            width: 100%;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.84rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
        }

        .btn-pinjam-action.ready {
            background-color: #2563eb;
            color: #ffffff;
        }

        .btn-pinjam-action.ready:hover {
            background-color: #1d4ed8;
        }

        .btn-pinjam-action.disabled {
            background-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Announcement Banner */
        .announcement-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #2563eb;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .announcement-bar i {
            font-size: 1.4rem;
            color: #2563eb;
            flex-shrink: 0;
        }

        /* Footer */
        .public-footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 3.5rem 0 2rem 0;
            border-top: 1px solid #1e293b;
            font-size: 0.88rem;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            font-weight: 800;
            font-size: 1.15rem;
            margin-bottom: 12px;
        }

        .footer-brand img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid #334155;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.85rem;
            }
            .hero-search-box {
                flex-direction: column;
                padding: 6px;
            }
            .hero-search-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <header class="public-navbar">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="index.php" class="nav-brand">
                <img src="logo 40.jpeg" alt="Logo PerpusOnline" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
                <div>
                    <div><?= NAMA_PERPUS ?></div>
                    <span class="nav-brand-sub"><?= TAGLINE_PERPUS ?></span>
                </div>
            </a>

            <!-- Menu Kanan / Status Login -->
            <div class="d-flex align-items-center gap-3">
                <a href="#katalog" class="text-white text-decoration-none d-none d-md-inline-block fw-semibold me-2" style="font-size: 0.9rem;">
                    <i class="bi bi-book me-1"></i>Katalog
                </a>
                <a href="#operasional" class="text-white text-decoration-none d-none d-md-inline-block fw-semibold me-2" style="font-size: 0.9rem;">
                    <i class="bi bi-clock me-1"></i>Jam Operasional
                </a>

                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <a href="#" class="user-dropdown-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar-circle"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                            <span class="d-none d-sm-inline"><?= htmlspecialchars($userName) ?></span>
                            <span class="badge bg-primary" style="font-size:0.65rem;text-transform:capitalize;"><?= $userLevel ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                            <li class="dropdown-header text-muted">Akun Masuk</li>
                            <?php if ($userLevel === 'admin'): ?>
                                <li><a class="dropdown-item" href="beranda.php"><i class="bi bi-speedometer2 me-2"></i>Panel Dashboard Admin</a></li>
                                <li><a class="dropdown-item" href="persetujuan_admin.php"><i class="bi bi-check2-square me-2"></i>Persetujuan Pinjam</a></li>
                                <li><a class="dropdown-item" href="buku.php"><i class="bi bi-collection me-2"></i>Kelola Buku</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="login_anggota.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard Saya</a></li>
                                <li><a class="dropdown-item" href="login_anggota.php?hal=profil"><i class="bi bi-person-vcard me-2"></i>Kartu Anggota Digital</a></li>
                                <li><a class="dropdown-item" href="peminjaman_anggota.php"><i class="bi bi-clock-history me-2"></i>Peminjaman Saya</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="masuk.php" class="btn-login-nav">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Masuk / Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ================= HERO WELCOME BANNER ================= -->
    <section class="hero-banner">
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="hero-badge-tag">
                        <i class="bi bi-stars"></i>
                        <span>Katalog Publik Terbuka Siswa</span>
                    </div>

                    <h1 class="hero-title">
                        Jelajahi Pustaka Ilmu, Wujudkan Prestasi Literasi.
                    </h1>

                    <p class="hero-desc">
                        Cari buku pelajaran, fiksi inspiratif, sains, hingga komik edukasi langsung tanpa login. Temukan posisi rak buku fisik dan ajukan pinjaman dengan mudah.
                    </p>

                    <!-- Literature Quote -->
                    <div class="quote-box">
                        <div class="quote-text">
                            "Membaca adalah jembatan emas yang menghubungkan masa lalu dengan masa depan dan membukakan pintu ke seluruh dunia."
                        </div>
                        <div class="quote-author">— PerpusOnline</div>
                    </div>

                    <!-- Open Search Input -->
                    <form action="index.php#katalog" method="GET" class="hero-search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" 
                               name="search" 
                               class="hero-search-input" 
                               placeholder="Cari judul buku, pengarang, atau ISBN..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="hero-search-btn">
                            Cari Buku
                        </button>
                    </form>
                </div>

                <div class="col-lg-5 d-none d-lg-block">
                    <div class="d-flex flex-column gap-3">
                        <div class="hero-stat-card">
                            <div class="hero-stat-icon">
                                <i class="bi bi-journal-album"></i>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 800;"><?= $totalJudul ?> Judul</div>
                                <div style="font-size: 0.85rem; color: #cbd5e1;">Koleksi Pustaka Terdaftar</div>
                            </div>
                        </div>

                        <div class="hero-stat-card">
                            <div class="hero-stat-icon">
                                <i class="bi bi-check2-circle text-success"></i>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 800;"><?= $totalTersedia ?> Eksemplar</div>
                                <div style="font-size: 0.85rem; color: #cbd5e1;">Buku Tersedia di Rak</div>
                            </div>
                        </div>

                        <div class="hero-stat-card">
                            <div class="hero-stat-icon">
                                <i class="bi bi-people-fill text-warning"></i>
                            </div>
                            <div>
                                <div style="font-size: 1.5rem; font-weight: 800;"><?= $totalAnggota ?> Siswa</div>
                                <div style="font-size: 0.85rem; color: #cbd5e1;">Anggota Aktif Perpustakaan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= MAIN CONTENT WRAPPER ================= -->
    <main class="py-5">
        <div class="container">

            <!-- PENGUMUMAN PERPUSTAKAAN -->
            <div class="announcement-bar">
                <i class="bi bi-megaphone-fill"></i>
                <div class="flex-grow-1" style="font-size: 0.92rem;">
                    <strong>Pengumuman Layanan:</strong> Layanan sirkulasi buku tatap muka dibuka setiap hari sekolah. Jangan lupa tunjukkan <strong>Kartu Anggota Digital</strong> pada ponsel Anda saat mengambil buku di ruang perpustakaan.
                </div>
                <span class="status-indicator-pill <?= $isBuka ? 'open' : 'closed' ?>">
                    <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                    <?= $isBuka ? 'Buka Sekarang' : 'Sedang Tutup' ?>
                </span>
            </div>

            <!-- ================= BUKU POPULER / REKOMENDASI PEKAN INI ================= -->
            <?php if ($popularRes && $popularRes->num_rows > 0 && empty($search) && empty($kategori_filter)): ?>
            <section class="mb-5">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="bi bi-fire text-danger"></i>
                            Buku Populer / Rekomendasi Pekan Ini
                        </h2>
                        <p class="section-subtitle">Paling sering dipinjam dan dibaca oleh siswa</p>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                        <i class="bi bi-star-fill text-danger me-1"></i>Pilihan Favorit
                    </span>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                    <?php while ($pop = $popularRes->fetch_assoc()): 
                        $stokPop = intval($pop['stok'] ?? 0);
                        $hasCoverPop = !empty($pop['cover']) && file_exists('covers/' . $pop['cover']);
                    ?>
                    <div class="col">
                        <div class="book-card h-100">
                            <div class="book-card-top">
                                <?= renderBookCover($pop['cover'], $pop['judul_buku'], $pop['penulis'], $pop['nama_kategori'], $pop['lokasi_rak'] ?? 'Rak A-1') ?>
                            </div>
                            <div class="book-card-body">
                                <div>
                                    <span class="bc-category-tag"><?= htmlspecialchars($pop['nama_kategori'] ?? 'Umum') ?></span>
                                    <h3 class="bc-title" title="<?= htmlspecialchars($pop['judul_buku']) ?>"><?= htmlspecialchars($pop['judul_buku']) ?></h3>
                                    <div class="bc-author">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($pop['penulis'] ?? '-') ?>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <?= renderRakBadge($pop['lokasi_rak'] ?? 'Rak A-1') ?>
                                </div>

                                <div class="bc-meta">
                                    <?= renderStockBadge($stokPop) ?>
                                    <span class="text-muted" style="font-size:0.75rem;">
                                        <i class="bi bi-arrow-repeat text-primary"></i> <?= intval($pop['total_dipinjam']) ?>x dipinjam
                                    </span>
                                </div>

                                <?php if ($stokPop > 0): ?>
                                    <?php if ($isLoggedIn && $userLevel === 'anggota'): ?>
                                        <a href="buku_anggota.php?search=<?= urlencode($pop['judul_buku']) ?>" class="btn-pinjam-action ready">
                                            <i class="bi bi-book-fill"></i> Pinjam Buku
                                        </a>
                                    <?php elseif ($isLoggedIn && $userLevel === 'admin'): ?>
                                        <a href="buku.php" class="btn-pinjam-action ready">
                                            <i class="bi bi-pencil-square"></i> Kelola di Panel
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-pinjam-action ready" onclick="promptLogin('<?= addslashes(htmlspecialchars($pop['judul_buku'])) ?>')">
                                            <i class="bi bi-book-fill"></i> Pinjam Buku
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-pinjam-action disabled" disabled>
                                        <i class="bi bi-x-circle-fill"></i> Stok Habis
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- ================= KATALOG BUKU LENGKAP ================= -->
            <section id="katalog" class="pt-3">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="bi bi-bookshelf text-primary"></i>
                            Katalog Buku Perpustakaan
                        </h2>
                        <p class="section-subtitle">
                            <?= $catalogRes ? $catalogRes->num_rows : 0 ?> judul buku ditemukan
                            <?= $search ? ' untuk pencarian "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>
                            <?= $kategori_filter ? ' dalam kategori "<strong>' . htmlspecialchars($kategori_filter) . '</strong>"' : '' ?>
                        </p>
                    </div>

                    <?php if ($search || $kategori_filter): ?>
                        <a href="index.php#katalog" class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="bi bi-x-circle me-1"></i>Reset Filter
                        </a>
                    <?php endif; ?>
                </div>

                <!-- 2. FILTER KATEGORI CEPAT (TOMBOL PILLS) -->
                <div class="category-pills-bar mb-4">
                    <a href="index.php?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>#katalog" 
                       class="cat-pill <?= $kategori_filter === '' ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill"></i> Semua Kategori
                    </a>

                    <?php 
                    if ($kategoriList && $kategoriList->num_rows > 0): 
                        mysqli_data_seek($kategoriList, 0);
                        while ($kat = $kategoriList->fetch_assoc()):
                            $kn = $kat['nama_kategori'];
                            $isActive = (strtolower($kategori_filter) === strtolower($kn));
                    ?>
                        <a href="index.php?kategori=<?= urlencode($kn) ?><?= $search ? '&search=' . urlencode($search) : '' ?>#katalog" 
                           class="cat-pill <?= $isActive ? 'active' : '' ?>">
                            <?php 
                                $kl = strtolower($kn);
                                if (strpos($kl, 'fiksi') !== false || strpos($kl, 'novel') !== false) echo '<i class="bi bi-journal-bookmark"></i>';
                                elseif (strpos($kl, 'pendidikan') !== false || strpos($kl, 'pelajaran') !== false) echo '<i class="bi bi-mortarboard"></i>';
                                elseif (strpos($kl, 'sains') !== false) echo '<i class="bi bi-cpu"></i>';
                                elseif (strpos($kl, 'komik') !== false) echo '<i class="bi bi-brush"></i>';
                                elseif (strpos($kl, 'biografi') !== false) echo '<i class="bi bi-person-lines-fill"></i>';
                                else echo '<i class="bi bi-tag"></i>';
                            ?>
                            <?= htmlspecialchars($kn) ?>
                        </a>
                    <?php endwhile; endif; ?>
                </div>

                <!-- GRID KOLEKSI BUKU -->
                <?php if ($catalogRes && $catalogRes->num_rows > 0): ?>
                    <div class="books-grid">
                        <?php while ($buku = $catalogRes->fetch_assoc()): 
                            $stok = intval($buku['stok'] ?? 0);
                            $lokasi = $buku['lokasi_rak'] ?? 'Rak A-1';
                        ?>
                            <div class="book-card">
                                <div class="book-card-top">
                                    <?= renderBookCover($buku['cover'], $buku['judul_buku'], $buku['penulis'], $buku['nama_kategori'], $lokasi) ?>
                                </div>
                                <div class="book-card-body">
                                    <div>
                                        <span class="bc-category-tag"><?= htmlspecialchars($buku['nama_kategori'] ?? 'Umum') ?></span>
                                        <h3 class="bc-title" title="<?= htmlspecialchars($buku['judul_buku']) ?>"><?= htmlspecialchars($buku['judul_buku']) ?></h3>
                                        <div class="bc-author">
                                            <i class="bi bi-person"></i> <?= htmlspecialchars($buku['penulis'] ?? '-') ?>
                                        </div>
                                    </div>

                                    <!-- Nomor Rak & Lokasi Fisik -->
                                    <div class="mb-2">
                                        <?= renderRakBadge($lokasi) ?>
                                    </div>

                                    <!-- Badge Status Stok Buku -->
                                    <div class="bc-meta">
                                        <?= renderStockBadge($stok) ?>
                                        <span class="text-muted" style="font-size:0.75rem;">
                                            Thn: <?= htmlspecialchars($buku['tahun_terbit'] ?? '-') ?>
                                        </span>
                                    </div>

                                    <!-- Tombol Pinjam / Nonaktif jika Habis -->
                                    <?php if ($stok > 0): ?>
                                        <?php if ($isLoggedIn && $userLevel === 'anggota'): ?>
                                            <a href="buku_anggota.php?search=<?= urlencode($buku['judul_buku']) ?>" class="btn-pinjam-action ready">
                                                <i class="bi bi-book-fill"></i> Pinjam Buku
                                            </a>
                                        <?php elseif ($isLoggedIn && $userLevel === 'admin'): ?>
                                            <a href="buku.php" class="btn-pinjam-action ready">
                                                <i class="bi bi-pencil-square"></i> Kelola di Admin
                                            </a>
                                        <?php else: ?>
                                            <button class="btn-pinjam-action ready" onclick="promptLogin('<?= addslashes(htmlspecialchars($buku['judul_buku'])) ?>')">
                                                <i class="bi bi-book-fill"></i> Pinjam Buku
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn-pinjam-action disabled" disabled title="Buku sedang dipinjam habis">
                                            <i class="bi bi-x-circle-fill"></i> Stok Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded-4 border p-4">
                        <i class="bi bi-journal-x text-muted" style="font-size: 3.5rem;"></i>
                        <h4 class="mt-3 fw-bold">Tidak Ada Buku Ditemukan</h4>
                        <p class="text-muted">Coba gunakan kata kunci pencarian lain atau pilih kategori lain.</p>
                        <a href="index.php#katalog" class="btn btn-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Tampilkan Semua Buku
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ================= 4. WIDGET JAM OPERASIONAL & ATURAN PINJAM ================= -->
            <section id="operasional" class="my-5 pt-3">
                <div class="row g-4">
                    <!-- Widget Jam Operasional -->
                    <div class="col-lg-6">
                        <div class="info-widget-card h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="p-2 rounded-3 bg-primary text-white">
                                        <i class="bi bi-clock-history fs-5"></i>
                                    </div>
                                    <h3 class="fs-5 fw-bold m-0">Jam Operasional Perpustakaan</h3>
                                </div>
                                <span class="status-indicator-pill <?= $isBuka ? 'open' : 'closed' ?>">
                                    <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                                    <?= $isBuka ? 'Buka Sekarang' : 'Sedang Tutup' ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-3">
                                Pelayanan peminjaman, pengembalian, dan ruang baca dibuka pada hari efektif operasional:
                            </p>

                            <ul class="list-group list-group-flush border-top border-bottom mb-3" style="font-size: 0.88rem;">
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                    <span><i class="bi bi-calendar-check text-primary me-2"></i><strong>Senin – Kamis</strong></span>
                                    <span class="badge bg-light text-dark border">07.00 – 15.30 WIB</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0">
                                    <span><i class="bi bi-calendar-check text-primary me-2"></i><strong>Jumat</strong></span>
                                    <span class="badge bg-light text-dark border">07.00 – 15.00 WIB</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 text-muted">
                                    <span><i class="bi bi-clock text-warning me-2"></i><em>Istirahat Siang (Jumat)</em></span>
                                    <span>11.30 – 13.00 WIB</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 text-muted">
                                    <span><i class="bi bi-calendar-x text-danger me-2"></i><strong>Sabtu, Minggu &amp; Libur Nasional</strong></span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Tutup</span>
                                </li>
                            </ul>

                            <small class="text-muted d-block">
                                <i class="bi bi-info-circle me-1"></i>Peminjaman daring lewat web dapat diajukan 24 jam dan akan diverifikasi petugas di jam operasional.
                            </small>
                        </div>
                    </div>

                    <!-- Aturan Singkat Peminjaman -->
                    <div class="col-lg-6">
                        <div class="info-widget-card h-100">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="p-2 rounded-3 bg-success text-white">
                                    <i class="bi bi-shield-check fs-5"></i>
                                </div>
                                <h3 class="fs-5 fw-bold m-0">Tata Tertib &amp; Aturan Peminjaman</h3>
                            </div>

                            <p class="text-muted small mb-3">
                                Ketentuan peminjaman buku untuk menjaga kelancaran sirkulasi literasi seluruh siswa:
                            </p>

                            <div class="d-flex flex-column gap-2" style="font-size: 0.88rem;">
                                <div class="d-flex align-items-start gap-2 p-2 rounded-2 bg-light">
                                    <i class="bi bi-check-circle-fill text-success mt-1"></i>
                                    <div>
                                        <strong>Batas Maksimal Buku:</strong> Setiap siswa berhak meminjam maksimal <strong>2 (dua) buku</strong> sekaligus.
                                    </div>
                                </div>

                                <div class="d-flex align-items-start gap-2 p-2 rounded-2 bg-light">
                                    <i class="bi bi-calendar2-range-fill text-primary mt-1"></i>
                                    <div>
                                        <strong>Durasi Pinjam:</strong> Jangka waktu peminjaman adalah <strong>7 (tujuh) hari kalender</strong> sejak persetujuan.
                                    </div>
                                </div>

                                <div class="d-flex align-items-start gap-2 p-2 rounded-2 bg-light">
                                    <i class="bi bi-person-vcard-fill text-warning mt-1"></i>
                                    <div>
                                        <strong>Pengambilan Buku:</strong> Tunjukkan <strong>Kartu Anggota Digital</strong> pada menu Profil Anda kepada pustakawan.
                                    </div>
                                </div>

                                <div class="d-flex align-items-start gap-2 p-2 rounded-2 bg-light">
                                    <i class="bi bi-exclamation-octagon-fill text-danger mt-1"></i>
                                    <div>
                                        <strong>Tepat Waktu &amp; Tanggung Jawab:</strong> Kembalikan buku tepat waktu sebelum tanggal jatuh tempo dan jaga keutuhan halaman buku.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="public-footer">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="footer-brand">
                        <img src="logo 40.jpeg" alt="Logo PerpusOnline" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
                        <div>
                            <div><?= NAMA_PERPUS ?></div>
                            <small class="text-secondary fw-normal"><?= TAGLINE_PERPUS ?></small>
                        </div>
                    </div>
                    <p class="text-secondary small mb-3">
                        <?= ALAMAT_PERPUS ?>
                    </p>
                    <p class="text-secondary small m-0">
                        <i class="bi bi-telephone me-1"></i><?= KONTAK_PERPUS ?>
                    </p>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h4 class="text-white fw-bold mb-3" style="font-size:0.95rem;">Tautan Cepat</h4>
                    <ul class="list-unstyled text-secondary small d-flex flex-column gap-2 m-0">
                        <li><a href="index.php" class="text-secondary text-decoration-none">Beranda &amp; Pencarian Buku</a></li>
                        <li><a href="index.php#katalog" class="text-secondary text-decoration-none">Daftar Koleksi Pustaka</a></li>
                        <li><a href="index.php#operasional" class="text-secondary text-decoration-none">Jadwal &amp; Tata Tertib</a></li>
                        <li><a href="masuk.php" class="text-secondary text-decoration-none">Portal Masuk Siswa / Admin</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-6">
                    <h4 class="text-white fw-bold mb-3" style="font-size:0.95rem;">Akses Siswa &amp; Petugas</h4>
                    <p class="text-secondary small mb-3">
                        Gunakan akun NIS siswa yang telah didaftarkan untuk mengajukan pinjaman atau membuka Kartu Perpustakaan Digital.
                    </p>
                    <?php if (!$isLoggedIn): ?>
                        <a href="masuk.php" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk ke Akun Anda
                        </a>
                    <?php else: ?>
                        <a href="<?= $userLevel === 'admin' ? 'beranda.php' : 'login_anggota.php' ?>" class="btn btn-outline-light btn-sm rounded-pill px-4 fw-bold">
                            <i class="bi bi-speedometer2 me-1"></i>Buka Dashboard (<?= htmlspecialchars($userName) ?>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-3 border-top border-secondary text-center text-secondary small">
                &copy; <?= date('Y') ?> <?= NAMA_PERPUS ?>. Hak Cipta Dilindungi Undang-Undang.
            </div>
        </div>
    </footer>

    <!-- ================= MODAL LOGIN PROMPT ================= -->
    <div class="modal fade" id="modalPromptLogin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fs-6 fw-bold">
                        <i class="bi bi-shield-lock me-2 text-primary"></i>Login Diperlukan untuk Meminjam
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-book-half text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Ingin Meminjam Buku Ini?</h6>
                    <p class="text-muted small mb-4" id="promptBookTitle">
                        Silakan login dengan akun siswa Anda untuk mengirimkan pengajuan peminjaman buku ke pustakawan.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-bs-dismiss="modal">
                            Nanti Saja
                        </button>
                        <a href="masuk.php?pesan=harus_login" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function promptLogin(judul) {
            const el = document.getElementById('promptBookTitle');
            if (el && judul) {
                el.innerHTML = `Kamu memilih buku <strong>"${judul}"</strong>. Silakan login dengan akun siswa untuk mengajukan pinjaman.`;
            }
            const modal = new bootstrap.Modal(document.getElementById('modalPromptLogin'));
            modal.show();
        }
    </script>
</body>
</html>
