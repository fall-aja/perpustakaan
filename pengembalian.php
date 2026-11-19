<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
require_once 'helper_katalog.php';
$menunggu_acc = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='menunggu'")->fetch_assoc()['c'] ?? 0;

// ==================== DETEKSI TABEL USER ====================
$tabelUser = null;
$kolomPK   = null;
$kolomNama = null;

$semuaTabel = [];
$resTabel   = $conn->query("SHOW TABLES");
while ($t = $resTabel->fetch_row()) $semuaTabel[] = $t[0];

foreach (['users', 'anggota', 'user', 'member', 'pengguna'] as $k) {
    if (in_array($k, $semuaTabel)) { $tabelUser = $k; break; }
}

if ($tabelUser) {
    $cols = [];
    $resCols = $conn->query("SHOW COLUMNS FROM `$tabelUser`");
    while ($c = $resCols->fetch_assoc()) $cols[] = $c['Field'];
    foreach (['nama', 'name', 'nama_lengkap', 'full_name', 'username'] as $cn) {
        if (in_array($cn, $cols)) { $kolomNama = $cn; break; }
    }
    foreach (['id_user', 'id_anggota', 'id', 'id_member', 'id_pengguna'] as $cp) {
        if (in_array($cp, $cols)) { $kolomPK = $cp; break; }
    }
}

// ==================== DETEKSI KOLOM STOK TABEL BUKU ====================
$kolomStok   = null;
$resBukuCols = $conn->query("SHOW COLUMNS FROM `buku`");
if ($resBukuCols) {
    $bukuCols = [];
    while ($bc = $resBukuCols->fetch_assoc()) $bukuCols[] = $bc['Field'];
    foreach (['stok', 'jumlah', 'jumlah_buku', 'stok_buku', 'qty', 'quantity', 'total'] as $ks) {
        if (in_array($ks, $bukuCols)) { $kolomStok = $ks; break; }
    }
}

