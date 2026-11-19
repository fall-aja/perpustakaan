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

// ==================== DETEKSI TABEL USER/ANGGOTA ====================
$tabelUser  = null;
$kolomPK    = null;
$kolomNama  = null;
$semuaTabel = [];

$resTabel = $conn->query("SHOW TABLES");
while ($t = $resTabel->fetch_row()) {
    $semuaTabel[] = $t[0];
}

// Cari tabel user/anggota
$kandidat = ['users', 'anggota', 'user', 'member', 'pengguna'];
foreach ($kandidat as $k) {
    if (in_array($k, $semuaTabel)) {
        $tabelUser = $k;
        break;
    }
}

// ==================== PROSES TAMBAH PEMINJAMAN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $id_user              = intval($_POST['id_user']);
    $id_buku              = intval($_POST['id_buku']);
    $tanggal_peminjaman   = $conn->real_escape_string($_POST['tanggal_peminjaman']);
    $tanggal_pengembalian = $conn->real_escape_string($_POST['tanggal_pengembalian']);
    
    // Deteksi kolom stok
    $kolomStok = null;
    $resBukuCols = $conn->query("SHOW COLUMNS FROM `buku`");
    if ($resBukuCols) {
        $bukuCols = [];
        while ($bc = $resBukuCols->fetch_assoc()) {
            $bukuCols[] = $bc['Field'];
        }
        foreach (['stok_tersedia', 'stok', 'jumlah', 'jumlah_buku', 'stok_buku', 'qty', 'quantity'] as $ks) {
            if (in_array($ks, $bukuCols)) {
                $kolomStok = $ks;
                break;
            }
        }
    }
    
    // Cek stok
    $stok = 0;
    if ($kolomStok) {
        $cekStok = $conn->query("SELECT `$kolomStok` AS stok FROM buku WHERE id_buku = $id_buku");
        if ($cekStok && $cekStok->num_rows > 0) {
            $stok = intval($cekStok->fetch_assoc()['stok']);
        }
    }
    
    if ($stok > 0) {
        // Cek apakah buku sudah dipinjam dan belum dikembalikan
        $cekPinjam = $conn->query("SELECT id_peminjaman FROM peminjaman WHERE id_buku = $id_buku AND id_user = $id_user AND status_peminjaman = 'dipinjam'");
        
        if ($cekPinjam && $cekPinjam->num_rows > 0) {
            header("Location: peminjaman.php?pesan=sudah_dipinjam");
            exit();
        }
        
        $query = "INSERT INTO peminjaman (id_user, id_buku, tanggal_peminjaman, tanggal_pengembalian, status_peminjaman)
                  VALUES ($id_user, $id_buku, '$tanggal_peminjaman', '$tanggal_pengembalian', 'dipinjam')";
        
        if ($conn->query($query)) {
            // Kurangi stok
            if ($kolomStok) {
                $conn->query("UPDATE buku SET `$kolomStok` = `$kolomStok` - 1 WHERE id_buku = $id_buku");
            }
            header("Location: peminjaman.php?pesan=tambah_sukses");
        } else {
            header("Location: peminjaman.php?pesan=error&err=" . urlencode($conn->error));
        }
    } else {
        header("Location: peminjaman.php?pesan=stok_habis");
    }
    exit();
}

