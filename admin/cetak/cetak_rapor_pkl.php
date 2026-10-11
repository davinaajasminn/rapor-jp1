<?php
include '../../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$id_siswa = $_GET['id_siswa'] ?? ($_GET['id'] ?? '');
$id_siswa = mysqli_real_escape_string($conn, $id_siswa);

// FIX: terima id_tahun_ajaran dari cetak_rapor.php. Tanpa ini, data PKL, absensi PKL,
// dan nilai mapel akademik semua diambil TANPA filter tahun ajaran (asal ambil 1 baris),
// sehingga Ganjil/Genap tidak terpisah dan bisa ke-ambil baris yang salah/dobel.
$id_ta_get = $_GET['id_tahun_ajaran'] ?? '';
if(!empty($id_ta_get)){
    $id_ta_safe = mysqli_real_escape_string($conn, $id_ta_get);
    $qTaPilih = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
} else {
    $qTaPilih = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
}
$ta_pilih = ($qTaPilih && $qTaPilih->num_rows > 0) ? $qTaPilih->fetch_assoc() : null;
$id_ta_pilih = $ta_pilih['id'] ?? 0;
$id_ta_pilih_safe = mysqli_real_escape_string($conn, $id_ta_pilih);

if(empty($id_ta_pilih)){
    echo "Tahun Ajaran tidak ditemukan.";
    exit;
}

