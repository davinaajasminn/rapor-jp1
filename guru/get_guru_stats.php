<?php
session_start();
include '../config/Database.php';

if(!isset($_SESSION['guru']) || $_SESSION['guru']['status'] != 'login'){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$nama_guru_login = $_SESSION['guru']['nama'];
$nip_guru = $_SESSION['guru']['nip'];

$db = new Database();
$conn = $db->getConnection();

// --- LOGIKA SINKRONISASI & HITUNG PROGRES AKURAT ---
$qGuru = $conn->query("SELECT mapel FROM guru WHERE nama_guru = '$nama_guru_login'");
$dGuru = $qGuru->fetch_assoc();

$total_kelas = 0;
$total_mapel = 0;
$mapel_ids_diampu = [];
$progres = 0;

if($dGuru){
    $mapel_raw = $dGuru['mapel'];
    $list_items = array_map('trim', explode(',', $mapel_raw));

    $mapel_unik_nama = [];
    $kelas_unik = [];

    foreach($list_items as $item){
        if(preg_match('/^(.*?) \((.*?)\)$/', $item, $matches)){
            $nama_m = trim($matches[1]);
            $nama_k = trim($matches[2]);

            if(!in_array($nama_m, $mapel_unik_nama)) $mapel_unik_nama[] = $nama_m;
            if(!in_array($nama_k, $kelas_unik)) $kelas_unik[] = $nama_k;

            $qM = $conn->query("SELECT id FROM mapel WHERE nama_mapel = '$nama_m'");
            if($qM->num_rows > 0) {
                $id_m = $qM->fetch_assoc()['id'];
                $qK = $conn->query("SELECT id FROM kelas WHERE nama_kelas = '$nama_k'");
                if($qK->num_rows > 0){
                    $id_k = $qK->fetch_assoc()['id'];
                    $mapel_ids_diampu[] = ['id_m' => $id_m, 'id_k' => $id_k];
                }
            }
        }
    }
    $total_mapel = count($mapel_unik_nama);
    $total_kelas = count($kelas_unik);

    $mapel_selesai = 0;
    $total_tugas_mengajar = count($mapel_ids_diampu);

    if($total_tugas_mengajar > 0){
        foreach($mapel_ids_diampu as $tugas){
            $m_id = $tugas['id_m'];
            $k_id = $tugas['id_k'];
            $cekNilai = $conn->query("SELECT id FROM nilai WHERE id_mapel = '$m_id' AND id_kelas = '$k_id' LIMIT 1");
            if($cekNilai->num_rows > 0) $mapel_selesai++;
        }
        $progres = round(($mapel_selesai / $total_tugas_mengajar) * 100);
    }
}

echo json_encode([
    'total_kelas' => $total_kelas,
    'total_mapel' => $total_mapel,
    'progres' => $progres
]);
?>
