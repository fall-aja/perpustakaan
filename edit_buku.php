<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['level'] != 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "perpustakaan");
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

$error   = '';
$success = '';

// ==================== AMBIL DATA BUKU ====================
$id_buku = intval($_GET['id'] ?? 0);
if ($id_buku <= 0) {
    header("Location: buku.php");
    exit();
}

$result = $conn->query("SELECT * FROM buku WHERE id_buku = $id_buku");
$buku = $result->fetch_assoc();

if (!$buku) {
    header("Location: buku.php");
    exit();
}

// ==================== UPDATE BUKU ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul       = $conn->real_escape_string(trim($_POST['judul'] ?? ''));
    $penulis     = $conn->real_escape_string(trim($_POST['penulis'] ?? ''));
    $isbn        = $conn->real_escape_string(trim($_POST['isbn'] ?? ''));
    $id_kategori = intval($_POST['id_kategori'] ?? 0);
    $stok        = intval($_POST['stok'] ?? 1);
    $tahun       = intval($_POST['tahun'] ?? date('Y'));
    $deskripsi   = $conn->real_escape_string(trim($_POST['deskripsi'] ?? ''));
    $cover_name  = $buku['cover']; // Default cover lama
    
    if ($judul === '') {
        $error = 'Judul buku tidak boleh kosong.';
    } else {
        // Upload cover baru (jika ada)
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
                // Hapus cover lama jika ada
                if (!empty($buku['cover']) && file_exists($uploadDir . $buku['cover'])) {
                    unlink($uploadDir . $buku['cover']);
                }
                $cover_name = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . $cover_name);
            }
        }
        
        if ($error === '') {
            $conn->query("UPDATE buku SET
                judul_buku = '$judul',
                penulis = '$penulis',
                isbn = '$isbn',
                tahun_terbit = '$tahun',
                stok = '$stok',
                stok_total = '$stok',
                stok_tersedia = '$stok',
                id_kategori = '$id_kategori',
                deskripsi = '$deskripsi',
                cover = '$cover_name'
                WHERE id_buku = $id_buku");
            
            header("Location: buku.php?pesan=edit_sukses");
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
<title>Edit Buku - PerpusOnline</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
/* ── Base ── */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f1f5f9;
}
/* ── Header ── */
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
    display: flex; align-items: center; gap: 12px;
    color: white; text-decoration: none;
    font-size: 1.25rem; font-weight: 600;
}
.brand img { width: 32px; height: 32px; border-radius: 50%; }
.header-right { margin-left: auto; display: flex; align-items: center; gap: 1rem; }
.user-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.7);
    cursor: pointer; transition: transform 0.2s;
}
.user-avatar:hover { transform: scale(1.05); }
.mobile-toggle {
    display: none; background: none; border: none;
    color: white; font-size: 1.5rem; cursor: pointer; margin-right: 1rem;
}
/* ── Layout ── */
.layout-wrapper { display: flex; padding-top: 60px; min-height: 100vh; }
/* ── Sidebar ── */
.sidebar {
    position: fixed;
    top: 60px; left: 0; bottom: 0;
    width: 260px;
    background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
    border-right: 1px solid rgba(255,255,255,0.05);
    overflow-y: auto; z-index: 900;
    display: flex; flex-direction: column;
}
.sidebar-section { padding: 1.5rem 0; }
.sidebar-title {
    font-size: 0.7rem; font-weight: 600;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase; letter-spacing: 0.5px;
    padding: 0 1.5rem; margin-bottom: 0.75rem;
}
.nav-item { margin: 0.25rem 1rem; }
.nav-link {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1rem; color: rgba(245,245,245,0.7);
    text-decoration: none; border-radius: 8px;
    transition: all 0.2s; font-size: 0.9rem;
}
.nav-link:hover { background: rgba(255,255,255,0.05); color: white; }
.nav-link.active {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: white; box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}
.nav-link i { font-size: 1.1rem; width: 20px; }
.sidebar-user {
    margin-top: auto; padding: 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; gap: 0.75rem;
    background: rgba(0,0,0,0.2);
}
.sidebar-user img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(227,227,227,0.8); }
.sidebar-user-name { font-weight: 600; color: white; font-size: 0.9rem; }
.sidebar-user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); }
/* ── Main ── */
.main-content { flex: 1; margin-left: 260px; padding: 2rem; }
/* ── Page title area ── */
.page-top {
    display: flex; align-items: center; gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}
