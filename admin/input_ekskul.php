<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// --- PENGAMAN KONEKSI ---
if (!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// 1. Ambil semester & ID tahun ajaran aktif (dipakai sebagai DEFAULT saja)
$qTahun = $conn->query("SELECT id, semester FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
$dTahun = $qTahun->fetch_assoc();
$id_tahun_aktif = $dTahun['id'] ?? 0;
$semester_aktif = $dTahun['semester'] ?? 'Ganjil';

// 1b. FILTER TAHUN AJARAN (dropdown per-record, sama gaya dengan Input Absensi)
$filter_tahun = $_GET['filter_tahun'] ?? $_POST['filter_tahun'] ?? $id_tahun_aktif;
$id_ta_pilih  = $filter_tahun;
$id_ta_safe   = mysqli_real_escape_string($conn, $id_ta_pilih);

// Info tahun ajaran yang sedang dipilih (fallback ke aktif kalau tidak ketemu)
$qInfoTA = $conn->query("SELECT * FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
$infoTA  = ($qInfoTA && $qInfoTA->num_rows > 0) ? $qInfoTA->fetch_assoc() : ($dTahun ?? ['id' => '', 'tahun' => '-', 'semester' => $semester_aktif]);
$semester_pilih = $infoTA['semester'] ?? $semester_aktif;

// List seluruh tahun ajaran untuk dropdown (urut terbaru dulu)
$list_tahun_ajaran = [];
$qListTA = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");
while($row_ta = $qListTA->fetch_assoc()){ $list_tahun_ajaran[] = $row_ta; }

// --- OBAT PENYAKIT ERROR (AUTO-HEALING TABEL DIPERBAIKI) ---
$cek_tabel = $conn->query("SHOW TABLES LIKE 'nilai_ekskul'");
if($cek_tabel->num_rows > 0) {
    $cek_kolom = $conn->query("SHOW COLUMNS FROM nilai_ekskul LIKE 'id_tahun_ajaran'");
    if($cek_kolom->num_rows == 0) {
        $conn->query("DROP TABLE nilai_ekskul");
    }
}

// 2. AUTO-CREATE TABEL YANG BENAR
$conn->query("CREATE TABLE IF NOT EXISTS nilai_ekskul (
    id int(11) NOT NULL AUTO_INCREMENT,
    id_siswa INT(11) NOT NULL,
    id_tahun_ajaran INT(11) NOT NULL,
    ekskul_1 INT(11) DEFAULT NULL,
    nilai_1 VARCHAR(20) DEFAULT NULL,
    ekskul_2 INT(11) DEFAULT NULL,
    nilai_2 VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_siswa, id_tahun_ajaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// --- DATA FILTER ---
$id_kelas = $_GET['id_kelas'] ?? '';
$jurusan_pilih = $_GET['jurusan'] ?? '';
$tingkat_pilih = $_GET['tingkat'] ?? '';
$search_siswa = $_GET['search'] ?? '';

// 3. PROSES SIMPAN DATA
if (isset($_POST['simpan_ekskul'])) {
    $id_siswas = $_POST['id_siswa'];
    $eks1s = $_POST['ekskul_1'];
    $nilai1s = $_POST['nilai_1'];
    $eks2s = $_POST['ekskul_2'];
    $nilai2s = $_POST['nilai_2'];

    foreach ($id_siswas as $key => $id_s) {
        $e1 = empty($eks1s[$key]) ? "NULL" : "'".$eks1s[$key]."'";
        $n1 = empty($nilai1s[$key]) ? "NULL" : "'".$nilai1s[$key]."'";
        $e2 = empty($eks2s[$key]) ? "NULL" : "'".$eks2s[$key]."'";
        $n2 = empty($nilai2s[$key]) ? "NULL" : "'".$nilai2s[$key]."'";

        $sql = "INSERT INTO nilai_ekskul (id_siswa, id_tahun_ajaran, ekskul_1, nilai_1, ekskul_2, nilai_2) 
                VALUES ('$id_s', '$id_ta_safe', $e1, $n1, $e2, $n2)
                ON DUPLICATE KEY UPDATE 
                ekskul_1=$e1, nilai_1=$n1, ekskul_2=$e2, nilai_2=$n2";
        $conn->query($sql);
    }

    $search_siswa = $_POST['search'] ?? $search_siswa;
echo "<script>alert('Data Ekskul berhasil disimpan!'); window.location='index.php?page=input_ekskul&filter_tahun=".urlencode($id_ta_pilih)."&id_kelas=$id_kelas&jurusan=$jurusan_pilih&tingkat=$tingkat_pilih&search=$search_siswa';</script>";
}

// 4. AMBIL DATA MASTER EKSKUL UNTUK DROPDOWN
$master_ekskul = [];
$qMasterEks = $conn->query("SELECT * FROM master_ekskul ORDER BY nama_ekskul ASC");
while($row = $qMasterEks->fetch_assoc()){
    $master_ekskul[] = $row;
}

// 5. AMBIL DATA JURUSAN UNTUK FILTER
$qJurusan = $conn->query("SELECT kode_jurusan, nama_jurusan FROM jurusan ORDER BY kode_jurusan ASC");

// 6. AMBIL DATA KELAS BERDASARKAN FILTER
$where_kelas = "WHERE 1=1";

if(!empty($jurusan_pilih)){
    $jurusan_safe = mysqli_real_escape_string($conn, $jurusan_pilih);
    $where_kelas .= " AND kode_jurusan = '$jurusan_safe'";
}

if(!empty($tingkat_pilih)){
    $tingkat_safe = mysqli_real_escape_string($conn, $tingkat_pilih);
    $where_kelas .= " AND tingkat = '$tingkat_safe'";
}

$kelasQ = $conn->query("SELECT id, nama_kelas, tingkat, kode_jurusan 
                        FROM kelas 
                        $where_kelas 
                        ORDER BY tingkat ASC, kode_jurusan ASC, nama_kelas ASC");
?>

<style>
    .filter-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .filter-box {
        background: #ffffff;
        border-radius: 20px;
    }

    .ekskul-icon-box {
        width: 45px;
        height: 45px;
        background: #EAF3FF;
        color: #0d385e;
    }

    .search-siswa-box {
        position: relative;
    }

    .search-siswa-box i {
        position: absolute;
        left: 18px;
        top: 14px;
        color: #9aabbc;
        z-index: 2;
    }

    .input-search-siswa {
        height: 42px;
        border-radius: 50px;
        border: 1px solid #dbe5ef;
        background-color: #f8fbff;
        color: #0d385e;
        font-size: 14px;
        font-weight: 600;
        padding-left: 45px;
        box-shadow: none;
    }

    .input-search-siswa:focus {
        border-color: #9fc1e7;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14);
        background-color: #ffffff;
    }
</style>

<div class="container-fluid p-4 bg-white rounded-4 shadow-sm border-0">
    <div class="d-flex align-items-center mb-4">
        <div class="rounded-circle d-flex justify-content-center align-items-center me-3 ekskul-icon-box">
            <i class="fas fa-running fs-5"></i>
        </div>

        <div>
            <h4 class="fw-bold text-dark mb-0">Input Nilai Ekskul</h4>
            <small class="text-muted">Tahun Ajaran: <b><?= htmlspecialchars($infoTA['tahun'] ?? '-') ?> (<?= strtoupper(htmlspecialchars($semester_pilih)) ?>)</b></small>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-3 mb-4 rounded-4 filter-box">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="input_ekskul">

            <div class="col-md-2">
                <label class="filter-label">Tahun Ajaran</label>
                <select name="filter_tahun" class="form-select border-0 bg-light rounded-3 fw-bold text-secondary" onchange="this.form.submit()">
                    <?php foreach($list_tahun_ajaran as $t):
                        $label_aktif = (strtolower($t['status']) == 'aktif') ? ' - [AKTIF]' : '';
                    ?>
                        <option value="<?= $t['id'] ?>" <?= ($id_ta_pilih == $t['id']) ? 'selected' : '' ?>>
                            TP <?= htmlspecialchars($t['tahun']) ?> (<?= htmlspecialchars($t['semester']) ?>)<?= $label_aktif ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="filter-label">Filter Jurusan</label>
                <select name="jurusan" class="form-select border-0 bg-light rounded-3 fw-bold text-secondary" onchange="this.form.submit()">
                    <option value="">Semua Jurusan</option>
                    <?php while($j = $qJurusan->fetch_assoc()): ?>
                        <option value="<?= $j['kode_jurusan'] ?>" <?= ($jurusan_pilih == $j['kode_jurusan']) ? 'selected' : '' ?>>
                            <?= $j['kode_jurusan'] ?> - <?= $j['nama_jurusan'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="filter-label">Filter Tingkat</label>
                <select name="tingkat" class="form-select border-0 bg-light rounded-3 fw-bold text-secondary" onchange="this.form.submit()">
                    <option value="">Semua Tingkat</option>
                    <option value="X" <?= ($tingkat_pilih == 'X') ? 'selected' : '' ?>>Kelas X</option>
                    <option value="XI" <?= ($tingkat_pilih == 'XI') ? 'selected' : '' ?>>Kelas XI</option>
                    <option value="XII" <?= ($tingkat_pilih == 'XII') ? 'selected' : '' ?>>Kelas XII</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="filter-label">Pilih Kelas</label>
                <select name="id_kelas" class="form-select border-0 bg-light rounded-3 fw-bold text-secondary">
                    <option value="">Semua Kelas</option>
                    <?php 
                    if($kelasQ && $kelasQ->num_rows > 0):
                        while($k = $kelasQ->fetch_assoc()):
                            $selected = ($id_kelas == $k['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $k['id'] ?>" <?= $selected ?>>
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

            <div class="col-md-2">
                <label class="filter-label">Cari Siswa</label>
                <div class="search-siswa-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="form-control input-search-siswa" placeholder="Nama/NISN..." value="<?= htmlspecialchars($search_siswa) ?>">
                </div>
            </div>

            <div class="col-md-1">
                <button type="submit" class="btn btn-filter-data w-100 fw-bold rounded-pill" style="height: 42px;">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <?php // Default: data ekskul langsung tampil semua siswa. Filter hanya membatasi data jika dipilih. ?>
    
    <div class="card bg-light p-3 mb-4 border-0 rounded-4 shadow-sm">
        <h6 class="fw-bold text-dark">
            <i class="fas fa-magic text-warning me-2"></i>Isi Cepat Massal
        </h6>

        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Terapkan Ke</label>
                <select id="bulk_target" class="form-select" style="border-radius:10px;">
                    <option value="1">Kolom Ekskul 1</option>
                    <option value="2">Kolom Ekskul 2</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="small fw-bold text-muted">Pilih Ekskul</label>
                <select id="bulk_ekskul" class="form-select" style="border-radius:10px;">
                    <option value="">- Kosongkan -</option>
                    <?php foreach($master_ekskul as $me): ?>
                        <option value="<?= $me['id'] ?>"><?= $me['nama_ekskul'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="small fw-bold text-muted">Pilih Predikat</label>
                <select id="bulk_nilai" class="form-select" style="border-radius:10px;">
                    <option value="">- Kosongkan -</option>
                    <option value="Sangat Baik">Sangat Baik</option>
                    <option value="Baik">Baik</option>
                    <option value="Cukup">Cukup</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="button" class="btn btn-soft-apply w-100 fw-bold" style="border-radius:10px; height: 38px;" onclick="applyBulk()">
                    Terapkan <i class="fas fa-check ms-1"></i>
                </button>
            </div>
        </div>

        <small class="text-muted mt-2 d-block">
            <i>Fitur ini akan mengisi data secara otomatis untuk seluruh siswa yang tampil pada tabel di bawah.</i>
        </small>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($id_ta_pilih) ?>">
        <input type="hidden" name="jurusan" value="<?= $jurusan_pilih ?>">
        <input type="hidden" name="tingkat" value="<?= $tingkat_pilih ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search_siswa) ?>">
        <input type="hidden" name="id_kelas" value="<?= htmlspecialchars($id_kelas) ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle border">
                <thead style="background-color: #e6f2ff;">
                    <tr>
                        <th class="py-3 px-3 text-center" width="5%">NO</th>
                        <th width="25%">NAMA SISWA</th>
                        <th width="20%">EKSKUL 1</th>
                        <th width="15%">NILAI 1</th>
                        <th width="20%">EKSKUL 2</th>
                        <th width="15%">NILAI 2</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);
                    $search_siswa_safe = mysqli_real_escape_string($conn, $search_siswa);

                    $jurusan_siswa_safe = mysqli_real_escape_string($conn, $jurusan_pilih);
                    $tingkat_siswa_safe = mysqli_real_escape_string($conn, $tingkat_pilih);

                    // Filter berbasis RIWAYAT KELAS (posisi kelas siswa PADA tahun ajaran yang difilter),
                    // bukan kelas siswa saat ini. Ini penting karena setelah Kenaikan Kelas,
                    // siswa.id_kelas berubah ke kelas terbaru, padahal utk tahun ajaran lama
                    // siswa tsb ada di kelas yang berbeda.
                    $where_rk = "WHERE 1=1";
                    if(!empty($id_kelas_safe)) $where_rk .= " AND r.id_kelas = '$id_kelas_safe'";
                    if(!empty($jurusan_siswa_safe)) $where_rk .= " AND k.kode_jurusan = '$jurusan_siswa_safe'";
                    if(!empty($tingkat_siswa_safe)) $where_rk .= " AND k.tingkat = '$tingkat_siswa_safe'";
                    if(!empty($search_siswa_safe)){
                        $where_rk .= " AND (s.nama_siswa LIKE '%$search_siswa_safe%' OR s.nisn LIKE '%$search_siswa_safe%' OR s.nis LIKE '%$search_siswa_safe%')";
                    }

                    // Subquery: ambil HANYA baris riwayat_kelas paling baru (MAX id) per siswa
                    // untuk tahun ajaran yang sedang difilter.
                    $sql = "SELECT s.id, s.nama_siswa, k.nama_kelas, ne.ekskul_1, ne.nilai_1, ne.ekskul_2, ne.nilai_2
                            FROM (
                                SELECT id_siswa, MAX(id) AS max_id
                                FROM riwayat_kelas
                                WHERE id_tahun_ajaran = '$id_ta_safe' AND id_siswa != 0 AND id_kelas != 0
                                GROUP BY id_siswa
                            ) rk
                            INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                            INNER JOIN siswa s ON r.id_siswa = s.id
                            LEFT JOIN kelas k ON r.id_kelas = k.id
                            LEFT JOIN nilai_ekskul ne ON s.id = ne.id_siswa AND ne.id_tahun_ajaran = '$id_ta_safe'
                            $where_rk
                            ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";
                    $qSiswa = $conn->query($sql);

                    // FALLBACK: kalau riwayat_kelas belum ada data utk tahun ajaran ini
                    // (misal Kenaikan Kelas belum pernah diproses), tampilkan siswa
                    // berdasarkan kelas mereka saat ini (siswa.id_kelas), seperti perilaku lama.
                    $pakai_fallback_kelas = (!$qSiswa || $qSiswa->num_rows == 0);
                    if($pakai_fallback_kelas){
                        $where_fb = "WHERE 1=1";
                        if(!empty($id_kelas_safe)) $where_fb .= " AND s.id_kelas = '$id_kelas_safe'";
                        if(!empty($jurusan_siswa_safe)) $where_fb .= " AND k.kode_jurusan = '$jurusan_siswa_safe'";
                        if(!empty($tingkat_siswa_safe)) $where_fb .= " AND k.tingkat = '$tingkat_siswa_safe'";
                        if(!empty($search_siswa_safe)){
                            $where_fb .= " AND (s.nama_siswa LIKE '%$search_siswa_safe%' OR s.nisn LIKE '%$search_siswa_safe%' OR s.nis LIKE '%$search_siswa_safe%')";
                        }

                        $sql_fb = "SELECT s.id, s.nama_siswa, k.nama_kelas, ne.ekskul_1, ne.nilai_1, ne.ekskul_2, ne.nilai_2
                                   FROM siswa s
                                   LEFT JOIN kelas k ON s.id_kelas = k.id
                                   LEFT JOIN nilai_ekskul ne ON s.id = ne.id_siswa AND ne.id_tahun_ajaran = '$id_ta_safe'
                                   $where_fb
                                   ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";
                        $qSiswa = $conn->query($sql_fb);
                    }

                    if($pakai_fallback_kelas && $qSiswa && $qSiswa->num_rows > 0):
                    ?>
                    <tr>
                        <td colspan="6" class="small text-muted fst-italic bg-light">
                            <i class="fas fa-info-circle me-1"></i> Riwayat kelas untuk tahun ajaran ini belum diproses (Kenaikan Kelas), menampilkan siswa berdasarkan kelas saat ini.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php
                    $no = 1;

                    if($qSiswa->num_rows > 0):
                        while($s = $qSiswa->fetch_assoc()):
                    ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $no++ ?></td>

                        <td class="fw-bold">
                            <?= $s['nama_siswa'] ?>
                            <?php if(empty($id_kelas)): ?>
                                <br><span class="badge bg-light text-dark border mt-1"><?= $s['nama_kelas'] ?></span>
                            <?php endif; ?>
                            <input type="hidden" name="id_siswa[]" value="<?= $s['id'] ?>">
                        </td>
                        
                        <td>
                            <select name="ekskul_1[]" class="form-select form-select-sm">
                                <option value="">- Kosong -</option>
                                <?php foreach($master_ekskul as $me): ?>
                                    <option value="<?= $me['id'] ?>" <?= ($s['ekskul_1'] == $me['id']) ? 'selected' : '' ?>>
                                        <?= $me['nama_ekskul'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <select name="nilai_1[]" class="form-select form-select-sm">
                                <option value="">- Predikat -</option>
                                <option value="Sangat Baik" <?= ($s['nilai_1'] == 'Sangat Baik') ? 'selected' : '' ?>>Sangat Baik</option>
                                <option value="Baik" <?= ($s['nilai_1'] == 'Baik') ? 'selected' : '' ?>>Baik</option>
                                <option value="Cukup" <?= ($s['nilai_1'] == 'Cukup') ? 'selected' : '' ?>>Cukup</option>
                            </select>
                        </td>

                        <td>
                            <select name="ekskul_2[]" class="form-select form-select-sm">
                                <option value="">- Kosong -</option>
                                <?php foreach($master_ekskul as $me): ?>
                                    <option value="<?= $me['id'] ?>" <?= ($s['ekskul_2'] == $me['id']) ? 'selected' : '' ?>>
                                        <?= $me['nama_ekskul'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <select name="nilai_2[]" class="form-select form-select-sm">
                                <option value="">- Predikat -</option>
                                <option value="Sangat Baik" <?= ($s['nilai_2'] == 'Sangat Baik') ? 'selected' : '' ?>>Sangat Baik</option>
                                <option value="Baik" <?= ($s['nilai_2'] == 'Baik') ? 'selected' : '' ?>>Baik</option>
                                <option value="Cukup" <?= ($s['nilai_2'] == 'Cukup') ? 'selected' : '' ?>>Cukup</option>
                            </select>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted fw-bold">
                            Belum ada siswa sesuai filter yang dipilih.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($qSiswa->num_rows > 0): ?>
        <div class="text-end mt-3">
            <button type="submit" name="simpan_ekskul" class="btn btn-save-data rounded-pill px-5 fw-bold shadow-sm" style="height: 45px;">
                <i class="fas fa-save me-2"></i>Simpan Perubahan
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function applyBulk() {
    let target = document.getElementById('bulk_target').value;
    let ekskul = document.getElementById('bulk_ekskul').value;
    let nilai = document.getElementById('bulk_nilai').value;

    let eksSelects = document.querySelectorAll(`select[name="ekskul_${target}[]"]`);
    let nilaiSelects = document.querySelectorAll(`select[name="nilai_${target}[]"]`);

    eksSelects.forEach(sel => sel.value = ekskul);
    nilaiSelects.forEach(sel => sel.value = nilai);
}
</script>