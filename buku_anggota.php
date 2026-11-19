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

// ==================== PROSES PENGAJUAN (perlu persetujuan admin) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'pinjam') {
    $id_buku     = intval($_POST['id_buku']);
    $tgl_kembali = $conn->real_escape_string($_POST['tanggal_pengembalian']);
    $tgl_pinjam  = date('Y-m-d');

    // Hitung durasi hari
    $durasi_hari = max(1, (int)((strtotime($tgl_kembali) - strtotime($tgl_pinjam)) / 86400));

    // Cek stok
    $cekStok = $conn->query("SELECT stok FROM buku WHERE id_buku=$id_buku");
    if (!$cekStok || $cekStok->num_rows === 0 || intval($cekStok->fetch_assoc()['stok']) <= 0) {
        header("Location: buku_anggota.php?pesan=stok_habis");
        $conn->close(); exit();
    }

    // Cek sudah punya pengajuan menunggu untuk buku yang sama
    $cekAjuan = $conn->query("SELECT id_permintaan FROM persetujuan WHERE id_user=$id_user AND id_buku=$id_buku AND status='menunggu' LIMIT 1");
    if ($cekAjuan && $cekAjuan->num_rows > 0) {
        header("Location: buku_anggota.php?pesan=sudah_diajukan");
        $conn->close(); exit();
    }

    // Cek sudah dipinjam
    $cekPinjam = $conn->query("SELECT id_peminjaman FROM peminjaman WHERE id_buku=$id_buku AND id_user=$id_user AND status_peminjaman='dipinjam'");
    if ($cekPinjam && $cekPinjam->num_rows > 0) {
        header("Location: buku_anggota.php?pesan=sudah_dipinjam");
        $conn->close(); exit();
    }

    // Simpan ke tabel persetujuan (menunggu acc admin)
    $conn->query("INSERT INTO persetujuan (id_user, id_buku, tanggal_pengajuan, durasi_hari, alasan, status)
                  VALUES ($id_user, $id_buku, '$tgl_pinjam', $durasi_hari, '', 'menunggu')");

    header("Location: buku_anggota.php?pesan=ajuan_terkirim");
    $conn->close(); exit();
}

// ==================== FILTER & SEARCH ====================
$kategori_filter = isset($_GET['kategori']) ? $conn->real_escape_string(trim($_GET['kategori'])) : '';
$search          = isset($_GET['search'])   ? $conn->real_escape_string(trim($_GET['search']))   : '';

$where = "WHERE 1=1";
if ($kategori_filter !== '') $where .= " AND LOWER(k.nama_kategori) = LOWER('$kategori_filter')";
if ($search !== '')           $where .= " AND (b.judul_buku LIKE '%$search%' OR b.penulis LIKE '%$search%')";

// Deteksi kolom stok
$kolomStok = null;
$cols = [];
$rc = $conn->query("SHOW COLUMNS FROM `buku`");
while ($c = $rc->fetch_assoc()) $cols[] = $c['Field'];
foreach (['stok_tersedia','stok','jumlah','stok_buku','qty'] as $ks) {
    if (in_array($ks, $cols)) { $kolomStok = $ks; break; }
}
$stokCol = $kolomStok ? "b.`$kolomStok` AS stok" : "1 AS stok";

$result = $conn->query("
    SELECT b.*, $stokCol, k.nama_kategori
    FROM buku b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    $where
    ORDER BY b.id_buku DESC
");

$kategoriList = $conn->query("SELECT DISTINCT nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$totalBuku    = $conn->query("SELECT COUNT(*) AS c FROM buku")->fetch_assoc()['c'];

// Buku yang sedang dipinjam user ini
$dipinjamIds = [];
$dp = $conn->query("SELECT id_buku FROM peminjaman WHERE id_user=$id_user AND status_peminjaman='dipinjam'");
if ($dp) while ($d = $dp->fetch_assoc()) $dipinjamIds[] = $d['id_buku'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Buku | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
    <style>

/*1. RESET & BASE */
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
    color: #0f172a;
}

/* 2. HEADER - Solid Slate */
.main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
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

.brand img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid #334155;
}

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
    background: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.95rem;
    text-decoration: none;
    border: 2px solid #334155;
    cursor: pointer;
    transition: border-color 0.2s;
}