function h($v){
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

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


function pick_val($arr, $keys, $default=''){
    foreach($keys as $k){
        if(isset($arr[$k]) && $arr[$k] !== null && $arr[$k] !== ''){
            return $arr[$k];
        }
    }
    return $default;
}

function tgl_indo_pkl($tanggal){
    if(empty($tanggal) || $tanggal == '0000-00-00') return '-';
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $ts = strtotime($tanggal);
    if(!$ts) return '-';
    return date('d', $ts).' '.$bulan[(int)date('m', $ts)].' '.date('Y', $ts);
}

function predikat_desc($nilai, $mapel){
    $nilai = (int)$nilai;
    if($nilai >= 90) return "Menunjukkan penguasaan yang sangat baik dalam ".$mapel.".";
    if($nilai >= 80) return "Menunjukkan penguasaan baik dalam ".$mapel.".";
    if($nilai >= 70) return "Menunjukkan penguasaan cukup baik dalam ".$mapel.".";
    return "Perlu meningkatkan penguasaan kompetensi dalam ".$mapel.".";
}

$qS = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan,
                           wali.nama_guru AS nama_wali_kelas,
                           wali.ttd_digital AS ttd_wali_kelas,
                           p.*,
                           gp.nama_guru AS nama_guru_pembimbing,
                           gp.ttd_digital AS ttd_guru_pembimbing,
                           i.*
                    FROM siswa s
                    JOIN kelas k ON s.id_kelas = k.id
                    LEFT JOIN guru wali ON k.id_wali_kelas = wali.id
                    LEFT JOIN data_pkl p ON p.id_siswa = s.id AND p.id_tahun_ajaran = '$id_ta_pilih_safe'
                    LEFT JOIN guru gp ON p.id_guru_pembimbing = gp.id
                    CROSS JOIN identitas_sekolah i
                    WHERE s.id = '$id_siswa'
                    LIMIT 1");

$data = ($qS && $qS->num_rows > 0) ? $qS->fetch_assoc() : null;
if(!$data){
    echo "Data siswa tidak ditemukan.";
    exit;
}

// FIX: "(tempat PKL)" sebelumnya teks statis, sekarang diganti otomatis pakai nama
// instansi/mitra DUDI asli siswa (mitra_dudi). Kalau belum diisi, tetap fallback
// ke frasa generik "tempat PKL" biar kalimatnya tidak pincang.
$nama_tempat_pkl = trim((string)($data['mitra_dudi'] ?? ''));
if($nama_tempat_pkl === ''){
    $nama_tempat_pkl = 'tempat PKL';
}
$default_tp = [
    1 => "Menerapkan soft skills yang dibutuhkan dalam dunia kerja ($nama_tempat_pkl)",
    2 => "Menerapkan norma, POS dan K3LH yang ada pada dunia kerja ($nama_tempat_pkl)",
    3 => "Menerapkan kompetensi teknis yang sudah dipelajari di sekolah dan/atau baru dipelajari pada dunia kerja ($nama_tempat_pkl)",
    4 => "Memahami alur bisnis dunia kerja di $nama_tempat_pkl"
];

// Ambil kepala sekolah dari tabel kepala_sekolah kalau identitas_sekolah tidak punya nama kepsek.
$kepsek_nama = pick_val($data, ['nama_kepsek','kepala_sekolah','nama_kepala_sekolah','nama_kepala'], '');
$kepsek_nip = pick_val($data, ['nip_kepsek','nip_kepala_sekolah','nip_kepala'], '');
$ttd_kepsek = pick_val($data, ['ttd_kepsek','ttd_kepala_sekolah','ttd_digital_kepsek'], '');

$qKS = $conn->query("SELECT * FROM kepala_sekolah LIMIT 1");
if($qKS && $qKS->num_rows > 0){
    $dKS = $qKS->fetch_assoc();
    if(empty($kepsek_nama)) $kepsek_nama = pick_val($dKS, ['nama_kepsek','nama_kepala_sekolah','nama_lengkap','nama','nama_kepala'], '');
    if(empty($kepsek_nip)) $kepsek_nip = pick_val($dKS, ['nip_kepsek','nip_kepala_sekolah','nip'], '');
    if(empty($ttd_kepsek)) $ttd_kepsek = pick_val($dKS, ['ttd_digital','ttd','ttd_kepsek'], '');
}

$wali_nama = pick_val($data, ['nama_wali_kelas','wali','nama_wali'], '');
$guru_pembimbing = pick_val($data, ['nama_guru_pembimbing','nama_pembimbing'], '');
$ttd_wali = pick_val($data, ['ttd_wali_kelas','ttd_wali'], '');
$ttd_guru_pembimbing = pick_val($data, ['ttd_guru_pembimbing','ttd_pembimbing'], '');
$nama_instruktur = pick_val($data, ['nama_instruktur','instruktur'], '');
$kota = pick_val($data, ['kota','kabupaten','alamat_kota'], 'Jakarta');

$bulan_indo = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$tgl_cetak = date('d').' '.$bulan_indo[(int)date('m')].' '.date('Y');

$thn_pelajaran = $ta_pilih['tahun'] ?? ambil_tahun_pelajaran_aktif($conn, pick_val($data, ['tahun_pelajaran','tahun_ajaran'], ''));
$thn_cover = explode('/', $thn_pelajaran)[0];



$nama_kelas = $data['nama_kelas'] ?? '-';
$tingkat = $data['tingkat'] ?? '';
$jurusan_kode = $data['kode_jurusan'] ?? '';

if(empty($tingkat)){
    $tingkat = explode(' ', trim($nama_kelas))[0] ?? 'XII';
}

$semester_db = $ta_pilih['semester'] ?? ambil_semester_identitas($conn, 'Ganjil');

// FIX: Ketidakhadiran di rapor PKL sekarang disamakan sumbernya dengan Input Absensi Siswa
// (tabel `absensi`, diisi admin), bukan lagi dari data_pkl.sakit/izin/tanpa_keterangan.
// Field ketidakhadiran di halaman Input PKL sudah tidak dipakai lagi untuk keperluan ini.
$semester_absen_pkl_safe = mysqli_real_escape_string($conn, $semester_db);
$qAbsenPkl = $conn->query("SELECT sakit, izin, alpha FROM absensi
                           WHERE id_siswa = '$id_siswa'
                           AND semester = '$semester_absen_pkl_safe'
                           AND id_tahun_ajaran = '$id_ta_pilih_safe'
                           LIMIT 1");
$data_absen_pkl = ($qAbsenPkl && $qAbsenPkl->num_rows > 0) ? $qAbsenPkl->fetch_assoc() : ['sakit' => 0, 'izin' => 0, 'alpha' => 0];
$semester_rapor = format_semester_kumulatif($tingkat, $semester_db);

if(empty($jurusan_kode)){
    if(stripos($nama_kelas, 'RPL') !== false) $jurusan_kode = 'RPL';
    elseif(stripos($nama_kelas, 'AKL') !== false) $jurusan_kode = 'AKL';
    elseif(stripos($nama_kelas, 'AKT') !== false) $jurusan_kode = 'AKT';
    elseif(stripos($nama_kelas, 'BR') !== false) $jurusan_kode = 'BR';
    elseif(stripos($nama_kelas, 'MP') !== false) $jurusan_kode = 'MP';
}

$nama_jurusan = $jurusan_kode;
$qJur = $conn->query("SELECT * FROM jurusan WHERE kode_jurusan = '".mysqli_real_escape_string($conn,$jurusan_kode)."' OR nama_jurusan LIKE '%".mysqli_real_escape_string($conn,$jurusan_kode)."%' LIMIT 1");
if($qJur && $qJur->num_rows > 0){
    $dJur = $qJur->fetch_assoc();
    $nama_jurusan = pick_val($dJur, ['nama_jurusan','jurusan'], $jurusan_kode);
} else {
    if($jurusan_kode == 'RPL') $nama_jurusan = 'Rekayasa Perangkat Lunak';
    elseif($jurusan_kode == 'AKL' || $jurusan_kode == 'AKT') $nama_jurusan = 'Akuntansi dan Keuangan Lembaga';
    elseif($jurusan_kode == 'BR') $nama_jurusan = 'Bisnis Retail';
    elseif($jurusan_kode == 'MP') $nama_jurusan = 'Manajemen Perkantoran';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_kode);

// Deteksi kolom jurusan pada mapel.
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

$id_jurusan_for_mapel = 0;
if($mapel_jurusan_col == 'id_jurusan'){
    $qIdJur = $conn->query("SELECT id FROM jurusan WHERE kode_jurusan = '$jurusan_safe' OR nama_jurusan LIKE '%$jurusan_safe%' LIMIT 1");
    if($qIdJur && $qIdJur->num_rows > 0){
        $id_jurusan_for_mapel = (int)$qIdJur->fetch_assoc()['id'];
    }
}

if($mapel_jurusan_col == 'id_jurusan'){
    $kondisi_mapel = "m.tingkat = '$tingkat_safe'
                      AND m.nama_mapel NOT LIKE '%PKL%'
                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                      AND (m.kelompok = 'A' OR m.id_jurusan = '$id_jurusan_for_mapel')";
} elseif(!empty($mapel_jurusan_col)){
    $kondisi_mapel = "m.tingkat = '$tingkat_safe'
                      AND m.nama_mapel NOT LIKE '%PKL%'
                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                      AND (
                            m.kelompok = 'A'
                            OR m.$mapel_jurusan_col = '$jurusan_safe'
                            OR m.$mapel_jurusan_col = ''
                            OR m.$mapel_jurusan_col IS NULL
                          )";
} else {
    $kondisi_mapel = "m.tingkat = '$tingkat_safe'
                      AND m.nama_mapel NOT LIKE '%PKL%'
                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                      AND (
                            m.kelompok = 'A'
                            OR m.nama_mapel LIKE '%$jurusan_safe%'
                            OR m.nama_mapel LIKE '%($jurusan_safe)%'
                          )";
}

$qMapel = $conn->query("SELECT m.*, n.nilai_t, n.nilai_p, n.nilai_k, n.status_validasi
                        FROM mapel m
                        LEFT JOIN nilai n ON n.id_mapel = m.id AND n.id_siswa = '$id_siswa' AND n.id_tahun_ajaran = '$id_ta_pilih_safe'
                        WHERE $kondisi_mapel
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

$mapel_rows = [];
$mapel_tidak_lengkap = [];
if($qMapel){
    while($m = $qMapel->fetch_assoc()){
        $nt = $m['nilai_t'];
        $np = $m['nilai_p'];
        $nk = $m['nilai_k'];

        $lengkap = ((int)($m['status_validasi'] ?? 0) == 1)
                   && $nt !== null && $nt !== '' && (float)$nt > 0
                   && $np !== null && $np !== '' && (float)$np > 0
                   && $nk !== null && $nk !== '' && (float)$nk > 0;

        if(!$lengkap){
            $mapel_tidak_lengkap[] = $m['nama_mapel'];
        }

        if($nt === null || $nt === '') $nt = 0;
        if($np === null || $np === '') $np = 0;
        if($nk === null || $nk === '') $nk = 0;

        if((float)$np == 0 && (float)$nk == 0) {
            $na = (float)$nt;
        } elseif((float)$nk == 0) {
            $na = (((float)$nt * 4) + ((float)$np * 2)) / 6;
        } else {
            $na = (((float)$nt * 4) + ((float)$np * 2) + ((float)$nk * 4)) / 10;
        }

        $m['nilai_akhir'] = round($na);
        $mapel_rows[] = $m;
    }
}

$skor_pkl = [];
foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $f){
    if(isset($data[$f]) && $data[$f] !== null && $data[$f] !== ''){
        $skor_pkl[] = (int)$data[$f];
    }
}

$pkl_lengkap = count($skor_pkl) == 4;
$pkl_valid = ((int)($data['status_validasi_pkl'] ?? 0) == 1);

$boleh_cetak = (count($mapel_rows) > 0 && count($mapel_tidak_lengkap) == 0 && $pkl_lengkap && $pkl_valid);

if(!$boleh_cetak){
    echo "<div style='font-family:Arial; max-width:820px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Rapor kelas XII belum bisa dicetak</h2>
            <p>Rapor kelas XII harus memuat nilai mapel akademik dan nilai PKL dalam satu laporan.</p>";

    if(count($mapel_rows) == 0){
        echo "<p>Mapel akademik kelas XII belum ditemukan.</p>";
    } elseif(count($mapel_tidak_lengkap) > 0){
        echo "<p>Mapel akademik belum lengkap/valid:</p><ul>";
        foreach($mapel_tidak_lengkap as $mm){
            echo "<li>".h($mm)."</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Mapel akademik sudah lengkap dan valid.</p>";
    }

    echo "<p>Skor PKL terisi: ".count($skor_pkl)."/4</p>
          <p>Status validasi PKL: ".($pkl_valid ? "Valid" : "Belum valid")."</p>
          </div>";
    exit;
}

$nilai_pkl = !empty($data['nilai_pkl']) ? (int)$data['nilai_pkl'] : round(array_sum($skor_pkl) / count($skor_pkl));

function nama_file_rapor_otomatis_pkl($text){
    $text = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$text);
    $text = trim(preg_replace('/\s+/', '_', $text));
    return $text !== '' ? $text : 'siswa';
}
$nama_file_pdf = 'Rapor_PKL_' . nama_file_rapor_otomatis_pkl(($data['nis'] ?? 'tanpa_nis') . '_' . ($data['nama_siswa'] ?? 'siswa')) . '.pdf';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= h($nama_file_pdf) ?></title>
<style>
    body { font-family: Arial, sans-serif; font-size: 10pt; color:#000; margin:0; padding:0; }
    .page { width:210mm; min-height:297mm; padding:18mm 20mm; margin:0 auto; box-sizing:border-box; background:#fff; }
    .page-break { page-break-after: always; }
    .cover-title { text-align:center; font-weight:bold; font-size:14pt; margin-top:48px; line-height:1.6; }
    .cover-logo { text-align:center; margin:48px 0; }
    .cover-logo img { width:150px; }
    .cover-box { text-align:center; margin-bottom:18px; }
    .cover-box p { font-weight:bold; margin-bottom:5px; font-size:11pt; }
    .cover-box .box { border:2px solid #000; padding:10px; width:62%; margin:0 auto; font-weight:bold; font-size:12pt; text-transform:uppercase; }
    .cover-footer { text-align:center; font-weight:bold; font-size:12pt; margin-top:74px; line-height:1.5; }
    .title { text-align:center; font-weight:bold; font-size:12pt; line-height:1.4; margin-bottom:14px; }
    .section-title { font-weight:bold; font-size:11pt; margin:14px 0 8px; }
    .identitas { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:10pt; }
    .identitas td { padding:2px 4px; vertical-align:top; }
    .table-rapor { width:100%; border-collapse:collapse; font-size:9.5pt; }
    .table-rapor th, .table-rapor td { border:1px solid #000; padding:6px; vertical-align:top; }
    .table-rapor th { background:#eaeaea; text-align:center; }
    .kelompok { background:#f3f3f3; font-weight:bold; }
    .center { text-align:center; vertical-align:middle !important; }
    .desc { text-align:justify; line-height:1.35; }
    .catatan { border:1px solid #000; min-height:42px; padding:8px; margin-top:12px; text-align:justify; }
    .absensi { width:55%; border-collapse:collapse; margin-top:12px; font-size:10pt; }
    .absensi td { border:1px solid #000; padding:5px; }
    .ttd-wrap { display:flex; justify-content:space-between; margin-top:30px; text-align:center; }
    .ttd-box { width:31%; text-align:center; }
    .ttd-space { height:82px; }
    .ttd-img { height:64px; max-width:170px; object-fit:contain; display:block; margin:8px auto 6px auto; }
    .ttd-name { display:inline-block; min-width:150px; border-bottom:1px solid #000; padding-bottom:3px; font-weight:bold; }
    .ttd-nip { margin-top:3px; font-size:9pt; }
    .ttd-center { text-align:center; margin-top:24px; }
    .no-print { text-align:center; padding:14px; background:#e3f2fd; margin-bottom:10px; }
    @media print { .no-print { display:none; } .page { margin:0; width:auto; min-height:auto; } }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
<div class="no-print">
    <button id="btnDownloadPdf" onclick="downloadRaporPdf()" style="padding:10px 22px; font-weight:bold; cursor:pointer; background:#dc3545; color:white; border:0; border-radius:7px; margin-right:8px;">📄 Download PDF</button>
    <button onclick="cetakRaporDenganNama()" style="padding:10px 22px; font-weight:bold; cursor:pointer; background:#6c757d; color:white; border:0; border-radius:7px;">🖨️ Cetak Rapor</button>
    <div style="font-family:Arial,sans-serif; font-size:12px; color:#555; margin-top:8px;">Nama file otomatis: <?= h($nama_file_pdf) ?></div>
</div>

<div class="page page-break">
    <div class="cover-title">
        RAPOR PESERTA DIDIK<br>
        KELAS XII<br>
        TAHUN PELAJARAN <?= h($thn_pelajaran) ?><br>
        <?= strtoupper(h($data['nama_sekolah'])) ?>
    </div>
    <div class="cover-logo"><img src="../../assets/logo.png" alt="Logo Sekolah"></div>
    <div class="cover-box"><p>Nama Peserta Didik</p><div class="box"><?= h($data['nama_siswa']) ?></div></div>
    <div class="cover-box"><p>NISN / NIS</p><div class="box"><?= h(($data['nisn'] ?: '-') . ' / ' . ($data['nis'] ?: '-')) ?></div></div>
    <div class="cover-footer"><?= strtoupper(h($data['nama_sekolah'])) ?><br>TAHUN <?= h($thn_cover) ?></div>
</div>

<div class="page page-break">
    <div class="title">
        RAPOR PESERTA DIDIK KELAS XII<br>
        <?= strtoupper(h($data['nama_sekolah'])) ?><br>
        TAHUN PELAJARAN <?= h($thn_pelajaran) ?>
    </div>

    <table class="identitas">
        <tr><td width="28%">Nama Peserta Didik</td><td width="2%">:</td><td><?= h($data['nama_siswa']) ?></td></tr>
        <tr><td>NISN / NIS</td><td>:</td><td><?= h(($data['nisn'] ?: '-') . ' / ' . ($data['nis'] ?: '-')) ?></td></tr>
        <tr><td>Kelas</td><td>:</td><td><?= h($nama_kelas) ?></td></tr>
        <tr><td>Program/Konsentrasi Keahlian</td><td>:</td><td><?= h($nama_jurusan) ?></td></tr>
        <tr><td>Semester</td><td>:</td><td><?= h($semester_rapor) ?></td></tr>
    </table>

    <div class="section-title">A. Nilai Akademik</div>
    <table class="table-rapor">
        <thead>
            <tr>
                <th width="7%">No</th>
                <th width="28%">Mata Pelajaran</th>
                <th width="12%">Nilai Akhir</th>
                <th width="53%">Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $last_kel = '';
            foreach($mapel_rows as $m):
                $kel = strtoupper((string)($m['kelompok'] ?? ''));
                $label_kel = ($kel == 'B' || strpos($kel,'C') === 0) ? 'B. Kelompok Mata Pelajaran Kejuruan' : 'A. Kelompok Mata Pelajaran Umum';
                if($label_kel != $last_kel):
                    $last_kel = $label_kel;
            ?>
                <tr><td colspan="4" class="kelompok"><?= h($label_kel) ?></td></tr>
            <?php endif; ?>
                <tr>
                    <td class="center"><?= $no++ ?></td>
                    <td><?= h($m['nama_mapel']) ?></td>
                    <td class="center"><b><?= h($m['nilai_akhir']) ?></b></td>
                    <td class="desc"><?= h(predikat_desc($m['nilai_akhir'], $m['nama_mapel'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="page">
    <div class="title">
        NILAI PRAKTIK KERJA LAPANGAN (PKL)<br>
        <?= strtoupper(h($data['nama_sekolah'])) ?><br>
        TAHUN PELAJARAN <?= h($thn_pelajaran) ?>
    </div>

    <table class="identitas">
        <tr><td width="28%">Nama Peserta Didik</td><td width="2%">:</td><td><?= h($data['nama_siswa']) ?></td></tr>
        <tr><td>Kelas</td><td>:</td><td><?= h($nama_kelas) ?></td></tr>
        <tr><td>Tempat PKL</td><td>:</td><td><?= h($data['mitra_dudi'] ?: '-') ?></td></tr>
        <tr><td>Lokasi PKL</td><td>:</td><td><?= h($data['lokasi'] ?: '-') ?></td></tr>
        <tr><td>Tanggal PKL</td><td>:</td><td><?= tgl_indo_pkl($data['tanggal_mulai'] ?? '') ?> s.d. <?= tgl_indo_pkl($data['tanggal_selesai'] ?? '') ?></td></tr>
        <tr><td>Nama Instruktur</td><td>:</td><td><?= h($nama_instruktur ?: '-') ?></td></tr>
        <tr><td>Guru Pembimbing</td><td>:</td><td><?= h($guru_pembimbing ?: '-') ?></td></tr>
    </table>

    <div class="section-title">B. Nilai Praktik Kerja Lapangan</div>
    <table class="table-rapor">
        <thead><tr><th width="42%">Tujuan Pembelajaran</th><th width="13%">Skor</th><th width="45%">Deskripsi</th></tr></thead>
        <tbody>
            <?php for($i=1; $i<=4; $i++):
                $field_skor = "tp{$i}_skor";
                $field_desc = "tp{$i}_deskripsi";
                $desc = $data[$field_desc] ?? '';
                if(empty($desc)) $desc = "Peserta didik mampu mencapai tujuan pembelajaran PKL dengan baik sesuai tuntutan dunia kerja.";
            ?>
            <tr>
                <td><?= $i ?>. <?= h($default_tp[$i]) ?></td>
                <td class="center"><?= h($data[$field_skor] ?? '-') ?></td>
                <td class="desc"><?= h($desc) ?></td>
            </tr>
            <?php endfor; ?>
            <tr>
                <td><b>Nilai Akhir PKL</b></td>
                <td class="center"><b><?= h($nilai_pkl) ?></b></td>
                <td class="desc"><?= h($data['keterangan'] ?: 'Peserta didik telah menyelesaikan kegiatan Praktik Kerja Lapangan dengan baik.') ?></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">C. Catatan dan Ketidakhadiran PKL</div>
    <div class="catatan"><b>Catatan:</b><br><?= h($data['catatan_pkl'] ?: 'Peserta didik telah mengikuti kegiatan PKL sesuai ketentuan yang berlaku.') ?></div>

    <table class="absensi">
        <tr><td width="60%">Sakit</td><td class="center" width="20%"><?= (int)($data_absen_pkl['sakit'] ?? 0) ?></td><td class="center" width="20%">Hari</td></tr>
        <tr><td>Izin</td><td class="center"><?= (int)($data_absen_pkl['izin'] ?? 0) ?></td><td class="center">Hari</td></tr>
        <tr><td>Tanpa Keterangan</td><td class="center"><?= (int)($data_absen_pkl['alpha'] ?? 0) ?></td><td class="center">Hari</td></tr>
    </table>

    <div style="text-align:right; margin-top:10px;"><?= h($kota) ?>, <?= h($tgl_cetak) ?></div>

    <div class="ttd-wrap">
        <div class="ttd-box">
            Guru Pembimbing
            <?php if(!empty($ttd_guru_pembimbing)): ?>
                <img class="ttd-img" src="<?= h($ttd_guru_pembimbing) ?>" alt="TTD Guru Pembimbing">
            <?php else: ?>
                <div class="ttd-space"></div>
            <?php endif; ?>
            <span class="ttd-name"><?= h($guru_pembimbing ?: '................................') ?></span>
        </div>
        <div class="ttd-box">
            Wali Kelas
            <?php if(!empty($ttd_wali)): ?>
                <img class="ttd-img" src="<?= h($ttd_wali) ?>" alt="TTD Wali Kelas">
            <?php else: ?>
                <div class="ttd-space"></div>
            <?php endif; ?>
            <span class="ttd-name"><?= h($wali_nama ?: '................................') ?></span>
        </div>
        <div class="ttd-box">
            Pembimbing Dunia Kerja
            <div class="ttd-space"></div>
            <span class="ttd-name"><?= h($nama_instruktur ?: '................................') ?></span>
        </div>
    </div>

    <div class="ttd-center">
        Mengetahui,<br>
        Kepala <?= h($data['nama_sekolah'] ?? 'Sekolah') ?>
        <?php if(!empty($ttd_kepsek)): ?>
            <img class="ttd-img" src="<?= h($ttd_kepsek) ?>" alt="TTD Kepala Sekolah">
        <?php else: ?>
            <div class="ttd-space"></div>
        <?php endif; ?>
        <span class="ttd-name"><?= h($kepsek_nama ?: '................................') ?></span>
        <?php if(!empty($kepsek_nip)): ?><div class="ttd-nip">NIP. <?= h($kepsek_nip) ?></div><?php endif; ?>
    </div>
</div>

<script>
const namaFileRapor = <?= json_encode($nama_file_pdf) ?>;
function cetakRaporDenganNama(){ document.title = namaFileRapor.replace(/\.pdf$/i, ''); window.print(); }
async function downloadRaporPdf(){
    const btn=document.getElementById('btnDownloadPdf'); const old=btn?btn.innerHTML:''; if(btn){btn.innerHTML='⏳ Membuat PDF...'; btn.disabled=true;}
    const {jsPDF}=window.jspdf; const pdf=new jsPDF('p','mm','a4'); const pages=Array.from(document.querySelectorAll('.page'));
    for(let i=0;i<pages.length;i++){
        const clone=pages[i].cloneNode(true); clone.style.width='210mm'; clone.style.minHeight='297mm'; clone.style.margin='0'; clone.style.background='#fff'; clone.style.boxSizing='border-box';
        const wrap=document.createElement('div'); wrap.style.position='fixed'; wrap.style.left='0'; wrap.style.top='0'; wrap.style.width='210mm'; wrap.style.background='#fff'; wrap.style.zIndex='-9999'; wrap.appendChild(clone); document.body.appendChild(wrap);
        await new Promise(r=>setTimeout(r,120));
        const canvas=await html2canvas(clone,{scale:2,useCORS:true,allowTaint:true,backgroundColor:'#fff',scrollX:0,scrollY:0,windowWidth:clone.scrollWidth,windowHeight:clone.scrollHeight});
        const img=canvas.toDataURL('image/jpeg',0.98); const w=210; const h=(canvas.height*w)/canvas.width; if(i>0) pdf.addPage('a4','p'); pdf.addImage(img,'JPEG',0,0,w,h); document.body.removeChild(wrap);
    }
    pdf.save(namaFileRapor); if(btn){btn.innerHTML=old; btn.disabled=false;}
}
</script>
</body>
</html>