<?php
include '../../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Hitung data
$total_siswa = $conn->query("SELECT COUNT(*) as total FROM siswa")->fetch_assoc()['total'];
$total_guru = $conn->query("SELECT COUNT(*) as total FROM guru")->fetch_assoc()['total'];
$total_kelas = $conn->query("SELECT COUNT(*) as total FROM kelas")->fetch_assoc()['total'];

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'total_siswa' => $total_siswa,
    'total_guru' => $total_guru,
    'total_kelas' => $total_kelas
]);
?>
