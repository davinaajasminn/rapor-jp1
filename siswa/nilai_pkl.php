<?php
// File ini di-include di siswa/index.php.
// Variabel yang biasanya sudah ada dari index.php: $conn, $id_siswa_asli, $id_kelas_siswa, $nama_siswa_login.
// Kalau siswa login itu alumni ($is_alumni_siswa dari index.php), $id_kelas_siswa (siswa.id_kelas) sudah NULL
// permanen sejak lulus -- jadi buat nentuin tingkat/kelas di halaman ini, alumni WAJIB pakai kelas TERAKHIR
// mereka sebelum lulus ($id_kelas_terakhir_alumni), bukan $id_kelas_siswa yang sekarang kosong.
$id_kelas_utk_pkl = (!empty($is_alumni_siswa)) ? ($id_kelas_terakhir_alumni ?? 0) : ($id_kelas_siswa ?? '');

$id_siswa_asli_safe = mysqli_real_escape_string($conn, $id_siswa_asli ?? '');
$id_kelas_siswa_safe = mysqli_real_escape_string($conn, $id_kelas_utk_pkl);

if(!function_exists('kolom_ada_nilai_pkl_siswa')){
    function kolom_ada_nilai_pkl_siswa($conn, $table, $column){
        $table_safe = mysqli_real_escape_string($conn, $table);
        $column_safe = mysqli_real_escape_string($conn, $column);
        $q = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
        return ($q && $q->num_rows > 0);
    }
}

if(!function_exists('nilai_pkl_field_siswa')){
    function nilai_pkl_field_siswa($row, $candidates, $default=''){
        if(!$row) return $default;

        foreach($candidates as $c){
            if(isset($row[$c]) && $row[$c] !== null && $row[$c] !== ''){
                return $row[$c];
            }
        }

        return $default;
    }
}

if(!function_exists('format_tanggal_pkl_siswa')){
    function format_tanggal_pkl_siswa($tgl){
        $tgl = trim((string)$tgl);
        if($tgl == '' || $tgl == '0000-00-00') return '-';

        $time = strtotime($tgl);
        if(!$time) return $tgl;

        return date('d/m/Y', $time);
    }
}

// Ambil info kelas siswa
$qKlsPKL = $conn->query("SELECT tingkat, kode_jurusan, nama_kelas FROM kelas WHERE id='$id_kelas_siswa_safe' LIMIT 1");
$dKlsPKL = ($qKlsPKL && $qKlsPKL->num_rows > 0) ? $qKlsPKL->fetch_assoc() : [];

$tingkat_siswa_pkl = $dKlsPKL['tingkat'] ?? '';
$nama_kelas_pkl = $dKlsPKL['nama_kelas'] ?? '';

if(empty($tingkat_siswa_pkl) && !empty($nama_kelas_pkl)){
    $tingkat_siswa_pkl = explode(' ', trim($nama_kelas_pkl))[0] ?? '';
}

$tingkat_siswa_pkl = strtoupper(trim($tingkat_siswa_pkl));

// Cek tabel data_pkl
$ada_tabel_pkl = false;
$qCekTabelPKL = $conn->query("SHOW TABLES LIKE 'data_pkl'");
if($qCekTabelPKL && $qCekTabelPKL->num_rows > 0){
    $ada_tabel_pkl = true;
}

$data_pkl_siswa = null;
$nama_guru_pembimbing_pkl = '-';

