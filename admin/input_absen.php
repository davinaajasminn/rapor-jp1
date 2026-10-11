<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';
$db = new Database(); $conn = $db->getConnection();

// --- 0. AMBIL TAHUN AJARAN AKTIF (DEFAULT) ---
$qTaAktif = $conn->query("SELECT id, tahun, semester, hari_efektif FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
$ta_aktif = $qTaAktif ? $qTaAktif->fetch_assoc() : null;

// --- 1. FILTER TAHUN AJARAN (dropdown per-record, gaya sama dengan Data Siswa/Data Kelas) ---
$filter_tahun = $_GET['filter_tahun'] ?? ($ta_aktif['id'] ?? '');
$id_ta_pilih  = $filter_tahun;
$id_ta_safe   = mysqli_real_escape_string($conn, $id_ta_pilih);

// Ambil info tahun ajaran yang sedang dipilih (fallback ke aktif kalau tidak ketemu)
$qInfoTA = $conn->query("SELECT * FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
$infoTA  = ($qInfoTA && $qInfoTA->num_rows > 0) ? $qInfoTA->fetch_assoc() : ($ta_aktif ?? ['id' => '', 'tahun' => '-', 'semester' => 'Ganjil', 'hari_efektif' => 100]);
$sem_aktif  = $infoTA['semester'];
$total_hari = !empty($infoTA['hari_efektif']) ? (int)$infoTA['hari_efektif'] : 100;
$sem_aktif_safe = mysqli_real_escape_string($conn, $sem_aktif);

// List seluruh tahun ajaran untuk dropdown (urut terbaru dulu)
$list_tahun_ajaran = [];
$qListTA = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");
while($row_ta = $qListTA->fetch_assoc()){ $list_tahun_ajaran[] = $row_ta; }

// --- 2. FILTER JURUSAN, TINGKAT, KELAS (cascading) + SEARCH NAMA/NISN ---
$jurusan_pilih  = $_GET['jurusan'] ?? '';
$tingkat_pilih  = $_GET['tingkat'] ?? '';
$id_k_pilih     = $_GET['id_kelas'] ?? '';
$search_siswa   = $_GET['search'] ?? '';

$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_pilih);
$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_pilih);
$id_k_safe    = mysqli_real_escape_string($conn, $id_k_pilih);

$qJurusan = $conn->query("SELECT kode_jurusan, nama_jurusan FROM jurusan ORDER BY kode_jurusan ASC");

// Dropdown Kelas mengikuti Jurusan & Tingkat yang dipilih
$where_k = "WHERE 1=1";
if(!empty($jurusan_safe)) $where_k .= " AND kode_jurusan = '$jurusan_safe'";
if(!empty($tingkat_safe)) $where_k .= " AND tingkat = '$tingkat_safe'";
$qK = $conn->query("SELECT id, nama_kelas FROM kelas $where_k ORDER BY nama_kelas ASC");

// --- 3. PROSES SIMPAN ---
if(isset($_POST['simpan_absen'])){
    foreach($_POST['absen'] as $id_siswa => $val){
        $id_siswa_safe = mysqli_real_escape_string($conn, $id_siswa);
        $sakit = (int)($val['sakit'] ?? 0);
        $izin  = (int)($val['izin'] ?? 0);
        $alpa  = (int)($val['alpa'] ?? 0);
        $cek = $conn->query("SELECT id_absen FROM absensi WHERE id_siswa = '$id_siswa_safe' AND semester = '$sem_aktif_safe' AND id_tahun_ajaran = '$id_ta_safe'");
        if($cek->num_rows > 0){
            $conn->query("UPDATE absensi SET sakit='$sakit', izin='$izin', alpha='$alpa' WHERE id_siswa = '$id_siswa_safe' AND semester = '$sem_aktif_safe' AND id_tahun_ajaran = '$id_ta_safe'");
        } else {
            $conn->query("INSERT INTO absensi (id_siswa, sakit, izin, alpha, semester, id_tahun_ajaran, status_validasi) VALUES ('$id_siswa_safe', '$sakit', '$izin', '$alpa', '$sem_aktif_safe', '$id_ta_safe', 1)");
        }
    }
    echo "<script>alert('Berhasil!'); window.location='?page=input_absen&filter_tahun=".urlencode($id_ta_pilih)."&jurusan=".urlencode($jurusan_pilih)."&tingkat=".urlencode($tingkat_pilih)."&id_kelas=".urlencode($id_k_pilih)."&search=".urlencode($search_siswa)."';</script>";
    exit;
}
?>

