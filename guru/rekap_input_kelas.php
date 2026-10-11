<?php
$id_kelas = $_GET['id_kelas'] ?? '';
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

// --- AMBIL TAHUN AJARAN AKTIF ---
// Sama seperti input_nilai.php: semua baca ke `nilai`, `guru_mengajar`, dan
// keanggotaan siswa di kelas WAJIB disaring/ditandai id_tahun_ajaran ini,
// supaya data antar tahun ajaran tidak tercampur/dobel.
$qTahunAktifRk = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahunAktifRk = ($qTahunAktifRk && $qTahunAktifRk->num_rows > 0) ? $qTahunAktifRk->fetch_assoc() : null;
$id_tahun_aktif_rk = (int)($dTahunAktifRk['id'] ?? 0);

if($id_tahun_aktif_rk <= 0){
    echo "<div class='alert alert-danger mt-4 text-center fw-bold shadow-sm rounded-4'>
            Tahun Ajaran Aktif belum diset oleh Admin. Rekap tidak dapat ditampilkan.
          </div>";
    return;
}

$id_tahun_aktif_rk_safe = mysqli_real_escape_string($conn, $id_tahun_aktif_rk);

// --- SINKRONISASI DATA WALI KELAS ---
$nip_guru = $_SESSION['guru']['nip'] ?? '';
$nip_guru_safe = mysqli_real_escape_string($conn, $nip_guru);

$qG = $conn->query("SELECT id, nama_guru FROM guru WHERE nip = '$nip_guru_safe' LIMIT 1");
$dG = ($qG && $qG->num_rows > 0) ? $qG->fetch_assoc() : [];

$id_guru_login = $dG['id'] ?? 0;
$nama_guru_login = $dG['nama_guru'] ?? 'Wali Kelas';

