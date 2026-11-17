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

// ==================== PROSES HAPUS ====================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Cek apakah anggota ini masih punya peminjaman aktif
    $cekPinjam = $conn->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_user = $id AND status_peminjaman = 'dipinjam'");
    $jmlPinjam = ($cekPinjam && $row = $cekPinjam->fetch_assoc()) ? $row['c'] : 0;
    if ($jmlPinjam > 0) {
        header("Location: anggota.php?pesan=hapus_gagal");
    } else {
        $conn->query("DELETE FROM user WHERE id_user = $id");
        header("Location: anggota.php?pesan=hapus_sukses");
    }
    exit();
}

// ==================== PROSES TAMBAH ANGGOTA ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $nama     = $conn->real_escape_string(trim($_POST['nama']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $conn->real_escape_string(trim($_POST['password']));
    $email    = $conn->real_escape_string(trim($_POST['email']));
    $alamat   = $conn->real_escape_string(trim($_POST['alamat']));
    $no_hp    = $conn->real_escape_string(trim($_POST['no_hp']));
    $level    = 'anggota';

    // Cek username sudah ada?
    $cekUser = $conn->query("SELECT id_user FROM user WHERE username = '$username'");
    if ($cekUser && $cekUser->num_rows > 0) {
        header("Location: anggota.php?pesan=username_duplikat");
    } else {
        $conn->query("INSERT INTO user (nama, username, password, email, alamat, no_hp, level)
                      VALUES ('$nama','$username','$password','$email','$alamat','$no_hp','$level')");
        header("Location: anggota.php?pesan=tambah_sukses");
    }
    exit();
}

// ==================== PROSES EDIT ANGGOTA ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id       = intval($_POST['id_user']);
    $nama     = $conn->real_escape_string(trim($_POST['nama']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email    = $conn->real_escape_string(trim($_POST['email']));
    $alamat   = $conn->real_escape_string(trim($_POST['alamat']));
    $no_hp    = $conn->real_escape_string(trim($_POST['no_hp']));

    // Update password hanya jika diisi
    if (!empty(trim($_POST['password']))) {
        $password = $conn->real_escape_string(trim($_POST['password']));
        $conn->query("UPDATE user SET nama='$nama', username='$username', password='$password', email='$email', alamat='$alamat', no_hp='$no_hp' WHERE id_user=$id");
    } else {
        $conn->query("UPDATE user SET nama='$nama', username='$username', email='$email', alamat='$alamat', no_hp='$no_hp' WHERE id_user=$id");
    }
    header("Location: anggota.php?pesan=edit_sukses");
    exit();
}

// ==================== QUERY DATA ====================
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$level_filter = isset($_GET['level']) ? $conn->real_escape_string($_GET['level']) : '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (nama LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($level_filter) {
    $where .= " AND level = '$level_filter'";
}

$result = $conn->query("SELECT * FROM user $where ORDER BY id_user ASC");

// Statistik
$total_semua   = $conn->query("SELECT COUNT(*) AS c FROM user")->fetch_assoc()['c'];
$total_anggota = $conn->query("SELECT COUNT(*) AS c FROM user WHERE level='anggota'")->fetch_assoc()['c'];
$total_admin   = $conn->query("SELECT COUNT(*) AS c FROM user WHERE level='admin'")->fetch_assoc()['c'];

