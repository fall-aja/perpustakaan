<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
require_once 'helper_katalog.php';
$menunggu_acc = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='menunggu'")->fetch_assoc()['c'] ?? 0;

$error   = '';
$success = '';

// ==================== SIMPAN BUKU ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul       = $conn->real_escape_string(trim($_POST['judul'] ?? ''));
    $penulis     = $conn->real_escape_string(trim($_POST['penulis'] ?? ''));
    $isbn        = $conn->real_escape_string(trim($_POST['isbn'] ?? ''));
    $id_kategori = intval($_POST['id_kategori'] ?? 0);
    $stok        = intval($_POST['stok'] ?? 1);
    $tahun       = intval($_POST['tahun'] ?? date('Y'));
    $deskripsi   = $conn->real_escape_string(trim($_POST['deskripsi'] ?? ''));
    $penerbit    = $conn->real_escape_string(trim($_POST['penerbit'] ?? ''));
    $lokasi_rak  = $conn->real_escape_string(trim($_POST['lokasi_rak'] ?? 'Rak A-1'));
    if ($lokasi_rak === '') $lokasi_rak = 'Rak A-1';
    $cover_name  = '';

    if ($judul === '') {
        $error = 'Judul buku tidak boleh kosong.';
    } else {
        // Upload cover
        if (!empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === 0) {
            $uploadDir = 'covers/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed)) {
                $error = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
            } elseif ($_FILES['cover']['size'] > 3 * 1024 * 1024) {
                $error = 'Ukuran gambar terlalu besar. Maksimal 3 MB.';
            } else {
                $cover_name = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . $cover_name);
            }
        }

        if ($error === '') {
            $conn->query("INSERT INTO buku
                (judul_buku, penulis, isbn, tahun_terbit, stok, stok_total, stok_tersedia, id_kategori, deskripsi, cover, lokasi_rak)
                VALUES
                ('$judul','$penulis','$isbn','$tahun','$stok','$stok','$stok','$id_kategori','$deskripsi','$cover_name','$lokasi_rak')");

            header("Location: buku.php?pesan=tambah_sukses");
            exit();
        }
    }
}

