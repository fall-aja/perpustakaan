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

// ── Generate cover SVG otomatis ──────────────────────────────
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

    $pen  = strlen($penulis) > 20 ? substr($penulis,0,20).'…' : $penulis;
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
    <title>Data Buku - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>

        /* =============================================
           RESET & BASE
        ============================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            background: #f1f5f9;
        }


        /* =============================================
           HEADER
        ============================================= */
        .main-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: linear-gradient(135deg, #2d56b6 0%, #4172c1 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        .brand img { width: 32px; height: 32px; border-radius: 50%; }

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
            border: 2px solid rgba(255,255,255,0.7);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-avatar:hover { transform: scale(1.05); }


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
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            border-right: 1px solid rgba(255,255,255,0.05);
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section { padding: 1.5rem 0; }

        .sidebar-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
        }

        .nav-item { margin: 0.25rem 1rem; }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(245,245,245,0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-link:hover { background: rgba(255,255,255,0.05); color: white; }

        .nav-link.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }

        .nav-link i { font-size: 1.1rem; width: 20px; }

        .sidebar-user {
            margin-top: auto;
            padding: 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(0,0,0,0.2);
        }

        .sidebar-user img {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(227,227,227,0.8);
        }

        .sidebar-user-name { font-weight: 600; color: white; font-size: 0.9rem; }
        .sidebar-user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); }


        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }


        /* =============================================
           PAGE HEADER
        ============================================= */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.75rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .page-title i { color: #2563eb; }

        .page-title .total-badge {
            font-size: 0.8rem;
            font-weight: 600;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            margin-left: 0.25rem;
        }

        .btn-tambah {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.55rem 1.2rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-tambah:hover {
            opacity: 0.9;
            color: white;
            transform: translateY(-1px);
        }


        /* =============================================
           FILTER BAR
        ============================================= */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .filter-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-right: 0.25rem;
        }

        .filter-btn {
            padding: 0.35rem 0.9rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border: 1.5px solid #e2e8f0;
            color: #475569;
            background: transparent;
            transition: all 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .filter-btn:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }

        .filter-btn.active {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }

        /* Warna aktif per kategori */
        .filter-btn.aktif-fiksi        { background: #8b5cf6; border-color: #8b5cf6; color: white; }
        .filter-btn.aktif-non-fiksi    { background: #3b82f6; border-color: #3b82f6; color: white; }
        .filter-btn.aktif-pendidikan   { background: #10b981; border-color: #10b981; color: white; }
        .filter-btn.aktif-biografi     { background: #f59e0b; border-color: #f59e0b; color: white; }

        .search-wrap {
            margin-left: auto;
            display: flex;
            gap: 0.4rem;
        }

        .search-wrap input {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.35rem 0.9rem;
            font-size: 0.83rem;
            color: #1e293b;
            width: 200px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-wrap input:focus { border-color: #2563eb; }

        .search-wrap button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
            font-size: 0.9rem;
            cursor: pointer;
        }


        /* =============================================
           TABEL BUKU
        ============================================= */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
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
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #e2e8f0;
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
        tr:hover td { background: #fafbff; }


        /* =============================================
           COVER THUMBNAIL
        ============================================= */
        .cover-thumb {
            width: 42px;
            height: 58px;
            border-radius: 5px;
            object-fit: cover;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            flex-shrink: 0;
            display: block;
        }

        .cover-thumb-wrap {
            flex-shrink: 0;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            width: 42px;
            height: 58px;
        }

        .cover-placeholder {
            width: 42px;
            height: 58px;
            border-radius: 5px;
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
            color: #1e293b;
            line-height: 1.35;
        }

        .book-author-text {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 0.15rem;
        }


        /* =============================================
           BADGE KATEGORI
        ============================================= */
        .badge-kat {
            padding: 0.25rem 0.65rem;
            border-radius: 12px;
            font-size: 0.73rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-block;
        }

        .kat-non-fiksi  { background: rgba(59,130,246,0.1);  color: #2563eb; }
        .kat-fiksi      { background: rgba(139,92,246,0.1);  color: #7c3aed; }
        .kat-pendidikan { background: rgba(16,185,129,0.1);  color: #059669; }
        .kat-biografi   { background: rgba(245,158,11,0.1);  color: #d97706; }
        .kat-default    { background: rgba(100,116,139,0.1); color: #475569; }


        /* =============================================
           STOK INDICATOR
        ============================================= */
        .stok-ok   { color: #059669; font-weight: 600; }
        .stok-low  { color: #d97706; font-weight: 600; }
        .stok-zero { color: #dc2626; font-weight: 600; }


        /* =============================================
           TOMBOL AKSI
        ============================================= */
        .btn-act {
            padding: 0.28rem 0.65rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.18s;
        }

        .btn-edit-act  { background: rgba(37,99,235,0.1);  color: #2563eb; }
        .btn-edit-act:hover  { background: #2563eb; color: white; }

        .btn-del-act   { background: rgba(239,68,68,0.1);  color: #dc2626; }
        .btn-del-act:hover   { background: #ef4444; color: white; }


        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            color: #94a3b8;
        }

        .empty-state i    { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }
        .empty-state h5   { font-size: 1rem; color: #64748b; margin-bottom: 0.35rem; }
        .empty-state p    { font-size: 0.83rem; }


        /* =============================================
           ALERT FLOATING
        ============================================= */
        .alert-floating {
            position: fixed;
            top: 72px; right: 1.5rem;
            z-index: 9999;
            min-width: 270px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
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
            color: white;
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
        <div class="dropdown">
            <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile_admin.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
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
            <div class="nav-item"><a href="persetujuan_admin.php" class="nav-link"><i class="bi bi-check2-square"></i><span>Persetujuan</span></a></div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-title">Lainnya</div>
            <div class="nav-item"><a href="profile_admin.php" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Admin</span></a></div>
            <div class="nav-item"><a href="tambah_kategori.php" class="nav-link"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></div>
            <div class="nav-item"><a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
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
            gap: 1.5rem;
        }

        .book-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }

.book-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.15);
}

.book-card-cover {
    position: relative;
    height: 280px;  /* Lebih tinggi untuk rasio buku asli */
    overflow: hidden;
    flex-shrink: 0;
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
}

.cover-gen .cg-title {
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1.4;
    color: #fff;
    word-break: break-word;
    max-width: 100%;
}

.cover-gen .cg-author {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.7);
}

.stok-pill {
    position: absolute;
    top: 12px; 
    right: 12px;
    padding: 0.3rem 0.7rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.stok-pill.sp-ok   { background: rgba(5,150,105,0.9); }
.stok-pill.sp-low  { background: rgba(217,119,6,0.9); }
.stok-pill.sp-zero { background: rgba(220,38,38,0.9); }

.book-card-body {
    padding: 1.2rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.bc-kat {
    display: inline-block;
    padding: 0.25rem 0.7rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 0.6rem;
    width: fit-content;
}

.bc-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    margin-bottom: 0.3rem;
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
    margin-bottom: 1rem;
}

.bc-actions {
    margin-top: auto;
    display: flex;
    gap: 0.5rem;
    padding-top: 0.8rem;
    border-top: 1px solid #f1f5f9;
}

.bc-btn-edit {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.5rem 0.7rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    background: rgba(37,99,235,0.1);
    color: #2563eb;
    transition: all 0.2s;
}

.bc-btn-edit:hover { 
    background: #2563eb; 
    color: #fff; 
}

.bc-btn-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px; 
    height: 38px;
    border-radius: 8px;
    background: rgba(239,68,68,0.1);
    color: #dc2626;
    text-decoration: none;
    font-size: 0.9rem;
    flex-shrink: 0;
    transition: all 0.2s;
}

.bc-btn-del:hover { 
    background: #ef4444; 
    color: #fff; 
}
</style>

        <div class="books-grid">
        <?php
        // Helper warna kategori
        function katClr2($k) {
            $k = strtolower($k ?? '');
            if (strpos($k,'novel')!==false)                                  return ['#10b981','rgba(16,185,129,0.13)'];
            if (strpos($k,'sains')!==false)                                  return ['#f59e0b','rgba(245,158,11,0.13)'];
            if (strpos($k,'fiksi')!==false && strpos($k,'non')===false)      return ['#8b5cf6','rgba(139,92,246,0.13)'];
            if (strpos($k,'non')!==false)                                    return ['#3b82f6','rgba(59,130,246,0.13)'];
            if (strpos($k,'pendidikan')!==false)                             return ['#059669','rgba(5,150,105,0.13)'];
            if (strpos($k,'biografi')!==false)                               return ['#d97706','rgba(217,119,6,0.13)'];
            if (strpos($k,'sejarah')!==false)                                return ['#dc2626','rgba(220,38,38,0.13)'];
            return ['#6366f1','rgba(99,102,241,0.13)'];
        }
        // Helper gradien cover
        function coverGrad2($k, $judul) {
            $k = strtolower($k ?? '');
            if (strpos($k,'novel')!==false)                              return ['#134e4a','#0d9488'];
            if (strpos($k,'sains')!==false)                              return ['#78350f','#d97706'];
            if (strpos($k,'fiksi')!==false && strpos($k,'non')===false)  return ['#4c1d95','#7c3aed'];
            if (strpos($k,'non')!==false)                                return ['#1e3a5f','#1d4ed8'];
            if (strpos($k,'pendidikan')!==false)                         return ['#064e3b','#059669'];
            if (strpos($k,'biografi')!==false)                           return ['#78350f','#c2410c'];
            if (strpos($k,'sejarah')!==false)                            return ['#7f1d1d','#dc2626'];
            $p=[['#1e1b4b','#4338ca'],['#134e4a','#0f766e'],['#4a044e','#a21caf'],['#0c4a6e','#0369a1']];
            return $p[abs(crc32($judul))%count($p)];
        }

        if ($result && $result->num_rows > 0):
            while ($buku = $result->fetch_assoc()):
                $kNama   = $buku['nama_kategori'] ?? 'Umum';
                $stok    = intval($buku['stok_tersedia'] ?? $buku['stok'] ?? 0);
                $stokT   = intval($buku['stok_total'] ?? $buku['stok'] ?? 0);
                $hasCover = !empty($buku['cover']) && file_exists('covers/'.$buku['cover']);
                [$kc, $kb] = katClr2($kNama);
                [$g1, $g2] = coverGrad2($kNama, $buku['judul_buku']);
                $spCls = $stok === 0 ? 'sp-zero' : ($stok <= 3 ? 'sp-low' : 'sp-ok');
        ?>
            <div class="book-card">
                <div class="book-card-cover">
                    <?php if ($hasCover): ?>
                        <img src="covers/<?= htmlspecialchars($buku['cover']) ?>"
                             alt="<?= htmlspecialchars($buku['judul_buku']) ?>">
                    <?php else: ?>
                        <div class="cover-gen"
                             style="background:linear-gradient(155deg,<?= $g1 ?>,<?= $g2 ?>);">
                            <div class="cg-title"><?= htmlspecialchars($buku['judul_buku']) ?></div>
                            <div class="cg-author"><?= htmlspecialchars($buku['penulis'] ?? '') ?></div>
                        </div>
                    <?php endif; ?>
                    <span class="stok-pill <?= $spCls ?>"><?= $stokT ?> eks</span>
                </div>

                <div class="book-card-body">
                    <span class="bc-kat" style="color:<?= $kc ?>;background:<?= $kb ?>;">
                        <?= htmlspecialchars($kNama) ?>
                    </span>
                    <div class="bc-title"><?= htmlspecialchars($buku['judul_buku']) ?></div>
                    <div class="bc-author">
                        <i class="bi bi-person" style="font-size:0.68rem;"></i>
                        <?= htmlspecialchars($buku['penulis'] ?? '-') ?>
                    </div>
                    <div class="bc-actions">
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