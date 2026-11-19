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

// ==================== HAPUS BUKU ====================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $conn->query("DELETE FROM buku WHERE id_buku = $id");
    header("Location: buku.php");
    exit();
}

// ==================== FILTER KATEGORI ====================
$kategori_filter = isset($_GET['kategori']) ? $conn->real_escape_string(trim($_GET['kategori'])) : '';
$search          = isset($_GET['search'])   ? $conn->real_escape_string(trim($_GET['search']))   : '';

$where = "WHERE 1=1";
if ($kategori_filter !== '') {
    $where .= " AND LOWER(k.nama_kategori) = LOWER('$kategori_filter')";
}
if ($search !== '') {
    $where .= " AND (b.judul_buku LIKE '%$search%' OR b.penulis LIKE '%$search%')";
}

$query = "SELECT b.*, k.nama_kategori
          FROM buku b
          LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
          $where
          ORDER BY b.id_buku DESC";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $judul   = $conn->real_escape_string(trim($_POST['judul']));
    $penulis = $conn->real_escape_string(trim($_POST['penulis']));
    $penerbit = $conn->real_escape_string(trim($_POST['penerbit']));
    $tahun   = intval($_POST['tahun']);
    $stok    = intval($_POST['stok']); // Tambah stok
    
    $conn->query("INSERT INTO buku (judul, penulis, penerbit, tahun, stok) 
                  VALUES ('$judul','$penulis','$penerbit','$tahun','$stok')");
    
    header("Location: buku.php?pesan=tambah_sukses");
    exit();
}

$result = $conn->query($query);
if (!$result) die("Query Error: " . $conn->error);

// Ambil semua kategori untuk tombol filter dinamis
$kategoriList = $conn->query("SELECT DISTINCT nama_kategori FROM kategori ORDER BY nama_kategori ASC");

// Statistik total buku
$total_buku = $conn->query("SELECT COUNT(*) AS c FROM buku")->fetch_assoc()['c'];

