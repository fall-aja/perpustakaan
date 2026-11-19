<?php
// helper_katalog.php - Helper bersama untuk katalog, identitas sekolah, sampul buku, dan status stok

define('NAMA_PERPUS', 'PerpusOnline');
define('TAGLINE_PERPUS', 'Pusat Literasi & Sumber Belajar Digital Terbuka');
define('ALAMAT_PERPUS', 'Gedung Perpustakaan Digital Lantai 2, Jakarta');
define('KONTAK_PERPUS', '(021) 8515082 | info@perpusonline.sch.id');

/**
 * Cek apakah perpustakaan sedang buka saat ini
 */
function isPerpusBuka() {
    $hari = date('N'); // 1 = Senin, 7 = Minggu
    $jamMenit = date('H:i');
    
    // Libur Sabtu (6) dan Minggu (7)
    if ($hari >= 6) return false;
    
    // Jumat: 07.00 - 15.00, istirahat Jumat 11.30 - 13.00
    if ($hari == 5) {
        if ($jamMenit >= '11:30' && $jamMenit <= '13:00') return false;
        return ($jamMenit >= '07:00' && $jamMenit <= '15:00');
    }
    
    // Senin - Kamis: 07.00 - 15.30
    return ($jamMenit >= '07:00' && $jamMenit <= '15:30');
}

/**
 * Mendapatkan palet warna sampul kain jilid (Solid Cloth-bound)
 */
function getClothTheme($judul, $kategori = '') {
    $kat = strtolower($kategori ?? '');
    
    // Palet warna kain klasik (Navy, Burgundy, Forest Green, Slate, Royal Amber, Deep Indigo)
    if (strpos($kat, 'novel') !== false || strpos($kat, 'fiksi') !== false) {
        return ['bg' => '#1e1b4b', 'accent' => '#c084fc', 'border' => 'rgba(192, 132, 252, 0.4)', 'ribbon' => '#e879f9'];
    }
    if (strpos($kat, 'sains') !== false || strpos($kat, 'teknologi') !== false) {
        return ['bg' => '#0c4a6e', 'accent' => '#38bdf8', 'border' => 'rgba(56, 189, 248, 0.4)', 'ribbon' => '#0ea5e9'];
    }
    if (strpos($kat, 'pendidikan') !== false || strpos($kat, 'pelajaran') !== false) {
        return ['bg' => '#14532d', 'accent' => '#86efac', 'border' => 'rgba(134, 239, 172, 0.4)', 'ribbon' => '#22c55e'];
    }
    if (strpos($kat, 'biografi') !== false || strpos($kat, 'sejarah') !== false) {
        return ['bg' => '#78350f', 'accent' => '#fde047', 'border' => 'rgba(253, 224, 71, 0.4)', 'ribbon' => '#eab308'];
    }
    if (strpos($kat, 'komik') !== false) {
        return ['bg' => '#831843', 'accent' => '#f472b6', 'border' => 'rgba(244, 114, 182, 0.4)', 'ribbon' => '#ec4899'];
    }

    $palettes = [
        ['bg' => '#0f172a', 'accent' => '#cbd5e1', 'border' => 'rgba(203, 213, 225, 0.35)', 'ribbon' => '#94a3b8'],
        ['bg' => '#1e293b', 'accent' => '#93c5fd', 'border' => 'rgba(147, 197, 253, 0.4)', 'ribbon' => '#3b82f6'],
        ['bg' => '#3b0764', 'accent' => '#d8b4fe', 'border' => 'rgba(216, 180, 254, 0.4)', 'ribbon' => '#a855f7'],
        ['bg' => '#450a0a', 'accent' => '#fca5a5', 'border' => 'rgba(252, 165, 165, 0.4)', 'ribbon' => '#ef4444'],
        ['bg' => '#134e4a', 'accent' => '#5eead4', 'border' => 'rgba(94, 234, 212, 0.4)', 'ribbon' => '#14b8a6'],
    ];
    $idx = abs(crc32($judul)) % count($palettes);
    return $palettes[$idx];
}

/**
 * Render Sampul Buku (dengan fallback desain Sampul Jilid Kain Solid)
 */