<!-- UI ESTETIK (Banner Biru + Tombol Biru Pastel) -->
<style>
    .banner-absen { background-color: #B0C4DE; padding: 30px; border-radius: 20px; margin-bottom: 25px; }
    .banner-absen h4 { font-weight: 700; font-size: 1.65rem; letter-spacing: 0.2px; margin-bottom: 6px; }
    .btn-biru-pastel { background-color: #A3C1DA !important; color: #14304D !important; border-radius: 50px; font-weight: 700; padding: 10px 25px; border: none; display: inline-flex; align-items: center; }
    .btn-hijau-pastel { background-color: #7FBF8E !important; color: #0E4D2B !important; border-radius: 50px; font-weight: 700; padding: 10px 25px; border: none; display: inline-flex; align-items: center; }
    .filter-card { background: #fff; border-radius: 20px; padding: 22px 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .filter-card label { color: #6c757d; margin-bottom: 6px; display: block; }
    .search-group .btn { background-color: #16213e; color: #fff; border: none; }
    .tbl-absen th, .tbl-absen td { vertical-align: middle; }
    .tbl-absen th.col-no, .tbl-absen td.col-no { width: 50px; text-align: center; }
    .tbl-absen th.col-nama, .tbl-absen td.col-nama { width: auto; }
    .tbl-absen th.col-sempit, .tbl-absen td.col-sempit { width: 80px; }
    .tbl-absen th.col-hadir, .tbl-absen td.col-hadir { width: 100px; }
    .tbl-absen input[type="number"] { width: 100%; max-width: 65px; margin: 0 auto; text-align: center; }
    .badge-nama-kembar { display: inline-block; background-color: #FFF3CD; color: #7A5C00; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; margin-left: 6px; vertical-align: middle; white-space: nowrap; }
    .info-kelas-dup { display: inline-block; background-color: #E8F0FE; color: #14304D; font-weight: 700; font-size: 13px; padding: 2px 10px; border-radius: 8px; margin-top: 3px; }
</style>

<div class="container-fluid p-4">
    <div class="banner-absen">
        <div>
            <h4>Input Absensi Siswa</h4>
            <p class="mb-0">Kelola rekapitulasi kehadiran siswa semester <?= htmlspecialchars(strtoupper($sem_aktif)) ?> &mdash; Tahun Ajaran <?= htmlspecialchars($infoTA['tahun'] ?? '-') ?></p>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET">
        <input type="hidden" name="page" value="input_absen">

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="small fw-bold">Tahun Ajaran</label>
                <select name="filter_tahun" class="form-select" onchange="this.form.submit()">
                    <?php foreach($list_tahun_ajaran as $t):
                        $label_aktif = (strtolower($t['status']) == 'aktif') ? ' - [AKTIF]' : '';
                    ?>
                        <option value="<?= $t['id'] ?>" <?= ($id_ta_pilih == $t['id']) ? 'selected' : '' ?>>
                            TP <?= htmlspecialchars($t['tahun']) ?> (<?= htmlspecialchars($t['semester']) ?>)<?= $label_aktif ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="filter-card">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold">Filter Jurusan</label>
                    <select name="jurusan" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php while($j = $qJurusan->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($j['kode_jurusan']) ?>" <?= ($jurusan_pilih == $j['kode_jurusan']) ? 'selected' : '' ?>><?= htmlspecialchars($j['kode_jurusan']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="small fw-bold">Filter Tingkat</label>
                    <select name="tingkat" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Tingkat</option>
                        <option value="X" <?= ($tingkat_pilih == 'X') ? 'selected' : '' ?>>Kelas X</option>
                        <option value="XI" <?= ($tingkat_pilih == 'XI') ? 'selected' : '' ?>>Kelas XI</option>
                        <option value="XII" <?= ($tingkat_pilih == 'XII') ? 'selected' : '' ?>>Kelas XII</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="small fw-bold">Pilih Kelas</label>
                    <select name="id_kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php while($k = $qK->fetch_assoc()): ?>
                            <option value="<?= $k['id'] ?>" <?= ($id_k_pilih == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="small fw-bold">Cari Siswa</label>
                    <div class="input-group search-group">
                        <input type="text" name="search" class="form-control" placeholder="Nama/NISN" value="<?= htmlspecialchars($search_siswa) ?>">
                        <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php
        $qs_ta      = urlencode($id_ta_pilih);
        $qs_jurusan = urlencode($jurusan_pilih);
        $qs_tingkat = urlencode($tingkat_pilih);
        $qs_kelas   = urlencode($id_k_pilih);
    ?>

    <div class="mb-4 d-flex flex-wrap gap-2">
        <?php if(!empty($jurusan_pilih) && !empty($tingkat_pilih)): ?>
            <!-- Muncul begitu Jurusan & Tingkat dipilih: cetak ZIP semua kelas dalam jurusan+tingkat itu -->
            <a href="cetak/cetak_absen_massal.php?jurusan=<?= $qs_jurusan ?>&tingkat=<?= $qs_tingkat ?>&id_ta=<?= $qs_ta ?>" target="_blank" class="btn btn-biru-pastel">
                <i class="fas fa-file-archive me-2"></i> Download ZIP Absensi (<?= htmlspecialchars($tingkat_pilih) ?> <?= htmlspecialchars($jurusan_pilih) ?>)
            </a>
        <?php endif; ?>

        <?php if(!empty($jurusan_pilih) && !empty($tingkat_pilih) && !empty($id_k_pilih)): ?>
            <!-- Muncul begitu Jurusan, Tingkat, DAN Kelas dipilih: cetak 1 PDF untuk kelas itu saja -->
            <a href="cetak/cetak_absen.php?id_kelas=<?= $qs_kelas ?>&id_ta=<?= $qs_ta ?>" target="_blank" class="btn btn-hijau-pastel">
                <i class="fas fa-file-pdf me-2"></i> Cetak PDF Kelas Ini
            </a>
        <?php endif; ?>
    </div>

    <!-- Tabel Data -->
    <form method="POST">
        <input type="hidden" name="id_kelas_post" value="<?= htmlspecialchars($id_k_pilih) ?>">
        <input type="hidden" name="semester_post" value="<?= htmlspecialchars($sem_aktif) ?>">
        <input type="hidden" name="tahun_post" value="<?= htmlspecialchars($id_ta_pilih) ?>">
        <table class="table table-hover bg-white rounded-4 shadow-sm tbl-absen">
            <thead>
                <tr>
                    <th class="col-no">NO</th>
                    <th class="col-nama">NAMA SISWA</th>
                    <th class="col-sempit">SAKIT</th>
                    <th class="col-sempit">IZIN</th>
                    <th class="col-sempit">ALPA</th>
                    <th class="col-hadir">KEHADIRAN</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $where = "WHERE 1=1";
                if(!empty($id_k_safe)) $where .= " AND r.id_kelas = '$id_k_safe'";
                if(!empty($jurusan_safe)) $where .= " AND k.kode_jurusan = '$jurusan_safe'";
                if(!empty($tingkat_safe)) $where .= " AND k.tingkat = '$tingkat_safe'";
                $search_safe = '';
                if(!empty($search_siswa)){
                    $search_safe = mysqli_real_escape_string($conn, $search_siswa);
                    $where .= " AND (s.nama_siswa LIKE '%$search_safe%' OR s.nisn LIKE '%$search_safe%')";
                }
                // Subquery rk: ambil HANYA baris paling baru (MAX id) per siswa untuk tahun ajaran ini.
                // Ini mencegah siswa tampil dobel/triple kalau data riwayat_kelas di database
                // sempat ke-input ganda (misal proses Kenaikan Kelas ke-run lebih dari sekali).
                $sql = "SELECT s.id, s.nama_siswa, s.nis, s.nisn, k.nama_kelas, a.sakit, a.izin, a.alpha
                        FROM (
                            SELECT id_siswa, MAX(id) AS max_id
                            FROM riwayat_kelas
                            WHERE id_tahun_ajaran = '$id_ta_safe' AND id_siswa != 0 AND id_kelas != 0
                            GROUP BY id_siswa
                        ) rk
                        INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                        INNER JOIN siswa s ON r.id_siswa = s.id
                        LEFT JOIN kelas k ON r.id_kelas = k.id
                        LEFT JOIN absensi a ON s.id = a.id_siswa AND a.semester = '$sem_aktif_safe' AND a.id_tahun_ajaran = '$id_ta_safe'
                        $where
                        ORDER BY s.nama_siswa ASC";
                $qS = $conn->query($sql);

                // FALLBACK: kalau riwayat_kelas belum ada data untuk tahun ajaran ini
                // (misal Kenaikan Kelas belum diproses untuk kelas/jurusan tsb),

                // tampilkan siswa berdasarkan penempatan kelas mereka saat ini (siswa.id_kelas)
                $pakai_fallback = (!$qS || $qS->num_rows == 0);
                if($pakai_fallback){
                    $where_fb = "WHERE 1=1";
                    if(!empty($id_k_safe)) $where_fb .= " AND s.id_kelas = '$id_k_safe'";
                    if(!empty($jurusan_safe)) $where_fb .= " AND k.kode_jurusan = '$jurusan_safe'";
                    if(!empty($tingkat_safe)) $where_fb .= " AND k.tingkat = '$tingkat_safe'";
                    if(!empty($search_safe)) $where_fb .= " AND (s.nama_siswa LIKE '%$search_safe%' OR s.nisn LIKE '%$search_safe%')";

                    $sql_fb = "SELECT s.id, s.nama_siswa, s.nis, s.nisn, k.nama_kelas, a.sakit, a.izin, a.alpha
                               FROM siswa s
                               LEFT JOIN kelas k ON s.id_kelas = k.id
                               LEFT JOIN absensi a ON s.id = a.id_siswa AND a.semester = '$sem_aktif_safe' AND a.id_tahun_ajaran = '$id_ta_safe'
                               $where_fb
                               ORDER BY s.nama_siswa ASC";
                    $qS = $conn->query($sql_fb);
                }

                if($pakai_fallback && $qS && $qS->num_rows > 0):
                ?>
                <tr>
                    <td colspan="6" class="small text-muted fst-italic bg-light">
                        <i class="fas fa-info-circle me-1"></i> Riwayat kelas untuk tahun ajaran ini belum diproses (Kenaikan Kelas), menampilkan siswa berdasarkan kelas saat ini.
                    </td>
                </tr>
                <?php endif; ?>
                <?php
                // Kumpulkan dulu semua baris ke array supaya bisa dihitung nama yang kembar
                $siswa_list = [];
                if($qS && $qS->num_rows > 0){
                    while($row = $qS->fetch_assoc()){ $siswa_list[] = $row; }
                }
                $nama_count = [];
                foreach($siswa_list as $row){
                    $key = strtoupper(trim($row['nama_siswa']));
                    $nama_count[$key] = ($nama_count[$key] ?? 0) + 1;
                }

                if(count($siswa_list) > 0):
                    $no = 1;
                    foreach($siswa_list as $s):
                        $terpakai = ($s['sakit'] ?? 0) + ($s['izin'] ?? 0) + ($s['alpha'] ?? 0);
                        $p = $total_hari > 0 ? round((($total_hari - $terpakai) / $total_hari) * 100, 1) : 0;
                        $nama_key = strtoupper(trim($s['nama_siswa']));
                        $is_dup_nama = $nama_count[$nama_key] > 1;
                ?>
                <tr>
                    <td class="col-no"><?= $no++ ?></td>
                    <td class="col-nama fw-bold">
                        <?= htmlspecialchars(strtoupper($s['nama_siswa'])) ?>
                        <?php if($is_dup_nama): ?>
                            <span class="badge-nama-kembar" title="Ada siswa lain dengan nama yang sama, bedakan lewat kelas/NISN">⚠ NAMA KEMBAR</span>
                        <?php endif; ?>
                        <br>
                        <span class="<?= $is_dup_nama ? 'info-kelas-dup' : 'text-muted small' ?>">
                            <?= htmlspecialchars($s['nama_kelas'] ?? '-') ?> &bull; NISN: <?= htmlspecialchars($s['nisn'] ?? '-') ?>
                        </span>
                    </td>
                    <td class="col-sempit"><input type="number" name="absen[<?= $s['id'] ?>][sakit]" class="form-control" value="<?= (int)($s['sakit'] ?? 0) ?>"></td>
                    <td class="col-sempit"><input type="number" name="absen[<?= $s['id'] ?>][izin]" class="form-control" value="<?= (int)($s['izin'] ?? 0) ?>"></td>
                    <td class="col-sempit"><input type="number" name="absen[<?= $s['id'] ?>][alpa]" class="form-control" value="<?= (int)($s['alpha'] ?? 0) ?>"></td>
                    <td class="col-hadir fw-bold"><?= max(0, $p) ?>%</td>
                </tr>
                <?php
                    endforeach;
                else:
                ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data siswa untuk filter ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="submit" name="simpan_absen" class="btn btn-success rounded-pill px-5">SIMPAN</button>
    </form>
</div>