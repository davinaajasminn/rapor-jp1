<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN GURU) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['guru']) || $_SESSION['guru']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// =======================================================
// INPUT NILAI & DATA PKL - KHUSUS GURU PEMBIMBING PKL
// =======================================================
// Guru pembimbing PKL ditentukan oleh admin di menu Atur Pembimbing PKL.
// Guru pembimbing boleh input data/nilai PKL walaupun BUKAN wali kelas.
// Guru pembimbing TIDAK melakukan validasi. Validasi tetap oleh wali kelas.
// =======================================================

if(!isset($conn) || !$conn){
    if(!class_exists('Database')){
        require_once '../config/Database.php';
    }
    $db = new Database();
    $conn = $db->getConnection();
}

$id_guru_login = $_SESSION['guru']['id'] ?? ($_SESSION['id_guru'] ?? ($_SESSION['id'] ?? 0));
$nip_guru_login = $_SESSION['guru']['nip'] ?? ($_SESSION['nip'] ?? '');
$nama_guru_login = $_SESSION['guru']['nama_guru'] ?? ($_SESSION['guru']['nama'] ?? ($_SESSION['nama_guru'] ?? 'Guru Pembimbing'));

if((empty($id_guru_login) || $id_guru_login == 0) && !empty($nip_guru_login)){
    $nip_safe = mysqli_real_escape_string($conn, $nip_guru_login);
    $qGuruLogin = $conn->query("SELECT id, nama_guru, nip FROM guru WHERE nip = '$nip_safe' LIMIT 1");
    if($qGuruLogin && $qGuruLogin->num_rows > 0){
        $dGuruLogin = $qGuruLogin->fetch_assoc();
        $id_guru_login = (int)$dGuruLogin['id'];
        $nama_guru_login = $dGuruLogin['nama_guru'];
    }
} else if(!empty($id_guru_login)){
    $qGuruLogin = $conn->query("SELECT id, nama_guru, nip FROM guru WHERE id = '$id_guru_login' LIMIT 1");
    if($qGuruLogin && $qGuruLogin->num_rows > 0){
        $dGuruLogin = $qGuruLogin->fetch_assoc();
        $nama_guru_login = $dGuruLogin['nama_guru'];
        $nip_guru_login = $dGuruLogin['nip'];
    }
}

if(empty($id_guru_login)){
    echo "<div class='alert alert-danger border-0 shadow-sm rounded-4 mt-4'>
            Data guru login tidak terbaca. Pastikan session guru menyimpan id atau nip guru.
          </div>";
    return;
}

function ensureColumnPKLFix($conn, $table, $column, $definition){
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $cek = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
    if($cek && $cek->num_rows == 0){
        $conn->query("ALTER TABLE `$table_safe` ADD `$column` $definition");
    }
}

ensureColumnPKLFix($conn, 'data_pkl', 'id_guru_pembimbing', "INT NULL AFTER id_siswa");
ensureColumnPKLFix($conn, 'data_pkl', 'nilai_pkl', "INT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tanggal_mulai', "DATE NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tanggal_selesai', "DATE NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'nama_instruktur', "VARCHAR(150) NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp1_skor', "INT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp1_deskripsi', "TEXT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp2_skor', "INT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp2_deskripsi', "TEXT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp3_skor', "INT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp3_deskripsi', "TEXT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp4_skor', "INT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'tp4_deskripsi', "TEXT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'catatan_pkl', "TEXT NULL");
ensureColumnPKLFix($conn, 'data_pkl', 'sakit', "INT NULL DEFAULT 0");
ensureColumnPKLFix($conn, 'data_pkl', 'izin', "INT NULL DEFAULT 0");
ensureColumnPKLFix($conn, 'data_pkl', 'tanpa_keterangan', "INT NULL DEFAULT 0");
ensureColumnPKLFix($conn, 'data_pkl', 'status_validasi_pkl', "TINYINT(1) NOT NULL DEFAULT 0");

$default_tp = [
    1 => "Menerapkan soft skills yang dibutuhkan dalam dunia kerja (tempat PKL)",
    2 => "Menerapkan norma, POS dan K3LH yang ada pada dunia kerja (tempat PKL)",
    3 => "Menerapkan kompetensi teknis yang sudah dipelajari di sekolah dan/atau baru dipelajari pada dunia kerja (tempat PKL)",
    4 => "Memahami alur bisnis dunia kerja tempat PKL"
];