.user-avatar:hover {
    border-color: #60a5fa;
}

.mobile-toggle {
    display: none;
    background: none;
    border: none;
    color: #f8fafc;
    font-size: 1.4rem;
    cursor: pointer;
    margin-right: 1rem;
}

.layout-wrapper {
    display: flex;
    padding-top: 60px;
    min-height: 100vh;
}

/* 3. SIDEBAR - Solid Slate */
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
    padding: 1.25rem 0;
}

.sidebar-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 0 1.5rem;
    margin-bottom: 0.65rem;
}

.nav-item {
    margin: 0.2rem 0.85rem;
}

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

.nav-link:hover {
    background: #1e293b;
    color: white;
}

.nav-link.active {
    background: #2563eb;
    color: white;
    font-weight: 600;
    box-shadow: none;
}

.nav-link i {
    font-size: 1.1rem;
    width: 20px;
}

.sidebar-user {
    margin-top: auto;
    padding: 1.1rem 1.25rem;
    border-top: 1px solid #1e293b;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #0b1120;
}

.sidebar-user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.95rem;
    border: 2px solid #334155;
    flex-shrink: 0;
}

.sidebar-user-name {
    font-weight: 600;
    color: #f8fafc;
    font-size: 0.88rem;
}

.sidebar-user-role {
    font-size: 0.75rem;
    color: #64748b;
}

.main-content {
    flex: 1;
    margin-left: 260px;
    padding: 2rem 2.25rem;
}

/* 4. PAGE HEADER */
.page-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
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

.page-title i {
    color: #1d4ed8;
}

.total-badge {
    font-size: 0.78rem;
    font-weight: 600;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
    border-radius: 20px;
    padding: 0.2rem 0.65rem;
}

/* 5. FILTER BAR */
.filter-bar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.9rem 1.35rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
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
    color: white;
    font-weight: 600;
}

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
    width: 210px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.search-wrap input:focus {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
}

.search-wrap button {
    background: #1d4ed8;
    color: white;
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

/* 6. BOOK GRID & CARDS */
.books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
}

.book-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    display: flex;
    flex-direction: column;
}

.book-card:hover {
    transform: translateY(-3px);
    border-color: #cbd5e1;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
}

.book-cover {
    position: relative;
    height: 200px;
    overflow: hidden;
    flex-shrink: 0;
    background-color: #f1f5f9;
}

.book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.cover-gen {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.2rem;
    text-align: center;
    gap: 0.4rem;
    position: relative;
}

.cover-gen::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 12px;
    background: rgba(0,0,0,0.2);
    border-right: 1px solid rgba(255,255,255,0.15);
}

.cover-gen .cg-title {
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.35;
    color: #ffffff;
    word-break: break-word;
}

.cover-gen .cg-author {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.8);
}

