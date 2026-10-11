<?php
if(!isset($conn) || !$conn){
    if(!class_exists('Database')){
        require_once '../config/Database.php';
    }
    $db = new Database();
    $conn = $db->getConnection();
}

$id_guru_login = $_SESSION['guru']['id'] ?? ($_SESSION['id_guru'] ?? ($_SESSION['id'] ?? 0));
$nip_guru_login = $_SESSION['guru']['nip'] ?? ($_SESSION['nip'] ?? '');

if((empty($id_guru_login) || $id_guru_login == 0) && !empty($nip_guru_login)){
    $nip_safe = mysqli_real_escape_string($conn, $nip_guru_login);
    $qGuruLogin = $conn->query("SELECT id FROM guru WHERE nip='$nip_safe' LIMIT 1");
    if($qGuruLogin && $qGuruLogin->num_rows > 0){
        $id_guru_login = (int)$qGuruLogin->fetch_assoc()['id'];
    }
}

$id_kelas = $_GET['id_kelas'] ?? '';
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

// Tahun ajaran aktif, dipakai untuk cek status wali kelas lewat riwayat_wali_kelas
// DAN untuk filter data_pkl (FIX: sebelumnya data_pkl gak difilter tahun ajaran sama sekali,
// jadi data PKL dari tahun ajaran yang sudah gak aktif ikut kebawa terus).
$qTahunAktifPkl = $conn->query("SELECT id, tahun FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$row_ta_aktif_pkl = ($qTahunAktifPkl && $qTahunAktifPkl->num_rows > 0) ? $qTahunAktifPkl->fetch_assoc() : null;
$tahun_aktif_pkl = $row_ta_aktif_pkl['tahun'] ?? '';
$id_ta_aktif_pkl = $row_ta_aktif_pkl['id'] ?? 0;
$tahun_aktif_pkl_esc = mysqli_real_escape_string($conn, $tahun_aktif_pkl);
$id_ta_aktif_pkl_esc = mysqli_real_escape_string($conn, $id_ta_aktif_pkl);

if(empty($id_ta_aktif_pkl)){
    echo "<div class='alert alert-warning border-0 shadow-sm rounded-4'>Belum ada Tahun Ajaran yang berstatus Aktif.</div>";
    return;
}

if(empty($id_kelas)){
    $qKelas = $conn->query("SELECT DISTINCT k.id, k.nama_kelas, COUNT(s.id) AS total_pkl
                            FROM data_pkl p
                            JOIN siswa s ON p.id_siswa = s.id
                            JOIN kelas k ON s.id_kelas = k.id
                            LEFT JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id AND rwk.tahun = '$tahun_aktif_pkl_esc'
                            WHERE p.id_tahun_ajaran = '$id_ta_aktif_pkl_esc'
                            AND (p.id_guru_pembimbing = '$id_guru_login'
                               OR rwk.id_guru = '$id_guru_login')
                            GROUP BY k.id, k.nama_kelas
                            ORDER BY k.nama_kelas ASC");
?>
<style>
    .pkl-banner { background:#BFD3EA; color:#0d385e; border:1px solid #AFC6E0; border-radius:22px; padding:28px 32px; box-shadow:0 8px 22px rgba(80,115,155,.14); }
    .pkl-card { border:none; border-radius:20px; box-shadow:0 8px 22px rgba(80,115,155,.10); background:#fff; }
    .btn-soft-blue-pkl { background:#D8E9FA!important; color:#0d385e!important; border:1px solid #AFC6E0!important; border-radius:50px!important; font-weight:800!important; }

    .btn-soft-blue-pkl:hover { background:#BFD3EA!important; color:#0d385e!important; }
    .pkl-card h5 { color:#0d385e!important; }
    .rekap-pkl-card .card-header { background:#EEF6FF!important; color:#0d385e!important; border-bottom:1px solid #AFC6E0!important; }
    .rekap-pkl-table thead { background:#EEF6FF!important; color:#0d385e!important; }
    .rekap-pkl-table thead th { color:#0d385e!important; }
</style>
<div class="pkl-banner mb-4">
    <h3 class="fw-bold mb-2"><i class="fas fa-table-list me-2"></i>Rekap Nilai PKL</h3>
    <p class="mb-0 fw-semibold">Rekap berdasarkan siswa PKL yang ditugaskan kepada guru pembimbing atau kelas wali.</p>
</div>

<div class="row g-3">
    <?php if($qKelas && $qKelas->num_rows > 0): ?>
        <?php while($k = $qKelas->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="card pkl-card h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-2" style="color:#0d385e;"><?= htmlspecialchars($k['nama_kelas']) ?></h5>
                        <p class="text-muted small mb-3"><?= (int)$k['total_pkl'] ?> data PKL</p>
                        <a href="?page=rekap_pkl&id_kelas=<?= $k['id'] ?>" class="btn btn-soft-blue-pkl btn-sm px-4">
                            <i class="fas fa-eye me-1"></i> Lihat Rekap
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12"><div class="alert alert-warning border-0 shadow-sm rounded-4">Belum ada data PKL untuk akun ini.</div></div>
    <?php endif; ?>
</div>
<?php return; }

$qKelasInfo = $conn->query("SELECT nama_kelas FROM kelas WHERE id='$id_kelas_safe' LIMIT 1");
$nama_kelas = ($qKelasInfo && $qKelasInfo->num_rows > 0) ? $qKelasInfo->fetch_assoc()['nama_kelas'] : '-';

$qRekap = $conn->query("SELECT s.nama_siswa, s.nisn, p.*, g.nama_guru AS nama_pembimbing
                        FROM data_pkl p
                        JOIN siswa s ON p.id_siswa = s.id
                        JOIN kelas k ON s.id_kelas = k.id
                        LEFT JOIN guru g ON p.id_guru_pembimbing = g.id
                        LEFT JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id AND rwk.tahun = '$tahun_aktif_pkl_esc'
                        WHERE s.id_kelas = '$id_kelas_safe'
                        AND p.id_tahun_ajaran = '$id_ta_aktif_pkl_esc'
                        AND (p.id_guru_pembimbing = '$id_guru_login' OR rwk.id_guru = '$id_guru_login')
                        ORDER BY s.nama_siswa ASC");
?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white rekap-pkl-card">
    <div class="card-header bg-white border-0 p-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Rekap Nilai PKL <?= htmlspecialchars($nama_kelas) ?></h5>
        <a href="?page=rekap_pkl" class="btn btn-sm btn-soft-blue-pkl px-4">Kembali</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center rekap-pkl-table">
            <thead class="bg-light small fw-bold text-muted text-uppercase">
                <tr>
                    <th>No</th>
                    <th class="text-start">Nama Siswa</th>
                    <th>Tempat PKL</th>
                    <th>Guru Pembimbing</th>
                    <th>TP1</th>
                    <th>TP2</th>
                    <th>TP3</th>
                    <th>TP4</th>
                    <th>Nilai PKL</th>
                    <th>Status Validasi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; if($qRekap && $qRekap->num_rows > 0): ?>
                    <?php while($r = $qRekap->fetch_assoc()): ?>
                        <?php
                        $skor = [];
                        foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $f){
                            if($r[$f] !== null && $r[$f] !== '') $skor[] = (int)$r[$f];
                        }
                        $nilai = !empty($r['nilai_pkl']) ? (int)$r['nilai_pkl'] : (!empty($skor) ? round(array_sum($skor)/count($skor)) : '-');
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="text-start fw-bold"><?= strtoupper(htmlspecialchars($r['nama_siswa'])) ?><br><small class="text-muted">NISN: <?= htmlspecialchars($r['nisn'] ?? '-') ?></small></td>
                            <td class="text-start"><?= htmlspecialchars($r['mitra_dudi'] ?: '-') ?><br><small class="text-muted"><?= htmlspecialchars($r['lokasi'] ?: '-') ?></small></td>
                            <td><?= htmlspecialchars($r['nama_pembimbing'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($r['tp1_skor'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['tp2_skor'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['tp3_skor'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['tp4_skor'] ?? '-') ?></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($nilai) ?></td>
                            <td>
                                <?php if((int)($r['status_validasi_pkl'] ?? 0) == 1): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">Valid</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data PKL.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>