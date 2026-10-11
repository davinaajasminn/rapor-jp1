<?php
include '../../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$jurusan = $_GET['jurusan'] ?? '';
$tingkat = $_GET['tingkat'] ?? '';

if($jurusan == '' || $tingkat == ''){
    die("Pilih jurusan dan tingkat terlebih dahulu!");
}

$jurusan_safe = mysqli_real_escape_string($conn, $jurusan);
$tingkat_safe = mysqli_real_escape_string($conn, $tingkat);

function h_absen($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function teks_nama_absen($nama){
    $nama = trim((string)$nama);
    if($nama === '') return '';
    // Biar nama siswa tidak kapital semua.
    // Format jadi Title Case: Davina Jasmin Azzahra.
    return ucwords(strtolower($nama));
}

function ambil_ttd_guru_absen($row){
    if(!$row) return '';
    $kandidat = ['ttd_digital', 'ttd', 'foto_ttd', 'file_ttd', 'signature'];
    foreach($kandidat as $k){
        if(isset($row[$k]) && trim((string)$row[$k]) !== ''){
            return trim((string)$row[$k]);
        }
    }
    return '';
}

function src_ttd_absen($file){
    $file = trim((string)$file);
    if($file === '') return '';

    if(preg_match('/^(https?:\/\/|data:image)/i', $file)){
        return $file;
    }

    $file = str_replace('\\', '/', $file);

    if(strpos($file, '../') === 0){
        return $file;
    }

    if(strpos($file, 'assets/') === 0 || strpos($file, 'uploads/') === 0){
        return '../../'.$file;
    }

    return '../../assets/uploads/'.$file;
}

function src_logo_absen(){
    $candidates = [
        '../../assets/logo.png',
        '../../assets/img/logo.png',
        '../../assets/images/logo.png',
        '../../uploads/logo.png',
        '../../assets/logo.jpg',
        '../../assets/img/logo.jpg'
    ];
    foreach($candidates as $p){
        if(file_exists($p)){
            return $p;
        }
    }
    // fallback mengikuti yang dipakai rapor kamu
    return '../../assets/logo.png';
}

function tanggal_indo_absen($tanggal){
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
    return (int)$pecah[2].' '.$bulan[(int)$pecah[1]].' '.$pecah[0];
}

function nama_file_absen($nama){
    $nama = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$nama);
    $nama = trim(preg_replace('/\s+/', '_', $nama));
    return $nama ?: 'kelas';
}


$qIden = $conn->query("SELECT * FROM identitas_sekolah LIMIT 1");
$iden = ($qIden && $qIden->num_rows > 0) ? $qIden->fetch_assoc() : [];

// Ambil tahun ajaran: pakai id_ta dari filter kalau dikirim, kalau tidak fallback ke yang aktif
$id_ta = $_GET['id_ta'] ?? '';
if(!empty($id_ta)){
    $id_ta_safe = mysqli_real_escape_string($conn, $id_ta);
    $qTA = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
} else {
    $qTA = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
}
$ta = ($qTA && $qTA->num_rows > 0) ? $qTA->fetch_assoc() : null;
$id_ta_pakai = $ta['id'] ?? null;

$semester_aktif = $ta['semester'] ?? ($iden['semester_aktif'] ?? ($iden['semester'] ?? 'Ganjil'));
$tahun_pelajaran = $ta['tahun'] ?? ($iden['tahun_pelajaran'] ?? ($iden['tahun_ajaran'] ?? date('Y').'/'.(date('Y')+1)));
$semester_safe = mysqli_real_escape_string($conn, $semester_aktif);

