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

$pesan = '';
$pesan_type = '';

// ==================== HAPUS KATEGORI ====================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Cek apakah kategori masih dipakai buku
    $cek = $conn->query("SELECT COUNT(*) AS c FROM buku WHERE id_kategori = $id")->fetch_assoc()['c'];
    if ($cek > 0) {
        $pesan = "Kategori tidak bisa dihapus karena masih digunakan oleh $cek buku!";
        $pesan_type = 'error';
    } else {
        $conn->query("DELETE FROM kategori WHERE id_kategori = $id");
        header("Location: tambah_kategori.php?pesan=hapus_sukses");
        exit();
    }
}

// ==================== EDIT KATEGORI ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id   = intval($_POST['id_kategori']);
    $nama = $conn->real_escape_string(trim($_POST['nama_kategori']));
    if ($nama !== '') {
        $conn->query("UPDATE kategori SET nama_kategori = '$nama' WHERE id_kategori = $id");
        header("Location: tambah_kategori.php?pesan=edit_sukses");
        exit();
    } else {
        $pesan = "Nama kategori tidak boleh kosong!";
        $pesan_type = 'error';
    }
}

// ==================== TAMBAH KATEGORI ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $nama = $conn->real_escape_string(trim($_POST['nama_kategori']));
    if ($nama !== '') {
        // Cek duplikat
        $cek = $conn->query("SELECT COUNT(*) AS c FROM kategori WHERE LOWER(nama_kategori) = LOWER('$nama')")->fetch_assoc()['c'];
        if ($cek > 0) {
            $pesan = "Kategori \"$nama\" sudah ada!";
            $pesan_type = 'error';
        } else {
            $conn->query("INSERT INTO kategori (nama_kategori) VALUES ('$nama')");
            header("Location: tambah_kategori.php?pesan=tambah_sukses");
            exit();
        }
    } else {
        $pesan = "Nama kategori tidak boleh kosong!";
        $pesan_type = 'error';
    }
}

// Pesan dari redirect
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') { $pesan = "Kategori berhasil ditambahkan!"; $pesan_type = 'success'; }
    if ($_GET['pesan'] === 'edit_sukses')   { $pesan = "Kategori berhasil diperbarui!";  $pesan_type = 'success'; }
    if ($_GET['pesan'] === 'hapus_sukses')  { $pesan = "Kategori berhasil dihapus!";     $pesan_type = 'success'; }
}