// Ambil daftar kategori
$kategoriList = $conn->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Buku | <?= NAMA_PERPUS ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="katalog_styles.css">
    <style>
        /* Base */
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        /* Header */
        .main-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            z-index: 1000;
        }

        .brand {
            display: flex; align-items: center; gap: 12px;
            color: #f8fafc; text-decoration: none;
            font-size: 1.15rem; font-weight: 600;
        }

        .brand img { width: 32px; height: 32px; border-radius: 6px; object-fit: cover; }

        .header-right { margin-left: auto; display: flex; align-items: center; gap: 1rem; }

        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 2px solid #334155;
            cursor: pointer; transition: opacity 0.2s;
        }
        .user-avatar:hover { opacity: 0.85; }

        .mobile-toggle {
            display: none; background: none; border: none;
            color: #f8fafc; font-size: 1.5rem; cursor: pointer; margin-right: 1rem;
        }

        /* Layout */
        .layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 60px; left: 0; bottom: 0;
            width: 260px;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            overflow-y: auto; z-index: 900;
            display: flex; flex-direction: column;
        }

        .sidebar-section { padding: 1.5rem 0; }

        .sidebar-title {
            font-size: 0.72rem; font-weight: 600;
            color: #64748b;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 0 1.5rem; margin-bottom: 0.75rem;
        }

        .nav-item { margin: 0.25rem 0.875rem; }

        .nav-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.7rem 1rem; color: #94a3b8;
            text-decoration: none; border-radius: 8px;
            transition: background 0.15s, color 0.15s; font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-link:hover { background: #1e293b; color: #f8fafc; }

        .nav-link.active {
            background: #2563eb;
            color: #ffffff;
        }

        .nav-link i { font-size: 1.1rem; width: 20px; }

        .sidebar-user {
            margin-top: auto; padding: 1.25rem;
            border-top: 1px solid #1e293b;
            display: flex; align-items: center; gap: 0.75rem;
            background: #090d16;
        }

        .sidebar-user img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #334155; object-fit: cover; }
        .sidebar-user-name { font-weight: 600; color: #f8fafc; font-size: 0.9rem; }
        .sidebar-user-role { font-size: 0.75rem; color: #94a3b8; text-transform: capitalize; }

        /* Main */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; background: #f8fafc; }

        /* Page title area */
        .page-top {
            display: flex; align-items: center; gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-top-icon {
            width: 38px; height: 38px;
            border-radius: 8px;
            background: #1d4ed8;
            display: flex; align-items: center; justify-content: center;
            color: #ffffff; font-size: 1rem;
        }

        .page-top h1 {
            font-size: 1.35rem; font-weight: 700;
            color: #0f172a; margin: 0;
        }

        /* Form card */
        .form-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        /* Section divider label */
        .section-sep {
            display: flex; align-items: center; gap: 0.75rem;
            margin: 1.5rem 0 1.1rem;
        }

        .section-sep-line {
            flex: 1; height: 1px; background: #e2e8f0;
        }

        .section-sep-label {
            font-size: 0.72rem; font-weight: 700;
            color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;
            white-space: nowrap;
        }

        /* Field labels */
        .form-label {
            font-size: 0.8rem; font-weight: 600;
            color: #334155; margin-bottom: 0.35rem;
            display: flex; align-items: center; gap: 0.3rem;
        }

        .form-label i { color: #64748b; font-size: 0.85rem; }

        /* Inputs */
        .form-control, .form-select {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.875rem;
            padding: 0.55rem 0.85rem;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color 0.15s, background 0.15s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: none;
            outline: none;
        }

        .form-control::placeholder { color: #94a3b8; }
        textarea.form-control { resize: vertical; min-height: 90px; }

        /* Cover upload */
        .cover-section {
            display: flex; gap: 1.5rem;
            align-items: flex-start;
        }

        .cover-left { flex-shrink: 0; width: 160px; }

        .cover-drop {
            width: 160px; height: 210px;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 0.6rem; cursor: pointer;
            transition: border-color 0.15s, background 0.15s; position: relative;
            overflow: hidden; text-align: center;
            padding: 1rem;
        }

        .cover-drop input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }

        .cover-drop:hover, .cover-drop.drag-over {
            border-color: #2563eb; background: #eff6ff;
        }

        .cover-drop .cd-icon { font-size: 1.8rem; color: #64748b; transition: color 0.15s; }
        .cover-drop:hover .cd-icon { color: #2563eb; }
        .cover-drop .cd-text { font-size: 0.78rem; font-weight: 600; color: #334155; }
        .cover-drop .cd-hint { font-size: 0.7rem; color: #94a3b8; }

        /* Preview */
        .cover-preview {
            width: 160px; height: 210px;
            border-radius: 8px;
            overflow: hidden;
            display: none; position: relative;
            border: 1px solid #e2e8f0;
        }

        .cover-preview img {
            width: 100%; height: 100%;
            object-fit: cover; display: block;
        }

        .cover-preview .cp-remove {
            position: absolute; top: 7px; right: 7px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: #0f172a;
            color: white; border: none; cursor: pointer;
            font-size: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }

        .cover-preview .cp-remove:hover { background: #dc2626; }

        .cover-hint-text {
            margin-top: 0.6rem; font-size: 0.7rem;
            color: #94a3b8; text-align: center; line-height: 1.5;
        }

        .cover-right { flex: 1; }

        /* Action buttons */
        .form-actions {
            display: flex; gap: 0.75rem; flex-wrap: wrap;
            margin-top: 2rem; padding-top: 1.25rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn-save {
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.6rem 1.5rem;
            background: #1d4ed8;
            color: white; border: none; border-radius: 8px;
            font-size: 0.875rem; font-weight: 600; cursor: pointer;
            transition: background 0.15s;
        }

        .btn-save:hover { background: #1e40af; }

        .btn-cancel {
            display: inline-flex; align-items: center; gap: 0.45rem;
            padding: 0.6rem 1.3rem;
            background: #ffffff; color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 0.875rem;
            font-weight: 500; cursor: pointer;
            text-decoration: none; transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .btn-cancel:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }

        /* Error alert */
        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca;
            color: #b91c1c; border-radius: 8px;
            padding: 0.75rem 1rem; font-size: 0.85rem;
            display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        /* Required star */
        .req { color: #dc2626; margin-left: 1px; }

        /* Responsive */
        @media (max-width: 991px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }

        @media (max-width: 640px) {
            .cover-section { flex-direction: column; align-items: center; }
            .cover-right { width: 100%; }
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
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex me-2" style="font-size:0.8rem;">
            <i class="bi bi-globe me-1"></i>Katalog Publik
        </a>
        <div class="dropdown">
            <img src="logo 40.jpeg" alt="User" class="user-avatar" data-bs-toggle="dropdown">
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile_admin.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
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
            <div class="nav-item"><a href="buku.php" class="nav-link active"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
            <div class="nav-item">
                <a href="persetujuan_admin.php" class="nav-link d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-square"></i><span>Persetujuan</span>
                    </div>
                    <?php if (!empty($menunggu_acc) && $menunggu_acc > 0): ?>
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
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin') ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($_SESSION['level'] ?? 'admin') ?></div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- Page title -->
        <div class="page-top">
            <div class="page-top-icon"><i class="bi bi-plus-lg"></i></div>
            <h1>Tambah Buku Baru</h1>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert-err">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="formTambah">

                <!-- â”€â”€ Cover + Judul section â”€â”€ -->
                <div class="cover-section">

                    <!-- Cover upload -->
                    <div class="cover-left">
                        <label class="form-label"><i class="bi bi-image"></i> Cover Buku</label>

                        <div class="cover-drop" id="coverDrop">
                            <input type="file" name="cover" id="coverInput"
                                   accept=".jpg,.jpeg,.png,.webp,.gif"
                                   onchange="previewCover(this)">
                            <i class="bi bi-cloud-arrow-up cd-icon"></i>
                            <div class="cd-text">Klik atau drag &amp; drop</div>
                            <div class="cd-hint">JPG, PNG, WEBP<br>Maks. 3 MB</div>
                        </div>

                        <div class="cover-preview" id="coverPreview">
                            <img src="" alt="Preview" id="coverPreviewImg">
                            <button type="button" class="cp-remove" onclick="removeCover()" title="Hapus">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>

                        <div class="cover-hint-text">
                            Jika tidak diisi,<br>cover otomatis dibuat
                        </div>
                    </div>

                    <!-- Judul + Penulis + ISBN -->
                    <div class="cover-right">

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-book"></i> Judul Buku <span class="req">*</span>
                            </label>
                            <input type="text" name="judul" class="form-control"
                                   placeholder="Masukkan judul buku"
                                   value="<?= htmlspecialchars($_POST['judul'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <i class="bi bi-person"></i> Penulis
                                </label>
                                <input type="text" name="penulis" class="form-control"
                                       placeholder="Nama penulis"
                                       value="<?= htmlspecialchars($_POST['penulis'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <i class="bi bi-upc"></i>ISBN
                                </label>
                                <input type="text" name="isbn" class="form-control"
                                       placeholder="ISBN buku"
                                       value="<?= htmlspecialchars($_POST['isbn'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label">
                                    <i class="bi bi-folder"></i> Kategori <span class="req">*</span>
                                </label>
                                <select name="id_kategori" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php
                                    if ($kategoriList) while ($k = $kategoriList->fetch_assoc()):
                                        $sel = (isset($_POST['id_kategori']) && $_POST['id_kategori'] == $k['id_kategori']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $k['id_kategori'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">
                                    <i class="bi bi-box-seam"></i> Stok <span class="req">*</span>
                                </label>
                                <input type="number" name="stok" class="form-control"
                                       placeholder="Jumlah stok"
                                       min="1" value="<?= intval($_POST['stok'] ?? 1) ?>" required>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">
                                    <i class="bi bi-calendar3"></i> Tahun Terbit
                                </label>
                                <input type="number" name="tahun" class="form-control"
                                       placeholder="Contoh: 2023"
                                       min="1900" max="<?= date('Y') ?>"
                                       value="<?= intval($_POST['tahun'] ?? date('Y')) ?>">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- â”€â”€ Penerbit + Deskripsi â”€â”€ -->
                <div class="section-sep">
                    <div class="section-sep-line"></div>
                    <span class="section-sep-label">Informasi Tambahan</span>
                    <div class="section-sep-line"></div>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">
                            <i class="bi bi-geo-alt-fill text-danger"></i> Nomor Rak &amp; Lokasi Fisik
                        </label>
                        <input type="text" name="lokasi_rak" class="form-control"
                               placeholder="Contoh: Rak A-3 / Buku Paket"
                               value="<?= htmlspecialchars($_POST['lokasi_rak'] ?? 'Rak A-1') ?>">
                        <small class="text-muted" style="font-size:0.75rem;">Letak lemari atau rak tempat buku disimpan di perpustakaan</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">
                            <i class="bi bi-card-text"></i> Deskripsi
                        </label>
                        <textarea name="deskripsi" class="form-control" rows="2"
                                  placeholder="Deskripsi buku (opsional)"><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- â”€â”€ Actions â”€â”€ -->
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="bi bi-floppy-fill"></i> Simpan
                    </button>
                    <a href="buku.php" class="btn-cancel">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                </div>

            </form>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // â”€â”€ Cover preview â”€â”€
    function previewCover(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran file terlalu besar. Maksimal 3 MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('coverPreviewImg').src = e.target.result;
            document.getElementById('coverDrop').style.display    = 'none';
            document.getElementById('coverPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function removeCover() {
        document.getElementById('coverInput').value = '';
        document.getElementById('coverPreviewImg').src = '';
        document.getElementById('coverPreview').style.display = 'none';
        document.getElementById('coverDrop').style.display    = 'flex';
    }

    // â”€â”€ Drag & drop feedback â”€â”€
    const drop = document.getElementById('coverDrop');
    drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('drag-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('drag-over'));
    drop.addEventListener('drop',      () => drop.classList.remove('drag-over'));

    // â”€â”€ Sidebar toggle â”€â”€
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.querySelector('.mobile-toggle');
        if (window.innerWidth <= 991 && toggle &&
            !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
</script>
</body>
</html>
<?php $conn->close(); ?>