<?php
session_start();
// Jika sudah login, redirect sesuai level
if(isset($_SESSION['level'])){
if($_SESSION['level'] == 'admin'){
header("Location: profile_admin.php");
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
<title>Login - Perpustakaan Digital</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
body {
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
min-height: 100vh;
display: flex;
align-items: center;
justify-content: center;
}
.login-card {
background: #1e293b;
border: none;
border-radius: 20px;
box-shadow: 0 15px 50px rgba(0,0,0,0.3);
padding: 2.5rem;
max-width: 400px;
width: 100%;
}
.form-control {
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.1);
color: #fff;
}
.form-control:focus {
background: rgba(255,255,255,0.1);
border-color: #667eea;
color: #fff;
}
.btn-login {
background: linear-gradient(135deg, #667eea, #764ba2);
border: none;
width: 100%;
padding: 0.75rem;
font-weight: 600;
}
/* Password toggle button */
.password-toggle {
background: transparent;
border: none;
color: rgba(255,255,255,0.5);
cursor: pointer;
padding: 0.375rem 0.75rem;
transition: all 0.2s;
}
.password-toggle:hover {
color: #fff;
}
.password-toggle:focus {
outline: none;
color: #667eea;
}
</style>
</head>
<body>
<div class="login-card">
<div class="text-center mb-4">
<i class="bi bi-book text-primary" style="font-size: 3rem;"></i>
<h3 class="text-white mt-3">Login Perpustakaan</h3>
<p class="text-muted">Silakan masuk ke akun Anda</p>
</div>
<?php
if(isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'){
echo '<div class="alert alert-danger alert-dismissible fade show">
<i class="bi bi-exclamation-circle"></i> Username dan Password tidak sesuai!
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>';
}
?>
<form method="POST" action="login.php">
<div class="mb-3">
<label class="text-white">Username</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person"></i></span>
<input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
</div>
</div>
<div class="mb-4">
<label class="text-white">Password</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-lock"></i></span>
<input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
<button class="password-toggle" type="button" id="togglePassword" tabindex="-1">
<i class="bi bi-eye" id="toggleIcon"></i>
</button>
</div>
</div>
<button type="submit" class="btn btn-primary btn-login">
<i class="bi bi-box-arrow-in-right me-2"></i>Login
</button>
</form>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
const togglePassword = document.querySelector('#togglePassword');
const passwordInput = document.querySelector('#password');
const toggleIcon = document.querySelector('#toggleIcon');

togglePassword.addEventListener('click', function() {
// Toggle type attribute
const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
passwordInput.setAttribute('type', type);

// Toggle icon
if (type === 'text') {
toggleIcon.classList.remove('bi-eye');
toggleIcon.classList.add('bi-eye-slash');
} else {
toggleIcon.classList.remove('bi-eye-slash');
toggleIcon.classList.add('bi-eye');
}
});
</script>
</body>
</html>