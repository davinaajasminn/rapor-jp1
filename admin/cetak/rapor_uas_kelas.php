<?php
include '../../config/Database.php'; 
$db = new Database(); 
$conn = $db->getConnection();

// FIX: resolusi tahun ajaran yang dipakai untuk cetak massal.
// Kalau admin memilih tahun ajaran/semester tertentu lewat parameter ?id_tahun_ajaran=,
// pakai itu (buat lihat rapor tahun-tahun lalu). Kalau tidak dipilih, fallback ke yang Aktif.
function resolve_tahun_ajaran_cetak($conn, $id_ta_get){
    $id_ta_get = trim((string)$id_ta_get);
    if($id_ta_get !== '' && ctype_digit($id_ta_get)){
        $id_ta_safe = mysqli_real_escape_string($conn, $id_ta_get);
        $q = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE id='$id_ta_safe' LIMIT 1");
        if($q && $q->num_rows > 0) return $q->fetch_assoc();
    }

    $q = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
    if($q && $q->num_rows > 0) return $q->fetch_assoc();

    $q = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    if($q && $q->num_rows > 0) return $q->fetch_assoc();

    return ['id' => 0, 'tahun' => date('Y').'/'.(date('Y') + 1), 'semester' => 'Ganjil'];
}

$ta_terpilih = resolve_tahun_ajaran_cetak($conn, $_GET['id_tahun_ajaran'] ?? '');
$id_tahun_dipilih = (int)($ta_terpilih['id'] ?? 0);
$id_tahun_dipilih_safe = mysqli_real_escape_string($conn, $id_tahun_dipilih);

