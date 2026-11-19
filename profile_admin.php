<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
require_once 'helper_katalog.php';
$menunggu_acc = getJumlahMenunggu($conn);

$id_user  = $_SESSION['id_user'] ?? null;
$nama     = $_SESSION['nama']     ?? '';
$username = $_SESSION['username'] ?? '';
$pesan    = '';
$error    = '';

// Cek kolom foto_profil, tambah jika belum ada (kompatibel MySQL lama)
$cekKolom = $conn->query("SHOW COLUMNS FROM user LIKE 'foto_profil'");
if ($cekKolom && $cekKolom->num_rows === 0) {
    $conn->query("ALTER TABLE user ADD COLUMN foto_profil VARCHAR(255) DEFAULT NULL");
}

$adminData = null;
if ($id_user) {
    $res = $conn->query("SELECT * FROM user WHERE id_user = $id_user LIMIT 1");
    if ($res && $res->num_rows > 0) $adminData = $res->fetch_assoc();
}
$foto_profil = $adminData['foto_profil'] ?? '';
$email       = $adminData['email']       ?? 'naufal.chand@gmail.com';
$no_hp       = $adminData['no_hp']       ?? '+62 813-1877-5548';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ubah_username') {
    $username_baru = $conn->real_escape_string(trim($_POST['username_baru'] ?? ''));
    $password_cek  = $conn->real_escape_string(trim($_POST['password_cek']  ?? ''));
    if (strlen($username_baru) < 3) {
        $error = 'Username minimal 3 karakter.';
    } else {
        $cekPass = $conn->query("SELECT password FROM user WHERE id_user = $id_user");
        $passRow = $cekPass ? $cekPass->fetch_assoc() : null;
        if (!$passRow || $passRow['password'] !== $password_cek) {
            $error = 'Password salah.';
        } else {
            $cekDup = $conn->query("SELECT id_user FROM user WHERE username='$username_baru' AND id_user != $id_user");
            if ($cekDup && $cekDup->num_rows > 0) {
                $error = 'Username sudah digunakan akun lain.';
            } else {
                $conn->query("UPDATE user SET username='$username_baru' WHERE id_user=$id_user");
                $_SESSION['username'] = $username_baru;
                $username = $username_baru;
                header("Location: profile_admin.php?pesan=username_sukses");
                exit();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'upload_foto') {
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === 0) {
        $uploadDir = 'foto_profil/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $error = 'Format tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        } elseif ($_FILES['foto']['size'] > 3*1024*1024) {
            $error = 'Ukuran file terlalu besar. Maksimal 3 MB.';
        } else {
            if (!empty($foto_profil) && file_exists($uploadDir.$foto_profil)) unlink($uploadDir.$foto_profil);
            $namaFile = 'admin_'.$id_user.'_'.time().'.'.$ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir.$namaFile);
            $conn->query("UPDATE user SET foto_profil='".$conn->real_escape_string($namaFile)."' WHERE id_user=$id_user");
            header("Location: profile_admin.php?pesan=foto_sukses");
            exit();
        }
    } else {
        $error = 'Pilih file foto terlebih dahulu.';
    }
}

if (isset($_GET['hapus_foto']) && $id_user) {
    $uploadDir = 'foto_profil/';
    if (!empty($foto_profil) && file_exists($uploadDir.$foto_profil)) unlink($uploadDir.$foto_profil);
    $conn->query("UPDATE user SET foto_profil='' WHERE id_user=$id_user");
    header("Location: profile_admin.php?pesan=foto_hapus"); exit();
}

if (empty($pesan) && isset($_GET['pesan'])) $pesan = $_GET['pesan'];

function time_ago($datetime) {
    if (empty($datetime)) return 'Baru saja';
    $ts = strtotime($datetime);
    if (!$ts) return 'Baru saja';
    $diff = time() - $ts;
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return floor($diff/60).' menit yang lalu';
    if ($diff < 86400)  return floor($diff/3600).' jam yang lalu';
    if ($diff < 604800) return floor($diff/86400).' hari yang lalu';
    return date('d M Y', $ts);
}

$activities = [];

$rb = $conn->query("SELECT judul_buku, id_buku FROM buku ORDER BY id_buku DESC LIMIT 5");
if ($rb) while ($r = $rb->fetch_assoc())
    $activities[] = ['text'=>'Menambahkan buku baru: '.$r['judul_buku'], 'time'=>date('Y-m-d H:i:s'), 'icon'=>'bi-book', 'color'=>'blue'];

