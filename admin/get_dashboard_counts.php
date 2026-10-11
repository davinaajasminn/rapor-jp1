<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

// Hitung data
$qSiswa = $conn->query("SELECT COUNT(*) as total FROM siswa");
$siswa = $qSiswa->fetch_assoc();

$qGuru = $conn->query("SELECT COUNT(*) as total FROM guru");
$guru = $qGuru->fetch_assoc();

$qKelas = $conn->query("SELECT COUNT(*) as total FROM kelas");
$kelas = $qKelas->fetch_assoc();

$qMapel = $conn->query("SELECT COUNT(*) as total FROM mapel");
$mapel = $qMapel->fetch_assoc();

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'siswa' => $siswa['total'],
    'guru' => $guru['total'],
    'kelas' => $kelas['total'],
    'mapel' => $mapel['total']
]);
?>