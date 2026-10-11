<?php
// --- AUTO-UPGRADE DATABASE Pengecekan Satu-Satu ---
$checkColE = $conn->query("SHOW COLUMNS FROM setting_pernyataan LIKE 'template_e'");
if($checkColE && $checkColE->num_rows == 0) {
    $conn->query("ALTER TABLE setting_pernyataan ADD template_e TEXT");
}

$checkColF = $conn->query("SHOW COLUMNS FROM setting_pernyataan LIKE 'template_f'");
if($checkColF && $checkColF->num_rows == 0) {
    $conn->query("ALTER TABLE setting_pernyataan ADD template_f TEXT");
}

$checkColDasar = $conn->query("SHOW COLUMNS FROM setting_pernyataan LIKE 'nama_mapel_dasar'");
if($checkColDasar && $checkColDasar->num_rows == 0) {
    $conn->query("ALTER TABLE setting_pernyataan ADD nama_mapel_dasar VARCHAR(150) NULL");
}

$checkColJur = $conn->query("SHOW COLUMNS FROM setting_pernyataan LIKE 'kode_jurusan'");
if($checkColJur && $checkColJur->num_rows == 0) {
    $conn->query("ALTER TABLE setting_pernyataan ADD kode_jurusan VARCHAR(50) NULL");
}

// 🩹 FIX: kolom ini dipakai buat nge-resync CP kalau template diupdate (lihat bagian
// SIMPAN CP di bawah). Migrasi juga di sini biar gak error kalau setting_cp.php dibuka
// duluan sebelum ada guru yang buka Input Nilai.
$checkColPredikat = $conn->query("SHOW COLUMNS FROM nilai LIKE 'predikat_huruf'");
if($checkColPredikat && $checkColPredikat->num_rows == 0) {
    $conn->query("ALTER TABLE nilai ADD predikat_huruf VARCHAR(1) NULL AFTER deskripsi_cp");
}
$checkColCatatanManual = $conn->query("SHOW COLUMNS FROM nilai LIKE 'catatan_guru_manual'");
if($checkColCatatanManual && $checkColCatatanManual->num_rows == 0) {
    $conn->query("ALTER TABLE nilai ADD catatan_guru_manual TEXT NULL AFTER predikat_huruf");
}
// -----------------------------------------------------------

$nip_guru = $_SESSION['guru']['nip'] ?? '';
$nama_guru = $_SESSION['guru']['nama'] ?? '';

// Ambil ID Guru
$qG = $conn->query("SELECT id FROM guru WHERE nip = '$nip_guru' OR nama_guru = '$nama_guru' LIMIT 1");
$dG = $qG->fetch_assoc();
$id_guru_db = $dG['id'] ?? 0;

// --- MESIN PENGGABUNG MAPEL & JURUSAN ---
$kombo_list = [];

