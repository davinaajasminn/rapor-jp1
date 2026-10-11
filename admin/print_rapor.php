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

$id = $_GET['id']; 
$type = $_GET['type'] ?? 'uts';

// Query ambil data siswa, kelas, dan identitas sekolah (semester_aktif) sekaligus
$s = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan, g.nama_guru as wali, i.* FROM siswa s 
                   JOIN kelas k ON s.id_kelas=k.id 
                   LEFT JOIN guru g ON k.wali_kelas=g.nip 
                   CROSS JOIN identitas_sekolah i 
                   WHERE s.id='$id'")->fetch_assoc();

// LOGIKA HITUNG SEMESTER OTOMATIS
$tingkat = $s['tingkat']; // 10, 11, atau 12
$status_smt = $s['semester_aktif']; // Ganjil atau Genap

if ($status_smt == 'Genap') {
    $semester_ke = ($tingkat - 10) * 2 + 2;
} else {
    $semester_ke = ($tingkat - 10) * 2 + 1;
}

function getNilai($id_s, $id_m, $conn) {
    $q = $conn->query("SELECT * FROM nilai WHERE id_siswa='$id_s' AND id_mapel='$id_m'");
    return ($q->num_rows > 0) ? $q->fetch_assoc() : ['nilai_t'=>0,'nilai_p'=>0,'nilai_k'=>0,'nilai_uas'=>0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor <?= strtoupper($type) ?> - <?= $s['nama_siswa'] ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 10mm 15mm; font-size: 11pt; color: #000; }
        .header { text-align: center; margin-bottom: 20px; line-height: 1.2; }
        .table-info { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .table-info td { padding: 3px 0; vertical-align: top; }
        .table-nilai { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-nilai th, .table-nilai td { border: 1px solid black; padding: 8px; text-align: center; }
        .text-left { text-align: left !important; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="background:#fff3cd; padding:10px; margin-bottom:20px; border:1px solid #ffeeba; border-radius:8px;">
        <button onclick="window.print()" style="padding:8px 20px; cursor:pointer;">Cetak Sekarang</button>
        <small style="margin-left:10px;">* Semester otomatis terhitung: <b><?= $semester_ke ?> (<?= $status_smt ?>)</b></small>
    </div>

    <div class="header">
        <h3 style="margin:0">LAPORAN HASIL BELAJAR PESERTA DIDIK (RAPOR)</h3>
        <h4 style="margin:5px 0"><?= strtoupper($type) ?> - <?= $s['tahun_pelajaran'] ?></h4>
    </div>

    <table class="table-info">
        <tr>
            <td style="width: 15%;">Nama Siswa</td><td style="width: 2%;">:</td><td style="width: 40%; font-weight:bold;"><?= $s['nama_siswa'] ?></td>
            <td style="width: 15%;">Kelas</td><td style="width: 2%;">:</td><td style="width: 26%;"><?= $s['nama_kelas'] ?></td>
        </tr>
        <tr>
            <td>NIS / NISN</td><td>:</td><td><?= $s['nis'] ?> / <?= $s['nisn'] ?? '-' ?></td>
            <td>Semester</td><td>:</td><td><?= $semester_ke ?> (<?= $status_smt ?>)</td>
        </tr>
        <tr>
            <td>Sekolah</td><td>:</td><td><?= $s['nama_sekolah'] ?></td>
            <td>Fase</td><td>:</td><td><?= ($tingkat == 10) ? 'E' : 'F' ?></td>
        </tr>
    </table>

    <table class="table-nilai">
        <thead>
            <tr style="background: #f2f2f2;">
                <th rowspan="2" style="width:5%">No</th>
                <th rowspan="2" style="width:40%">Mata Pelajaran</th>
                <?php if($type == 'uas'): ?>
                    <th colspan="4">Nilai</th>
                <?php endif; ?>
                <th rowspan="2">Akhir</th>
                <th rowspan="2">Predikat</th>
            </tr>
            <?php if($type == 'uas'): ?>
            <tr style="background: #f2f2f2;">
                <th>T</th><th>P</th><th>K</th><th>UAS</th>
            </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php 
            $kelompok = ['A' => 'Muatan Nasional', 'B' => 'Muatan Kewilayahan', 'C' => 'Muatan Peminatan Kejuruan'];
            foreach($kelompok as $key => $val): 
            ?>
                <tr style="background:#f9f9f9;"><td colspan="<?= ($type == 'uas') ? 8 : 4 ?>" class="text-left"><b>Kelompok <?= $key ?>. <?= $val ?></b></td></tr>
                <?php 
                $no = 1;
                $mq = $conn->query("SELECT * FROM mapel WHERE kelompok='$key' AND kode_jurusan='".$s['kode_jurusan']."'"); 
                while($m = $mq->fetch_assoc()): 
                    $n = getNilai($id, $m['id'], $conn);
                    
                    if($type == 'uas') {
                        $na = round(($n['nilai_t'] + $n['nilai_p'] + $n['nilai_k'] + $n['nilai_uas']) / 4);
                    } else {
                        $na = round(($n['nilai_t'] + $n['nilai_p'] + $n['nilai_k']) / 3);
                    }
                    
                    $pred = ($na >= 90 ? 'A' : ($na >= 80 ? 'B' : ($na >= 70 ? 'C' : 'D')));
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-left"><?= $m['nama_mapel'] ?></td>
                    <?php if($type == 'uas'): ?>
                        <td><?= $n['nilai_t'] ?></td>
                        <td><?= $n['nilai_p'] ?></td>
                        <td><?= $n['nilai_k'] ?></td>
                        <td><?= $n['nilai_uas'] ?></td>
                    <?php endif; ?>
                    <td><b><?= $na ?: '0' ?></b></td>
                    <td><?= ($na == 0 ? '-' : $pred) ?></td>
                </tr>
            <?php endwhile; endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top:40px; display:flex; justify-content:space-between">
        <div style="text-align:center; width:220px">
            Mengetahui,<br>Orang Tua/Wali<br><br><br><br>
            ( ............................ )
        </div>
        <div style="text-align:center; width:220px">
            Jakarta, <?= date('d F Y') ?><br>Wali Kelas<br><br><br><br>
            <b><?= $s['wali'] ?? '( Belum Diatur )' ?></b>
        </div>
    </div>
    <div style="text-align:center; margin-top:20px;">
        Mengetahui,<br>Kepala Sekolah<br><br><br><br>
        <b><?= $s['nama_kepsek'] ?></b><br>
        NIP. <?= $s['nip_kepsek'] ?>
    </div>
</body>
</html>