function tanggal_indo($tanggal = null) {
    $bulan_indo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $timestamp = $tanggal ? strtotime($tanggal) : time();
    return date('d', $timestamp) . ' ' . $bulan_indo[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}


// Tangkap ID Kelas dari URL
$id_kelas = $_GET['id_kelas'] ?? ''; 

if(empty($id_kelas)){ 
    echo "<h2 style='text-align:center; margin-top:50px;'>Error: Kelas belum dipilih!</h2>"; 
    exit; 
}

// --- AMBIL INFO KELAS ---
$qKlsInfo = $conn->query("SELECT nama_kelas FROM kelas WHERE id = '$id_kelas'");
$nama_kelas_info = ($qKlsInfo->num_rows > 0) ? $qKlsInfo->fetch_assoc()['nama_kelas'] : 'Kelas';
$kelas_nama_file = str_replace(' ', '_', $nama_kelas_info);

// --- LOGIKA FILTER SISWA YANG SUDAH VALID SAJA (SINKRON DENGAN CETAK PERSONAL) ---
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

$qKlsFull = $conn->query("SELECT * FROM kelas WHERE id = '$id_kelas_safe' LIMIT 1");
$dKlsFull = ($qKlsFull && $qKlsFull->num_rows > 0) ? $qKlsFull->fetch_assoc() : [];

$tingkat_romawi = $dKlsFull['tingkat'] ?? explode(' ', trim($nama_kelas_info))[0];
$jurusan_key = $dKlsFull['kode_jurusan'] ?? '';

if(empty($jurusan_key)){
    if(stripos($nama_kelas_info, 'RPL') !== false) $jurusan_key = 'RPL';
    elseif(stripos($nama_kelas_info, 'AKL') !== false) $jurusan_key = 'AKL';
    elseif(stripos($nama_kelas_info, 'AKT') !== false) $jurusan_key = 'AKT';
    elseif(stripos($nama_kelas_info, 'BR') !== false) $jurusan_key = 'BR';
    elseif(stripos($nama_kelas_info, 'MP') !== false) $jurusan_key = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_key);

$qCekGM = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                        FROM guru_mengajar gm
                        JOIN mapel m ON gm.id_mapel = m.id
                        WHERE gm.id_kelas = '$id_kelas_safe'
                        AND m.tingkat = '$tingkat_safe'
                        AND m.nama_mapel NOT LIKE '%PKL%'
                        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                        AND m.nama_mapel NOT LIKE '%Prakerin%'");
$jml_gm = ($qCekGM && $qCekGM->num_rows > 0) ? (int)$qCekGM->fetch_assoc()['total'] : 0;
$pakai_guru_mengajar_massal = ($jml_gm > 0);

if($pakai_guru_mengajar_massal){
    $qWajib = $conn->query("SELECT COUNT(DISTINCT m.id) AS tot
                            FROM guru_mengajar gm
                            JOIN mapel m ON gm.id_mapel = m.id
                            WHERE gm.id_kelas = '$id_kelas_safe'
                            AND m.tingkat = '$tingkat_safe'
                            AND m.nama_mapel NOT LIKE '%PKL%'
                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND m.nama_mapel NOT LIKE '%Prakerin%'");
} else {
    $qWajib = $conn->query("SELECT COUNT(DISTINCT id) AS tot
                            FROM mapel
                            WHERE tingkat = '$tingkat_safe'
                            AND nama_mapel NOT LIKE '%PKL%'
                            AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                kelompok = 'A'
                                OR kode_jurusan = '$jurusan_safe'
                            )");
}

$jml_wajib = $qWajib ? ((int)($qWajib->fetch_assoc()['tot'] ?? 0)) : 0;

$siswa_ready_list = [];
$siswa_pending_list = [];

// FIX: ambil siswa berdasarkan KELAS HISTORIS (riwayat_kelas) sesuai tahun ajaran yang dipilih,
// bukan kelas siswa SEKARANG (siswa.id_kelas). Kalau id kelas ini dulu ditempati siswa lain
// (sebelum ada Kenaikan Kelas), yang harus muncul di rapor tahun ajaran lama adalah siswa yang
// LAMA itu, bukan siswa yang sekarang menempati id kelas yang sama.
$qSiswaList = $conn->query("SELECT s.id, s.nama_siswa
                            FROM (
                                SELECT id_siswa, MAX(id) AS max_id
                                FROM riwayat_kelas
                                WHERE id_tahun_ajaran = '$id_tahun_dipilih_safe' AND id_kelas = '$id_kelas_safe' AND id_siswa != 0
                                GROUP BY id_siswa
                            ) rk
                            INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                            INNER JOIN siswa s ON r.id_siswa = s.id
                            ORDER BY s.nama_siswa ASC");

// FALLBACK: kalau riwayat_kelas belum ada data utk tahun ajaran ini, pakai penempatan kelas
// siswa saat ini supaya halaman tidak kosong.
if(!$qSiswaList || $qSiswaList->num_rows == 0){
    $qSiswaList = $conn->query("SELECT id, nama_siswa FROM siswa WHERE id_kelas = '$id_kelas_safe' ORDER BY nama_siswa ASC");
}

if($qSiswaList->num_rows == 0){
    echo "<h2 style='text-align:center; margin-top:50px;'>Tidak ada siswa di kelas ini!</h2>"; 
    exit; 
}

// Pisahkan mana siswa yang laporannya sudah valid dan mana yang masih pending.
// UTS wajib TP + STS. UAS wajib TP + STS + SAS.
while($rowS = $qSiswaList->fetch_assoc()){
    $id_s = (int)$rowS['id'];

    if($pakai_guru_mengajar_massal){
        $qReady = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS tot
                                FROM nilai n
                                JOIN mapel m ON n.id_mapel = m.id
                                JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'
                                WHERE n.id_siswa = '$id_s'
                                AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                AND n.status_validasi = 1
                                AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
            AND n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0
                                AND m.tingkat = '$tingkat_safe'
                                AND m.nama_mapel NOT LIKE '%PKL%'
                                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND m.nama_mapel NOT LIKE '%Prakerin%'");
    } else {
        $qReady = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS tot
                                FROM nilai n
                                JOIN mapel m ON n.id_mapel = m.id
                                WHERE n.id_siswa = '$id_s'
                                AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                AND n.status_validasi = 1
                                AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
            AND n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0
                                AND m.tingkat = '$tingkat_safe'
                                AND m.nama_mapel NOT LIKE '%PKL%'
                                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND m.nama_mapel NOT LIKE '%Prakerin%'
                                AND (
                                    m.kelompok = 'A'
                                    OR m.kode_jurusan = '$jurusan_safe'
                                )");
    }

    $jml_ready = $qReady ? ((int)($qReady->fetch_assoc()['tot'] ?? 0)) : 0;

    if($jml_ready >= $jml_wajib && $jml_wajib > 0){
        $siswa_ready_list[] = $id_s;
    } else {
        $siswa_pending_list[] = $rowS['nama_siswa'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal Rapor UAS</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        body { font-family: 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; background: #525659; }
        .page { width: 210mm; min-height: 296mm; padding: 20mm; margin: 0 auto; box-sizing: border-box; background: white; }
        .page-break { page-break-after: always; }
        .student-rapor { margin-bottom: 30px; page-break-after: always; background: white; }
        
        .cover-title { text-align: center; font-weight: bold; font-size: 14pt; margin-top: 50px; line-height: 1.5; }
        .cover-logo { text-align: center; margin: 60px 0; }
        .cover-logo img { width: 150px; }
        .cover-box { text-align: center; margin-bottom: 20px; }
        .cover-box p { font-weight: bold; margin-bottom: 5px; font-size: 11pt; }
        .cover-box .box { border: 2px solid #000; padding: 10px; width: 60%; margin: 0 auto; font-weight: bold; font-size: 12pt; text-transform: uppercase; }
        .cover-footer { text-align: center; font-weight: bold; font-size: 12pt; margin-top: 100px; line-height: 1.5; }
        .identitas-box { border: 2px solid #000; padding: 30px; height: 90%; }
        .identitas-title { text-align: center; font-weight: bold; font-size: 12pt; margin-bottom: 40px; }
        .identitas-table { width: 100%; font-size: 11pt; line-height: 2; margin-left: 20px; }
        .identitas-table td { vertical-align: top; }
        .header-rapor { display: flex; justify-content: space-between; font-size: 10pt; margin-bottom: 10px; }
        .header-rapor table { width: 100%; }
        .table-nilai { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9.5pt; }
        .table-nilai th, .table-nilai td { border: 1px solid black; padding: 5px; }
        .table-nilai th { background: #e0e0e0; text-align: center; }
        .td-center { text-align: center; }
        .td-cp { text-align: justify; font-size: 9pt; }
        .kelompok-title { background: #f2f2f2; font-weight: bold; }
        .table-bawah { width: 50%; border-collapse: collapse; margin-top: 15px; font-size: 10pt;}
        .table-bawah th, .table-bawah td { border: 1px solid black; padding: 5px; }
        .ttd-area { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; }
        .ttd-box { width: 220px; text-align: center; }
        .ttd-img-digital { width: 150px; height: 65px; object-fit: contain; display: block; margin: 6px auto 2px auto; }
        
        @media print {
            body { background: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; padding:15px; background:#e3f2fd; margin-bottom:10px; position: sticky; top: 0; z-index: 999; border-bottom: 2px solid #b6d4fe;">
        
        <?php if(count($siswa_ready_list) == 0): ?>
            <div style="background: #f8d7da; color: #842029; padding: 15px; border-radius: 8px; max-width: 600px; margin: 0 auto;">
                <h3 style="margin: 0 0 10px 0;">⚠️ Akses Cetak Ditutup!</h3>
                <p style="margin: 0;">Belum ada satupun rapor siswa yang <b>Valid (Selesai dinilai & disetujui)</b> di kelas ini.</p>
                <button onclick="window.close()" style="margin-top: 10px; padding: 8px 15px; cursor: pointer;">Tutup Halaman</button>
            </div>
        <?php else: ?>
            <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-right: 10px;">
                🖨️ Cetak Biasa (<?= count($siswa_ready_list) ?> Siswa)
            </button>
            
            <button id="btn-zip" onclick="downloadZip()" style="padding:10px 20px; font-weight:bold; cursor:pointer; background: #198754; color: white; border: none; border-radius: 5px;">
                📦 Download ZIP Rapor (<?= count($siswa_ready_list) ?> Siswa)
            </button>
            
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;">Sistem hanya akan mencetak rapor siswa yang statusnya sudah <b>Ready/Valid</b>.</p>
            
            <?php if(count($siswa_pending_list) > 0): ?>
                <p style="color: #dc3545; font-weight: bold; font-size: 12px; margin-top: 5px;">⚠️ Ada <?= count($siswa_pending_list) ?> siswa yang dilewati otomatis karena nilai UAS/SAS belum diinput lengkap atau belum divalidasi.</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <?php 
    // =========================================================================
    // LOOPING UTAMA: HANYA MERENDER SISWA YANG "READY" SAJA
    // =========================================================================
    foreach($siswa_ready_list as $id_siswa): 

        $qS = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan, g.nama_guru as wali, g.ttd_digital as ttd_wali, ks.ttd_digital as ttd_kepsek, i.* FROM siswa s 
                            JOIN kelas k ON k.id='$id_kelas_safe' 
                            LEFT JOIN guru g ON k.id_wali_kelas=g.id
                            CROSS JOIN identitas_sekolah i
                            LEFT JOIN kepala_sekolah ks ON (ks.nama_lengkap = i.nama_kepsek OR ks.nip = i.nip_kepsek) 
                            WHERE s.id='$id_siswa'");
        $s = $qS->fetch_assoc();

        // --- MESIN EKSTRAK JURUSAN SISWA ---
        // Disamakan dengan rapor personal supaya CP dan filter mapel tidak nyasar ke jurusan lain.
        $kelas_siswa_aktif = trim($s['nama_kelas']);
        $jur_siswa = preg_replace('/^(?:X|XI|XII)\s+/', '', $kelas_siswa_aktif); 
        $jur_siswa = preg_replace('/\s+\d+$/', '', $jur_siswa); 
        $jur_siswa = trim($jur_siswa);

        // FIX: label tahun pelajaran & semester sekarang ikut tahun ajaran yang dipilih/aktif
        // (dulu selalu ambil dari identitas_sekolah, jadi gak nyambung kalau admin lagi cetak rapor tahun lalu).
        $thn_pelajaran_aktif = !empty($ta_terpilih['tahun']) ? $ta_terpilih['tahun'] : (!empty($s['tahun_pelajaran']) ? $s['tahun_pelajaran'] : (date('Y') . '/' . (date('Y') + 1)));
        $thn_cover = explode('/', $thn_pelajaran_aktif)[0];

        $status_smt = !empty($ta_terpilih['semester']) ? $ta_terpilih['semester'] : ($s['semester_aktif'] ?? 'Ganjil');
        if ($s['tingkat'] == 'XII' || $tingkat_romawi == 'XII') { $semester_ke = ($status_smt == 'Genap') ? 6 : 5; } 
        elseif ($s['tingkat'] == 'XI' || $tingkat_romawi == 'XI') { $semester_ke = ($status_smt == 'Genap') ? 4 : 3; } 
        else { $semester_ke = ($status_smt == 'Genap') ? 2 : 1; }

        // Filter Mapel disamakan dengan rapor personal
        $jurusan_lawan_siswa = ($jur_siswa == 'RPL') ? 'AKT' : (($jur_siswa == 'AKT') ? 'RPL' : 'XXXX');

        if($pakai_guru_mengajar_massal){
            $qMapel = $conn->query("SELECT DISTINCT m.* FROM guru_mengajar gm
                                    JOIN mapel m ON gm.id_mapel = m.id
                                    WHERE gm.id_kelas = '$id_kelas_safe'
                                    AND m.tingkat = '$tingkat_safe'
                                    AND m.nama_mapel NOT LIKE '%PKL%'
                                    AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                    AND m.nama_mapel NOT LIKE '%Prakerin%'
                                    ORDER BY
                                        CASE 
                                            WHEN m.kelompok = 'A' THEN 1
                                            WHEN m.kelompok = 'B' THEN 2
                                            WHEN m.kelompok = 'C1' THEN 3
                                            WHEN m.kelompok = 'C2' THEN 4
                                            WHEN m.kelompok = 'C3' THEN 5
                                            ELSE 6
                                        END ASC,
                                        m.urutan ASC,
                                        m.id ASC");
        } else {
            $qMapel = $conn->query("SELECT * FROM mapel
                                    WHERE tingkat = '$tingkat_safe'
                                    AND nama_mapel NOT LIKE '%PKL%'
                                    AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                    AND nama_mapel NOT LIKE '%Prakerin%'
                                    AND (
                                        kelompok = 'A'
                                        OR kode_jurusan = '$jurusan_safe'
                                    )
                                    ORDER BY
                                        CASE 
                                            WHEN kelompok = 'A' THEN 1
                                            WHEN kelompok = 'B' THEN 2
                                            WHEN kelompok = 'C1' THEN 3
                                            WHEN kelompok = 'C2' THEN 4
                                            WHEN kelompok = 'C3' THEN 5
                                            ELSE 6
                                        END ASC,
                                        urutan ASC,
                                        id ASC");
        }

        // Tahun ajaran yang dipakai untuk Absensi & Ekskul = tahun ajaran yang dipilih/aktif di atas
        // (dulu selalu ambil status Aktif, sekarang ikut tahun ajaran yang lagi dicetak biar gak ketuker).
        $id_tahun_aktif_ekskul = $id_tahun_dipilih;
        $id_tahun_aktif_ekskul_safe = $id_tahun_dipilih_safe;
        $status_smt_safe = mysqli_real_escape_string($conn, $status_smt);

        // FIX: sama seperti rapor personal, absensi dulu diambil tanpa filter semester/tahun ajaran
        // sehingga bisa ke-ambil baris siswa dari tahun ajaran/semester lain. Sekarang difilter.
        $qAbsen = $conn->query("SELECT * FROM absensi
                                WHERE id_siswa = '$id_siswa'
                                AND semester = '$status_smt_safe'
                                AND id_tahun_ajaran = '$id_tahun_aktif_ekskul_safe'
                                LIMIT 1")->fetch_assoc();

        $qEkskul = $conn->query("SELECT ne.*, m1.nama_ekskul as nama_eks1, m2.nama_ekskul as nama_eks2 
                                FROM nilai_ekskul ne 
                                LEFT JOIN master_ekskul m1 ON ne.ekskul_1 = m1.id 
                                LEFT JOIN master_ekskul m2 ON ne.ekskul_2 = m2.id 
                                WHERE ne.id_siswa = '$id_siswa'
                                AND ne.id_tahun_ajaran = '$id_tahun_aktif_ekskul_safe'
                                LIMIT 1");
        $dataEkskul = $qEkskul->fetch_assoc();
    ?>

    <div class="student-rapor" data-nis="<?= $s['nis'] ?>" data-nama="<?= htmlspecialchars($s['nama_siswa']) ?>">
        
        <div class="page page-break">
            <div class="cover-title">
                RAPOR PESERTA DIDIK<br>
                AKHIR SEMESTER <?= strtoupper($status_smt) ?><br>
                TAHUN PELAJARAN <?= $thn_pelajaran_aktif ?><br>
                <?= strtoupper($s['nama_sekolah']) ?>
            </div>
            
            <div class="cover-logo"><img src="../../assets/logo.png" alt="Logo Sekolah" style="width: 180px;"></div>
            <div class="cover-box"><p>Nama Siswa</p><div class="box"><?= $s['nama_siswa'] ?></div></div>
            <div class="cover-box"><p>NISN</p><div class="box"><?= $s['nisn'] ?: $s['nis'] ?></div></div>
            <div class="cover-footer">KEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN<br>REPUBLIK INDONESIA<br>TAHUN <?= $thn_cover ?></div>
        </div>

        <div class="page page-break">
            <div class="identitas-box">
                <div class="identitas-title">RAPOR PESERTA DIDIK<br>SEKOLAH MENENGAH KEJURUAN<br><?= strtoupper($s['nama_sekolah']) ?></div>
                <table class="identitas-table">
                    <tr><td width="30%">Nama Sekolah</td><td width="5%">:</td><td><?= $s['nama_sekolah'] ?></td></tr>
                    <tr><td>NPSN</td><td>:</td><td><?= $s['npsn'] ?></td></tr>
                    <tr><td>NIS/NSS/NDS</td><td>:</td><td>12191 / 0084378740</td></tr>
                    <tr><td valign="top">Alamat Sekolah</td><td valign="top">:</td><td><?= $s['alamat_sekolah'] ?></td></tr>
                    <tr><td>Tlp</td><td>:</td><td><?= $s['telepon'] ?></td></tr>
                    <tr><td>Kelurahan</td><td>:</td><td><?= $s['kelurahan'] ?></td></tr>
                    <tr><td>Kecamatan</td><td>:</td><td><?= $s['kecamatan'] ?></td></tr>
                    <tr><td>Kota/Kabupaten</td><td>:</td><td><?= $s['kota'] ?></td></tr>
                    <tr><td>Provinsi</td><td>:</td><td><?= $s['provinsi'] ?></td></tr>
                    <tr><td>Website</td><td>:</td><td><?= $s['website'] ?: '-' ?></td></tr>
                    <tr><td>e-mail</td><td>:</td><td><?= $s['email'] ?: '-' ?></td></tr>
                </table>
            </div>
        </div>

        <div class="page page-break">
            <div class="header-rapor">
                <div style="width: 100%;">
                    <table border="0">
                        <tr><td width="150">Nama Peserta Didik</td><td>: <?= strtoupper($s['nama_siswa']) ?></td></tr>
                        <tr><td>NISN / NIS</td><td>: <?= $s['nisn'] ?> / <?= $s['nis'] ?></td></tr>
                        <tr><td>Kelas</td><td>: <?= $s['nama_kelas'] ?></td></tr>
                        <tr><td>Semester</td><td>: <?= $semester_ke ?> (<?= strtoupper($status_smt) ?>)</td></tr>
                    </table>
                </div>
            </div>

            <b>A. Nilai Akademik</b>
            <table class="table-nilai">
                <thead>
                    <tr><th width="5%">No</th><th width="25%">Mata Pelajaran</th><th width="10%">Nilai Akhir</th><th width="60%">Capaian Kompetensi</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $kategori_list = ['A' => 'Kelompok Mata Pelajaran Umum', 'B' => 'Kelompok Mata Pelajaran Kejuruan', 'C1'=>'', 'C2'=>'', 'C3'=>''];
                    $current_kat = "";
                    $no = 1;
                    $mapel_remedial = [];

                    while($m = $qMapel->fetch_assoc()):
                        $kkm_mapel = (isset($m['kkm']) && $m['kkm'] > 0) ? $m['kkm'] : 75;
                        $id_m = $m['id'];
                        $kat = $m['kelompok'];
                        
                        if($kat != $current_kat && in_array($kat, ['A', 'B'])){
                            echo "<tr class='kelompok-title'><td colspan='4'>$kat. ".$kategori_list[$kat]."</td></tr>";
                            $current_kat = $kat;
                        }

                        // --- NAMA MAPEL BERSIH ---
                        // Hapus label kelas/jurusan di dalam kurung supaya CP tidak salah nyebut mapel.
                        $base_name = trim(preg_replace('/\s+/', ' ', preg_replace('/\s*\([^)]*\)/', '', $m['nama_mapel'])));
                        $qN = $conn->query("SELECT * FROM nilai WHERE id_siswa='$id_siswa' AND id_mapel='$id_m' AND id_tahun_ajaran='$id_tahun_dipilih_safe' LIMIT 1");
                        $dn = $qN->fetch_assoc();
                        
                        if($dn){
                            $tp = $dn['nilai_t']; $sts = $dn['nilai_p']; $sas = $dn['nilai_k'];
                            $na = round(((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10); 
                            
                            if($na < $kkm_mapel) { $mapel_remedial[] = $base_name; }

                            $cp = $dn['deskripsi_cp'];

                            // --- AUTO KOREKSI CP ---
                            // Kalau CP dari guru masih kebawa teks "Dasar-dasar Kejuruan RPL",
                            // otomatis diganti sesuai mapel yang sedang dicetak.
                            $cp = str_ireplace("materi Dasar-dasar Kejuruan RPL", $base_name, $cp);
                            $cp = str_ireplace("Dasar-dasar Kejuruan RPL", $base_name, $cp);

                            if(!empty($cp)) {
                                $kalimat_arr = array_map('trim', explode('.', $cp));
                                $kalimat_arr = array_filter($kalimat_arr);
                                $kalimat_unik = array_unique($kalimat_arr);
                                $cp = implode('. ', $kalimat_unik) . (!empty($kalimat_unik) ? '.' : '');
                            }

                            if(empty($cp)){
                                $qTemplate = $conn->query("SELECT * FROM setting_pernyataan WHERE nama_mapel_dasar = '$base_name' AND kode_jurusan = '$jur_siswa' LIMIT 1");
                                if($qTemplate && $qTemplate->num_rows > 0){
                                    $tpl = $qTemplate->fetch_assoc();
                                    if($na >= 90) $cp = $tpl['template_a'];
                                    elseif($na >= 80) $cp = $tpl['template_b'];
                                    elseif($na >= $kkm_mapel) $cp = $tpl['template_c'];
                                    else $cp = $tpl['template_d'];
                                } else {
                                    if($na >= 90) $cp = "Menunjukkan penguasaan sangat baik dalam ".$base_name.".";
                                    elseif($na >= 80) $cp = "Menunjukkan penguasaan baik dalam ".$base_name.".";
                                    elseif($na >= $kkm_mapel) $cp = "Menunjukkan penguasaan cukup baik dalam ".$base_name.".";
                                    else $cp = "Memerlukan bimbingan lebih lanjut dalam ".$base_name.".";
                                }
                            }
                        } else {
                            $na = "-"; $cp = "-";
                        }
                    ?>
                    <tr>
                        <td class="td-center"><?= $no++ ?></td>
                        <td><?= $base_name ?></td>
                        <td class="td-center" style="font-size: 11pt; font-weight:bold;"><?= $na ?></td>
                        <td class="td-cp"><?= $cp ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="page">
            <b>B. Catatan Akademik</b>
            <div style="border: 1px solid #000; padding: 10px; margin-bottom: 15px; min-height: 40px; text-align: justify;">
                <?php 
                $smt_aktif = strtolower($status_smt);
                if (count($mapel_remedial) > 0) {
                    $gabungan_mapel = implode(", ", $mapel_remedial);
                    $catatan_otomatis = "Peserta didik perlu melakukan perbaikan mata pelajaran $gabungan_mapel, sebagai bekal pembelajaran sampai akhir semester $smt_aktif.";
                } else {
                    $catatan_otomatis = "Selamat peserta didik telah menuntaskan seluruh mata pelajaran, pertahankan prestasi sampai akhir semester.";
                }
                echo !empty($qAbsen['catatan']) ? $qAbsen['catatan'] : $catatan_otomatis; 
                ?>
            </div>

            <b>C. Praktik Kerja Lapangan</b>
            <table class="table-nilai" style="margin-bottom: 15px;">
                <tr><th width="5%">No</th><th width="40%">Mitra DU/DI</th><th width="30%">Lokasi</th><th width="25%">Lamanya (Bulan)</th></tr>
                <tr><td class="td-center">1</td><td></td><td></td><td></td></tr>
            </table>

            <b>D. Ekstra Kurikuler</b>
            <table class="table-nilai" style="margin-bottom: 15px;">
                <tr><th width="5%">No</th><th width="45%">Kegiatan Ekstra Kurikuler</th><th width="50%">Keterangan</th></tr>
                <?php
                $nex = 1;
                $has_ekskul = false;
                if ($dataEkskul && !empty($dataEkskul['nama_eks1'])) {
                    echo "<tr><td class='td-center'>".$nex++."</td><td style='padding-left: 8px;'>".$dataEkskul['nama_eks1']."</td><td style='padding-left: 8px;'>".$dataEkskul['nilai_1']."</td></tr>";
                    $has_ekskul = true;
                }
                if ($dataEkskul && !empty($dataEkskul['nama_eks2'])) {
                    echo "<tr><td class='td-center'>".$nex++."</td><td style='padding-left: 8px;'>".$dataEkskul['nama_eks2']."</td><td style='padding-left: 8px;'>".$dataEkskul['nilai_2']."</td></tr>";
                    $has_ekskul = true;
                }
                if (!$has_ekskul) {
                    echo "<tr><td class='td-center'>-</td><td class='td-center'>-</td><td class='td-center'>-</td></tr>";
                }
                ?>
            </table>

            <b>E. Ketidakhadiran</b>
            <table class="table-bawah">
                <tr><td width="60%">Sakit</td><td width="20%" class="td-center"><?= $qAbsen['sakit'] ?? 0 ?></td><td width="20%" class="td-center">Hari</td></tr>
                <tr><td>Ijin</td><td class="td-center"><?= $qAbsen['izin'] ?? 0 ?></td><td class="td-center">Hari</td></tr>
                <tr><td>Tanpa Keterangan</td><td class="td-center"><?= $qAbsen['alpha'] ?? 0 ?></td><td class="td-center">Hari</td></tr>
            </table>

            <?php $tgl_cetak = tanggal_indo(); ?>

            <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                <div class="ttd-box">
                    Mengetahui,<br>Orang tua/wali
                    <br><br><br><br><br><br>
                    _________________________
                </div>

                <div class="ttd-box">
                    <?= $s['kota'] ?>, <?= $tgl_cetak ?><br>Wali Kelas
                    <?php if(!empty($s['ttd_wali'])): ?>
                        <img src="<?= $s['ttd_wali'] ?>" class="ttd-img-digital" alt="TTD Wali Kelas">
                    <?php else: ?>
                        <br><br><br><br><br><br>
                    <?php endif; ?>
                    <b><?= $s['wali'] ?? '......................' ?></b>
                </div>
            </div>

            <div style="display: flex; justify-content: center; margin-top: 10px;">
                <div class="ttd-box" style="text-align: center;">
                    Mengetahui,<br>Kepala <?= $s['nama_sekolah'] ?>
                    <?php if(!empty($s['ttd_kepsek'])): ?>
                        <img src="<?= $s['ttd_kepsek'] ?>" class="ttd-img-digital" alt="TTD Kepala Sekolah">
                    <?php else: ?>
                        <br><br><br><br><br><br>
                    <?php endif; ?>
                    <b><?= $s['nama_kepsek'] ?></b>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    async function pageToCanvas(page) {
        const canvas = await html2canvas(page, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            scrollX: 0,
            scrollY: 0,
            windowWidth: page.scrollWidth,
            windowHeight: page.scrollHeight
        });
        return canvas;
    }

    async function studentToPdfBlob(student) {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pages = student.querySelectorAll('.page');

        for (let p = 0; p < pages.length; p++) {
            const page = pages[p];

            const clone = page.cloneNode(true);
            clone.style.width = '210mm';
            clone.style.minHeight = '297mm';
            clone.style.height = '297mm';
            clone.style.margin = '0';
            clone.style.background = '#ffffff';
            clone.style.boxSizing = 'border-box';
            clone.style.pageBreakAfter = 'auto';
            clone.classList.remove('page-break');

            const wrap = document.createElement('div');
            wrap.style.position = 'fixed';
            wrap.style.left = '-10000px';
            wrap.style.top = '0';
            wrap.style.width = '210mm';
            wrap.style.height = '297mm';
            wrap.style.background = '#ffffff';
            wrap.appendChild(clone);
            document.body.appendChild(wrap);

            const canvas = await pageToCanvas(clone);
            const imgData = canvas.toDataURL('image/jpeg', 0.98);

            if (p > 0) pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);

            document.body.removeChild(wrap);
        }

        return pdf.output('blob');
    }

    async function downloadZip() {
        const btn = document.getElementById('btn-zip');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Menyiapkan Sistem...';
        btn.disabled = true;

        const zip = new JSZip();
        const className = "<?= $kelas_nama_file ?>"; 
        const folder = zip.folder("Rapor_UAS_" + className);

        const students = document.querySelectorAll('.student-rapor');

        if(students.length === 0) {
            alert('Tidak ada data rapor yang bisa di-ZIP (Semua masih pending).');
            btn.innerHTML = originalText;
            btn.disabled = false;
            return;
        }

        for (let i = 0; i < students.length; i++) {
            const student = students[i];
            const nis = student.getAttribute('data-nis');
            const nama = student.getAttribute('data-nama').replace(/[^a-zA-Z0-9]/g, '_');
            const filename = `Rapor_UAS_${nis}_${nama}.pdf`;

            btn.innerHTML = `⏳ Memproses PDF ${i + 1} dari ${students.length}...`;

            try {
                const pdfBlob = await studentToPdfBlob(student);
                folder.file(filename, pdfBlob);
            } catch (err) {
                console.error("Gagal memproses", filename, err);
            }
        }

        btn.innerHTML = '📦 Sedang Mengemas File ZIP...';

        zip.generateAsync({type:"blob"}).then(function(content) {
            saveAs(content, `Rapor_UAS_${className}.zip`);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert("✅ Berhasil! File ZIP Rapor UAS akan terdownload otomatis.");
        });
    }
    </script>
</body>
</html>