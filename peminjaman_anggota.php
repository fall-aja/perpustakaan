<?php
session_start();
if (!isset($_SESSION['username']) || trim($_SESSION['level']) != 'anggota') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
require_once 'helper_katalog.php';

$id_user = $_SESSION['id_user'];
$nama    = $_SESSION['nama'];

$tab    = $_GET['tab']    ?? 'aktif';
$f      = $_GET['f']      ?? 'semua';
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

// ========== STATISTIK ==========
$total_pinjam = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user")->fetch_assoc()['c'];
$dipinjam     = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam'")->fetch_assoc()['c'];
$terlambat    = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'];
$dikembalikan = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dikembalikan'")->fetch_assoc()['c'];

// ========== QUERY TAB AKTIF ==========
$search_cond = $search ? " AND (b.judul_buku LIKE '%$search%' OR b.penulis LIKE '%$search%')" : '';
$aktif_cond  = "p.id_user=$id_user AND p.status_peminjaman='dipinjam'";
if ($f === 'terlambat') $aktif_cond .= " AND p.tanggal_pengembalian < CURDATE()";

$aktif_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis, b.isbn, b.lokasi_rak, k.nama_kategori
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE $aktif_cond $search_cond
    ORDER BY p.tanggal_pengembalian ASC
");

// ========== QUERY TAB RIWAYAT ==========
$riw_cond = "p.id_user=$id_user";
if ($f === 'dipinjam')     $riw_cond .= " AND p.status_peminjaman='dipinjam'";
if ($f === 'dikembalikan') $riw_cond .= " AND p.status_peminjaman='dikembalikan'";
if ($f === 'terlambat')    $riw_cond .= " AND p.status_peminjaman='dipinjam' AND p.tanggal_pengembalian < CURDATE()";