// ==================== PROSES KEMBALIKAN BUKU ====================
if (isset($_GET['kembalikan'])) {
    $id        = intval($_GET['kembalikan']);
    $getPinjam = $conn->query("SELECT id_buku, id_user, tanggal_pengembalian
                               FROM peminjaman
                               WHERE id_peminjaman = $id AND status_peminjaman = 'dipinjam'");
    if ($getPinjam && $row = $getPinjam->fetch_assoc()) {
        // 1. Tambah stok buku
        if ($kolomStok) {
            $conn->query("UPDATE buku SET `$kolomStok` = `$kolomStok` + 1 WHERE id_buku = " . intval($row['id_buku']));
        }
        // 2. Update status peminjaman
        $conn->query("UPDATE peminjaman SET status_peminjaman = 'dikembalikan' WHERE id_peminjaman = $id");

        // 3. Catat ke tabel riwayat pengembalian
        $id_buku_kembali = intval($row['id_buku']);
        $id_user_kembali = intval($row['id_user']);
        $tgl_kembali     = date('Y-m-d');
        $tgl_jatuh_tempo = !empty($row['tanggal_pengembalian']) ? strtotime($row['tanggal_pengembalian']) : time();
        $tgl_sekarang    = strtotime($tgl_kembali);
        $hari_terlambat  = ($tgl_sekarang > $tgl_jatuh_tempo) ? max(0, (int)floor(($tgl_sekarang - $tgl_jatuh_tempo) / 86400)) : 0;

        $conn->query("INSERT INTO pengembalian (id_peminjaman, id_user, id_buku, tanggal_kembali, hari_terlambat) 
                      VALUES ($id, $id_user_kembali, $id_buku_kembali, '$tgl_kembali', $hari_terlambat)");
        header("Location: pengembalian.php?pesan=kembali_sukses");
    } else {
        header("Location: pengembalian.php?pesan=sudah_kembali");
    }
    exit();
}

// ==================== QUERY DATA ====================
$search        = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'dipinjam'; // Default: tampilkan yg belum dikembalikan

// Bangun JOIN ke tabel user
if ($tabelUser && $kolomPK && $kolomNama) {
    $selectNama = "u.$kolomNama AS nama_user";
    $joinStr    = "LEFT JOIN `$tabelUser` u ON p.id_user = u.$kolomPK";
    $searchCond = $search ? " AND (u.$kolomNama LIKE '%$search%' OR b.judul_buku LIKE '%$search%')" : '';
} else {
    $selectNama = "p.id_user AS nama_user";
    $joinStr    = "";
    $searchCond = $search ? " AND b.judul_buku LIKE '%$search%'" : '';
}

$filterSql = '';
if ($filter_status === 'dipinjam')     $filterSql = " AND p.status_peminjaman = 'dipinjam'";
elseif ($filter_status === 'terlambat') $filterSql = " AND p.status_peminjaman = 'dipinjam' AND p.tanggal_pengembalian < CURDATE()";
elseif ($filter_status === 'selesai')  $filterSql = " AND p.status_peminjaman = 'dikembalikan'";

$query = "SELECT p.*, $selectNama, b.judul_buku, b.penulis
          FROM peminjaman p
          $joinStr
          LEFT JOIN buku b ON p.id_buku = b.id_buku
          WHERE 1=1
          $filterSql
          $searchCond
          ORDER BY p.tanggal_pengembalian ASC";

$result = $conn->query($query);

// ==================== STATISTIK ====================
$total_dipinjam  = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam'")->fetch_assoc()['c'];
$total_terlambat = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'];
$total_hari_ini  = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian = CURDATE()")->fetch_assoc()['c'];
$total_selesai   = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dikembalikan'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
    <style>

        /* =============================================
           RESET & BASE
        ============================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }


        /* =============================================
           HEADER
        ============================================= */
        .main-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
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
            font-weight: 600;
        }

        .brand img { width: 32px; height: 32px; border-radius: 6px; object-fit: cover; }

        .search-box {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #334155;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .user-avatar:hover { opacity: 0.85; }


        /* =============================================
           LAYOUT
        ============================================= */
        .layout-wrapper {
            display: flex;
            padding-top: 60px;
            min-height: 100vh;
        }


        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            position: fixed;
            top: 60px; left: 0; bottom: 0;
            width: 260px;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section { padding: 1.5rem 0; }

        .sidebar-title {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
        }

        .nav-item { margin: 0.25rem 0.875rem; }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.15s, color 0.15s;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-link:hover { background: #1e293b; color: #f8fafc; }

        .nav-link.active {
            background: #2563eb;
            color: #ffffff;
        }

        .nav-link i { font-size: 1.1rem; width: 20px; }

        .sidebar-user {
            margin-top: auto;
            padding: 1.25rem;
            border-top: 1px solid #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #090d16;
        }

        .sidebar-user img {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: 2px solid #334155;
            object-fit: cover;
        }

        .sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.9rem; }
        .sidebar-user-role { font-size: 0.75rem; color: #94a3b8; text-transform: capitalize; }


        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
        }


        /* =============================================
           PAGE HEADER
        ============================================= */
        .page-header {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }


        /* =============================================
           STATISTIK CARDS
        ============================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.15s, border-color 0.15s;
        }

        .stat-card:hover { 
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .stat-icon.orange { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .stat-icon.red    { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .stat-icon.yellow { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .stat-icon.green  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        .stat-label { font-size: 0.8rem; color: #64748b; margin-bottom: 0.2rem; font-weight: 500; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; }


        /* =============================================
           FILTER BAR
        ============================================= */
        .filter-bar {
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.45rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid #cbd5e1;
            color: #475569;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-btn:hover  { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }

        .filter-btn.active         { background: #d97706; border-color: #d97706; color: #ffffff; }
        .filter-btn.active.red     { background: #dc2626; border-color: #dc2626; color: #ffffff; }
        .filter-btn.active.green   { background: #16a34a; border-color: #16a34a; color: #ffffff; }

        .search-filter {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-filter input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.45rem 1rem;
            font-size: 0.85rem;
            color: #0f172a;
            width: 220px;
            outline: none;
            background: #f8fafc;
            transition: border-color 0.2s, background 0.2s;
        }

        .search-filter input:focus { border-color: #2563eb; background: #ffffff; }

        .search-filter button {
            background: #1d4ed8;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 0.45rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
        }

        .search-filter button:hover {
            background: #1e40af;
        }


        /* =============================================
           TABEL
        ============================================= */
        .table-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }

        thead tr { background: #f8fafc; }

        th {
            padding: 0.9rem 1rem;
            text-align: left;
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.88rem;
            color: #1e293b;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        /* Row highlight terlambat */
        tr.row-terlambat td { background: #fef2f2; }
        tr.row-terlambat:hover td { background: #fee2e2; }


        /* =============================================
           BADGE STATUS
        ============================================= */
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-dipinjam     { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-dikembalikan { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-terlambat    { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }


        /* =============================================
           TOMBOL AKSI
        ============================================= */
        .btn-action {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-kembali       { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .btn-kembali:hover { background: #15803d; color: #ffffff; border-color: #15803d; }

        .btn-detail        { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-detail:hover  { background: #1d4ed8; color: #ffffff; border-color: #1d4ed8; }


        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            color: #94a3b8;
        }

        .empty-state i { font-size: 3rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }


        /* =============================================
           ALERT FLOATING
        ============================================= */
        .alert-floating {
            position: fixed;
            top: 75px; right: 1.5rem;
            z-index: 9999;
            min-width: 280px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }


        /* =============================================
           MOBILE TOGGLE
        ============================================= */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: #f8fafc;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
        }


        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 991px) {
            .mobile-toggle  { display: block; }
            .sidebar        { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show   { transform: translateX(0); }
            .main-content   { margin-left: 0; }
            .page-header    { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .search-filter  { margin-left: 0; }
        }

    </style>
</head>
<body>

<!-- ALERT FLOATING -->
<?php if (isset($_GET['pesan'])):
    $alertClass = 'success'; $alertIcon = 'check-circle-fill'; $alertMsg = '';
    if     ($_GET['pesan'] === 'kembali_sukses') { $alertMsg  = 'Buku berhasil dikembalikan! Stok bertambah.'; }
    elseif ($_GET['pesan'] === 'sudah_kembali')  { $alertClass = 'warning'; $alertIcon = 'exclamation-triangle-fill'; $alertMsg = 'Buku ini sudah pernah dikembalikan sebelumnya.'; }
?>
<div class="alert alert-<?= $alertClass ?> alert-dismissible fade show alert-floating" role="alert">
    <i class="bi bi-<?= $alertIcon ?> me-2"></i><?= $alertMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- HEADER -->
<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="beranda.php" class="brand">
        <img src="logo 40.jpeg" alt="Logo"><span>PerpusOnline</span>
    </a>
    <div class="search-box">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex" style="font-size:0.8rem;">
            <i class="bi bi-globe me-1"></i>Katalog Publik
        </a>
        <div class="dropdown">
            <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile_admin.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="layout-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-title">Menu Utama</div>
            <div class="nav-item"><a href="beranda.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda</span></a></div>
            <div class="nav-item"><a href="buku.php"    class="nav-link"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link active"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
            <div class="nav-item">
                <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-square"></i>
                        <span>Persetujuan</span>
                    </div>
                    <?php if (!empty($menunggu_acc) && $menunggu_acc > 0): ?>
                        <span class="sidebar-notif-badge"><?= $menunggu_acc ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-title">Lainnya</div>
            <div class="nav-item"><a href="profile_admin.php" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Admin</span></a></div>
            <div class="nav-item"><a href="tambah_kategori.php" class="nav-link"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></div>
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-globe"></i><span>Katalog Publik</span></a></div>
            <div class="nav-item"><a href="logout.php"        class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
        </div>
        <div class="sidebar-user">
            <img src="logo 40.jpeg" alt="User">
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($_SESSION['level']) ?></div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-arrow-return-left" style="font-size:1.75rem;"></i>
                Data Pengembalian
            </h1>
        </div>

        <!-- STATISTIK -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-label">Belum Dikembalikan</div>
                    <div class="stat-value"><?= $total_dipinjam ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-label">Terlambat</div>
                    <div class="stat-value"><?= $total_terlambat ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="stat-label">Jatuh Tempo Hari Ini</div>
                    <div class="stat-value"><?= $total_hari_ini ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-label">Sudah Dikembalikan</div>
                    <div class="stat-value"><?= $total_selesai ?></div>
                </div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <?php $fs = $filter_status; ?>
            <a href="pengembalian.php?status=dipinjam"  class="filter-btn <?= $fs==='dipinjam'  ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> Belum Dikembalikan
            </a>
            <a href="pengembalian.php?status=terlambat" class="filter-btn red <?= $fs==='terlambat' ? 'active red' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i> Terlambat
            </a>
            <a href="pengembalian.php?status=selesai"   class="filter-btn green <?= $fs==='selesai' ? 'active green' : '' ?>">
                <i class="bi bi-check2-circle"></i> Selesai
            </a>
            <div class="search-filter">
                <form method="GET" style="display:flex;gap:0.5rem;">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <input type="text" name="search" placeholder="Cari nama / judul buku..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <!-- TABEL -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Peminjam</th>
                            <th>Judul Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                            $today      = new DateTime();
                            $tglKembali = new DateTime($row['tanggal_pengembalian']);
                            $terlambat  = ($row['status_peminjaman'] === 'dipinjam' && $tglKembali < $today);
                            $hariLambat = $terlambat ? $today->diff($tglKembali)->days : 0;

                            if ($terlambat) {
                                $statusLabel = 'Terlambat'; $badgeClass = 'badge-terlambat'; $rowClass = 'row-terlambat';
                            } elseif ($row['status_peminjaman'] === 'dikembalikan') {
                                $statusLabel = 'Dikembalikan'; $badgeClass = 'badge-dikembalikan'; $rowClass = '';
                            } else {
                                $statusLabel = 'Dipinjam'; $badgeClass = 'badge-dipinjam'; $rowClass = '';
                            }
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td style="color:#94a3b8;font-size:0.8rem;"><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:600;">
                                    <?= $row['nama_user']
                                        ? htmlspecialchars($row['nama_user'])
                                        : '<span style="color:#94a3b8;">User #'.$row['id_user'].'</span>' ?>
                                </div>
                                <div style="font-size:0.75rem;color:#94a3b8;">ID Pinjam: #<?= $row['id_peminjaman'] ?></div>
                            </td>
                            <td>
                                <div style="font-weight:500;"><?= htmlspecialchars($row['judul_buku'] ?? '-') ?></div>
                                <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($row['penulis'] ?? '') ?></div>
                            </td>
                            <td style="color:#64748b;"><?= date('d M Y', strtotime($row['tanggal_peminjaman'])) ?></td>
                            <td>
                                <?php if ($terlambat): ?>
                                    <span style="color:#dc2626;font-weight:700;">
                                        <?= date('d M Y', strtotime($row['tanggal_pengembalian'])) ?>
                                    </span>
                                <?php else: ?>
                                    <?= date('d M Y', strtotime($row['tanggal_pengembalian'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $badgeClass ?>">
                                    <?= $statusLabel ?><?= $terlambat ? ' âš ï¸' : '' ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                    <?php if ($row['status_peminjaman'] === 'dipinjam'): ?>
                                        <a href="pengembalian.php?kembalikan=<?= $row['id_peminjaman'] ?>&status=<?= $filter_status ?>"
                                           class="btn-action btn-kembali"
                                           onclick="return confirm('Konfirmasi pengembalian buku\n<?= addslashes($row['judul_buku'] ?? '') ?>\noleh <?= addslashes($row['nama_user'] ?? '') ?>?')">
                                            <i class="bi bi-arrow-return-left"></i> Kembalikan
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size:0.78rem;color:#10b981;font-weight:600;">
                                            <i class="bi bi-check-circle"></i> Selesai
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-arrow-return-left"></i>
                                <h5>Tidak Ada Data</h5>
                                <p style="font-size:0.85rem;">
                                    <?php
                                    if ($filter_status === 'dipinjam')     echo 'Semua buku sudah dikembalikan.';
                                    elseif ($filter_status === 'terlambat') echo 'Tidak ada peminjaman yang terlambat.';
                                    else                                    echo 'Belum ada buku yang dikembalikan.';
                                    ?>
                                </p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.querySelector('.mobile-toggle');
        if (window.innerWidth <= 991 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });

    // Auto-dismiss alert setelah 4 detik
    setTimeout(() => {
        const al = document.querySelector('.alert-floating');
        if (al) {
            al.style.transition = 'opacity 0.5s';
            al.style.opacity = '0';
            setTimeout(() => al.remove(), 500);
        }
    }, 4000);
</script>
</body>
</html>
<?php $conn->close(); ?>