$ra = $conn->query("SELECT nama, id_user FROM user WHERE level='anggota' ORDER BY id_user DESC LIMIT 5");
if ($ra) while ($r = $ra->fetch_assoc())
    $activities[] = ['text'=>'Mendaftar anggota baru: '.$r['nama'], 'time'=>date('Y-m-d H:i:s'), 'icon'=>'bi-person-plus', 'color'=>'green'];

$rp = $conn->query("SELECT p.id_peminjaman, u.nama, b.judul_buku, p.tanggal_peminjaman FROM peminjaman p JOIN user u ON p.id_user=u.id_user JOIN buku b ON p.id_buku=b.id_buku ORDER BY p.tanggal_peminjaman DESC LIMIT 5");
if ($rp) while ($r = $rp->fetch_assoc())
    $activities[] = ['text'=>'Peminjaman: '.$r['judul_buku'].' oleh '.$r['nama'], 'time'=>$r['tanggal_peminjaman'], 'icon'=>'bi-clock-history', 'color'=>'orange'];

$rk = $conn->query("SELECT pg.id_pengembalian, u.nama, b.judul_buku, pg.tanggal_kembali FROM pengembalian pg JOIN user u ON pg.id_user=u.id_user JOIN buku b ON pg.id_buku=b.id_buku ORDER BY pg.tanggal_kembali DESC LIMIT 5");
if ($rk) while ($r = $rk->fetch_assoc())
    $activities[] = ['text'=>'Pengembalian: '.$r['judul_buku'].' oleh '.$r['nama'], 'time'=>$r['tanggal_kembali'], 'icon'=>'bi-arrow-return-left', 'color'=>'purple'];

usort($activities, fn($a,$b) => strtotime($b['time']) - strtotime($a['time']));
$activities = array_slice($activities, 0, 10);

