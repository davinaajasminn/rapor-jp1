<?php
include '../../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$jurusan = $_GET['jurusan'] ?? '';
$tingkat = $_GET['tingkat'] ?? 'XII';

$jurusan_safe = mysqli_real_escape_string($conn, $jurusan);
$tingkat_safe = mysqli_real_escape_string($conn, $tingkat);

// FIX: terima id_tahun_ajaran dari cetak_rapor.php. Tanpa ini, JOIN ke data_pkl mengambil
// SEMUA baris siswa di data_pkl lintas tahun ajaran, bikin siswa yang punya lebih dari 1
// baris data_pkl (misal Ganjil & Genap) muncul DOBEL di daftar ZIP multi kelas.
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

if(empty($jurusan_safe) || empty($tingkat_safe)){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Jurusan dan tingkat wajib dipilih</h2>
            <p>Silakan kembali ke halaman cetak rapor dan pilih jurusan serta tingkat terlebih dahulu.</p>
            <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p>
          </div>";
    exit;
}

function bersih_nama_file($text){
    $text = preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$text);
    $text = preg_replace('/\s+/', '_', trim($text));
    return $text ?: 'file';
}

$q = $conn->query("SELECT s.id, s.nama_siswa, s.nisn, s.nis, k.nama_kelas
                   FROM siswa s
                   JOIN kelas k ON s.id_kelas = k.id
                   JOIN data_pkl p ON p.id_siswa = s.id AND p.id_tahun_ajaran = '$id_ta_pilih_safe'
                   WHERE k.tingkat = '$tingkat_safe'
                   AND k.kode_jurusan = '$jurusan_safe'
                   AND p.status_validasi_pkl = 1
                   AND p.tp1_skor IS NOT NULL AND p.tp1_skor != ''
                   AND p.tp2_skor IS NOT NULL AND p.tp2_skor != ''
                   AND p.tp3_skor IS NOT NULL AND p.tp3_skor != ''
                   AND p.tp4_skor IS NOT NULL AND p.tp4_skor != ''
                   ORDER BY k.nama_kelas ASC, s.nama_siswa ASC");

$data_kelas = [];
if($q){
    while($row = $q->fetch_assoc()){
        $data_kelas[$row['nama_kelas']][] = $row;
    }
}

$total_siswa = 0;
foreach($data_kelas as $rows){
    $total_siswa += count($rows);
}

if($total_siswa == 0){
    echo "<div style='font-family:Arial; max-width:760px; margin:50px auto; padding:24px; border:1px solid #f0aeb8; background:#fff5f6; color:#842029; border-radius:12px;'>
            <h2>Belum ada rapor kelas XII yang siap dibuat ZIP</h2>
            <p>Pastikan nilai mapel akademik sudah lengkap/valid dan data PKL sudah valid.</p>
            <p>Jurusan: <b>".htmlspecialchars($jurusan_safe)."</b> | Tingkat: <b>".htmlspecialchars($tingkat_safe)."</b></p>
            <p style='margin-top:18px;'><a href='../index.php?page=cetak_rapor&jurusan=".urlencode($jurusan_safe)."&tingkat=".urlencode($tingkat_safe)."' style='display:inline-block;padding:10px 18px;border-radius:999px;background:#eaf3ff;color:#0d385e;text-decoration:none;font-weight:bold;border:1px solid #b8cde5;'>← Kembali ke Cetak Rapor</a></p>
          </div>";
    exit;
}

$nama_zip = 'Rapor_PKL_' . bersih_nama_file($tingkat_safe . '_' . $jurusan_safe) . '.zip';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>ZIP Rapor PKL <?= htmlspecialchars($tingkat_safe.' '.$jurusan_safe) ?></title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    body {
        margin:0;
        padding:0;
        background:#f3f7fb;
        font-family: Arial, sans-serif;
        color:#0f172a;
    }

    .toolbar {
        position: sticky;
        top: 0;
        z-index: 9999;
        background: #ffffff;
        border-bottom: 1px solid #dbe7f3;
        padding: 14px 18px;
        box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }

    .toolbar-inner {
        max-width: 1100px;
        margin: 0 auto;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
        flex-wrap:wrap;
    }

    .toolbar h2 {
        margin:0;
        font-size:18px;
        color:#0d385e;
    }

    .toolbar p {
        margin:4px 0 0;
        color:#64748b;
        font-size:13px;
    }

    .btn {
        border:0;
        padding:10px 22px;
        border-radius:999px;
        font-weight:bold;
        cursor:pointer;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
    }

    .btn-primary {
        background:#6FA7DF;
        color:#fff;
    }

    .btn-success {
        background:#198754;
        color:#fff;
    }

    .btn-back {
        background:#eaf3ff;
        color:#0d385e;
        border:1px solid #b8cde5;
    }

    .btn:disabled {
        opacity:.65;
        cursor:not-allowed;
    }

    .kelas-section {
        max-width: 1100px;
        margin: 18px auto;
        background:#fff;
        border-radius:18px;
        box-shadow:0 8px 20px rgba(80,115,155,.12);
        overflow:hidden;
    }

    .kelas-header {
        background:#fff7e6;
        color:#7A5200;
        padding:14px 18px;
        border-bottom:1px solid #ffe2ad;
        font-weight:bold;
    }

    .siswa-list {
        padding:14px 18px;
        display:grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap:10px;
    }

    .siswa-item {
        border:1px solid #e2e8f0;
        border-radius:12px;
        padding:10px 12px;
        color:#0f172a;
        background:#f8fafc;
        font-size:13px;
    }

    .status {
        max-width: 1100px;
        margin: 18px auto;
        padding: 14px 16px;
        border-radius: 14px;
        background: #fff7e6;
        border: 1px solid #ffe2ad;
        color: #7a5200;
        font-size: 14px;
        display: none;
    }

    .status.ok {
        background:#ecfdf3;
        border-color:#bbf7d0;
        color:#166534;
    }

    .status.err {
        background:#fff5f6;
        border-color:#f0aeb8;
        color:#842029;
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

<div class="toolbar">
    <div class="toolbar-inner">
        <div>
            <h2>ZIP Rapor PKL <?= htmlspecialchars($tingkat_safe.' '.$jurusan_safe) ?></h2>
            <p>Hasil akhir: 1 ZIP → folder per kelas → file PDF rapor per siswa.</p>
        </div>
        <div>
            <a href="../index.php?page=cetak_rapor&jurusan=<?= urlencode($jurusan_safe) ?>&tingkat=<?= urlencode($tingkat_safe) ?>" class="btn btn-back">← Kembali</a>
            <button id="btnZip" onclick="downloadZipRapor()" class="btn btn-success">📦 Download ZIP</button>
        </div>
    </div>
</div>

<?php foreach($data_kelas as $nama_kelas => $rows): ?>
    <div class="kelas-section">
        <div class="kelas-header">
            <?= htmlspecialchars($nama_kelas) ?> — <?= count($rows) ?> siswa
        </div>
        <div class="siswa-list">
            <?php foreach($rows as $s): ?>
                <div class="siswa-item">
                    <b><?= htmlspecialchars($s['nama_siswa']) ?></b><br>
                    <small>NIS: <?= htmlspecialchars($s['nis'] ?: '-') ?></small><br>
                    <small>NISN: <?= htmlspecialchars($s['nisn'] ?: '-') ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div id="statusBox" class="status"></div>
<div id="renderArea"></div>

<script>
const dataKelas = <?= json_encode($data_kelas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const namaZip = <?= json_encode($nama_zip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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
    return String(text || 'file')
        .replace(/[^a-zA-Z0-9 _-]/g, '')
        .trim()
        .replace(/\s+/g, '_') || 'file';
}

function copyStyles(doc) {
    doc.querySelectorAll('style').forEach((styleTag) => {
        const cloned = document.createElement('style');
        cloned.textContent = styleTag.textContent;
        renderArea.appendChild(cloned);
    });
}

async function htmlPageToCanvas(pageNode) {
    return await html2canvas(pageNode, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: pageNode.scrollWidth,
        windowHeight: pageNode.scrollHeight
    });
}

async function raporHtmlToPdfBlob(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const pages = doc.querySelectorAll('.page');

    if (!pages || pages.length === 0) {
        const plainText = (doc.body && doc.body.innerText) ? doc.body.innerText.trim() : '';
        throw new Error(plainText || 'Rapor belum siap / halaman .page tidak ditemukan.');
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
        await new Promise(r => setTimeout(r, 80));

        const canvas = await htmlPageToCanvas(pageClone);
        const imgData = canvas.toDataURL('image/jpeg', 0.98);

        if (i > 0) pdf.addPage('a4', 'p');
        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);

        renderArea.removeChild(pageClone);
    }

    return pdf.output('blob');
}

async function fetchRaporPersonal(idSiswa) {
    const url = `cetak_rapor_pkl.php?id=${encodeURIComponent(idSiswa)}&id_siswa=${encodeURIComponent(idSiswa)}&id_tahun_ajaran=${encodeURIComponent(idTahunAjaran)}&zip=1`;
    const res = await fetch(url, { cache: 'no-store' });

    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }

    return await res.text();
}

