<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

$id_admin = $_SESSION['id_user'];

// ==================== PROSES SETUJUI / TOLAK ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $id_permintaan = intval($_POST['id_permintaan']);
    $catatan_admin = $conn->real_escape_string(trim($_POST['catatan_admin'] ?? ''));
    $now           = date('Y-m-d H:i:s');

    $req = $conn->query("SELECT * FROM persetujuan WHERE id_permintaan = $id_permintaan AND status = 'menunggu' LIMIT 1");
    if ($req && $req->num_rows > 0) {
        $data    = $req->fetch_assoc();
        $id_user = intval($data['id_user']);
        $id_buku = intval($data['id_buku']);
        $durasi  = intval($data['durasi_hari']);

        if ($_POST['aksi'] === 'setujui') {
            $conn->begin_transaction();
            try {
                $tgl_pinjam  = date('Y-m-d');
                $tgl_kembali = date('Y-m-d', strtotime("+$durasi days"));

                $conn->query("INSERT INTO peminjaman (id_user, id_buku, tanggal_peminjaman, tanggal_pengembalian, status_peminjaman)
                              VALUES ($id_user, $id_buku, '$tgl_pinjam', '$tgl_kembali', 'dipinjam')");
                $id_pinjam_baru = $conn->insert_id;

                $conn->query("UPDATE buku SET stok = stok - 1 WHERE id_buku = $id_buku AND stok > 0");

                $conn->query("UPDATE persetujuan SET status='disetujui', catatan_admin='$catatan_admin',
                              id_admin=$id_admin, tanggal_diproses='$now', id_peminjaman=$id_pinjam_baru
                              WHERE id_permintaan=$id_permintaan");

                $conn->commit();
                header("Location: persetujuan_admin.php?tab=disetujui&pesan=disetujui");
            } catch (Exception $e) {
                $conn->rollback();
                header("Location: persetujuan_admin.php?pesan=error");
            }

        } elseif ($_POST['aksi'] === 'tolak') {
            $conn->query("UPDATE persetujuan SET status='ditolak', catatan_admin='$catatan_admin',
                          id_admin=$id_admin, tanggal_diproses='$now'
                          WHERE id_permintaan=$id_permintaan");
            header("Location: persetujuan_admin.php?tab=ditolak&pesan=ditolak");
        }
    } else {
        header("Location: persetujuan_admin.php?pesan=error");
    }
    $conn->close();
    exit();
}

// ==================== QUERY DATA ====================
$tab = $_GET['tab'] ?? 'menunggu';
$tab = in_array($tab, ['menunggu','disetujui','ditolak']) ? $tab : 'menunggu';

$result = $conn->query("
    SELECT p.*, u.nama AS nama_anggota, u.username,
           b.judul_buku, b.penulis, b.stok,
           a.nama AS nama_admin
    FROM persetujuan p
    JOIN user u ON p.id_user = u.id_user
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN user a ON p.id_admin = a.id_user
    WHERE p.status = '$tab'
    ORDER BY p.tanggal_pengajuan DESC
");

$cnt_menunggu  = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='menunggu'")->fetch_assoc()['c'];
$cnt_disetujui = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='disetujui'")->fetch_assoc()['c'];
$cnt_ditolak   = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='ditolak'")->fetch_assoc()['c'];
require_once 'helper_katalog.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Persetujuan Peminjaman | <?= NAMA_PERPUS ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="katalog_styles.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
.main-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #0f172a; border-bottom: 1px solid #1e293b; display: flex; align-items: center; padding: 0 1.5rem; z-index: 1000; }
.brand { display: flex; align-items: center; gap: 12px; color: #f8fafc; text-decoration: none; font-size: 1.15rem; font-weight: 600; }
.brand img { width: 32px; height: 32px; border-radius: 6px; object-fit: cover; }
.search-box { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #334155; cursor: pointer; transition: opacity 0.2s; }
.user-avatar:hover { opacity: 0.85; }
.layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }
.sidebar { position: fixed; top: 60px; left: 0; bottom: 0; width: 260px; background: #0f172a; border-right: 1px solid #1e293b; overflow-y: auto; z-index: 900; display: flex; flex-direction: column; }
.sidebar-section { padding: 1.5rem 0; }
.sidebar-title { font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; padding: 0 1.5rem; margin-bottom: 0.75rem; }
.nav-item { margin: 0.25rem 0.875rem; }
.nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1rem; color: #94a3b8; text-decoration: none; border-radius: 8px; transition: background 0.15s, color 0.15s; font-size: 0.9rem; font-weight: 500; }
.nav-link:hover { background: #1e293b; color: #f8fafc; }
.nav-link.active { background: #2563eb; color: #ffffff; }
.nav-link i { font-size: 1.1rem; width: 20px; }
.notif-badge { background: #dc2626; color: white; font-size: 0.68rem; padding: 2px 7px; border-radius: 6px; margin-left: auto; font-weight: 700; }
.sidebar-user { margin-top: auto; padding: 1.25rem; border-top: 1px solid #1e293b; display: flex; align-items: center; gap: 0.75rem; background: #090d16; }
.sidebar-user img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #334155; object-fit: cover; }
.sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.9rem; }
.sidebar-user-role { font-size: 0.75rem; color: #94a3b8; text-transform: capitalize; }
.main-content { flex: 1; margin-left: 260px; padding: 2rem; background: #f8fafc; }
.page-header { background: #ffffff; border-radius: 12px; padding: 1.5rem 2rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 1.4rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.75rem; }
.tab-bar { background: #ffffff; border-radius: 12px; padding: 1rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.tab-btn { padding: 0.45rem 1rem; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; border: 1px solid #cbd5e1; color: #475569; transition: background 0.15s, border-color 0.15s, color 0.15s; display: inline-flex; align-items: center; gap: 0.5rem; background: #ffffff; }
.tab-btn:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
.tab-btn.active-orange { background: #d97706; border-color: #d97706; color: #ffffff; }
.tab-btn.active-green  { background: #16a34a; border-color: #16a34a; color: #ffffff; }
.tab-btn.active-red    { background: #dc2626; border-color: #dc2626; color: #ffffff; }
.cnt { background: rgba(255,255,255,0.25); padding: 1px 7px; border-radius: 6px; font-size: 0.72rem; }
.cnt-default { background: #e2e8f0; color: #475569; padding: 1px 7px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; }
.table-card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; }
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; }
th { padding: 0.9rem 1rem; text-align: left; font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #1e293b; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f8fafc; }
.badge-status { padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }
.badge-menunggu  { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-disetujui { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge-ditolak   { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.btn-action { padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid transparent; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-setujui { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.btn-setujui:hover { background: #15803d; color: #ffffff; border-color: #15803d; }
.btn-tolak   { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
.btn-tolak:hover   { background: #dc2626; color: #ffffff; border-color: #dc2626; }
.empty-state { text-align: center; padding: 3.5rem 1rem; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }
.modal-content { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
.modal-header { background: #0f172a; color: #ffffff; border-bottom: 1px solid #1e293b; border-radius: 0; padding: 1.15rem 1.5rem; }
.modal-header.red { background: #991b1b; border-bottom: 1px solid #7f1d1d; }
.modal-header .btn-close { filter: invert(1); }
.modal-body { padding: 1.5rem; background: #ffffff; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
.form-label { font-weight: 600; font-size: 0.85rem; color: #334155; }
.form-control { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.55rem 0.85rem; font-size: 0.9rem; }
.form-control:focus { border-color: #2563eb; box-shadow: none; }
.btn-primary-custom { background: #15803d; color: white; border: none; border-radius: 8px; padding: 0.55rem 1.2rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.15s; }
.btn-primary-custom:hover { background: #166534; }
.btn-danger-custom  { background: #dc2626; color: white; border: none; border-radius: 8px; padding: 0.55rem 1.2rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.15s; }
.btn-danger-custom:hover  { background: #b91c1c; }
.info-box     { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1rem; font-size: 0.88rem; color: #166534; }
.info-box-red { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1rem; font-size: 0.88rem; color: #991b1b; }
.alert-floating { position: fixed; top: 75px; right: 1.5rem; z-index: 9999; min-width: 280px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s ease; }
@keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.mobile-toggle { display: none; background: none; border: none; color: #f8fafc; font-size: 1.5rem; cursor: pointer; margin-right: 1rem; }
@media (max-width: 991px) {
    .mobile-toggle { display: block; }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.show { transform: translateX(0); }
    .main-content { margin-left: 0; }
}
</style>
</head>
<body>

<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="beranda.php" class="brand">
        <img src="logo 40.jpeg" alt="Logo">
        <span>PerpusOnline</span>
    </a>
    <div class="search-box">
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex" style="font-size:0.8rem;">
            <i class="bi bi-globe me-1"></i>Katalog Publik
        </a>
        <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="profile_admin.php">Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php">Keluar</a></li>
        </ul>
    </div>
</header>

<div class="layout-wrapper">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item"><a href="beranda.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda</span></a></div>
        <div class="nav-item"><a href="buku.php" class="nav-link"><i class="bi bi-book"></i><span>Buku</span></a></div>
        <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
        <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
        <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
        <div class="nav-item">
            <a href="persetujuan_admin.php" class="nav-link active d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check2-square"></i><span>Persetujuan</span>
                </div>
                <?php if ($cnt_menunggu > 0): ?>
                    <span class="sidebar-notif-badge"><?= $cnt_menunggu ?></span>
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
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($_SESSION['level']) ?></div>
        </div>
    </div>
</aside>

<main class="main-content">

<?php if (isset($_GET['pesan'])): ?>
    <?php if ($_GET['pesan'] == 'disetujui'): ?>
        <div class="alert alert-success alert-floating">âœ… Pengajuan berhasil disetujui dan peminjaman dibuat.</div>
    <?php elseif ($_GET['pesan'] == 'ditolak'): ?>
        <div class="alert alert-warning alert-floating">âŒ Pengajuan berhasil ditolak.</div>
    <?php elseif ($_GET['pesan'] == 'error'): ?>
        <div class="alert alert-danger alert-floating">âš ï¸ Terjadi kesalahan. Silakan coba lagi.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-check2-square" style="font-size:1.75rem;"></i> Persetujuan Peminjaman
    </h1>
</div>

<div class="tab-bar">
    <a href="?tab=menunggu"  class="tab-btn <?= $tab=='menunggu'  ? 'active-orange' : '' ?>">
        <i class="bi bi-hourglass-split"></i> Menunggu
        <span class="<?= $tab=='menunggu'  ? 'cnt' : 'cnt-default' ?>"><?= $cnt_menunggu ?></span>
    </a>
    <a href="?tab=disetujui" class="tab-btn <?= $tab=='disetujui' ? 'active-green'  : '' ?>">
        <i class="bi bi-check-circle"></i> Disetujui
        <span class="<?= $tab=='disetujui' ? 'cnt' : 'cnt-default' ?>"><?= $cnt_disetujui ?></span>
    </a>
    <a href="?tab=ditolak"   class="tab-btn <?= $tab=='ditolak'   ? 'active-red'    : '' ?>">
        <i class="bi bi-x-circle"></i> Ditolak
        <span class="<?= $tab=='ditolak'   ? 'cnt' : 'cnt-default' ?>"><?= $cnt_ditolak ?></span>
    </a>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Anggota</th>
                    <th>Judul Buku</th>
                    <th>Tgl Pengajuan</th>
                    <th>Durasi</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th><?= $tab == 'menunggu' ? 'Aksi' : 'Catatan Admin' ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0):
                $no = 1;
                while ($row = $result->fetch_assoc()):
                    $tgl_kembali = date('d M Y', strtotime($row['tanggal_pengajuan'] . ' +' . $row['durasi_hari'] . ' days'));
            ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($row['nama_anggota']) ?></div>
                        <div style="font-size:0.78rem;color:#94a3b8;">@<?= htmlspecialchars($row['username']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:500;"><?= htmlspecialchars($row['judul_buku']) ?></div>
                        <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($row['penulis']) ?></div>
                    </td>
                    <td><?= date('d M Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                    <td>
                        <?= $row['durasi_hari'] ?> hari
                        <div style="font-size:0.75rem;color:#94a3b8;">s/d <?= $tgl_kembali ?></div>
                    </td>
                    <td><?= $row['alasan'] ? htmlspecialchars($row['alasan']) : '<span style="color:#94a3b8;">â€”</span>' ?></td>
                    <td>
                        <?php if ($row['status'] == 'menunggu'): ?>
                            <span class="badge-status badge-menunggu">â³ Menunggu</span>
                        <?php elseif ($row['status'] == 'disetujui'): ?>
                            <span class="badge-status badge-disetujui">âœ… Disetujui</span>
                        <?php else: ?>
                            <span class="badge-status badge-ditolak">âŒ Ditolak</span>
                        <?php endif; ?>
                    </td>
                    <td>
                    <?php if ($tab == 'menunggu'): ?>
                        <div style="display:flex;gap:0.4rem;">
                            <button class="btn-action btn-setujui"
                                onclick="bukaSetujui(<?= $row['id_permintaan'] ?>, '<?= addslashes(htmlspecialchars($row['nama_anggota'])) ?>', '<?= addslashes(htmlspecialchars($row['judul_buku'])) ?>')">
                                <i class="bi bi-check-lg"></i> Setujui
                            </button>
                            <button class="btn-action btn-tolak"
                                onclick="bukaTolak(<?= $row['id_permintaan'] ?>, '<?= addslashes(htmlspecialchars($row['nama_anggota'])) ?>', '<?= addslashes(htmlspecialchars($row['judul_buku'])) ?>')">
                                <i class="bi bi-x-lg"></i> Tolak
                            </button>
                        </div>
                    <?php else: ?>
                        <?= $row['catatan_admin'] ? htmlspecialchars($row['catatan_admin']) : '<span style="color:#94a3b8;">â€”</span>' ?>
                        <?php if ($row['tanggal_diproses']): ?>
                            <div style="font-size:0.75rem;color:#94a3b8;">
                                oleh <?= htmlspecialchars($row['nama_admin'] ?? 'Admin') ?>,
                                <?= date('d M Y', strtotime($row['tanggal_diproses'])) ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h5>Tidak Ada Data</h5>
                        <p style="font-size:0.85rem;">Belum ada pengajuan dengan status "<?= $tab ?>".</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
</div>

<!-- MODAL SETUJUI -->
<div class="modal fade" id="modalSetujui" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Konfirmasi Persetujuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="setujui">
                <input type="hidden" name="id_permintaan" id="s_id">
                <div class="modal-body">
                    <div class="info-box" id="s_info"></div>
                    <div class="mb-2">
                        <label class="form-label"><i class="bi bi-chat-text me-1"></i>Catatan untuk anggota <span style="color:#94a3b8;font-weight:400;">(opsional)</span></label>
                        <textarea name="catatan_admin" class="form-control" rows="2" placeholder="Contoh: Jangan lupa di kembalikan yaa..." style="resize:none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg"></i> Ya, Setujui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TOLAK -->
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header red">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Konfirmasi Penolakan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="tolak">
                <input type="hidden" name="id_permintaan" id="t_id">
                <div class="modal-body">
                    <div class="info-box-red" id="t_info"></div>
                    <div class="mb-2">
                        <label class="form-label"><i class="bi bi-chat-text me-1"></i>Alasan penolakan <span style="color:#ef4444;">*</span></label>
                        <textarea name="catatan_admin" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..." style="resize:none;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-danger-custom"><i class="bi bi-x-lg"></i> Ya, Tolak</button>
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
function bukaSetujui(id, anggota, buku) {
    document.getElementById('s_id').value = id;
    document.getElementById('s_info').innerHTML = 'âœ… Setujui pengajuan dari <strong>' + anggota + '</strong> untuk buku <strong>' + buku + '</strong>?';
    new bootstrap.Modal(document.getElementById('modalSetujui')).show();
}
function bukaTolak(id, anggota, buku) {
    document.getElementById('t_id').value = id;
    document.getElementById('t_info').innerHTML = 'âŒ Tolak pengajuan dari <strong>' + anggota + '</strong> untuk buku <strong>' + buku + '</strong>?';
    new bootstrap.Modal(document.getElementById('modalTolak')).show();
}
setTimeout(() => {
    const al = document.querySelector('.alert-floating');
    if (al) { al.style.transition = 'opacity 0.5s'; al.style.opacity = '0'; setTimeout(() => al.remove(), 500); }
}, 4000);
</script>
</body>
</html>
<?php $conn->close(); ?>