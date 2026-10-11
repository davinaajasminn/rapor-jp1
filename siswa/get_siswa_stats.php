<?php
session_start();
include '../config/Database.php';

if(!isset($_SESSION['siswa']) || $_SESSION['siswa']['status'] != "login"){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id_siswa_login = $_SESSION['siswa']['id'];

$db = new Database();
$conn = $db->getConnection();

// --- TAHUN AJARAN AKTIF (biar statistik gak kecampur semester/tahun lain) ---
$qTaStats = $conn->query("SELECT id, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$dTaStats = ($qTaStats && $qTaStats->num_rows > 0) ? $qTaStats->fetch_assoc() : null;

if(!$dTaStats){
    $qTaLastStats = $conn->query("SELECT id, semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    $dTaStats = ($qTaLastStats && $qTaLastStats->num_rows > 0) ? $qTaLastStats->fetch_assoc() : ['id' => 0, 'semester' => 'Ganjil'];
}

$id_ta_stats = (int)($dTaStats['id'] ?? 0);
$id_ta_stats_safe = mysqli_real_escape_string($conn, $id_ta_stats);
$sem_stats_safe = mysqli_real_escape_string($conn, $dTaStats['semester'] ?? 'Ganjil');

// --- HITUNG RATA-RATA NILAI ---
$qRata = $conn->query("SELECT AVG((nilai_p + nilai_k)/2) as rata FROM nilai WHERE id_siswa = '$id_siswa_login' AND id_tahun_ajaran = '$id_ta_stats_safe'");
$rata_akhir = $qRata->fetch_assoc()['rata'] ?? 0;

// --- HITUNG ABSENSI ---
$qAbsen = $conn->query("SELECT alpha FROM absensi WHERE id_siswa = '$id_siswa_login' AND id_tahun_ajaran = '$id_ta_stats_safe' AND UPPER(semester) = UPPER('$sem_stats_safe')");
$dAbsen = $qAbsen->fetch_assoc();

// --- HITUNG MATA PELAJARAN ---
$qMapel = $conn->query("SELECT COUNT(DISTINCT id_mapel) as count FROM nilai WHERE id_siswa = '$id_siswa_login' AND id_tahun_ajaran = '$id_ta_stats_safe'");
$mapel_count = $qMapel->fetch_assoc()['count'] ?? 0;

echo json_encode([
    'rata' => number_format($rata_akhir, 1),
    'alpha' => $dAbsen['alpha'] ?? 0,
    'mapel_count' => $mapel_count
]);
?>