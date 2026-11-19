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

// ==================== PROSES PENGAJUAN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_buku     = intval($_POST['id_buku']);
    $durasi_hari = max(1, min(30, intval($_POST['durasi_hari'])));
    $alasan      = $conn->real_escape_string(trim($_POST['alasan'] ?? ''));

    // Cek stok
    $stok_res = $conn->query("SELECT stok FROM buku WHERE id_buku = $id_buku");
    if (!$stok_res || $stok_res->num_rows === 0 || intval($stok_res->fetch_assoc()['stok']) <= 0) {
        header("Location: pengajuan_pinjam.php?pesan=stok");
        $conn->close(); exit();
    }

    // Cek sudah punya pengajuan menunggu untuk buku yang sama
    $cek = $conn->query("SELECT id_permintaan FROM persetujuan WHERE id_user=$id_user AND id_buku=$id_buku AND status='menunggu' LIMIT 1");
    if ($cek && $cek->num_rows > 0) {
        header("Location: pengajuan_pinjam.php?pesan=sudah");
        $conn->close(); exit();
    }

    $tgl = date('Y-m-d');
    $conn->query("INSERT INTO persetujuan (id_user, id_buku, tanggal_pengajuan, durasi_hari, alasan, status)
                  VALUES ($id_user, $id_buku, '$tgl', $durasi_hari, '$alasan', 'menunggu')");
    header("Location: pengajuan_pinjam.php?pesan=sukses");
    $conn->close(); exit();
}

// ==================== QUERY DATA ====================
$search = isset($_GET['cari']) ? $conn->real_escape_string(trim($_GET['cari'])) : '';
$where  = $search
    ? "WHERE (b.judul_buku LIKE '%$search%' OR b.penulis LIKE '%$search%') AND b.stok > 0"
    : "WHERE b.stok > 0";

