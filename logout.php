<?php
include 'config/Session.php';

Session::start();
Session::destroy();

// Hapus cookies
setcookie('role', '', time()-3600, '/');
setcookie('guru_nip', '', time()-3600, '/');

// Mengalihkan halaman kembali ke index.php (Login) sambil bawa pesan
header("location:index.php?pesan=logout");
?>