// Data kategori + jumlah buku
$result = $conn->query("
    SELECT k.id_kategori, k.nama_kategori, COUNT(b.id_buku) AS jumlah_buku
    FROM kategori k
    LEFT JOIN buku b ON b.id_kategori = k.id_kategori
    GROUP BY k.id_kategori, k.nama_kategori
    ORDER BY k.id_kategori ASC
");

$total_kategori = $conn->query("SELECT COUNT(*) AS c FROM kategori")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - PerpusOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f1f5f9;
        }

        /* HEADER */
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
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.7);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .user-avatar:hover { transform: scale(1.05); }

        /* LAYOUT */
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }

        /* SIDEBAR */
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

        .sidebar-user img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(227,227,227,0.8); }
        .sidebar-user-name { font-weight: 600; color: white; font-size: 0.9rem; }
        .sidebar-user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); }

        /* MAIN CONTENT */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }

        /* PAGE HEADER */
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

        .page-title i { color: #0f766e; }

        .page-title .total-badge {
            font-size: 0.8rem;
            font-weight: 600;
            background: #f0fdf4;
            color: #059669;
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            margin-left: 0.25rem;
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
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
        }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }

        /* ALERT */
        .alert-custom {
            border-radius: 10px;
            padding: 0.85rem 1.2rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border: none;
        }
        .alert-success-custom { background: #f0fdf4; color: #166534; }
        .alert-error-custom   { background: #fef2f2; color: #991b1b; }

        /* GRID LAYOUT */
        .content-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        /* FORM CARD */
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .form-card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .form-card-header h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .form-card-header i { color: #0f766e; font-size: 1.1rem; }

        .form-card-body { padding: 1.5rem; }

        .form-label-custom {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 0.4rem;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            font-size: 0.875rem;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fafbff;
        }

        .form-control-custom:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15,118,110,0.1);
            background: white;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #0f766e, #0d9488);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.65rem 1.2rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }

        /* TABLE CARD */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .table-card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .table-card-header h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .table-card-header i { color: #0f766e; font-size: 1.1rem; }

        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        th {
            padding: 0.85rem 1.25rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            color: #1e293b;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafbff; }

        .kat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .buku-count {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .btn-edit-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            padding: 0.35rem 0.8rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(37,99,235,0.1);
            color: #2563eb;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-edit-row:hover { background: #2563eb; color: white; }

        .btn-del-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            border-radius: 7px;
            background: rgba(239,68,68,0.1);
            color: #dc2626;
            text-decoration: none;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-del-row:hover { background: #ef4444; color: white; }

        /* MODAL EDIT */
        .modal-custom {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }
        .modal-custom.show { display: flex; }

        .modal-box {
            background: white;
            border-radius: 14px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from { transform: scale(0.92); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        .modal-box-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-box-header h5 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0; }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            transition: color 0.2s;
        }
        .btn-close-modal:hover { color: #1e293b; }

        .modal-box-body { padding: 1.5rem; }

        .modal-box-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 8px;
            padding: 0.55rem 1.2rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-cancel:hover { background: #e2e8f0; }

        .btn-save {
            background: linear-gradient(135deg, #0f766e, #0d9488);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.55rem 1.4rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-save:hover { opacity: 0.9; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }

        /* Mobile */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
        }

        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .content-grid { grid-template-columns: 1fr; }
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
            <div class="nav-item"><a href="buku.php" class="nav-link"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-title">Lainnya</div>
            <div class="nav-item"><a href="profile_admin.php" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Admin</span></a></div>
            <div class="nav-item"><a href="tambah_kategori.php" class="nav-link active"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></div>
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
                <i class="bi bi-tags-fill"></i>
                Kelola Kategori
                <span class="total-badge"><?= $total_kategori ?> kategori</span>
            </h1>
            <a href="buku.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali ke Buku
            </a>
        </div>

        <!-- ALERT -->
        <?php if ($pesan): ?>
        <div class="alert-custom <?= $pesan_type === 'success' ? 'alert-success-custom' : 'alert-error-custom' ?>">
            <i class="bi <?= $pesan_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
            <?= htmlspecialchars($pesan) ?>
        </div>
        <?php endif; ?>

        <!-- CONTENT GRID -->
        <div class="content-grid">

            <!-- FORM TAMBAH -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-plus-circle-fill"></i>
                    <h5>Tambah Kategori Baru</h5>
                </div>
                <div class="form-card-body">
                    <form method="POST" action="tambah_kategori.php">
                        <input type="hidden" name="aksi" value="tambah">
                        <label class="form-label-custom" for="nama_kategori">Nama Kategori</label>
                        <input
                            type="text"
                            id="nama_kategori"
                            name="nama_kategori"
                            class="form-control-custom"
                            placeholder="Contoh: Sains, Sejarah, Komik..."
                            autocomplete="off"
                            required
                        >
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-plus-lg"></i> Tambah Kategori
                        </button>
                    </form>
                </div>
            </div>

            <!-- TABEL KATEGORI -->
            <div class="table-card">
                <div class="table-card-header">
                    <i class="bi bi-list-ul"></i>
                    <h5>Daftar Kategori</h5>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Kategori</th>
                            <th>Jumlah Buku</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    // Palet warna untuk badge kategori
                    $palettes = [
                        ['#8b5cf6','rgba(139,92,246,0.12)'],
                        ['#3b82f6','rgba(59,130,246,0.12)'],
                        ['#059669','rgba(5,150,105,0.12)'],
                        ['#d97706','rgba(217,119,6,0.12)'],
                        ['#dc2626','rgba(220,38,38,0.12)'],
                        ['#0891b2','rgba(8,145,178,0.12)'],
                        ['#7c3aed','rgba(124,58,237,0.12)'],
                        ['#0f766e','rgba(15,118,110,0.12)'],
                    ];
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $p = $palettes[($row['id_kategori'] - 1) % count($palettes)];
                    ?>
                        <tr>
                            <td style="color:#94a3b8;font-weight:600;"><?= $no++ ?></td>
                            <td>
                                <span class="kat-badge" style="color:<?= $p[0] ?>;background:<?= $p[1] ?>;">
                                    <i class="bi bi-tag-fill" style="font-size:0.65rem;"></i>
                                    <?= htmlspecialchars($row['nama_kategori']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="buku-count">
                                    <i class="bi bi-book"></i>
                                    <?= $row['jumlah_buku'] ?> buku
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem;align-items:center;">
                                    <button class="btn-edit-row"
                                        onclick="openEdit(<?= $row['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($row['nama_kategori'])) ?>')">
                                        <i class="bi bi-pencil-fill"></i> Edit
                                    </button>
                                    <a href="tambah_kategori.php?hapus=<?= $row['id_kategori'] ?>"
                                       class="btn-del-row"
                                       onclick="return confirm('Yakin hapus kategori \'<?= htmlspecialchars(addslashes($row['nama_kategori'])) ?>\'?')"
                                       title="Hapus">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-tags"></i>
                                    <p>Belum ada kategori. Tambahkan sekarang!</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><!-- /content-grid -->

    </main>
</div>

<!-- MODAL EDIT -->
<div class="modal-custom" id="modalEdit">
    <div class="modal-box">
        <div class="modal-box-header">
            <h5><i class="bi bi-pencil-fill me-2" style="color:#2563eb;"></i>Edit Kategori</h5>
            <button class="btn-close-modal" onclick="closeEdit()">&times;</button>
        </div>
        <form method="POST" action="tambah_kategori.php">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_kategori" id="edit_id">
            <div class="modal-box-body">
                <label class="form-label-custom" for="edit_nama">Nama Kategori</label>
                <input type="text" id="edit_nama" name="nama_kategori" class="form-control-custom" required>
            </div>
            <div class="modal-box-footer">
                <button type="button" class="btn-cancel" onclick="closeEdit()">Batal</button>
                <button type="submit" class="btn-save"><i class="bi bi-check-lg me-1"></i>Simpan</button>
            </div>
        </form>
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

    function openEdit(id, nama) {
        document.getElementById('edit_id').value  = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('modalEdit').classList.add('show');
        document.getElementById('edit_nama').focus();
    }

    function closeEdit() {
        document.getElementById('modalEdit').classList.remove('show');
    }

    // Tutup modal klik backdrop
    document.getElementById('modalEdit').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });

    // Auto-hide alert
    setTimeout(() => {
        const alert = document.querySelector('.alert-custom');
        if (alert) alert.style.transition = 'opacity 0.5s', alert.style.opacity = '0',
            setTimeout(() => alert.remove(), 500);
    }, 3500);
</script>
</body>
</html>
<?php $conn->close(); ?>