$riw_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis, b.isbn, b.lokasi_rak, k.nama_kategori
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE $riw_cond $search_cond
    ORDER BY p.tanggal_peminjaman DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Saya | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .main-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #0f172a; border-bottom: 1px solid #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; padding: 0 1.5rem; z-index: 1000; }
        .brand { display: flex; align-items: center; gap: 12px; color: #f8fafc; text-decoration: none; font-size: 1.15rem; font-weight: 700; letter-spacing: 0.2px; }
        .brand img { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #334155; }
        .search-box { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
        .search-input { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 0.45rem 1rem; color: #f8fafc; width: 260px; font-size: 0.88rem; transition: all 0.2s; outline: none; }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { background: #0f172a; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); width: 300px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #334155; cursor: pointer; transition: border-color 0.2s; background: #1d4ed8; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.95rem; text-decoration:none; }
        .user-avatar:hover { border-color: #60a5fa; }
        .mobile-toggle { display: none; background: none; border: none; color: #f8fafc; font-size: 1.4rem; cursor: pointer; margin-right: 1rem; }
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }
        .sidebar { position: fixed; top: 60px; left: 0; bottom: 0; width: 260px; background: #0f172a; border-right: 1px solid #1e293b; overflow-y: auto; z-index: 900; display: flex; flex-direction: column; }
        .sidebar-section { padding: 1.25rem 0; }
        .sidebar-title { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; padding: 0 1.5rem; margin-bottom: 0.65rem; }
        .nav-item { margin: 0.2rem 0.85rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.95rem; color: #94a3b8; text-decoration: none; border-radius: 8px; transition: background-color 0.15s, color 0.15s; font-size: 0.88rem; font-weight: 500; }
        .nav-link:hover { background: #1e293b; color: #ffffff; }
        .nav-link.active { background: #2563eb; color: #ffffff; font-weight: 600; box-shadow: none; }
        .nav-link i { font-size: 1.1rem; width: 20px; }
        .sidebar-user { margin-top: auto; padding: 1.1rem 1.25rem; border-top: 1px solid #1e293b; display: flex; align-items: center; gap: 0.75rem; background: #0b1120; }
        .sidebar-user img { width: 38px; height: 38px; border-radius: 50%; border: 2px solid #334155; object-fit: cover; }
        .sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.88rem; }
        .sidebar-user-role { font-size: 0.75rem; color: #64748b; text-transform: capitalize; }
        .main-content { flex: 1; margin-left: 260px; padding: 2rem 2.25rem; }

        /* PAGE HEADER */
        .page-header { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .page-title { font-size: 1.35rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.65rem; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.4rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1rem; transition: transform 0.15s ease, border-color 0.15s ease; text-decoration: none; color: inherit; }
        .stat-card:hover { transform: translateY(-2px); border-color: #cbd5e1; color: inherit; }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
        .stat-icon.blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .stat-icon.orange { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
        .stat-icon.red    { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        .stat-icon.green  { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.2rem; }
        .stat-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.1; }

        /* BANNER */
        .banner { border-radius: 8px; padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.88rem; font-weight: 500; }
        .banner-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* TABS */
        .tab-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; }
        .tab-nav { display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .tab-btn { flex: 1; padding: 0.9rem 1.5rem; background: none; border: none; font-size: 0.88rem; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; justify-content: center; gap: 0.5rem; position: relative; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .tab-btn:hover { color: #0f172a; background: #f1f5f9; }
        .tab-btn.active { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #ffffff; }
        .tab-count { background: #e2e8f0; color: #475569; font-size: 0.72rem; padding: 2px 7px; border-radius: 20px; font-weight: 700; }
        .tab-btn.active .tab-count { background: #eff6ff; color: #1d4ed8; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* FILTER BAR */
        .filter-bar { padding: 0.9rem 1.35rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.35rem 0.85rem; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 500; border: 1px solid #cbd5e1; color: #334155; transition: all 0.15s ease; background: #ffffff; display: inline-flex; align-items: center; gap: 0.3rem; }
        .filter-btn:hover { background: #eff6ff; border-color: #1d4ed8; color: #1d4ed8; }
        .filter-btn.active        { background: #1d4ed8; border-color: #1d4ed8; color: white; font-weight: 600; }
        .filter-btn.active.orange { background: #b45309; border-color: #b45309; color: white; font-weight: 600; }
        .filter-btn.active.green  { background: #15803d; border-color: #15803d; color: white; font-weight: 600; }
        .filter-btn.active.red    { background: #b91c1c; border-color: #b91c1c; color: white; font-weight: 600; }
        .search-filter { margin-left: auto; display: flex; align-items: center; gap: 0.4rem; }
        .search-filter input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.85rem; color: #0f172a; width: 210px; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
        .search-filter input:focus { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
        .search-filter button { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.88rem; cursor: pointer; transition: background-color 0.15s; }
        .search-filter button:hover { background: #1e40af; }

        /* TABLE */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th { padding: 0.85rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge-status { padding: 0.28rem 0.7rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge-dipinjam     { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
        .badge-dikembalikan { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .badge-terlambat    { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .empty-state i { font-size: 2.8rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }
        .info-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #92400e; display: flex; align-items: center; gap: 0.5rem; }
        .alert-floating { position: fixed; top: 75px; right: 1.5rem; z-index: 9999; min-width: 280px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); animation: slideIn 0.25s ease; }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .search-filter { margin-left: 0; }
            .search-input { width: 180px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="login_anggota.php" class="brand">
        <img src="logo 40.jpeg" alt="Logo" onerror="this.style.display='none'">
        <span>PerpusOnline</span>
    </a>
    <div class="search-box">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex" style="font-size:0.8rem;">
            <i class="bi bi-globe me-1"></i>Katalog Publik
        </a>
        <a href="login_anggota.php?hal=profil" class="user-avatar" data-bs-toggle="dropdown" style="text-decoration:none;">
            <?= strtoupper(substr($nama,0,1)) ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="login_anggota.php?hal=kartu"><i class="bi bi-person-vcard me-2"></i>Kartu Anggota Digital</a></li>
            <li><a class="dropdown-item" href="login_anggota.php?hal=profil"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
        </ul>
    </div>
</header>

<div class="layout-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item">
            <a href="login_anggota.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda Siswa</span></a>
        </div>
        <div class="nav-item">
            <a href="buku_anggota.php" class="nav-link"><i class="bi bi-book"></i><span>Katalog Buku</span></a>
        </div>
        <div class="nav-item">
            <a href="peminjaman_anggota.php" class="nav-link active"><i class="bi bi-clock-history"></i><span>Peminjaman Saya</span></a>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Fitur Siswa</div>
        <div class="nav-item">
            <a href="login_anggota.php?hal=kartu" class="nav-link"><i class="bi bi-person-vcard-fill"></i><span>Kartu Anggota Digital</span></a>
        </div>
        <div class="nav-item">
            <a href="index.php" class="nav-link"><i class="bi bi-globe"></i><span>Katalog Publik</span></a>
        </div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a>
        </div>
    </div>
    <div class="sidebar-user">
        <img src="logo 40.jpeg" alt="User" onerror="this.style.display='none'">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">Siswa Anggota</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main-content">

    <!-- BANNER TERLAMBAT -->
    <?php if ($terlambat > 0): ?>
    <div class="banner banner-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Kamu memiliki <strong><?= $terlambat ?> buku terlambat dikembalikan!</strong> Segera hubungi petugas perpustakaan.
    </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-clock-history" style="font-size:1.75rem;"></i> Peminjaman Saya</h1>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <a href="?tab=aktif&f=semua" class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-text"></i></div>
            <div><div class="stat-label">Total Pinjaman</div><div class="stat-value"><?= $total_pinjam ?></div></div>
        </a>
        <a href="?tab=aktif&f=semua" class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-clock"></i></div>
            <div><div class="stat-label">Sedang Dipinjam</div><div class="stat-value"><?= $dipinjam ?></div></div>
        </a>
        <a href="?tab=aktif&f=terlambat" class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
            <div><div class="stat-label">Terlambat</div><div class="stat-value"><?= $terlambat ?></div></div>
        </a>
        <a href="?tab=riwayat&f=dikembalikan" class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-label">Dikembalikan</div><div class="stat-value"><?= $dikembalikan ?></div></div>
        </a>
    </div>

    <!-- TABS -->
    <div class="tab-wrapper">
        <div class="tab-nav">
            <button class="tab-btn <?= $tab=='aktif'?'active':'' ?>" onclick="switchTab('aktif', this)">
                <i class="bi bi-book-fill"></i>
                Sedang Dipinjam
                <span class="tab-count"><?= $dipinjam ?></span>
            </button>
            <button class="tab-btn <?= $tab=='riwayat'?'active':'' ?>" onclick="switchTab('riwayat', this)">
                <i class="bi bi-clock-history"></i>
                Riwayat Peminjaman
                <span class="tab-count"><?= $total_pinjam ?></span>
            </button>
        </div>

        <!-- ===== TAB AKTIF ===== -->
        <div class="tab-pane <?= $tab=='aktif'?'active':'' ?>" id="pane-aktif">
            <div class="filter-bar">
                <a href="?tab=aktif&f=semua<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn <?= ($tab=='aktif'&&$f=='semua')?'active':'' ?>">
                    <i class="bi bi-grid"></i> Semua
                </a>
                <a href="?tab=aktif&f=terlambat<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn red <?= ($tab=='aktif'&&$f=='terlambat')?'active':'' ?>">
                    <i class="bi bi-exclamation-triangle"></i> Terlambat
                </a>
                <div class="search-filter">
                    <form method="GET" style="display:flex;gap:0.5rem;">
                        <input type="hidden" name="tab" value="aktif">
                        <input type="hidden" name="f" value="<?= htmlspecialchars($f) ?>">
                        <input type="text" name="search" placeholder="Cari judul buku..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Buku</th>
                            <th>Lokasi Rak</th>
                            <th>Kategori</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                            <th>Sisa Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($aktif_res && $aktif_res->num_rows > 0):
                        $no = 1;
                        while ($r = $aktif_res->fetch_assoc()):
                            $tgl   = new DateTime($r['tanggal_pengembalian']);
                            $hari  = new DateTime(date('Y-m-d'));
                            $diff  = $hari->diff($tgl);
                            $sisa  = $tgl >= $hari ? (int)$diff->days : -(int)$diff->days;
                            $late  = $sisa < 0;
                            $hariLambat = $late ? abs($sisa) : 0;
                            if ($late)        { $badge='badge-terlambat'; $label='Terlambat âš ï¸'; }
                            else              { $badge='badge-dipinjam';  $label='Dipinjam'; }
                            if ($late)        $cdown = '<span style="color:#dc2626;font-weight:700;">'.$hariLambat.' hari terlambat</span>';
                            elseif ($sisa==0) $cdown = '<span style="color:#f59e0b;font-weight:700;">Hari ini!</span>';
                            elseif ($sisa<=3) $cdown = '<span style="color:#f59e0b;font-weight:700;">Sisa '.$sisa.' hari</span>';
                            else              $cdown = '<span style="color:#10b981;font-weight:600;">Sisa '.$sisa.' hari</span>';
                    ?>
                        <tr class="<?= $late ? 'row-terlambat' : '' ?>">
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($r['judul_buku']) ?></div>
                                <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($r['penulis']) ?></div>
                            </td>
                            <td><?= renderRakBadge($r['lokasi_rak'] ?? 'Rak A-1') ?></td>
                            <td><span style="background:rgba(37,99,235,0.08);color:#2563eb;padding:2px 8px;border-radius:6px;font-size:0.75rem;"><?= htmlspecialchars($r['nama_kategori'] ?? '-') ?></span></td>
                            <td><?= date('d M Y', strtotime($r['tanggal_peminjaman'])) ?></td>
                            <td>
                                <?php if ($late): ?>
                                <span style="color:#dc2626;font-weight:700;"><?= date('d M Y', strtotime($r['tanggal_pengembalian'])) ?></span>
                                <?php else: echo date('d M Y', strtotime($r['tanggal_pengembalian'])); endif; ?>
                            </td>
                            <td><span class="badge-status <?= $badge ?>"><?= $label ?></span></td>
                            <td><?= $cdown ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-book"></i>
                                <h5><?= $f=='terlambat'?'Tidak Ada Buku Terlambat':'Tidak Ada Pinjaman Aktif' ?></h5>
                                <p style="font-size:0.85rem;"><?= $f=='terlambat'?'Tidak ada buku yang terlambat. Bagus!':'Kamu tidak sedang meminjam buku apapun saat ini.' ?></p>
                                <?php if ($f!='terlambat'): ?>
                                <a href="buku_anggota.php" class="btn btn-primary btn-sm mt-2">Jelajahi Katalog Buku</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== TAB RIWAYAT ===== -->
        <div class="tab-pane <?= $tab=='riwayat'?'active':'' ?>" id="pane-riwayat">
            <div class="filter-bar">
                <a href="?tab=riwayat&f=semua<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn <?= ($tab=='riwayat'&&$f=='semua')?'active':'' ?>">
                    <i class="bi bi-grid"></i> Semua
                </a>
                <a href="?tab=riwayat&f=dipinjam<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn orange <?= ($tab=='riwayat'&&$f=='dipinjam')?'active':'' ?>">
                    <i class="bi bi-clock"></i> Dipinjam
                </a>
                <a href="?tab=riwayat&f=terlambat<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn red <?= ($tab=='riwayat'&&$f=='terlambat')?'active':'' ?>">
                    <i class="bi bi-exclamation-triangle"></i> Terlambat
                </a>
                <a href="?tab=riwayat&f=dikembalikan<?= $search?"&search=".urlencode($search):'' ?>"
                   class="filter-btn green <?= ($tab=='riwayat'&&$f=='dikembalikan')?'active':'' ?>">
                    <i class="bi bi-check2-circle"></i> Dikembalikan
                </a>
                <div class="search-filter">
                    <form method="GET" style="display:flex;gap:0.5rem;">
                        <input type="hidden" name="tab" value="riwayat">
                        <input type="hidden" name="f" value="<?= htmlspecialchars($f) ?>">
                        <input type="text" name="search" placeholder="Cari judul buku..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Buku</th>
                            <th>Kategori</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($riw_res && $riw_res->num_rows > 0):
                        $no = 1;
                        while ($r = $riw_res->fetch_assoc()):
                            $sudahKembali = $r['status_peminjaman'] === 'dikembalikan';
                            if ($sudahKembali) {
                                $badge = 'badge-dikembalikan'; $label = 'Dikembalikan';
                                $ket   = '<span style="color:#94a3b8;font-size:0.8rem;">â€”</span>';
                            } else {
                                $tgl   = new DateTime($r['tanggal_pengembalian']);
                                $hari  = new DateTime(date('Y-m-d'));
                                $diff  = $hari->diff($tgl);
                                $sisa  = $tgl >= $hari ? (int)$diff->days : -(int)$diff->days;
                                $late  = $sisa < 0;
                                if ($late) { $badge='badge-terlambat'; $label='Terlambat âš ï¸'; $ket='<span style="color:#dc2626;font-weight:700;">'.abs($sisa).' hari terlambat</span>'; }
                                else       { $badge='badge-dipinjam';  $label='Dipinjam';
                                    if ($sisa==0)      $ket='<span style="color:#f59e0b;font-weight:700;">Hari ini!</span>';
                                    elseif ($sisa<=3)  $ket='<span style="color:#f59e0b;font-weight:700;">Sisa '.$sisa.' hari</span>';
                                    else               $ket='<span style="color:#10b981;font-weight:600;">Sisa '.$sisa.' hari</span>';
                                }
                            }
                    ?>
                        <tr class="<?= $late ? 'row-terlambat' : '' ?>">
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($r['judul_buku']) ?></div>
                                <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($r['penulis']) ?></div>
                            </td>
                            <td><?= renderRakBadge($r['lokasi_rak'] ?? 'Rak A-1') ?></td>
                            <td><span style="background:rgba(37,99,235,0.08);color:#2563eb;padding:2px 8px;border-radius:6px;font-size:0.75rem;"><?= htmlspecialchars($r['nama_kategori'] ?? '-') ?></span></td>
                            <td><?= date('d M Y', strtotime($r['tanggal_peminjaman'])) ?></td>
                            <td>
                                <?php if (!$sudahKembali && $sisa < 0): ?>
                                <span style="color:#dc2626;font-weight:700;"><?= date('d M Y', strtotime($r['tanggal_pengembalian'])) ?></span>
                                <?php else: echo date('d M Y', strtotime($r['tanggal_pengembalian'])); endif; ?>
                            </td>
                            <td><span class="badge-status <?= $badge ?>"><?= $label ?></span></td>
                            <td><?= $ket ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-clock-history"></i>
                                <h5>Tidak Ada Riwayat</h5>
                                <p style="font-size:0.85rem;">Belum ada data peminjaman yang sesuai filter.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /tab-wrapper -->

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.querySelector('.mobile-toggle');
    if (window.innerWidth <= 991 && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

function switchTab(tabName, btn) {
    // Update panes
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('pane-' + tabName).classList.add('active');
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    url.searchParams.set('f', 'semua');
    url.searchParams.delete('search');
    window.history.replaceState({}, '', url);
}
</script>
</body>
</html>