.stok-pill {
    position: absolute;
    top: 9px;
    right: 9px;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.stok-pill.ok {
    background: #15803d;
}

.stok-pill.low {
    background: #b45309;
}

.stok-pill.zero {
    background: #b91c1c;
}

.book-body {
    padding: 0.9rem 1rem 0.85rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.bc-kat {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 600;
    margin-bottom: 0.45rem;
    width: fit-content;
    border: 1px solid transparent;
}

.bc-title {
    font-size: 0.92rem;
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
    font-size: 0.75rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-bottom: 0.8rem;
}

.bc-actions {
    margin-top: auto;
    padding-top: 0.65rem;
    border-top: 1px solid #f1f5f9;
}

/* 7. BUTTONS */
.btn-pinjam {
    width: 100%;
    padding: 0.5rem 0.6rem;
    border: 1px solid #1d4ed8;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    transition: background-color 0.15s ease;
    background: #1d4ed8;
    color: white;
}

.btn-pinjam:hover {
    background: #1e40af;
    border-color: #1e40af;
}

.btn-pinjam:disabled,
.btn-pinjam.sudah {
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
    transform: none;
}

.btn-pinjam.habis {
    background: #fef2f2;
    border-color: #fee2e2;
    color: #b91c1c;
    cursor: not-allowed;
    transform: none;
}

/* 8. EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 4rem 1rem;
    color: #94a3b8;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 2.8rem;
    display: block;
    margin-bottom: 0.75rem;
    color: #cbd5e1;
}

.empty-state h5 {
    font-size: 1.05rem;
    color: #475569;
    margin-bottom: 0.35rem;
}

/* 9. ALERT FLOATING */
.alert-floating {
    position: fixed;
    top: 72px;
    right: 1.5rem;
    z-index: 9999;
    min-width: 280px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    animation: slideIn 0.25s ease;
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

/* 10. MODAL PINJAM */
.modal-pinjam .modal-content {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.modal-pinjam .modal-header {
    background: #0f172a;
    border-bottom: 1px solid #1e293b;
    padding: 1.1rem 1.5rem;
}

.modal-pinjam .modal-title {
    color: #f8fafc;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-pinjam .btn-close {
    filter: invert(1);
    opacity: 0.8;
}

.modal-pinjam .modal-body {
    padding: 1.5rem;
    background: #f8fafc;
}

.modal-pinjam .modal-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 0.9rem 1.5rem;
}

/* Book Preview di Modal */
.modal-book-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.25rem;
}

.modal-book-cover {
    width: 56px;
    height: 76px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}

.modal-book-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-book-cover .mini-cover {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 700;
    color: #ffffff;
    text-align: center;
    padding: 0.25rem;
    line-height: 1.2;
}

.modal-book-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.95rem;
    line-height: 1.3;
    margin-bottom: 0.2rem;
}

.modal-book-author {
    font-size: 0.78rem;
    color: #64748b;
}

.modal-stok {
    font-size: 0.75rem;
    color: #15803d;
    font-weight: 600;
    margin-top: 0.3rem;
}

.modal-stok.low {
    color: #b45309;
}

.modal-stok.zero {
    color: #b91c1c;
}

/* Form Tanggal di Modal */
.tgl-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.1rem;
}

.tgl-section label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.tgl-section label i {
    color: #1d4ed8;
}

.tgl-section input[type="date"] {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0.5rem 0.85rem;
    font-size: 0.875rem;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s;
}

.tgl-section input[type="date"]:focus {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
}

.tgl-section .tgl-info {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.tgl-section .tgl-info.warn {
    color: #b45309;
}

/* Tanggal Pinjam (Readonly) */
.tgl-pinjam-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.82rem;
    color: #64748b;
    margin-bottom: 1rem;
}

.tgl-pinjam-row i {
    color: #1d4ed8;
}

.tgl-pinjam-row strong {
    color: #0f172a;
}

/* Modal Buttons */
.btn-modal-save {
    background: #1d4ed8;
    color: white;
    border: 1px solid #1d4ed8;
    border-radius: 6px;
    padding: 0.55rem 1.3rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: background-color 0.15s ease;
}

.btn-modal-save:hover {
    background: #1e40af;
    border-color: #1e40af;
}