$default_deskripsi_pkl = [
    1 => "Mampu menerapkan soft skills seperti disiplin, tanggung jawab, komunikasi, kerja sama, dan sikap profesional sesuai tuntutan dunia kerja.",
    2 => "Mampu menerapkan norma kerja, prosedur operasional standar, serta prinsip K3LH dengan baik selama melaksanakan kegiatan PKL.",
    3 => "Mampu menerapkan kompetensi teknis yang dipelajari di sekolah maupun keterampilan baru yang diperoleh di tempat PKL.",
    4 => "Mampu memahami alur bisnis, budaya kerja, dan proses kerja di tempat PKL sehingga dapat beradaptasi dengan lingkungan dunia kerja."
];

$id_kelas = $_GET['id_kelas'] ?? '';
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

// FIX: ambil Tahun Ajaran AKTIF, dipakai untuk filter semua query data_pkl di bawah.
// Tanpa ini, data PKL dari tahun ajaran lain (yang sudah tidak aktif) tetap ikut muncul.
$qTaAktifPkl = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status) = 'aktif' LIMIT 1");
$ta_aktif_pkl = ($qTaAktifPkl && $qTaAktifPkl->num_rows > 0) ? $qTaAktifPkl->fetch_assoc() : null;
$id_ta_aktif_pkl = $ta_aktif_pkl['id'] ?? 0;
$id_ta_aktif_pkl_safe = mysqli_real_escape_string($conn, $id_ta_aktif_pkl);

if(empty($id_ta_aktif_pkl)){
    echo "<div class='alert alert-danger border-0 shadow-sm rounded-4 mt-4'>
            Belum ada Tahun Ajaran yang berstatus Aktif. Hubungi admin untuk mengaktifkan Tahun Ajaran di menu Identitas Sekolah.
          </div>";
    return;
}