.page-top-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1rem;
}
.page-top h1 {
    font-size: 1.35rem; font-weight: 700;
    color: #d97706; margin: 0;
}
/* ── Form card ── */
.form-card {
    background: white;
    border-radius: 14px;
    padding: 2rem;
    box-shadow: 0 1px 6px rgba(0,0,0,0.07);
}
/* ── Section divider label ── */
.section-sep {
    display: flex; align-items: center; gap: 0.75rem;
    margin: 1.5rem 0 1.1rem;
}
.section-sep-line {
    flex: 1; height: 1px; background: #e2e8f0;
}
.section-sep-label {
    font-size: 0.7rem; font-weight: 700;
    color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px;
    white-space: nowrap;
}
/* ── Field labels ── */
.form-label {
    font-size: 0.8rem; font-weight: 600;
    color: #374151; margin-bottom: 0.35rem;
    display: flex; align-items: center; gap: 0.3rem;
}
.form-label i { color: #64748b; font-size: 0.85rem; }
/* ── Inputs ── */
.form-control, .form-select {
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    font-size: 0.875rem;
    padding: 0.55rem 0.85rem;
    color: #1e293b;
    background: #fff;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.form-control:focus, .form-select:focus {
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
    outline: none;
}
.form-control::placeholder { color: #cbd5e1; }
textarea.form-control { resize: vertical; min-height: 90px; }
/* ── Cover upload ── */
.cover-section {
    display: flex; gap: 1.5rem;
    align-items: flex-start;
}
.cover-left { flex-shrink: 0; width: 160px; }
.cover-drop {
    width: 160px; height: 210px;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 0.6rem; cursor: pointer;
    transition: all 0.2s; position: relative;
    overflow: hidden; text-align: center;
    padding: 1rem;
}
.cover-drop input[type="file"] {
    position: absolute; inset: 0;
    opacity: 0; cursor: pointer;
    width: 100%; height: 100%;
}
.cover-drop:hover, .cover-drop.drag-over {
    border-color: #f59e0b; background: #fffbeb;
}
.cover-drop .cd-icon { font-size: 2rem; color: #94a3b8; transition: color 0.2s; }
.cover-drop:hover .cd-icon { color: #f59e0b; }
.cover-drop .cd-text { font-size: 0.75rem; font-weight: 600; color: #64748b; }
.cover-drop .cd-hint { font-size: 0.67rem; color: #94a3b8; }
/* Preview */
.cover-preview {
    width: 160px; height: 210px;
    border-radius: 12px;
    overflow: hidden;
    display: none; position: relative;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.cover-preview img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
}
.cover-preview .cp-remove {
    position: absolute; top: 7px; right: 7px;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: rgba(0,0,0,0.55);
    color: white; border: none; cursor: pointer;
    font-size: 0.75rem;
    display: flex; align-items: center; justify-content: center;
    transition: 0.18s;
}
.cover-preview .cp-remove:hover { background: #ef4444; }
.cover-hint-text {
    margin-top: 0.6rem; font-size: 0.68rem;
    color: #94a3b8; text-align: center; line-height: 1.5;
}
.cover-right { flex: 1; }
/* ── Action buttons ── */
.form-actions {
    display: flex; gap: 0.75rem; flex-wrap: wrap;
    margin-top: 2rem; padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}
.btn-save {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.6rem 1.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border: none; border-radius: 9px;
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    box-shadow: 0 4px 12px rgba(245,158,11,0.25);
    transition: all 0.2s;
}
.btn-save:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-cancel {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.6rem 1.3rem;
    background: white; color: #64748b;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px; font-size: 0.875rem;
    font-weight: 500; cursor: pointer;
    text-decoration: none; transition: all 0.18s;
}
.btn-cancel:hover { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; }
/* ── Error/Success alert ── */
.alert-err {
    background: #fef2f2; border: 1.5px solid #fecaca;
    color: #dc2626; border-radius: 9px;
    padding: 0.75rem 1rem; font-size: 0.85rem;
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 1.25rem;
}
.alert-suc {
    background: #f0fdf4; border: 1.5px solid #bbf7d0;
    color: #16a34a; border-radius: 9px;
    padding: 0.75rem 1rem; font-size: 0.85rem;
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 1.25rem;
}
/* ── Required star ── */
.req { color: #ef4444; margin-left: 1px; }
/* ── Responsive ── */
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
            <div class="nav-item"><a href="buku.php" class="nav-link active"><i class="bi bi-book"></i><span>Buku</span></a></div>
            <div class="nav-item"><a href="anggota.php" class="nav-link"><i class="bi bi-people"></i><span>Anggota</span></a></div>
            <div class="nav-item"><a href="peminjaman.php" class="nav-link"><i class="bi bi-clock-history"></i><span>Peminjaman</span></a></div>
            <div class="nav-item"><a href="pengembalian.php" class="nav-link"><i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-title">Lainnya</div>
            <div class="nav-item"><a href="profile_admin.php" class="nav-link"><i class="bi bi-person-circle"></i><span>Profile Admin</span></a></div>
            <div class="nav-item"><a href="tambah_kategori.php" class="nav-link"><i class="bi bi-tags-fill"></i><span>Kategori</span></a></div>
            <div class="nav-item"><a href="pengaturan.php" class="nav-link"><i class="bi bi-gear"></i><span>Pengaturan</span></a></div>
            <div class="nav-item"><a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-left"></i><span>Keluar</span></a></div>
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
            <div class="page-top-icon"><i class="bi bi-pencil-fill"></i></div>
            <h1>Edit Buku</h1>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert-err">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Success -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'edit_sukses'): ?>
        <div class="alert-suc">
            <i class="bi bi-check-circle-fill"></i> Data buku berhasil diupdate!
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="formEdit">
                <!-- ── Cover + Judul section ── -->
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
                            Kosongkan jika tidak ingin<br>mengubah cover
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
                                value="<?= htmlspecialchars($buku['judul_buku'] ?? '') ?>"
                                required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <i class="bi bi-person"></i> Penulis
                                </label>
                                <input type="text" name="penulis" class="form-control"
                                    placeholder="Nama penulis"
                                    value="<?= htmlspecialchars($buku['penulis'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <i class="bi bi-upc"></i> ISBN
                                </label>
                                <input type="text" name="isbn" class="form-control"
                                    placeholder="ISBN buku"
                                    value="<?= htmlspecialchars($buku['isbn'] ?? '') ?>">
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
                                        $sel = ($buku['id_kategori'] == $k['id_kategori']) ? 'selected' : '';
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
                                    min="1" value="<?= intval($buku['stok'] ?? 1) ?>" required>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">
                                    <i class="bi bi-calendar3"></i> Tahun Terbit
                                </label>
                                <input type="number" name="tahun" class="form-control"
                                    placeholder="Contoh: 2023"
                                    min="1900" max="<?= date('Y') ?>"
                                    value="<?= intval($buku['tahun_terbit'] ?? date('Y')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Penerbit + Deskripsi ── -->
                <div class="section-sep">
                    <div class="section-sep-line"></div>
                    <span class="section-sep-label">Informasi Tambahan</span>
                    <div class="section-sep-line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-sm-8">
                        <label class="form-label">
                            <i class="bi bi-card-text"></i> Deskripsi
                        </label>
                        <textarea name="deskripsi" class="form-control"
                            placeholder="Deskripsi buku (opsional)"><?= htmlspecialchars($buku['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- ── Actions ── -->
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="bi bi-floppy-fill"></i> Update
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
// ── Show existing cover if exists ──
<?php if (!empty($buku['cover']) && file_exists('covers/'.$buku['cover'])): ?>
document.getElementById('coverPreviewImg').src = 'covers/<?= htmlspecialchars($buku['cover']) ?>';
document.getElementById('coverDrop').style.display    = 'none';
document.getElementById('coverPreview').style.display = 'block';
<?php endif; ?>

// ── Cover preview ──
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

// ── Drag & drop feedback ──
const drop = document.getElementById('coverDrop');
drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('drag-over'); });
drop.addEventListener('dragleave', () => drop.classList.remove('drag-over'));
drop.addEventListener('drop',      () => drop.classList.remove('drag-over'));

// ── Sidebar toggle ──
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