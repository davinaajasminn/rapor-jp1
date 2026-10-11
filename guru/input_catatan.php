<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN GURU) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['guru']) || $_SESSION['guru']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// --- PENGAMAN KONEKSI ---
if (!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// 1. Ambil semester aktif dari tabel tahun_ajaran, bukan identitas_sekolah
$qTahun = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahun = ($qTahun && $qTahun->num_rows > 0) ? $qTahun->fetch_assoc() : null;

$id_tahun_aktif = $dTahun['id'] ?? 0;
$tahun_aktif_teks = $dTahun['tahun'] ?? '';
$semester_aktif = $dTahun['semester'] ?? 'Ganjil';
$id_tahun_aktif_catatan_safe = mysqli_real_escape_string($conn, $id_tahun_aktif);

// 2. Pastikan kolom catatan ada di tabel absensi
$checkCol = $conn->query("SHOW COLUMNS FROM absensi LIKE 'catatan'");
if($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE absensi ADD catatan TEXT AFTER alpha");
}

$id_kelas = $_GET['id_kelas'] ?? '';

// --- 3. AMBIL DATA GURU LOGIN ---
$id_guru_login = $_SESSION['guru']['id'] ?? ($_SESSION['guru']['id_guru'] ?? '');
$nip_guru = $_SESSION['guru']['nip'] ?? '';
$nama_guru = $_SESSION['guru']['nama'] ?? ($_SESSION['guru']['nama_guru'] ?? '');

// Kalau session belum punya id guru, cari dari NIP/nama
if(empty($id_guru_login) && (!empty($nip_guru) || !empty($nama_guru))) {
    $nip_safe = mysqli_real_escape_string($conn, $nip_guru);
    $nama_safe = mysqli_real_escape_string($conn, $nama_guru);

    $qGuruLogin = $conn->query("SELECT id FROM guru 
                                WHERE nip = '$nip_safe' OR nama_guru = '$nama_safe' 
                                LIMIT 1");
    if($qGuruLogin && $qGuruLogin->num_rows > 0) {
        $id_guru_login = $qGuruLogin->fetch_assoc()['id'];
    }
}

// --- 4. VALIDASI HAK AKSES WALI KELAS ---
if (!empty($id_kelas) && isset($_SESSION['guru'])) {
    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);
    $id_guru_safe = mysqli_real_escape_string($conn, $id_guru_login);
    $tahun_aktif_safe = mysqli_real_escape_string($conn, $tahun_aktif_teks);

    // Dicek lewat riwayat_wali_kelas untuk tahun ajaran yang sedang aktif,
    // bukan lagi kolom statis kelas.id_wali_kelas.
    $cekWali = $conn->query("SELECT k.nama_kelas 
                             FROM kelas k
                             JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id
                             WHERE k.id = '$id_kelas_safe' 
                             AND rwk.id_guru = '$id_guru_safe'
                             AND rwk.tahun = '$tahun_aktif_safe'");

    if (!$cekWali || $cekWali->num_rows == 0) {
        echo "<div class='container-fluid p-4'>
                <div class='alert alert-danger border-0 shadow-sm rounded-4 text-center p-5'>
                    <i class='fas fa-shield-alt text-danger mb-3' style='font-size: 50px;'></i>
                    <h4 class='fw-bold text-dark'>Akses Ditolak!</h4>
                    <p class='text-muted mb-0'>Anda tidak memiliki hak akses untuk mengisi catatan di kelas ini karena Anda bukan wali kelasnya.</p>
                    <a href='index.php?page=validasi_index' class='btn btn-outline-danger rounded-pill mt-3 px-4'>Kembali</a>
                </div>
              </div>";
        exit;
    }
}

// 5. PROSES SIMPAN CATATAN
if (isset($_POST['simpan_catatan'])) {
    $id_siswas = $_POST['id_siswa'] ?? [];
    $catatans = $_POST['catatan'] ?? [];

    foreach ($id_siswas as $key => $id_s) {
        $id_s_safe = mysqli_real_escape_string($conn, $id_s);
        $c = mysqli_real_escape_string($conn, $catatans[$key] ?? '');

        $cek_data = $conn->query("SELECT id_absen FROM absensi 
                                  WHERE id_siswa = '$id_s_safe' 
                                  AND semester = '$semester_aktif'
                                  LIMIT 1");

        if($cek_data && $cek_data->num_rows > 0) {
            $conn->query("UPDATE absensi 
                          SET catatan = '$c' 
                          WHERE id_siswa = '$id_s_safe' 
                          AND semester = '$semester_aktif'");
        } else {
            // Insert disesuaikan dengan struktur tabel absensi yang tidak punya kolom id_tahun_ajaran
            $conn->query("INSERT INTO absensi 
                          (id_siswa, semester, sakit, izin, alpha, catatan, status_validasi) 
                          VALUES 
                          ('$id_s_safe', '$semester_aktif', 0, 0, 0, '$c', 1)");
        }
    }

    echo "<script>alert('Catatan Akademik Wali Kelas berhasil disimpan!'); window.location='index.php?page=input_catatan&id_kelas=$id_kelas';</script>";
}

// 6. AMBIL DATA KELAS UNTUK DROPDOWN
if(isset($_SESSION['guru']) && !empty($id_guru_login)) {
    $id_guru_safe = mysqli_real_escape_string($conn, $id_guru_login);
    $tahun_aktif_safe2 = mysqli_real_escape_string($conn, $tahun_aktif_teks);
    $kelasQ = $conn->query("SELECT k.* 
                            FROM kelas k
                            JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id
                            WHERE rwk.id_guru = '$id_guru_safe' 
                            AND rwk.tahun = '$tahun_aktif_safe2'
                            ORDER BY k.tingkat ASC, k.nama_kelas ASC");
} else {
    $kelasQ = $conn->query("SELECT * FROM kelas ORDER BY tingkat ASC, nama_kelas ASC");
}
?>

<style>
    .catatan-wrapper {
        background: #ffffff;
        border-radius: 22px;
        box-shadow: 0 5px 18px rgba(80, 115, 155, 0.08);
    }

    .catatan-icon-box {
        width: 45px;
        height: 45px;
        background: #D8E9FA;
        color: #0d385e;
    }

    .filter-catatan-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08);
        padding: 16px;
    }

    .filter-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .select-catatan {
        height: 45px;
        border-radius: 50px !important;
        border: 1px solid #dbe5ef !important;
        background-color: #f8fbff !important;
        color: #0d385e !important;
        font-weight: 700;
        box-shadow: none !important;
    }

    .bulk-catatan-card {
        background: #FFF7DF;
        border-radius: 18px;
        border-left: 5px solid #E8C46A;
    }

    .btn-apply-catatan {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #E8D5AB !important;
        font-weight: 800 !important;
        box-shadow: 0 5px 12px rgba(214, 189, 145, 0.18) !important;
    }

    .btn-save-catatan {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        font-weight: 800 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
    }

    .area-catatan:focus {
        border-color: #9fc1e7 !important;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14) !important;
    }

    .btn-back-catatan {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        padding: 10px 22px !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.16) !important;
        text-decoration: none !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-back-catatan:hover,
    .btn-back-catatan:focus,
    .btn-back-catatan:active {
        background: #C8DFF6 !important;
        color: #0d385e !important;
    }
</style>

<div class="container-fluid p-4 catatan-wrapper border-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex justify-content-center align-items-center me-3 catatan-icon-box">
                <i class="fas fa-comment-dots fs-5"></i>
            </div>

            <div>
                <h4 class="fw-bold text-dark mb-0">Input Catatan Akademik</h4>
                <small class="text-muted">Halaman Khusus Wali Kelas | Semester: <b><?= strtoupper($semester_aktif) ?></b></small>
            </div>
        </div>

        <a href="index.php?page=validasi_index" class="btn btn-back-catatan">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="filter-catatan-card mb-4">
        <form method="GET" action="">
            <input type="hidden" name="page" value="input_catatan">

            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="filter-label">Pilih Kelas Perwalian</label>
                    <select name="id_kelas" class="form-select select-catatan px-3" onchange="this.form.submit()">
                        <option value="">-- Pilih Kelas Perwalian --</option>
                        <?php if($kelasQ && $kelasQ->num_rows > 0): ?>
                            <?php while($k = $kelasQ->fetch_assoc()): ?>
                                <option value="<?= $k['id'] ?>" <?= ($id_kelas == $k['id']) ? 'selected' : '' ?>>
                                    <?= $k['nama_kelas'] ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">Belum ada kelas perwalian</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if(!empty($id_kelas)): ?>

    <div class="card bulk-catatan-card p-3 mb-4 border-0 shadow-sm">
        <h6 class="fw-bold text-dark">
            <i class="fas fa-magic text-warning me-2"></i>Terapkan Catatan Massal (Satu Kelas)
        </h6>

        <div class="row g-2 align-items-center">
            <div class="col-md-10">
                <input type="text" id="bulk_catatan" class="form-control fw-bold text-secondary shadow-sm" 
                       style="border-radius:10px; height: 45px; border: 1px solid #E8C46A;" 
                       placeholder="Ketik template catatan untuk satu kelas di sini..." 
                       value="Selamat peserta didik telah menuntaskan seluruh mata pelajaran, pertahankan prestasi">
            </div>

            <div class="col-md-2">
                <button type="button" class="btn btn-apply-catatan w-100 rounded-3" style="height: 45px;" onclick="applyCatatan()">
                    Terapkan <i class="fas fa-check ms-1"></i>
                </button>
            </div>
        </div>

        <small class="text-muted mt-2 d-block">
            <i>*Catatan: Ketik pesan Anda di atas, lalu klik Terapkan untuk mengisi seluruh kolom teks siswa sekaligus.</i>
        </small>
    </div>

    <form method="POST" action="">
        <div class="table-responsive">
            <table class="table table-hover align-middle border">
                <thead style="background-color: #e6f2ff;">
                    <tr>
                        <th class="py-3 px-3 text-center" width="5%">NO</th>
                        <th width="25%">NAMA SISWA</th>
                        <th width="70%">PESAN / CATATAN AKADEMIK WALI KELAS</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

                    // FIX: riwayat_kelas pada tahun ajaran aktif jadi sumber utama.
                    // Kolom statis siswa.id_kelas HANYA fallback untuk siswa yang
                    // belum sama sekali punya baris riwayat_kelas di tahun ajaran ini,
                    // supaya siswa yang sudah pindah/naik kelas di tahun ini tidak
                    // ikut ketarik hanya karena kolom statisnya belum ter-update.
                    $qSiswa = $conn->query("SELECT s.id, s.nama_siswa, MAX(a.catatan) as catatan 
                                            FROM siswa s 
                                            LEFT JOIN absensi a ON s.id = a.id_siswa AND a.semester = '$semester_aktif'
                                            WHERE s.id IN (
                                                SELECT rk.id_siswa FROM riwayat_kelas rk 
                                                WHERE rk.id_kelas = '$id_kelas_safe' 
                                                AND rk.id_tahun_ajaran = '$id_tahun_aktif_catatan_safe'
                                            )
                                            OR (
                                                s.id_kelas = '$id_kelas_safe'
                                                AND s.id NOT IN (
                                                    SELECT rk2.id_siswa FROM riwayat_kelas rk2 
                                                    WHERE rk2.id_tahun_ajaran = '$id_tahun_aktif_catatan_safe'
                                                )
                                            )
                                            GROUP BY s.id, s.nama_siswa
                                            ORDER BY s.nama_siswa ASC");
                    $no = 1;

                    if($qSiswa && $qSiswa->num_rows > 0):
                        while($s = $qSiswa->fetch_assoc()):
                    ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                        <td class="fw-bold text-dark">
                            <?= ucwords(strtolower($s['nama_siswa'])) ?>
                            <input type="hidden" name="id_siswa[]" value="<?= $s['id'] ?>">
                        </td>

                        <td>
                            <textarea name="catatan[]" class="form-control bg-white area-catatan shadow-sm" rows="2" style="border-radius: 10px;" placeholder="Tulis pesan motivasi atau teguran di sini..."><?= htmlspecialchars($s['catatan'] ?? '') ?></textarea>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="3" class="text-center py-4 text-muted fw-bold">Belum ada siswa di kelas ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($qSiswa && $qSiswa->num_rows > 0): ?>
        <div class="text-end mt-3">
            <button type="submit" name="simpan_catatan" class="btn btn-save-catatan rounded-pill px-5 shadow-sm" style="height: 45px;">
                <i class="fas fa-save me-2"></i>Simpan Semua Catatan
            </button>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<script>
function applyCatatan() {
    let template = document.getElementById('bulk_catatan').value;
    let catatanAreas = document.querySelectorAll('.area-catatan');

    catatanAreas.forEach(area => {
        area.value = template;
        area.style.transition = "none"; 
        area.style.boxShadow = "0 0 8px #E8C46A"; 
        setTimeout(() => area.style.boxShadow = "none", 800);
    });
}
</script>