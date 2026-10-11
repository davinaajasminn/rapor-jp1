<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$mapel_kelas = $_POST['mapel_kelas'] ?? ''; // Format: "Agama (X RPL 2)"
$id_guru_skrg = $_POST['id_guru'] ?? '';

// Cari di semua guru, apakah ada yang punya string mapel ini di kolom mapel mereka
// Tapi abaikan guru yang lagi kita edit sekarang (id_guru_skrg)
$sql = "SELECT nama_guru FROM guru WHERE mapel LIKE '%$mapel_kelas%' AND id != '$id_guru_skrg'";
$q = $conn->query($sql);

if($q->num_rows > 0){
    $d = $q->fetch_assoc();
    echo $d['nama_guru']; // Balikin nama guru yang udah ambil
} else {
    echo "aman";
}
?>