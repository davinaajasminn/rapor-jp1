<?php
// --- PROSES KIRIM NOTIFIKASI KE WALI KELAS ---
if(isset($_POST['colek_wali'])){
    $id_wali = $_POST['id_wali'];
    $nama_kelas = $_POST['nama_kelas'];
    $progres = $_POST['progres'];

    // Data Kepsek yang ngirim
    $id_kepsek = $_SESSION['kepsek']['id'];
    $nama_kepsek = $_SESSION['kepsek']['nama_lengkap'];

    // Pesan dari Kepsek bisa diedit sebelum dikirim
    $pesan_custom = trim($_POST['pesan_custom'] ?? '');

    if($pesan_custom != ''){
        $pesan = $pesan_custom;
    } else {
        $pesan = "Tolong perhatikan! Progres akhir rapor UAS kelas $nama_kelas baru $progres%. Segera kejar dan ingatkan guru mapel yang belum menginput nilai lengkap ya.";
    }
    
    // Link biar pas Wali Kelas ngeklik notifnya, langsung diarahkan ke halaman kelasnya
    $id_kelas = $_POST['id_kelas'];
    $link = "?page=wali_siswa&id_kelas=$id_kelas";

    $stmt = $conn->prepare("INSERT INTO notifikasi (pengirim_role, pengirim_id, nama_pengirim, penerima_role, penerima_id, pesan, link_tujuan) VALUES ('kepsek', ?, ?, 'guru', ?, ?, ?)");
    $stmt->bind_param("isiss", $id_kepsek, $nama_kepsek, $id_wali, $pesan, $link);
    
    if($stmt->execute()){
        echo "<script>alert('Lonceng Peringatan berhasil dikirim ke Wali Kelas!'); window.location='?page=pantau_kelas';</script>";
    }
}

// --- TAHUN AJARAN YANG SEDANG DIPILIH ---
// $id_ta_pilih & $daftar_tahun_ajaran sudah disiapkan di index.php (default: tahun ajaran aktif)
$ta_pilih_safe = (int)$id_ta_pilih;
$return_ta_url = '&ta=' . urlencode($id_ta_pilih);