if($id_guru_db > 0) {
    $qMengajar = $conn->query("
        SELECT DISTINCT m.nama_mapel, k.kode_jurusan 
        FROM guru_mengajar gm
        JOIN mapel m ON gm.id_mapel = m.id
        JOIN kelas k ON gm.id_kelas = k.id
        WHERE gm.id_guru = '$id_guru_db'
    ");
    
    while($dm = $qMengajar->fetch_assoc()){
        $jur = trim($dm['kode_jurusan']);
        if(empty($jur)) $jur = "Umum";
        
        $kombo = trim($dm['nama_mapel']) . " | " . $jur;
        if(!in_array($kombo, $kombo_list)) {
            $kombo_list[] = $kombo;
        }
    }
}

$selected_kombo = trim($_POST['mapel_kombo'] ?? '');
$mapel_dasar_aktif = "";
$jur_aktif = "";
$is_pkl = false; 

if($selected_kombo){
    $parts = explode(' | ', $selected_kombo);
    $mapel_dasar_aktif = trim($parts[0] ?? '');
    $jur_aktif = trim($parts[1] ?? '');

    if(stripos($mapel_dasar_aktif, 'PKL') !== false || stripos($mapel_dasar_aktif, 'Praktik Kerja Lapangan') !== false){
        $is_pkl = true;
    }
}

// --- PROSES SIMPAN CP ---
if(isset($_POST['save_template']) && $mapel_dasar_aktif && $jur_aktif){
    $tmplt_a = trim($_POST['tmplt_a']);
    $tmplt_b = trim($_POST['tmplt_b']);
    $tmplt_c = trim($_POST['tmplt_c']);
    $tmplt_d = trim($_POST['tmplt_d']);
    $tmplt_e = trim($_POST['tmplt_e'] ?? '');
    $tmplt_f = trim($_POST['tmplt_f'] ?? '');
    
    $cek = $conn->query("SELECT id FROM setting_pernyataan WHERE nama_mapel_dasar = '$mapel_dasar_aktif' AND kode_jurusan = '$jur_aktif'");
    
    if($cek && $cek->num_rows > 0) {
        $conn->query("UPDATE setting_pernyataan SET 
            id_guru = '$id_guru_db', 
            template_a = '$tmplt_a',
            template_b = '$tmplt_b',
            template_c = '$tmplt_c',
            template_d = '$tmplt_d',
            template_e = '$tmplt_e',
            template_f = '$tmplt_f'
            WHERE nama_mapel_dasar = '$mapel_dasar_aktif' AND kode_jurusan = '$jur_aktif'");
    } else {
        $stmt_save = $conn->prepare("INSERT INTO setting_pernyataan 
            (id_guru, nama_mapel_dasar, kode_jurusan, template_a, template_b, template_c, template_d, template_e, template_f) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_save->bind_param("issssssss", 
            $id_guru_db, $mapel_dasar_aktif, $jur_aktif, 
            $tmplt_a, $tmplt_b, $tmplt_c, $tmplt_d, $tmplt_e, $tmplt_f
        );
        $stmt_save->execute();
    }
    
    // 🩹 FIX: RESYNC NILAI YANG SUDAH ADA
    // Supaya rapor yang udah jadi ikut nyesuain begitu template diupdate, tanpa guru
    // harus input ulang satu-satu. Hanya nilai yang predikat_huruf-nya kesimpen (artinya
    // pakai auto-template, bukan tulisan manual guru murni) yang di-resync. Catatan manual
    // guru (catatan_guru_manual) tetap dipertahankan, cuma bagian predikat teksnya diganti.
    if(!$is_pkl){
        $jur_aktif_safe = mysqli_real_escape_string($conn, $jur_aktif);
        $mapel_dasar_aktif_safe = mysqli_real_escape_string($conn, $mapel_dasar_aktif);
        $tmplt_a_sql = mysqli_real_escape_string($conn, $tmplt_a);
        $tmplt_b_sql = mysqli_real_escape_string($conn, $tmplt_b);
        $tmplt_c_sql = mysqli_real_escape_string($conn, $tmplt_c);
        $tmplt_d_sql = mysqli_real_escape_string($conn, $tmplt_d);

        $conn->query("UPDATE nilai n
                      JOIN mapel m ON m.id = n.id_mapel AND m.nama_mapel = '$mapel_dasar_aktif_safe'
                      JOIN riwayat_kelas rk ON rk.id_siswa = n.id_siswa AND rk.id_tahun_ajaran = n.id_tahun_ajaran
                      JOIN kelas k ON k.id = rk.id_kelas AND k.kode_jurusan = '$jur_aktif_safe'
                      SET n.deskripsi_cp = CASE n.predikat_huruf
                          WHEN 'A' THEN CONCAT('$tmplt_a_sql', IF(n.catatan_guru_manual IS NOT NULL AND n.catatan_guru_manual != '', CONCAT('. ', n.catatan_guru_manual), ''))
                          WHEN 'B' THEN CONCAT('$tmplt_b_sql', IF(n.catatan_guru_manual IS NOT NULL AND n.catatan_guru_manual != '', CONCAT('. ', n.catatan_guru_manual), ''))
                          WHEN 'C' THEN CONCAT('$tmplt_c_sql', IF(n.catatan_guru_manual IS NOT NULL AND n.catatan_guru_manual != '', CONCAT('. ', n.catatan_guru_manual), ''))
                          WHEN 'D' THEN CONCAT('$tmplt_d_sql', IF(n.catatan_guru_manual IS NOT NULL AND n.catatan_guru_manual != '', CONCAT('. ', n.catatan_guru_manual), ''))
                          ELSE n.deskripsi_cp
                      END
                      WHERE n.predikat_huruf IS NOT NULL");
    }

    echo "<script>alert('Template CP untuk Mapel $mapel_dasar_aktif (Jurusan $jur_aktif) Berhasil Disimpan & Dikompakkan! Rapor yang sudah ada juga otomatis ikut diperbarui.'); window.location='?page=setting_cp';</script>";
}

// Ambil data lama
$old = [
    'template_a'=>'',
    'template_b'=>'',
    'template_c'=>'',
    'template_d'=>'',
    'template_e'=>'',
    'template_f'=>''
];

if($mapel_dasar_aktif && $jur_aktif){
    $qO = $conn->query("SELECT * FROM setting_pernyataan WHERE nama_mapel_dasar = '$mapel_dasar_aktif' AND kode_jurusan = '$jur_aktif' LIMIT 1");
    if($qO && $qO->num_rows > 0) $old = $qO->fetch_assoc();
}
?>

<style>
    .setting-cp-card {
        background: #ffffff;
        border-radius: 18px !important;
        border-left: 5px solid #6FA7DF !important;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08) !important;
    }

    .setting-cp-card h5 {
        color: #0d385e !important;
        font-size: 22px !important;
        margin-bottom: 8px;
    }

    .setting-cp-desc {
        color: #6c757d;
        font-size: 14px;
        margin-bottom: 22px;
    }

    .setting-label {
        font-size: 13px !important;
        color: #244b70 !important;
        font-weight: 700 !important;
        margin-bottom: 8px;
        display: block;
    }

    .form-filter-soft {
        height: 52px !important;
        border-radius: 50px !important;
        border: 1px solid #dbe5ef !important;
        background-color: #f8fbff !important;
        color: #0d385e !important;
        font-weight: 600 !important;
        font-size: 16px !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
        box-shadow: none !important;
    }

    .form-filter-soft:focus {
        border-color: #9fc1e7 !important;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14) !important;
    }

    .form-filter-soft option {
        font-size: 16px !important;
        font-weight: 400 !important;
        color: #0d385e !important;
        background: #ffffff !important;
        padding: 12px !important;
    }

    .textarea-soft {
        border: 1px solid #cbd8e5 !important;
        border-radius: 12px !important;
        background-color: #ffffff !important;
        font-size: 14px !important;
        color: #244b70 !important;
        padding: 12px !important;
        box-shadow: none !important;
    }

    .textarea-soft:focus {
        border-color: #6FA7DF !important;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14) !important;
    }

    .label-soft-blue {
        color: #2b5d8a !important;
        font-size: 13px !important;
    }

    .label-soft-green {
        color: #2E5E4E !important;
        font-size: 13px !important;
    }

    .label-soft-yellow {
        color: #87653B !important;
        font-size: 13px !important;
    }

    .label-soft-red {
        color: #b92c44 !important;
        font-size: 13px !important;
    }

    .section-title-soft {
        color: #0d385e;
        border-bottom: 1px solid #dbe5ef;
        padding-bottom: 8px;
        margin-top: 20px;
        font-weight: 700;
        font-size: 15px;
    }

    .alert-pkl-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 14px !important;
        box-shadow: none !important;
        font-size: 14px;
    }

    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        min-height: 44px;
        padding: 0 30px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px;
        font-size: 14px !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.28) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-blue:hover,
    .btn-soft-blue:focus,
    .btn-soft-blue:active {
        background: #5E97D2 !important;
        color: #ffffff !important;
        box-shadow: 0 5px 12px rgba(94, 151, 210, 0.30) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    button,
    button:hover,
    button:focus,
    button:active,
    .btn,
    .btn:hover,
    .btn:focus,
    .btn:active {
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
</style>

<div class="card border-0 p-4 mb-4 setting-cp-card">
    <h5 class="fw-bold">
        <i class="fas fa-cog me-2"></i>Master Setting Pernyataan CP (Mode Kompak)
    </h5>

    <p class="setting-cp-desc">
        Setting ini akan otomatis diterapkan ke semua kelas dengan jurusan yang sama yang menggunakan mata pelajaran ini. Gak perlu ngulang ngetik!
    </p>
    
    <form method="POST">
        <label class="setting-label">Pilih Mata Pelajaran & Jurusan</label>

        <div class="row">
            <div class="col-md-7">
                <select name="mapel_kombo" class="form-select form-filter-soft mb-3" onchange="this.form.submit()">
                    <option value="">-- Pilih Mapel --</option>
                    <?php foreach($kombo_list as $kl): ?>
                        <?php 
                            $pt = explode(' | ', $kl); 
                            $tampil = $pt[0] . " (Jurusan: " . $pt[1] . ")";
                        ?>
                        <option value="<?= htmlspecialchars($kl) ?>" <?= ($selected_kombo == $kl)?'selected':'' ?>>
                            <?= htmlspecialchars($tampil) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php if($selected_kombo): ?>
    <form method="POST">
        <input type="hidden" name="mapel_kombo" value="<?= htmlspecialchars($selected_kombo) ?>">

        <div class="row g-3">
            
            <?php if($is_pkl): ?>
                <div class="col-12">
                    <div class="alert alert-pkl-soft mb-2">
                        <i class="fas fa-industry me-2"></i>
                        <span class="fw-bold">Mode Khusus PKL:</span> Silakan isi deskripsi untuk masing-masing Tujuan Pembelajaran (TP) beserta Kesimpulan Akhirnya.
                    </div>
                </div>
                
                <h6 class="section-title-soft">A. Deskripsi Per-Tujuan Pembelajaran (TP)</h6>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-blue">Deskripsi TP 1 (Soft Skills)</label>
                    <textarea name="tmplt_a" class="form-control textarea-soft" rows="4"><?= htmlspecialchars($old['template_a'] ?: "Mampu menerapkan seluruh aspek soft skill (integritas, etos kerja, kemandirian, kerjasama, kepedulian) dengan sangat baik dan konsisten sesuai tuntutan dunia kerja.") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-green">Deskripsi TP 2 (Norma, POS & K3LH)</label>
                    <textarea name="tmplt_b" class="form-control textarea-soft" rows="4"><?= htmlspecialchars($old['template_b'] ?: "Menerapkan penggunaan APD dan pelaksanaan pekerjaan sesuai POS dengan sempurna, menunjukkan kedisiplinan, kepatuhan prosedur, dan kesadaran K3LH yang tinggi.") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-yellow">Deskripsi TP 3 (Kompetensi Teknis)</label>
                    <textarea name="tmplt_c" class="form-control textarea-soft" rows="4"><?= htmlspecialchars($old['template_c'] ?: "Mampu menguasai sebagian besar kompetensi teknis yang ditetapkan dunia industri dan menerapkannya dalam pelaksanaan tugas di tempat PKL.") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-red">Deskripsi TP 4 (Alur Bisnis)</label>
                    <textarea name="tmplt_d" class="form-control textarea-soft" rows="4"><?= htmlspecialchars($old['template_d'] ?: "Mampu memahami alur bisnis dunia kerja tempat PKL serta memiliki wawasan wirausaha yang baik, sehingga dapat berkontribusi dalam organisasi.") ?></textarea>
                </div>

                <h6 class="section-title-soft">B. Kesimpulan Akhir (Tampil di Bawah Tabel)</h6>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-blue">Tujuan Pembelajaran (Kesimpulan Umum)</label>
                    <textarea name="tmplt_e" class="form-control textarea-soft bg-light" rows="4"><?= htmlspecialchars($old['template_e'] ?: "Selama melaksanakan PKL Siswa telah menunjukkan kemampuan yang sangat baik dalam beradaptasi, bekerja sama, dan menyelesaikan tugas sesuai prosedur yang berlaku.") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-green">Deskripsi (Saran & Motivasi)</label>
                    <textarea name="tmplt_f" class="form-control textarea-soft bg-light" rows="4"><?= htmlspecialchars($old['template_f'] ?: "Terus pertahankan soft skill dan kedisiplinan yang sudah baik, serta tingkatkan penguasaan kompetensi teknis dan wawasan bisnis agar siap bersaing di dunia kerja atau wirausaha.") ?></textarea>
                </div>

            <?php else: ?>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-green">Template Predikat A (Sangat Baik)</label>
                    <textarea name="tmplt_a" class="form-control textarea-soft" rows="3"><?= htmlspecialchars($old['template_a'] ?: "Menunjukkan penguasaan yang sangat baik dalam materi $mapel_dasar_aktif") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-blue">Template Predikat B (Baik)</label>
                    <textarea name="tmplt_b" class="form-control textarea-soft" rows="3"><?= htmlspecialchars($old['template_b'] ?: "Menunjukkan penguasaan baik dalam materi $mapel_dasar_aktif") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-yellow">Template Predikat C (Cukup)</label>
                    <textarea name="tmplt_c" class="form-control textarea-soft" rows="3"><?= htmlspecialchars($old['template_c'] ?: "Menunjukkan penguasaan cukup baik dalam materi $mapel_dasar_aktif") ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold label-soft-red">Template Predikat D (Kurang)</label>
                    <textarea name="tmplt_d" class="form-control textarea-soft" rows="3"><?= htmlspecialchars($old['template_d'] ?: "Perlu bimbingan lebih lanjut dalam materi $mapel_dasar_aktif") ?></textarea>
                </div>

            <?php endif; ?>

        </div>

        <button type="submit" name="save_template" class="btn btn-soft-blue mt-4 px-5 rounded-pill fw-bold">
            <i class="fas fa-save me-2"></i>SIMPAN TEMPLATE KOMPAK
        </button>
    </form>
    <?php endif; ?>
</div>