// --- AMBIL DATA KELAS ---
$qS = $conn->query("SELECT id, nama_kelas, tingkat, kode_jurusan 
                    FROM kelas 
                    WHERE id = '$id_kelas_safe'
                    LIMIT 1");
$ds = ($qS && $qS->num_rows > 0) ? $qS->fetch_assoc() : [];

$nama_kelas = $ds['nama_kelas'] ?? '';
$tingkat_romawi = $ds['tingkat'] ?? explode(' ', trim($nama_kelas))[0];

// Ambil jurusan dari kolom kode_jurusan. Kalau kosong, baru baca dari nama kelas.
$jurusan_key = $ds['kode_jurusan'] ?? '';

if(empty($jurusan_key)){
    if(stripos($nama_kelas, 'RPL') !== false) $jurusan_key = 'RPL';
    elseif(stripos($nama_kelas, 'AKL') !== false) $jurusan_key = 'AKL';
    elseif(stripos($nama_kelas, 'AKT') !== false) $jurusan_key = 'AKT';
    elseif(stripos($nama_kelas, 'BR') !== false) $jurusan_key = 'BR';
    elseif(stripos($nama_kelas, 'MP') !== false) $jurusan_key = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_key);

// --- PROSES PING MASSAL GURU MAPEL ---
if(isset($_POST['ping_massal'])){
    $id_penerima = mysqli_real_escape_string($conn, $_POST['id_guru'] ?? '');
    $pesan = $conn->real_escape_string($_POST['pesan_custom'] ?? '');

    $conn->query("INSERT INTO notifikasi (pengirim_role, pengirim_id, nama_pengirim, penerima_role, penerima_id, pesan, link_tujuan) 
                  VALUES ('guru', '$id_guru_login', '$nama_guru_login (Wali Kelas)', 'guru', '$id_penerima', '$pesan', '?page=input_nilai')");

    echo "<script>alert('Peringatan massal berhasil dikirim ke Guru Mapel!'); window.location='?page=rekap_input_kelas&id_kelas=$id_kelas_safe';</script>";
}

// --- LOGIKA RINGKASAN PER MAPEL ---
// FIX SINKRON:
// Rekap wali kelas harus sama dengan Data Mapel admin.
// Untuk kelas X RPL, yang tampil hanya Muatan A + mapel jurusan RPL.
// Mapel jurusan lain seperti AKL/BR/MP tidak boleh ikut tampil walaupun pernah nyangkut di guru_mengajar.
$qRingkasan = $conn->query("SELECT 
                                m.id, 
                                m.nama_mapel, 
                                m.kelompok,
                                m.kode_jurusan,
                                gm.id_guru, 
                                g.nama_guru,
                                (SELECT COUNT(DISTINCT rk.id_siswa) 
                                 FROM riwayat_kelas rk 
                                 WHERE rk.id_kelas = '$id_kelas_safe'
                                 AND rk.id_tahun_ajaran = '$id_tahun_aktif_rk_safe') AS total_siswa,
                                (SELECT COUNT(DISTINCT n.id_siswa) 
                                 FROM nilai n 
                                 JOIN riwayat_kelas rk ON n.id_siswa = rk.id_siswa 
                                 WHERE rk.id_kelas = '$id_kelas_safe' 
                                 AND rk.id_tahun_ajaran = '$id_tahun_aktif_rk_safe'
                                 AND n.id_mapel = m.id
                                 AND n.id_tahun_ajaran = '$id_tahun_aktif_rk_safe') AS sudah_input
                            FROM mapel m
                            LEFT JOIN guru_mengajar gm 
                                ON m.id = gm.id_mapel 
                                AND gm.id_kelas = '$id_kelas_safe'
                                AND gm.id_tahun_ajaran = '$id_tahun_aktif_rk_safe'
                            LEFT JOIN guru g 
                                ON gm.id_guru = g.id
                            WHERE m.tingkat = '$tingkat_safe'
                            AND m.nama_mapel NOT LIKE '%PKL%'
                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND m.nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                m.kelompok = 'A'
                                OR m.kode_jurusan = '$jurusan_safe'
                            )
                            ORDER BY 
                                CASE 
                                    WHEN m.kelompok = 'A' THEN 1
                                    WHEN m.kelompok = 'B' THEN 2
                                    ELSE 3
                                END ASC,
                                m.urutan ASC,
                                m.id ASC");
?>

<style>
    .rekap-banner {
        background: #BFD3EA !important;
        color: #0d385e !important;
        border-radius: 22px !important;
        box-shadow: 0 8px 22px rgba(80, 115, 155, 0.16) !important;
        border: 1px solid rgba(255,255,255,0.75) !important;
    }

    .rekap-banner h4 {
        color: #0d385e !important;
        font-weight: 800 !important;
    }

    .rekap-banner p {
        color: #244b70 !important;
        font-weight: 600 !important;
    }

    .btn-rekap-back {
        background: #EAF3FF !important;
        color: #0d385e !important;
        border: 1px solid #A9C8EA !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(100, 145, 190, 0.18) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-rekap-back:hover,
    .btn-rekap-back:focus,
    .btn-rekap-back:active {
        background: #D8E9FA !important;
        color: #0d385e !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .progress-soft {
        height: 12px;
        border-radius: 10px;
        background-color: #eef1f4;
        overflow: hidden;
    }

    .progress-bar-soft-green {
        background-color: #5E9F86 !important;
    }

    .progress-bar-soft-red {
        background-color: #E66F82 !important;
    }

    .progress-bar-soft-yellow {
        background-color: #E7B24D !important;
    }

    .rekap-status-tuntas {
        background: #B9DDCC !important;
        color: #1F5F48 !important;
        border: 1px solid #7FC1A5 !important;
        border-radius: 50px !important;
        padding: 7px 16px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }

    .rekap-status-kosong {
        background: #FFD6DE !important;
        color: #8A1E2E !important;
        border: 1px solid #F09AAD !important;
        border-radius: 50px !important;
        padding: 7px 16px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }

    .rekap-status-kurang {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #D8BC82 !important;
        border-radius: 50px !important;
        padding: 7px 16px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }

    .btn-ping-soft {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #D8BC82 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        padding: 7px 14px !important;
        box-shadow: 0 5px 12px rgba(214, 189, 145, 0.22) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-ping-soft:hover,
    .btn-ping-soft:focus,
    .btn-ping-soft:active {
        background: #EED9AF !important;
        color: #76542D !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .text-progress-green {
        color: #1F5F48 !important;
    }

    .text-progress-red {
        color: #8A1E2E !important;
    }

    .text-progress-yellow {
        color: #87653B !important;
    }
</style>

<div class="card border-0 shadow-sm p-4 mb-4 rekap-banner">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fas fa-list-check me-2"></i>Rekap Input Nilai Kelas
            </h4>
            <p class="mb-0 small">
                Kelas: <?= $nama_kelas ?> | Pantau progres pengisian nilai dari seluruh Guru Mapel.
            </p>
        </div>

        <a href="?page=wali_siswa&id_kelas=<?= $id_kelas ?>" class="btn btn-rekap-back btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Siswa
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle text-center">
            <thead class="bg-light small fw-bold text-muted">
                <tr>
                    <th width="5%">NO</th>
                    <th width="35%" class="text-start">MATA PELAJARAN</th>
                    <th width="20%">PROGRES INPUT</th>
                    <th width="20%">STATUS</th>
                    <th width="20%">AKSI PING</th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $no = 1;
                while($row = $qRingkasan->fetch_assoc()): 
                    $total = $row['total_siswa'];
                    $sudah = $row['sudah_input'];
                    $belum = $total - $sudah;
                    
                    $persen = ($total > 0) ? round(($sudah / $total) * 100) : 0;
                    
                    $warna_bar = "progress-bar-soft-green";
                    $warna_text = "text-progress-green";

                    if($persen < 50) {
                        $warna_bar = "progress-bar-soft-red";
                        $warna_text = "text-progress-red";
                    } elseif($persen < 100) {
                        $warna_bar = "progress-bar-soft-yellow";
                        $warna_text = "text-progress-yellow";
                    }
                    
                    $mapel_js = addslashes($row['nama_mapel']);
                    $kelas_js = addslashes($nama_kelas);
                ?>
                <tr>
                    <td class="text-muted small"><?= $no++ ?></td>

                    <td class="text-start fw-bold">
                        <?= $row['nama_mapel'] ?><br>
                        <small class="text-muted fw-normal"><?= $row['nama_guru'] ?? 'Guru Kosong / Belum Diset' ?></small>
                    </td>

                    <td>
                        <div class="progress mb-1 shadow-sm progress-soft">
                            <div class="progress-bar <?= $warna_bar ?> progress-bar-striped progress-bar-animated" style="width: <?= $persen ?>%"></div>
                        </div>
                        <small class="fw-bold <?= $warna_text ?>"><?= $sudah ?> / <?= $total ?> Siswa</small>
                    </td>

                    <td>
                        <?php if($persen == 100): ?>
                            <span class="rekap-status-tuntas">
                                <i class="fas fa-check-circle"></i> Tuntas
                            </span>
                        <?php elseif($sudah == 0): ?>
                            <span class="rekap-status-kosong">
                                Kosong
                            </span>
                        <?php else: ?>
                            <span class="rekap-status-kurang">
                                Kurang <?= $belum ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php 
                        if($persen < 100 && !empty($row['id_guru']) && $row['id_guru'] != $id_guru_login): 
                        ?>
                            <form method="POST" class="m-0" id="formPingMassal_<?= $row['id'] ?>">
                                <input type="hidden" name="id_guru" value="<?= $row['id_guru'] ?>">
                                <input type="hidden" name="ping_massal" value="1">
                                <input type="hidden" name="pesan_custom" id="pesan_massal_<?= $row['id'] ?>" value="">
                                
                                <button type="button" class="btn btn-ping-soft" onclick="kirimPingMassal('<?= $row['id'] ?>', '<?= $mapel_js ?>', '<?= $kelas_js ?>', <?= $belum ?>)">
                                    <i class="fas fa-bell"></i> Peringatkan Guru
                                </button>
                            </form>
                        <?php elseif(empty($row['id_guru'])): ?>
                            <small class="text-danger">Guru belum diset</small>
                        <?php else: ?>
                            <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function kirimPingMassal(id_m, mapel, kelas, jml_kurang) {
    let template = `Bapak/Ibu, progres nilai mapel ${mapel} di kelas ${kelas} masih ada ${jml_kurang} siswa yang kosong/belum tuntas. Mohon segera diselesaikan ya.`;
    let pesan = prompt("Kirim notifikasi massal ke Guru Mapel:", template);
    
    if (pesan != null && pesan.trim() !== "") {
        document.getElementById('pesan_massal_' + id_m).value = pesan;
        document.getElementById('formPingMassal_' + id_m).submit();
    }
}
</script>