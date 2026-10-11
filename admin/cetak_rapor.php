<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

if (!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

$id_kelas = $_GET['id_kelas'] ?? '';
$search = $_GET['search'] ?? '';

// --- Filter Tahun Ajaran & Semester ---
// Admin bisa pilih tahun ajaran/semester mana yang mau dicetak/dilihat statusnya.
// Kalau tidak dipilih (pertama kali buka halaman), default ke tahun ajaran yang lagi Aktif.
$id_ta_get = trim((string)($_GET['id_tahun_ajaran'] ?? ''));

if ($id_ta_get !== '' && ctype_digit($id_ta_get)) {
    $id_ta_safe = mysqli_real_escape_string($conn, $id_ta_get);
    $qTaPilih = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE id='$id_ta_safe' LIMIT 1");
    $ta_terpilih = ($qTaPilih && $qTaPilih->num_rows > 0) ? $qTaPilih->fetch_assoc() : null;
}

if (empty($ta_terpilih)) {
    $qTaPilih = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
    $ta_terpilih = ($qTaPilih && $qTaPilih->num_rows > 0) ? $qTaPilih->fetch_assoc() : null;
}

if (empty($ta_terpilih)) {
    $qTaPilih = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    $ta_terpilih = ($qTaPilih && $qTaPilih->num_rows > 0) ? $qTaPilih->fetch_assoc() : ['id' => 0, 'tahun' => '-', 'semester' => '-'];
}

$id_tahun_dipilih = (int)$ta_terpilih['id'];
$id_tahun_dipilih_safe = mysqli_real_escape_string($conn, $id_tahun_dipilih);

// Daftar semua tahun ajaran (termasuk yang sudah tidak aktif) buat dropdown filter.
// Data nilai tiap tahun ajaran/semester tetap tersimpan terpisah, jadi yang lama tetap bisa dipilih & dilihat.
$qDaftarTA = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");

// --- Kolom validasi khusus PKL untuk kelas XII ---
$cekKolomValidPKL = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'status_validasi_pkl'");
if($cekKolomValidPKL && $cekKolomValidPKL->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD status_validasi_pkl TINYINT(1) NOT NULL DEFAULT 0");
}

// --- Deteksi kolom jurusan pada tabel mapel ---
// Ini penting supaya X RPL 2 cuma dihitung 9 mapel miliknya, bukan semua mapel tingkat X.
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

$jurusan_pilih = $_GET['jurusan'] ?? '';
$tingkat_pilih = $_GET['tingkat'] ?? '';

// Ambil data jurusan untuk filter
$qJurusan = $conn->query("SELECT kode_jurusan, nama_jurusan FROM jurusan ORDER BY kode_jurusan ASC");

// Query kelas berdasarkan filter jurusan dan tingkat
$where_kelas = "WHERE 1=1";

if (!empty($jurusan_pilih)) {
    $jurusan_safe = mysqli_real_escape_string($conn, $jurusan_pilih);
    $where_kelas .= " AND kode_jurusan = '$jurusan_safe'";
}

if (!empty($tingkat_pilih)) {
    $tingkat_safe = mysqli_real_escape_string($conn, $tingkat_pilih);
    $where_kelas .= " AND tingkat = '$tingkat_safe'";
}

$kelasQ = $conn->query("SELECT * FROM kelas $where_kelas ORDER BY tingkat ASC, kode_jurusan ASC, nama_kelas ASC");

// Data cetak massal selalu disiapkan
$ada_kelas_dipilih = !empty($id_kelas);
$dKlsInfo = null;
$tk_kelas = '';

if ($ada_kelas_dipilih) {
    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);
    $qKlsInfo = $conn->query("SELECT tingkat, nama_kelas FROM kelas WHERE id='$id_kelas_safe' LIMIT 1");

    if ($qKlsInfo && $qKlsInfo->num_rows > 0) {
        $dKlsInfo = $qKlsInfo->fetch_assoc();
        $tk_kelas = $dKlsInfo['tingkat'];
    } else {
        $ada_kelas_dipilih = false;
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    .form-custom-height {
        height: 45px !important;
        border-radius: 50px !important;
        border: 1px solid #dee2e6 !important;
        font-size: 14px !important;
    }

    .select2-container--default .select2-selection--single {
        height: 45px !important;
        border-radius: 50px !important;
        border: 1px solid #dee2e6 !important;
        padding: 8px 15px !important;
        background-color: #fff !important;
        font-size: 14px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #6c757d !important;
        font-weight: 600 !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 43px !important;
        right: 10px !important;
    }

    .btn-cari {
        height: 45px !important;
        border-radius: 50px !important;
        padding: 0 24px !important;
        font-weight: 700 !important;
        font-size: 14px !important;
        white-space: nowrap !important;
        width: 100% !important;
    }

    .search-icon-wrapper {
        position: relative;
    }

    .search-icon-wrapper i {
        position: absolute;
        left: 18px;
        top: 15px;
        color: #adb5bd;
        font-size: 14px;
        z-index: 2;
    }

    .search-input-padding {
        padding-left: 43px !important;
    }

    .status-pending {
        background-color: #FFE3E8;
        color: #842029;
        font-weight: bold;
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-ready {
        background-color: #DDF5EA;
        color: #1f5f48;
        font-weight: bold;
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .mass-print-box {
        border-left: 5px solid #BFD3EA !important;
    }

    .filter-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .filter-card {
        background: #ffffff;
        border-radius: 20px;
    }

    .btn-mass-print {
        height: 38px !important;
        min-width: 130px !important;
        padding: 0 16px !important;
        font-size: 13px !important;
        border-radius: 50px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .btn-personal-print {
        height: 30px !important;
        min-width: 58px !important;
        padding: 0 12px !important;
        font-size: 12px !important;
        border-radius: 50px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1 !important;
    }

    @media (max-width: 992px) {
        .btn-cari {
            margin-top: 4px;
        }

        .mass-print-box {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 12px;
        }
    }
</style>

<div class="container-fluid p-4">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Cetak Rapor Siswa</h4>
        <small class="text-muted">Filter jurusan, tingkat, kelas, atau langsung ketik nama siswa/NIS.</small>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-4 filter-card">
        <form method="GET" action="">
            <input type="hidden" name="page" value="cetak_rapor">

            <div class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="filter-label">Tahun Ajaran / Semester</label>
                    <select name="id_tahun_ajaran" class="form-select form-custom-height fw-bold text-secondary" onchange="this.form.submit()">
                        <?php if($qDaftarTA && $qDaftarTA->num_rows > 0): $qDaftarTA->data_seek(0); ?>
                            <?php while($ta = $qDaftarTA->fetch_assoc()): ?>
                                <option value="<?= $ta['id'] ?>" <?= ($id_tahun_dipilih == $ta['id']) ? 'selected' : '' ?>>
                                    <?= $ta['tahun'] ?> - <?= $ta['semester'] ?><?= ($ta['status'] == 'Aktif') ? ' (Aktif)' : '' ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">Belum ada data tahun ajaran</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="filter-label">Jurusan</label>
                    <select name="jurusan" class="form-select form-custom-height fw-bold text-secondary" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php while($j = $qJurusan->fetch_assoc()): ?>
                            <option value="<?= $j['kode_jurusan'] ?>" <?= ($jurusan_pilih == $j['kode_jurusan']) ? 'selected' : '' ?>>
                                <?= $j['kode_jurusan'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="filter-label">Tingkat</label>
                    <select name="tingkat" class="form-select form-custom-height fw-bold text-secondary" onchange="this.form.submit()">
                        <option value="">Semua Tingkat</option>
                        <option value="X" <?= ($tingkat_pilih == 'X') ? 'selected' : '' ?>>Kelas X</option>
                        <option value="XI" <?= ($tingkat_pilih == 'XI') ? 'selected' : '' ?>>Kelas XI</option>
                        <option value="XII" <?= ($tingkat_pilih == 'XII') ? 'selected' : '' ?>>Kelas XII</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="filter-label">Kelas</label>
                    <select name="id_kelas" class="form-control select2-kelas" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas --</option>
                        <?php 
                        if($kelasQ && $kelasQ->num_rows > 0):
                            while($k = $kelasQ->fetch_assoc()): 
                        ?>
                            <option value="<?= $k['id'] ?>" <?= ($id_kelas == $k['id']) ? 'selected' : '' ?>>
                                <?= $k['nama_kelas'] ?>
                            </option>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <option value="">Kelas tidak ditemukan</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="filter-label">Nama / NIS</label>
                    <div class="search-icon-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control form-custom-height search-input-padding" 
                               placeholder="Ketik Nama atau NIS..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <div class="col-lg-2 col-md-12">
                    <label class="filter-label d-none d-lg-block">&nbsp;</label>
                    <button type="submit" class="btn btn-filter-data btn-cari">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded-4 border shadow-sm mass-print-box">
        <div>
            <?php if($ada_kelas_dipilih): ?>
                <h6 class="fw-bold mb-1 text-primary">
                    <i class="fas fa-layer-group me-2"></i>Aksi Cetak Massal (Kelas <?= $dKlsInfo['nama_kelas'] ?>)
                </h6>
                <small class="text-muted">Cetak rapor seluruh siswa di kelas ini sekaligus dalam 1 file PDF.</small>
            <?php elseif(!empty($jurusan_pilih) && !empty($tingkat_pilih)): ?>
                <h6 class="fw-bold mb-1 text-primary">
                    <i class="fas fa-layer-group me-2"></i>Aksi Cetak Massal (<?= $tingkat_pilih ?> <?= $jurusan_pilih ?>)
                </h6>
                <small class="text-muted">Cetak rapor semua kelas sesuai jurusan dan tingkat. Hasil ZIP akan berisi folder per kelas.</small>
            <?php else: ?>
                <h6 class="fw-bold mb-1 text-primary">
                    <i class="fas fa-layer-group me-2"></i>Aksi Cetak Massal
                </h6>
                <small class="text-muted">Pilih jurusan dan tingkat untuk cetak beberapa kelas, atau pilih satu kelas saja.</small>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <?php if($ada_kelas_dipilih): ?>
                <?php if($tk_kelas == 'XII'): ?>
                    <a href="cetak/cetak_rapor_pkl_kelas.php?id_kelas=<?= $id_kelas ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-add-data btn-mass-print">
                        <i class="fas fa-print"></i> Rapor PKL
                    </a>
                <?php else: ?>
                    <a href="cetak/rapor_uts_kelas.php?id_kelas=<?= $id_kelas ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-print-soft btn-mass-print">
                        <i class="fas fa-print"></i> Semua UTS
                    </a>

                    <a href="cetak/rapor_uas_kelas.php?id_kelas=<?= $id_kelas ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-soft-green btn-mass-print">
                        <i class="fas fa-print"></i> Semua UAS
                    </a>
                <?php endif; ?>

            <?php elseif(!empty($jurusan_pilih) && !empty($tingkat_pilih)): ?>
                <?php if($tingkat_pilih == 'XII'): ?>
                    <a href="cetak/cetak_rapor_pkl_multi_print.php?jurusan=<?= urlencode($jurusan_pilih) ?>&tingkat=<?= urlencode($tingkat_pilih) ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-add-data btn-mass-print">
                        <i class="fas fa-print"></i> Cetak Massal PKL
                    </a>

                <?php else: ?>
                    <a href="cetak/rapor_uts_multi_kelas.php?jurusan=<?= urlencode($jurusan_pilih) ?>&tingkat=<?= urlencode($tingkat_pilih) ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-print-soft btn-mass-print">
                        <i class="fas fa-folder"></i> ZIP UTS
                    </a>

                    <a href="cetak/rapor_uas_multi_kelas.php?jurusan=<?= urlencode($jurusan_pilih) ?>&tingkat=<?= urlencode($tingkat_pilih) ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-soft-green btn-mass-print">
                        <i class="fas fa-folder"></i> ZIP UAS
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <button type="button" class="btn btn-print-soft btn-mass-print disabled opacity-75">
                    <i class="fas fa-print"></i> Semua UTS
                </button>

                <button type="button" class="btn btn-soft-green btn-mass-print disabled opacity-75">
                    <i class="fas fa-print"></i> Semua UAS
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="text-muted small text-uppercase">
                    <th class="px-4 py-3" width="5%">No</th>
                    <th width="15%">NIS</th>
                    <th>Nama Lengkap</th>
                    <th class="text-center">Kelas</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi Cetak Personal</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $id_kelas_safe = '';
                $jurusan_safe  = '';
                $tingkat_safe  = '';
                $search_safe   = '';

                if($id_kelas != '')     $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);
                if($jurusan_pilih != '') $jurusan_safe = mysqli_real_escape_string($conn, $jurusan_pilih);
                if($tingkat_pilih != '') $tingkat_safe = mysqli_real_escape_string($conn, $tingkat_pilih);
                if($search != '')       $search_safe  = mysqli_real_escape_string($conn, $search);

                // FIX: siswa diambil berdasarkan KELAS HISTORIS (riwayat_kelas) sesuai tahun ajaran
                // yang lagi dipilih, bukan kelas siswa SEKARANG (s.id_kelas). Soalnya siswa naik
                // kelas tiap tahun ajaran/kenaikan kelas, jadi kalau admin lihat rapor tahun ajaran
                // lama, kelas & tingkatnya harus yang berlaku PAS tahun ajaran itu, bukan yang sekarang.
                // (pola sama persis kayak fix riwayat_kelas di input_absen.php)
                $where = "WHERE 1=1";
                if($id_kelas_safe != '')  $where .= " AND r.id_kelas = '$id_kelas_safe'";
                if($jurusan_safe != '')   $where .= " AND k.kode_jurusan = '$jurusan_safe'";
                if($tingkat_safe != '')   $where .= " AND k.tingkat = '$tingkat_safe'";
                if($search_safe != '')    $where .= " AND (s.nama_siswa LIKE '%$search_safe%' OR s.nis LIKE '%$search_safe%')";

                $siswaQ = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan
                                        FROM (
                                            SELECT id_siswa, MAX(id) AS max_id
                                            FROM riwayat_kelas
                                            WHERE id_tahun_ajaran = '$id_tahun_dipilih_safe' AND id_siswa != 0 AND id_kelas != 0
                                            GROUP BY id_siswa
                                        ) rk
                                        INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                                        INNER JOIN siswa s ON r.id_siswa = s.id
                                        JOIN kelas k ON r.id_kelas = k.id
                                        $where
                                        ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama_siswa ASC");

                // FALLBACK: kalau riwayat_kelas belum ada data utk tahun ajaran ini (misal Kenaikan
                // Kelas belum pernah diproses, atau baru pertama kali pakai sistem), tetap tampilkan
                // siswa berdasarkan penempatan kelas mereka saat ini supaya halaman tidak kosong.
                if(!$siswaQ || $siswaQ->num_rows == 0){
                    $where_fb = "WHERE 1=1";
                    if($id_kelas_safe != '') $where_fb .= " AND s.id_kelas = '$id_kelas_safe'";
                    if($jurusan_safe != '')  $where_fb .= " AND k.kode_jurusan = '$jurusan_safe'";
                    if($tingkat_safe != '')  $where_fb .= " AND k.tingkat = '$tingkat_safe'";
                    if($search_safe != '')   $where_fb .= " AND (s.nama_siswa LIKE '%$search_safe%' OR s.nis LIKE '%$search_safe%')";

                    $siswaQ = $conn->query("SELECT s.*, k.nama_kelas, k.tingkat, k.kode_jurusan
                                            FROM siswa s
                                            JOIN kelas k ON s.id_kelas = k.id
                                            $where_fb
                                            ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama_siswa ASC");
                }
                $no = 1;

                if($siswaQ && $siswaQ->num_rows > 0):
                    while($s = $siswaQ->fetch_assoc()):
                        $id_s = $s['id'];
                        $nama_kelas = $s['nama_kelas'];
                        
                        $tingkat_romawi = $s['tingkat'] ?? explode(' ', trim($nama_kelas))[0]; 
                        $jurusan_key = $s['kode_jurusan'] ?? '';

                        if(empty($jurusan_key)){
                            $jurusan_key = (strpos($nama_kelas, 'RPL') !== false) ? 'RPL' : ((strpos($nama_kelas, 'AKT') !== false) ? 'AKT' : '');
                        }

                        $jurusan_lawan = ($jurusan_key == 'RPL') ? 'AKT' : (($jurusan_key == 'AKT') ? 'RPL' : '');

                        if($tingkat_romawi == 'XII'){
                            // Kelas XII khusus PKL. Tidak cek UTS/UAS.
                            // Syarat ready: TP1-TP4 terisi dan status_validasi_pkl = 1.
                            $qPKLStatus = $conn->query("SELECT tp1_skor, tp2_skor, tp3_skor, tp4_skor, nilai_pkl, status_validasi_pkl
                                                        FROM data_pkl
                                                        WHERE id_siswa = '$id_s'
                                                        AND id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                                        LIMIT 1");
                            $dPKLStatus = ($qPKLStatus && $qPKLStatus->num_rows > 0) ? $qPKLStatus->fetch_assoc() : null;

                            $jml_wajib = 4;
                            $jml_ready = 0;

                            if($dPKLStatus){
                                foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_pkl){
                                    if($dPKLStatus[$field_pkl] !== null && $dPKLStatus[$field_pkl] !== ''){
                                        $jml_ready++;
                                    }
                                }
                            }

                            $isACC = ($dPKLStatus && $jml_ready == 4 && (int)$dPKLStatus['status_validasi_pkl'] == 1);
                        } else {
                            // Kelas X/XI dihitung berdasarkan mapel yang memang berlaku untuk kelas/jurusan itu.
                            // Ini mencegah X RPL 2 terbaca 13 mapel padahal mapelnya cuma 9.
                            $tingkat_safe_loop = mysqli_real_escape_string($conn, $tingkat_romawi);
                            $jurusan_safe_loop = mysqli_real_escape_string($conn, $jurusan_key);

                            if(!empty($mapel_jurusan_col)){
                                if($mapel_jurusan_col == 'id_jurusan'){
                                    $qIdJur = $conn->query("SELECT id FROM jurusan WHERE kode_jurusan='$jurusan_safe_loop' LIMIT 1");
                                    $id_jur_loop = ($qIdJur && $qIdJur->num_rows > 0) ? (int)$qIdJur->fetch_assoc()['id'] : 0;

                                    $kondisi_mapel = "m.tingkat = '$tingkat_safe_loop'
                                                      AND m.nama_mapel NOT LIKE '%PKL%'
                                                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                                                      AND (
                                                            m.kelompok = 'A'
                                                            OR m.id_jurusan = '$id_jur_loop'
                                                          )";

                                    $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_loop'
                                                            AND nama_mapel NOT LIKE '%PKL%'
                                                            AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                            AND nama_mapel NOT LIKE '%Prakerin%'
                                                            AND (
                                                                  kelompok = 'A'
                                                                  OR id_jurusan = '$id_jur_loop'
                                                                )";
                                } else {
                                    $kondisi_mapel = "m.tingkat = '$tingkat_safe_loop'
                                                      AND m.nama_mapel NOT LIKE '%PKL%'
                                                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                                                      AND (
                                                            m.kelompok = 'A'
                                                            OR m.$mapel_jurusan_col = '$jurusan_safe_loop'
                                                            OR m.$mapel_jurusan_col = ''
                                                            OR m.$mapel_jurusan_col IS NULL
                                                          )";

                                    $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_loop'
                                                            AND nama_mapel NOT LIKE '%PKL%'
                                                            AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                            AND nama_mapel NOT LIKE '%Prakerin%'
                                                            AND (
                                                                  kelompok = 'A'
                                                                  OR $mapel_jurusan_col = '$jurusan_safe_loop'
                                                                  OR $mapel_jurusan_col = ''
                                                                  OR $mapel_jurusan_col IS NULL
                                                                )";
                                }
                            } else {
                                // Fallback kalau tabel mapel belum punya kolom jurusan.
                                $kondisi_mapel = "m.tingkat = '$tingkat_safe_loop'
                                                  AND m.nama_mapel NOT LIKE '%PKL%'
                                                  AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                  AND m.nama_mapel NOT LIKE '%Prakerin%'
                                                  AND (
                                                        m.kelompok = 'A'
                                                        OR m.nama_mapel LIKE '%$jurusan_safe_loop%'
                                                        OR m.nama_mapel LIKE '%($jurusan_safe_loop)%'
                                                      )";

                                $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_loop'
                                                        AND nama_mapel NOT LIKE '%PKL%'
                                                        AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                        AND nama_mapel NOT LIKE '%Prakerin%'
                                                        AND (
                                                              kelompok = 'A'
                                                              OR nama_mapel LIKE '%$jurusan_safe_loop%'
                                                              OR nama_mapel LIKE '%($jurusan_safe_loop)%'
                                                            )";
                            }

                            $qWajib = $conn->query("SELECT COUNT(*) as tot FROM mapel WHERE $kondisi_mapel_wajib");

                            $qReady = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) as tot 
                                                    FROM nilai n 
                                                    JOIN mapel m ON n.id_mapel = m.id 
                                                    WHERE n.id_siswa = '$id_s' 
                                                    AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                                    AND n.status_validasi = 1
                                                    AND $kondisi_mapel");

                            // UTS wajib TP + STS. UAS wajib TP + STS + SAS.
                            $qReadyUTS = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) as tot
                                                       FROM nilai n
                                                       JOIN mapel m ON n.id_mapel = m.id
                                                       WHERE n.id_siswa = '$id_s'
                                                       AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                                       AND n.status_validasi = 1
                                                       AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                       AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
                                                       AND $kondisi_mapel");

                            $qReadyUAS = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) as tot
                                                       FROM nilai n
                                                       JOIN mapel m ON n.id_mapel = m.id
                                                       WHERE n.id_siswa = '$id_s'
                                                       AND n.id_tahun_ajaran = '$id_tahun_dipilih_safe'
                                                       AND n.status_validasi = 1
                                                       AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                       AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
                                                       AND n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0
                                                       AND $kondisi_mapel");

                            $jml_wajib = $qWajib ? ($qWajib->fetch_assoc()['tot'] ?? 0) : 0;
                            $jml_ready = $qReady ? ($qReady->fetch_assoc()['tot'] ?? 0) : 0;
                            $jml_ready_uts = $qReadyUTS ? ($qReadyUTS->fetch_assoc()['tot'] ?? 0) : 0;
                            $jml_ready_uas = $qReadyUAS ? ($qReadyUAS->fetch_assoc()['tot'] ?? 0) : 0;

                            $isReadyUTS = ($jml_ready_uts >= $jml_wajib && $jml_wajib > 0);
                            $isReadyUAS = ($jml_ready_uas >= $jml_wajib && $jml_wajib > 0);
                            $isACC = $isReadyUTS;
                        }
                ?>
                <tr>
                    <td class="px-4"><?= $no++ ?></td>
                    <td class="text-secondary"><?= $s['nis'] ?></td>
                    <td class="fw-bold"><?= $s['nama_siswa'] ?></td>

                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><?= $s['nama_kelas'] ?></span>
                    </td>

                    <td class="text-center">
                        <?php if($isACC): ?>
                            <span class="status-ready">
                                <i class="fas fa-check-circle me-1"></i>
                                <?= ($tingkat_romawi == 'XII') ? 'Ready PKL' : 'Ready' ?>
                            </span>
                        <?php else: ?>
                            <span class="status-pending">
                                <i class="fas fa-history me-1"></i>
                                <?= ($tingkat_romawi == 'XII') ? 'Pending PKL' : 'Pending' ?>
                                (<?= $jml_ready ?>/<?= $jml_wajib ?>)
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
                        <div class="d-flex gap-2 justify-content-center">
                            <?php if($tingkat_romawi == 'XII'): ?>
                                <a href="cetak/cetak_rapor_pkl.php?id=<?= $id_s ?>&id_siswa=<?= $id_s ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-sm btn-add-data btn-personal-print <?= !$isACC ? 'disabled opacity-50' : '' ?>" title="Cetak Rapor PKL">
                                    PKL
                                </a>
                            <?php else: ?>
                                <a href="cetak/rapor_uts.php?id=<?= $id_s ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-sm btn-print-soft btn-personal-print <?= !$isReadyUTS ? 'disabled opacity-50' : '' ?>" title="UTS bisa dicetak kalau TP dan STS sudah lengkap/divalidasi">
                                    UTS
                                </a>

                                <a href="cetak/rapor_uas.php?id=<?= $id_s ?>&id_tahun_ajaran=<?= $id_tahun_dipilih ?>" target="_blank" class="btn btn-sm btn-soft-green btn-personal-print <?= !$isReadyUAS ? 'disabled opacity-50' : '' ?>" title="UAS bisa dicetak kalau TP, STS, dan SAS sudah lengkap/divalidasi">
                                    UAS
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted fw-bold">Siswa tidak ditemukan.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() { 
    $('.select2-kelas').select2({ 
        width: '100%' 
    });

    $('.select2-kelas').on('select2:select', function () {
        $(this).closest('form').submit();
    });
});
</script>