async function downloadZipRapor() {
    btnZip.disabled = true;
    const oldText = btnZip.innerHTML;
    btnZip.innerHTML = '⏳ Menyiapkan ZIP...';

    const zip = new JSZip();
    let sukses = 0;
    let gagal = [];

    const kelasNames = Object.keys(dataKelas);

    for (let k = 0; k < kelasNames.length; k++) {
        const namaKelas = kelasNames[k];
        const folder = zip.folder(sanitizeFileName(namaKelas));
        const siswaRows = dataKelas[namaKelas] || [];

        for (let i = 0; i < siswaRows.length; i++) {
            const siswa = siswaRows[i];
            const namaSiswa = siswa.nama_siswa || ('siswa_' + siswa.id);
            const nis = siswa.nis || siswa.nisn || siswa.id;
            const filename = `Rapor_PKL_${sanitizeFileName(nis)}_${sanitizeFileName(namaSiswa)}.pdf`;

            btnZip.innerHTML = `⏳ Membuat PDF ${sukses + 1}/${<?= (int)$total_siswa ?>}: ${namaSiswa}`;
            showStatus(`Memproses folder <b>${namaKelas}</b> → <b>${namaSiswa}</b>...`);

            try {
                const html = await fetchRaporPersonal(siswa.id);
                const pdfBlob = await raporHtmlToPdfBlob(html);
                folder.file(filename, pdfBlob);
                sukses++;
            } catch (err) {
                console.error('Gagal membuat PDF:', siswa, err);
                gagal.push(`${namaKelas} - ${namaSiswa}`);
            }
        }
    }

    if (sukses === 0) {
        btnZip.innerHTML = oldText;
        btnZip.disabled = false;
        showStatus('Gagal membuat ZIP. Tidak ada rapor siswa yang berhasil dibuat. Cek validasi mapel akademik + PKL.', 'err');
        return;
    }

    btnZip.innerHTML = '📦 Mengemas ZIP...';
    showStatus(`Mengemas <b>${sukses}</b> file PDF ke ZIP <b>${namaZip}</b>...`);

    const zipBlob = await zip.generateAsync({ type: 'blob' });
    saveAs(zipBlob, namaZip);

    let pesan = `Berhasil membuat ZIP berisi <b>${sukses}</b> file PDF rapor siswa.`;
    if (gagal.length > 0) {
        pesan += `<br><br>Yang dilewati karena belum siap/error:<br>${gagal.map(n => '• ' + n).join('<br>')}`;
    }

    showStatus(pesan, gagal.length > 0 ? '' : 'ok');
    btnZip.innerHTML = oldText;
    btnZip.disabled = false;
}
</script>

</body>
</html>