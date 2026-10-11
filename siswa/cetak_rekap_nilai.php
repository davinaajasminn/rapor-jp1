<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

if(!isset($conn) || !$conn){
    require_once '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function namaFile($v){
    $v = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$v);
    return trim(preg_replace('/\s+/', '_', $v)) ?: 'siswa';
}
function namaRapi($v){
    $v = trim((string)$v);
    return $v === '' ? '' : ucwords(strtolower($v));
}
function tglIndo($tgl){
    $b=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $p=explode('-', date('Y-m-d', strtotime($tgl)));
    return (int)$p[2].' '.$b[(int)$p[1]].' '.$p[0];
}
function logoSekolah(){
    foreach(['../assets/logo.png','../assets/img/logo.png','../assets/images/logo.png','../uploads/logo.png','../assets/logo.jpg'] as $p){
        if(file_exists($p)) return $p;
    }
    return '../assets/logo.png';
}

// FIX UTAMA:
// File cetak dibuka tab baru, jadi variabel dari index.php tidak ikut.
// Maka id siswa dikirim dari tombol lihat_nilai.php lewat GET: ?id=...
$id_siswa = $_GET['id'] ?? 0;

// Fallback kalau suatu saat dibuka tanpa GET.
if(empty($id_siswa)){
    $id_siswa = $_SESSION['siswa']['id'] ?? ($_SESSION['id_siswa'] ?? ($_SESSION['siswa_id'] ?? 0));
}

$nis = $_SESSION['siswa']['nis'] ?? ($_SESSION['nis'] ?? '');

if((empty($id_siswa) || $id_siswa == 0) && !empty($nis)){
    $nis_safe = mysqli_real_escape_string($conn, $nis);
    $q = $conn->query("SELECT id FROM siswa WHERE nis='$nis_safe' LIMIT 1");
    if($q && $q->num_rows > 0) $id_siswa = (int)$q->fetch_assoc()['id'];
}

$id_siswa_safe = mysqli_real_escape_string($conn, $id_siswa);

$id_siswa_safe = mysqli_real_escape_string($conn, $id_siswa);

// --- AMBIL DATA DASAR SISWA (tanpa join kelas -- kelasnya nanti diresolve per TA) ---
$qSiswa = $conn->query("SELECT * FROM siswa WHERE id='$id_siswa_safe' LIMIT 1");

if(!$qSiswa || $qSiswa->num_rows == 0){
    die('Data siswa tidak ditemukan.');
}

$siswa = $qSiswa->fetch_assoc();

// --- RESOLUSI TAHUN AJARAN YANG MAU DICETAK (harus duluan, sebelum resolusi kelas) ---
$qTA = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$ta = ($qTA && $qTA->num_rows > 0) ? $qTA->fetch_assoc() : null;

if(!$ta){
    $qTALast = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    $ta = ($qTALast && $qTALast->num_rows > 0) ? $qTALast->fetch_assoc() : null;
}

// Kalau ada pilihan tahun ajaran dari halaman Lihat Nilai (?id_tahun_ajaran=...), pakai itu.
$id_ta_get_rekap = trim((string)($_GET['id_tahun_ajaran'] ?? ''));
if($id_ta_get_rekap !== '' && ctype_digit($id_ta_get_rekap)){
    $id_ta_get_rekap_safe = mysqli_real_escape_string($conn, $id_ta_get_rekap);
    $qTaPilihRekap = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE id='$id_ta_get_rekap_safe' LIMIT 1");
    if($qTaPilihRekap && $qTaPilihRekap->num_rows > 0){
        $ta = $qTaPilihRekap->fetch_assoc();
    }
}

$id_ta_rekap = (int)($ta['id'] ?? 0);
$id_ta_rekap_safe = mysqli_real_escape_string($conn, $id_ta_rekap);