$qKelas = $conn->query("SELECT k.id AS id_kelas_fix, k.nama_kelas, k.id_wali_kelas, g.*
                        FROM kelas k
                        LEFT JOIN guru g ON k.id_wali_kelas = g.id
                        WHERE k.kode_jurusan='$jurusan_safe' AND k.tingkat='$tingkat_safe'
                        ORDER BY k.nama_kelas ASC");

if(!$qKelas || $qKelas->num_rows == 0){
    die("Tidak ada kelas untuk jurusan dan tingkat yang dipilih.");
}

$nama_folder_zip = 'Rekap_Absensi_'.$tingkat_safe.'_'.$jurusan_safe;
$src_logo = src_logo_absen();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal Rekap Absensi <?= h_absen($tingkat.' '.$jurusan) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; margin: 0; padding: 0; background: #525659; }
        .no-print { text-align:center; padding:15px; background:#e3f2fd; margin-bottom:10px; position: sticky; top: 0; z-index: 999; border-bottom: 2px solid #b6d4fe; font-family: Arial, sans-serif; }
        .no-print button { padding:10px 20px; font-weight:bold; cursor:pointer; border: none; border-radius: 5px; margin: 0 5px; }
        #btn-print { background: #6c757d; color: white; }
        #btn-zip { background: #198754; color: white; }
        .lembar-absen { width: 210mm; min-height: 296mm; padding: 20mm; margin: 0 auto 15px auto; box-sizing: border-box; background: white; page-break-after: always; }
        .kop-surat { display: flex; align-items: center; justify-content: center; gap: 18px; margin-bottom: 20px; border-bottom: 3px solid #000; padding-bottom: 10px; text-align: center; }
        .kop-logo { width: 75px; height: 75px; object-fit: contain; }
        .kop-text h2, .kop-text p { margin: 0; padding: 0; }
        .kop-text h2 { font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .info-rekap { width: 100%; margin-bottom: 20px; font-size: 11pt; }
        .info-rekap td { padding: 2px 5px; }
        .tabel-data { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .tabel-data th, .tabel-data td { border: 1px solid #000; padding: 8px; text-align: center; }
        .tabel-data th { background-color: #f2f2f2; font-weight: bold; }
        .tabel-data td.text-left { text-align: left; padding-left: 10px; }
        .ttd-area { float: right; width: 300px; text-align: center; margin-top: 40px; }
        .ttd-img { height: 70px; max-width: 180px; object-fit: contain; margin: 8px auto 2px auto; display:block; }
        .ttd-space { height: 80px; }
        @media print {
            body { background: none; }
            .no-print { display: none; }
            .lembar-absen { margin: 0; page-break-after: always; }
            .lembar-absen:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button id="btn-print" onclick="window.print()">🖨️ Cetak Biasa</button>
    <button id="btn-zip" onclick="downloadZipAbsensi()">📦 Download ZIP Absensi</button>
    <p style="margin: 7px 0 0 0; font-size: 12px; color:#555;">ZIP akan berisi file PDF rekap absensi per kelas.</p>
</div>

<?php while($k = $qKelas->fetch_assoc()): ?>
    <?php
    $id_kelas_fix = $k['id_kelas_fix'];
    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas_fix);

    $nama_kelas = $k['nama_kelas'] ?? '-';
    $wali_kelas = $k['nama_guru'] ?? 'Belum Ditentukan';
    $ttd_wali = ambil_ttd_guru_absen($k);
    $src_ttd_wali = src_ttd_absen($ttd_wali);
    $nama_file_kelas = nama_file_absen($nama_kelas);

    if(!empty($id_ta_pakai)){
        $id_ta_pakai_safe = mysqli_real_escape_string($conn, $id_ta_pakai);
        $qSiswa = $conn->query("SELECT s.id, s.nis, s.nama_siswa,
                                       COALESCE(MAX(a.sakit), 0) AS sakit,
                                       COALESCE(MAX(a.izin), 0) AS izin,
                                       COALESCE(MAX(a.alpha), 0) AS alpha
                                FROM riwayat_kelas r
                                INNER JOIN siswa s ON r.id_siswa = s.id
                                LEFT JOIN absensi a ON s.id = a.id_siswa AND a.semester = '$semester_safe' AND a.id_tahun_ajaran = '$id_ta_pakai_safe'
                                WHERE r.id_kelas = '$id_kelas_safe' AND r.id_tahun_ajaran = '$id_ta_pakai_safe' AND r.id_siswa != 0
                                GROUP BY s.id, s.nis, s.nama_siswa
                                ORDER BY s.nama_siswa ASC");
    } else {
        $qSiswa = $conn->query("SELECT s.id, s.nis, s.nama_siswa, 
                                       COALESCE(MAX(a.sakit), 0) AS sakit, 
                                       COALESCE(MAX(a.izin), 0) AS izin, 
                                       COALESCE(MAX(a.alpha), 0) AS alpha
                                FROM siswa s 
                                LEFT JOIN absensi a ON s.id = a.id_siswa AND a.semester = '$semester_safe'
                                WHERE s.id_kelas = '$id_kelas_safe' 
                                GROUP BY s.id, s.nis, s.nama_siswa
                                ORDER BY s.nama_siswa ASC");
    }
    ?>
    <div class="lembar-absen" data-kelas="<?= h_absen($nama_file_kelas) ?>">
        <div class="kop-surat">
            <img src="<?= h_absen($src_logo) ?>" class="kop-logo" alt="Logo Sekolah">
            <div class="kop-text">
                <h2><?= h_absen(strtoupper($iden['nama_sekolah'] ?? 'SMK Jakarta Pusat 1')) ?></h2>
                <p><?= h_absen($iden['alamat_sekolah'] ?? '') ?>, <?= h_absen($iden['kelurahan'] ?? '') ?>, Kec. <?= h_absen($iden['kecamatan'] ?? '') ?></p>
                <p>Telp: <?= h_absen($iden['telepon'] ?? '') ?> | Email: <?= h_absen($iden['email'] ?? '') ?></p>
            </div>
        </div>

        <h3 style="text-align: center; text-transform: uppercase; margin-bottom: 20px; text-decoration: underline;">Rekapitulasi Absensi Siswa</h3>

        <table class="info-rekap">
            <tr>
                <td width="15%"><b>Kelas</b></td><td width="2%">:</td><td width="33%"><?= h_absen($nama_kelas) ?></td>
                <td width="15%"><b>Semester</b></td><td width="2%">:</td><td width="33%"><?= h_absen(strtoupper($semester_aktif)) ?></td>
            </tr>
            <tr>
                <td><b>Wali Kelas</b></td><td>:</td><td><?= h_absen($wali_kelas) ?></td>
                <td><b>Tahun Ajaran</b></td><td>:</td><td><?= h_absen($tahun_pelajaran) ?></td>
            </tr>
        </table>

        <table class="tabel-data">
            <thead>
                <tr>
                    <th width="5%" rowspan="2">No</th>
                    <th width="15%" rowspan="2">NIS</th>
                    <th width="50%" rowspan="2">Nama Siswa</th>
                    <th width="30%" colspan="3">Ketidakhadiran (Hari)</th>
                </tr>
                <tr>
                    <th width="10%">Sakit</th><th width="10%">Izin</th><th width="10%">Alpha</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; if($qSiswa && $qSiswa->num_rows > 0): while($s = $qSiswa->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= h_absen($s['nis']) ?></td>
                    <td class="text-left"><?= h_absen(teks_nama_absen($s['nama_siswa'])) ?></td>
                    <td><?= h_absen($s['sakit']) ?></td>
                    <td><?= h_absen($s['izin']) ?></td>
                    <td><?= h_absen($s['alpha']) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" style="padding: 20px;">Belum ada data siswa di kelas ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="ttd-area">
            Jakarta, <?= tanggal_indo_absen(date('Y-m-d')) ?><br>
            Wali Kelas,
            <?php if(!empty($src_ttd_wali)): ?>
                <img src="<?= h_absen($src_ttd_wali) ?>" class="ttd-img" alt="Tanda Tangan Wali Kelas">
            <?php else: ?>
                <div class="ttd-space"></div>
            <?php endif; ?>
            <b><u><?= h_absen($wali_kelas) ?></u></b>
        </div>
    </div>
<?php endwhile; ?>

<script>
async function absenToPdfBlob(lembar) {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');

    const clone = lembar.cloneNode(true);
    clone.style.width = '210mm';
    clone.style.minHeight = '297mm';
    clone.style.height = '297mm';
    clone.style.margin = '0';
    clone.style.background = '#ffffff';
    clone.style.boxSizing = 'border-box';
    clone.style.pageBreakAfter = 'auto';

    const wrap = document.createElement('div');
    wrap.style.position = 'fixed';
    wrap.style.left = '-10000px';
    wrap.style.top = '0';
    wrap.style.width = '210mm';
    wrap.style.height = '297mm';
    wrap.style.background = '#ffffff';
    wrap.appendChild(clone);
    document.body.appendChild(wrap);

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
    pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);

    document.body.removeChild(wrap);

    return pdf.output('blob');
}

async function downloadZipAbsensi() {
    const btn = document.getElementById('btn-zip');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Menyiapkan ZIP...';
    btn.disabled = true;

    const zip = new JSZip();
    const folderName = "<?= $nama_folder_zip ?>";
    const folder = zip.folder(folderName);
    const lembarList = document.querySelectorAll('.lembar-absen');

    if(lembarList.length === 0){
        alert('Tidak ada data kelas yang bisa dibuat.');
        btn.innerHTML = originalText;
        btn.disabled = false;
        return;
    }

    for(let i = 0; i < lembarList.length; i++){
        const lembar = lembarList[i];
        const kelas = lembar.getAttribute('data-kelas') || ('Kelas_' + (i + 1));
        const filename = `Rekap_Absensi_${kelas}.pdf`;

        btn.innerHTML = `⏳ Memproses PDF ${i + 1} dari ${lembarList.length}...`;
        const blob = await absenToPdfBlob(lembar);
        folder.file(filename, blob);
    }

    btn.innerHTML = '⏳ Membuat ZIP...';
    const content = await zip.generateAsync({ type: 'blob' });
    saveAs(content, folderName + ".zip");

    btn.innerHTML = originalText;
    btn.disabled = false;
}
</script>

</body>
</html>