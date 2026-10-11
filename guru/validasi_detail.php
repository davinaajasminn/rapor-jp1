<?php
$id_siswa = $_GET['id_siswa'] ?? '';

if(!isset($conn) || !$conn){
    if(!class_exists('Database')){
        require_once '../config/Database.php';
    }
    $db = new Database();
    $conn = $db->getConnection();
}

$cekKolomValidPKL = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'status_validasi_pkl'");
if($cekKolomValidPKL && $cekKolomValidPKL->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD status_validasi_pkl TINYINT(1) NOT NULL DEFAULT 0");
}

// FIX TAHUN AJARAN: halaman guru/wali gak punya dropdown TA, jadi selalu ikut TA yang statusnya Aktif.
$qTaAktifVD = $conn->query("SELECT id FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_aktif_vd = ($qTaAktifVD && $qTaAktifVD->num_rows > 0) ? (int)$qTaAktifVD->fetch_assoc()['id'] : 0;

// Toggle mapel reguler
if(isset($_GET['action']) && $_GET['action'] == 'toggle_valid'){
    $id_n = (int)$_GET['id_nilai'];
    $current_status = (int)$_GET['current'];
    $new_status = ($current_status == 1) ? 0 : 1;

    if($new_status == 1){
        $qCekNilaiToggle = $conn->query("SELECT n.nilai_t, n.nilai_p, n.nilai_k, k.tingkat
                                         FROM nilai n
                                         JOIN siswa s ON n.id_siswa = s.id
                                         JOIN riwayat_kelas rk ON rk.id_siswa = s.id AND rk.id_tahun_ajaran = n.id_tahun_ajaran
                                         JOIN kelas k ON rk.id_kelas = k.id
                                         WHERE n.id = '$id_n'
                                         LIMIT 1");
        if($qCekNilaiToggle && $qCekNilaiToggle->num_rows > 0){
            $dCekToggle = $qCekNilaiToggle->fetch_assoc();
            if($dCekToggle['tingkat'] == 'XII' && (
                $dCekToggle['nilai_t'] === null || $dCekToggle['nilai_t'] === '' || (float)$dCekToggle['nilai_t'] <= 0 ||
                $dCekToggle['nilai_p'] === null || $dCekToggle['nilai_p'] === '' || (float)$dCekToggle['nilai_p'] <= 0 ||
                $dCekToggle['nilai_k'] === null || $dCekToggle['nilai_k'] === '' || (float)$dCekToggle['nilai_k'] <= 0
            )){
                echo "<script>alert('Nilai kelas XII belum bisa divalidasi karena TP, STS, atau SAS masih kosong.'); window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
                exit;
            }
        }
    }

    $conn->query("UPDATE nilai SET status_validasi = '$new_status' WHERE id = '$id_n'");
    echo "<script>window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
    exit;
}

// Toggle PKL
if(isset($_GET['action']) && $_GET['action'] == 'toggle_valid_pkl'){
    $current_status = (int)$_GET['current'];
    $new_status = ($current_status == 1) ? 0 : 1;
    $conn->query("UPDATE data_pkl SET status_validasi_pkl = '$new_status' WHERE id_siswa = '$id_siswa'");
    echo "<script>window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
    exit;
}

$qS = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan, rk.id_kelas AS id_kelas_ta 
                    FROM siswa s 
                    JOIN riwayat_kelas rk ON rk.id_siswa = s.id AND rk.id_tahun_ajaran = '$id_tahun_aktif_vd'
                    JOIN kelas k ON rk.id_kelas = k.id 
                    WHERE s.id = '$id_siswa'");

if(!$qS || $qS->num_rows == 0){
    echo "<div class='alert alert-danger'>Data siswa tidak ditemukan.</div>";
    return;
}

$ds = $qS->fetch_assoc();
$nama_kelas = $ds['nama_kelas'];
$tingkat_romawi = $ds['tingkat'] ?? explode(' ', trim($nama_kelas))[0];
$id_kelas_siswa = $ds['id_kelas_ta'];
$kode_jurusan_kelas = $ds['kode_jurusan'] ?? '';

if(empty($kode_jurusan_kelas)){
    if(stripos($nama_kelas, 'RPL') !== false) $kode_jurusan_kelas = 'RPL';
    elseif(stripos($nama_kelas, 'AKL') !== false) $kode_jurusan_kelas = 'AKL';
    elseif(stripos($nama_kelas, 'AKT') !== false) $kode_jurusan_kelas = 'AKT';
    elseif(stripos($nama_kelas, 'BR') !== false) $kode_jurusan_kelas = 'BR';
    elseif(stripos($nama_kelas, 'MP') !== false) $kode_jurusan_kelas = 'MP';
}

$jurusan_key = $kode_jurusan_kelas;
$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $kode_jurusan_kelas);
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas_siswa);