// --- AMBIL DATA KELAS YANG BENAR-BENAR ADA DI TAHUN AJARAN INI (via riwayat_kelas) ---
// Kelas cuma ditampilkan kalau punya siswa yang tercatat di riwayat_kelas untuk TA yang dipilih,
// biar kelas yang belum ada siswanya di TA ini (misal kelas X yang baru dibuat sebelum PPDB) gak nyasar muncul.
$qKelas = $conn->query("SELECT k.id, k.nama_kelas, k.kode_jurusan, k.id_wali_kelas, g.nama_guru, 
                        (SELECT COUNT(DISTINCT r.id_siswa) FROM riwayat_kelas r WHERE r.id_kelas = k.id AND r.id_tahun_ajaran = '$ta_pilih_safe') as jml_siswa
                        FROM kelas k 
                        LEFT JOIN guru g ON k.id_wali_kelas = g.id 
                        WHERE EXISTS (
                            SELECT 1 FROM riwayat_kelas r2 WHERE r2.id_kelas = k.id AND r2.id_tahun_ajaran = '$ta_pilih_safe'
                        )
                        ORDER BY k.tingkat ASC, k.nama_kelas ASC");

// --- AMBIL DATA JURUSAN BUAT DROPDOWN ---
$qJurusan = $conn->query("SELECT kode_jurusan FROM jurusan ORDER BY kode_jurusan ASC");

$filter_jurusan_aktif = $_GET['jurusan'] ?? 'ALL';
$filter_tingkat_aktif = $_GET['tingkat'] ?? 'ALL';
$filter_cari_aktif = $_GET['cari'] ?? '';
?>

<style>
    .btn-soft-blue-kepsek {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: 1px solid #6FA7DF !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.24) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-blue-kepsek:hover,
    .btn-soft-blue-kepsek:focus,
    .btn-soft-blue-kepsek:active {
        background: #5E97D2 !important;
        color: #ffffff !important;
        border-color: #5E97D2 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-back-kepsek {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(174, 205, 238, 0.18) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-back-kepsek:hover,
    .btn-soft-back-kepsek:focus,
    .btn-soft-back-kepsek:active {
        background: #C7DEF4 !important;
        color: #0d385e !important;
        border-color: #9FC1E7 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-cream-kepsek {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #D8BC82 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(214, 189, 145, 0.22) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-cream-kepsek:hover,
    .btn-soft-cream-kepsek:focus,
    .btn-soft-cream-kepsek:active {
        background: #EED9AF !important;
        color: #76542D !important;
        border-color: #CDAE70 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-red-kepsek {
        background: #E994A5 !important;
        color: #ffffff !important;
        border: 1px solid #E994A5 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(233, 148, 165, 0.25) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-red-kepsek:hover,
    .btn-soft-red-kepsek:focus,
    .btn-soft-red-kepsek:active {
        background: #DD7F92 !important;
        color: #ffffff !important;
        border-color: #DD7F92 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-green-kepsek {
        background: #B9DDCC !important;
        color: #2E5E4E !important;
        border: 1px solid #9FCDB8 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(156, 201, 182, 0.22) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
    .btn-soft-green-kepsek:hover,
    .btn-soft-green-kepsek:focus,
    .btn-soft-green-kepsek:active {
        background: #A7D1BD !important;
        color: #244F42 !important;
        border-color: #8BBFA9 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .textarea-pesan-kepsek {
        border-radius: 14px !important;
        border: 1px solid #dbe5ef !important;
        background: #f8fbff !important;
        color: #0d385e !important;
        font-size: 14px;
        line-height: 1.5;
        box-shadow: none !important;
    }

    .textarea-pesan-kepsek:focus {
        border-color: #9fc1e7 !important;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14) !important;
        background: #ffffff !important;
    }

    .modal-peringatan-kepsek .modal-content {
        border: none;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(80, 115, 155, 0.20);
    }


    .progress-section-kepsek {
        border: 1px solid #edf2f7;
        border-radius: 18px;
        padding: 14px;
        background: #fbfdff;
        margin-bottom: 12px;
    }
    .progress-section-kepsek.uas {
        background: #f7fffb;
    }
    .progress-title-kepsek {
        font-size: 13px;
        font-weight: 900;
        color: #0d385e;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-ledger-mini-kepsek {
        font-size: 11px !important;
        padding: 6px 14px !important;
        min-height: 30px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }


    .filter-kepsek-box {
        background: #ffffff;
        border-radius: 22px;
        padding: 18px 22px;
        box-shadow: 0 8px 22px rgba(80,115,155,0.10);
        width: 100%;
    }
    .filter-input-kepsek,
    .filter-select-kepsek {
        border-radius: 50px !important;
        border: 1px solid #dbe7f3 !important;
        background: #f8fbff !important;
        color: #0d385e !important;
        font-weight: 700 !important;
        height: 48px;
        box-shadow: none !important;
        padding-left: 18px !important;
        padding-right: 18px !important;
    }
    .filter-input-kepsek:focus,
    .filter-select-kepsek:focus {
        border-color: #9fc1e7 !important;
        box-shadow: 0 0 0 3px rgba(111,167,223,0.14) !important;
        background: #fff !important;
    }
    .kelas-empty-state {
        display: none;
        border-radius: 22px;
        padding: 35px;
        text-align: center;
        background: #fff;
        box-shadow: 0 8px 22px rgba(80,115,155,0.10);
        color: #64748b;
        font-weight: 700;
    }

</style>


<div class="card border-0 shadow-sm p-4 mb-3 bg-white" style="border-radius:15px;">
    <div>
        <h5 class="fw-bold mb-1"><i class="fas fa-eye me-2 text-primary"></i>Monitoring Rapor per Kelas</h5>
        <p class="text-muted small mb-0">Memantau progres pengisian nilai dan validasi wali kelas secara real-time.</p>
    </div>
</div>

<div class="filter-kepsek-box mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-lg-4 col-md-12">
            <input type="text" id="filterCari" class="form-control filter-input-kepsek" placeholder="Cari nama kelas..." value="<?= htmlspecialchars($filter_cari_aktif) ?>">
        </div>
        <div class="col-lg-3 col-md-6">
            <select id="filterJurusan" class="form-select filter-select-kepsek">
                <option value="ALL">SEMUA JURUSAN</option>
                <?php while($j = $qJurusan->fetch_assoc()): ?>
                    <option value="<?= $j['kode_jurusan'] ?>" <?= ($filter_jurusan_aktif == $j['kode_jurusan']) ? 'selected' : '' ?>>
                        <?= $j['kode_jurusan'] ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <select id="filterTingkat" class="form-select filter-select-kepsek">
                <option value="ALL" <?= ($filter_tingkat_aktif == 'ALL') ? 'selected' : '' ?>>SEMUA TINGKAT</option>
                <option value="X" <?= ($filter_tingkat_aktif == 'X') ? 'selected' : '' ?>>KELAS X</option>
                <option value="XI" <?= ($filter_tingkat_aktif == 'XI') ? 'selected' : '' ?>>KELAS XI</option>
                <option value="XII" <?= ($filter_tingkat_aktif == 'XII') ? 'selected' : '' ?>>KELAS XII</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <form method="GET" id="formTahunAjaran" class="m-0">
                <input type="hidden" name="page" value="pantau_kelas">
                <input type="hidden" name="jurusan" value="<?= htmlspecialchars($filter_jurusan_aktif) ?>">
                <input type="hidden" name="tingkat" value="<?= htmlspecialchars($filter_tingkat_aktif) ?>">
                <input type="hidden" name="cari" value="<?= htmlspecialchars($filter_cari_aktif) ?>">
                <select name="ta" class="form-select filter-select-kepsek" onchange="document.getElementById('formTahunAjaran').submit();">
                    <?php foreach($daftar_tahun_ajaran as $ta): ?>
                        <option value="<?= $ta['id'] ?>" <?= ($id_ta_pilih == $ta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?><?= ($ta['status'] == 'Aktif') ? ' (Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<div class="row g-4" id="kelasContainer">
    <?php while($k = $qKelas->fetch_assoc()): 
        $id_k = $k['id'];
        $jml_s = $k['jml_siswa'];
        $id_wali = $k['id_wali_kelas'];
        
        // --- LOGIKA HITUNG PROGRES PER KELAS ---
        // Dibagi 2 progress:
        // 1) Progress UTS: TP / nilai_t + STS / nilai_p
        // 2) Progress UAS: TP / nilai_t + STS / nilai_p + SAS / nilai_k
        // Jadi kalau baru input sampai UTS, bar UTS bisa 100%, tapi bar UAS belum 100%.

        $id_k_safe = mysqli_real_escape_string($conn, $id_k);
        $tingkat_kelas = strtoupper(explode(' ', trim($k['nama_kelas']))[0]);
        $jurusan_kelas = $k['kode_jurusan'] ?? '';

        $tingkat_safe = mysqli_real_escape_string($conn, $tingkat_kelas);
        $jurusan_safe = mysqli_real_escape_string($conn, $jurusan_kelas);

        // Ambil jumlah mapel sinkron dengan guru_mengajar, KHUSUS tahun ajaran yang sedang dipilih.
        // Kalau guru_mengajar masih kosong, fallback ke mapel kelompok A + jurusan.
        $qCekGM = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                                FROM guru_mengajar gm
                                JOIN mapel m ON gm.id_mapel = m.id
                                WHERE gm.id_kelas = '$id_k_safe'
                                AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                                AND m.tingkat = '$tingkat_safe'
                                AND m.nama_mapel NOT LIKE '%PKL%'
                                AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                AND m.nama_mapel NOT LIKE '%Prakerin%'");
        $jml_gm = ($qCekGM && $qCekGM->num_rows > 0) ? (int)$qCekGM->fetch_assoc()['total'] : 0;
        $pakai_guru_mengajar = ($jml_gm > 0);

        if($pakai_guru_mengajar){
            $qJumlahMapel = $conn->query("SELECT COUNT(DISTINCT m.id) as total
                                          FROM guru_mengajar gm
                                          JOIN mapel m ON gm.id_mapel = m.id
                                          WHERE gm.id_kelas = '$id_k_safe'
                                          AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                                          AND m.tingkat = '$tingkat_safe'
                                          AND m.nama_mapel NOT LIKE '%PKL%'
                                          AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                          AND m.nama_mapel NOT LIKE '%Prakerin%'");

            $where_mapel_progress = "gm.id_kelas = '$id_k_safe'
                                     AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                                     AND m.tingkat = '$tingkat_safe'
                                     AND m.nama_mapel NOT LIKE '%PKL%'
                                     AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                     AND m.nama_mapel NOT LIKE '%Prakerin%'";
            $join_gm_progress = "JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_k_safe' AND gm.id_tahun_ajaran = '$ta_pilih_safe'";
        } else {
            $qJumlahMapel = $conn->query("SELECT COUNT(id) as total
                                          FROM mapel
                                          WHERE tingkat = '$tingkat_safe'
                                          AND nama_mapel NOT LIKE '%PKL%'
                                          AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                          AND nama_mapel NOT LIKE '%Prakerin%'
                                          AND (
                                              kelompok = 'A'
                                              OR kode_jurusan = '$jurusan_safe'
                                          )");

            $where_mapel_progress = "m.tingkat = '$tingkat_safe'
                                     AND m.nama_mapel NOT LIKE '%PKL%'
                                     AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                     AND m.nama_mapel NOT LIKE '%Prakerin%'
                                     AND (
                                         m.kelompok = 'A'
                                         OR m.kode_jurusan = '$jurusan_safe'
                                     )";
            $join_gm_progress = "";
        }

        $jml_mapel = ($qJumlahMapel && $qJumlahMapel->num_rows > 0) ? ((int)($qJumlahMapel->fetch_assoc()['total'] ?? 0)) : 0;

        // Target UTS: jumlah siswa x jumlah mapel x 2 komponen nilai (TP dan STS)
        $target_uts = $jml_s * $jml_mapel * 2;

        // Target UAS: jumlah siswa x jumlah mapel x 3 komponen nilai (TP, STS, SAS)
        $target_uas = $jml_s * $jml_mapel * 3;

        // Progress kepsek = progress pengisian nilai, bukan validasi.
        // Jadi status_validasi tidak wajib agar UAS bisa kebaca walaupun belum disahkan.
        // FIX ROSTER SISWA: siswa kelas ini diambil dari riwayat_kelas sesuai TA yang dipilih,
        // BUKAN dari siswa.id_kelas (posisi kelas siswa saat ini) yang bisa beda kalau TA yang
        // dipilih bukan TA aktif sekarang (siswa udah pindah/naik kelas).
        $qNilaiTerisi = $conn->query("SELECT 
                                        SUM(CASE WHEN n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0 THEN 1 ELSE 0 END) AS total_tp,
                                        SUM(CASE WHEN 
                                            (n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0)
                                            OR (n.nilai_sts IS NOT NULL AND n.nilai_sts != '' AND n.nilai_sts > 0)
                                        THEN 1 ELSE 0 END) AS total_sts,
                                        SUM(CASE WHEN 
                                            (n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0)
                                            OR (n.nilai_sas IS NOT NULL AND n.nilai_sas != '' AND n.nilai_sas > 0)
                                        THEN 1 ELSE 0 END) AS total_sas
                                      FROM riwayat_kelas rk
                                      JOIN siswa s ON s.id = rk.id_siswa
                                      JOIN nilai n ON n.id_siswa = s.id AND n.id_tahun_ajaran = rk.id_tahun_ajaran
                                      JOIN mapel m ON n.id_mapel = m.id
                                      $join_gm_progress
                                      WHERE rk.id_kelas = '$id_k_safe'
                                      AND rk.id_tahun_ajaran = '$ta_pilih_safe'
                                      AND $where_mapel_progress");

        $dataNilai = ($qNilaiTerisi && $qNilaiTerisi->num_rows > 0) ? $qNilaiTerisi->fetch_assoc() : [];

        $total_tp = $dataNilai['total_tp'] ?? 0;
        $total_sts = $dataNilai['total_sts'] ?? 0;
        $total_sas = $dataNilai['total_sas'] ?? 0;

        $nilai_uts_terisi = $total_tp + $total_sts;
        $nilai_uas_terisi = $total_tp + $total_sts + $total_sas;

        $progres_uts = ($target_uts > 0) ? round(($nilai_uts_terisi / $target_uts) * 100) : 0;
        $progres_uas = ($target_uas > 0) ? round(($nilai_uas_terisi / $target_uas) * 100) : 0;

        if($progres_uts > 100) $progres_uts = 100;
        if($progres_uas > 100) $progres_uas = 100;

        // Variabel $progres tetap dipakai untuk notifikasi/peringatan, pakai progress UAS sebagai progress akhir rapor.
        $progres = $progres_uas;

        $warna_uts = ($progres_uts >= 100) ? 'bg-success' : (($progres_uts >= 60) ? 'bg-warning' : 'bg-danger');
        $warna_uas = ($progres_uas >= 100) ? 'bg-success' : (($progres_uas >= 60) ? 'bg-warning' : 'bg-danger');
        $warna = $warna_uas;
    ?>
    <div class="col-md-6 col-lg-4 kelas-item" data-idkelas="<?= $id_k ?>" data-jurusan="<?= $k['kode_jurusan'] ?>" data-tingkat="<?= strtoupper(explode(' ', trim($k['nama_kelas']))[0]) ?>" data-kelas="<?= strtolower($k['nama_kelas']) ?>">
        <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><?= $k['nama_kelas'] ?></h5>
                        <small class="text-muted d-block"><i class="fas fa-user-tie me-1"></i> Wali: <?= $k['nama_guru'] ?? '-' ?></small>
                        <small class="text-muted d-block"><i class="fas fa-users me-1"></i> <?= $jml_s ?> Siswa</small>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="progress-section-kepsek">
                        <div class="progress-title-kepsek">
                            <span><i class="fas fa-file-alt me-1 text-info"></i>Progress UTS</span>
                            <span><?= $progres_uts ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 50px;">
                            <div class="progress-bar <?= $warna_uts ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $progres_uts ?>%"></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="?page=lihat_ledger&id_kelas=<?= $id_k ?>&jenis=uts&jurusan=<?= urlencode($filter_jurusan_aktif) ?>&tingkat=<?= urlencode($filter_tingkat_aktif) ?>&cari=<?= urlencode($filter_cari_aktif) ?><?= $return_ta_url ?>" class="btn btn-soft-blue-kepsek btn-ledger-mini-kepsek rounded-pill fw-bold js-link-ledger-uts">
                                <i class="fas fa-file-excel"></i> Ledger UTS
                            </a>
                        </div>
                    </div>

                    <div class="progress-section-kepsek uas">
                        <div class="progress-title-kepsek">
                            <span><i class="fas fa-file-signature me-1 text-success"></i>Progress UAS</span>
                            <span><?= $progres_uas ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 50px;">
                            <div class="progress-bar <?= $warna_uas ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $progres_uas ?>%"></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="?page=lihat_ledger&id_kelas=<?= $id_k ?>&jenis=uas&jurusan=<?= urlencode($filter_jurusan_aktif) ?>&tingkat=<?= urlencode($filter_tingkat_aktif) ?>&cari=<?= urlencode($filter_cari_aktif) ?><?= $return_ta_url ?>" class="btn btn-soft-green-kepsek btn-ledger-mini-kepsek rounded-pill fw-bold js-link-ledger-uas">
                                <i class="fas fa-file-excel"></i> Ledger UAS
                            </a>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <a href="?page=pantau_siswa&id_kelas=<?= $id_k ?>&jenis=uas&jurusan=<?= urlencode($filter_jurusan_aktif) ?>&tingkat=<?= urlencode($filter_tingkat_aktif) ?>&cari=<?= urlencode($filter_cari_aktif) ?><?= $return_ta_url ?>" class="btn btn-soft-back-kepsek btn-sm rounded-pill px-3 fw-bold js-link-detail">Detail</a>
                    
                    <?php if($progres_uts == 100 && $progres_uas == 100): ?>
                        <span class="text-success small fw-bold"><i class="fas fa-check-double me-1"></i>Selesai</span>
                    <?php else: ?>
                        <?php if(!empty($id_wali)): ?>
                        <button type="button" class="btn btn-soft-cream-kepsek btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPeringatan<?= $id_k ?>">
                            <i class="fas fa-bell me-1"></i> Peringatkan
                        </button>

                        <div class="modal fade modal-peringatan-kepsek" id="modalPeringatan<?= $id_k ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header border-0 pb-0">
                                            <div>
                                                <h5 class="modal-title fw-bold text-dark mb-1">
                                                    <i class="fas fa-bell me-2 text-warning"></i>Edit Pesan Peringatan
                                                </h5>
                                                <small class="text-muted">Untuk wali kelas <?= $k['nama_kelas'] ?></small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <input type="hidden" name="id_wali" value="<?= $id_wali ?>">
                                            <input type="hidden" name="nama_kelas" value="<?= $k['nama_kelas'] ?>">
                                            <input type="hidden" name="progres" value="<?= $progres ?>">
                                            <input type="hidden" name="id_kelas" value="<?= $id_k ?>">

                                            <label class="form-label fw-bold small text-muted">Pesan yang akan dikirim</label>
                                            <textarea name="pesan_custom" class="form-control textarea-pesan-kepsek" rows="5">Tolong perhatikan! Progres akhir rapor UAS kelas <?= $k['nama_kelas'] ?> baru <?= $progres ?>%. Segera kejar dan ingatkan guru mapel yang belum menginput nilai lengkap ya.</textarea>

                                            <small class="text-muted d-block mt-2">
                                                Pesan ini akan masuk ke lonceng notifikasi wali kelas.
                                            </small>
                                        </div>

                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-soft-back-kepsek rounded-pill px-4" data-bs-dismiss="modal">
                                                Batal
                                            </button>
                                            <button type="submit" name="colek_wali" class="btn btn-soft-cream-kepsek rounded-pill px-4" onclick="return confirm('Kirim pesan peringatan ini ke wali kelas?');">
                                                <i class="fas fa-paper-plane me-1"></i> Kirim
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                            <span class="badge bg-secondary">Belum Set Wali</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<div id="kelasEmptyState" class="kelas-empty-state mt-4">
    <i class="fas fa-search me-2"></i>Tidak ada kelas yang sesuai dengan filter.
</div>

<script>
function getFilterAktif() {
    return {
        jurusan: document.getElementById('filterJurusan').value || 'ALL',
        tingkat: document.getElementById('filterTingkat').value || 'ALL',
        cari: document.getElementById('filterCari').value || ''
    };
}

function simpanFilterKeUrl() {
    const f = getFilterAktif();

    const url = new URL(window.location.href);
    url.searchParams.set('page', 'pantau_kelas');
    url.searchParams.set('jurusan', f.jurusan);
    url.searchParams.set('tingkat', f.tingkat);
    url.searchParams.set('cari', f.cari);

    window.history.replaceState({}, '', url.toString());
    localStorage.setItem('kepsek_filter_jurusan', f.jurusan);
    localStorage.setItem('kepsek_filter_tingkat', f.tingkat);
    localStorage.setItem('kepsek_filter_cari', f.cari);
}

function updateLinkAksiKelas() {
    const f = getFilterAktif();
    const taAktif = new URLSearchParams(window.location.search).get('ta') || '<?= (int)$id_ta_pilih ?>';

    document.querySelectorAll('.kelas-item').forEach(item => {
        const idKelas = item.getAttribute('data-idkelas') || '';
        const filterUrl = '&jurusan=' + encodeURIComponent(f.jurusan)
                        + '&tingkat=' + encodeURIComponent(f.tingkat)
                        + '&cari=' + encodeURIComponent(f.cari)
                        + '&ta=' + encodeURIComponent(taAktif);

        const detail = item.querySelector('.js-link-detail');
        const ledgerUts = item.querySelector('.js-link-ledger-uts');
        const ledgerUas = item.querySelector('.js-link-ledger-uas');

        if(detail) detail.href = '?page=pantau_siswa&id_kelas=' + idKelas + '&jenis=uas' + filterUrl;
        if(ledgerUts) ledgerUts.href = '?page=lihat_ledger&id_kelas=' + idKelas + '&jenis=uts' + filterUrl;
        if(ledgerUas) ledgerUas.href = '?page=lihat_ledger&id_kelas=' + idKelas + '&jenis=uas' + filterUrl;
    });
}

function filterKelas() {
    const f = getFilterAktif();
    const cariLower = (f.cari || '').toLowerCase().trim();

    let tampil = 0;

    document.querySelectorAll('.kelas-item').forEach(item => {
        const itemJurusan = item.getAttribute('data-jurusan') || '';
        const itemTingkat = item.getAttribute('data-tingkat') || '';
        const itemKelas = item.getAttribute('data-kelas') || '';

        const cocokJurusan = (f.jurusan === 'ALL' || itemJurusan === f.jurusan);
        const cocokTingkat = (f.tingkat === 'ALL' || itemTingkat === f.tingkat);
        const cocokCari = (cariLower === '' || itemKelas.includes(cariLower));

        if(cocokJurusan && cocokTingkat && cocokCari) {
            item.style.display = 'block';
            tampil++;
        } else {
            item.style.display = 'none';
        }
    });

    const empty = document.getElementById('kelasEmptyState');
    if(empty) empty.style.display = tampil > 0 ? 'none' : 'block';

    simpanFilterKeUrl();
    updateLinkAksiKelas();
}

document.addEventListener('DOMContentLoaded', function(){
    const params = new URLSearchParams(window.location.search);

    const savedJurusan = params.get('jurusan') || localStorage.getItem('kepsek_filter_jurusan') || 'ALL';
    const savedTingkat = params.get('tingkat') || localStorage.getItem('kepsek_filter_tingkat') || 'ALL';
    const savedCari = params.get('cari') || localStorage.getItem('kepsek_filter_cari') || '';

    document.getElementById('filterJurusan').value = savedJurusan;
    document.getElementById('filterTingkat').value = savedTingkat;
    document.getElementById('filterCari').value = savedCari;

    filterKelas();

    document.getElementById('filterJurusan').addEventListener('change', filterKelas);
    document.getElementById('filterTingkat').addEventListener('change', filterKelas);
    document.getElementById('filterCari').addEventListener('input', filterKelas);
});
</script>