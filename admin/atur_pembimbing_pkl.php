<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// =======================================================
// ADMIN - ATUR GURU PEMBIMBING PKL PER SISWA
// =======================================================
// Konsep:
// Admin menentukan guru pembimbing PKL per siswa, PER TAHUN AJARAN.
// Guru pembimbing nanti input data PKL dan nilai PKL.
// =======================================================

// Koneksi aman
if(!isset($conn) || !$conn){
    if(!class_exists('Database')){
        require_once '../config/Database.php';
    }
    $db = new Database();
    $conn = $db->getConnection();
}

// Pastikan tabel data_pkl punya kolom yang dibutuhkan
$cekKolomGuru = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'id_guru_pembimbing'");
if($cekKolomGuru && $cekKolomGuru->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD id_guru_pembimbing INT NULL AFTER id_siswa");
}

$cekKolomNilai = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'nilai_pkl'");
if($cekKolomNilai && $cekKolomNilai->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD nilai_pkl INT NULL AFTER lamanya");
}

// --- FILTER TAHUN AJARAN (dropdown per-record, sama gaya dengan halaman lain) ---
$qTaAktif = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
$ta_aktif = $qTaAktif ? $qTaAktif->fetch_assoc() : null;

$filter_tahun = $_GET['filter_tahun'] ?? $_POST['filter_tahun'] ?? ($ta_aktif['id'] ?? '');
$id_ta_pilih  = $filter_tahun;
$id_ta_safe   = mysqli_real_escape_string($conn, $id_ta_pilih);

$qInfoTA = $conn->query("SELECT * FROM tahun_ajaran WHERE id = '$id_ta_safe' LIMIT 1");
$infoTA  = ($qInfoTA && $qInfoTA->num_rows > 0) ? $qInfoTA->fetch_assoc() : ($ta_aktif ?? ['id' => '', 'tahun' => '-', 'semester' => '-']);

$list_tahun_ajaran = [];
$qListTA = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");
while($row_ta = $qListTA->fetch_assoc()){ $list_tahun_ajaran[] = $row_ta; }

$id_kelas = $_GET['id_kelas'] ?? '';
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