if($ada_tabel_pkl){
    // Coba ambil data PKL untuk tahun ajaran yang lagi Aktif dulu.
    $qTaPKL = $conn->query("SELECT id FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
    $id_ta_pkl = ($qTaPKL && $qTaPKL->num_rows > 0) ? (int)$qTaPKL->fetch_assoc()['id'] : 0;
    $id_ta_pkl_safe = mysqli_real_escape_string($conn, $id_ta_pkl);

    $qPKL = null;
    if($id_ta_pkl > 0){
        $qPKL = $conn->query("SELECT * FROM data_pkl WHERE id_siswa='$id_siswa_asli_safe' AND id_tahun_ajaran='$id_ta_pkl_safe' LIMIT 1");
    }

    // Fallback: kalau belum ada data PKL untuk tahun ajaran aktif (misal data lama yang belum
    // ditandai tahun ajarannya), tetap tampilkan data PKL siswa yang paling baru biar gak hilang.
    if(!$qPKL || $qPKL->num_rows == 0){
        $qPKL = $conn->query("SELECT * FROM data_pkl WHERE id_siswa='$id_siswa_asli_safe' ORDER BY id DESC LIMIT 1");
    }

    if($qPKL && $qPKL->num_rows > 0){
        $data_pkl_siswa = $qPKL->fetch_assoc();

        $id_guru_pkl = nilai_pkl_field_siswa($data_pkl_siswa, ['id_guru_pembimbing','id_guru','guru_pembimbing','id_pembimbing'], '');

        if(!empty($id_guru_pkl) && is_numeric($id_guru_pkl)){
            $qGuruPKL = $conn->query("SELECT nama_guru FROM guru WHERE id='".(int)$id_guru_pkl."' LIMIT 1");
            if($qGuruPKL && $qGuruPKL->num_rows > 0){
                $nama_guru_pembimbing_pkl = $qGuruPKL->fetch_assoc()['nama_guru'];
            }
        } else {
            $nama_guru_pembimbing_pkl = nilai_pkl_field_siswa($data_pkl_siswa, ['nama_guru_pembimbing','guru_pembimbing','pembimbing_sekolah'], '-');
        }
    }
}

$tp1_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp1_skor','tp1','skor_tp1'], '') : '';
$tp2_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp2_skor','tp2','skor_tp2'], '') : '';
$tp3_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp3_skor','tp3','skor_tp3'], '') : '';
$tp4_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp4_skor','tp4','skor_tp4'], '') : '';

$skor_pkl_arr = [];
foreach([$tp1_pkl, $tp2_pkl, $tp3_pkl, $tp4_pkl] as $v_pkl){
    if($v_pkl !== '' && is_numeric($v_pkl)){
        $skor_pkl_arr[] = (float)$v_pkl;
    }
}

$nilai_akhir_pkl = '-';
if(count($skor_pkl_arr) > 0){
    $nilai_akhir_pkl = round(array_sum($skor_pkl_arr) / count($skor_pkl_arr));
}

$status_validasi_pkl = $data_pkl_siswa ? (int)nilai_pkl_field_siswa($data_pkl_siswa, ['status_validasi_pkl','validasi_pkl','status_validasi'], 0) : 0;

$deskripsi_tp1 = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp1_deskripsi','deskripsi_tp1','desc_tp1'], 'Mampu menerapkan soft skills yang dibutuhkan dalam dunia kerja.') : '';
$deskripsi_tp2 = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp2_deskripsi','deskripsi_tp2','desc_tp2'], 'Mampu menerapkan norma, POS, dan K3LH selama kegiatan PKL.') : '';
$deskripsi_tp3 = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp3_deskripsi','deskripsi_tp3','desc_tp3'], 'Mampu menerapkan kompetensi teknis yang dipelajari di dunia kerja.') : '';
$deskripsi_tp4 = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tp4_deskripsi','deskripsi_tp4','desc_tp4'], 'Mampu memahami alur bisnis dunia kerja tempat PKL.') : '';

$tp_pkl_rows = [
    ['1. Menerapkan soft skills yang dibutuhkan dalam dunia kerja (tempat PKL)', $tp1_pkl, $deskripsi_tp1],
    ['2. Menerapkan norma, POS dan K3LH yang ada pada dunia kerja (tempat PKL)', $tp2_pkl, $deskripsi_tp2],
    ['3. Menerapkan kompetensi teknis yang sudah dipelajari di sekolah dan/atau baru dipelajari pada dunia kerja (tempat PKL)', $tp3_pkl, $deskripsi_tp3],
    ['4. Memahami alur bisnis dunia kerja tempat PKL', $tp4_pkl, $deskripsi_tp4],
];

$tempat_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tempat_pkl','mitra_dudi','mitra_du_di','nama_tempat','nama_instansi'], '-') : '-';
$lokasi_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['lokasi','alamat','alamat_pkl'], '-') : '-';
$instruktur_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['nama_instruktur','instruktur','pembimbing_dudi'], '-') : '-';

$tgl_mulai_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tanggal_mulai','tgl_mulai','mulai'], '-') : '-';
$tgl_selesai_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['tanggal_selesai','tgl_selesai','selesai'], '-') : '-';

$lama_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['lamanya','lama_pkl','durasi'], '-') : '-';
$ket_umum = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['keterangan_umum','keterangan','predikat_pkl'], '-') : '-';
$catatan_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['catatan','catatan_pkl'], 'Peserta didik telah mengikuti kegiatan PKL sesuai ketentuan yang berlaku.') : '-';

$sakit_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['sakit','absen_sakit'], '0') : '0';
$izin_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['izin','absen_izin'], '0') : '0';
$alpha_pkl = $data_pkl_siswa ? nilai_pkl_field_siswa($data_pkl_siswa, ['alpha','alpa','tanpa_keterangan'], '0') : '0';
?>

