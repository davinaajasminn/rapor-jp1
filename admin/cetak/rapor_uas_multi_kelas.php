<?php
include '../../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$jenis = 'uas';
$label_rapor = 'UAS';

$id_kelas = $_GET['id_kelas'] ?? '';
$jurusan = $_GET['jurusan'] ?? '';
$tingkat = $_GET['tingkat'] ?? '';
$id_tahun_ajaran_get = $_GET['id_tahun_ajaran'] ?? '';

$where = "WHERE 1=1";

if(!empty($id_kelas)){
    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);
    $where .= " AND id = '$id_kelas_safe'";
} else {
    if(empty($jurusan) || empty($tingkat)){
        echo "<div style='font-family:Arial; max-width:650px; margin:70px auto; padding:25px; border:1px solid #AECDEE; background:#F3F8FF; color:#0d385e; border-radius:18px; text-align:center;'>
                <h3>Filter belum lengkap</h3>
                <p>Silakan pilih jurusan dan tingkat terlebih dahulu, atau pilih satu kelas.</p>
                <button onclick='window.close()' style='padding:10px 22px; border-radius:50px; border:0; background:#6FA7DF; color:white; font-weight:bold;'>Tutup</button>
              </div>";
        exit;
    }

    $jurusan_safe = mysqli_real_escape_string($conn, $jurusan);
    $tingkat_safe = mysqli_real_escape_string($conn, $tingkat);
    $where .= " AND kode_jurusan = '$jurusan_safe' AND tingkat = '$tingkat_safe'";
}