function renderBookCover($cover, $judul, $penulis = '', $kategori = '', $lokasiRak = 'Rak A-1') {
    $hasFile = !empty($cover) && file_exists(__DIR__ . '/covers/' . $cover);
    
    if ($hasFile) {
        $coverEsc = htmlspecialchars($cover);
        $judulEsc = htmlspecialchars($judul);
        return <<<HTML
        <div class="book-cover-wrap has-img">
            <img src="covers/{$coverEsc}" alt="{$judulEsc}" class="book-real-cover" loading="lazy" onerror="this.parentElement.classList.remove('has-img'); this.remove();">
        </div>
HTML;
    }

    // Desain Sampul Jilid Kain Solid (Cloth-Bound Hardcover)
    $theme = getClothTheme($judul, $kategori);
    $bg = $theme['bg'];
    $accent = $theme['accent'];
    $border = $theme['border'];
    $ribbon = $theme['ribbon'];
    
    $judulEsc   = htmlspecialchars($judul);
    $penulisEsc = htmlspecialchars($penulis ?: 'Penulis Perpustakaan');
    $katEsc     = htmlspecialchars($kategori ?: 'Pustaka');
    $rakEsc     = htmlspecialchars($lokasiRak ?: 'Rak A-1');

    return <<<HTML
    <div class="cloth-book-cover" style="--cloth-bg: {$bg}; --cloth-accent: {$accent}; --cloth-border: {$border}; --cloth-ribbon: {$ribbon};" title="{$judulEsc}">
        <div class="cloth-spine"></div>
        <div class="cloth-ribbon"></div>
        <div class="cloth-frame">
            <div class="cloth-header">
                <span class="cloth-cat"><i class="bi bi-bookmark-fill me-1"></i>{$katEsc}</span>
            </div>
            <div class="cloth-title">{$judulEsc}</div>
            <div class="cloth-divider"></div>
            <div class="cloth-author"><i class="bi bi-pen me-1"></i>{$penulisEsc}</div>
            <div class="cloth-footer">
                <span class="cloth-rak"><i class="bi bi-bookshelf me-1"></i>{$rakEsc}</span>
            </div>
        </div>
    </div>
HTML;
}

/**
 * Render Badge Status Stok
 * Hijau: Tersedia (X)
 * Abu-abu: Sedang Dipinjam / Stok Habis (0)
 */
function renderStockBadge($stok) {
    $stok = intval($stok);
    if ($stok > 0) {
        return '<span class="badge-stock stock-available"><i class="bi bi-check-circle-fill"></i> Tersedia (' . $stok . ')</span>';
    } else {
        return '<span class="badge-stock stock-borrowed"><i class="bi bi-clock-history"></i> Sedang Dipinjam</span>';
    }
}

/**
 * Render Badge Nomor Rak & Lokasi Fisik
 */
function renderRakBadge($lokasiRak) {
    $lokasi = htmlspecialchars($lokasiRak ?: 'Rak A-1');
    return '<span class="badge-rak" title="Lokasi Fisik Buku"><i class="bi bi-geo-alt-fill text-danger me-1"></i>' . $lokasi . '</span>';
}

/**
 * Hitung jumlah permintaan pinjam yang masih berstatus menunggu
 */
function getJumlahMenunggu($conn) {
    if (!$conn) return 0;
    $res = $conn->query("SELECT COUNT(*) AS c FROM persetujuan WHERE status='menunggu'");
    return $res ? intval($res->fetch_assoc()['c']) : 0;
}

/**
 * Render Pelacak Status Peminjaman (4 Langkah: Diajukan -> Disetujui -> Siap Diambil di Ruang Perpus -> Dikembalikan)
 */