// SIMPAN DATA PKL
if(isset($_POST['simpan_pkl'])){
    if(isset($_POST['pkl']) && is_array($_POST['pkl'])){
        foreach($_POST['pkl'] as $id_siswa => $data){
            $id_siswa = (int)$id_siswa;

            // Keamanan: guru hanya boleh update siswa yang memang ditugaskan admin ke dia,
            // DAN hanya untuk baris di Tahun Ajaran yang sedang aktif (biar gak nimpa data PKL tahun lain)
            $cekAkses = $conn->query("SELECT id FROM data_pkl 
                                      WHERE id_siswa = '$id_siswa'
                                      AND id_guru_pembimbing = '$id_guru_login'
                                      AND id_tahun_ajaran = '$id_ta_aktif_pkl_safe'
                                      LIMIT 1");
            if(!$cekAkses || $cekAkses->num_rows == 0){
                continue;
            }

            $mitra = $conn->real_escape_string(trim($data['mitra'] ?? ''));
            $lokasi = $conn->real_escape_string(trim($data['lokasi'] ?? ''));
            $nama_instruktur = $conn->real_escape_string(trim($data['nama_instruktur'] ?? ''));
            $lamanya = $conn->real_escape_string(trim($data['lamanya'] ?? ''));
            $keterangan = $conn->real_escape_string(trim($data['keterangan'] ?? ''));
            $catatan_pkl = $conn->real_escape_string(trim($data['catatan_pkl'] ?? ''));

            $tanggal_mulai = trim($data['tanggal_mulai'] ?? '');
            $tanggal_selesai = trim($data['tanggal_selesai'] ?? '');
            $tanggal_mulai_sql = ($tanggal_mulai === '') ? "NULL" : "'" . $conn->real_escape_string($tanggal_mulai) . "'";
            $tanggal_selesai_sql = ($tanggal_selesai === '') ? "NULL" : "'" . $conn->real_escape_string($tanggal_selesai) . "'";

            $skor_valid = [];
            $tp_sql = [];

            for($i=1; $i<=4; $i++){
                $skor = trim($data["tp{$i}_skor"] ?? '');
                if($skor !== '' && ($skor < 0 || $skor > 100)){
                    echo "<script>alert('Skor TP harus 0 sampai 100.'); window.history.back();</script>";
                    exit;
                }

                $tp_sql["tp{$i}_skor"] = ($skor === '') ? "NULL" : intval($skor);
                if($skor !== '') $skor_valid[] = (int)$skor;

                $desc = $conn->real_escape_string(trim($data["tp{$i}_deskripsi"] ?? ''));
                $tp_sql["tp{$i}_deskripsi"] = $desc;
            }

            $nilai_pkl_sql = count($skor_valid) > 0 ? round(array_sum($skor_valid) / count($skor_valid)) : "NULL";

            $conn->query("UPDATE data_pkl SET
                            mitra_dudi = '$mitra',
                            lokasi = '$lokasi',
                            tanggal_mulai = $tanggal_mulai_sql,
                            tanggal_selesai = $tanggal_selesai_sql,
                            nama_instruktur = '$nama_instruktur',
                            lamanya = '$lamanya',
                            keterangan = '$keterangan',
                            nilai_pkl = $nilai_pkl_sql,
                            tp1_skor = {$tp_sql['tp1_skor']},
                            tp1_deskripsi = '{$tp_sql['tp1_deskripsi']}',
                            tp2_skor = {$tp_sql['tp2_skor']},
                            tp2_deskripsi = '{$tp_sql['tp2_deskripsi']}',
                            tp3_skor = {$tp_sql['tp3_skor']},
                            tp3_deskripsi = '{$tp_sql['tp3_deskripsi']}',
                            tp4_skor = {$tp_sql['tp4_skor']},
                            tp4_deskripsi = '{$tp_sql['tp4_deskripsi']}',
                            catatan_pkl = '$catatan_pkl'
                          WHERE id_siswa = '$id_siswa'
                          AND id_guru_pembimbing = '$id_guru_login'
                          AND id_tahun_ajaran = '$id_ta_aktif_pkl_safe'");
        }
    }

    echo "<script>alert('Data dan nilai PKL berhasil disimpan. Validasi tetap dilakukan oleh wali kelas.'); window.location='?page=input_pkl&id_kelas=$id_kelas_safe';</script>";
    exit;
}

// Kalau belum pilih kelas, tampilkan kelas bimbingan PKL berdasarkan data_pkl
if(empty($id_kelas)){
    $qKelas = $conn->query("SELECT k.id, k.nama_kelas, COUNT(s.id) AS total_siswa
                            FROM data_pkl p
                            JOIN siswa s ON p.id_siswa = s.id
                            JOIN kelas k ON s.id_kelas = k.id
                            WHERE p.id_guru_pembimbing = '$id_guru_login'
                            AND p.id_tahun_ajaran = '$id_ta_aktif_pkl_safe'
                            GROUP BY k.id, k.nama_kelas
                            ORDER BY k.nama_kelas ASC");
?>
<style>
    .pkl-banner { background:#BFD3EA; color:#0d385e; border:1px solid #AFC6E0; border-radius:22px; padding:28px 32px; box-shadow:0 8px 22px rgba(80,115,155,.14); }
    .pkl-card { border:none; border-radius:20px; box-shadow:0 8px 22px rgba(80,115,155,.10); background:#fff; }
    .btn-pkl-soft { background:#D8E9FA!important; color:#0d385e!important; border:1px solid #AFC6E0!important; border-radius:50px!important; font-weight:800!important; }
    .btn-pkl-soft:hover { background:#BFD3EA!important; color:#0d385e!important; }
    .alert-pkl-blue { background:#EEF6FF; color:#0d385e; border:1px solid #AFC6E0; border-radius:16px; padding:14px 18px; box-shadow:0 5px 12px rgba(80,115,155,.08); font-weight:500; }
</style>

<div class="pkl-banner mb-4">
    <h3 class="fw-bold mb-2"><i class="fas fa-briefcase me-2"></i>Input Data & Nilai PKL</h3>
    <p class="mb-0 fw-semibold">Guru Pembimbing: <?= htmlspecialchars($nama_guru_login) ?></p>
    <small>Data diambil dari penugasan admin pada menu Atur Pembimbing PKL.</small>
</div>

<div class="alert-pkl-blue mb-4">
    Guru pembimbing hanya input data dan nilai PKL. Validasi nilai PKL tetap dilakukan oleh wali kelas.
</div>

<div class="row g-3">
    <?php if($qKelas && $qKelas->num_rows > 0): ?>
        <?php while($k = $qKelas->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="card pkl-card h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-2" style="color:#0d385e;"><?= htmlspecialchars($k['nama_kelas']) ?></h5>
                        <p class="text-muted small mb-3"><?= (int)$k['total_siswa'] ?> siswa bimbingan PKL</p>
                        <a href="?page=input_pkl&id_kelas=<?= $k['id'] ?>" class="btn btn-pkl-soft btn-sm px-4">
                            <i class="fas fa-edit me-1"></i> Input PKL
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm rounded-4">
                Belum ada siswa PKL yang ditugaskan ke akun ini. Admin perlu mengatur pembimbing PKL terlebih dahulu.
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
    return;
}

// Validasi akses kelas
$qAkses = $conn->query("SELECT k.nama_kelas, COUNT(s.id) AS total_siswa
                        FROM data_pkl p
                        JOIN siswa s ON p.id_siswa = s.id
                        JOIN kelas k ON s.id_kelas = k.id
                        WHERE p.id_guru_pembimbing = '$id_guru_login'
                        AND p.id_tahun_ajaran = '$id_ta_aktif_pkl_safe'
                        AND s.id_kelas = '$id_kelas_safe'
                        GROUP BY k.id, k.nama_kelas
                        LIMIT 1");

if(!$qAkses || $qAkses->num_rows == 0){
    echo "<div class='card p-5 text-center border-0 shadow-sm rounded-4 mt-4'>
            <i class='fas fa-lock fa-4x mb-3 text-danger'></i>
            <h4 class='fw-bold'>Akses Ditolak</h4>
            <p class='text-muted'>Anda bukan guru pembimbing PKL untuk kelas ini.</p>
            <a href='?page=input_pkl' class='btn btn-secondary rounded-pill px-4 fw-bold'>Kembali</a>
          </div>";
    return;
}

$nama_kelas = $qAkses->fetch_assoc()['nama_kelas'];

$qSiswa = $conn->query("SELECT s.id, s.nama_siswa, s.nisn,
                               p.mitra_dudi, p.lokasi, p.tanggal_mulai, p.tanggal_selesai, p.nama_instruktur,
                               p.lamanya, p.nilai_pkl, p.keterangan,
                               p.tp1_skor, p.tp1_deskripsi,
                               p.tp2_skor, p.tp2_deskripsi,
                               p.tp3_skor, p.tp3_deskripsi,
                               p.tp4_skor, p.tp4_deskripsi,
                               p.catatan_pkl,
                               p.status_validasi_pkl
                        FROM data_pkl p
                        JOIN siswa s ON p.id_siswa = s.id
                        WHERE p.id_guru_pembimbing = '$id_guru_login'
                        AND p.id_tahun_ajaran = '$id_ta_aktif_pkl_safe'
                        AND s.id_kelas = '$id_kelas_safe'
                        ORDER BY s.nama_siswa ASC");

$daftar_siswa_pkl = [];
if($qSiswa && $qSiswa->num_rows > 0){
    while($row = $qSiswa->fetch_assoc()){
        $daftar_siswa_pkl[] = $row;
    }
}
?>

<style>
    .pkl-header { background:#BFD3EA; color:#0d385e; border:1px solid #AFC6E0; border-radius:22px; padding:26px 30px; box-shadow:0 8px 22px rgba(80,115,155,.14); }

    .btn-pkl-soft { background:#D8E9FA!important; color:#0d385e!important; border:1px solid #AFC6E0!important; border-radius:50px!important; font-weight:800!important; }

    .btn-pkl-soft:hover { background:#BFD3EA!important; color:#0d385e!important; }

    .btn-back-soft { background:#D8E9FA!important; color:#0d385e!important; border:1px solid #AFC6E0!important; border-radius:50px!important; font-weight:800!important; }

    .btn-back-soft:hover { background:#BFD3EA!important; color:#0d385e!important; }

    .alert-pkl-blue { background:#EEF6FF; color:#0d385e; border:1px solid #AFC6E0; border-radius:16px; padding:14px 18px; box-shadow:0 5px 12px rgba(80,115,155,.08); font-weight:500; }

    .pkl-student-card {
        display:none;
        border:none;
        border-radius:20px;
        box-shadow:0 8px 22px rgba(80,115,155,.10);
        overflow:hidden;
        margin-bottom:16px;
        background:#fff;
    }

    .pkl-student-card.open {
        display:block;
    }

    .pkl-student-head {
        background:#f8fbff;
        border-bottom:1px solid #e5eef8;
        padding:16px 20px;
    }

    .pkl-table th { background:#EEF6FF; color:#0d385e; font-size:12px; text-transform:uppercase; text-align:center; vertical-align:middle; }

    .pkl-table td { vertical-align:top; }

    .pkl-tujuan {
        font-size:13px;
        line-height:1.45;
        color:#1f2937;
        font-weight:600;
    }

    .input-skor-pkl {
        max-width:90px;
        margin:auto;
        text-align:center;
        font-weight:800;
    }

    .pkl-list-card {
        border:none;
        border-radius:20px;
        box-shadow:0 8px 22px rgba(80,115,155,.10);
        background:#fff;
        overflow:hidden;
    }

    .pkl-list-head {
        background:#f8fbff;
        border-bottom:1px solid #e5eef8;
        padding:16px 20px;
    }

    .pkl-name-btn {
        border:1px solid #dce8f5;
        background:#ffffff;
        color:#0d385e;
        border-radius:16px;
        padding:12px 14px;
        width:100%;
        text-align:left;
        font-weight:800;
        transition:.15s;
        box-shadow:0 4px 12px rgba(80,115,155,.06);
    }

    .pkl-name-btn:hover { background:#EEF6FF; border-color:#AFC6E0; }

    .pkl-name-btn.active { background:#D8E9FA; border-color:#AFC6E0; color:#0d385e; }

    .pkl-form-body {
        display:block;
        padding:16px;
        border-top:1px solid #e5eef8;
    }

    .pkl-student-card.open .btn-toggle-form { background:#BFD3EA!important; color:#0d385e!important; border-color:#AFC6E0!important; }

    .sticky-save-pkl {
        position:sticky;
        bottom:0;
        background:rgba(248,250,252,.95);
        backdrop-filter: blur(8px);
        z-index:10;
        border-top:1px solid #e5eef8;
    }
</style>

<div class="pkl-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-2"><i class="fas fa-briefcase me-2"></i>Input Data & Nilai PKL</h4>
            <p class="mb-0 fw-semibold">Guru Pembimbing: <?= htmlspecialchars($nama_guru_login) ?> | Kelas <?= htmlspecialchars($nama_kelas) ?></p>
            <small>Guru pembimbing menginput data/nilai PKL. Validasi tetap oleh wali kelas.</small>
        </div>
        <a href="?page=input_pkl" class="btn btn-back-soft btn-sm px-4"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<?php if(count($daftar_siswa_pkl) > 0): ?>
    <div class="pkl-list-card mb-4">
        <div class="pkl-list-head">
            <h5 class="fw-bold mb-1" style="color:#0d385e;">
                <i class="fas fa-users me-2"></i>Pilih Siswa PKL
            </h5>
            <small class="text-muted">Klik nama siswa untuk membuka form input. Jadi tidak perlu scroll panjang dari awal.</small>
        </div>

        <div class="p-3">
            <div class="row g-3">
                <?php foreach($daftar_siswa_pkl as $idx => $s): ?>
                    <?php
                        $nilai_akhir_list = $s['nilai_pkl'] ?? '';
                        $valid_pkl_list = (int)($s['status_validasi_pkl'] ?? 0);
                        $jumlah_tp_list = 0;
                        foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_tp_list){
                            if($s[$field_tp_list] !== null && $s[$field_tp_list] !== ''){
                                $jumlah_tp_list++;
                            }
                        }
                    ?>
                    <div class="col-md-4">
                        <button type="button" class="pkl-name-btn" data-target="form-pkl-<?= $s['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div><?= strtoupper(htmlspecialchars($s['nama_siswa'])) ?></div>
                                    <small class="text-muted fw-normal">NISN: <?= htmlspecialchars($s['nisn'] ?? '-') ?></small>
                                </div>
                                <span class="badge rounded-pill <?= $valid_pkl_list ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                    <?= $valid_pkl_list ? 'Valid' : $jumlah_tp_list.'/4' ?>
                                </span>
                            </div>
                            <div class="mt-2 small text-muted fw-normal">
                                Nilai Akhir: <?= ($nilai_akhir_list !== '' && $nilai_akhir_list !== null) ? htmlspecialchars($nilai_akhir_list) : '-' ?>
                            </div>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if(count($daftar_siswa_pkl) > 0): ?>
    <div id="pklPlaceholder" class="alert-pkl-blue mb-4">
        Pilih salah satu nama siswa pada kotak di atas untuk menampilkan form input PKL.
    </div>
<?php endif; ?>

<form method="POST">
    <?php if(count($daftar_siswa_pkl) > 0): ?>
        <?php foreach($daftar_siswa_pkl as $idx => $s): ?>
            <?php
                $nilai_akhir = $s['nilai_pkl'] ?? '';
                $valid_pkl = (int)($s['status_validasi_pkl'] ?? 0);
                $jumlah_tp = 0;
                foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_tp){
                    if($s[$field_tp] !== null && $s[$field_tp] !== ''){
                        $jumlah_tp++;
                    }
                }
            ?>

            <div class="pkl-student-card" id="form-pkl-<?= $s['id'] ?>">
                <div class="pkl-student-head">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold mb-1"><?= strtoupper(htmlspecialchars($s['nama_siswa'])) ?></h5>
                            <small class="text-muted">NISN: <?= htmlspecialchars($s['nisn'] ?? '-') ?> | TP terisi: <?= $jumlah_tp ?>/4</small>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                                Nilai Akhir: <?= ($nilai_akhir !== '' && $nilai_akhir !== null) ? htmlspecialchars($nilai_akhir) : '-' ?>
                            </span>

                            <span class="badge <?= $valid_pkl ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?> rounded-pill px-3 py-2">
                                <?= $valid_pkl ? 'Sudah Divalidasi Wali Kelas' : 'Menunggu Validasi Wali Kelas' ?>
                            </span>

                            <button type="button" class="btn btn-sm btn-back-soft btn-toggle-form" data-target="form-pkl-<?= $s['id'] ?>">
                                <i class="fas fa-chevron-down me-1"></i> Buka Form
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pkl-form-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Tempat PKL</label>
                            <input type="text" name="pkl[<?= $s['id'] ?>][mitra]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['mitra_dudi'] ?? '') ?>" placeholder="Nama tempat PKL">
                        </div>

                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Lokasi</label>
                            <input type="text" name="pkl[<?= $s['id'] ?>][lokasi]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['lokasi'] ?? '') ?>" placeholder="Alamat/Kota">
                        </div>

                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Nama Instruktur</label>
                            <input type="text" name="pkl[<?= $s['id'] ?>][nama_instruktur]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['nama_instruktur'] ?? '') ?>" placeholder="Nama instruktur di tempat PKL">
                        </div>

                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">Tanggal Mulai</label>
                            <input type="date" name="pkl[<?= $s['id'] ?>][tanggal_mulai]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['tanggal_mulai'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">Tanggal Selesai</label>
                            <input type="date" name="pkl[<?= $s['id'] ?>][tanggal_selesai]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['tanggal_selesai'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">Lamanya</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="pkl[<?= $s['id'] ?>][lamanya]" class="form-control text-center" value="<?= htmlspecialchars($s['lamanya'] ?? '6') ?>">
                                <span class="input-group-text bg-light text-muted">Bulan</span>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="small fw-bold text-muted">Keterangan Umum</label>
                            <input type="text" name="pkl[<?= $s['id'] ?>][keterangan]" class="form-control form-control-sm" value="<?= htmlspecialchars($s['keterangan'] ?? '') ?>" placeholder="Contoh: Sangat Baik">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered pkl-table mb-3">
                            <thead>
                                <tr>
                                    <th width="38%">Tujuan Pembelajaran</th>
                                    <th width="12%">Skor</th>
                                    <th width="50%">Deskripsi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php for($i=1; $i<=4; $i++):
                                    $field_skor = "tp{$i}_skor";
                                    $field_deskripsi = "tp{$i}_deskripsi";
                                    $isi_desc = !empty($s[$field_deskripsi]) ? $s[$field_deskripsi] : $default_deskripsi_pkl[$i];
                                ?>
                                    <tr>
                                        <td><div class="pkl-tujuan"><?= $i ?>. <?= htmlspecialchars($default_tp[$i]) ?></div></td>

                                        <td class="text-center">
                                            <input type="number" min="0" max="100" name="pkl[<?= $s['id'] ?>][tp<?= $i ?>_skor]" class="form-control form-control-sm input-skor-pkl" value="<?= htmlspecialchars($s[$field_skor] ?? '') ?>" placeholder="0-100">
                                        </td>

                                        <td>
                                            <textarea name="pkl[<?= $s['id'] ?>][tp<?= $i ?>_deskripsi]" class="form-control form-control-sm textarea-deskripsi-pkl" rows="3" data-template="<?= htmlspecialchars($default_deskripsi_pkl[$i]) ?>"><?= htmlspecialchars($isi_desc) ?></textarea>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3 text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-template-deskripsi">
                            <i class="fas fa-magic me-1"></i> Isi Ulang Template Deskripsi
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="small fw-bold text-muted">Catatan</label>
                            <textarea name="pkl[<?= $s['id'] ?>][catatan_pkl]" class="form-control form-control-sm" rows="3" placeholder="Catatan PKL siswa"><?= htmlspecialchars($s['catatan_pkl'] ?? '') ?></textarea>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i> Ketidakhadiran siswa sekarang diambil otomatis dari menu <b>Input Absensi Siswa</b> (diisi admin/wali kelas), tidak lagi diinput di sini.
                            </small>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-close-form" data-target="form-pkl-<?= $s['id'] ?>">
                            Tutup Form
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4">Belum ada siswa bimbingan PKL pada kelas ini.</div>
    <?php endif; ?>

    <?php if(count($daftar_siswa_pkl) > 0): ?>
        <div class="text-end sticky-save-pkl py-3">
            <button type="submit" name="simpan_pkl" class="btn btn-pkl-soft px-5 rounded-pill shadow-sm">
                <i class="fas fa-save me-2"></i> Simpan Semua Data & Nilai PKL
            </button>
        </div>
    <?php endif; ?>
</form>

<script>
document.addEventListener('click', function(e){
    const templateBtn = e.target.closest('.btn-template-deskripsi');
    if(templateBtn){
        const card = templateBtn.closest('.pkl-student-card');
        if(!card) return;

        card.querySelectorAll('.textarea-deskripsi-pkl').forEach(function(textarea){
            textarea.value = textarea.getAttribute('data-template') || '';
        });
        return;
    }

    const targetBtn = e.target.closest('.pkl-name-btn, .btn-toggle-form');
    if(targetBtn){
        const targetId = targetBtn.getAttribute('data-target');
        const card = document.getElementById(targetId);
        if(!card) return;

        document.querySelectorAll('.pkl-student-card').forEach(function(item){
            if(item !== card) item.classList.remove('open');
        });

        document.querySelectorAll('.pkl-name-btn').forEach(function(item){
            item.classList.remove('active');
        });

        card.classList.add('open');

        const placeholder = document.getElementById('pklPlaceholder');
        if(placeholder) placeholder.style.display = 'none';

        const listBtn = document.querySelector('.pkl-name-btn[data-target="'+targetId+'"]');
        if(listBtn) listBtn.classList.add('active');

        setTimeout(function(){
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
        return;
    }

    const closeBtn = e.target.closest('.btn-close-form');
    if(closeBtn){
        const targetId = closeBtn.getAttribute('data-target');
        const card = document.getElementById(targetId);
        if(card) card.classList.remove('open');

        const listBtn = document.querySelector('.pkl-name-btn[data-target="'+targetId+'"]');
        if(listBtn) listBtn.classList.remove('active');

        const masihOpen = document.querySelector('.pkl-student-card.open');
        const placeholder = document.getElementById('pklPlaceholder');
        if(!masihOpen && placeholder) placeholder.style.display = 'block';

        return;
    }
});
</script>