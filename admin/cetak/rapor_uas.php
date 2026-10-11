<?php

function ambil_tahun_pelajaran_aktif($conn, $fallback = ''){
    $qTA = $conn->query("SELECT tahun FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
    if($qTA && $qTA->num_rows > 0){
        $v = trim((string)($qTA->fetch_assoc()['tahun'] ?? ''));
        if($v !== '') return $v;
    }

    $qLast = $conn->query("SELECT tahun FROM tahun_ajaran WHERE tahun IS NOT NULL AND tahun != '' ORDER BY id DESC LIMIT 1");
    if($qLast && $qLast->num_rows > 0){
        $v = trim((string)($qLast->fetch_assoc()['tahun'] ?? ''));
        if($v !== '') return $v;
    }

    return $fallback !== '' ? $fallback : date('Y').'/'.(date('Y') + 1);
}

function ambil_semester_identitas($conn, $fallback = 'Ganjil'){
    // PRIORITAS UTAMA: tabel tahun_ajaran yang statusnya Aktif.
    // Ini sinkron dengan halaman Identitas Sekolah yang menyimpan semester ke tahun_ajaran.
    $qTA = $conn->query("SELECT semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
    if($qTA && $qTA->num_rows > 0){
        $v = trim((string)($qTA->fetch_assoc()['semester'] ?? ''));
        if($v !== '') return $v;
    }

    // Fallback: kalau belum ada status aktif, ambil data tahun_ajaran terakhir.
    $qLast = $conn->query("SELECT semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    if($qLast && $qLast->num_rows > 0){
        $v = trim((string)($qLast->fetch_assoc()['semester'] ?? ''));
        if($v !== '') return $v;
    }

    // Fallback tambahan kalau suatu saat semester disimpan langsung di identitas_sekolah.
    $kolom_kandidat = ['semester_aktif', 'semester', 'smt', 'semester_sekolah'];
    $qKolom = $conn->query("SHOW COLUMNS FROM identitas_sekolah");
    if($qKolom){
        $kolom_ada = [];
        while($c = $qKolom->fetch_assoc()){
            $kolom_ada[] = $c['Field'];
        }

        foreach($kolom_kandidat as $kolom){
            if(in_array($kolom, $kolom_ada)){
                $q = $conn->query("SELECT `$kolom` AS semester_fix FROM identitas_sekolah LIMIT 1");
                if($q && $q->num_rows > 0){
                    $v = trim((string)($q->fetch_assoc()['semester_fix'] ?? ''));
                    if($v !== '') return $v;
                }
            }
        }
    }

    return $fallback;
}

function format_semester_kumulatif($tingkat, $semester_identitas){
    $tingkat = strtoupper(trim((string)$tingkat));
    $smt = strtoupper(trim((string)$semester_identitas));

    $is_genap = (
        $smt === '2' || $smt === '4' || $smt === '6' ||
        strpos($smt, 'GENAP') !== false
    );

    if($tingkat === 'XII') return $is_genap ? '6 (GENAP)' : '5 (GANJIL)';
    if($tingkat === 'XI') return $is_genap ? '4 (GENAP)' : '3 (GANJIL)';
    return $is_genap ? '2 (GENAP)' : '1 (GANJIL)';
}

// FIX: resolusi tahun ajaran yang dipakai untuk cetak.
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

include '../../config/Database.php';
$db = new Database(); 
$conn = $db->getConnection();

$id_siswa = $_GET['id'] ?? ''; 
$id_siswa_safe = mysqli_real_escape_string($conn, $id_siswa);

// FIX: resolusi tahun ajaran DIPINDAH ke atas (sebelum ambil data siswa) supaya bisa dipakai
// buat nentuin kelas HISTORIS siswa lewat riwayat_kelas, bukan cuma buat query nilai.
$ta_terpilih = resolve_tahun_ajaran_cetak($conn, $_GET['id_tahun_ajaran'] ?? '');
$id_tahun_dipilih = (int)($ta_terpilih['id'] ?? 0);
$id_tahun_dipilih_safe = mysqli_real_escape_string($conn, $id_tahun_dipilih);

// FIX: kelas siswa diambil dari KELAS HISTORIS (riwayat_kelas) sesuai tahun ajaran yang dicetak,
// bukan kelas siswa SEKARANG (s.id_kelas). Kalau siswa sudah naik kelas sejak tahun ajaran itu,
// nama_kelas/tingkat/wali kelas yang tampil di rapor harus yang berlaku SAAT itu.
$qS = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan, g.nama_guru as wali, g.ttd_digital AS ttd_wali, i.*
                    FROM siswa s
                    LEFT JOIN riwayat_kelas r ON r.id_siswa = s.id AND r.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                    JOIN kelas k ON k.id = COALESCE(r.id_kelas, s.id_kelas)
                    LEFT JOIN guru g ON k.id_wali_kelas=g.id
                    CROSS JOIN identitas_sekolah i 
                    WHERE s.id='$id_siswa_safe'
                    ORDER BY r.id DESC
                    LIMIT 1");
$s = $qS->fetch_assoc();

if(!$s){ echo "Data Siswa Tidak Ditemukan!"; exit; }

function h_rapor($v){
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// Ambil TTD digital kepala sekolah dari profil kepsek
$kepsek_nama = $s['nama_kepsek'] ?? '';
$kepsek_nip = $s['nip_kepsek'] ?? '';
$ttd_kepsek = $s['ttd_kepsek'] ?? '';

$qKepsekTtd = $conn->query("SELECT * FROM kepala_sekolah LIMIT 1");
if($qKepsekTtd && $qKepsekTtd->num_rows > 0){
    $dKepsekTtd = $qKepsekTtd->fetch_assoc();
    if(empty($kepsek_nama)) $kepsek_nama = $dKepsekTtd['nama_lengkap'] ?? ($dKepsekTtd['nama_kepsek'] ?? ($dKepsekTtd['nama'] ?? ''));
    if(empty($kepsek_nip)) $kepsek_nip = $dKepsekTtd['nip'] ?? ($dKepsekTtd['nip_kepsek'] ?? '');
    if(empty($ttd_kepsek)) $ttd_kepsek = $dKepsekTtd['ttd_digital'] ?? '';
}

$ttd_wali = $s['ttd_wali'] ?? '';


// --- LOGIKA PENGAMAN TAHUN PELAJARAN ---
// (resolusi $ta_terpilih / $id_tahun_dipilih sudah dilakukan di atas, dekat query siswa,
// supaya bisa dipakai buat ambil kelas historis lewat riwayat_kelas juga)
$thn_pelajaran_aktif = $ta_terpilih['tahun'] ?: ambil_tahun_pelajaran_aktif($conn);
$thn_cover = explode('/', $thn_pelajaran_aktif)[0];

// Logika Semester
// Semester diambil dari tahun_ajaran yang dipilih/aktif.
// X = 1/2, XI = 3/4, XII = 5/6.
$tingkat_romawi = explode(' ', trim($s['nama_kelas']))[0];
$semester_identitas_fix = $ta_terpilih['semester'] ?: ambil_semester_identitas($conn, 'Ganjil');
$semester_rapor_fix = format_semester_kumulatif(($s['tingkat'] ?? $tingkat_romawi), $semester_identitas_fix);

if(strpos($semester_rapor_fix, 'GENAP') !== false){
    $status_smt = 'Genap';
} else {
    $status_smt = 'Ganjil';
}

$semester_ke = (int)preg_replace('/[^0-9]/', '', $semester_rapor_fix);

// --- FILTER MAPEL SINKRON ADMIN/GURU ---
// Jangan ambil semua kelompok B/C. Mapel yang dicetak hanya mapel umum + mapel jurusan siswa.
$tingkat_romawi = $s['tingkat'] ?? $tingkat_romawi;
$jur_siswa = $s['kode_jurusan'] ?? '';

if(empty($jur_siswa)){
    if(stripos($s['nama_kelas'], 'RPL') !== false) $jur_siswa = 'RPL';
    elseif(stripos($s['nama_kelas'], 'AKL') !== false) $jur_siswa = 'AKL';
    elseif(stripos($s['nama_kelas'], 'AKT') !== false) $jur_siswa = 'AKT';
    elseif(stripos($s['nama_kelas'], 'BR') !== false) $jur_siswa = 'BR';
    elseif(stripos($s['nama_kelas'], 'MP') !== false) $jur_siswa = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $jur_siswa);

$mapel_cols = [];
$qMapelCols = $conn->query("SHOW COLUMNS FROM mapel");
if($qMapelCols){
    while($mc = $qMapelCols->fetch_assoc()){
        $mapel_cols[] = $mc['Field'];
    }
}

$mapel_jurusan_col = '';
if(in_array('kode_jurusan', $mapel_cols)){
    $mapel_jurusan_col = 'kode_jurusan';
} elseif(in_array('jurusan', $mapel_cols)){
    $mapel_jurusan_col = 'jurusan';
} elseif(in_array('id_jurusan', $mapel_cols)){
    $mapel_jurusan_col = 'id_jurusan';
}

if(!empty($mapel_jurusan_col)){
    if($mapel_jurusan_col == 'id_jurusan'){
        $qIdJur = $conn->query("SELECT id FROM jurusan WHERE kode_jurusan='$jurusan_safe' LIMIT 1");
        $id_jur_loop = ($qIdJur && $qIdJur->num_rows > 0) ? (int)$qIdJur->fetch_assoc()['id'] : 0;

        $kondisi_mapel_cetak = "tingkat = '$tingkat_safe'
                                AND nama_mapel NOT LIKE '%PKL%'
                                AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND nama_mapel NOT LIKE '%Prakerin%'
                                AND (
                                    kelompok = 'A'
                                    OR id_jurusan = '$id_jur_loop'
                                )";

        $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe'
                                AND m.nama_mapel NOT LIKE '%PKL%'
                                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND m.nama_mapel NOT LIKE '%Prakerin%'
                                AND (
                                    m.kelompok = 'A'
                                    OR m.id_jurusan = '$id_jur_loop'
                                )";
    } else {
        $kondisi_mapel_cetak = "tingkat = '$tingkat_safe'
                                AND nama_mapel NOT LIKE '%PKL%'
                                AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND nama_mapel NOT LIKE '%Prakerin%'
                                AND (
                                    kelompok = 'A'
                                    OR $mapel_jurusan_col = '$jurusan_safe'
                                    OR $mapel_jurusan_col = ''
                                    OR $mapel_jurusan_col IS NULL
                                )";

        $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe'
                                AND m.nama_mapel NOT LIKE '%PKL%'
                                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND m.nama_mapel NOT LIKE '%Prakerin%'
                                AND (
                                    m.kelompok = 'A'
                                    OR m.$mapel_jurusan_col = '$jurusan_safe'
                                    OR m.$mapel_jurusan_col = ''
                                    OR m.$mapel_jurusan_col IS NULL
                                )";
    }
} else {
    $kondisi_mapel_cetak = "tingkat = '$tingkat_safe'
                            AND nama_mapel NOT LIKE '%PKL%'
                            AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                kelompok = 'A'
                                OR nama_mapel LIKE '%$jurusan_safe%'
                                OR nama_mapel LIKE '%($jurusan_safe)%'
                            )";

    $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe'
                            AND m.nama_mapel NOT LIKE '%PKL%'
                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND m.nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                m.kelompok = 'A'
                                OR m.nama_mapel LIKE '%$jurusan_safe%'
                                OR m.nama_mapel LIKE '%($jurusan_safe)%'
                            )";
}

$qWajibPersonal = $conn->query("SELECT COUNT(*) AS total FROM mapel WHERE $kondisi_mapel_cetak");
$jml_wajib_personal = $qWajibPersonal ? ((int)($qWajibPersonal->fetch_assoc()['total'] ?? 0)) : 0;

// UAS baru boleh dicetak kalau TP, STS, dan SAS sudah terisi serta divalidasi.
$qCekSiap = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS total
                          FROM nilai n
                          JOIN mapel m ON n.id_mapel = m.id
                          WHERE n.id_siswa = '$id_siswa'
                          AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                          AND n.status_validasi = 1
                          AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                          AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
                          AND n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0
                          AND $kondisi_mapel_valid");
$jml_siap_personal = $qCekSiap ? ((int)($qCekSiap->fetch_assoc()['total'] ?? 0)) : 0;

if($jml_wajib_personal <= 0 || $jml_siap_personal < $jml_wajib_personal){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Rapor UAS belum bisa dicetak</h2>
            <p>Pastikan semua nilai TP, STS, dan SAS sudah diinput serta divalidasi.</p>
            <p>Lengkap: {$jml_siap_personal}/{$jml_wajib_personal} mapel</p>
          </div>";
    exit;
}

$qMapel = $conn->query("SELECT * FROM mapel
                        WHERE $kondisi_mapel_cetak
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

// Tahun ajaran yang dipakai untuk Absensi & Ekskul = tahun ajaran yang dipilih/aktif di atas
// (dulu selalu ambil status Aktif, sekarang ikut tahun ajaran yang lagi dicetak biar gak ketuker).
$id_tahun_aktif_ekskul = $id_tahun_dipilih;
$id_tahun_aktif_ekskul_safe = $id_tahun_dipilih_safe;
$semester_absen_safe = mysqli_real_escape_string($conn, $semester_identitas_fix);

// Ambil Absensi & Catatan Akademik
// FIX: filter semester + id_tahun_ajaran biar gak ke-ambil baris siswa dari tahun ajaran/semester lain
$qAbsen = $conn->query("SELECT * FROM absensi
                        WHERE id_siswa = '$id_siswa'
                        AND semester = '$semester_absen_safe'
                        AND id_tahun_ajaran = '$id_tahun_aktif_ekskul_safe'
                        LIMIT 1")->fetch_assoc();

// Ambil Data Ekskul
$qEkskul = $conn->query("SELECT ne.*, 
                        m1.nama_ekskul as nama_eks1, 
                        m2.nama_ekskul as nama_eks2 
                        FROM nilai_ekskul ne 
                        LEFT JOIN master_ekskul m1 ON ne.ekskul_1 = m1.id 
                        LEFT JOIN master_ekskul m2 ON ne.ekskul_2 = m2.id 
                        WHERE ne.id_siswa = '$id_siswa'
                        AND ne.id_tahun_ajaran = '$id_tahun_aktif_ekskul'
                        LIMIT 1");
$dataEkskul = $qEkskul->fetch_assoc();

function nama_file_rapor_otomatis($text){
    $text = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$text);
    $text = trim(preg_replace('/\s+/', '_', $text));
    return $text !== '' ? $text : 'siswa';
}
$nama_file_pdf = 'Rapor_UAS_' . nama_file_rapor_otomatis(($s['nis'] ?? 'tanpa_nis') . '_' . ($s['nama_siswa'] ?? 'siswa')) . '.pdf';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= h_rapor($nama_file_pdf) ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
        .page { width: 210mm; min-height: 297mm; padding: 20mm; margin: 0 auto; box-sizing: border-box; background: white; }
        .page-break { page-break-after: always; }
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
        .ttd-box { width: 220px; text-align: center; }
        .ttd-img { height: 64px; max-width: 170px; object-fit: contain; display:block; margin: 8px auto 6px auto; }
        .ttd-space { height: 64px; }
        @media print {
            body { background: none; }
            .page { margin: 0; border: initial; border-radius: initial; width: initial; min-height: initial; box-shadow: initial; background: initial; }
            .no-print { display: none; }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <div class="no-print" style="text-align:center; padding:15px; background:#e3f2fd; margin-bottom:10px;">
        <button id="btnDownloadPdf" onclick="downloadRaporPdf()" style="padding:10px 20px; font-weight:bold; cursor:pointer; background:#dc3545; color:white; border:0; border-radius:7px; margin-right:8px;">📄 Download PDF</button>
        <button onclick="cetakRaporDenganNama()" style="padding:10px 20px; font-weight:bold; cursor:pointer; background:#6c757d; color:white; border:0; border-radius:7px;">🖨️ Cetak Rapor</button>
        <div style="font-family:Arial,sans-serif; font-size:12px; color:#555; margin-top:8px;">Nama file otomatis: <?= h_rapor($nama_file_pdf) ?></div>
    </div>

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

    <div class="page">
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
                    $base_name = trim(str_replace(["(", ")", "X RPL 2", "X AKT 1", "X RPL 1"], "", $m['nama_mapel']));

                    $qN = $conn->query("SELECT * FROM nilai WHERE id_siswa='$id_siswa' AND id_mapel='$id_m' AND id_tahun_ajaran='$id_tahun_dipilih_safe' LIMIT 1");
                    $dn = $qN->fetch_assoc();
                    
                    if($dn){
                        $tp = $dn['nilai_t']; $sts = $dn['nilai_p']; $sas = $dn['nilai_k'];
                        $na = round(((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10); 
                        
                        if($na < $kkm_mapel) { $mapel_remedial[] = $base_name; }

                        $cp = $dn['deskripsi_cp'];
                        // --- AUTO KOREKSI ---
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
                    <td class="text-start"><?= $base_name ?></td>
                    <td class="td-center" style="font-size: 11pt; font-weight:bold;"><?= $na ?></td>
                    <td class="td-cp"><?= $cp ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <b style="display:block; margin-top: 20px;">B. Catatan Akademik</b>
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

        <?php 
        $bulan_indo = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $tgl_cetak = date('d') . ' ' . $bulan_indo[(int)date('m')] . ' ' . date('Y');
        ?>
        <div style="display: flex; justify-content: space-between; margin-top: 30px;">
            <div class="ttd-box">
                Mengetahui,<br>Orang tua/wali
                <div class="ttd-space"></div>
                _________________________
            </div>
            <div class="ttd-box">
                Jakarta, <?= $tgl_cetak ?><br>Wali Kelas
                <?php if(!empty($ttd_wali)): ?>
                    <img class="ttd-img" src="<?= h_rapor($ttd_wali) ?>" alt="TTD Wali Kelas">
                <?php else: ?>
                    <div class="ttd-space"></div>
                <?php endif; ?>
                <b><?= h_rapor($s['wali'] ?? '......................') ?></b>
            </div>
        </div>

        <div style="display: flex; justify-content: center; margin-top: 10px;">
            <div class="ttd-box" style="text-align: center;">
                Mengetahui,<br>Kepala <?= h_rapor($s['nama_sekolah']) ?>
                <?php if(!empty($ttd_kepsek)): ?>
                    <img class="ttd-img" src="<?= h_rapor($ttd_kepsek) ?>" alt="TTD Kepala Sekolah">
                <?php else: ?>
                    <div class="ttd-space"></div>
                <?php endif; ?>
                <b><?= h_rapor($kepsek_nama ?: ($s['nama_kepsek'] ?? '......................')) ?></b>
                <?php if(!empty($kepsek_nip)): ?><br><span style="font-size:9pt;">NIP. <?= h_rapor($kepsek_nip) ?></span><?php endif; ?>
            </div>
        </div>
    </div>

<script>
const namaFileRapor = <?= json_encode($nama_file_pdf) ?>;

function cetakRaporDenganNama(){
    document.title = namaFileRapor.replace(/\.pdf$/i, '');
    window.print();
}

async function downloadRaporPdf(){
    const btn = document.getElementById('btnDownloadPdf');
    const old = btn ? btn.innerHTML : '';
    if(btn){ btn.innerHTML = '⏳ Membuat PDF...'; btn.disabled = true; }

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pages = Array.from(document.querySelectorAll('.page'));

    for(let i = 0; i < pages.length; i++){
        const page = pages[i];
        const clone = page.cloneNode(true);
        clone.style.width = '210mm';
        clone.style.minHeight = '297mm';
        clone.style.margin = '0';
        clone.style.background = '#ffffff';
        clone.style.boxSizing = 'border-box';

        const wrap = document.createElement('div');
        wrap.style.position = 'fixed';
        wrap.style.left = '0';
        wrap.style.top = '0';
        wrap.style.width = '210mm';
        wrap.style.background = '#ffffff';
        wrap.style.zIndex = '-9999';
        wrap.appendChild(clone);
        document.body.appendChild(wrap);

        await new Promise(resolve => setTimeout(resolve, 120));

        const canvas = await html2canvas(clone, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            scrollX: 0,
            scrollY: 0,
            windowWidth: clone.scrollWidth,
            windowHeight: clone.scrollHeight
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.98);
        const imgWidth = 210;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        if(i > 0) pdf.addPage('a4', 'p');
        pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
        document.body.removeChild(wrap);
    }

    pdf.save(namaFileRapor);
    if(btn){ btn.innerHTML = old; btn.disabled = false; }
}
</script>
</body>
</html>