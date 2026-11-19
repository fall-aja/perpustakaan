<?php
session_start();
// Jika sudah login, langsung arahkan sesuai level
if (isset($_SESSION['level'])) {
    if ($_SESSION['level'] == 'admin') {
        header("Location: beranda.php");
        exit();
    } else {
        header("Location: login_anggota.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | PerpusOnline</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --primary-light: #eff6ff;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --border-hover: #cbd5e1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #ffffff;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        .auth-container {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            flex-direction: row;
        }

        /* Left Side: Showcase */
        .auth-showcase {
            flex: 1.1;
            min-height: 100vh;
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
            border-right: 1px solid #1e293b;
            color: #ffffff;
            padding: 3.5rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #1e293b;
            padding: 8px 18px 8px 10px;
            border-radius: 50px;
            border: 1px solid #334155;
            margin-bottom: 2rem;
        }

        .brand-badge img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #334155;
        }

        .brand-badge span {
            font-size: 0.95rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .showcase-title {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 1.25rem;
            letter-spacing: -0.5px;
            color: #ffffff;
            max-width: 520px;
        }

        .showcase-desc {
            font-size: 0.98rem;
            color: #94a3b8;
            line-height: 1.65;
            margin-bottom: 2.5rem;
            max-width: 480px;
        }

        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
            max-width: 480px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.95rem;
            color: #cbd5e1;
            font-weight: 500;
        }

        .feature-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #1e293b;
            border: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #60a5fa;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .showcase-footer {
            padding-top: 1.75rem;
            border-top: 1px solid #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Right Side: Form */
        .auth-form-side {
            flex: 1;
            min-height: 100vh;
            padding: 3rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #ffffff;
            position: relative;
        }

        .top-back-link {
            position: absolute;
            top: 2rem;
            left: 2.5rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            transition: color 0.15s;
        }

        .top-back-link:hover {
            color: var(--primary);
        }

        .auth-form-inner {
            width: 100%;
            max-width: 420px;
        }

        .mobile-brand {
            display: none;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.75rem;
        }

        .mobile-brand img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
        }

        .mobile-brand span {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--primary);
        }

        .form-heading {
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.4px;
            margin-bottom: 0.4rem;
        }

        .form-subtitle {
            font-size: 0.92rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .custom-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .custom-alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .custom-alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .custom-alert-info {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .form-group-custom {
            margin-bottom: 1.25rem;
        }

        .input-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 1.1rem;
            pointer-events: none;
        }

        .form-control-custom {
            width: 100%;
            height: 48px;
            padding: 0 42px 0 42px;
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--text-main);
            transition: all 0.2s ease;
            outline: none;
            font-family: inherit;
        }

        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .btn-submit {
            width: 100%;
            height: 48px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        @media (max-width: 991px) {
            .auth-container {
                flex-direction: column;
            }
            .auth-showcase {
                display: none;
            }
            .mobile-brand {
                display: flex;
            }
            .auth-form-side {
                padding: 4rem 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <!-- Left Showcase Side -->
        <div class="auth-showcase">
            <div class="showcase-header">
                <a href="index.php" class="brand-badge text-decoration-none">
                    <img src="logo 40.jpeg" alt="Logo PerpusOnline" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
                    <span>PerpusOnline</span>
                </a>
            </div>

            <div class="showcase-content">
                <h1 class="showcase-title">Pusat Pustaka &amp; Literasi Digital Siswa</h1>
                <p class="showcase-desc">
                    Masuk ke akun siswa Anda untuk mengajukan peminjaman buku, memantau riwayat bacaan, dan mengakses Kartu Anggota Digital.
                </p>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon-box">
                            <i class="bi bi-person-vcard-fill"></i>
                        </div>
                        <span>Kartu Anggota Digital ber-barcode siap scan</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon-box">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <span>Pengingat jatuh tempo ramah &amp; bebas repot</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon-box">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <span>Pelacak status peminjaman bertahap real-time</span>
                    </div>
                </div>
            </div>

            <div class="showcase-footer">
                <span>&copy; <?= date('Y') ?> PerpusOnline</span>
                <a href="index.php" class="text-white-50 text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Ke Katalog Terbuka</a>
            </div>
        </div>

        <!-- Right Form Side -->
        <div class="auth-form-side">
            <a href="index.php" class="top-back-link">
                <i class="bi bi-arrow-left"></i> Kembali ke Katalog Terbuka
            </a>

            <div class="auth-form-inner">
                <!-- Mobile Brand Header -->
                <div class="mobile-brand">
                    <img src="logo 40.jpeg" alt="Logo PerpusOnline" onerror="this.src='https://ui-avatars.com/api/?name=PO&background=2563eb&color=fff'">
                    <span>PerpusOnline</span>
                </div>

                <div class="form-heading">
                    <h2 class="form-title">Selamat Datang 👋</h2>
                    <p class="form-subtitle">Silakan masukkan username dan password Anda untuk masuk.</p>
                </div>

                <!-- Alerts -->
                <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
                    <div class="custom-alert custom-alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Login Gagal!</strong> Username atau password tidak sesuai.
                        </div>
                    </div>
                <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'logout'): ?>
                    <div class="custom-alert custom-alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <strong>Berhasil Logout!</strong> Anda telah keluar dari sistem.
                        </div>
                    </div>
                <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'harus_login'): ?>
                    <div class="custom-alert custom-alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>Akses Terbatas:</strong> Silakan login terlebih dahulu untuk meminjam buku atau melihat riwayat.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="login.php" id="loginForm">
                    <div class="form-group-custom">
                        <label class="input-label" for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control-custom" 
                                   placeholder="Masukkan username..." 
                                   required 
                                   autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label class="input-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control-custom" 
                                   placeholder="Masukkan password..." 
                                   required 
                                   autocomplete="current-password">
                            <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Lihat Password">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>Masuk Sekarang</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <small class="text-muted">
                        Belum punya akun anggota? Hubungi pustakawan di ruang perpustakaan sekolah.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        toggleBtn.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>