.btn-modal-cancel {
    background: transparent;
    color: #475569;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0.55rem 1.1rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-modal-cancel:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* 13. RESPONSIVE */
@media (max-width: 991px) {
    .mobile-toggle {
        display: block;
    }

    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.25s ease;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
        padding: 1.5rem 1rem;
    }

    .search-wrap {
        margin-left: 0;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 1.25rem 1rem;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
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
    <div class="header-right">
        <div class="dropdown">
            <a href="#" class="user-avatar" data-bs-toggle="dropdown">
                <?= strtoupper(substr($nama, 0, 1)) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                <li><a class="dropdown-item" href="login_anggota.php?hal=profil"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                <li><a class="dropdown-item" href="peminjaman_anggota.php"><i class="bi bi-clock-history me-2"></i>Peminjaman Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="layout-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu Utama</div>
        <div class="nav-item"><a href="login_anggota.php" class="nav-link"><i class="bi bi-house-door-fill"></i><span>Beranda</span></a></div>
        <div class="nav-item"><a href="buku_anggota.php" class="nav-link active"><i class="bi bi-book"></i><span>Katalog Buku</span></a></div>
        <div class="nav-item"><a href="peminjaman_anggota.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman Saya</span></a></div>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Lainnya</div>
        <div class="nav-item"><a href="login_anggota.php?hal=profil" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Saya</span></a></div>
        <div class="nav-item"><a href="logout.php" class="nav-link" style="color:rgba(248,113,113,.85)"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= strtoupper(substr($nama, 0, 1)) ?></div>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($nama) ?></div>
            <div class="sidebar-user-role">Anggota</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main-content">

    <!-- Alert pesan -->
    <?php
    $pesanMap = [
        'ajuan_terkirim'  => ['success','<i class="bi bi-check-circle-fill me-2"></i>Pengajuan berhasil dikirim! Tunggu persetujuan dari admin.'],
        'sudah_diajukan'  => ['warning','<i class="bi bi-exclamation-circle-fill me-2"></i>Kamu sudah punya pengajuan menunggu untuk buku ini.'],
        'stok_habis'      => ['warning','<i class="bi bi-exclamation-circle-fill me-2"></i>Stok buku habis, tidak bisa diajukan saat ini.'],
        'sudah_dipinjam'  => ['info','<i class="bi bi-info-circle-fill me-2"></i>Kamu sudah meminjam buku ini dan belum mengembalikannya.'],
    ];
    if (isset($_GET['pesan']) && isset($pesanMap[$_GET['pesan']])):
        [$type, $msg] = $pesanMap[$_GET['pesan']];
    ?>
    <div class="alert alert-<?= $type ?> alert-floating alert-dismissible fade show" role="alert">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-book-fill"></i>
            Katalog Buku
            <span class="total-badge"><?= $totalBuku ?> judul</span>
        </h1>
    </div>

    <!-- Filter bar -->
    <div class="category-pills-bar mb-3">
        <a href="buku_anggota.php<?= $search ? '?search='.urlencode($search) : '' ?>"
           class="cat-pill <?= $kategori_filter===''?'active':'' ?>">
            <i class="bi bi-grid-fill"></i> Semua
        </a>
        <?php
        if ($kategoriList) {
            mysqli_data_seek($kategoriList, 0);
            while ($kat = $kategoriList->fetch_assoc()):
                $kn   = $kat['nama_kategori'];
                $href = 'buku_anggota.php?kategori='.urlencode($kn).($search?'&search='.urlencode($search):'');
                $isActive = (strtolower($kategori_filter) === strtolower($kn));
        ?>
        <a href="<?= $href ?>" class="cat-pill <?= $isActive ? 'active' : '' ?>">
            <?php 
                $kl = strtolower($kn);
                if (strpos($kl, 'fiksi') !== false || strpos($kl, 'novel') !== false) echo '<i class="bi bi-journal-bookmark"></i>';
                elseif (strpos($kl, 'pendidikan') !== false || strpos($kl, 'pelajaran') !== false) echo '<i class="bi bi-mortarboard"></i>';
                elseif (strpos($kl, 'sains') !== false) echo '<i class="bi bi-cpu"></i>';
                elseif (strpos($kl, 'komik') !== false) echo '<i class="bi bi-brush"></i>';
                elseif (strpos($kl, 'biografi') !== false) echo '<i class="bi bi-person-lines-fill"></i>';
                else echo '<i class="bi bi-tag"></i>';
            ?>
            <?= htmlspecialchars($kn) ?>
        </a>
        <?php endwhile; } ?>
    </div>

    <div class="filter-bar mb-4">
        <form method="GET" class="search-wrap w-100">
            <?php if ($kategori_filter): ?><input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_filter) ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Cari judul / penulis / nomor rak..." value="<?= htmlspecialchars($search) ?>" class="w-100">
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <!-- Book grid -->
    <div class="books-grid">
    <?php
    if ($result && $result->num_rows > 0):
        while ($buku = $result->fetch_assoc()):
            $kNama    = $buku['nama_kategori'] ?? 'Umum';
            $stok     = intval($buku['stok'] ?? 0);
            $hasCover = !empty($buku['cover']) && file_exists('covers/'.$buku['cover']);
            $sudah    = in_array($buku['id_buku'], $dipinjamIds);
            $lokasi   = $buku['lokasi_rak'] ?? 'Rak A-1';

            // Data untuk modal (encode JSON aman)
            $modalData = json_encode([
                'id'     => $buku['id_buku'],
                'judul'  => $buku['judul_buku'],
                'penulis'=> $buku['penulis'] ?? '',
                'stok'   => $stok,
                'cover'  => $hasCover ? 'covers/'.htmlspecialchars($buku['cover']) : '',
                'lokasi' => $lokasi,
                'kategori'=> $kNama
            ], JSON_HEX_APOS|JSON_HEX_QUOT);
    ?>
        <div class="book-card">
            <div class="book-cover">
                <?= renderBookCover($buku['cover'], $buku['judul_buku'], $buku['penulis'], $kNama, $lokasi) ?>
            </div>

            <div class="book-body">
                <span class="bc-kat">
                    <?= htmlspecialchars($kNama) ?>
                </span>
                <div class="bc-title"><?= htmlspecialchars($buku['judul_buku']) ?></div>
                <div class="bc-author">
                    <i class="bi bi-person" style="font-size:.68rem;"></i>
                    <?= htmlspecialchars($buku['penulis'] ?? '-') ?>
                </div>

                <div class="d-flex align-items-center justify-content-between my-2">
                    <?= renderRakBadge($lokasi) ?>
                    <?= renderStockBadge($stok) ?>
                </div>

                <div class="bc-actions">
                    <?php if ($sudah): ?>
                        <button class="btn-pinjam sudah" disabled>
                            <i class="bi bi-check-circle-fill"></i> Sedang Dipinjam
                        </button>
                    <?php elseif ($stok <= 0): ?>
                        <button class="btn-pinjam habis" disabled>
                            <i class="bi bi-x-circle-fill"></i> Stok Habis
                        </button>
                    <?php else: ?>
                        <button class="btn-pinjam"
                                onclick='bukaPinjam(<?= $modalData ?>)'>
                            <i class="bi bi-book-fill"></i> Pinjam Buku
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endwhile; else: ?>
        <div class="empty-state">
            <i class="bi bi-book"></i>
            <h5>Tidak Ada Buku</h5>
            <p>Tidak ada buku<?= $search?' dengan kata kunci "<strong>'.htmlspecialchars($search).'</strong>"':($kategori_filter?' dalam kategori ini':'') ?>.</p>
        </div>
    <?php endif; ?>
    </div>

</main>
</div>

<!-- =============================================
     MODAL KONFIRMASI PINJAM BUKU
============================================= -->
<div class="modal fade modal-pinjam" id="modalPinjam" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-send-fill"></i> Ajukan Pinjam Buku
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="formPinjam">
                <input type="hidden" name="aksi" value="pinjam">
                <input type="hidden" name="id_buku" id="inputIdBuku">

                <div class="modal-body">

                    <!-- Info buku -->
                    <div class="modal-book-info">
                        <div class="modal-book-cover" id="modalCoverWrap">
                            <!-- diisi JS -->
                        </div>
                        <div style="flex:1;">
                            <div class="modal-book-title" id="modalJudul">â€”</div>
                            <div class="modal-book-author" id="modalPenulis">â€”</div>
                            <div class="modal-stok" id="modalStok">â€”</div>
                        </div>
                    </div>

                    <!-- Tanggal -->
                    <div class="tgl-section">
                        <!-- Tanggal peminjaman (readonly) -->
                        <div class="tgl-pinjam-row">
                            <i class="bi bi-calendar3"></i>
                            Tanggal Pinjam: <strong><?= date('d/m/Y') ?></strong>
                        </div>

                        <!-- Tanggal pengembalian (input user) -->
                        <label for="inputTglKembali">
                            <i class="bi bi-calendar-check"></i>
                            Tanggal Pengembalian <span style="color:#ef4444">*</span>
                        </label>
                        <input type="date"
                               name="tanggal_pengembalian"
                               id="inputTglKembali"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                               required
                               onchange="hitungSisa()">
                        <div class="tgl-info" id="infoSisaHari">
                            <i class="bi bi-info-circle"></i>
                            <span></span>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn-modal-save">
                        <i class="bi bi-send-fill"></i> Ajukan Pinjam
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// â”€â”€ Buka modal pinjam â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function bukaPinjam(data) {
    // Isi hidden field
    document.getElementById('inputIdBuku').value = data.id;

    // Judul & penulis
    document.getElementById('modalJudul').textContent  = data.judul;
    document.getElementById('modalPenulis').textContent = data.penulis || 'â€”';

    // Stok
    const stokEl  = document.getElementById('modalStok');
    const stok    = parseInt(data.stok);
    stokEl.textContent = 'Stok tersedia: ' + stok + ' eksemplar';
    stokEl.className   = 'modal-stok' + (stok <= 3 ? ' low' : '');

    // Cover
    const wrap = document.getElementById('modalCoverWrap');
    if (data.cover) {
        wrap.innerHTML = `<img src="${data.cover}" alt="${data.judul}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
        wrap.innerHTML = `<div class="mini-cover" style="background-color:${data.cSolid || '#1e293b'};">${data.judul}</div>`;
    }

    // Reset & hitung sisa hari
    hitungSisa();

    // Tampilkan modal
    new bootstrap.Modal(document.getElementById('modalPinjam')).show();
}

// â”€â”€ Hitung sisa hari dari tanggal kembali â”€â”€â”€â”€â”€â”€
function hitungSisa() {
    const tglInput = document.getElementById('inputTglKembali');
    const infoEl   = document.getElementById('infoSisaHari');
    const span     = infoEl.querySelector('span');
    const icon     = infoEl.querySelector('i') || document.createElement('i');

    if (!tglInput.value) { span.textContent = ''; return; }

    const today    = new Date(); today.setHours(0,0,0,0);
    const kembali  = new Date(tglInput.value);
    const diff     = Math.round((kembali - today) / 86400000);

    if (diff < 1) {
        span.textContent  = 'Tanggal pengembalian minimal besok.';
        infoEl.className  = 'tgl-info warn';
    } else if (diff > 30) {
        span.textContent  = 'Maksimal 30 hari dari sekarang.';
        infoEl.className  = 'tgl-info warn';
    } else {
        span.textContent  = `Durasi peminjaman: ${diff} hari`;
        infoEl.className  = 'tgl-info';
    }
}

// â”€â”€ Init hitung saat halaman load â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
document.addEventListener('DOMContentLoaded', hitungSisa);

// â”€â”€ Sidebar toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.querySelector('.mobile-toggle');
    if (window.innerWidth <= 991 && toggle &&
        !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// â”€â”€ Auto-close alert â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
setTimeout(() => {
    const al = document.querySelector('.alert-floating');
    if (al) { al.style.transition='opacity .4s'; al.style.opacity='0'; setTimeout(()=>al.remove(),400); }
}, 4000);
</script>
</body>
</html>
<?php $conn->close(); ?>