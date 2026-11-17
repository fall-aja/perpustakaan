<?php
// mengaktifkan session pada php
session_start();
// menghubungkan php dengan koneksi database
include 'koneksi.php';
// menangkap data yang dikirim dari form login
$username = $_POST['username'];
$password = $_POST['password'];

// membersihkan input dari SQL injection
$username = mysqli_real_escape_string($koneksi, $username);
$password = mysqli_real_escape_string($koneksi, $password);

// query untuk mencari user
$login = mysqli_query($koneksi, "SELECT * FROM user WHERE username='$username' AND password='$password'");
$cek = mysqli_num_rows($login);
// cek apakah username dan password ditemukan
if($cek > 0){
    $data = mysqli_fetch_assoc($login);
    
    // PENTING: Gunakan trim() untuk menghilangkan spasi
    $level_bersih = trim($data['level']);
    
    // SET SEMUA SESSION YANG DIBUTUHKAN
    $_SESSION['id_user'] = $data['id_user'];
    $_SESSION['username'] = $data['username'];
    $_SESSION['nama'] = $data['nama'];
    $_SESSION['email'] = $data['email'];
    $_SESSION['level'] = $level_bersih; // Simpan level yang sudah di-trim
    
    // cek jika user login sebagai admin
    if($level_bersih == "admin"){
        header("Location: profile_admin.php");
        exit();
    }
    // cek jika user login sebagai anggota
    else if($level_bersih == "anggota"){
        header("Location: login_anggota.php");
        exit();
    }
    else{
        header("Location: index.php?pesan=gagal");
        exit();
    }
}else{
    header("Location: index.php?pesan=gagal");
    exit();
}
?>