// Hitung anggota yang sedang meminjam
$total_aktif   = $conn->query("SELECT COUNT(DISTINCT id_user) AS c FROM peminjaman WHERE status_peminjaman='dipinjam'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>

        /* =============================================
           RESET & BASE
        ============================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            background: #e7e7e7d2;
        }


        /* =============================================
           HEADER
        ============================================= */
        .main-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: linear-gradient(135deg, #2d56b6 0%, #4172c1 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .brand img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .search-box {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(177, 177, 177, 0.53);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
            width: 280px;
            transition: all 0.3s;
        }

        .search-input::placeholder { color: rgba(230, 230, 230, 0.7); }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            outline: none;
            width: 320px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(233, 233, 233, 0.8);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-avatar:hover { transform: scale(1.05); }


        /* =============================================
           LAYOUT WRAPPER
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
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            overflow-y: auto;
            z-index: 900;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section { padding: 1.5rem 0; }

        .sidebar-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
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
            color: rgba(245, 245, 245, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        .sidebar-user {
            margin-top: auto;
            padding: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(227, 227, 227, 0.8);
        }

        .sidebar-user-name {
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
        }


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
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4879c8;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.25rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .btn-primary-custom:hover {
            opacity: 0.9;
            color: white;
            transform: translateY(-1px);
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
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }

        .stat-card:hover { transform: translateY(-3px); }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-icon.blue   { background: rgba(37,  99, 235, 0.1); color: #2563eb; }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
        .stat-icon.orange { background: rgba(245,158,  11, 0.1); color: #f59e0b; }
        .stat-icon.green  { background: rgba( 16,185, 129, 0.1); color: #10b981; }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 0.2rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
        }


        /* =============================================
           FILTER BAR
        ============================================= */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1.5px solid #e2e8f0;
            color: #64748b;
            transition: all 0.2s;
            background: transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .filter-btn:hover { background: #f1f5f9; color: #1e293b; }

        .filter-btn.active        { background: #2563eb; border-color: #2563eb; color: white; }
        .filter-btn.active.purple { background: #7c3aed; border-color: #7c3aed; color: white; }
        .filter-btn.active.orange { background: #f59e0b; border-color: #f59e0b; color: white; }

        .search-filter {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-filter input {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            color: #1e293b;
            width: 220px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-filter input:focus { border-color: #2563eb; }

        .search-filter button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.85rem;
            font-size: 0.9rem;
            cursor: pointer;
        }


        /* =============================================
           TABEL
        ============================================= */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr { background: #f8fafc; }

        th {
            padding: 0.9rem 1rem;
            text-align: left;
            font-size: 0.78rem;
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
            font-size: 0.88rem;
            color: #1e293b;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }


        /* =============================================
           BADGE & AVATAR
        ============================================= */
        .badge-level {
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-admin   { background: rgba( 37, 99, 235, 0.12); color: #1d4ed8; }
        .badge-anggota { background: rgba( 16,185, 129, 0.12); color: #059669; }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }


        /* =============================================
           TOMBOL AKSI
        ============================================= */
        .btn-action {
            padding: 0.3rem 0.65rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit               { background: rgba( 37, 99, 235, 0.1); color: #2563eb; }
        .btn-edit:hover         { background: #2563eb; color: white; }

        .btn-hapus              { background: rgba(239, 68,  68, 0.1); color: #dc2626; }
        .btn-hapus:hover        { background: #ef4444; color: white; }


        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }


        /* =============================================
           MODAL
        ============================================= */
        .modal-content {
            border: none;
            border-radius: 16px;
        }

        .modal-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.25rem 1.5rem;
        }

        .modal-header .btn-close { filter: invert(1); }

        .modal-body  { padding: 1.5rem; }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
        }

        .form-control,
        .form-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.55rem 0.85rem;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: none;
        }


        /* =============================================
           ALERT FLOATING
        ============================================= */
        .alert-floating {
            position: fixed;
            top: 75px;
            right: 1.5rem;
            z-index: 9999;
            min-width: 280px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
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
            .mobile-toggle   { display: block; }
            .sidebar         { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show    { transform: translateX(0); }
            .main-content    { margin-left: 0; }
            .search-input    { width: 180px; }
            .page-header     { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .search-filter   { margin-left: 0; }
        }

    </style>
</head>
<body>

<!-- ALERT -->
<?php if (isset($_GET['pesan'])):
    $alertClass = 'success'; $alertIcon = 'check-circle-fill'; $alertMsg = '';
    if     ($_GET['pesan'] === 'tambah_sukses')   { $alertMsg = 'Anggota berhasil ditambahkan!'; }
    elseif ($_GET['pesan'] === 'hapus_sukses')    { $alertMsg = 'Data anggota berhasil dihapus.'; }
    elseif ($_GET['pesan'] === 'edit_sukses')     { $alertMsg = 'Data anggota berhasil diperbarui!'; }
    elseif ($_GET['pesan'] === 'hapus_gagal')     { $alertClass = 'warning'; $alertIcon = 'exclamation-triangle-fill'; $alertMsg = 'Tidak bisa hapus! Anggota masih memiliki buku yang dipinjam.'; }
    elseif ($_GET['pesan'] === 'username_duplikat') { $alertClass = 'danger'; $alertIcon = 'x-circle-fill'; $alertMsg = 'Username sudah digunakan! Coba username lain.'; }
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
            <div class="nav-item"><a href="buku.php" class="nav-link"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link active"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
            <div class="nav-item"><a href="persetujuan_admin.php" class="nav-link"><i class="bi bi-check2-square"></i><span>Persetujuan</span></a></div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-title">Lainnya</div>
            <div class="nav-item">
                <a href="profile_admin.php" class="nav-link">
                    <i class="bi bi-person-circle"></i><span>Profile Admin</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="tambah_kategori.php" class="nav-link">
                    <i class="bi bi-tags-fill"></i>
                    <span>Kategori</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-left"></i><span>Keluar</span>
                </a>
            </div>
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

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-people-fill" style="font-size:1.75rem;"></i> Data Anggota
            </h1>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg"></i> Tambah Anggota
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div><div class="stat-label">Total Pengguna</div><div class="stat-value"><?= $total_semua ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-person-check"></i></div>
                <div><div class="stat-label">Anggota</div><div class="stat-value"><?= $total_anggota ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="bi bi-shield-check"></i></div>
                <div><div class="stat-label">Admin</div><div class="stat-value"><?= $total_admin ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-book-half"></i></div>
                <div><div class="stat-label">Sedang Meminjam</div><div class="stat-value"><?= $total_aktif ?></div></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <a href="anggota.php" class="filter-btn <?= $level_filter == '' ? 'active' : '' ?>"><i class="bi bi-grid"></i> Semua</a>
            <a href="anggota.php?level=anggota" class="filter-btn <?= $level_filter == 'anggota' ? 'active' : '' ?>"><i class="bi bi-person"></i> Anggota</a>
            <a href="anggota.php?level=admin" class="filter-btn purple <?= $level_filter == 'admin' ? 'active purple' : '' ?>"><i class="bi bi-shield"></i> Admin</a>
            <div class="search-filter">
                <form method="GET" style="display:flex;gap:0.5rem;">
                    <?php if ($level_filter): ?><input type="hidden" name="level" value="<?= htmlspecialchars($level_filter) ?>"><?php endif; ?>
                    <input type="text" name="search" placeholder="Cari nama / username..." value="<?= htmlspecialchars($search) ?>">
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
                            <th>Anggota</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>No. HP</th>
                            <th>Alamat</th>
                            <th>Level</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                            $inisial = strtoupper(mb_substr($row['nama'], 0, 1));
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <div class="avatar-circle"><?= htmlspecialchars($inisial) ?></div>
                                    <div>
                                        <div style="font-weight:600;"><?= htmlspecialchars($row['nama']) ?></div>
                                        <div style="font-size:0.75rem;color:#94a3b8;">ID User : <?= $no - 1 ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['no_hp'] ?? '-') ?></td>
                            <td>
                                <span title="<?= htmlspecialchars($row['alamat'] ?? '') ?>" style="max-width:150px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($row['alamat'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-level <?= $row['level'] === 'admin' ? 'badge-admin' : 'badge-anggota' ?>">
                                    <?= ucfirst(htmlspecialchars($row['level'])) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                    <button class="btn-action btn-edit"
                                        onclick="bukaModalEdit(<?= $row['id_user'] ?>, '<?= addslashes($row['nama']) ?>', '<?= addslashes($row['username']) ?>', '<?= addslashes($row['email'] ?? '') ?>', '<?= addslashes($row['alamat'] ?? '') ?>', '<?= addslashes($row['no_hp'] ?? '') ?>')"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <a href="anggota.php?hapus=<?= $row['id_user'] ?>" class="btn-action btn-hapus"
                                       onclick="return confirm('Yakin hapus anggota <?= addslashes($row['nama']) ?>?')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8">
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h5>Tidak Ada Data Anggota</h5>
                                <p style="font-size:0.85rem;">Belum ada data yang sesuai dengan filter atau pencarian.</p>
                            </div>
                        </td></tr>
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
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Tambah Anggota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-person me-1"></i>Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Masukkan nama lengkap" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-at me-1"></i>Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Username unik" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
                            <input type="text" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-envelope me-1"></i>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@contoh.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-phone me-1"></i>No. HP</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Alamat</label>
                            <input type="text" name="alamat" class="form-control" placeholder="Alamat lengkap">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Anggota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_user" id="edit_id_user">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-person me-1"></i>Nama Lengkap</label>
                            <input type="text" name="nama" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-at me-1"></i>Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-lock me-1"></i>Password Baru</label>
                            <input type="text" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-envelope me-1"></i>Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-phone me-1"></i>No. HP</label>
                            <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Alamat</label>
                            <input type="text" name="alamat" id="edit_alamat" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-save me-1"></i> Update</button>
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
        if (window.innerWidth <= 991 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });

    function bukaModalEdit(id, nama, username, email, alamat, no_hp) {
        document.getElementById('edit_id_user').value  = id;
        document.getElementById('edit_nama').value     = nama;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_email').value    = email;
        document.getElementById('edit_alamat').value   = alamat;
        document.getElementById('edit_no_hp').value    = no_hp;
    }

    setTimeout(() => {
        const al = document.querySelector('.alert-floating');
        if (al) { al.style.transition = 'opacity 0.5s'; al.style.opacity = '0'; setTimeout(() => al.remove(), 500); }
    }, 4000);
</script>
</body>
</html>
<?php $conn->close(); ?>