$qKelas = $conn->query("SELECT id, nama_kelas, tingkat, kode_jurusan 
                        FROM kelas 
                        $where 
                        ORDER BY tingkat ASC, kode_jurusan ASC, nama_kelas ASC");

$kelas_list = [];
if($qKelas){
    while($k = $qKelas->fetch_assoc()){
        $kelas_list[] = $k;
    }
}

if(count($kelas_list) == 0){
    echo "<div style='font-family:Arial; max-width:650px; margin:70px auto; padding:25px; border:1px solid #F2A6B3; background:#FFF5F7; color:#842029; border-radius:18px; text-align:center;'>
            <h3>Kelas tidak ditemukan</h3>
            <p>Tidak ada kelas yang sesuai dengan filter yang dipilih.</p>
            <button onclick='window.close()' style='padding:10px 22px; border-radius:50px; border:0; background:#842029; color:white; font-weight:bold;'>Tutup</button>
          </div>";
    exit;
}

$judul_zip = !empty($id_kelas)
    ? "Rapor_UAS_" . preg_replace('/[^A-Za-z0-9_]/', '_', $kelas_list[0]['nama_kelas'])
    : "Rapor_UAS_" . preg_replace('/[^A-Za-z0-9_]/', '_', $tingkat . "_" . $jurusan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal Multi Kelas <?= $label_rapor ?></title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #F3F6FA;
            margin: 0;
            padding: 30px;
            color: #0d385e;
        }

        .box {
            max-width: 820px;
            margin: 35px auto;
            background: #ffffff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(80, 115, 155, 0.12);
            border-left: 5px solid #BFD3EA;
        }

        h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #0d385e;
        }

        p {
            color: #607488;
            line-height: 1.5;
        }

        .btn-download {
            background: #B9DDCC;
            color: #2E5E4E;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50px;
            padding: 12px 24px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(80, 115, 155, 0.12);
            margin-top: 12px;
        }

        .class-list {
            margin-top: 18px;
            background: #F8FBFF;
            border: 1px solid #DDEAF5;
            border-radius: 16px;
            padding: 14px 18px;
        }

        .class-list div {
            padding: 5px 0;
            font-size: 14px;
            font-weight: 700;
        }

        .iframe-wrap {
            position: absolute;
            left: -99999px;
            top: 0;
            width: 1200px;
            height: 2000px;
            overflow: hidden;
        }

        iframe {
            width: 1200px;
            height: 2000px;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Download ZIP Rapor <?= $label_rapor ?></h2>
        <p>
            Sistem akan membuat ZIP berisi folder per kelas.
            Di dalam setiap folder kelas, terdapat file PDF rapor siswa yang sudah siap dicetak.
        </p>

        <div class="class-list">
            <?php foreach($kelas_list as $k): ?>
                <div>📁 <?= htmlspecialchars($k['nama_kelas']) ?></div>
            <?php endforeach; ?>
        </div>

        <button id="btnDownload" class="btn-download" onclick="downloadMultiZip()">
            📦 Download ZIP Rapor <?= $label_rapor ?> (<?= count($kelas_list) ?> Kelas)
        </button>

        <p id="statusText" style="font-size:13px; margin-top:14px;">Tunggu sampai semua data kelas selesai dimuat.</p>
    </div>

    <div class="iframe-wrap">
        <?php foreach($kelas_list as $k): ?>
            <iframe 
                class="kelas-frame" 
                data-kelas="<?= htmlspecialchars($k['nama_kelas']) ?>"
                src="rapor_uas_kelas.php?id_kelas=<?= $k['id'] ?>&embed=1&id_tahun_ajaran=<?= urlencode($id_tahun_ajaran_get) ?>&_ts=<?= time() ?>">
            </iframe>
        <?php endforeach; ?>
    </div>

    <script>
        const frames = document.querySelectorAll('.kelas-frame');
        const statusText = document.getElementById('statusText');
        const btnDownload = document.getElementById('btnDownload');
        let loadedFrames = 0;
        let totalRaporSiap = 0;
        let kelasSiap = 0;
        let kelasBelumSiap = 0;

        btnDownload.disabled = true;
        btnDownload.style.opacity = '0.65';

        frames.forEach(frame => {
            frame.addEventListener('load', () => {
                loadedFrames++;

                let jumlahRapor = 0;
                try {
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    jumlahRapor = doc.querySelectorAll('.student-rapor').length;
                } catch(e) {
                    jumlahRapor = 0;
                }

                if(jumlahRapor > 0){
                    kelasSiap++;
                    totalRaporSiap += jumlahRapor;
                } else {
                    kelasBelumSiap++;
                }

                statusText.innerHTML = `Memuat data kelas ${loadedFrames} dari ${frames.length}...`;

                if(loadedFrames >= frames.length){
                    if(totalRaporSiap > 0){
                        btnDownload.disabled = false;
                        btnDownload.style.opacity = '1';
                        statusText.innerHTML = `ZIP siap dibuat. Ada ${totalRaporSiap} rapor UAS siap cetak dari ${kelasSiap} kelas. ${kelasBelumSiap > 0 ? kelasBelumSiap + ' kelas dilewati karena nilai UAS/SAS belum lengkap.' : ''}`;
                    } else {
                        btnDownload.disabled = true;
                        btnDownload.style.opacity = '0.65';
                        btnDownload.innerHTML = 'Nilai UAS/SAS Belum Lengkap';
                        statusText.innerHTML = 'Nilai UAS/SAS belum diinput lengkap atau belum divalidasi. ZIP UAS belum bisa dibuat.';
                    }
                }
            });
        });

        function cleanName(text) {
            return (text || '')
                .replace(/[^a-zA-Z0-9\s_-]/g, '')
                .trim()
                .replace(/\s+/g, '_');
        }

        async function downloadMultiZip() {
            if(totalRaporSiap === 0){
                alert('Nilai UAS/SAS belum diinput lengkap atau belum divalidasi. ZIP UAS belum bisa dibuat.');
                return;
            }

            btnDownload.disabled = true;
            const originalText = btnDownload.innerHTML;
            btnDownload.innerHTML = '⏳ Menyiapkan ZIP...';

            const zip = new JSZip();
            let totalPdf = 0;
            let skippedClass = 0;

            for(let f = 0; f < frames.length; f++){
                const frame = frames[f];
                const kelasName = frame.getAttribute('data-kelas');
                const folderName = cleanName(kelasName);
                const folder = zip.folder(folderName);

                let doc;
                try {
                    doc = frame.contentDocument || frame.contentWindow.document;
                } catch(e) {
                    console.error('Tidak bisa membaca iframe kelas', kelasName, e);
                    skippedClass++;
                    continue;
                }

                const students = doc.querySelectorAll('.student-rapor');

                if(students.length === 0){
                    skippedClass++;
                    continue;
                }

                for(let i = 0; i < students.length; i++){
                    const student = students[i];
                    const nis = student.getAttribute('data-nis') || 'tanpa_nis';
                    const nama = cleanName(student.getAttribute('data-nama') || 'siswa');
                    const filename = `Rapor_UAS_${nis}_${nama}.pdf`;

                    statusText.innerHTML = `Memproses ${kelasName} - PDF ${i + 1} dari ${students.length}...`;
                    btnDownload.innerHTML = `⏳ ${totalPdf + 1} PDF diproses...`;

                    try {
                        // FIX MULTI KELAS:
                        // Jangan generate PDF langsung pakai html2pdf dari iframe,
                        // karena itu yang bikin halaman rapor kepotong/geser kiri.
                        // Pakai fungsi studentToPdfBlob() dari file rapor_<?= strtolower($label_rapor) ?>_kelas.php,
                        // jadi logikanya sama persis dengan cetak rapor per kelas yang sudah benar.
                        let pdfBlob;

                        if(frame.contentWindow && typeof frame.contentWindow.studentToPdfBlob === 'function'){
                            pdfBlob = await frame.contentWindow.studentToPdfBlob(student);
                        } else {
                            // Fallback kalau fungsi belum kebaca.
                            const opt = {
                                margin: 0,
                                filename: filename,
                                image: { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#ffffff' },
                                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                                pagebreak: { mode: ['css', 'legacy'] }
                            };
                            pdfBlob = await html2pdf().set(opt).from(student).output('blob');
                        }

                        folder.file(filename, pdfBlob);
                        totalPdf++;
                    } catch(err) {
                        console.error('Gagal membuat PDF', filename, err);
                    }
                }
            }

            if(totalPdf === 0){
                alert('Nilai UAS/SAS belum diinput lengkap atau belum divalidasi. ZIP UAS belum bisa dibuat.');
                btnDownload.innerHTML = originalText;
                btnDownload.disabled = false;
                statusText.innerHTML = 'Tidak ada rapor yang bisa dibuat.';
                return;
            }

            btnDownload.innerHTML = '📦 Mengemas ZIP...';
            statusText.innerHTML = 'Mengemas semua PDF ke dalam ZIP...';

            const content = await zip.generateAsync({type: 'blob'});
            saveAs(content, "<?= $judul_zip ?>.zip");

            btnDownload.innerHTML = originalText;
            btnDownload.disabled = false;

            let pesan = `Berhasil membuat ${totalPdf} PDF rapor.`;
            if(skippedClass > 0){
                pesan += `\nAda ${skippedClass} kelas yang dilewati karena belum ada data rapor siap cetak.`;
            }

            alert(pesan);
            statusText.innerHTML = pesan.replace(/\n/g, '<br>');
        }
    </script>
</body>
</html>