function renderStatusTracker($stepName, $tglPengajuan = '', $tglKembali = '', $catatan = '') {
    if ($stepName === 'ditolak') {
        $catatanEsc = htmlspecialchars($catatan ?: 'Pengajuan belum dapat disetujui pustakawan.');
        return <<<HTML
        <div class="alert alert-danger d-flex align-items-center gap-2 p-3 mb-3 rounded-3" style="font-size:0.88rem;">
            <i class="bi bi-x-circle-fill fs-4 text-danger flex-shrink-0"></i>
            <div>
                <strong>Pengajuan Ditolak:</strong> {$catatanEsc}
            </div>
        </div>
HTML;
    }

    $stepNum = 1;
    if ($stepName === 'disetujui')   $stepNum = 2;
    if ($stepName === 'dipinjam')    $stepNum = 3;
    if ($stepName === 'dikembalikan') $stepNum = 4;

    $pct = match($stepNum) {
        1 => '12%',
        2 => '40%',
        3 => '70%',
        4 => '100%',
        default => '12%'
    };

    $s1Cls = $stepNum >= 1 ? ($stepNum == 1 ? 'active' : 'completed') : '';
    $s2Cls = $stepNum >= 2 ? ($stepNum == 2 ? 'active' : 'completed') : '';
    $s3Cls = $stepNum >= 3 ? ($stepNum == 3 ? 'active' : 'completed') : '';
    $s4Cls = $stepNum >= 4 ? ($stepNum == 4 ? 'active' : 'completed') : '';

    $s1Icon = $stepNum > 1 ? '<i class="bi bi-check-lg"></i>' : '1';
    $s2Icon = $stepNum > 2 ? '<i class="bi bi-check-lg"></i>' : '2';
    $s3Icon = $stepNum > 3 ? '<i class="bi bi-check-lg"></i>' : '3';
    $s4Icon = $stepNum >= 4 ? '<i class="bi bi-check-lg"></i>' : '4';

    return <<<HTML
    <div class="p-3 bg-white rounded-3 border mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold" style="font-size:0.85rem;color:#0f172a;">
                <i class="bi bi-diagram-3-fill text-primary me-1"></i>Pelacak Alur Peminjaman
            </span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.75rem;">
                Tahap {$stepNum} dari 4
            </span>
        </div>
        <div class="status-tracker">
            <div class="tracker-progress-bar" style="width: {$pct};"></div>
            
            <div class="tracker-step {$s1Cls}">
                <div class="tracker-circle">{$s1Icon}</div>
                <div class="tracker-label">Diajukan</div>
            </div>

            <div class="tracker-step {$s2Cls}">
                <div class="tracker-circle">{$s2Icon}</div>
                <div class="tracker-label">Disetujui</div>
            </div>

            <div class="tracker-step {$s3Cls}">
                <div class="tracker-circle">{$s3Icon}</div>
                <div class="tracker-label">Siap Diambil di Ruang Perpus</div>
            </div>

            <div class="tracker-step {$s4Cls}">
                <div class="tracker-circle">{$s4Icon}</div>
                <div class="tracker-label">Dikembalikan</div>
            </div>
        </div>
    </div>
HTML;
}

/**
 * Render Kartu Anggota Perpustakaan Digital Lengkap dengan NIS, Foto, & Barcode
 */
function renderDigitalIdCard($user) {
    $nama   = htmlspecialchars($user['nama'] ?? 'Anggota Siswa');
    $nis    = htmlspecialchars($user['nis'] ?: ('2024' . str_pad($user['id_user'] ?? 1, 4, '0', STR_PAD_LEFT)));
    $foto   = !empty($user['foto_profil']) && file_exists(__DIR__ . '/foto_profil/' . $user['foto_profil'])
              ? 'foto_profil/' . htmlspecialchars($user['foto_profil'])
              : null;
    $initial = strtoupper(substr($nama, 0, 1));
    $kadaluarsa = date('Y') + 1;

    $photoHtml = $foto 
        ? "<img src=\"{$foto}\" alt=\"{$nama}\">"
        : "<div class=\"id-photo-placeholder\">{$initial}</div>";

    // Generate dynamic barcode lines from NIS
    $barcodeLines = '';
    $nisDigits = preg_replace('/\D/', '', $nis) . '78912345';
    for ($i = 0; $i < 30; $i++) {
        $digit = intval($nisDigits[$i % strlen($nisDigits)] ?? 3);
        $w = ($digit % 3) + 1;
        $margin = ($i % 2 === 0) ? 1 : 2;
        $barcodeLines .= "<span style=\"width: {$w}px; margin-right: {$margin}px;\"></span>";
    }

    return <<<HTML
    <div class="digital-id-card">
        <div class="id-header">
            <img src="logo 40.jpeg" alt="Logo PerpusOnline" class="id-logo" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
            <div>
                <div class="id-school-title">KARTU ANGGOTA RESMI</div>
                <div class="id-school-sub">PERPUSONLINE</div>
            </div>
        </div>

        <div class="id-body">
            <div class="id-photo-frame">
                {$photoHtml}
            </div>
            <div class="id-info">
                <div class="id-student-name">{$nama}</div>
                <span class="id-badge-nis"><i class="bi bi-upc-scan me-1"></i>NIS: {$nis}</span>
                <div class="id-meta-row"><i class="bi bi-person-badge me-1"></i>Status: <strong>Anggota Aktif</strong></div>
                <div class="id-meta-row"><i class="bi bi-calendar-check me-1"></i>Berlaku s/d: Des {$kadaluarsa}</div>
            </div>
        </div>

        <div class="id-footer">
            <div class="id-barcode-wrap">
                <div class="id-barcode-lines">
                    {$barcodeLines}
                </div>
            </div>
            <div class="id-valid-info">
                <div>KARTU PERPUSTAKAAN</div>
                <small class="text-white-50">PERPUSONLINE</small>
            </div>
        </div>
    </div>
HTML;
}
?>