$buku_res = $conn->query("
    SELECT b.*, k.nama_kategori FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    $where ORDER BY b.judul_buku ASC
");

$menunggu_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis, b.cover, b.lokasi_rak, k.nama_kategori 
    FROM persetujuan p
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.id_user=$id_user AND p.status='menunggu'
    ORDER BY p.tanggal_pengajuan DESC
");

$riwayat_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis, b.cover, b.lokasi_rak, k.nama_kategori 
    FROM persetujuan p
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE p.id_user=$id_user AND p.status != 'menunggu'
    ORDER BY p.tanggal_diproses DESC LIMIT 20
");

// Simpan ke array agar bisa di-loop lebih dari sekali
$data_menunggu = [];
if ($menunggu_res) { while ($row = $menunggu_res->fetch_assoc()) { $data_menunggu[] = $row; } }
$jml_menunggu = count($data_menunggu);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Pinjam | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
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

        /* TABS */
        .tab-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 1.5rem; }
        .tab-nav { display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .tab-btn { flex: 1; padding: 0.9rem 1.5rem; background: none; border: none; font-size: 0.88rem; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .tab-btn:hover { color: #0f172a; background: #f1f5f9; }
        .tab-btn.active { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #ffffff; }
        .tab-count { background: #e2e8f0; color: #475569; font-size: 0.72rem; padding: 2px 7px; border-radius: 20px; font-weight: 700; }
        .tab-btn.active .tab-count { background: #eff6ff; color: #1d4ed8; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* FILTER / SEARCH BAR */
        .filter-bar { padding: 0.9rem 1.35rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .search-filter { margin-left: auto; display: flex; align-items: center; gap: 0.4rem; }
        .search-filter input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.85rem; color: #0f172a; width: 220px; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
        .search-filter input:focus { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
        .search-filter button { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.88rem; cursor: pointer; transition: background-color 0.15s; }
        .search-filter button:hover { background: #1e40af; }

        /* BUKU GRID */
        .buku-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.25rem; padding: 1.25rem 1.5rem; }
        .buku-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; transition: transform 0.15s ease, border-color 0.15s ease; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; }
        .buku-card:hover { border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
        .buku-card-cover { position: relative; height: 230px; overflow: hidden; background: #f1f5f9; }
        .buku-card-body { padding: 1rem 1.15rem; flex: 1; display: flex; flex-direction: column; }
        .buku-badge { display: inline-block; background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; margin-bottom: 0.4rem; width: fit-content; }
        .buku-judul { font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.2rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .buku-penulis { font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem; }
        .btn-ajukan { width: 100%; margin-top: auto; background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 6px; padding: 0.5rem; font-weight: 600; font-size: 0.82rem; cursor: pointer; transition: background-color 0.15s; }
        .btn-ajukan:hover { background: #1e40af; }

        /* TABLE */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th { padding: 0.85rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge-status { padding: 0.28rem 0.7rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge-menunggu  { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
        .badge-disetujui { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .badge-ditolak   { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .empty-state i { font-size: 2.8rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }

        /* MODAL */
        .modal-content { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .modal-header { background: #0f172a; color: white; border-bottom: 1px solid #1e293b; padding: 1.1rem 1.5rem; }
        .modal-header .btn-close { filter: invert(1); }
        .modal-body { padding: 1.5rem; background: #ffffff; }
        .modal-footer { padding: 0.9rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
        .form-control, .form-select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.85rem; font-size: 0.88rem; }
        .form-control:focus, .form-select:focus { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
        .btn-submit { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 6px; padding: 0.5rem 1.2rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: background-color 0.15s; }
        .btn-submit:hover { background: #1e40af; }

        /* ALERT */
        .alert-floating { position: fixed; top: 75px; right: 1.5rem; z-index: 9999; min-width: 280px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); animation: slideIn 0.25s ease; }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.25s ease; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1.5rem 1rem; }
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
        <span><?= NAMA_PERPUS ?></span>
    </a>
    <div class="search-box">
        <a href="login_anggota.php?hal=kartu" class="btn btn-sm btn-outline-light d-none d-md-inline-flex align-items-center gap-1 rounded-pill px-3" style="font-size:0.8rem;">
            <i class="bi bi-person-badge"></i> Kartu Digital
        </a>
        <a href="login_anggota.php?hal=profil" class="user-avatar" data-bs-toggle="dropdown" style="text-decoration:none;">
            <?= strtoupper(substr($nama, 0, 1)) ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="login_anggota.php?hal=profil"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
            <li><a class="dropdown-item" href="login_anggota.php?hal=kartu"><i class="bi bi-person-badge me-2"></i>Kartu Perpustakaan</a></li>
            <li><a class="dropdown-item" href="index.php"><i class="bi bi-globe me-2"></i>Katalog Publik</a></li>
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
            <a href="login_anggota.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda</span></a>
        </div>
        <div class="nav-item">
            <a href="buku_anggota.php" class="nav-link"><i class="bi bi-book"></i><span>Katalog Buku</span></a>
        </div>
        <div class="nav-item">
            <a href="peminjaman_anggota.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman Saya</span></a>
        </div>
        <div class="nav-item">
            <a href="pengajuan_pinjam.php" class="nav-link active"><i class="bi bi-send"></i><span>Ajukan Pinjam</span></a>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Lainnya</div>
        <div class="nav-item">
            <a href="login_anggota.php?hal=kartu" class="nav-link"><i class="bi bi-person-badge"></i><span>Kartu Digital</span></a>
        </div>
        <div class="nav-item">
            <a href="login_anggota.php?hal=profil" class="nav-link"><i class="bi bi-person-circle"></i><span>Profil Saya</span></a>
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

<!-- MAIN CONTENT -->
<main class="main-content">

<?php if (isset($_GET['pesan'])): ?>
    <?php if ($_GET['pesan'] == 'sukses'): ?>
        <div class="alert alert-success alert-floating"><i class="bi bi-check-circle-fill me-2"></i>Pengajuan berhasil dikirim! Tunggu persetujuan admin.</div>
    <?php elseif ($_GET['pesan'] == 'sudah'): ?>
        <div class="alert alert-warning alert-floating"><i class="bi bi-exclamation-triangle-fill me-2"></i>Kamu sudah punya pengajuan menunggu untuk buku ini.</div>
    <?php elseif ($_GET['pesan'] == 'stok'): ?>
        <div class="alert alert-danger alert-floating"><i class="bi bi-x-circle-fill me-2"></i>Stok buku tidak tersedia saat ini.</div>
    <?php endif; ?>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-send-fill" style="font-size:1.75rem;color:#1d4ed8;"></i> Ajukan Peminjaman Buku
        </h1>
        <p class="text-muted small mb-0 mt-1">Pilih judul buku yang tersedia di rak perpustakaan sekolah dan ajukan secara online.</p>
    </div>
</div>

<!-- TABS -->
<div class="tab-wrapper">
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('katalog', this)">
            <i class="bi bi-book"></i> Pilih Buku
            <?php if ($buku_res): ?>
                <span class="tab-count"><?= $buku_res->num_rows ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('menunggu', this)">
            <i class="bi bi-hourglass-split"></i> Pengajuan Menunggu
            <span class="tab-count"><?= $jml_menunggu ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('riwayat', this)">
            <i class="bi bi-clock-history"></i> Riwayat Pengajuan
        </button>
    </div>

    <!-- TAB: KATALOG BUKU -->
    <div class="tab-pane active" id="pane-katalog">
        <div class="filter-bar">
            <div class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>Buku dengan stok &gt; 0 yang siap diajukan untuk peminjaman:
            </div>
            <div class="search-filter">
                <form method="GET" style="display:flex;gap:0.5rem;">
                    <input type="text" name="cari" placeholder="Cari judul / penulis..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <?php if ($buku_res && $buku_res->num_rows > 0): ?>
        <div class="buku-grid">
        <?php while ($b = $buku_res->fetch_assoc()): 
            $lokasi = $b['lokasi_rak'] ?? 'Rak A-1';
            $kategori = $b['nama_kategori'] ?? 'Umum';
            $stok = intval($b['stok']);
        ?>
            <div class="buku-card">
                <div class="buku-card-cover">
                    <?= renderBookCover($b['cover'], $b['judul_buku'], $b['penulis'], $kategori, $lokasi) ?>
                </div>
                <div class="buku-card-body">
                    <span class="buku-badge"><?= htmlspecialchars($kategori) ?></span>
                    <div class="buku-judul" title="<?= htmlspecialchars($b['judul_buku']) ?>"><?= htmlspecialchars($b['judul_buku']) ?></div>
                    <div class="buku-penulis"><i class="bi bi-person me-1"></i><?= htmlspecialchars($b['penulis']) ?></div>
                    
                    <div class="d-flex align-items-center justify-content-between my-2">
                        <?= renderRakBadge($lokasi) ?>
                        <?= renderStockBadge($stok) ?>
                    </div>

                    <button class="btn-ajukan"
                        onclick="bukaModal(<?= $b['id_buku'] ?>, '<?= addslashes(htmlspecialchars($b['judul_buku'])) ?>', '<?= addslashes(htmlspecialchars($b['penulis'])) ?>')">
                        <i class="bi bi-send-fill me-1"></i> Ajukan Pinjam
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-book"></i>
                <h5>Tidak ada buku tersedia</h5>
                <p style="font-size:0.85rem;">Coba kata kunci lain atau periksa buku di katalog publik.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: MENUNGGU -->
    <div class="tab-pane" id="pane-menunggu">
        <?php if ($jml_menunggu > 0): ?>
        <div class="p-3">
            <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                <div class="small">Pengajuan di bawah ini sedang dalam antrean peninjauan petugas perpustakaan. Pelacak alur peminjaman akan otomatis bergerak setelah disetujui.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Buku</th>
                        <th>Lokasi Fisik</th>
                        <th>Tgl Pengajuan</th>
                        <th>Durasi</th>
                        <th>Pelacak Alur Peminjaman</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                foreach ($data_menunggu as $m): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($m['judul_buku']) ?></div>
                            <div style="font-size:0.78rem;color:#64748b;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($m['penulis']) ?></div>
                            <?php if (!empty($m['alasan'])): ?>
                                <div class="small text-muted mt-1 fst-italic">"<?= htmlspecialchars($m['alasan']) ?>"</div>
                            <?php endif; ?>
                        </td>
                        <td><?= renderRakBadge($m['lokasi_rak'] ?? 'Rak A-1') ?></td>
                        <td><?= date('d M Y', strtotime($m['tanggal_pengajuan'])) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $m['durasi_hari'] ?> hari</span></td>
                        <td style="min-width:320px;">
                            <?= renderStatusTracker('diajukan') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-hourglass"></i>
                <h5>Tidak Ada Pengajuan Menunggu</h5>
                <p style="font-size:0.85rem;">Kamu belum punya pengajuan yang sedang menunggu persetujuan petugas.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: RIWAYAT -->
    <div class="tab-pane" id="pane-riwayat">
        <?php if ($riwayat_res && $riwayat_res->num_rows > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
                        <th>Lokasi Rak</th>
                        <th>Tgl Pengajuan</th>
                        <th>Durasi</th>
                        <th>Status & Alur</th>
                        <th>Catatan Petugas</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; while ($r = $riwayat_res->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($r['judul_buku']) ?></div>
                            <div style="font-size:0.78rem;color:#64748b;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($r['penulis']) ?></div>
                        </td>
                        <td><?= renderRakBadge($r['lokasi_rak'] ?? 'Rak A-1') ?></td>
                        <td><?= date('d M Y', strtotime($r['tanggal_pengajuan'])) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $r['durasi_hari'] ?> hari</span></td>
                        <td style="min-width:300px;">
                            <?php if ($r['status'] == 'disetujui'): ?>
                                <?= renderStatusTracker('siap_diambil') ?>
                                <div class="mt-1 small text-success fw-semibold">
                                    <i class="bi bi-check-circle me-1"></i>Disetujui! Silakan ambil di <?= htmlspecialchars($r['lokasi_rak'] ?? 'Ruang Perpustakaan') ?>
                                </div>
                            <?php else: ?>
                                <span class="badge-status badge-ditolak"><i class="bi bi-x-circle me-1"></i>Pengajuan Ditolak</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['catatan_admin'] ? htmlspecialchars($r['catatan_admin']) : '<span style="color:#94a3b8;">-</span>' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <h5>Belum Ada Riwayat</h5>
                <p style="font-size:0.85rem;">Pengajuan yang sudah diproses admin perpustakaan akan tercatat di sini.</p>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /tab-wrapper -->

</main>
</div>

<!-- MODAL AJUKAN -->
<div class="modal fade" id="modalAjukan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send-fill me-2"></i>Ajukan Peminjaman Buku</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_buku" id="modal_id_buku">
                <div class="modal-body">
                    <div style="background:#f8fafc;border-radius:8px;padding:0.9rem;margin-bottom:1.1rem;border:1px solid #e2e8f0;">
                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:0.25rem;">Buku yang dipilih:</div>
                        <div id="modal_judul" style="font-weight:700;color:#1e293b;font-size:1rem;"></div>
                        <div id="modal_penulis" style="font-size:0.8rem;color:#64748b;margin-top:0.15rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-calendar-range me-1"></i>Durasi Peminjaman</label>
                        <select name="durasi_hari" class="form-select" required>
                            <option value="3">3 hari</option>
                            <option value="7" selected>7 hari (Standar Sekolah)</option>
                            <option value="14">14 hari (Tugas Khusus)</option>
                        </select>
                        <div class="form-text text-muted" style="font-size:0.75rem;">Aturan perpustakaan: Maksimal peminjaman standar 7 hari.</div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label"><i class="bi bi-chat-text me-1"></i>Keperluan / Alasan <span style="color:#94a3b8;font-weight:400;">(opsional)</span></label>
                        <textarea name="alasan" class="form-control" rows="2" placeholder="Contoh: Untuk tugas literasi atau referensi belajar..." style="resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-submit"><i class="bi bi-send me-1"></i> Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.querySelector('.mobile-toggle');
    if (window.innerWidth <= 991 && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target))
        sidebar.classList.remove('show');
});

function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('pane-' + tabName).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function bukaModal(id, judul, penulis) {
    document.getElementById('modal_id_buku').value = id;
    document.getElementById('modal_judul').textContent   = judul;
    document.getElementById('modal_penulis').textContent = penulis;
    new bootstrap.Modal(document.getElementById('modalAjukan')).show();
}

setTimeout(() => {
    const al = document.querySelector('.alert-floating');
    if (al) { al.style.transition = 'opacity 0.5s'; al.style.opacity = '0'; setTimeout(() => al.remove(), 500); }
}, 4000);

// Jika ada pesan sukses, langsung buka tab menunggu
<?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses'): ?>
window.addEventListener('load', () => {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('pane-menunggu').classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-btn')[1].classList.add('active');
});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>