$inisial  = strtoupper(mb_substr($nama, 0, 1));
$fotoPath = (!empty($foto_profil) && file_exists('foto_profil/'.$foto_profil)) ? 'foto_profil/'.$foto_profil : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
    *,
    *::before,
    *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f8fafc;
        color: #0f172a;
    }

    .main-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
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

    .brand img {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        object-fit: cover;
    }

    .search-box {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .search-input {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 8px;
        padding: .45rem 1rem;
        color: #f8fafc;
        width: 260px;
        font-size: .85rem;
        transition: border-color .15s;
    }

    .search-input::placeholder {
        color: #94a3b8;
    }

    .search-input:focus {
        background: #1e293b;
        border-color: #2563eb;
        outline: none;
        width: 280px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid #334155;
        cursor: pointer;
        transition: opacity .2s;
        object-fit: cover;
    }

    .user-avatar:hover {
        opacity: .85;
    }

    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: #f8fafc;
        font-size: 1.5rem;
        cursor: pointer;
        margin-right: 1rem;
    }

    .layout-wrapper {
        display: flex;
        padding-top: 60px;
        min-height: 100vh;
    }

    .sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        bottom: 0;
        width: 260px;
        background: #0f172a;
        border-right: 1px solid #1e293b;
        overflow-y: auto;
        z-index: 900;
        display: flex;
        flex-direction: column;
    }

    .sidebar-section {
        padding: 1.5rem 0;
    }

    .sidebar-title {
        font-size: .72rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 0 1.5rem;
        margin-bottom: .75rem;
    }

    .nav-item {
        margin: .25rem .875rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .7rem 1rem;
        color: #94a3b8;
        text-decoration: none;
        border-radius: 8px;
        transition: background .15s, color .15s;
        font-size: .9rem;
        font-weight: 500;
    }

    .nav-link:hover {
        background: #1e293b;
        color: #f8fafc;
    }

    .nav-link.active {
        background: #2563eb;
        color: #ffffff;
    }

    .nav-link i {
        font-size: 1.1rem;
        width: 20px;
    }

    .nav-logout {
        color: #ef4444 !important;
    }

    .nav-logout:hover {
        background: #1e293b !important;
        color: #f87171 !important;
    }

    .sidebar-user {
        margin-top: auto;
        padding: 1.25rem;
        border-top: 1px solid #1e293b;
        display: flex;
        align-items: center;
        gap: .75rem;
        background: #090d16;
    }

    .sb-foto {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #334155;
        flex-shrink: 0;
    }

    .sb-ini {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f8fafc;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
        border: 2px solid #334155;
    }

    .sidebar-user-name {
        font-weight: 600;
        color: #f8fafc;
        font-size: .9rem;
    }

    .sidebar-user-role {
        font-size: .75rem;
        color: #94a3b8;
        text-transform: capitalize;
    }

    .main-content {
        flex: 1;
        margin-left: 260px;
        padding: 2rem;
        background: #f8fafc;
    }

    .page-header {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .alert-floating {
        position: fixed;
        top: 72px;
        right: 1.5rem;
        z-index: 9999;
        min-width: 300px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        animation: slideIn .3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(120%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Profile card */
    .profile-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
        margin-bottom: 1.5rem;
    }

    .profile-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .avatar-wrap {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 1rem;
    }

    .avatar-foto {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e2e8f0;
        display: block;
    }

    .avatar-ini {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.75rem;
        color: #f8fafc;
        font-weight: 700;
        border: 3px solid #e2e8f0;
    }

    .avatar-cam {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #1d4ed8;
        color: #ffffff;
        border: 2px solid #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .85rem;
        cursor: pointer;
        transition: background .15s;
    }

    .avatar-cam:hover {
        background: #1e40af;
    }

    /* Upload area */
    .foto-panel {
        max-width: 340px;
        margin: 1.25rem auto 0;
    }

    .upload-area {
        border: 1.5px dashed #cbd5e1;
        border-radius: 8px;
        padding: 1.25rem 1rem;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        position: relative;
        background: #f8fafc;
        text-align: center;
    }

    .upload-area:hover,
    .upload-area.drag-over {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .upload-area input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }

    .ua-icon {
        font-size: 1.4rem;
        color: #64748b;
        transition: color .15s;
    }

    .upload-area:hover .ua-icon {
        color: #2563eb;
    }

    .ua-text {
        font-size: .78rem;
        color: #334155;
        font-weight: 600;
        margin-top: .25rem;
    }

    .ua-hint {
        font-size: .7rem;
        color: #94a3b8;
    }

    .preview-wrap {
        display: none;
        position: relative;
        margin-bottom: .65rem;
    }

    .preview-wrap img {
        width: 100%;
        border-radius: 8px;
        object-fit: cover;
        max-height: 120px;
    }

    .pv-remove {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #0f172a;
        color: white;
        border: none;
        cursor: pointer;
        font-size: .7rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pv-remove:hover {
        background: #dc2626;
    }

    .btn-save-foto {
        width: 100%;
        margin-top: .6rem;
        padding: .5rem;
        background: #1d4ed8;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        transition: background .15s;
    }

    .btn-save-foto:hover {
        background: #1e40af;
    }

    .btn-hapus-foto {
        width: 100%;
        margin-top: .45rem;
        padding: .45rem;
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        border-radius: 8px;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        transition: background .15s, border-color .15s;
        text-decoration: none;
    }

    .btn-hapus-foto:hover {
        background: #dc2626;
        color: white;
        border-color: #dc2626;
    }

    /* Info */
    .profile-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
    }

    .info-item {
        padding: 1.1rem 1.25rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #1d4ed8;
    }

    .info-label {
        font-size: .8rem;
        color: #64748b;
        margin-bottom: .4rem;
        display: flex;
        align-items: center;
        gap: .4rem;
        font-weight: 500;
    }

    .info-value {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }

    /* Edit username */
    .edit-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.75rem 2rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
        margin-bottom: 1.5rem;
    }

    .edit-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: 1.25rem;
        padding-bottom: .75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .form-label {
        font-size: .8rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: .3rem;
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    .form-label i {
        color: #64748b;
    }

    .form-control {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: .875rem;
        padding: .5rem .85rem;
        color: #0f172a;
        background: #f8fafc;
        transition: border-color .15s, background .15s;
    }

    .form-control:focus {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: none;
        outline: none;
    }

    .btn-save {
        padding: .55rem 1.4rem;
        background: #1d4ed8;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        transition: background .15s;
    }

    .btn-save:hover {
        background: #1e40af;
    }

    /* Activity */
    .activity-section {
        background: #ffffff;
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
    }

    .activity-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    .activity-list {
        list-style: none;
    }

    .activity-item {
        padding: .85rem .75rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: .85rem;
        border-radius: 8px;
        transition: background .15s;
    }

    .activity-item:hover {
        background: #f8fafc;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .act-blue {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .act-green {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .act-orange {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    .act-purple {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .activity-text {
        font-weight: 600;
        color: #0f172a;
        font-size: .88rem;
        margin-bottom: .2rem;
    }

    .activity-time {
        font-size: .78rem;
        color: #64748b;
    }

    @media(max-width:991px) {
        .mobile-toggle {
            display: block;
        }

        .sidebar {
            transform: translateX(-100%);
            transition: transform .3s;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }
    }

    @media(max-width:576px) {
        .main-content {
            padding: 1.25rem;
        }
    }
</style>
    <link rel="stylesheet" href="katalog_styles.css">
</head>
<body>
<header class="main-header">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="beranda.php" class="brand"><img src="logo 40.jpeg" alt="Logo"><span><?= NAMA_PERPUS ?></span></a>
    <div class="search-box">
        <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="profile_admin.php"><i class="bi bi-person me-2"></i>Profil</a></li>
            <li><a class="dropdown-item" href="index.php"><i class="bi bi-globe me-2"></i>Katalog Publik</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
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
            <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check2-square"></i><span>Persetujuan</span>
                </div>
                <?php if (!empty($menunggu_acc) && $menunggu_acc > 0): ?>
                    <span class="badge-notif-sidebar"><?= $menunggu_acc ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Lainnya</div>
        <div class="nav-item"><a href="profile_admin.php" class="nav-link active"><i class="bi bi-person-circle"></i><span>Profile Admin</span></a></div>
        <div class="nav-item"><a href="tambah_kategori.php" class="nav-link"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></div>
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-globe"></i><span>Katalog Publik</span></a></div>
        <div class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
    </div>
    <div class="sidebar-user">
        <?php if ($fotoPath): ?>
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Foto" class="sb-foto">
        <?php else: ?>
            <div class="sb-ini"><?= $inisial ?></div>
        <?php endif; ?>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($_SESSION['level']) ?></div>
        </div>
    </div>
</aside>
<main class="main-content">

<?php
$alertMap = [
    'foto_sukses'    => ['success','<i class="bi bi-check-circle-fill me-2"></i>Foto profil berhasil diperbarui!'],
    'foto_hapus'     => ['info',   '<i class="bi bi-info-circle-fill me-2"></i>Foto profil berhasil dihapus.'],
    'username_sukses'=> ['success','<i class="bi bi-check-circle-fill me-2"></i>Username berhasil diubah!'],
];
if ($pesan && isset($alertMap[$pesan])): [$aType,$aMsg] = $alertMap[$pesan]; ?>
<div class="alert alert-<?= $aType ?> alert-floating alert-dismissible fade show">
    <?= $aMsg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif;
if ($error): ?>
<div class="alert alert-danger alert-floating alert-dismissible fade show">
    <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-person-circle" style="font-size:1.75rem;"></i> Profile Admin</h1>
</div>

<!-- PROFILE CARD -->
<div class="profile-card">
    <div class="profile-header">
        <div class="avatar-wrap">
            <?php if ($fotoPath): ?>
                <img src="<?= htmlspecialchars($fotoPath) ?>?v=<?= time() ?>" alt="Foto" class="avatar-foto">
            <?php else: ?>
                <div class="avatar-ini"><?= $inisial ?></div>
            <?php endif; ?>
            <label for="fotoInputCam" class="avatar-cam" title="Ganti foto">
                <i class="bi bi-camera-fill"></i>
            </label>
        </div>
        <h2 class="profile-name"><?= htmlspecialchars($nama) ?></h2>
        <span class="profile-role">Administrator</span>

        <div class="foto-panel">
            <form method="POST" enctype="multipart/form-data" id="formFoto">
                <input type="hidden" name="aksi" value="upload_foto">
                <input type="file" name="foto" id="fotoInputCam" accept=".jpg,.jpeg,.png,.webp" onchange="previewFoto(this)" style="display:none;">
                <div class="preview-wrap" id="previewWrap">
                    <img src="" alt="Preview" id="previewImg">
                    <button type="button" class="pv-remove" onclick="hapusPreview()"><i class="bi bi-x"></i></button>
                </div>
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="foto" id="fotoInputDrop" accept=".jpg,.jpeg,.png,.webp" onchange="previewFoto(this)">
                    <i class="bi bi-cloud-arrow-up ua-icon"></i>
                    <div class="ua-text">Klik atau drag &amp; drop foto baru</div>
                    <div class="ua-hint">JPG, PNG, WEBP &middot; Maks. 3 MB</div>
                </div>
                <button type="submit" class="btn-save-foto" id="btnSaveFoto">
                    <i class="bi bi-upload"></i> Simpan Foto
                </button>
            </form>
            <?php if ($fotoPath): ?>
            <a href="profile_admin.php?hapus_foto=1" class="btn-hapus-foto" onclick="return confirm('Hapus foto profil?')">
                <i class="bi bi-trash"></i> Hapus Foto
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-info">
        <div class="info-item">
            <div class="info-label"><i class="bi bi-person"></i> Username</div>
            <div class="info-value"><?= htmlspecialchars($username) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="bi bi-shield-check"></i> Level Akses</div>
            <div class="info-value">Administrator</div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="bi bi-envelope"></i> Email</div>
            <div class="info-value"><?= htmlspecialchars($email) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="bi bi-telephone"></i> Telepon</div>
            <div class="info-value"><?= htmlspecialchars($no_hp) ?></div>
        </div>
    </div>
</div>

<!-- UBAH USERNAME -->
<div class="edit-card">
    <h3 class="edit-card-title"><i class="bi bi-pencil-square" style="color:#2563eb;"></i> Ubah Username</h3>
    <form method="POST">
        <input type="hidden" name="aksi" value="ubah_username">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label"><i class="bi bi-at"></i> Username Baru</label>
                <input type="text" name="username_baru" class="form-control"
                       placeholder="Masukkan username baru"
                       value="<?= htmlspecialchars($username) ?>"
                       minlength="3" required>
            </div>
            <div class="col-md-5">
                <label class="form-label"><i class="bi bi-lock-fill"></i> Konfirmasi Password</label>
                <div style="position:relative;">
                    <input type="password" name="password_cek" id="passInput" class="form-control"
                           placeholder="Masukkan password kamu" style="padding-right:2.75rem;" required>
                    <button type="button" onclick="togglePass()"
                            style="position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn-save w-100"><i class="bi bi-floppy-fill"></i> Simpan</button>
            </div>
        </div>
    </form>
</div>

<!-- AKTIVITAS OTOMATIS -->
<div class="activity-section">
    <h3 class="activity-title"><i class="bi bi-clock-history"></i> Aktivitas Terakhir</h3>
    <ul class="activity-list">
        <?php if (empty($activities)): ?>
            <li class="activity-item"><div><div class="activity-text" style="color:#94a3b8;">Belum ada aktivitas tercatat.</div></div></li>
        <?php else:
            $colorMap = ['blue'=>'act-blue','green'=>'act-green','orange'=>'act-orange','purple'=>'act-purple'];
            foreach ($activities as $act):
                $cls = $colorMap[$act['color']] ?? 'act-blue'; ?>
            <li class="activity-item">
                <div class="activity-icon <?= $cls ?>"><i class="bi <?= htmlspecialchars($act['icon']) ?>"></i></div>
                <div>
                    <div class="activity-text"><?= htmlspecialchars($act['text']) ?></div>
                    <div class="activity-time"><?= time_ago($act['time']) ?></div>
                </div>
            </li>
        <?php endforeach; endif; ?>
    </ul>
</div>

</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewFoto(input) {
    if (!input.files || !input.files[0]) return;
    if (input.files[0].size > 3*1024*1024) { alert('Ukuran file maksimal 3 MB.'); input.value=''; return; }
    if (input.id === 'fotoInputCam') {
        try { const dt=new DataTransfer(); dt.items.add(input.files[0]); document.getElementById('fotoInputDrop').files=dt.files; } catch(e){}
    }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewWrap').style.display = 'block';
        document.getElementById('uploadArea').style.display  = 'none';
        document.getElementById('btnSaveFoto').style.display = 'flex';
    };
    reader.readAsDataURL(input.files[0]);
}
function hapusPreview() {
    document.getElementById('fotoInputDrop').value='';
    document.getElementById('fotoInputCam').value='';
    document.getElementById('previewImg').src='';
    document.getElementById('previewWrap').style.display='none';
    document.getElementById('uploadArea').style.display='flex';
    document.getElementById('btnSaveFoto').style.display='none';
}
const ua=document.getElementById('uploadArea');
if(ua) {
    ua.addEventListener('dragover',e=>{e.preventDefault();
    ua.classList.add('drag-over')});
    ua.addEventListener('dragleave',()=>ua.classList.remove('drag-over'));
    ua.addEventListener('drop',()=>ua.classList.remove('drag-over'));
}

function togglePass(){const inp=document.getElementById('passInput');const ico=document.getElementById('eyeIcon');inp.type=inp.type==='password'?'text':'password';ico.className=inp.type==='text'?'bi bi-eye-slash':'bi bi-eye';}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');}
document.addEventListener('click',function(e){const sb=document.getElementById('sidebar');const tg=document.querySelector('.mobile-toggle');if(window.innerWidth<=991&&tg&&!sb.contains(e.target)&&!tg.contains(e.target))sb.classList.remove('show');});
setTimeout(()=>{document.querySelectorAll('.alert-floating').forEach(a=>{a.style.transition='opacity .4s';a.style.opacity='0';setTimeout(()=>a.remove(),400);});},4500);
</script>
</body>
</html>
<?php $conn->close(); ?>