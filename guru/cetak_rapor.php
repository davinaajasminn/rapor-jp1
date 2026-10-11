<?php
$nip_guru_login = $_SESSION['guru']['nip']; 
$nama_guru_login = $_SESSION['guru']['nama'];

// --- 0. AMBIL ID GURU DARI DATABASE (SINKRON FK) ---
$qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip_guru_login' LIMIT 1");
$id_guru_login = ($qGuru && $qGuru->num_rows > 0) ? $qGuru->fetch_assoc()['id'] : 0;

// --- 1. CARI KELAS PERWALIAN GURU INI LEWAT riwayat_wali_kelas + TAHUN AJARAN AKTIF ---
$qTahunAktifCetak = $conn->query("SELECT id, tahun FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahunAktifCetak = ($qTahunAktifCetak && $qTahunAktifCetak->num_rows > 0) ? $qTahunAktifCetak->fetch_assoc() : null;
$tahun_aktif_cetak = $dTahunAktifCetak['tahun'] ?? '';
$id_tahun_aktif_cetak = $dTahunAktifCetak['id'] ?? 0;
$tahun_aktif_cetak_esc = mysqli_real_escape_string($conn, $tahun_aktif_cetak);
$id_tahun_aktif_cetak_safe = mysqli_real_escape_string($conn, $id_tahun_aktif_cetak);

$qWali = $conn->query("SELECT k.id, k.nama_kelas, k.tingkat, k.kode_jurusan 
                       FROM kelas k
                       JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id
                       WHERE rwk.id_guru = '$id_guru_login'
                       AND rwk.tahun = '$tahun_aktif_cetak_esc'");
$kelas_wali = [];
if($qWali) {
    while($kw = $qWali->fetch_assoc()){
        $kelas_wali[] = $kw;
    }
}

if(count($kelas_wali) == 0){
    echo "<div class='alert alert-danger mt-4 text-center fw-bold'>Akses ditolak. Lo bukan wali kelas, Dav!</div>";
    exit;
}

$id_kelas_pilihan = $_GET['id_kelas'] ?? $kelas_wali[0]['id'];
$search = $_GET['search'] ?? '';

// --- Kolom validasi khusus PKL ---
$cekKolomValidPKL = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'status_validasi_pkl'");
if($cekKolomValidPKL && $cekKolomValidPKL->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD status_validasi_pkl TINYINT(1) NOT NULL DEFAULT 0");
}


// --- 2. AMBIL NAMA KELAS UNTUK HITUNG MAPEL WAJIB ---
$nama_kelas_wali = '';
$tingkat_romawi = '';
$jurusan_key = '';

foreach($kelas_wali as $kw) {
    if($kw['id'] == $id_kelas_pilihan) {
        $nama_kelas_wali = $kw['nama_kelas'];
        $tingkat_romawi = $kw['tingkat'] ?? '';
        $jurusan_key = $kw['kode_jurusan'] ?? '';
    }
}

if(empty($tingkat_romawi)){
    $tingkat_romawi = explode(' ', trim($nama_kelas_wali))[0];
}

if(empty($jurusan_key)){
    if(stripos($nama_kelas_wali, 'RPL') !== false) $jurusan_key = 'RPL';
    elseif(stripos($nama_kelas_wali, 'AKL') !== false) $jurusan_key = 'AKL';
    elseif(stripos($nama_kelas_wali, 'AKT') !== false) $jurusan_key = 'AKT';
    elseif(stripos($nama_kelas_wali, 'BR') !== false) $jurusan_key = 'BR';
    elseif(stripos($nama_kelas_wali, 'MP') !== false) $jurusan_key = 'MP';
}

$tingkat_safe_global = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe_global = mysqli_real_escape_string($conn, $jurusan_key);

// Deteksi kolom jurusan pada tabel mapel, disamakan dengan admin/cetak_rapor.php.
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

if($tingkat_romawi == 'XII'){
    // Kelas XII memakai rapor PKL. Tidak memakai status UTS/UAS.
    $jml_mapel_wajib = 4;
    $kondisi_mapel_valid = '';
} else {
    if(!empty($mapel_jurusan_col)){
        if($mapel_jurusan_col == 'id_jurusan'){
            $qIdJur = $conn->query("SELECT id FROM jurusan WHERE kode_jurusan='$jurusan_safe_global' LIMIT 1");
            $id_jur_loop = ($qIdJur && $qIdJur->num_rows > 0) ? (int)$qIdJur->fetch_assoc()['id'] : 0;

            $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe_global'
                                      AND m.nama_mapel NOT LIKE '%PKL%'
                                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                                      AND (
                                            m.kelompok = 'A'
                                            OR m.id_jurusan = '$id_jur_loop'
                                          )";

            $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_global'
                                      AND nama_mapel NOT LIKE '%PKL%'
                                      AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                      AND nama_mapel NOT LIKE '%Prakerin%'
                                      AND (
                                            kelompok = 'A'
                                            OR id_jurusan = '$id_jur_loop'
                                          )";
        } else {
            // PENTING:
            // Ini sengaja disamakan dengan admin:
            // mapel umum ikut jika kelompok A / kode_jurusan kosong,
            // mapel kejuruan ikut jika kode_jurusan sesuai jurusan kelas.
            $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe_global'
                                      AND m.nama_mapel NOT LIKE '%PKL%'
                                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                      AND m.nama_mapel NOT LIKE '%Prakerin%'
                                      AND (
                                            m.kelompok = 'A'
                                            OR m.$mapel_jurusan_col = '$jurusan_safe_global'
                                            OR m.$mapel_jurusan_col = ''
                                            OR m.$mapel_jurusan_col IS NULL
                                          )";

            $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_global'
                                      AND nama_mapel NOT LIKE '%PKL%'
                                      AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                      AND nama_mapel NOT LIKE '%Prakerin%'
                                      AND (
                                            kelompok = 'A'
                                            OR $mapel_jurusan_col = '$jurusan_safe_global'
                                            OR $mapel_jurusan_col = ''
                                            OR $mapel_jurusan_col IS NULL
                                          )";
        }
    } else {
        $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe_global'
                                  AND m.nama_mapel NOT LIKE '%PKL%'
                                  AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                  AND m.nama_mapel NOT LIKE '%Prakerin%'
                                  AND (
                                        m.kelompok = 'A'
                                        OR m.nama_mapel LIKE '%$jurusan_safe_global%'
                                        OR m.nama_mapel LIKE '%($jurusan_safe_global)%'
                                      )";

        $kondisi_mapel_wajib = "tingkat = '$tingkat_safe_global'
                                  AND nama_mapel NOT LIKE '%PKL%'
                                  AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                  AND nama_mapel NOT LIKE '%Prakerin%'
                                  AND (
                                        kelompok = 'A'
                                        OR nama_mapel LIKE '%$jurusan_safe_global%'
                                        OR nama_mapel LIKE '%($jurusan_safe_global)%'
                                      )";
    }

    $qJmlMapel = $conn->query("SELECT COUNT(*) as total FROM mapel WHERE $kondisi_mapel_wajib");
    $jml_mapel_wajib = $qJmlMapel ? ($qJmlMapel->fetch_assoc()['total'] ?? 0) : 0;
}
?>

<style>
    .cetak-header-card {
        background: #ffffff !important;
        border-radius: 18px !important;
        border-left: 5px solid #6FA7DF !important;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08) !important;
    }

    .cetak-header-card h5 {
        color: #0d385e !important;
        font-size: 22px !important;
    }

    .cetak-header-card p {
        color: #6c757d !important;
        font-size: 14px !important;
    }

    .kelas-badge-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        padding: 8px 16px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
    }

    .form-custom-height { 
        height: 46px !important; 
        border-radius: 50px !important; 
        border: 1px solid #dbe5ef !important; 
        padding-left: 45px !important;
        color: #0d385e !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
    }

    .form-select-soft {
        height: 46px !important;
        border-radius: 50px !important;
        border: 1px solid #dbe5ef !important;
        color: #0d385e !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        padding-left: 18px !important;
        box-shadow: none !important;
    }

    .form-select-soft option {
        font-weight: 400 !important;
        font-size: 14px !important;
    }

    .search-icon-wrapper { 
        position: relative; 
    }

    .search-icon-wrapper i { 
        position: absolute; 
        left: 20px; 
        top: 15px; 
        color: #9aabbc; 
        z-index: 2;
    }

    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-blue:hover,
    .btn-soft-blue:focus,
    .btn-soft-blue:active {
        background: #5E97D2 !important;
        color: #ffffff !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-cream {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #E8D5AB !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(214, 189, 145, 0.20) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-cream:hover,
    .btn-soft-cream:focus,
    .btn-soft-cream:active {
        background: #EED9AF !important;
        color: #76542D !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-green {
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

    .btn-soft-green:hover,
    .btn-soft-green:focus,
    .btn-soft-green:active {
        background: #A7D1BD !important;
        color: #244F42 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-cari-soft {
        height: 46px !important;
        border-radius: 50px !important;
        font-size: 14px !important;
    }

    .btn-cetak-personal {
        min-height: 32px !important;
        min-width: 65px !important;
        padding: 0 15px !important;
        font-size: 12px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
    }

    .status-pending-soft {
        background: #FFE3E8 !important;
        color: #842029 !important;
        border: 1px solid #F2A6B3 !important;
        border-radius: 50px !important;
        padding: 7px 15px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }

    .status-ready-soft {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
        border-radius: 50px !important;
        padding: 7px 15px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
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

<div class="card border-0 p-4 mb-4 cetak-header-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="fw-bold mb-1">
                <i class="fas fa-print me-2 text-primary"></i>Cetak Rapor Siswa
            </h5>
            <p class="mb-0">Halaman khusus Wali Kelas untuk mencetak rapor akademik dan PKL siswa.</p>
        </div>

        <div>
            <span class="kelas-badge-soft">
                <i class="fas fa-chalkboard-teacher me-1"></i> Kelas <?= $nama_kelas_wali ?>
            </span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
    <form method="GET" action="">
        <input type="hidden" name="page" value="cetak_rapor">

        <div class="row g-2">
            <div class="col-md-4">
                <select name="id_kelas" class="form-select form-select-soft" onchange="this.form.submit()">
                    <?php foreach($kelas_wali as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= ($id_kelas_pilihan == $k['id']) ? 'selected' : '' ?>>
                            <?= $k['nama_kelas'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <div class="search-icon-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="form-control form-custom-height" placeholder="Cari Nama atau NIS Siswa..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-soft-blue btn-cari-soft w-100 fw-bold shadow-sm">
                    <i class="fas fa-search me-1"></i> CARI
                </button>
            </div>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-users me-2"></i>Daftar Siswa Kelas <?= $nama_kelas_wali ?>
        </h6>
        <div class="d-flex gap-2">
            <?php if($tingkat_romawi == 'XII'): ?>
                <a href="../admin/cetak/cetak_rapor_pkl_kelas.php?id_kelas=<?= $id_kelas_pilihan ?>" target="_blank" class="btn btn-soft-blue fw-bold shadow-sm" style="font-size: 13px; padding: 8px 18px;">
                    <i class="fas fa-print me-1"></i> Cetak Massal PKL
                </a>
            <?php else: ?>
                <a href="../admin/cetak/rapor_uts_kelas.php?id_kelas=<?= $id_kelas_pilihan ?>" target="_blank" class="btn btn-soft-cream fw-bold shadow-sm" style="font-size: 13px; padding: 8px 18px;">
                    <i class="fas fa-print me-1"></i> Cetak Massal UTS
                </a>
                <a href="../admin/cetak/rapor_uas_kelas.php?id_kelas=<?= $id_kelas_pilihan ?>" target="_blank" class="btn btn-soft-green fw-bold shadow-sm" style="font-size: 13px; padding: 8px 18px;">
                    <i class="fas fa-print me-1"></i> Cetak Massal UAS
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="text-center">
                    <th class="py-3" width="50">NO</th>
                    <th class="text-start">NAMA SISWA</th>
                    <th>NISN</th>
                    <th>STATUS VALIDASI</th>
                    <th width="200">AKSI CETAK</th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $no = 1;
                // 🩹 FIX: kolom master siswa.id_kelas kadang gak sinkron (misal abis di-edit
                // sambil gak sengaja kepencet "Alumni"). Makanya di sini kita juga cek
                // riwayat_kelas tahun aktif sebagai cadangan, supaya siswa yang sebenarnya
                // masih aktif di kelas ini (menurut riwayat_kelas) tetap ketemu.
                $where = "WHERE (id_kelas = '$id_kelas_pilihan' 
                           OR id IN (
                               SELECT rk.id_siswa FROM riwayat_kelas rk 
                               WHERE rk.id_kelas = '$id_kelas_pilihan' 
                               AND rk.id_tahun_ajaran = '$id_tahun_aktif_cetak_safe'
                           ))";
                if(!empty($search)) $where .= " AND (nama_siswa LIKE '%$search%' OR nis LIKE '%$search%')";
                
                $qSiswa = $conn->query("SELECT * FROM siswa $where ORDER BY nama_siswa ASC");
                
                if($qSiswa && $qSiswa->num_rows > 0):
                    while($s = $qSiswa->fetch_assoc()): 
                        $id_s = $s['id'];
                        
                        if($tingkat_romawi == 'XII'){
                            // Status cetak kelas XII khusus PKL:
                            // tidak cek UTS/UAS, tapi cek data_pkl, 4 skor TP, dan status_validasi_pkl.
                            $qPKLStatus = $conn->query("SELECT 
                                                            tp1_skor, tp2_skor, tp3_skor, tp4_skor,
                                                            nilai_pkl, status_validasi_pkl
                                                        FROM data_pkl
                                                        WHERE id_siswa = '$id_s'
                                                        LIMIT 1");
                            $dPKLStatus = ($qPKLStatus && $qPKLStatus->num_rows > 0) ? $qPKLStatus->fetch_assoc() : null;

                            $pkl_tp_terisi = 0;
                            if($dPKLStatus){
                                foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_pkl){
                                    if($dPKLStatus[$field_pkl] !== null && $dPKLStatus[$field_pkl] !== ''){
                                        $pkl_tp_terisi++;
                                    }
                                }
                            }

                            $jml_valid_siswa = $pkl_tp_terisi;
                            $jml_mapel_wajib = 4;
                            $is_complete = ($dPKLStatus && $pkl_tp_terisi == 4 && (int)$dPKLStatus['status_validasi_pkl'] == 1);
                        } else {
                            // Status cetak kelas X/XI pakai kondisi mapel yang sama dengan admin.
                            // Jadi jumlah mapel guru/wali tidak beda dari admin.
                            $qValid = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) as jml_valid FROM nilai n 
                                                    JOIN mapel m ON n.id_mapel = m.id
                                                    WHERE n.id_siswa = '$id_s' 
                                                    AND n.status_validasi = 1
                                                    AND $kondisi_mapel_valid");
                            $jml_valid_siswa = $qValid ? ($qValid->fetch_assoc()['jml_valid'] ?? 0) : 0;

                            $is_complete = ($jml_valid_siswa >= $jml_mapel_wajib && $jml_mapel_wajib > 0);
                        }
                ?>
                <tr class="text-center">
                    <td class="text-muted"><?= $no++ ?></td>

                    <td class="text-start fw-bold">
                        <?= ucwords(strtolower($s['nama_siswa'])) ?>
                    </td>

                    <td><?= $s['nisn'] ?></td>

                    <td>
                        <?php if(!$is_complete): ?>
                            <span class="status-pending-soft">
                                <i class="fas fa-clock"></i>
                                <?= ($tingkat_romawi == 'XII') ? 'Pending PKL' : 'Pending' ?>
                                (<?= $jml_valid_siswa ?>/<?= $jml_mapel_wajib ?>)
                            </span>
                        <?php else: ?>
                            <span class="status-ready-soft">
                                <i class="fas fa-check-circle"></i>
                                <?= ($tingkat_romawi == 'XII') ? 'Ready PKL' : 'Ready' ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            
                            <?php if($tingkat_romawi == 'XII'): ?>
                                <a href="../admin/cetak/cetak_rapor_pkl.php?id=<?= $s['id'] ?>&id_siswa=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-soft-blue btn-cetak-personal fw-bold rounded-pill shadow-sm <?= !$is_complete ? 'disabled opacity-50' : '' ?>" title="Cetak Rapor Industri">
                                    <i class="fas fa-print"></i> PKL
                                </a>
                            <?php else: ?>
                                <a href="../admin/cetak/rapor_uts.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-soft-cream btn-cetak-personal fw-bold rounded-pill shadow-sm <?= !$is_complete ? 'disabled opacity-50' : '' ?>" title="Cetak Tengah Semester">
                                    UTS
                                </a>

                                <a href="../admin/cetak/rapor_uas.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-soft-green btn-cetak-personal fw-bold rounded-pill shadow-sm <?= !$is_complete ? 'disabled opacity-50' : '' ?>" title="Cetak Akhir Semester">
                                    UAS
                                </a>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <h6 class="text-muted fw-bold">Data siswa tidak ditemukan.</h6>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>