// Deteksi kolom nama user
if ($tabelUser) {
    $cols = [];
    $resCols = $conn->query("SHOW COLUMNS FROM `$tabelUser`");
    while ($c = $resCols->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
    
    foreach (['nama', 'name', 'nama_lengkap', 'full_name', 'username'] as $cn) {
        if (in_array($cn, $cols)) { 
            $kolomNama = $cn; 
            break; 
        }
    }
    
    foreach (['id_user', 'id_anggota', 'id', 'id_member', 'id_pengguna'] as $cp) {
        if (in_array($cp, $cols)) { 
            $kolomPK = $cp; 
            break; 
        }
    }
}

// ==================== DETEKSI KOLOM STOK ====================
$kolomStok = null;
$resBukuCols = $conn->query("SHOW COLUMNS FROM `buku`");
if ($resBukuCols) {
    $bukuCols = [];
    while ($bc = $resBukuCols->fetch_assoc()) {
        $bukuCols[] = $bc['Field'];
    }
    foreach (['stok_tersedia', 'stok', 'jumlah', 'jumlah_buku', 'stok_buku', 'qty', 'quantity', 'total'] as $ks) {
        if (in_array($ks, $bukuCols)) {
            $kolomStok = $ks;
            break;
        }
    }
}

// ==================== PROSES HAPUS ====================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    $getPinjam = $conn->query("SELECT id_buku, status_peminjaman FROM peminjaman WHERE id_peminjaman = $id");
    if ($getPinjam && $row = $getPinjam->fetch_assoc()) {
        // Jika masih dipinjam, kembalikan stok
        if ($row['status_peminjaman'] === 'dipinjam' && $kolomStok) {
            $conn->query("UPDATE buku SET `$kolomStok` = `$kolomStok` + 1 WHERE id_buku = " . intval($row['id_buku']));
        }
    }
    
    $conn->query("DELETE FROM peminjaman WHERE id_peminjaman = $id");
    header("Location: peminjaman.php?pesan=hapus_sukses");
    exit();
}

// ==================== PROSES KEMBALIKAN BUKU ====================
if (isset($_GET['kembalikan'])) {
    $id = intval($_GET['kembalikan']);
    
    $getPinjam = $conn->query("SELECT id_buku FROM peminjaman WHERE id_peminjaman = $id");
    if ($getPinjam && $row = $getPinjam->fetch_assoc()) {
        // Tambah stok kembali
        if ($kolomStok) {
            $conn->query("UPDATE buku SET `$kolomStok` = `$kolomStok` + 1 WHERE id_buku = " . intval($row['id_buku']));
        }
    }
    
    $conn->query("UPDATE peminjaman SET status_peminjaman = 'dikembalikan' WHERE id_peminjaman = $id");
    header("Location: peminjaman.php?pesan=kembali_sukses");
    exit();
}

// ==================== QUERY DATA ====================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search        = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

// Build query untuk menampilkan semua peminjaman dari anggota
if ($tabelUser && $kolomPK && $kolomNama) {
    $selectNama = "u.$kolomNama AS nama_user";
    $joinStr    = "LEFT JOIN `$tabelUser` u ON p.id_user = u.$kolomPK";
    $searchCond = $search ? " AND (u.$kolomNama LIKE '%$search%' OR b.judul_buku LIKE '%$search%')" : '';
} else {
    $selectNama = "p.id_user AS nama_user";
    $joinStr    = "";
    $searchCond = $search ? " AND b.judul_buku LIKE '%$search%'" : '';
}

$query = "SELECT p.*, $selectNama, b.judul_buku, b.penulis
          FROM peminjaman p
          $joinStr
          LEFT JOIN buku b ON p.id_buku = b.id_buku
          WHERE 1=1";

if ($status_filter) {
    $sf     = $conn->real_escape_string($status_filter);
    $query .= " AND p.status_peminjaman = '$sf'";
}

$query .= $searchCond;
$query .= " ORDER BY p.id_peminjaman DESC";

$result = $conn->query($query);

// Dropdown user untuk modal tambah
if ($tabelUser && $kolomPK && $kolomNama) {
    $daftar_user = $conn->query("SELECT $kolomPK AS id_user, $kolomNama AS nama FROM `$tabelUser` ORDER BY $kolomNama ASC");
} else {
    $daftar_user = null;
}

// Dropdown buku untuk modal tambah
if ($kolomStok) {
    $daftar_buku = $conn->query("SELECT id_buku, judul_buku, `$kolomStok` AS stok FROM buku WHERE `$kolomStok` > 0 ORDER BY judul_buku ASC");
} else {
    $daftar_buku = $conn->query("SELECT id_buku, judul_buku FROM buku ORDER BY judul_buku ASC");
}

// Statistik
$total_semua     = $conn->query("SELECT COUNT(*) AS c FROM peminjaman")->fetch_assoc()['c'];
$total_pinjam    = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam'")->fetch_assoc()['c'];
$total_kembali   = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dikembalikan'")->fetch_assoc()['c'];
$total_terlambat = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE status_peminjaman = 'dipinjam' AND tanggal_pengembalian < CURDATE()")->fetch_assoc()['c'];

