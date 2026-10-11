<?php
// =====================================================
// PENGESAHAN RAPOR UTS & UAS DIPISAH
// =====================================================
// Catatan:
// File ini otomatis menambahkan kolom:
// - rapor_uts_dikunci
// - rapor_uas_dikunci
// kalau belum ada di tabel identitas_sekolah.
// Jadi kamu tidak wajib ALTER manual dari phpMyAdmin.
// =====================================================

// Catatan: fungsi kolom_ada(), tambah_kolom_jika_belum_ada(), hitung_progress_nilai(),
// serta variabel $id_ta_aktif / $label_ta_aktif sudah didefinisikan & dijalankan di index.php
// (file ini selalu di-include dari index.php, jadi semua itu sudah tersedia di sini).
// Sengaja TIDAK didefinisikan ulang di sini supaya tidak fatal error "Cannot redeclare function".

// Pengesahan rapor selalu mengacu ke tahun ajaran yang sedang AKTIF (bukan tahun ajaran
// yang mungkin sedang dipilih lewat dropdown di menu monitoring lain).
$data_uts = hitung_progress_nilai($conn, 'uts', $id_ta_aktif);
$data_uas = hitung_progress_nilai($conn, 'uas', $id_ta_aktif);

$progress_uts = $data_uts['progress'];
$progress_uas = $data_uas['progress'];