// Simpan guru pembimbing PKL
if(isset($_POST['simpan_pembimbing_pkl'])){
    $post_id_kelas = $_POST['id_kelas'] ?? '';
    $post_filter_tahun = $_POST['filter_tahun'] ?? $id_ta_pilih;
    $post_filter_tahun_safe = mysqli_real_escape_string($conn, $post_filter_tahun);

    if(isset($_POST['pembimbing']) && is_array($_POST['pembimbing'])){
        foreach($_POST['pembimbing'] as $id_siswa => $id_guru_pembimbing){
            $id_siswa = (int)$id_siswa;
            $id_guru_pembimbing = (int)$id_guru_pembimbing;

            // Data PKL sekarang dibuat 1 baris per siswa PER TAHUN AJARAN,
            // supaya guru pembimbing di tahun ajaran berbeda tidak saling menimpa.
            $cek = $conn->query("SELECT id FROM data_pkl WHERE id_siswa = '$id_siswa' AND id_tahun_ajaran = '$post_filter_tahun_safe' LIMIT 1");

            if($cek && $cek->num_rows > 0){
                $nilai = ($id_guru_pembimbing > 0) ? "'$id_guru_pembimbing'" : "NULL";
                $conn->query("UPDATE data_pkl 
                              SET id_guru_pembimbing = $nilai
                              WHERE id_siswa = '$id_siswa' AND id_tahun_ajaran = '$post_filter_tahun_safe'");
            } else {
                if($id_guru_pembimbing > 0){
                    $conn->query("INSERT INTO data_pkl 
                                    (id_siswa, id_tahun_ajaran, id_guru_pembimbing, mitra_dudi, lokasi, lamanya, keterangan)
                                  VALUES
                                    ('$id_siswa', '$post_filter_tahun_safe', '$id_guru_pembimbing', '', '', '6', '')");
                }
            }
        }
    }

    echo "<script>alert('Guru pembimbing PKL berhasil disimpan!'); window.location='?page=atur_pembimbing_pkl&filter_tahun=".urlencode($post_filter_tahun)."&id_kelas=$post_id_kelas';</script>";
    exit;
}

$qKelas = $conn->query("SELECT id, nama_kelas FROM kelas WHERE nama_kelas LIKE 'XII%' ORDER BY nama_kelas ASC");
$qGuru = $conn->query("SELECT id, nama_guru, nip FROM guru ORDER BY nama_guru ASC");

$daftar_guru = [];
if($qGuru){
    while($g = $qGuru->fetch_assoc()){
        $daftar_guru[] = $g;
    }
}
?>

<style>
    .pkl-admin-banner {
        background: linear-gradient(135deg, #eaf3ff 0%, #c7ddf5 55%, #a9c5e6 100%);
        color: #0d385e;
        border-radius: 22px;
        padding: 26px 30px;
        box-shadow: 0 8px 22px rgba(80, 115, 155, 0.14);
        border: 1px solid rgba(255,255,255,0.75);
    }

    .btn-soft-blue-pkl {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
    }

    .btn-soft-green-pkl {
        background: #DDF5EA !important;
        color: #1f5f48 !important;
        border: 1px solid #9DDCC4 !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
    }

    .info-pkl-soft {
        background: #fff7e6;
        color: #7A5200;
        border: 1px solid #ffe2ad;
        border-radius: 16px;
        padding: 12px 15px;
        font-weight: 600;
        margin-bottom: 16px;
    }
</style>

<div class="pkl-admin-banner mb-4">
    <h3 class="fw-bold mb-2">
        <i class="fas fa-user-tie me-2"></i>Atur Pembimbing PKL
    </h3>
    <p class="mb-0 fw-semibold">
        Admin menentukan guru pembimbing PKL per siswa. Guru pembimbing akan menginput data PKL dan nilai PKL.
    </p>
</div>

<div class="info-pkl-soft">
    <i class="fas fa-info-circle me-1"></i>
    Data pembimbing sekarang dipisah per Tahun Ajaran. Pilih Tahun Ajaran dan Kelas XII untuk melihat/mengatur pembimbing.
</div>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="atur_pembimbing_pkl">

        <div class="col-md-4">
            <label class="small fw-bold text-muted">Tahun Ajaran</label>
            <select name="filter_tahun" class="form-select rounded-pill" onchange="this.form.submit()">
                <?php foreach($list_tahun_ajaran as $t):
                    $label_aktif = (strtolower($t['status']) == 'aktif') ? ' - [AKTIF]' : '';
                ?>
                    <option value="<?= $t['id'] ?>" <?= ($id_ta_pilih == $t['id']) ? 'selected' : '' ?>>
                        TP <?= htmlspecialchars($t['tahun']) ?> (<?= htmlspecialchars($t['semester']) ?>)<?= $label_aktif ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-5">
            <label class="small fw-bold text-muted">Pilih Kelas XII</label>
            <select name="id_kelas" class="form-select rounded-pill" required>
                <option value="">-- Pilih Kelas --</option>
                <?php if($qKelas): ?>
                    <?php while($k = $qKelas->fetch_assoc()): ?>
                        <option value="<?= $k['id'] ?>" <?= ($id_kelas == $k['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_kelas']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="col-md-3">
            <button class="btn btn-soft-blue-pkl w-100">
                <i class="fas fa-search me-1"></i> Tampilkan
            </button>
        </div>
    </form>
</div>

<?php if(!empty($id_kelas) && !empty($id_ta_pilih)): 
    $qNamaKelas = $conn->query("SELECT nama_kelas FROM kelas WHERE id = '$id_kelas_safe'");
    $nama_kelas = ($qNamaKelas && $qNamaKelas->num_rows > 0) ? $qNamaKelas->fetch_assoc()['nama_kelas'] : '-';

    // Ambil siswa berdasarkan RIWAYAT KELAS pada tahun ajaran yang difilter
    // (bukan kelas siswa saat ini), supaya benar walau siswa sudah lulus/pindah kelas.
    $sql = "SELECT s.id, s.nama_siswa, s.nisn, p.id_guru_pembimbing
            FROM (
                SELECT id_siswa, MAX(id) AS max_id
                FROM riwayat_kelas
                WHERE id_tahun_ajaran = '$id_ta_safe' AND id_siswa != 0 AND id_kelas != 0
                GROUP BY id_siswa
            ) rk
            INNER JOIN riwayat_kelas r ON r.id = rk.max_id
            INNER JOIN siswa s ON r.id_siswa = s.id
            LEFT JOIN data_pkl p ON s.id = p.id_siswa AND p.id_tahun_ajaran = '$id_ta_safe'
            WHERE r.id_kelas = '$id_kelas_safe'
            ORDER BY s.nama_siswa ASC";
    $qSiswa = $conn->query($sql);

    // FALLBACK: kalau riwayat_kelas belum ada data utk tahun ajaran ini
    // (misal Kenaikan Kelas belum pernah diproses), tampilkan siswa
    // berdasarkan kelas mereka saat ini (siswa.id_kelas), seperti perilaku lama.
    $pakai_fallback_kelas = (!$qSiswa || $qSiswa->num_rows == 0);
    if($pakai_fallback_kelas){
        $qSiswa = $conn->query("SELECT s.id, s.nama_siswa, s.nisn, p.id_guru_pembimbing
                                FROM siswa s
                                LEFT JOIN data_pkl p ON s.id = p.id_siswa AND p.id_tahun_ajaran = '$id_ta_safe'
                                WHERE s.id_kelas = '$id_kelas_safe'
                                ORDER BY s.nama_siswa ASC");
    }
?>

<form method="POST">
    <input type="hidden" name="id_kelas" value="<?= htmlspecialchars($id_kelas) ?>">
    <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($id_ta_pilih) ?>">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-users me-2 text-primary"></i>Daftar Siswa <?= htmlspecialchars($nama_kelas) ?>
                <small class="text-muted fw-normal">&mdash; TP <?= htmlspecialchars($infoTA['tahun'] ?? '-') ?> (<?= htmlspecialchars($infoTA['semester'] ?? '-') ?>)</small>
            </h5>
            <?php if($pakai_fallback_kelas): ?>
                <small class="text-muted d-block mt-1">
                    <i class="fas fa-info-circle me-1"></i> Riwayat kelas untuk tahun ajaran ini belum diproses, menampilkan siswa berdasarkan kelas saat ini.
                </small>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-center small fw-bold text-muted text-uppercase">
                    <tr>
                        <th width="60">No</th>
                        <th class="text-start">Nama Siswa</th>
                        <th width="220">NISN</th>
                        <th width="360">Guru Pembimbing PKL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; if($qSiswa && $qSiswa->num_rows > 0): ?>
                        <?php while($s = $qSiswa->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="fw-bold text-start"><?= strtoupper(htmlspecialchars($s['nama_siswa'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($s['nisn'] ?? '-') ?></td>
                                <td>
                                    <select name="pembimbing[<?= $s['id'] ?>]" class="form-select form-select-sm">
                                        <option value="">-- Belum Ditentukan --</option>
                                        <?php foreach($daftar_guru as $g): ?>
                                            <option value="<?= $g['id'] ?>" <?= ((int)($s['id_guru_pembimbing'] ?? 0) == (int)$g['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($g['nama_guru']) ?><?= !empty($g['nip']) ? ' - '.$g['nip'] : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted fw-bold">Belum ada siswa di kelas ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-light text-end border-top">
            <button type="submit" name="simpan_pembimbing_pkl" class="btn btn-soft-green-pkl px-5">
                <i class="fas fa-save me-2"></i> Simpan Guru Pembimbing
            </button>
        </div>
    </div>
</form>

<?php endif; ?>