require_once 'helper_katalog.php';
$menunggu_acc    = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status = 'menunggu'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peminjaman | <?= NAMA_PERPUS ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="katalog_styles.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #0f172a; }
.main-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #0f172a; border-bottom: 1px solid #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; padding: 0 1.5rem; z-index: 1000; }
.brand { display: flex; align-items: center; gap: 12px; color: #f8fafc; text-decoration: none; font-size: 1.15rem; font-weight: 700; letter-spacing: 0.2px; }
.brand img { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #334155; }
.search-box { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
.search-input { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 0.45rem 1rem; color: #f8fafc; width: 260px; font-size: 0.88rem; transition: all 0.2s; outline: none; }
.search-input::placeholder { color: #94a3b8; }
.search-input:focus { background: #0f172a; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); width: 300px; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #334155; cursor: pointer; transition: border-color 0.2s; }
.user-avatar:hover { border-color: #60a5fa; }
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
.page-header { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 1.35rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.65rem; }
.page-title i { color: #1d4ed8; }
.btn-primary-custom { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 8px; padding: 0.55rem 1.2rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: background-color 0.15s ease; font-size: 0.88rem; cursor: pointer; }
.btn-primary-custom:hover { background: #1e40af; border-color: #1e40af; color: white; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
.stat-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.4rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1rem; transition: transform 0.15s ease, border-color 0.15s ease; }
.stat-card:hover { transform: translateY(-2px); border-color: #cbd5e1; }
.stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
.stat-icon.blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
.stat-icon.orange { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.stat-icon.red    { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.stat-icon.green  { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
.stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.2rem; }
.stat-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.filter-bar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.9rem 1.35rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.filter-btn { padding: 0.35rem 0.85rem; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 500; border: 1px solid #cbd5e1; color: #334155; transition: all 0.15s ease; background: #ffffff; display: inline-flex; align-items: center; gap: 0.3rem; }
.filter-btn:hover { background: #eff6ff; border-color: #1d4ed8; color: #1d4ed8; }
.filter-btn.active        { background: #1d4ed8; border-color: #1d4ed8; color: white; font-weight: 600; }
.filter-btn.active.orange { background: #b45309; border-color: #b45309; color: white; font-weight: 600; }
.filter-btn.active.green  { background: #15803d; border-color: #15803d; color: white; font-weight: 600; }
.search-filter { margin-left: auto; display: flex; align-items: center; gap: 0.4rem; }
.search-filter input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.85rem; color: #0f172a; width: 210px; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
.search-filter input:focus { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
.search-filter button { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.88rem; cursor: pointer; transition: background-color 0.15s; }
.search-filter button:hover { background: #1e40af; }
.table-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; }
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
.btn-action { padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.78rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; border: 1px solid transparent; cursor: pointer; transition: all 0.15s ease; }
.btn-kembali { background: #f0fdf4; color: #15803d; border-color: #dcfce7; }
.btn-kembali:hover { background: #15803d; color: white; }
.btn-hapus   { background: #fef2f2; color: #b91c1c; border-color: #fee2e2; }
.btn-hapus:hover   { background: #dc2626; color: white; }
.empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
.empty-state i { font-size: 2.8rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }
.modal-content { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.modal-header { background: #0f172a; color: white; border-bottom: 1px solid #1e293b; padding: 1.1rem 1.5rem; }
.modal-header .btn-close { filter: invert(1); }
.modal-body { padding: 1.5rem; background: #ffffff; }
.modal-footer { padding: 0.9rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
.form-label { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
.form-control, .form-select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.85rem; font-size: 0.88rem; }
.form-control:focus, .form-select:focus { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
.alert-floating { position: fixed; top: 75px; right: 1.5rem; z-index: 9999; min-width: 280px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); animation: slideIn 0.25s ease; }
@keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.info-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: #92400e; display: flex; align-items: center; gap: 0.5rem; }
.mobile-toggle { display: none; background: none; border: none; color: #f8fafc; font-size: 1.4rem; cursor: pointer; margin-right: 1rem; }
@media (max-width: 991px) {
.mobile-toggle { display: block; }
.sidebar { transform: translateX(-100%); transition: transform 0.25s ease; }
.sidebar.show { transform: translateX(0); }
.main-content { margin-left: 0; padding: 1.5rem 1rem; }
.search-input { width: 180px; }
.page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
.search-filter { margin-left: 0; }
}
</style>
</head>
<body>
<!-- ALERT -->
<?php if (isset($_GET['pesan'])):
$alertClass = 'success'; 
$alertIcon = 'check-circle-fill'; 
$alertMsg = '';

if ($_GET['pesan'] === 'tambah_sukses') { 
    $alertMsg = 'Peminjaman berhasil ditambahkan!'; 
} elseif ($_GET['pesan'] === 'sudah_dipinjam') { 
    $alertClass = 'warning'; 
    $alertIcon = 'exclamation-circle-fill'; 
    $alertMsg = 'Buku ini sedang dipinjam oleh anggota tersebut!'; 
} elseif ($_GET['pesan'] === 'hapus_sukses') { 
    $alertMsg = 'Data peminjaman berhasil dihapus.'; 
} elseif ($_GET['pesan'] === 'kembali_sukses') { 
    $alertMsg = 'Buku berhasil dikembalikan!'; 
} elseif ($_GET['pesan'] === 'stok_habis') { 
    $alertClass = 'warning'; 
    $alertIcon = 'exclamation-triangle-fill'; 
    $alertMsg = 'Stok buku habis!'; 
}
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
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item"><a href="beranda.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda</span></a></div>
        <div class="nav-item"><a href="buku.php" class="nav-link"><i class="bi bi-book"></i><span>Buku</span></a></div>
        <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
        <div class="nav-item"><a href="peminjaman.php" class="nav-link active"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
        <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
        <div class="nav-item">
            <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check2-square"></i>
                    <span>Persetujuan</span>
                </div>
                <?php if ($menunggu_acc > 0): ?>
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
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['nama']); ?></div>
            <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['level']); ?></div>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
<?php if (!$tabelUser): ?>
<div class="info-box">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>Tabel data user/anggota tidak ditemukan. Pastikan tabel 'users' atau 'anggota' sudah dibuat.</span>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-clock-history" style="font-size:1.75rem;"></i> 
        Data Peminjaman
    </h1>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg"></i> Tambah Peminjaman
    </button>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-journal-text"></i></div>
        <div>
            <div class="stat-label">Total Peminjaman</div>
            <div class="stat-value"><?= $total_semua ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-clock"></i></div>
        <div>
            <div class="stat-label">Sedang Dipinjam</div>
            <div class="stat-value"><?= $total_pinjam ?></div>
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
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div>
            <div class="stat-label">Dikembalikan</div>
            <div class="stat-value"><?= $total_kembali ?></div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <a href="peminjaman.php" class="filter-btn <?= $status_filter == '' ? 'active' : '' ?>">
        <i class="bi bi-grid"></i> Semua
    </a>
    <a href="peminjaman.php?status=dipinjam" class="filter-btn orange <?= $status_filter == 'dipinjam' ? 'active' : '' ?>">
        <i class="bi bi-clock"></i> Dipinjam
    </a>
    <a href="peminjaman.php?status=dikembalikan" class="filter-btn green <?= $status_filter == 'dikembalikan' ? 'active' : '' ?>">
        <i class="bi bi-check2-circle"></i> Dikembalikan
    </a>
    <div class="search-filter">
        <form method="GET" style="display:flex;gap:0.5rem;">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Cari nama / judul buku..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Peminjam</th>
                    <th>Judul Buku</th>
                    <th>Tgl Peminjaman</th>
                    <th>Tgl Pengembalian</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0):
                $no = 1;
                while ($row = $result->fetch_assoc()):
                    $terlambat = ($row['status_peminjaman'] === 'dipinjam' && $row['tanggal_pengembalian'] < date('Y-m-d'));
                    
                    if ($terlambat) {
                        $statusLabel = 'Terlambat'; 
                        $badgeClass = 'badge-terlambat';
                    } elseif ($row['status_peminjaman'] === 'dikembalikan') {
                        $statusLabel = 'Dikembalikan'; 
                        $badgeClass = 'badge-dikembalikan';
                    } else {
                        $statusLabel = 'Dipinjam'; 
                        $badgeClass = 'badge-dipinjam';
                    }
                ?>
                <tr class="<?= $terlambat ? 'row-terlambat' : '' ?>">
                    <td><?= $no++ ?></td>
                    <td>
                        <div style="font-weight:600;">
                            <?php
                            $namaUser = $row['nama_user'] ?? null;
                            echo $namaUser ? htmlspecialchars($namaUser) : '<span style="color:#94a3b8;">User #' . $row['id_user'] . '</span>';
                            ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:500;"><?= htmlspecialchars($row['judul_buku'] ?? '-') ?></div>
                        <div style="font-size:0.78rem;color:#94a3b8;"><?= htmlspecialchars($row['penulis'] ?? '') ?></div>
                    </td>
                    <td><?= date('d M Y', strtotime($row['tanggal_peminjaman'])) ?></td>
                    <td>
                        <?php if ($terlambat):
                            $hariLambat = (int)((strtotime('now') - strtotime($row['tanggal_pengembalian'])) / 86400); 
                        ?>
                            <span style="color:#dc2626;font-weight:700;"><?= date('d M Y', strtotime($row['tanggal_pengembalian'])) ?></span>
                            <div style="font-size:0.75rem;color:#dc2626;"><?= $hariLambat ?> hari terlambat</div>
                        <?php else: ?>
                            <?= date('d M Y', strtotime($row['tanggal_pengembalian'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-status <?= $badgeClass ?>">
                            <?= $statusLabel ?> <?= $terlambat ? 'âš ï¸' : '' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <?php if ($row['status_peminjaman'] === 'dipinjam'): ?>
                                <a href="peminjaman.php?kembalikan=<?= $row['id_peminjaman'] ?>" 
                                   class="btn-action btn-kembali" 
                                   onclick="return confirm('Konfirmasi pengembalian buku ini?')">
                                    <i class="bi bi-arrow-return-left"></i> Kembalikan
                                </a>
                            <?php endif; ?>
                            <a href="peminjaman.php?hapus=<?= $row['id_peminjaman'] ?>" 
                               class="btn-action btn-hapus" 
                               onclick="return confirm('Yakin hapus data peminjaman ini?')">
                                <i class="bi bi-trash"></i> Hapus
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-clock-history"></i>
                            <h5>Tidak Ada Data Peminjaman</h5>
                            <p style="font-size:0.85rem;">Belum ada data yang sesuai dengan filter.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Peminjaman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person me-1"></i>Peminjam</label>
                        <select name="id_user" class="form-select" required>
                            <option value="">-- Pilih Peminjam --</option>
                            <?php if ($daftar_user && $daftar_user->num_rows > 0):
                                while ($u = $daftar_user->fetch_assoc()): 
                            ?>
                                <option value="<?= $u['id_user'] ?>"><?= htmlspecialchars($u['nama']) ?></option>
                            <?php endwhile; else: ?>
                                <option disabled>Tabel user tidak ditemukan</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-book me-1"></i>Buku</label>
                        <select name="id_buku" class="form-select" required>
                            <option value="">-- Pilih Buku --</option>
                            <?php if ($daftar_buku && $daftar_buku->num_rows > 0):
                                while ($b = $daftar_buku->fetch_assoc()): 
                            ?>
                                <option value="<?= $b['id_buku'] ?>">
                                    <?= htmlspecialchars($b['judul_buku']) ?> 
                                    <?= isset($b['stok']) ? '(Stok: ' . $b['stok'] . ')' : '' ?>
                                </option>
                            <?php endwhile; else: ?>
                                <option disabled>Tidak ada buku tersedia</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-calendar me-1"></i>Tanggal Peminjaman</label>
                        <input type="date" name="tanggal_peminjaman" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-calendar-check me-1"></i>Tanggal Pengembalian</label>
                        <input type="date" name="tanggal_pengembalian" class="form-control" 
                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
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

// Auto-hide alert setelah 4 detik
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