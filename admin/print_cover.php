<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';
$db = new Database(); $conn = $db->getConnection();
$id = $_GET['id'];
$s = $conn->query("SELECT s.nama_siswa, s.nis, i.* FROM siswa s CROSS JOIN identitas_sekolah i WHERE s.id='$id'")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cover Rapor - <?= $s['nama_siswa'] ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; text-align: center; padding: 50mm 20mm; }
        .logo { width: 120px; margin-bottom: 30px; }
        h1 { font-size: 24pt; margin-bottom: 5px; }
        .box-nama { border: 2px solid black; padding: 15px; width: 60%; margin: 40px auto; font-weight: bold; font-size: 16pt; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <h2 style="letter-spacing: 2px;">RAPOR PESERTA DIDIK<br>SEKOLAH MENENGAH KEJURUAN<br>(SMK)</h2>
    <img src="../assets/logo.png" class="logo">
    <div style="margin-top: 50px;">
        <p>Nama Peserta Didik:</p>
        <div class="box-nama"><?= strtoupper($s['nama_siswa']) ?></div>
        <p>NISN / NIS:</p>
        <div class="box-nama"><?= $s['nis'] ?></div>
    </div>
    <div style="margin-top: 100px; font-weight: bold;">
        KEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN<br>REPUBLIK INDONESIA<br>TAHUN 2025
    </div>
</body>
</html>