<style>
    .pkl-soft-card {
        border: none;
        border-radius: 18px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        overflow: hidden;
        background: #fff;
    }

    .pkl-header {
        background: #fff7e6;
        border-left: 5px solid #f2b84b;
        padding: 20px;
        border-radius: 12px;
    }

    .pkl-info-box {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
        background: #f8fafc;
        height: 100%;
    }

    .pkl-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-bottom: 6px;
    }

    .pkl-value {
        color: #1e293b;
        font-weight: 800;
        font-size: 16px;
    }

    .pkl-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        text-align: center;
        vertical-align: middle;
    }

    .pkl-table td {
        vertical-align: middle;
    }
</style>

<div class="pkl-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1" style="color:#7a5200;">
                <i class="fas fa-briefcase me-2"></i>Nilai Praktik Kerja Lapangan (PKL)
            </h5>
            <p class="text-muted small mb-0">
                <?= (!empty($is_alumni_siswa)) ? 'Data PKL semasa kamu masih aktif sekolah (sebelum lulus).' : 'Halaman khusus data dan nilai PKL siswa kelas XII.' ?>
            </p>
        </div>

        <?php if($tingkat_siswa_pkl == 'XII' && $data_pkl_siswa && $status_validasi_pkl == 1): ?>
            <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i> Valid</span>
        <?php elseif($tingkat_siswa_pkl == 'XII' && $data_pkl_siswa): ?>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i> Menunggu Validasi</span>
        <?php endif; ?>
    </div>
</div>

<?php if($tingkat_siswa_pkl != 'XII'): ?>
    <div class="alert alert-info rounded-4 border-0 shadow-sm">
        Menu Nilai PKL hanya tersedia untuk siswa kelas XII.
    </div>
<?php elseif(!$ada_tabel_pkl): ?>
    <div class="alert alert-warning rounded-4 border-0 shadow-sm">
        Tabel <b>data_pkl</b> belum tersedia di database.
    </div>
<?php elseif(!$data_pkl_siswa): ?>
    <div class="alert alert-info rounded-4 border-0 shadow-sm">
        Data PKL belum tersedia. Data akan muncul setelah guru pembimbing PKL menginput data dan nilai PKL.
    </div>
<?php else: ?>
    <div class="card pkl-soft-card mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="pkl-info-box">
                        <div class="pkl-label">Tempat PKL</div>
                        <div class="pkl-value"><?= $tempat_pkl ?></div>
                        <div class="small text-muted mt-1"><?= $lokasi_pkl ?></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="pkl-info-box">
                        <div class="pkl-label">Guru Pembimbing</div>
                        <div class="pkl-value"><?= $nama_guru_pembimbing_pkl ?></div>
                        <div class="small text-muted mt-1">Instruktur: <?= $instruktur_pkl ?></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="pkl-info-box">
                        <div class="pkl-label">Periode PKL</div>
                        <div class="pkl-value"><?= format_tanggal_pkl_siswa($tgl_mulai_pkl) ?> s/d <?= format_tanggal_pkl_siswa($tgl_selesai_pkl) ?></div>
                        <div class="small text-muted mt-1">Lamanya: <?= $lama_pkl ?> Bulan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card pkl-soft-card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 pkl-table">
                    <thead>
                        <tr>
                            <th width="42%">Tujuan Pembelajaran</th>
                            <th width="100">Skor</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tp_pkl_rows as $row_pkl): ?>
                            <tr>
                                <td class="fw-semibold"><?= $row_pkl[0] ?></td>
                                <td class="text-center fw-bold"><?= ($row_pkl[1] !== '') ? $row_pkl[1] : '-' ?></td>
                                <td><?= !empty($row_pkl[2]) ? $row_pkl[2] : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr style="background:#f8fafc;">
                            <td class="fw-bold">Nilai Akhir PKL</td>
                            <td class="text-center fw-bold text-primary fs-5"><?= $nilai_akhir_pkl ?></td>
                            <td><?= $ket_umum ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card pkl-soft-card h-100">
                <div class="card-body p-4">
                    <div class="pkl-label">Catatan PKL</div>
                    <div><?= $catatan_pkl ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card pkl-soft-card h-100">
                <div class="card-body p-4">
                    <div class="pkl-label mb-3">Ketidakhadiran PKL</div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Sakit</span><b><?= $sakit_pkl ?> Hari</b>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Izin</span><b><?= $izin_pkl ?> Hari</b>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Tanpa Keterangan</span><b><?= $alpha_pkl ?> Hari</b>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>