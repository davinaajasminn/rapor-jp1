<?php
include '../../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$id_kelas = $_GET['id_kelas'] ?? '';
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

// FIX: terima id_tahun_ajaran dari cetak_rapor.php. Tanpa ini, JOIN ke data_pkl mengambil
// SEMUA baris siswa itu di data_pkl (lintas tahun ajaran), bikin siswa yang punya lebih
// dari 1 baris data_pkl (misal Ganjil & Genap) muncul DOBEL di daftar ZIP.
$id_ta_get = $_GET['id_tahun_ajaran'] ?? '';
if(!empty($id_ta_get)){
    $id_ta_safe = mysqli_real_escape_string($conn, $id_ta_get);
    $qTaPilih = $conn->query("SELECT id FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
} else {
    $qTaPilih = $conn->query("SELECT id FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
}
$ta_pilih = ($qTaPilih && $qTaPilih->num_rows > 0) ? $qTaPilih->fetch_assoc() : null;
$id_ta_pilih = $ta_pilih['id'] ?? 0;
$id_ta_pilih_safe = mysqli_real_escape_string($conn, $id_ta_pilih);

if(empty($id_ta_pilih)){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Tahun Ajaran tidak ditemukan</h2>
          <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p></div>";
    exit;
}

if(empty($id_kelas_safe)){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Kelas belum dipilih</h2>
            <p>Silakan kembali ke halaman cetak rapor dan pilih kelas terlebih dahulu.</p>
          <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p></div>";
    exit;
}

$qKelas = $conn->query("SELECT * FROM kelas WHERE id = '$id_kelas_safe' LIMIT 1");
$dKelas = ($qKelas && $qKelas->num_rows > 0) ? $qKelas->fetch_assoc() : null;

if(!$dKelas){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Kelas tidak ditemukan</h2>
          <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p></div>";
    exit;
}

$nama_kelas = $dKelas['nama_kelas'] ?? 'Kelas XII';
$tingkat = $dKelas['tingkat'] ?? explode(' ', trim($nama_kelas))[0];
$kode_jurusan = $dKelas['kode_jurusan'] ?? '';

function bersih_nama_file($text){
    $text = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$text);
    $text = preg_replace('/\s+/', '_', trim($text));
    return $text ?: 'file';
}

$nama_folder_zip = 'Rapor_Kelas_XII_' . bersih_nama_file($nama_kelas);

/*
    Ambil siswa yang PKL-nya sudah valid.
    Validasi mapel akademik tetap dicek lagi oleh cetak_rapor_pkl.php saat PDF personal dibuat.
    Kalau siswa belum memenuhi syarat rapor gabungan, dia akan dilewati otomatis di JS.
*/
$qSiswa = $conn->query("SELECT s.id, s.nama_siswa, s.nisn, s.nis
                        FROM siswa s
                        JOIN data_pkl p ON p.id_siswa = s.id AND p.id_tahun_ajaran = '$id_ta_pilih_safe'
                        WHERE s.id_kelas = '$id_kelas_safe'
                        AND p.status_validasi_pkl = 1
                        AND p.tp1_skor IS NOT NULL AND p.tp1_skor != ''
                        AND p.tp2_skor IS NOT NULL AND p.tp2_skor != ''
                        AND p.tp3_skor IS NOT NULL AND p.tp3_skor != ''
                        AND p.tp4_skor IS NOT NULL AND p.tp4_skor != ''
                        ORDER BY s.nama_siswa ASC");

$siswa_list = [];
if($qSiswa){
    while($r = $qSiswa->fetch_assoc()){
        $siswa_list[] = $r;
    }
}

if(count($siswa_list) == 0){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Belum ada rapor PKL yang siap dibuat ZIP</h2>
            <p>Pastikan siswa sudah punya 4 skor TP PKL dan status PKL sudah Valid.</p>
          <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Download ZIP Rapor Kelas XII - <?= htmlspecialchars($nama_kelas) ?></title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    body {
        margin: 0;
        padding: 0;
        background: #eef6ff;
        font-family: Arial, sans-serif;
        color: #0f172a;
    }

    .card {
        max-width: 860px;
        margin: 70px auto;
        background: #fff;
        border-radius: 18px;
        padding: 28px 32px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .12);
        border: 1px solid #dbeafe;
    }

    h2 {
        margin: 0 0 8px;
        color: #0d385e;
        font-size: 24px;
    }

    p {
        margin: 6px 0;
        color: #475569;
        line-height: 1.5;
    }

    .kelas-box {
        margin: 18px 0;
        padding: 14px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .siswa-list {
        margin-top: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .siswa-row {
        display: grid;
        grid-template-columns: 48px 1fr 180px;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 14px;
        align-items: center;
    }

    .siswa-row:last-child {
        border-bottom: none;
    }

    .siswa-head {
        background: #f1f5f9;
        font-weight: bold;
        color: #334155;
    }

    .btn {
        margin-top: 20px;
        border: none;
        background: #198754;
        color: white;
        padding: 12px 24px;
        border-radius: 999px;
        font-weight: bold;
        cursor: pointer;
        font-size: 15px;
    }

    .btn:disabled {
        opacity: .6;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: #eaf3ff;
        color: #0d385e;
        border: 1px solid #b8cde5;
        margin-left: 8px;
    }

    .status {
        margin-top: 16px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #fff7e6;
        border: 1px solid #ffe2ad;
        color: #7a5200;
        font-size: 14px;
        display: none;
    }

    .status.ok {
        background: #ecfdf3;
        border-color: #bbf7d0;
        color: #166534;
    }

    .status.err {
        background: #fff5f6;
        border-color: #f0aeb8;
        color: #842029;
    }

    #renderArea {
        position: fixed;
        left: -10000px;
        top: 0;
        width: 210mm;
        background: #fff;
        z-index: -1;
    }
</style>
</head>
<body>

<div class="card">
    <h2>Download ZIP Rapor Kelas XII</h2>
    <p>Sistem akan membuat 1 file ZIP sesuai nama kelas. Di dalam ZIP, setiap siswa mendapat 1 file PDF rapor sendiri.</p>

    <div class="kelas-box">
        <p><b>Kelas:</b> <?= htmlspecialchars($nama_kelas) ?></p>
        <p><b>Jumlah siswa PKL valid:</b> <?= count($siswa_list) ?> siswa</p>
        <p><b>Nama folder ZIP:</b> <?= htmlspecialchars($nama_folder_zip) ?></p>
    </div>

    <div class="siswa-list">
        <div class="siswa-row siswa-head">
            <div>No</div>
            <div>Nama Siswa</div>
            <div>NIS/NISN</div>
        </div>
        <?php $no=1; foreach($siswa_list as $s): ?>
            <div class="siswa-row">
                <div><?= $no++ ?></div>
                <div><?= htmlspecialchars($s['nama_siswa']) ?></div>
                <div><?= htmlspecialchars(($s['nis'] ?: '-') . ' / ' . ($s['nisn'] ?: '-')) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <button id="btnZip" class="btn" onclick="downloadZipRapor()">
        📦 Download ZIP Rapor Kelas XII (<?= count($siswa_list) ?> File)
    </button>

    <button class="btn btn-secondary" onclick="window.location.href='../index.php?page=cetak_rapor'">← Kembali</button>

    <div id="statusBox" class="status"></div>
</div>

<div id="renderArea"></div>

<script>
const siswaList = <?= json_encode($siswa_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const namaFolderZip = <?= json_encode($nama_folder_zip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const namaKelas = <?= json_encode($nama_kelas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const idTahunAjaran = <?= json_encode((string)$id_ta_pilih) ?>;

const btnZip = document.getElementById('btnZip');
const statusBox = document.getElementById('statusBox');
const renderArea = document.getElementById('renderArea');

function showStatus(text, mode = '') {
    statusBox.style.display = 'block';
    statusBox.className = 'status ' + mode;
    statusBox.innerHTML = text;
}

function sanitizeFileName(text) {
    return String(text || 'siswa')
        .replace(/[^a-zA-Z0-9 _-]/g, '')
        .trim()
        .replace(/\s+/g, '_') || 'siswa';
}

function copyStyles(doc) {
    doc.querySelectorAll('style').forEach((styleTag) => {
        const cloned = document.createElement('style');
        cloned.textContent = styleTag.textContent;
        renderArea.appendChild(cloned);
    });
}

async function htmlPageToCanvas(pageNode) {
    const canvas = await html2canvas(pageNode, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: pageNode.scrollWidth,
        windowHeight: pageNode.scrollHeight
    });
    return canvas;
}

async function raporHtmlToPdfBlob(html, siswa) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const pages = doc.querySelectorAll('.page');

    if (!pages || pages.length === 0) {
        const plainText = (doc.body && doc.body.innerText) ? doc.body.innerText.trim() : '';
        throw new Error(plainText || 'Rapor personal belum siap / tidak ada halaman .page.');
    }

    renderArea.innerHTML = '';
    copyStyles(doc);

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');

    for (let i = 0; i < pages.length; i++) {
        const pageClone = pages[i].cloneNode(true);

        pageClone.classList.remove('page-break');
        pageClone.style.width = '210mm';
        pageClone.style.minHeight = '297mm';
        pageClone.style.height = '297mm';
        pageClone.style.margin = '0 auto';
        pageClone.style.background = '#ffffff';
        pageClone.style.boxSizing = 'border-box';

        renderArea.appendChild(pageClone);

        const canvas = await htmlPageToCanvas(pageClone);
        const imgData = canvas.toDataURL('image/jpeg', 0.98);

        if (i > 0) pdf.addPage();
        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);

        renderArea.removeChild(pageClone);
    }

    return pdf.output('blob');
}

async function fetchRaporPersonal(siswa) {
    const url = `cetak_rapor_pkl.php?id=${encodeURIComponent(siswa.id)}&id_siswa=${encodeURIComponent(siswa.id)}&id_tahun_ajaran=${encodeURIComponent(idTahunAjaran)}&zip=1`;
    const res = await fetch(url, { cache: 'no-store' });

    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }

    return await res.text();
}

async function downloadZipRapor() {
    btnZip.disabled = true;
    const originalText = btnZip.innerHTML;
    btnZip.innerHTML = '⏳ Menyiapkan ZIP...';

    const zip = new JSZip();
    const folder = zip.folder(namaFolderZip);
    let sukses = 0;
    let gagal = [];

    for (let i = 0; i < siswaList.length; i++) {
        const siswa = siswaList[i];
        const namaSiswa = siswa.nama_siswa || ('siswa_' + siswa.id);
        const nis = siswa.nis || siswa.nisn || siswa.id;
        const filename = `Rapor_Kelas_XII_${sanitizeFileName(nis)}_${sanitizeFileName(namaSiswa)}.pdf`;

        btnZip.innerHTML = `⏳ Membuat PDF ${i + 1}/${siswaList.length}: ${namaSiswa}`;
        showStatus(`Memproses <b>${namaSiswa}</b> (${i + 1}/${siswaList.length})...`);

        try {
            const html = await fetchRaporPersonal(siswa);
            const pdfBlob = await raporHtmlToPdfBlob(html, siswa);
            folder.file(filename, pdfBlob);
            sukses++;
        } catch (err) {
            console.error('Gagal membuat PDF:', siswa, err);
            gagal.push(namaSiswa);
        }
    }

    if (sukses === 0) {
        btnZip.innerHTML = originalText;
        btnZip.disabled = false;
        showStatus('Gagal membuat ZIP. Tidak ada rapor siswa yang berhasil dibuat. Cek validasi mapel + PKL setiap siswa.', 'err');
        return;
    }

    btnZip.innerHTML = '📦 Mengemas file ZIP...';
    showStatus(`Mengemas <b>${sukses}</b> file PDF ke dalam ZIP...`);

    const zipBlob = await zip.generateAsync({ type: 'blob' });
    saveAs(zipBlob, `${namaFolderZip}.zip`);

    let pesan = `Berhasil membuat ZIP berisi <b>${sukses}</b> file PDF rapor siswa.`;
    if (gagal.length > 0) {
        pesan += `<br><br>Yang dilewati karena belum siap/error:<br>${gagal.map(n => '• ' + n).join('<br>')}`;
    }

    showStatus(pesan, gagal.length > 0 ? '' : 'ok');
    btnZip.innerHTML = originalText;
    btnZip.disabled = false;
}
</script>

</body>
</html>