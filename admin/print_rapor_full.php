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
$type = $_GET['type'] ?? 'uts';

// PENTING: Cek apakah sudah divalidasi wali kelas
$cek = $conn->query("SELECT is_validated FROM nilai WHERE id_siswa='$id' LIMIT 1")->fetch_assoc();
if(!$cek || $cek['is_validated'] == 0) {
    echo "<script>alert('Akses Ditolak! Rapor ini belum divalidasi oleh Wali Kelas.'); window.close();</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Cetak Rapor Lengkap - SMK JP 1</title>
    <style>
        @media print {
            .page-break { display: block; page-break-before: always; }
            .no-print { display: none; }
        }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body onload="window.print()">
    <div class="section-print">
        <?php include 'print_cover.php'; ?>
    </div>
    
    <div class="page-break"></div>

    <div class="section-print">
        <?php include 'print_identitas.php'; ?>
    </div>

    <div class="page-break"></div>

    <div class="section-print">
        <?php include 'print_rapor.php'; ?>
    </div>
</body>
</html>