// Sinkron dengan wali_siswa: prioritaskan mapel dari guru_mengajar kelas.
$qCekGMDetail = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                              FROM guru_mengajar gm
                              JOIN mapel m ON gm.id_mapel = m.id
                              WHERE gm.id_kelas = '$id_kelas_safe'
                              AND m.tingkat = '$tingkat_safe'
                              AND m.nama_mapel NOT LIKE '%PKL%'
                              AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                              AND m.nama_mapel NOT LIKE '%Prakerin%'");
$jml_gm_detail = ($qCekGMDetail && $qCekGMDetail->num_rows > 0) ? (int)$qCekGMDetail->fetch_assoc()['total'] : 0;
// Kelas XII tetap punya mapel reguler, jadi guru_mengajar tetap dipakai kalau ada.
$pakai_guru_mengajar = ($jml_gm_detail > 0);

$id_guru_login = $_SESSION['guru']['id'] ?? 0;
$nama_guru_login = $_SESSION['guru']['nama'] ?? 'Wali Kelas';
$nama_siswa_notif = addslashes($ds['nama_siswa']);

if(isset($_POST['validkan_semua'])){
    // Validasi semua nilai yang sudah lengkap.
    // Nilai di bawah KKM tetap boleh divalidasi jika keputusan akhirnya diterima wali kelas/sekolah.
    // Yang tetap tidak boleh divalidasi hanya nilai yang masih kosong/0.
    // 🩹 FIX: SAS/UAS TIDAK LAGI WAJIB buat bisa divalidasi. Sebelumnya mapel yang SAS-nya
    // belum diisi (guru mapel belum input UAS) gak bisa divalidasi sama sekali -- padahal
    // rapor UTS harusnya tetap bisa jalan cuma modal TP+STS. Rapor UAS sendiri TETAP kekunci
    // otomatis walau di sini divalidasi, karena rapor_uas.php punya pengecekan SAS>0 sendiri
    // yang independen dari status_validasi ini.
    $syarat_validkan_xii = "AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                            AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0";

    if($pakai_guru_mengajar){
        $conn->query("UPDATE nilai n
                      JOIN mapel m ON n.id_mapel = m.id
                      JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'
                      SET n.status_validasi = 1
                      WHERE n.id_siswa = '$id_siswa'
                      AND n.id_tahun_ajaran = '$id_tahun_aktif_vd'
                      $syarat_validkan_xii
                      AND m.tingkat = '$tingkat_safe'
                      AND m.nama_mapel NOT LIKE '%PKL%'
                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                      AND m.nama_mapel NOT LIKE '%Prakerin%'");
    } else {
        $conn->query("UPDATE nilai n
                      JOIN mapel m ON n.id_mapel = m.id
                      SET n.status_validasi = 1
                      WHERE n.id_siswa = '$id_siswa'
                      AND n.id_tahun_ajaran = '$id_tahun_aktif_vd'
                      $syarat_validkan_xii
                      AND m.tingkat = '$tingkat_safe'
                      AND m.nama_mapel NOT LIKE '%PKL%'
                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                      AND (
                            m.kelompok = 'A'
                            OR m.kode_jurusan = '$jurusan_safe'
                      )");
    }

    $conn->query("UPDATE data_pkl SET status_validasi_pkl = 1 WHERE id_siswa = '$id_siswa'");
    echo "<script>alert('Validasi disimpan. Mapel dengan TP+STS lengkap sudah divalidasi (bisa cetak Rapor UTS). Mapel yang SAS-nya belum diisi guru tetap tervalidasi untuk UTS, tapi Rapor UAS baru bisa dicetak setelah SAS diinput. Nilai di bawah KKM tetap ditandai merah sebagai remedial.'); window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
    exit;
}

if(isset($_POST['ping_guru'])){
    $id_penerima = (int)$_POST['id_guru'];
    $pesan = $conn->real_escape_string($_POST['pesan_custom']); 
    $conn->query("INSERT INTO notifikasi 
                    (pengirim_role, pengirim_id, nama_pengirim, penerima_role, penerima_id, pesan, link_tujuan) 
                  VALUES 
                    ('guru', '$id_guru_login', '$nama_guru_login', 'guru', '$id_penerima', '$pesan', '?page=input_nilai')");
    echo "<script>alert('Notifikasi berhasil dikirim ke Guru Mapel!'); window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
    exit;
}

if(isset($_POST['ping_siswa'])){
    $pesan = $conn->real_escape_string($_POST['pesan_custom']); 
    $conn->query("INSERT INTO notifikasi 
                    (pengirim_role, pengirim_id, nama_pengirim, penerima_role, penerima_id, pesan, link_tujuan) 
                  VALUES 
                    ('guru', '$id_guru_login', '$nama_guru_login (Wali Kelas)', 'siswa', '$id_siswa', '$pesan', '?page=lihat_nilai')");
    echo "<script>alert('Peringatan berhasil dikirim ke Siswa!'); window.location='?page=validasi_detail&id_siswa=$id_siswa';</script>";
    exit;
}

if($pakai_guru_mengajar){
    // Detail validasi ikut data mapel yang benar-benar diset untuk kelas di guru_mengajar.
    $qAllMapel = $conn->query("SELECT m.*, m.kkm, gm.id_guru, g.nama_guru 
                               FROM guru_mengajar gm
                               JOIN mapel m ON gm.id_mapel = m.id
                               LEFT JOIN guru g ON gm.id_guru = g.id
                               WHERE gm.id_kelas = '$id_kelas_safe'
                               AND m.tingkat = '$tingkat_safe'
                               AND m.nama_mapel NOT LIKE '%PKL%'
                               AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                               AND m.nama_mapel NOT LIKE '%Prakerin%'
                               GROUP BY m.id
                               ORDER BY m.kelompok ASC, m.urutan ASC, m.nama_mapel ASC");
} else {
    // Fallback kalau guru_mengajar belum diset.
    $qAllMapel = $conn->query("SELECT m.*, m.kkm, gm.id_guru, g.nama_guru 
                               FROM mapel m 
                               LEFT JOIN guru_mengajar gm ON m.id = gm.id_mapel AND gm.id_kelas = '$id_kelas_safe'
                               LEFT JOIN guru g ON gm.id_guru = g.id
                               WHERE m.tingkat = '$tingkat_safe'
                               AND (
                                    m.kelompok = 'A'
                                    OR m.kode_jurusan = '$jurusan_safe'
                               )
                               AND m.nama_mapel NOT LIKE '%PKL%'
                               AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                               AND m.nama_mapel NOT LIKE '%Prakerin%'
                               GROUP BY m.id
                               ORDER BY m.kelompok ASC, m.urutan ASC, m.nama_mapel ASC");
}

$qPKL = $conn->query("SELECT p.*, g.nama_guru AS nama_pembimbing
                      FROM data_pkl p
                      LEFT JOIN guru g ON p.id_guru_pembimbing = g.id
                      WHERE p.id_siswa = '$id_siswa'
                      LIMIT 1");
$dpkl = ($qPKL && $qPKL->num_rows > 0) ? $qPKL->fetch_assoc() : null;

$skor_pkl = [];
if($dpkl){
    foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $f){
        if($dpkl[$f] !== null && $dpkl[$f] !== '') $skor_pkl[] = (int)$dpkl[$f];
    }
}
$nilai_pkl = $dpkl ? (!empty($dpkl['nilai_pkl']) ? (int)$dpkl['nilai_pkl'] : (!empty($skor_pkl) ? round(array_sum($skor_pkl)/count($skor_pkl)) : '-')) : '-';
$pkl_lengkap = $dpkl && count($skor_pkl) == 4;
$status_valid_pkl = (int)($dpkl['status_validasi_pkl'] ?? 0);
?>

<style>
    .btn-print-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
    }

    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #fff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
    }

    .btn-soft-yellow {
        background: #F9E1A8 !important;
        color: #8A5A00 !important;
        border: 1px solid #E9C979 !important;
        font-weight: 800 !important;
    }

    .btn-soft-red {
        background: #F8D7DA !important;
        color: #7A1D2B !important;
        border: 1px solid #F0AEB8 !important;
        font-weight: 800 !important;
    }

    .status-valid-soft {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
    }

    .status-pending-brown {
        background: #F9E1A8 !important;
        color: #8A5A00 !important;
        border: 1px solid #E9C979 !important;
    }

    .pkl-card-validasi {
        border: none;
        border-radius: 20px;
        box-shadow: 0 8px 22px rgba(80, 115, 155, 0.10);
        overflow: hidden;
        background: #fff;
    }

    .pkl-head {
        background: #fff7e6;
        color: #7A5200;
        padding: 16px 20px;
        border-bottom: 1px solid #ffe2ad;
    }

    .pkl-score-box {
        background: #f8fbff;
        border: 1px solid #e5eef8;
        border-radius: 16px;
        padding: 14px;
        text-align: center;
    }
</style>

<div class="p-4 mb-4 shadow-sm" style="background:#C9DDF2; border-radius:22px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="badge rounded-pill bg-white text-primary px-3 py-2 mb-3">
                <i class="fas fa-user-check me-1"></i> Detail Validasi Siswa
            </span>
            <h3 class="fw-bold mb-2" style="color:#0d385e;">Manajemen Validasi: <?= strtoupper(htmlspecialchars($ds['nama_siswa'])) ?></h3>
            <p class="mb-0 fw-bold" style="color:#0d385e;">
                Kelas: <?= htmlspecialchars($nama_kelas) ?> | Tingkat: <?= htmlspecialchars($tingkat_romawi) ?> | Jurusan: <?= htmlspecialchars($jurusan_key ?: '-') ?>
            </p>
        </div>

        <a href="?page=wali_siswa&id_kelas=<?= $id_kelas_siswa ?>" class="btn btn-print-soft btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="alert border-0 shadow-sm mb-4 px-4 py-3 bg-white" style="border-radius:15px; border-left: 5px solid #E994A5 !important;">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-circle text-danger me-3 fs-4"></i>
        <div>
            <h6 class="fw-bold text-dark mb-0">Validasi Mapel Reguler dan PKL Dipisah</h6>
            <small class="text-muted">
                Mapel reguler tetap memakai TP, STS, SAS, dan NA. PKL langsung memakai 4 skor TP dan nilai akhir PKL, tanpa STS/SAS.
            </small>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3"><i class="fas fa-book me-2 text-primary"></i>A. Validasi Mapel Reguler</h5>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <table class="table table-hover align-middle mb-0 text-center">
        <thead class="bg-light small fw-bold text-muted">
            <tr>
                <th width="40">NO</th>
                <th class="text-start">MATA PELAJARAN</th>
                <th>KKM</th>
                <th>TP</th>
                <th>STS</th>
                <th>SAS</th>
                <th>NA</th>
                <th>STATUS</th>
                <th>AKSI PING</th>
            </tr>
        </thead>

        <tbody>
            <?php 
            $no = 1;
            if($qAllMapel && $qAllMapel->num_rows > 0):
            while($m = $qAllMapel->fetch_assoc()): 
                $id_m = $m['id'];
                $kkm_mapel = $m['kkm'] ?? 75; 
                $nama_mapel = $m['nama_mapel'];
                $mapel_js = addslashes($nama_mapel); 
                $id_guru_mapel = $m['id_guru'];
                $nama_guru_mapel = $m['nama_guru'];

                $qN = $conn->query("SELECT * FROM nilai WHERE id_siswa = '$id_siswa' AND id_mapel = '$id_m' AND id_tahun_ajaran = '$id_tahun_aktif_vd' LIMIT 1");
                $dn = ($qN && $qN->num_rows > 0) ? $qN->fetch_assoc() : null;

                $bg_row = "";
                $nilai_tp = "-";
                $nilai_sts = "-";
                $nilai_sas = "-";
                $na = "-";
                $warna_tp = "";
                $warna_sts = "";
                $warna_sas = "";
                $warna_na = "text-muted";
                $tebal_na = "";
                $ada_remedial = false;
                $aksi_ping = "-";
                $btn_status = "<span class='badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2 small'>Belum Input</span>";

                if($dn){
                    $tp = $dn['nilai_t']; 
                    $sts = $dn['nilai_p']; 
                    $sas = $dn['nilai_k'];

                    if ((empty($sts) || $sts == 0) && (empty($sas) || $sas == 0)) {
                        $na = $tp; 
                    } elseif (empty($sas) || $sas == 0) {
                        $na = round(((4 * $tp) + (2 * $sts)) / 6); 
                    } else {
                        $na = round(((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10); 
                    }

                    $nilai_tp = $tp; 
                    $nilai_sts = ($sts == 0 ? '-' : $sts); 
                    $nilai_sas = ($sas == 0 ? '-' : $sas);

                    $warna_tp = ($tp < $kkm_mapel) ? "text-danger fw-bold" : "";
                    $warna_sts = ($sts > 0 && $sts < $kkm_mapel) ? "text-danger fw-bold" : "";
                    $warna_sas = ($sas > 0 && $sas < $kkm_mapel) ? "text-danger fw-bold" : "";
                    $warna_na = ($na < $kkm_mapel) ? "text-danger fw-bold" : "text-primary fw-bold";
                    $tebal_na = "fw-bold";

                    $st = $dn['status_validasi'];
                    $id_nilai = $dn['id'];
                    $sas_belum_diisi = (empty($sas) || $sas == 0);
                    $label_valid = ($st == 1 && $sas_belum_diisi) ? 'Valid (UTS)' : ($st == 1 ? 'Valid' : 'Pending');
                    $btn_status = "<a href='?page=validasi_detail&id_siswa=$id_siswa&action=toggle_valid&id_nilai=$id_nilai&current=$st' 
                                      class='badge text-decoration-none rounded-pill px-3 py-2 " . ($st == 1 ? 'status-valid-soft' : 'status-pending-brown') . "'>
                                      $label_valid
                                   </a>";

                    $ada_remedial = (($tp > 0 && $tp < $kkm_mapel) || ($sts > 0 && $sts < $kkm_mapel) || ($sas > 0 && $sas < $kkm_mapel) || ($na != "-" && $na < $kkm_mapel));
                } else {
                    $bg_row = "table-light text-muted";

                    if(!empty($id_guru_mapel) && $id_guru_mapel != $id_guru_login){
                        $aksi_ping = "
                        <form method='POST' class='m-0' id='formPingGuru_$id_m'>
                            <input type='hidden' name='id_guru' value='$id_guru_mapel'>
                            <input type='hidden' name='ping_guru' value='1'>
                            <input type='hidden' name='pesan_custom' id='pesan_guru_$id_m' value=''>
                            <button type='button' class='btn btn-soft-yellow btn-sm rounded-pill shadow-sm fw-bold' style='font-size: 10px;' onclick='kirimPingGuru(\"$id_m\", \"$mapel_js\", \"$nama_siswa_notif\")'>
                                <i class='fas fa-bell'></i> Peringatkan Guru
                            </button>
                        </form>";
                    } elseif(empty($id_guru_mapel)){
                        $aksi_ping = "<small class='text-danger'>Belum diset</small>";
                    }
                }

                if($ada_remedial){
                    $aksi_ping = "
                    <form method='POST' class='m-0' id='formPingSiswa_$id_m'>
                        <input type='hidden' name='ping_siswa' value='1'>
                        <input type='hidden' name='pesan_custom' id='pesan_siswa_$id_m' value=''>
                        <button type='button' class='btn btn-soft-red btn-sm rounded-pill shadow-sm fw-bold' style='font-size: 10px;' onclick='kirimPingSiswa(\"$id_m\", \"$mapel_js\", \"$nama_siswa_notif\")'>
                            <i class='fas fa-bullhorn'></i> Tegur Siswa
                        </button>
                    </form>";
                }
            ?>
            <tr class="<?= $bg_row ?>">
                <td class="text-muted small"><?= $no++ ?></td>
                <td class="text-start fw-bold small">
                    <?= htmlspecialchars($nama_mapel) ?>
                    <br>
                    <small class="text-muted fw-normal"><?= htmlspecialchars($nama_guru_mapel ?? 'Guru Kosong') ?></small>
                </td>
                <td class="small text-muted fw-bold"><?= $kkm_mapel ?></td>
                <td class="<?= $warna_tp ?>"><?= $nilai_tp ?></td>
                <td class="<?= $warna_sts ?>"><?= $nilai_sts ?></td>
                <td class="<?= $warna_sas ?>"><?= $nilai_sas ?></td>
                <td class="<?= $tebal_na ?> <?= $warna_na ?> fs-6"><?= $na ?></td>
                <td><?= $btn_status ?></td>
                <td><?= $aksi_ping ?></td>
            </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Tidak ada mapel reguler.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h5 class="fw-bold mb-3"><i class="fas fa-briefcase me-2 text-warning"></i>B. Validasi Nilai PKL</h5>

<div class="pkl-card-validasi mb-4">
    <div class="pkl-head">
        <h6 class="fw-bold mb-1">Praktik Kerja Lapangan (PKL)</h6>
        <small>PKL tidak memakai STS/SAS. Nilai akhir berasal dari 4 skor Tujuan Pembelajaran.</small>
    </div>

    <div class="p-4">
        <?php if($dpkl): ?>
            <div class="row g-3 align-items-stretch">
                <div class="col-md-3">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">Tempat PKL</small>
                        <div class="fw-bold"><?= htmlspecialchars($dpkl['mitra_dudi'] ?: '-') ?></div>
                        <small class="text-muted"><?= htmlspecialchars($dpkl['lokasi'] ?: '-') ?></small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">TP 1</small>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($dpkl['tp1_skor'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">TP 2</small>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($dpkl['tp2_skor'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">TP 3</small>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($dpkl['tp3_skor'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">TP 4</small>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($dpkl['tp4_skor'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="col-md-1">
                    <div class="pkl-score-box h-100">
                        <small class="text-muted fw-bold">Nilai</small>
                        <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($nilai_pkl) ?></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <small class="text-muted fw-bold">Guru Pembimbing:</small>
                    <span class="fw-bold"><?= htmlspecialchars($dpkl['nama_pembimbing'] ?? '-') ?></span>
                    <?php if(!$pkl_lengkap): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-2">PKL Belum Lengkap</span>
                    <?php endif; ?>
                </div>

                <?php if($pkl_lengkap): ?>
                    <a href="?page=validasi_detail&id_siswa=<?= $id_siswa ?>&action=toggle_valid_pkl&current=<?= $status_valid_pkl ?>"
                       class="badge text-decoration-none rounded-pill px-3 py-2 <?= $status_valid_pkl == 1 ? 'status-valid-soft' : 'status-pending-brown' ?>">
                        <?= $status_valid_pkl == 1 ? 'Valid PKL' : 'Pending PKL' ?>
                    </a>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">Belum Bisa Divalidasi</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning border-0 rounded-4 mb-0">
                Data PKL siswa ini belum diinput atau belum ditentukan guru pembimbingnya oleh admin.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card p-4 border-0 shadow-sm rounded-4 text-center bg-white">
    <form method="POST">
        <button type="submit" name="validkan_semua" class="btn btn-soft-blue fw-bold px-5 rounded-pill shadow-sm py-2">
            <i class="fas fa-check-circle me-2"></i>VALIDASI SEMUA YANG READY
        </button>
    </form>
</div>

<script>
function kirimPingGuru(id_m, mapel, siswa) {
    let template = `Bapak/Ibu, nilai mapel ${mapel} untuk siswa ${siswa} masih kosong. Mohon segera diinput ya.`;
    let pesan = prompt("Kirim notifikasi ke Guru Mapel:", template);
    if (pesan != null && pesan.trim() !== "") {
        document.getElementById('pesan_guru_' + id_m).value = pesan;
        document.getElementById('formPingGuru_' + id_m).submit();
    }
}

function kirimPingSiswa(id_m, mapel, siswa) {
    let template = `Halo ${siswa}, terdapat nilai pada ${mapel} yang masih perlu diperbaiki. Silakan hubungi guru mapel/wali kelas ya.`;
    let pesan = prompt("Kirim teguran ke siswa:", template);
    if (pesan != null && pesan.trim() !== "") {
        document.getElementById('pesan_siswa_' + id_m).value = pesan;
        document.getElementById('formPingSiswa_' + id_m).submit();
    }
}
</script>