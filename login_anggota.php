<?php
session_start();
if (!isset($_SESSION['username']) || trim($_SESSION['level']) != 'anggota') {
    header("Location: index.php");
    exit();
}

require_once 'koneksi.php';
require_once 'helper_katalog.php';

$id_user = $_SESSION['id_user'];
$nama    = $_SESSION['nama'];
$hal     = $_GET['hal'] ?? 'beranda';

// ========== STATISTIK ==========
$total_pinjam = $koneksi->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user")->fetch_assoc()['c'] ?? 0;
$dipinjam     = $koneksi->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam'")->fetch_assoc()['c'] ?? 0;
$terlambat    = $koneksi->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'] ?? 0;
$dikembalikan = $koneksi->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dikembalikan'")->fetch_assoc()['c'] ?? 0;

// ========== DATA USER (SISWA) ==========
$user = $koneksi->query("SELECT * FROM user WHERE id_user=$id_user")->fetch_assoc();
if (empty($user['nis'])) {
    $nis_default = '2024' . str_pad($id_user, 4, '0', STR_PAD_LEFT);
    $user['nis'] = $nis_default;
}

// ========== PENGINGAT JATUH TEMPO (Sisa 1–2 Hari atau Hari Ini) ==========
$tempo_dekat_res = $koneksi->query("
    SELECT p.*, b.judul_buku, b.penulis, DATEDIFF(p.tanggal_pengembalian, CURDATE()) AS sisa_hari
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user=$id_user AND p.status_peminjaman='dipinjam' 
      AND p.tanggal_pengembalian >= CURDATE() 
      AND p.tanggal_pengembalian <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY p.tanggal_pengembalian ASC
");
$tempo_dekat_list = [];
if ($tempo_dekat_res) {
    while ($td = $tempo_dekat_res->fetch_assoc()) {
        $tempo_dekat_list[] = $td;
    }
}

// ========== PELACAK STATUS PEMINJAMAN TERAKHIR ==========
$latest_tracker = null;
// Cek apakah ada pengajuan di persetujuan
$status_req = $koneksi->query("
    SELECT p.*, b.judul_buku 
    FROM persetujuan p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user=$id_user 
    ORDER BY p.id_permintaan DESC LIMIT 1
");
if ($status_req && $status_req->num_rows > 0) {
    $reqRow = $status_req->fetch_assoc();
    if ($reqRow['status'] === 'menunggu') {
        $latest_tracker = [
            'judul' => $reqRow['judul_buku'],
            'step'  => 'diajukan',
            'catatan' => 'Menunggu verifikasi admin perpustakaan.'
        ];
    } elseif ($reqRow['status'] === 'disetujui') {
        // Cek peminjaman terkait
        $idPinjam = intval($reqRow['id_peminjaman'] ?? 0);
        $cekP = $idPinjam > 0 ? $koneksi->query("SELECT status_peminjaman FROM peminjaman WHERE id_peminjaman=$idPinjam") : null;
        $statusPinjam = ($cekP && $cekP->num_rows > 0) ? $cekP->fetch_assoc()['status_peminjaman'] : 'dipinjam';
        
        if ($statusPinjam === 'dikembalikan') {
            $latest_tracker = [
                'judul' => $reqRow['judul_buku'],
                'step'  => 'dikembalikan',
                'catatan' => 'Buku telah dikembalikan dan tersimpan di sistem.'
            ];
        } else {
            $latest_tracker = [
                'judul' => $reqRow['judul_buku'],
                'step'  => 'dipinjam',
                'catatan' => 'Disetujui. Buku siap diambil di ruang perpustakaan / sedang Anda pinjam.'
            ];
        }
    } elseif ($reqRow['status'] === 'ditolak') {
        $latest_tracker = [
            'judul' => $reqRow['judul_buku'],
            'step'  => 'ditolak',
            'catatan' => $reqRow['catatan_admin'] ?: 'Pengajuan ditolak oleh admin perpustakaan.'
        ];
    }
}

// ========== PINJAMAN AKTIF ==========
$aktif_res = $koneksi->query("
    SELECT p.*, b.judul_buku, b.penulis, b.lokasi_rak
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user=$id_user AND p.status_peminjaman='dipinjam'
    ORDER BY p.tanggal_pengembalian ASC LIMIT 5
");

// ========== BUKU POPULER / REKOMENDASI PEKAN INI ==========
$populer_res = $koneksi->query("
    SELECT b.*, k.nama_kategori, COUNT(p.id_peminjaman) AS total_dipinjam
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
    GROUP BY b.id_buku
    ORDER BY total_dipinjam DESC, b.stok DESC
    LIMIT 4
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa | <?= NAMA_PERPUS ?></title>
    
    <!-- Fonts & Bootstrap 5 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom Style Katalog & Kartu -->
    <link rel="stylesheet" href="katalog_styles.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8fafc; color: #0f172a; }

        /* HEADER */
        .main-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #0f172a; border-bottom: 1px solid #1e293b; display: flex; align-items: center; padding: 0 1.5rem; z-index: 1000; }
        .brand { display: flex; align-items: center; gap: 12px; color: #f8fafc; text-decoration: none; font-size: 1.15rem; font-weight: 700; }
        .brand img { width: 34px; height: 34px; border-radius: 50%; border: 1px solid #334155; }
        .search-box { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #334155; cursor: pointer; background: #1d4ed8; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.95rem; text-decoration:none; }
        .mobile-toggle { display: none; background: none; border: none; color: #f8fafc; font-size: 1.4rem; cursor: pointer; margin-right: 1rem; }

        /* LAYOUT */
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { position: fixed; top: 60px; left: 0; bottom: 0; width: 260px; background: #0f172a; border-right: 1px solid #1e293b; overflow-y: auto; z-index: 900; display: flex; flex-direction: column; }
        .sidebar-section { padding: 1.25rem 0; }
        .sidebar-title { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; padding: 0 1.5rem; margin-bottom: 0.65rem; }
        .nav-item { margin: 0.2rem 0.85rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.95rem; color: #94a3b8; text-decoration: none; border-radius: 8px; font-size: 0.88rem; font-weight: 500; transition: all 0.15s; }
        .nav-link:hover { background: #1e293b; color: #ffffff; }
        .nav-link.active { background: #2563eb; color: #ffffff; font-weight: 600; }
        .nav-link i { font-size: 1.1rem; width: 20px; }
        .sidebar-user { margin-top: auto; padding: 1.1rem 1.25rem; border-top: 1px solid #1e293b; display: flex; align-items: center; gap: 0.75rem; background: #0b1120; }
        .sidebar-user-avatar { width: 38px; height: 38px; border-radius: 50%; background: #2563eb; color: white; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: 0.75rem; color: #64748b; }

        /* MAIN CONTENT */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem 2.25rem; }

        /* CARDS & STATS */
        .welcome-section { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-top: 1.25rem; }
        .stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; text-decoration: none; color: inherit; display: flex; align-items: center; gap: 1rem; transition: transform 0.15s, border-color 0.15s; }
        .stat-card:hover { transform: translateY(-2px); border-color: #cbd5e1; color: inherit; }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
        .stat-icon.blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .stat-icon.green  { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .stat-icon.orange { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
        .stat-icon.red    { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        /* BANNERS */
        .banner-friendly { background: #fffbeb; border: 1px solid #fde68a; border-left: 5px solid #d97706; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #92400e; font-size: 0.9rem; }
        .banner-danger   { background: #fef2f2; border: 1px solid #fecaca; border-left: 5px solid #dc2626; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #991b1b; font-size: 0.9rem; }

        /* TABLE */
        .table-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th { padding: 0.85rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }

        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.25s ease; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="login_anggota.php" class="brand">
        <img src="logo 40.jpeg" alt="Logo PerpusOnline" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
        <span>PerpusOnline</span>
    </a>
    <div class="search-box">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1" style="font-size:0.8rem;">
            <i class="bi bi-globe"></i> Katalog Terbuka
        </a>
        <div class="dropdown">
            <a href="#" class="user-avatar" data-bs-toggle="dropdown">
                <?= strtoupper(substr($nama,0,1)) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="login_anggota.php?hal=kartu"><i class="bi bi-person-vcard me-2"></i>Kartu Anggota Digital</a></li>
                <li><a class="dropdown-item" href="peminjaman_anggota.php"><i class="bi bi-clock-history me-2"></i>Peminjaman Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="layout-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item">
            <a href="login_anggota.php" class="nav-link <?= $hal=='beranda'?'active':'' ?>">
                <i class="bi bi-house-door-fill"></i><span>Beranda Siswa</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="buku_anggota.php" class="nav-link">
                <i class="bi bi-book"></i><span>Katalog Buku</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="peminjaman_anggota.php" class="nav-link">
                <i class="bi bi-clock-history"></i><span>Peminjaman Saya</span>
            </a>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Fitur Siswa</div>
        <div class="nav-item">
            <a href="login_anggota.php?hal=kartu" class="nav-link <?= ($hal=='kartu'||$hal=='profil')?'active':'' ?>">
                <i class="bi bi-person-vcard-fill"></i><span>Kartu Anggota Digital</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="index.php" class="nav-link">
                <i class="bi bi-globe"></i><span>Katalog Publik</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left"></i><span>Keluar</span>
            </a>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= strtoupper(substr($nama,0,1)) ?></div>
        <div style="overflow:hidden;">
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">NIS: <?= htmlspecialchars($user['nis']) ?></div>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

<?php if ($hal === 'beranda'): ?>

    <!-- WELCOME SECTION -->
    <div class="welcome-section">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="fs-4 fw-bold m-0 text-dark">
                    Selamat Datang, <?= htmlspecialchars($nama) ?>! 👋
                </h1>
                <p class="text-muted small m-0 mt-1">
                    Anggota Resmi <?= NAMA_PERPUS ?> | NIS: <strong><?= htmlspecialchars($user['nis']) ?></strong>
                </p>
            </div>
            <a href="login_anggota.php?hal=kartu" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-person-vcard me-1"></i>Buka Kartu Digital
            </a>
        </div>

        <div class="stats-grid">
            <a href="peminjaman_anggota.php" class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-journals"></i></div>
                <div><div class="small text-muted fw-bold">Total Pinjaman</div><div class="fs-4 fw-bold"><?= $total_pinjam ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=aktif" class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-book-fill"></i></div>
                <div><div class="small text-muted fw-bold">Sedang Dipinjam</div><div class="fs-4 fw-bold"><?= $dipinjam ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=aktif&f=terlambat" class="stat-card">
                <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><div class="small text-muted fw-bold">Terlambat</div><div class="fs-4 fw-bold"><?= $terlambat ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=riwayat" class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="small text-muted fw-bold">Selesai</div><div class="fs-4 fw-bold"><?= $dikembalikan ?></div></div>
            </a>
        </div>
    </div>

    <!-- 3. PENGINGAT JATUH TEMPO (RAMAH & BERMANFAAT) -->
    <?php if ($terlambat > 0): ?>
        <div class="banner-danger d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
            <div>
                <strong>Perhatian:</strong> Kamu memiliki <strong><?= $terlambat ?> buku yang telah melewati batas tanggal pengembalian!</strong>
                <div class="small mt-1">Harap segera mengembalikan buku ke perpustakaan sekolah agar terhindar dari sanksi dan denda keterlambatan.</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($tempo_dekat_list)): ?>
        <?php foreach ($tempo_dekat_list as $td): 
            $sisa = intval($td['sisa_hari']);
            $pesanWaktu = ($sisa === 0) ? 'hari ini' : ($sisa === 1 ? 'besok (1 hari lagi)' : '2 hari lagi');
        ?>
            <div class="banner-friendly d-flex align-items-center gap-3">
                <i class="bi bi-clock-history fs-3 text-warning"></i>
                <div class="flex-grow-1">
                    <strong>⏰ Pengingat Jatuh Tempo:</strong> Buku <em>"<?= htmlspecialchars($td['judul_buku']) ?>"</em> jatuh tempo <strong><?= $pesanWaktu ?></strong> pada tanggal <strong><?= date('d M Y', strtotime($td['tanggal_pengembalian'])) ?></strong>.
                    <div class="small mt-1">Pastikan buku siap dibawa ke perpustakaan sebelum tanggal tersebut ya!</div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 4. PELACAK STATUS PEMINJAMAN (TRACKER 4 LANGKAH) -->
    <?php if ($latest_tracker): ?>
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="fs-6 fw-bold text-dark m-0">
                    <i class="bi bi-activity text-primary me-1"></i>Status Peminjaman Terkini: <em><?= htmlspecialchars($latest_tracker['judul']) ?></em>
                </h2>
                <small class="text-muted"><?= htmlspecialchars($latest_tracker['catatan']) ?></small>
            </div>
            <?= renderStatusTracker($latest_tracker['step'], '', '', $latest_tracker['catatan']) ?>
        </div>
    <?php endif; ?>

    <!-- TABEL BUKU SEDANG DIPINJAM -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="fs-5 fw-bold m-0"><i class="bi bi-book-fill text-primary me-2"></i>Buku Sedang Dipinjam</h2>
        <a href="peminjaman_anggota.php" class="btn btn-outline-secondary btn-sm rounded-pill">Lihat Riwayat Lengkap →</a>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
                        <th>Lokasi Rak</th>
                        <th>Tgl Pinjam</th>
                        <th>Batas Kembali</th>
                        <th>Status</th>
                        <th>Sisa Waktu</th>
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
                        $rowCls = $late ? 'row-terlambat' : '';
                ?>
                <tr class="<?= $rowCls ?>">
                    <td><?= $no++ ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($r['judul_buku']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($r['penulis']) ?></div>
                    </td>
                    <td><?= renderRakBadge($r['lokasi_rak'] ?? 'Rak A-1') ?></td>
                    <td><?= date('d M Y', strtotime($r['tanggal_peminjaman'])) ?></td>
                    <td>
                        <strong class="<?= $late ? 'text-danger' : 'text-dark' ?>">
                            <?= date('d M Y', strtotime($r['tanggal_pengembalian'])) ?>
                        </strong>
                    </td>
                    <td>
                        <?php if ($late): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Terlambat</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Dipinjam</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($late): ?>
                            <span class="text-danger fw-bold"><?= abs($sisa) ?> hari terlambat</span>
                        <?php elseif ($sisa == 0): ?>
                            <span class="text-warning fw-bold">Hari ini!</span>
                        <?php elseif ($sisa <= 2): ?>
                            <span class="text-warning fw-bold">Sisa <?= $sisa ?> hari</span>
                        <?php else: ?>
                            <span class="text-success fw-bold">Sisa <?= $sisa ?> hari</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7">
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-book fs-2 d-block mb-2 text-secondary"></i>
                        <p class="m-0">Kamu tidak sedang meminjam buku apapun saat ini.</p>
                        <a href="buku_anggota.php" class="btn btn-primary btn-sm rounded-pill mt-2">Jelajahi Katalog Buku</a>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 1. BUKU POPULER / REKOMENDASI PEKAN INI -->
    <?php if ($populer_res && $populer_res->num_rows > 0): ?>
    <section class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="fs-5 fw-bold m-0"><i class="bi bi-fire text-danger me-2"></i>Rekomendasi Buku Pekan Ini</h2>
                <small class="text-muted">Buku favorit yang paling sering dipinjam oleh siswa lain</small>
            </div>
            <a href="buku_anggota.php" class="btn btn-outline-primary btn-sm rounded-pill">Lihat Semua Buku</a>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
            <?php while ($bp = $populer_res->fetch_assoc()): 
                $stokBp = intval($bp['stok'] ?? 0);
            ?>
            <div class="col">
                <div class="book-card h-100 p-2">
                    <div class="book-card-top p-1">
                        <?= renderBookCover($bp['cover'], $bp['judul_buku'], $bp['penulis'], $bp['nama_kategori'], $bp['lokasi_rak'] ?? 'Rak A-1') ?>
                    </div>
                    <div class="book-card-body p-2">
                        <div class="bc-title" style="font-size:0.88rem;"><?= htmlspecialchars($bp['judul_buku']) ?></div>
                        <div class="small text-muted mb-2"><i class="bi bi-person"></i> <?= htmlspecialchars($bp['penulis'] ?? '-') ?></div>
                        
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <?= renderRakBadge($bp['lokasi_rak'] ?? 'Rak A-1') ?>
                            <?= renderStockBadge($stokBp) ?>
                        </div>

                        <?php if ($stokBp > 0): ?>
                            <a href="buku_anggota.php?search=<?= urlencode($bp['judul_buku']) ?>" class="btn btn-primary btn-sm w-100 rounded-pill mt-auto fw-bold" style="font-size:0.8rem;">
                                <i class="bi bi-book-fill me-1"></i>Pinjam Buku
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm w-100 rounded-pill mt-auto" disabled style="font-size:0.8rem;">
                                Stok Habis
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

<?php elseif ($hal === 'kartu' || $hal === 'profil'): ?>

    <!-- 2. KARTU ANGGOTA DIGITAL & PROFIL SISWA -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="fs-4 fw-bold m-0"><i class="bi bi-person-vcard text-primary me-2"></i>Kartu Anggota Digital Siswa</h1>
            <p class="text-muted small m-0 mt-1">Gunakan kartu ini sebagai tanda pengenal resmi saat sirkulasi buku di perpustakaan sekolah</p>
        </div>
        <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-printer me-2"></i>Cetak / Simpan Kartu
        </button>
    </div>

    <div class="row g-4 align-items-start">
        <!-- Digital Card Preview -->
        <div class="col-lg-6">
            <div class="p-3 bg-white rounded-4 border shadow-sm text-center">
                <?= renderDigitalIdCard($user) ?>

                <div class="mt-3 p-3 bg-light rounded-3 text-start small text-muted">
                    <div class="fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan me-1 text-primary"></i>Petunjuk Penggunaan Kartu:</div>
                    <ul class="m-0 ps-3">
                        <li>Tunjukkan barcode digital di atas ke pustakawan saat pengambilan buku di ruang perpus.</li>
                        <li>Klik tombol <strong>Cetak / Simpan Kartu</strong> untuk mencetak kartu fisik atau menyimpan ke PDF.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Detail Data Akun Siswa -->
        <div class="col-lg-6">
            <div class="p-4 bg-white rounded-4 border shadow-sm">
                <h2 class="fs-5 fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="bi bi-person-lines-fill text-primary me-2"></i>Identitas Anggota
                </h2>

                <div class="d-flex flex-column gap-3" style="font-size:0.92rem;">
                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-person me-2"></i>Nama Lengkap</span>
                        <strong class="text-dark"><?= htmlspecialchars($user['nama']) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-upc me-2"></i>Nomor Induk Siswa (NIS)</span>
                        <span class="badge bg-primary fs-6 px-3"><?= htmlspecialchars($user['nis']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-at me-2"></i>Username</span>
                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-envelope me-2"></i>Email</span>
                        <span><?= htmlspecialchars($user['email'] ?? '-') ?></span>
                    </div>

                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-telephone me-2"></i>No. Telepon / WA</span>
                        <span><?= htmlspecialchars($user['no_hp'] ?? '-') ?></span>
                    </div>

                    <div class="d-flex justify-content-between pb-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-geo-alt me-2"></i>Alamat</span>
                        <span><?= htmlspecialchars($user['alamat'] ?? '-') ?></span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-shield-check me-2"></i>Status Akun</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif Terdaftar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

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
</script>
</body>
</html>
