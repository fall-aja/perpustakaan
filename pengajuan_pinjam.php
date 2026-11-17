<?php
session_start();
if (!isset($_SESSION['username']) || trim($_SESSION['level']) != 'anggota') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

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
    SELECT p.*, b.judul_buku, b.penulis FROM persetujuan p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user=$id_user AND p.status='menunggu'
    ORDER BY p.tanggal_pengajuan DESC
");

$riwayat_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis FROM persetujuan p
    JOIN buku b ON p.id_buku = b.id_buku
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
    <title>Ajukan Pinjam - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #e7e7e7d2; }
        .main-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(135deg, #2d56b6 0%, #4172c1 100%); box-shadow: 0 2px 10px rgba(0,0,0,0); display: flex; align-items: center; padding: 0 1.5rem; z-index: 1000; }
        .brand { display: flex; align-items: center; gap: 12px; color: white; text-decoration: none; font-size: 1.25rem; font-weight: 600; }
        .brand img { width: 32px; height: 32px; border-radius: 50%; }
        .search-box { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
        .search-input { background: rgba(238,238,238,0.24); border: 1px solid rgba(177,177,177,0.53); border-radius: 8px; padding: 0.5rem 1rem; color: white; width: 280px; transition: all 0.3s; }
        .search-input::placeholder { color: rgba(230,230,230,0.7); }
        .search-input:focus { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); outline: none; width: 320px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(233,233,233,0.8); cursor: pointer; transition: transform 0.2s; background: linear-gradient(135deg,#1d4ed8,#2563eb); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.95rem; text-decoration:none; }
        .user-avatar:hover { transform: scale(1.05); }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; margin-right: 1rem; }
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }
        .sidebar { position: fixed; top: 60px; left: 0; bottom: 0; width: 260px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); border-right: 1px solid rgba(255,64,64,0.65); overflow-y: auto; z-index: 900; display: flex; flex-direction: column; }
        .sidebar-section { padding: 1.5rem 0; }
        .sidebar-title { font-size: 0.7rem; font-weight: 600; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; padding: 0 1.5rem; margin-bottom: 0.75rem; }
        .nav-item { margin: 0.25rem 1rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: rgba(245,245,245,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; font-size: 0.9rem; }
        .nav-link:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; }
        .nav-link.active { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .nav-link i { font-size: 1.1rem; width: 20px; }
        .sidebar-user { margin-top: auto; padding: 1.25rem; border-top: 1px solid rgba(255,255,255,0.7); display: flex; align-items: center; gap: 0.75rem; background: rgba(0,0,0,0.2); }
        .sidebar-user img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(227,227,227,0.8); }
        .sidebar-user-name { font-weight: 600; color: white; font-size: 0.9rem; }
        .sidebar-user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); }
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }

        /* PAGE HEADER */
        .page-header { background: white; border-radius: 12px; padding: 1.5rem 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #4879c8; display: flex; align-items: center; gap: 0.75rem; }

        /* TABS */
        .tab-wrapper { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 1.5rem; }
        .tab-nav { display: flex; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
        .tab-btn { flex: 1; padding: 1rem 1.5rem; background: none; border: none; font-size: 0.9rem; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn:hover { color: #1e293b; background: #f1f5f9; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; background: white; }
        .tab-count { background: #e2e8f0; color: #64748b; font-size: 0.72rem; padding: 1px 7px; border-radius: 20px; font-weight: 700; }
        .tab-btn.active .tab-count { background: rgba(37,99,235,0.1); color: #2563eb; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* FILTER / SEARCH BAR */
        .filter-bar { padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .search-filter { margin-left: auto; display: flex; align-items: center; gap: 0.5rem; }
        .search-filter input { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0.4rem 1rem; font-size: 0.85rem; color: #1e293b; width: 220px; outline: none; transition: border-color 0.2s; }
        .search-filter input:focus { border-color: #2563eb; }
        .search-filter button { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.9rem; cursor: pointer; }

        /* BUKU GRID */
        .buku-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; padding: 1.25rem 1.5rem; }
        .buku-card { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 1.1rem; transition: all 0.2s; background: #fafafa; }
        .buku-card:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.1); background: white; }
        .buku-badge { display: inline-block; background: rgba(37,99,235,0.08); color: #2563eb; padding: 2px 8px; border-radius: 6px; font-size: 0.73rem; margin-bottom: 0.5rem; }
        .buku-judul { font-weight: 700; font-size: 0.95rem; color: #1e293b; margin-bottom: 0.2rem; }
        .buku-penulis { font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem; }
        .buku-stok { font-size: 0.8rem; color: #10b981; font-weight: 600; }
        .btn-ajukan { width: 100%; margin-top: 0.7rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; border-radius: 8px; padding: 0.5rem; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: opacity 0.2s; }
        .btn-ajukan:hover { opacity: 0.88; }

        /* TABLE */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th { padding: 0.9rem 1rem; text-align: left; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge-status { padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge-menunggu  { background: rgba(245,158,11,0.12); color: #d97706; }
        .badge-disetujui { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-ditolak   { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; }

        /* MODAL */
        .modal-content { border: none; border-radius: 16px; }
        .modal-header { background: linear-gradient(135deg, #2d56b6, #4172c1); color: white; border-radius: 16px 16px 0 0; padding: 1.25rem 1.5rem; }
        .modal-header .btn-close { filter: invert(1); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #374151; }
        .form-control, .form-select { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0.55rem 0.85rem; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: none; }
        .btn-submit { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; border-radius: 8px; padding: 0.55rem 1.2rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; }

        /* ALERT */
        .alert-floating { position: fixed; top: 75px; right: 1.5rem; z-index: 9999; min-width: 280px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); animation: slideIn 0.3s ease; }
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
        <input type="text" class="search-input" placeholder="Cari buku...">
        <a href="login_anggota.php?hal=profil" class="user-avatar" data-bs-toggle="dropdown" style="text-decoration:none;">
            <?= strtoupper(substr($nama, 0, 1)) ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="login_anggota.php?hal=profil"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
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
            <a href="login_anggota.php?hal=profil" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Saya</span></a>
        </div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a>
        </div>
    </div>
    <div class="sidebar-user">
        <img src="logo 40.jpeg" alt="User" onerror="this.style.display='none'">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">Anggota</div>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

<?php if (isset($_GET['pesan'])): ?>
    <?php if ($_GET['pesan'] == 'sukses'): ?>
        <div class="alert alert-success alert-floating">✅ Pengajuan berhasil dikirim! Tunggu persetujuan admin.</div>
    <?php elseif ($_GET['pesan'] == 'sudah'): ?>
        <div class="alert alert-warning alert-floating">⚠️ Kamu sudah punya pengajuan menunggu untuk buku ini.</div>
    <?php elseif ($_GET['pesan'] == 'stok'): ?>
        <div class="alert alert-danger alert-floating">❌ Stok buku tidak tersedia saat ini.</div>
    <?php endif; ?>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-send-fill" style="font-size:1.75rem;"></i> Ajukan Pinjam Buku
    </h1>
</div>

<!-- TABS -->
<div class="tab-wrapper">
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('katalog', this)">
            <i class="bi bi-book"></i> Katalog Buku
            <?php if ($buku_res): ?>
                <span class="tab-count"><?= $buku_res->num_rows ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('menunggu', this)">
            <i class="bi bi-hourglass-split"></i> Menunggu
            <span class="tab-count"><?= $jml_menunggu ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('riwayat', this)">
            <i class="bi bi-clock-history"></i> Riwayat
        </button>
    </div>

    <!-- TAB: KATALOG BUKU -->
    <div class="tab-pane active" id="pane-katalog">
        <div class="filter-bar">
            <div class="search-filter">
                <form method="GET" style="display:flex;gap:0.5rem;">
                    <input type="text" name="cari" placeholder="Cari judul atau penulis..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <?php if ($buku_res && $buku_res->num_rows > 0): ?>
        <div class="buku-grid">
        <?php while ($b = $buku_res->fetch_assoc()): ?>
            <div class="buku-card">
                <span class="buku-badge"><?= htmlspecialchars($b['nama_kategori'] ?? 'Umum') ?></span>
                <div class="buku-judul"><?= htmlspecialchars($b['judul_buku']) ?></div>
                <div class="buku-penulis"><i class="bi bi-person"></i> <?= htmlspecialchars($b['penulis']) ?></div>
                <div class="buku-stok"><i class="bi bi-layers"></i> Stok: <?= $b['stok'] ?></div>
                <button class="btn-ajukan"
                    onclick="bukaModal(<?= $b['id_buku'] ?>, '<?= addslashes(htmlspecialchars($b['judul_buku'])) ?>', '<?= addslashes(htmlspecialchars($b['penulis'])) ?>')">
                    <i class="bi bi-send"></i> Ajukan Pinjam
                </button>
            </div>
        <?php endwhile; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-book"></i>
                <h5>Tidak ada buku tersedia</h5>
                <p style="font-size:0.85rem;">Coba kata kunci lain atau cek kembali nanti.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: MENUNGGU -->
    <div class="tab-pane" id="pane-menunggu">
        <?php if ($jml_menunggu > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
                        <th>Tgl Pengajuan</th>
                        <th>Durasi</th>
                        <th>Alasan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                
                $no = 1;
                foreach ($data_menunggu as $m): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($m['judul_buku']) ?></div>
                            <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($m['penulis']) ?></div>
                        </td>
                        <td><?= date('d M Y', strtotime($m['tanggal_pengajuan'])) ?></td>
                        <td><?= $m['durasi_hari'] ?> hari</td>
                        <td><?= $m['alasan'] ? htmlspecialchars($m['alasan']) : '<span style="color:#94a3b8;">—</span>' ?></td>
                        <td><span class="badge-status badge-menunggu">⏳ Menunggu</span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-hourglass"></i>
                <h5>Tidak Ada Pengajuan Menunggu</h5>
                <p style="font-size:0.85rem;">Kamu belum punya pengajuan yang sedang menunggu persetujuan.</p>
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
                        <th>Tgl Pengajuan</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th>Catatan Admin</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; while ($r = $riwayat_res->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($r['judul_buku']) ?></div>
                            <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($r['penulis']) ?></div>
                        </td>
                        <td><?= date('d M Y', strtotime($r['tanggal_pengajuan'])) ?></td>
                        <td><?= $r['durasi_hari'] ?> hari</td>
                        <td>
                            <?php if ($r['status'] == 'disetujui'): ?>
                                <span class="badge-status badge-disetujui">✅ Disetujui</span>
                            <?php else: ?>
                                <span class="badge-status badge-ditolak">❌ Ditolak</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['catatan_admin'] ? htmlspecialchars($r['catatan_admin']) : '<span style="color:#94a3b8;">—</span>' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <h5>Belum Ada Riwayat</h5>
                <p style="font-size:0.85rem;">Pengajuan yang sudah diproses admin akan muncul di sini.</p>
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
                <h5 class="modal-title"><i class="bi bi-send-fill me-2"></i>Ajukan Pinjam Buku</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_buku" id="modal_id_buku">
                <div class="modal-body">
                    <div style="background:#f8fafc;border-radius:8px;padding:0.9rem;margin-bottom:1.1rem;">
                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:0.25rem;">Buku yang dipilih:</div>
                        <div id="modal_judul" style="font-weight:700;color:#1e293b;font-size:1rem;"></div>
                        <div id="modal_penulis" style="font-size:0.8rem;color:#64748b;margin-top:0.15rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-calendar-range me-1"></i>Durasi Peminjaman</label>
                        <select name="durasi_hari" class="form-select" required>
                            <option value="3">3 hari</option>
                            <option value="7" selected>7 hari</option>
                            <option value="14">14 hari</option>
                            <option value="30">30 hari</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label"><i class="bi bi-chat-text me-1"></i>Alasan <span style="color:#94a3b8;font-weight:400;">(opsional)</span></label>
                        <textarea name="alasan" class="form-control" rows="2" placeholder="Keperluan peminjaman..." style="resize:none;"></textarea>
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