// --- RESOLUSI KELAS SESUAI TA YANG DICETAK ---
// FIX: sebelumnya kelas/tingkat/jurusan diambil dari siswa.id_kelas (kelas SEKARANG),
// jadi kalau siswa udah naik kelas sejak TA yang mau dicetak, mapel yang dicari jadi
// versi tingkat yang salah dan nilai yang udah ada gak ketemu (semua keliatan '-').
// Sumber kebenaran yang benar: riwayat_kelas per TA, sama kayak lihat_nilai.php.
$qRkRekap = $conn->query("SELECT id_kelas FROM riwayat_kelas
                           WHERE id_siswa='$id_siswa_safe' AND id_tahun_ajaran='$id_ta_rekap_safe'
                           ORDER BY id DESC LIMIT 1");

$id_kelas_utk_rekap = 0;
if($qRkRekap && $qRkRekap->num_rows > 0){
    $row_rk_rekap = $qRkRekap->fetch_assoc();
    // id_kelas NULL = alumni/tidak ditempatkan pada periode itu -> gak ada kelas buat TA ini.
    $id_kelas_utk_rekap = is_null($row_rk_rekap['id_kelas']) ? 0 : (int)$row_rk_rekap['id_kelas'];
} else {
    // Gak ada riwayat sama sekali buat TA ini -> fallback ke kelas siswa saat ini.
    $id_kelas_utk_rekap = (int)($siswa['id_kelas'] ?? 0);
}

$qKelasRekap = ($id_kelas_utk_rekap > 0)
    ? $conn->query("SELECT nama_kelas, tingkat, kode_jurusan FROM kelas WHERE id='$id_kelas_utk_rekap' LIMIT 1")
    : null;
$dKelasRekap = ($qKelasRekap && $qKelasRekap->num_rows > 0) ? $qKelasRekap->fetch_assoc() : [];

// --- WALI KELAS SESUAI TA YANG DICETAK ---
// Coba ambil dari riwayat_wali_kelas (histori resmi per tahun), fallback ke wali kelas saat ini
// kalau histori buat tahun itu belum ada.
$nama_wali = '-';
$nip_wali = '-';
if($id_kelas_utk_rekap > 0){
    $tahun_label_rekap_safe = mysqli_real_escape_string($conn, $ta['tahun'] ?? '');
    $qWaliRekap = $conn->query("SELECT g.nama_guru, g.nip FROM riwayat_wali_kelas rw
                                 JOIN guru g ON g.id = rw.id_guru
                                 WHERE rw.id_kelas='$id_kelas_utk_rekap' AND rw.tahun='$tahun_label_rekap_safe'
                                 LIMIT 1");
    if($qWaliRekap && $qWaliRekap->num_rows > 0){
        $dWaliRekap = $qWaliRekap->fetch_assoc();
        $nama_wali = $dWaliRekap['nama_guru'];
        $nip_wali = $dWaliRekap['nip'];
    } else {
        $qWaliNow = $conn->query("SELECT g.nama_guru, g.nip FROM kelas k LEFT JOIN guru g ON g.id = k.id_wali_kelas WHERE k.id='$id_kelas_utk_rekap' LIMIT 1");
        if($qWaliNow && $qWaliNow->num_rows > 0){
            $dWaliNow = $qWaliNow->fetch_assoc();
            $nama_wali = $dWaliNow['nama_guru'] ?? '-';
            $nip_wali = $dWaliNow['nip'] ?? '-';
        }
    }
}

$tingkat = $dKelasRekap['tingkat'] ?: 'X';
$jurusan = $dKelasRekap['kode_jurusan'] ?: '';
$nama_kelas = $dKelasRekap['nama_kelas'] ?: '-';

if(empty($jurusan)){
    if(stripos($nama_kelas,'RPL')!==false) $jurusan='RPL';
    elseif(stripos($nama_kelas,'AKL')!==false) $jurusan='AKL';
    elseif(stripos($nama_kelas,'AKT')!==false) $jurusan='AKT';
    elseif(stripos($nama_kelas,'BR')!==false) $jurusan='BR';
    elseif(stripos($nama_kelas,'MP')!==false) $jurusan='MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan);

$qIden = $conn->query("SELECT * FROM identitas_sekolah LIMIT 1");
$iden = ($qIden && $qIden->num_rows > 0) ? $qIden->fetch_assoc() : [];

$tahun_ajaran = $ta['tahun'] ?? ($iden['tahun_ajaran'] ?? ($iden['tahun_pelajaran'] ?? '-'));
$semester = $ta['semester'] ?? ($iden['semester_aktif'] ?? ($iden['semester'] ?? '-'));
$nama_kepsek = $iden['nama_kepsek'] ?? ($iden['kepala_sekolah'] ?? '-');
$nip_kepsek = $iden['nip_kepsek'] ?? '-';

$kolom_jurusan = 'kode_jurusan';
$cekCols = $conn->query("SHOW COLUMNS FROM mapel");
$cols = [];
if($cekCols){ while($c=$cekCols->fetch_assoc()) $cols[]=$c['Field']; }
if(!in_array('kode_jurusan',$cols)){
    if(in_array('jurusan',$cols)) $kolom_jurusan = 'jurusan';
    else $kolom_jurusan = '';
}

if($kolom_jurusan){
    $kondisi = "m.tingkat='$tingkat_safe'
                AND m.nama_mapel NOT LIKE '%PKL%'
                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                AND m.nama_mapel NOT LIKE '%Prakerin%'
                AND (m.kelompok='A' OR m.$kolom_jurusan='$jurusan_safe' OR m.$kolom_jurusan='' OR m.$kolom_jurusan IS NULL)";
} else {
    $kondisi = "m.tingkat='$tingkat_safe'
                AND m.nama_mapel NOT LIKE '%PKL%'
                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                AND m.nama_mapel NOT LIKE '%Prakerin%'
                AND (m.kelompok='A' OR m.nama_mapel LIKE '%$jurusan_safe%')";
}

$qNilai = $conn->query("SELECT DISTINCT m.id AS id_mapel, m.nama_mapel, m.kkm, m.kelompok, m.urutan,
                               n.nilai_t, n.nilai_p, n.nilai_k, n.nilai_sikap, n.status_validasi
                        FROM mapel m
                        LEFT JOIN nilai n ON m.id = n.id_mapel AND n.id_siswa='$id_siswa_safe' AND n.id_tahun_ajaran='$id_ta_rekap_safe'
                        WHERE $kondisi
                        ORDER BY CASE WHEN m.kelompok='A' THEN 1 WHEN m.kelompok='B' THEN 2 ELSE 3 END ASC,
                                 m.urutan ASC, m.nama_mapel ASC");

$nama_pdf = 'Rekap_Nilai_'.namaFile($siswa['nama_siswa']).'.pdf';
$logo = logoSekolah();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= h($nama_pdf) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
body{font-family:'Arial',sans-serif;background:#525659;margin:0;padding:20px;color:#000;font-size:10pt}
.toolbar{max-width:210mm;margin:0 auto 15px;background:#eef6ff;text-align:center;padding:14px;border-radius:12px;font-family:Arial,sans-serif}
.toolbar button{border:0;border-radius:50px;padding:10px 24px;font-weight:700;cursor:pointer;margin:0 5px}
.btn-print{background:#6c757d;color:#fff}.btn-pdf{background:#dc3545;color:#fff}
.page{width:210mm;min-height:297mm;margin:0 auto;background:#fff;padding:18mm;box-sizing:border-box}
.kop{display:flex;align-items:center;justify-content:center;gap:16px;text-align:center;border-bottom:3px solid #000;padding-bottom:10px;margin-bottom:18px}
.kop img{width:75px;height:75px;object-fit:contain}.kop h2,.kop p{margin:0}.kop h2{font-size:14pt;text-transform:uppercase}
.judul{text-align:center;text-transform:uppercase;text-decoration:underline;margin:16px 0 18px;font-size:12pt;font-weight:bold}
.info{width:100%;margin-bottom:16px;font-size:10pt}.info td{padding:3px 5px}
.tbl{width:100%;border-collapse:collapse;margin-top:10px;font-size:9.5pt}.tbl th,.tbl td{border:1px solid #000;padding:5px;text-align:center;vertical-align:middle}.tbl th{background:#e0e0e0}.left{text-align:left!important}
.catatan{margin-top:14px;font-size:9pt}.ttd{width:100%;margin-top:38px;display:flex;justify-content:space-between;text-align:center;font-size:10pt}.ttd-box{width:45%}.space{height:68px}
@media print{body{background:#fff;padding:0}.toolbar{display:none}.page{margin:0;width:210mm;min-height:297mm}}
</style>
</head>
<body>
<div class="toolbar">
<button class="btn-pdf" id="btnPdf" onclick="downloadPdf()">📄 Download PDF</button>
<div style="font-family:Arial,sans-serif;font-size:12px;color:#555;margin-top:8px;">
    PDF akan otomatis terunduh setelah halaman terbuka.
</div>
</div>

<div class="page" id="rekapPage">
<div class="kop">
<img src="<?= h($logo) ?>" alt="Logo">
<div>
<h2><?= h(strtoupper($iden['nama_sekolah'] ?? 'SMK Jakarta Pusat 1')) ?></h2>
<p><?= h($iden['alamat_sekolah'] ?? '') ?>, <?= h($iden['kelurahan'] ?? '') ?>, Kec. <?= h($iden['kecamatan'] ?? '') ?></p>
<p>Telp: <?= h($iden['telepon'] ?? '') ?> | Email: <?= h($iden['email'] ?? '') ?></p>
</div>
</div>

<h3 class="judul">Rekap Nilai Siswa</h3>

<table class="info">
<tr>
<td width="15%">Nama Siswa</td><td width="2%">:</td><td width="33%"><?= h(namaRapi($siswa['nama_siswa'])) ?></td>
<td width="15%">Kelas</td><td width="2%">:</td><td width="33%"><?= h($nama_kelas) ?></td>
</tr>
<tr>
<td>NIS</td><td>:</td><td><?= h($siswa['nis'] ?? '-') ?></td>
<td>NISN</td><td>:</td><td><?= h($siswa['nisn'] ?? '-') ?></td>
</tr>
<tr>
<td>Tahun Ajaran</td><td>:</td><td><?= h($tahun_ajaran) ?></td>
<td>Semester</td><td>:</td><td><?= h(strtoupper($semester)) ?></td>
</tr>
</table>

<table class="tbl">
<thead>
<tr>
<th>No</th><th>Mata Pelajaran</th><th>KKM</th><th>Rata TP</th><th>STS</th><th>SAS</th><th>Sikap</th><th>Nilai Akhir</th><th>Predikat</th>
</tr>
</thead>
<tbody>
<?php $no=1; if($qNilai && $qNilai->num_rows>0): while($r=$qNilai->fetch_assoc()):
$kkm=(int)($r['kkm']??75); $tp=(int)($r['nilai_t']??0); $sts=(int)($r['nilai_p']??0); $sas=(int)($r['nilai_k']??0); $sikap=(int)($r['nilai_sikap']??0);
if($sts==0 && $sas==0) $na=$tp; elseif($sas==0) $na=round(((4*$tp)+(2*$sts))/6); else $na=round(((4*$tp)+(2*$sts)+(4*$sas))/10);
if($na==0) $pred='-'; elseif($na>=90) $pred='A'; elseif($na>=80) $pred='B'; elseif($na>=$kkm) $pred='C'; else $pred='D';
?>
<tr>
<td><?= $no++ ?></td>
<td class="left"><?= h($r['nama_mapel']) ?></td>
<td><?= $kkm ?></td>
<td><?= $tp>0?$tp:'-' ?></td>
<td><?= $sts>0?$sts:'-' ?></td>
<td><?= $sas>0?$sas:'-' ?></td>
<td><?= $sikap>0?$sikap:'-' ?></td>
<td><?= $na>0?$na:'-' ?></td>
<td><?= h($pred) ?></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="9" style="padding:20px">Data nilai belum tersedia.</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="catatan">Catatan: Rekap nilai ini digunakan sebagai informasi perkembangan nilai siswa dan bukan merupakan rapor resmi.</div>

<div class="ttd">
<div class="ttd-box">
Mengetahui,<br>Wali Kelas,
<div class="space"></div>
<u><?= h($nama_wali) ?></u><br>
NIP. <?= h($nip_wali) ?>
</div>
<div class="ttd-box">
Jakarta, <?= tglIndo(date('Y-m-d')) ?><br>Kepala Sekolah,
<div class="space"></div>
<u><?= h($nama_kepsek) ?></u><br>
NIP. <?= h($nip_kepsek) ?>
</div>
</div>
</div>

<script>
async function downloadPdf(){
const btn=document.getElementById('btnPdf'), old=btn.innerHTML; btn.innerHTML='⏳ Membuat PDF...'; btn.disabled=true;
const {jsPDF}=window.jspdf; const pdf=new jsPDF('p','mm','a4'); const page=document.getElementById('rekapPage');
const clone=page.cloneNode(true); clone.style.width='210mm'; clone.style.minHeight='297mm'; clone.style.margin='0'; clone.style.background='#fff'; clone.style.boxSizing='border-box';
const wrap=document.createElement('div'); wrap.style.position='fixed'; wrap.style.left='0'; wrap.style.top='0'; wrap.style.width='210mm'; wrap.style.background='#fff'; wrap.style.zIndex='-9999'; wrap.appendChild(clone); document.body.appendChild(wrap);
await new Promise(r=>setTimeout(r,250));
const canvas=await html2canvas(clone,{scale:2,useCORS:true,allowTaint:true,backgroundColor:'#fff',scrollX:0,scrollY:0,windowWidth:clone.scrollWidth,windowHeight:clone.scrollHeight});
const img=canvas.toDataURL('image/jpeg',0.98); const w=210; const h=(canvas.height*w)/canvas.width; pdf.addImage(img,'JPEG',0,0,w,h);
document.body.removeChild(wrap); pdf.save("<?= h($nama_pdf) ?>"); btn.innerHTML=old; btn.disabled=false;
}

window.addEventListener('load', function(){
    setTimeout(function(){
        downloadPdf();
    }, 600);
});
</script>
</body>
</html>