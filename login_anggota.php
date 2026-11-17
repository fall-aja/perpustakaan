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
$hal     = $_GET['hal'] ?? 'beranda';

// ========== STATISTIK ==========
$total_pinjam = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user")->fetch_assoc()['c'];
$dipinjam     = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam'")->fetch_assoc()['c'];
$terlambat    = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'];
$dikembalikan = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dikembalikan'")->fetch_assoc()['c'];

// ========== DATA USER ==========
$user = $conn->query("SELECT * FROM user WHERE id_user=$id_user")->fetch_assoc();

// ========== PINJAMAN AKTIF (untuk beranda) ==========
$aktif_res = $conn->query("
    SELECT p.*, b.judul_buku, b.penulis
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE p.id_user=$id_user AND p.status_peminjaman='dipinjam'
    ORDER BY p.tanggal_pengembalian ASC LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anggota - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #e7e7e7d2; }

        /* HEADER */
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

        /* LAYOUT */
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }

        /* SIDEBAR */
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

        /* MAIN */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }

        /* WELCOME */
        .welcome-section { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.73); margin-bottom: 2rem; }
        .welcome-title { font-size: 1.75rem; font-weight: 700; color: #4879c8; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .welcome-subtitle { color: #64748b; font-size: 0.95rem; }
        .divider { height: 1px; background: #509cff; margin: 1.5rem 0; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #bababa00; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(116,116,116,0.44); transition: transform 0.2s, box-shadow 0.3s; text-decoration: none; color: inherit; display: flex; align-items: center; gap: 1rem; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(219,219,219,0.1); color: inherit; }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .stat-icon.blue   { background: rgba(136,172,255,0.26); color: #256afe; }
        .stat-icon.green  { background: rgba(145,222,187,0.33); color: #10b981; }
        .stat-icon.orange { background: rgba(245,158,11,0.1);   color: #f59e0b; }
        .stat-icon.red    { background: rgba(239,68,68,0.1);    color: #ef4444; }
        .stat-label { font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #171616b8; }

        /* PAGE HEADER */
        .page-header { background: white; border-radius: 12px; padding: 1.5rem 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #4879c8; display: flex; align-items: center; gap: 0.75rem; }

        /* TABLE */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 1.5rem; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th { padding: 0.9rem 1rem; text-align: left; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge-status { padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-dipinjam     { background: rgba(245,158,11,0.12); color: #d97706; }
        .badge-dikembalikan { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-terlambat    { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; }

        /* PROFIL */
        .profile-card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); max-width: 560px; }
        .profile-avatar { width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg,#2d56b6,#4172c1); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 700; color: white; margin: 0 auto 1.5rem; }
        .profile-row { display: flex; gap: 1rem; padding: 0.9rem 0; border-bottom: 1px solid #f1f5f9; align-items: center; }
        .profile-row:last-child { border-bottom: none; }
        .profile-label { min-width: 150px; font-size: 0.83rem; color: #64748b; display: flex; align-items: center; gap: 0.5rem; }
        .profile-value { font-weight: 600; color: #1e293b; font-size: 0.92rem; }
        .badge-level { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 3px 14px; border-radius: 20px; font-size: 0.78rem; }

        /* BANNER */
        .banner { border-radius: 10px; padding: 0.9rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; font-weight: 500; }
        .banner-danger { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
        .banner-info   { background: rgba(37,99,235,0.07);  color: #2563eb;  border: 1px solid rgba(37,99,235,0.18); }

        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .search-input { width: 200px; }
            .search-input:focus { width: 220px; }
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
            <?= strtoupper(substr($nama,0,1)) ?>
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
            <a href="login_anggota.php" class="nav-link <?= $hal=='beranda'?'active':'' ?>">
                <i class="bi bi-house-door-fill"></i><span>Beranda</span>
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
        <div class="sidebar-title">Lainnya</div>
        <div class="nav-item">
            <a href="login_anggota.php?hal=profil" class="nav-link <?= $hal=='profil'?'active':'' ?>">
                <i class="bi bi-person-circle"></i><span>Profile Saya</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-left"></i><span>Keluar</span>
            </a>
        </div>
    </div>
    <div class="sidebar-user">
        <img src="logo 40.jpeg" alt="User" onerror="this.outerHTML='<div style=&quot;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.1rem;&quot;><?= strtoupper(substr($nama,0,1)) ?></div>'">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">Anggota</div>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

<?php if ($hal === 'beranda'): ?>

    <!-- WELCOME -->
    <div class="welcome-section">
        <h1 class="welcome-title">
            <span style="font-size:2rem;">Selamat Datang</span>, <?= htmlspecialchars($nama) ?>!
        </h1>
        <p class="welcome-subtitle">Kamu Adalah Anggota Perpustakaan</p>
        <div class="divider"></div>
        <div class="stats-grid">
            <a href="peminjaman_anggota.php" class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-journals"></i></div>
                <div><div class="stat-label">Total Pinjaman</div><div class="stat-value"><?= $total_pinjam ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=aktif" class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-book-fill"></i></div>
                <div><div class="stat-label">Sedang Dipinjam</div><div class="stat-value"><?= $dipinjam ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=aktif&f=terlambat" class="stat-card">
                <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><div class="stat-label">Terlambat</div><div class="stat-value"><?= $terlambat ?></div></div>
            </a>
            <a href="peminjaman_anggota.php?tab=riwayat" class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="stat-label">Dikembalikan</div><div class="stat-value"><?= $dikembalikan ?></div></div>
            </a>
        </div>
    </div>

    <!-- BANNER -->
    <?php if ($terlambat > 0): ?>
    <div class="banner banner-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Kamu memiliki <strong><?= $terlambat ?> buku terlambat dikembalikan!</strong> Segera kembalikan ke petugas perpustakaan.
    </div>
    <?php elseif ($dipinjam > 0): ?>
    <div class="banner banner-info">
        <i class="bi bi-info-circle-fill"></i>
        Kamu sedang meminjam <strong><?= $dipinjam ?> buku</strong>. Jangan lupa kembalikan tepat waktu!
    </div>
    <?php endif; ?>

    <!-- TABEL PINJAMAN AKTIF -->
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-book-fill" style="font-size:1.5rem"></i> Buku Sedang Dipinjam</h1>
        <a href="peminjaman_anggota.php" style="font-size:0.88rem;color:#2563eb;text-decoration:none;font-weight:600;">Lihat Semua Riwayat →</a>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Judul Buku</th>
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
                        $badge = $late ? 'badge-terlambat' : 'badge-dipinjam';
                        $label = $late ? 'Terlambat ⚠️' : 'Dipinjam';
                        if ($late)         $cdown = '<span style="color:#dc2626;font-weight:700;">'.abs($sisa).' hari terlambat</span>';
                        elseif ($sisa==0)  $cdown = '<span style="color:#f59e0b;font-weight:700;">Hari ini!</span>';
                        elseif ($sisa<=3)  $cdown = '<span style="color:#f59e0b;font-weight:700;">Sisa '.$sisa.' hari</span>';
                        else               $cdown = '<span style="color:#10b981;font-weight:600;">Sisa '.$sisa.' hari</span>';
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($r['judul_buku']) ?></div>
                        <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($r['penulis']) ?></div>
                    </td>
                    <td><?= date('d M Y', strtotime($r['tanggal_peminjaman'])) ?></td>
                    <td>
                        <?php if ($late): ?>
                        <span style="color:#dc2626;font-weight:700;"><?= date('d M Y', strtotime($r['tanggal_pengembalian'])) ?></span>
                        <?php else: ?>
                        <?= date('d M Y', strtotime($r['tanggal_pengembalian'])) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status <?= $badge ?>"><?= $label ?></span></td>
                    <td><?= $cdown ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <i class="bi bi-book"></i>
                        <h5>Tidak Ada Buku Dipinjam</h5>
                        <p style="font-size:0.85rem;">Kamu tidak sedang meminjam buku apapun saat ini.</p>
                        <a href="buku_anggota.php" class="btn btn-primary btn-sm mt-2">Jelajahi Katalog Buku</a>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($hal === 'profil'): ?>

    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-person-circle" style="font-size:1.75rem;"></i> Profil Saya</h1>
    </div>

    <div class="profile-card">
        <div class="profile-avatar"><?= strtoupper(substr($nama,0,1)) ?></div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-person"></i> Nama Lengkap</div>
            <div class="profile-value"><?= htmlspecialchars($user['nama']) ?></div>
        </div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-at"></i> Username</div>
            <div class="profile-value"><?= htmlspecialchars($user['username']) ?></div>
        </div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-envelope"></i> Email</div>
            <div class="profile-value"><?= htmlspecialchars($user['email'] ?? '-') ?></div>
        </div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-geo-alt"></i> Alamat</div>
            <div class="profile-value"><?= htmlspecialchars($user['alamat'] ?? '-') ?></div>
        </div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-phone"></i> No. HP</div>
            <div class="profile-value"><?= htmlspecialchars($user['no_hp'] ?? '-') ?></div>
        </div>
        <div class="profile-row">
            <div class="profile-label"><i class="bi bi-shield-check"></i> Level</div>
            <div class="profile-value"><span class="badge-level">Anggota</span></div>
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