// =====================================================
// HITUNG PER SISWA (bukan per baris nilai)
// Dashboard "Lengkap: 303 dari 303" itu ngitung kombinasi
// siswa x mapel, gampang bikin bingung. Di sini dihitung ulang
// per SISWA: seorang siswa dianggap "lengkap" kalau SEMUA mapel
// wajibnya (via guru_mengajar, sesuai riwayat_kelas TA aktif)
// sudah lengkap nilainya. Kriteria lengkap per mapel disamain
// persis sama hitung_progress_nilai() di index.php biar konsisten.
// Fungsi ini sengaja diberi nama beda supaya tidak bentrok
// dengan hitung_progress_nilai() yang sudah ada di index.php.
function hitung_progress_siswa_pengesahan($conn, $jenis, $id_ta){
    $ta_safe = (int)$id_ta;

    $where_mapel_reguler = "
        m.nama_mapel NOT LIKE '%PKL%'
        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
        AND m.nama_mapel NOT LIKE '%Prakerin%'
    ";

    if($jenis === 'uts'){
        $syarat_lengkap = "
            n.id IS NOT NULL
            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
        ";
    } else {
        $syarat_lengkap = "
            n.id IS NOT NULL
            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
            AND COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0
        ";
    }

    $q = $conn->query("SELECT
                            tugas.id_siswa,
                            COUNT(*) AS jumlah_wajib,
                            SUM(CASE WHEN $syarat_lengkap THEN 1 ELSE 0 END) AS jumlah_lengkap
                        FROM (
                            SELECT DISTINCT
                                gm.id_kelas,
                                gm.id_mapel,
                                s.id AS id_siswa
                            FROM guru_mengajar gm
                            JOIN mapel m ON gm.id_mapel = m.id
                            JOIN kelas k ON gm.id_kelas = k.id
                            JOIN riwayat_kelas rk ON rk.id_kelas = k.id AND rk.id_tahun_ajaran = '$ta_safe'
                            JOIN siswa s ON s.id = rk.id_siswa
                            WHERE gm.id_tahun_ajaran = '$ta_safe'
                            AND $where_mapel_reguler
                        ) tugas
                        LEFT JOIN nilai n
                            ON n.id_siswa = tugas.id_siswa
                            AND n.id_mapel = tugas.id_mapel
                            AND n.id_tahun_ajaran = '$ta_safe'
                        GROUP BY tugas.id_siswa");

    $total_siswa = 0;
    $siswa_lengkap = 0;

    if($q){
        while($row = $q->fetch_assoc()){
            $total_siswa++;
            if((int)$row['jumlah_wajib'] > 0 && (int)$row['jumlah_lengkap'] >= (int)$row['jumlah_wajib']){
                $siswa_lengkap++;
            }
        }
    }

    return [
        'total_siswa' => $total_siswa,
        'siswa_lengkap' => $siswa_lengkap,
        'siswa_kurang' => max(0, $total_siswa - $siswa_lengkap)
    ];
}

$siswa_uts = hitung_progress_siswa_pengesahan($conn, 'uts', $id_ta_aktif);
$siswa_uas = hitung_progress_siswa_pengesahan($conn, 'uas', $id_ta_aktif);

// =====================================================
// AMBIL STATUS KUNCI SAAT INI
// Status ini dipakai untuk validasi urutan kunci/buka kunci.
// =====================================================
$id_ta_kunci = (int)$id_ta_aktif;
$qIdenStatusAwal = $conn->query("SELECT rapor_uts_dikunci, rapor_uas_dikunci FROM tahun_ajaran WHERE id = '$id_ta_kunci' LIMIT 1");
$dIdenStatusAwal = ($qIdenStatusAwal && $qIdenStatusAwal->num_rows > 0) ? $qIdenStatusAwal->fetch_assoc() : [];
$status_uts_awal = (int)($dIdenStatusAwal['rapor_uts_dikunci'] ?? 0);
$status_uas_awal = (int)($dIdenStatusAwal['rapor_uas_dikunci'] ?? 0);

// =====================================================
// PROSES KUNCI / BUKA KUNCI
// =====================================================
if(isset($_POST['aksi_kunci'])){
    $jenis = $_POST['jenis'] ?? '';
    $status_baru = (int)($_POST['status_baru'] ?? 0);

    if($jenis === 'uts'){
        $cek = hitung_progress_nilai($conn, 'uts', $id_ta_aktif);

        // Kalau UAS masih terkunci, UTS tidak boleh dibuka dulu.
        // Urutan revisi harus dari final ke tengah semester: buka UAS dulu, baru UTS.
        if($status_baru == 0 && $status_uas_awal == 1){
            echo "<script>alert('Buka kunci UAS terlebih dahulu sebelum membuka UTS.'); window.location='?page=pengesahan';</script>";
            exit;
        }

        if($status_baru == 1 && $cek['progress'] < 100){
            echo "<script>alert('Rapor UTS belum bisa disahkan karena progress belum 100%.'); window.location='?page=pengesahan';</script>";
            exit;
        }

        $conn->query("UPDATE tahun_ajaran SET rapor_uts_dikunci = '$status_baru' WHERE id = '$id_ta_kunci' LIMIT 1");
        $pesan = ($status_baru == 1) ? 'Rapor UTS berhasil disahkan dan dikunci!' : 'Kunci Rapor UTS berhasil dibuka!';
        echo "<script>alert('$pesan'); window.location='?page=pengesahan';</script>";
        exit;
    }

    if($jenis === 'uas'){
        $cek = hitung_progress_nilai($conn, 'uas', $id_ta_aktif);

        // UAS hanya boleh dikunci setelah UTS terkunci, karena UAS memakai komponen TP dan STS juga.
        if($status_baru == 1 && $status_uts_awal != 1){
            echo "<script>alert('Kunci Rapor UTS terlebih dahulu sebelum mengunci Rapor UAS.'); window.location='?page=pengesahan';</script>";
            exit;
        }

        if($status_baru == 1 && $cek['progress'] < 100){
            echo "<script>alert('Rapor UAS belum bisa disahkan karena progress belum 100%.'); window.location='?page=pengesahan';</script>";
            exit;
        }

        $conn->query("UPDATE tahun_ajaran SET rapor_uas_dikunci = '$status_baru' WHERE id = '$id_ta_kunci' LIMIT 1");
        $pesan = ($status_baru == 1) ? 'Rapor UAS berhasil disahkan dan dikunci!' : 'Kunci Rapor UAS berhasil dibuka!';
        echo "<script>alert('$pesan'); window.location='?page=pengesahan';</script>";
        exit;
    }
}

// =====================================================
// AMBIL STATUS KUNCI
// =====================================================
$qIden = $conn->query("SELECT rapor_uts_dikunci, rapor_uas_dikunci FROM tahun_ajaran WHERE id = '$id_ta_kunci' LIMIT 1");
$dIden = ($qIden && $qIden->num_rows > 0) ? $qIden->fetch_assoc() : [];

$status_uts = (int)($dIden['rapor_uts_dikunci'] ?? 0);
$status_uas = (int)($dIden['rapor_uas_dikunci'] ?? 0);
?>

<style>
    .pengesahan-header {
        border-radius: 18px !important;
        border-left: 5px solid #E994A5 !important;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08) !important;
    }

    .pengesahan-header h5 {
        color: #0d385e !important;
        font-size: 22px !important;
    }

    .pengesahan-card {
        border-radius: 24px !important;
        border: none !important;
        background: #ffffff !important;
        box-shadow: 0 7px 22px rgba(80, 115, 155, 0.10) !important;
        overflow: hidden;
        height: 100%;
    }

    .pengesahan-card.uts {
        border-top: 7px solid #6FA7DF !important;
    }

    .pengesahan-card.uas {
        border-top: 7px solid #5E9F86 !important;
    }

    .pengesahan-card-body {
        padding: 28px !important;
    }

    .pengesahan-label {
        font-size: 13px !important;
        color: #64748b !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 8px;
    }

    .pengesahan-title {
        color: #0d385e !important;
        font-size: 25px !important;
        font-weight: 900 !important;
        margin-bottom: 12px !important;
    }

    .pengesahan-percent {
        font-size: 58px !important;
        line-height: 1 !important;
        font-weight: 900 !important;
        margin-bottom: 8px !important;
    }

    .pengesahan-percent.uts {
        color: #6FA7DF !important;
    }

    .pengesahan-percent.uas {
        color: #5E9F86 !important;
    }

    .progress-pengesahan {
        height: 12px !important;
        border-radius: 999px !important;
        background: #e9eef5 !important;
        overflow: hidden !important;
        margin-bottom: 16px !important;
    }

    .progress-pengesahan .bar {
        height: 100% !important;
        border-radius: 999px !important;
    }

    .bar-uts {
        background: #6FA7DF !important;
    }

    .bar-uas {
        background: #5E9F86 !important;
    }

    .badge-status-open {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
        border-radius: 999px !important;
        padding: 9px 16px !important;
        font-size: 12px !important;
        font-weight: 800 !important;
    }

    .badge-status-lock {
        background: #FFE1E7 !important;
        color: #9b1c31 !important;
        border: 1px solid #F3A5B4 !important;
        border-radius: 999px !important;
        padding: 9px 16px !important;
        font-size: 12px !important;
        font-weight: 800 !important;
    }

    .info-soft-warning {
        background: #FFF1CC !important;
        color: #7A5200 !important;
        border: 1px solid #E8C46A !important;
        border-radius: 16px !important;
        font-size: 13px !important;
        padding: 12px 15px !important;
        margin-top: 16px !important;
    }

    .info-soft-success {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
        border-radius: 16px !important;
        font-size: 13px !important;
        padding: 12px 15px !important;
        margin-top: 16px !important;
    }

    .btn-lock-soft {
        background: #E994A5 !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        min-height: 46px !important;
        padding: 0 28px !important;
        font-size: 14px !important;
        box-shadow: 0 6px 14px rgba(233, 148, 165, 0.25) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-lock-soft:disabled {
        opacity: .55 !important;
        cursor: not-allowed !important;
        box-shadow: none !important;
    }

    .btn-unlock-soft {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        min-height: 46px !important;
        padding: 0 28px !important;
        font-size: 14px !important;
        box-shadow: 0 6px 14px rgba(157, 220, 196, 0.22) !important;
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

    .locked-block {
        background: #f8f9fb !important;
        border: 1px dashed #ccd5e0 !important;
        border-radius: 14px !important;
        padding: 12px !important;
        display: inline-block !important;
        max-width: 100%;
    }

    .locked-block .btn {
        min-height: 38px !important;
        padding: 0 20px !important;
        font-size: 12.5px !important;
    }

    .locked-block .info-soft-warning {
        margin-top: 10px !important;
        padding: 9px 12px !important;
        font-size: 12px !important;
    }
</style>

<div class="card border-0 p-4 mb-4 bg-white pengesahan-header">
    <h5 class="fw-bold mb-2">
        <i class="fas fa-file-signature me-2 text-danger"></i>Pengesahan & Kunci Rapor
    </h5>
    <p class="text-muted small mb-0">
        Pengesahan dipisah antara UTS dan UAS. Rapor hanya bisa dikunci jika progress masing-masing sudah 100%.
    </p>
    <span class="badge bg-light text-dark border mt-2" style="font-weight:700; width: fit-content;">
        <i class="fas fa-calendar-check me-1 text-primary"></i> Tahun Ajaran: <?= htmlspecialchars($label_ta_aktif) ?>
    </span>
</div>

<div class="row g-4 align-items-stretch">
    <!-- CARD UTS -->
    <div class="col-lg-6">
        <div class="pengesahan-card uts">
            <div class="pengesahan-card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <div class="pengesahan-label">Pengesahan Rapor</div>
                        <div class="pengesahan-title">
                            <i class="fas fa-file-alt me-2 text-primary"></i>UTS
                        </div>
                    </div>

                    <?php if($status_uts == 1): ?>
                        <span class="badge-status-lock"><i class="fas fa-lock me-1"></i> TERKUNCI</span>
                    <?php else: ?>
                        <span class="badge-status-open"><i class="fas fa-lock-open me-1"></i> TERBUKA</span>
                    <?php endif; ?>
                </div>

                <div class="pengesahan-percent uts"><?= $progress_uts ?>%</div>
                <div class="progress-pengesahan">
                    <div class="bar bar-uts" style="width: <?= $progress_uts ?>%;"></div>
                </div>

                <p class="small text-muted mb-1">
                    Sudah diinput: <b><?= $siswa_uts['siswa_lengkap'] ?></b> dari <b><?= $siswa_uts['total_siswa'] ?></b> siswa.
                    <?php if($siswa_uts['siswa_kurang'] > 0): ?>
                        <br>Belum lengkap: <b><?= $siswa_uts['siswa_kurang'] ?></b> siswa.
                    <?php endif; ?>
                </p>

                <?php if($progress_uts < 100): ?>
                    <div class="info-soft-warning">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Rapor UTS belum bisa disahkan karena masih ada data yang belum lengkap.
                    </div>
                <?php else: ?>
                    <div class="info-soft-success">
                        <i class="fas fa-check-circle me-1"></i>
                        Seluruh data UTS sudah lengkap dan siap disahkan.
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <?php if($status_uts == 0): ?>
                        <form method="POST" onsubmit="return confirm('Yakin ingin mengesahkan dan mengunci Rapor UTS?');">
                            <input type="hidden" name="jenis" value="uts">
                            <input type="hidden" name="status_baru" value="1">
                            <button type="submit" name="aksi_kunci" class="btn btn-lock-soft" <?= ($progress_uts < 100) ? 'disabled' : '' ?>>
                                SAHKAN & KUNCI UTS <i class="fas fa-lock ms-2"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <?php if($status_uas == 1): ?>
                            <div class="locked-block">
                                <button type="button" class="btn btn-unlock-soft" disabled style="opacity:.55; cursor:not-allowed; box-shadow:none !important;">
                                    BUKA KUNCI UTS <i class="fas fa-unlock ms-2"></i>
                                </button>
                                <div class="info-soft-warning">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Buka kunci UAS terlebih dahulu sebelum membuka UTS.
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Buka kunci Rapor UTS untuk revisi?');">
                                <input type="hidden" name="jenis" value="uts">
                                <input type="hidden" name="status_baru" value="0">
                                <button type="submit" name="aksi_kunci" class="btn btn-unlock-soft">
                                    BUKA KUNCI UTS <i class="fas fa-unlock ms-2"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD UAS -->
    <div class="col-lg-6">
        <div class="pengesahan-card uas">
            <div class="pengesahan-card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <div class="pengesahan-label">Pengesahan Rapor</div>
                        <div class="pengesahan-title">
                            <i class="fas fa-file-signature me-2 text-success"></i>UAS
                        </div>
                    </div>

                    <?php if($status_uas == 1): ?>
                        <span class="badge-status-lock"><i class="fas fa-lock me-1"></i> TERKUNCI</span>
                    <?php else: ?>
                        <span class="badge-status-open"><i class="fas fa-lock-open me-1"></i> TERBUKA</span>
                    <?php endif; ?>
                </div>

                <div class="pengesahan-percent uas"><?= $progress_uas ?>%</div>
                <div class="progress-pengesahan">
                    <div class="bar bar-uas" style="width: <?= $progress_uas ?>%;"></div>
                </div>

                <p class="small text-muted mb-1">
                    Sudah diinput: <b><?= $siswa_uas['siswa_lengkap'] ?></b> dari <b><?= $siswa_uas['total_siswa'] ?></b> siswa.
                    <?php if($siswa_uas['siswa_kurang'] > 0): ?>
                        <br>Belum lengkap: <b><?= $siswa_uas['siswa_kurang'] ?></b> siswa.
                    <?php endif; ?>
                </p>

                <?php if($progress_uas < 100): ?>
                    <div class="info-soft-warning">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Rapor UAS belum bisa disahkan karena masih ada data yang belum lengkap.
                    </div>
                <?php else: ?>
                    <div class="info-soft-success">
                        <i class="fas fa-check-circle me-1"></i>
                        Seluruh data UAS sudah lengkap dan siap disahkan.
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <?php if($status_uas == 0): ?>
                        <?php if($status_uts != 1): ?>
                            <div class="locked-block">
                                <button type="button" class="btn btn-lock-soft" disabled>
                                    SAHKAN & KUNCI UAS <i class="fas fa-lock ms-2"></i>
                                </button>
                                <div class="info-soft-warning">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Kunci Rapor UTS terlebih dahulu sebelum mengunci Rapor UAS.
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Yakin ingin mengesahkan dan mengunci Rapor UAS?');">
                                <input type="hidden" name="jenis" value="uas">
                                <input type="hidden" name="status_baru" value="1">
                                <button type="submit" name="aksi_kunci" class="btn btn-lock-soft" <?= ($progress_uas < 100) ? 'disabled' : '' ?>>
                                    SAHKAN & KUNCI UAS <i class="fas fa-lock ms-2"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Buka kunci Rapor UAS untuk revisi?');">
                            <input type="hidden" name="jenis" value="uas">
                            <input type="hidden" name="status_baru" value="0">
                            <button type="submit" name="aksi_kunci" class="btn btn-unlock-soft">
                                BUKA KUNCI UAS <i class="fas fa-unlock ms-2"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>