// â”€â”€ Generate cover SVG otomatis â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function makeCoverSVG($judul, $penulis, $kNama) {
    $k = strtolower($kNama ?? '');
    if (strpos($k,'fiksi')!==false && strpos($k,'non')===false)
        $p = ['#4c1d95','#6d28d9','#ddd6fe'];
    elseif (strpos($k,'non')!==false)
        $p = ['#1e3a5f','#1d4ed8','#bfdbfe'];
    elseif (strpos($k,'pendidikan')!==false)
        $p = ['#064e3b','#059669','#a7f3d0'];
    elseif (strpos($k,'biografi')!==false)
        $p = ['#78350f','#d97706','#fde68a'];
    elseif (strpos($k,'sejarah')!==false)
        $p = ['#7f1d1d','#dc2626','#fecaca'];
    else {
        $palettes = [
            ['#312e81','#4f46e5','#c7d2fe'],
            ['#134e4a','#0f766e','#99f6e4'],
            ['#4a044e','#a21caf','#f5d0fe'],
            ['#422006','#c2410c','#fed7aa'],
        ];
        $p = $palettes[abs(crc32($judul)) % count($palettes)];
    }
    [$bg1, $bg2, $acc] = $p;

    $lines = []; $cur2 = '';
    foreach (explode(' ', $judul) as $w) {
        if ($cur2 !== '' && strlen($cur2.' '.$w) > 16) { $lines[] = $cur2; $cur2 = $w; }
        else $cur2 = $cur2 === '' ? $w : $cur2.' '.$w;
    }
    $lines[] = $cur2;
    $lines   = array_slice($lines, 0, 4);

    $pen  = strlen($penulis) > 20 ? substr($penulis,0,20).'â€¦' : $penulis;
    $uid  = 'g'.abs(crc32($judul.$kNama));
    $seed = abs(crc32($judul)) % 100;
    $lbl  = strlen($kNama) > 12 ? substr($kNama,0,12) : ($kNama ?: 'Buku');

    $decor = '';
    for ($i=0;$i<6;$i++) {
        $yy = 20+$i*22; $x2 = 55+($seed%35);
        $decor .= "<line x1='0' y1='{$yy}' x2='{$x2}' y2='".($yy+18)."' stroke='{$acc}' stroke-opacity='0.15' stroke-width='1.2'/>";
    }

    $judulSVG = '';
    $startY   = 64 - (count($lines)-1)*9;
    foreach ($lines as $ii=>$ln) {
        $yj = $startY + $ii*18;
        $judulSVG .= "<text x='8' y='{$yj}' font-size='11' font-weight='bold' fill='#fff' font-family='system-ui,sans-serif'>".htmlspecialchars($ln)."</text>";
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 140" width="42" height="58" style="display:block;border-radius:5px;flex-shrink:0;">'
        .'<defs><linearGradient id="'.$uid.'" x1="0" y1="0" x2="0.3" y2="1">'
        .'<stop offset="0%" stop-color="'.$bg2.'"/><stop offset="100%" stop-color="'.$bg1.'"/>'
        .'</linearGradient></defs>'
        .'<rect width="100" height="140" rx="5" fill="url(#'.$uid.')"/>'
        .'<rect x="0" y="0" width="5" height="140" rx="2.5" fill="'.$acc.'" opacity="0.6"/>'
        .$decor
        .'<rect x="8" y="10" width="44" height="2.5" rx="1.2" fill="'.$acc.'" opacity="0.65"/>'
        .'<rect x="8" y="16" width="30" height="10" rx="2" fill="'.$acc.'" opacity="0.18"/>'
        .'<text x="11" y="24" font-size="7" fill="'.$acc.'" font-family="system-ui,sans-serif" font-weight="bold" opacity="0.9">'.htmlspecialchars($lbl).'</text>'
        .$judulSVG
        .'<line x1="8" y1="107" x2="92" y2="107" stroke="'.$acc.'" stroke-opacity="0.3" stroke-width="0.7"/>'
        .'<text x="8" y="118" font-size="7.5" fill="'.$acc.'" font-family="system-ui,sans-serif" opacity="0.8">'.htmlspecialchars($pen).'</text>'
        .'</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Buku | <?= NAMA_PERPUS ?></title>
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
           HEADER - Solid Slate
        ============================================= */
        .main-header {
            position: fixed;
            top: 0; left: 0; right: 0;
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

        .brand img { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #334155; }

        .header-right {
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
            transition: border-color 0.2s;
        }

        .user-avatar:hover { border-color: #60a5fa; }


        /* =============================================
           LAYOUT
        ============================================= */
        .layout-wrapper {
            display: flex;
            padding-top: 60px;
            min-height: 100vh;
        }


        /* =============================================
           SIDEBAR - Solid Slate
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

        .sidebar-section { padding: 1.25rem 0; }

        .sidebar-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0 1.5rem;
            margin-bottom: 0.65rem;
        }

        .nav-item { margin: 0.2rem 0.85rem; }

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

        .nav-link:hover { background: #1e293b; color: #ffffff; }

        .nav-link.active {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            box-shadow: none;
        }

        .nav-link i { font-size: 1.1rem; width: 20px; }

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
            width: 38px; height: 38px;
            border-radius: 50%;
            border: 2px solid #334155;
            object-fit: cover;
        }

        .sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.88rem; }
        .sidebar-user-role { font-size: 0.75rem; color: #64748b; text-transform: capitalize; }


        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem 2.25rem;
        }


        /* =============================================
           PAGE HEADER
        ============================================= */
        .page-header {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .page-title i { color: #1d4ed8; }

        .page-title .total-badge {
            font-size: 0.78rem;
            font-weight: 600;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            margin-left: 0.25rem;
        }

        .btn-tambah {
            background: #1d4ed8;
            color: #ffffff;
            border: 1px solid #1d4ed8;
            border-radius: 8px;
            padding: 0.55rem 1.2rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: background-color 0.15s ease;
            white-space: nowrap;
        }

        .btn-tambah:hover {
            background: #1e40af;
            border-color: #1e40af;
            color: white;
        }


        /* =============================================
           FILTER BAR
        ============================================= */
        .filter-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.9rem 1.35rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 0.25rem;
        }

        .filter-btn {
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #ffffff;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .filter-btn:hover {
            border-color: #1d4ed8;
            color: #1d4ed8;
            background: #eff6ff;
        }

        .filter-btn.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
            font-weight: 600;
        }

        /* Solid per-category active colors (No neon AI colors) */
        .filter-btn.aktif-fiksi        { background: #2563eb; border-color: #2563eb; color: #ffffff; }
        .filter-btn.aktif-non-fiksi    { background: #0284c7; border-color: #0284c7; color: #ffffff; }
        .filter-btn.aktif-pendidikan   { background: #15803d; border-color: #15803d; color: #ffffff; }
        .filter-btn.aktif-biografi     { background: #b45309; border-color: #b45309; color: #ffffff; }

        .search-wrap {
            margin-left: auto;
            display: flex;
            gap: 0.4rem;
        }

        .search-wrap input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.4rem 0.85rem;
            font-size: 0.85rem;
            color: #0f172a;
            width: 220px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .search-wrap input:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }

        .search-wrap button {
            background: #1d4ed8;
            color: #ffffff;
            border: 1px solid #1d4ed8;
            border-radius: 8px;
            padding: 0.4rem 0.85rem;
            font-size: 0.88rem;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .search-wrap button:hover {
            background: #1e40af;
        }


        /* =============================================
           TABEL BUKU
        ============================================= */
        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr { background: #f8fafc; }

        th {
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.75rem;
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
            font-size: 0.875rem;
            color: #1e293b;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }


        /* =============================================
           COVER THUMBNAIL
        ============================================= */
        .cover-thumb {
            width: 42px;
            height: 58px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
            display: block;
        }

        .cover-thumb-wrap {
            flex-shrink: 0;
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            width: 42px;
            height: 58px;
        }

        .cover-placeholder {
            width: 42px;
            height: 58px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .book-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .book-title-text {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.35;
        }

        .book-author-text {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 0.15rem;
        }


        /* =============================================
           BADGE KATEGORI - Clean Solid Tints
        ============================================= */
        .badge-kat {
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.73rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-block;
        }

        .kat-non-fiksi  { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .kat-fiksi      { background: #f5f3ff; color: #4f46e5; border: 1px solid #ede9fe; }
        .kat-pendidikan { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .kat-biografi   { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
        .kat-default    { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }


        /* =============================================
           STOK INDICATOR
        ============================================= */
        .stok-ok   { color: #15803d; font-weight: 600; }
        .stok-low  { color: #b45309; font-weight: 600; }
        .stok-zero { color: #b91c1c; font-weight: 600; }


        /* =============================================
           TOMBOL AKSI
        ============================================= */
        .btn-act {
            padding: 0.32rem 0.7rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s ease;
        }

        .btn-edit-act  { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
        .btn-edit-act:hover  { background: #1d4ed8; color: #ffffff; }

        .btn-del-act   { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .btn-del-act:hover   { background: #dc2626; color: #ffffff; }


        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            color: #94a3b8;
        }

        .empty-state i    { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: #cbd5e1; }
        .empty-state h5   { font-size: 1rem; color: #475569; margin-bottom: 0.35rem; }
        .empty-state p    { font-size: 0.83rem; }


        /* =============================================
           ALERT FLOATING
        ============================================= */
        .alert-floating {
            position: fixed;
            top: 72px; right: 1.5rem;
            z-index: 9999;
            min-width: 270px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            animation: slideIn 0.25s ease;
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
            font-size: 1.4rem;
            cursor: pointer;
            margin-right: 1rem;
        }


        /* =============================================
           RESPONSIVE
        ============================================= */
        @media (max-width: 991px) {
            .mobile-toggle  { display: block; }
            .sidebar        { transform: translateX(-100%); transition: transform 0.25s ease; }
            .sidebar.show   { transform: translateX(0); }
            .main-content   { margin-left: 0; padding: 1.5rem 1rem; }
            .search-wrap    { margin-left: 0; }
            .filter-bar     { flex-wrap: wrap; }
        }

        @media (max-width: 576px) {
            .page-header { flex-direction: column; align-items: flex-start; }
        }

    </style>
</head>
<body>

<!-- HEADER -->
<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="beranda.php" class="brand">
        <img src="logo 40.jpeg" alt="Logo">
        <span>PerpusOnline</span>
    </a>
    <div class="header-right">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex me-2" style="font-size:0.8rem;">
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
            <div class="nav-item"><a href="buku.php" class="nav-link active"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
            <div class="nav-item">
                <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-square"></i><span>Persetujuan</span>
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
            <div class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
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
                <i class="bi bi-book-fill"></i>
                Data Buku
                <span class="total-badge"><?= $total_buku ?> buku</span>
            </h1>
            <a href="tambahbuku.php" class="btn-tambah">
                <i class="bi bi-plus-lg"></i> Tambah Buku
            </a>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <span class="filter-label"><i class="bi bi-funnel me-1"></i>Kategori</span>

            <?php
            // Tentukan kelas aktif per tombol
            $kLower = strtolower($kategori_filter);
            $aktifSemua = ($kategori_filter === '') ? 'active' : '';
            ?>
            <a href="buku.php<?= $search ? '?search='.urlencode($search) : '' ?>"
               class="filter-btn <?= $aktifSemua ?>">
                <i class="bi bi-grid-3x3-gap"></i> Semua
            </a>

            <?php
            // Render tombol filter per kategori dari DB
            if ($kategoriList && $kategoriList->num_rows > 0):
                while ($kat = $kategoriList->fetch_assoc()):
                    $kNama   = $kat['nama_kategori'];
                    $kSlug   = strtolower($kNama);
                    $isAktif = ($kLower === $kSlug);

                    // Pilih icon per kategori
                    $icon = 'bi-tag';
                    if (strpos($kSlug, 'fiksi') !== false && strpos($kSlug, 'non') === false) $icon = 'bi-book-half';
                    elseif (strpos($kSlug, 'non') !== false)    $icon = 'bi-journal-text';
                    elseif (strpos($kSlug, 'pendidikan') !== false) $icon = 'bi-mortarboard';
                    elseif (strpos($kSlug, 'biografi') !== false)   $icon = 'bi-person-lines-fill';

                    // Kelas warna aktif
                    $aktifKelas = '';
                    if ($isAktif) {
                        if (strpos($kSlug, 'non') !== false)        $aktifKelas = 'aktif-non-fiksi';
                        elseif (strpos($kSlug, 'fiksi') !== false)  $aktifKelas = 'aktif-fiksi';
                        elseif (strpos($kSlug, 'pendidikan') !== false) $aktifKelas = 'aktif-pendidikan';
                        elseif (strpos($kSlug, 'biografi') !== false)   $aktifKelas = 'aktif-biografi';
                        else $aktifKelas = 'active';
                    }

                    $href = 'buku.php?kategori=' . urlencode($kNama);
                    if ($search) $href .= '&search=' . urlencode($search);
            ?>
            <a href="<?= $href ?>" class="filter-btn <?= $aktifKelas ?>">
                <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($kNama) ?>
            </a>
            <?php endwhile; endif; ?>

            <!-- Search -->
            <div class="search-wrap">
                <form method="GET" style="display:flex;gap:0.4rem;">
                    <?php if ($kategori_filter): ?>
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_filter) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" placeholder="Cari judul / penulis..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <!-- CARD GRID BUKU -->
        <style>
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        .book-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
            display: flex;
            flex-direction: column;
        }

        .book-card:hover {
            transform: translateY(-3px);
            border-color: #cbd5e1;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }

        .book-card-cover {
            position: relative;
            height: 260px;
            overflow: hidden;
            flex-shrink: 0;
            background-color: #f1f5f9;
        }

        .book-card-cover img {
            width: 100%; 
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .book-card-cover .cover-gen {
            width: 100%; 
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
            gap: 0.6rem;
            position: relative;
        }

        .book-card-cover .cover-gen::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 14px;
            background: rgba(0,0,0,0.2);
            border-right: 1px solid rgba(255,255,255,0.15);
        }

        .cover-gen .cg-title {
            font-weight: 700;
            font-size: 1.05rem;
            line-height: 1.35;
            color: #ffffff;
            word-break: break-word;
            max-width: 100%;
            letter-spacing: -0.2px;
        }

        .cover-gen .cg-author {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.8);
        }

        .stok-pill {
            position: absolute;
            top: 10px; 
            right: 10px;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .stok-pill.sp-ok   { background: #15803d; }
        .stok-pill.sp-low  { background: #b45309; }
        .stok-pill.sp-zero { background: #b91c1c; }

        .book-card-body {
            padding: 1rem 1.15rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .bc-kat {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            width: fit-content;
            border: 1px solid transparent;
        }

        .bc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .bc-author {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: 0.9rem;
        }

        .bc-actions {
            margin-top: auto;
            display: flex;
            gap: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
        }

        .bc-btn-edit {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.45rem 0.7rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            transition: all 0.15s ease;
        }

        .bc-btn-edit:hover { 
            background: #1d4ed8; 
            color: #ffffff; 
        }

        .bc-btn-del {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px; 
            height: 34px;
            border-radius: 6px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fee2e2;
            text-decoration: none;
            font-size: 0.85rem;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }

        .bc-btn-del:hover { 
            background: #dc2626; 
            color: #ffffff; 
        }
        </style>

        <div class="books-grid">
        <?php
        // Helper warna kategori (Solid and clean)
        function katClr2($k) {
            $k = strtolower($k ?? '');
            if (strpos($k,'novel')!==false)                                  return ['#0f766e','#f0fdfa','#ccfbf1'];
            if (strpos($k,'sains')!==false)                                  return ['#0369a1','#f0f9ff','#e0f2fe'];
            if (strpos($k,'fiksi')!==false && strpos($k,'non')===false)      return ['#4338ca','#eef2ff','#e0e7ff'];
            if (strpos($k,'non')!==false)                                    return ['#1d4ed8','#eff6ff','#dbeafe'];
            if (strpos($k,'pendidikan')!==false)                             return ['#15803d','#f0fdf4','#dcfce7'];
            if (strpos($k,'biografi')!==false)                               return ['#b45309','#fffbeb','#fef3c7'];
            if (strpos($k,'sejarah')!==false)                                return ['#b91c1c','#fef2f2','#fee2e2'];
            return ['#334155','#f8fafc','#e2e8f0'];
        }
        // Helper warna solid cover buku (Solid classic library book tones, no gradient)
        function coverColor2($k, $judul) {
            $k = strtolower($k ?? '');
            if (strpos($k,'novel')!==false)                              return '#115e59';
            if (strpos($k,'sains')!==false)                              return '#0369a1';
            if (strpos($k,'fiksi')!==false && strpos($k,'non')===false)  return '#3730a3';
            if (strpos($k,'non')!==false)                                return '#1e40af';
            if (strpos($k,'pendidikan')!==false)                         return '#166534';
            if (strpos($k,'biografi')!==false)                           return '#9a3412';
            if (strpos($k,'sejarah')!==false)                            return '#991b1b';
            $p=['#1e293b','#1e3a8a','#064e3b','#78350f','#831843'];
            return $p[abs(crc32($judul))%count($p)];
        }

        if ($result && $result->num_rows > 0):
            while ($buku = $result->fetch_assoc()):
                $kNama   = $buku['nama_kategori'] ?? 'Umum';
                $stok    = intval($buku['stok_tersedia'] ?? $buku['stok'] ?? 0);
                $lokasi  = $buku['lokasi_rak'] ?? 'Rak A-1';
        ?>
            <div class="book-card">
                <div class="book-card-cover">
                    <?= renderBookCover($buku['cover'], $buku['judul_buku'], $buku['penulis'], $kNama, $lokasi) ?>
                </div>

                <div class="book-card-body">
                    <span class="bc-kat">
                        <?= htmlspecialchars($kNama) ?>
                    </span>
                    <div class="bc-title"><?= htmlspecialchars($buku['judul_buku']) ?></div>
                    <div class="bc-author">
                        <i class="bi bi-person" style="font-size:0.68rem;"></i>
                        <?= htmlspecialchars($buku['penulis'] ?? '-') ?>
                    </div>

                    <div class="d-flex align-items-center justify-content-between my-2">
                        <?= renderRakBadge($lokasi) ?>
                        <?= renderStockBadge($stok) ?>
                    </div>

                    <div class="bc-actions mt-auto">
                        <a href="edit_buku.php?id=<?= $buku['id_buku'] ?>" class="bc-btn-edit">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </a>
                        <a href="buku.php?hapus=<?= $buku['id_buku'] ?>" class="bc-btn-del"
                           onclick="return confirm('Yakin hapus buku ini?')" title="Hapus">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="empty-state" style="grid-column:1/-1;">
                <i class="bi bi-book"></i>
                <h5>Tidak Ada Buku</h5>
                <p>Belum ada buku dalam kategori ini<?= $search ? ' atau hasil pencarian "<strong>'.htmlspecialchars($search).'</strong>"' : '' ?>.</p>
            </div>
        <?php endif; ?>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    document.addEventListener('click', function(e) {
        const sidebar  = document.getElementById('sidebar');
        const toggle   = document.querySelector('.mobile-toggle');
        if (window.innerWidth <= 991 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
</script>
</body>
</html>
<?php $conn->close(); ?>