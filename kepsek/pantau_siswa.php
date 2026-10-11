<?php
$id_k = $_GET['id_kelas'] ?? '';
$return_jurusan = $_GET['jurusan'] ?? 'ALL';
$return_tingkat = $_GET['tingkat'] ?? 'ALL';
$return_cari = $_GET['cari'] ?? '';
$return_filter_url = '&jurusan=' . urlencode($return_jurusan) . '&tingkat=' . urlencode($return_tingkat) . '&cari=' . urlencode($return_cari) . '&ta=' . urlencode($id_ta_pilih);
$ta_pilih_safe = (int)$id_ta_pilih;
$jenis = $_GET['jenis'] ?? 'uas';
$jenis = ($jenis == 'uts') ? 'uts' : 'uas';
$label_jenis = strtoupper($jenis);

if(empty($id_k)){
    echo "<div class='alert alert-danger mt-4 text-center'>Pilih kelas terlebih dahulu.</div>";
    exit;
}

$id_k_safe = mysqli_real_escape_string($conn, $id_k);

$qK = $conn->query("SELECT id, nama_kelas, tingkat, kode_jurusan FROM kelas WHERE id='$id_k_safe' LIMIT 1");
$dKelasPantau = ($qK && $qK->num_rows > 0) ? $qK->fetch_assoc() : [];

$nama_kelas = $dKelasPantau['nama_kelas'] ?? '-';
$tingkat_kelas = strtoupper($dKelasPantau['tingkat'] ?? explode(' ', trim($nama_kelas))[0]);
$jurusan_kelas = $dKelasPantau['kode_jurusan'] ?? '';

if(empty($jurusan_kelas)){
    if(stripos($nama_kelas, 'RPL') !== false) $jurusan_kelas = 'RPL';
    elseif(stripos($nama_kelas, 'AKL') !== false) $jurusan_kelas = 'AKL';
    elseif(stripos($nama_kelas, 'AKT') !== false) $jurusan_kelas = 'AKT';
    elseif(stripos($nama_kelas, 'BR') !== false) $jurusan_kelas = 'BR';
    elseif(stripos($nama_kelas, 'MP') !== false) $jurusan_kelas = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_kelas);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_kelas);

// Daftar mapel sinkron dengan ledger: prioritas guru_mengajar, fallback mapel umum + jurusan.
$qCekGM = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                        FROM guru_mengajar gm
                        JOIN mapel m ON gm.id_mapel = m.id
                        WHERE gm.id_kelas = '$id_k_safe'
                        AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                        AND m.tingkat = '$tingkat_safe'
                        AND m.nama_mapel NOT LIKE '%PKL%'
                        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                        AND m.nama_mapel NOT LIKE '%Prakerin%'");
$jml_gm = ($qCekGM && $qCekGM->num_rows > 0) ? (int)$qCekGM->fetch_assoc()['total'] : 0;

if($jml_gm > 0){
    $qMapelPantau = $conn->query("SELECT DISTINCT m.id, m.nama_mapel, m.kkm
                                  FROM guru_mengajar gm
                                  JOIN mapel m ON gm.id_mapel = m.id
                                  WHERE gm.id_kelas = '$id_k_safe'
                                  AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                                  AND m.tingkat = '$tingkat_safe'
                                  AND m.nama_mapel NOT LIKE '%PKL%'
                                  AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                  AND m.nama_mapel NOT LIKE '%Prakerin%'
                                  ORDER BY 
                                      CASE WHEN m.kelompok='A' THEN 1 WHEN m.kelompok='B' THEN 2 ELSE 3 END,
                                      m.urutan ASC,
                                      m.nama_mapel ASC");
} else {
    $qMapelPantau = $conn->query("SELECT id, nama_mapel, kkm
                                  FROM mapel
                                  WHERE tingkat = '$tingkat_safe'
                                  AND nama_mapel NOT LIKE '%PKL%'
                                  AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                  AND nama_mapel NOT LIKE '%Prakerin%'
                                  AND (
                                      kelompok = 'A'
                                      OR kode_jurusan = '$jurusan_safe'
                                  )
                                  ORDER BY 
                                      CASE WHEN kelompok='A' THEN 1 WHEN kelompok='B' THEN 2 ELSE 3 END,
                                      urutan ASC,
                                      nama_mapel ASC");
}

$list_mapel_pantau = [];
if($qMapelPantau && $qMapelPantau->num_rows > 0){
    while($mp = $qMapelPantau->fetch_assoc()){
        $list_mapel_pantau[] = $mp;
    }
}
$jml_mapel_wajib = count($list_mapel_pantau);

// FIX ROSTER SISWA: ambil siswa dari riwayat_kelas sesuai TA yang dipilih, bukan siswa.id_kelas
// (posisi kelas siswa saat ini), soalnya kalau TA yang dipilih bukan TA aktif sekarang,
// siswa yang dulu di kelas ini udah pindah/naik kelas di tabel siswa yang live.
$qSiswa = $conn->query("SELECT s.* FROM siswa s
                        JOIN riwayat_kelas rk ON rk.id_siswa = s.id
                        WHERE rk.id_kelas = '$id_k_safe' AND rk.id_tahun_ajaran = '$ta_pilih_safe'
                        ORDER BY s.nama_siswa ASC");
?>

<div class="card border-0 shadow-sm p-4 mb-4" style="background: #BFD3EA !important; color: #0d385e !important; border-radius:22px; border: 1px solid rgba(255,255,255,0.75); box-shadow: 0 8px 22px rgba(80,115,155,0.14);">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#0d385e;">
                <i class="fas fa-users me-2"></i>Detail Kelas: <?= $nama_kelas ?>
            </h4>
            <p class="mb-0 small" style="color:#35597c; font-weight:500;">
                Monitoring kelengkapan nilai siswa. Pilih tab UTS atau UAS untuk melihat statusnya.
            </p>
        </div>

        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="?page=pantau_siswa&id_kelas=<?= $id_k ?>&jenis=uts<?= $return_filter_url ?>" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background:<?= ($jenis=='uts')?'#6FA7DF':'#D8E9FA' ?> !important; color:<?= ($jenis=='uts')?'#fff':'#0d385e' ?> !important; border:1px solid #AECDEE !important;">
                Detail UTS
            </a>
            <a href="?page=pantau_siswa&id_kelas=<?= $id_k ?>&jenis=uas<?= $return_filter_url ?>" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background:<?= ($jenis=='uas')?'#5E9F86':'#D8E9FA' ?> !important; color:<?= ($jenis=='uas')?'#fff':'#0d385e' ?> !important; border:1px solid #AECDEE !important;">
                Detail UAS
            </a>
            <form method="GET" class="m-0">
                <input type="hidden" name="page" value="pantau_siswa">
                <input type="hidden" name="id_kelas" value="<?= $id_k ?>">
                <input type="hidden" name="jenis" value="<?= $jenis ?>">
                <input type="hidden" name="jurusan" value="<?= htmlspecialchars($return_jurusan) ?>">
                <input type="hidden" name="tingkat" value="<?= htmlspecialchars($return_tingkat) ?>">
                <input type="hidden" name="cari" value="<?= htmlspecialchars($return_cari) ?>">
                <select name="ta" class="form-select form-select-sm rounded-pill fw-bold" style="border:1px solid #AECDEE;" onchange="this.form.submit();">
                    <?php foreach($daftar_tahun_ajaran as $ta): ?>
                        <option value="<?= $ta['id'] ?>" <?= ($id_ta_pilih == $ta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?><?= ($ta['status'] == 'Aktif') ? ' (Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="?page=pantau_kelas<?= $return_filter_url ?>" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background: #D8E9FA !important; color: #0d385e !important; border: 1px solid #AECDEE !important;">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center">
            <thead class="bg-light">
                <tr class="small fw-bold text-muted">
                    <th width="50" class="py-3">NO</th>
                    <th class="text-start">NAMA SISWA</th>
                    <th>NISN</th>
                    <th>STATUS <?= $label_jenis ?></th>
                    <th>KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no=1; 
                if($qSiswa && $qSiswa->num_rows > 0):
                    while($s = $qSiswa->fetch_assoc()): 
                        $id_s = (int)$s['id'];

                        $jml_lengkap = 0;
                        $jml_remedial = 0;

                        foreach($list_mapel_pantau as $mp){
                            $id_m = (int)$mp['id'];
                            $kkm = (int)($mp['kkm'] ?? 75);

                            $qVal = $conn->query("SELECT nilai_t, nilai_p, nilai_sts, nilai_k, nilai_sas, status_validasi
                                                  FROM nilai
                                                  WHERE id_siswa='$id_s'
                                                  AND id_mapel='$id_m'
                                                  AND id_tahun_ajaran='$ta_pilih_safe'
                                                  LIMIT 1");
                            $dVal = ($qVal && $qVal->num_rows > 0) ? $qVal->fetch_assoc() : null;

                            if($dVal){
                                $tp  = (int)($dVal['nilai_t'] ?? 0);
                                $sts = (!empty($dVal['nilai_p'])) ? (int)$dVal['nilai_p'] : (int)($dVal['nilai_sts'] ?? 0);
                                $sas = (!empty($dVal['nilai_k'])) ? (int)$dVal['nilai_k'] : (int)($dVal['nilai_sas'] ?? 0);

                                if($jenis == 'uts'){
                                    if($tp > 0 && $sts > 0){
                                        $jml_lengkap++;
                                        $na = round(((4*$tp) + (2*$sts)) / 6);
                                        if($na < $kkm || $tp < $kkm || $sts < $kkm) $jml_remedial++;
                                    }
                                } else {
                                    if($tp > 0 && $sts > 0 && $sas > 0){
                                        $jml_lengkap++;
                                        $na = round(((4*$tp) + (2*$sts) + (4*$sas)) / 10);
                                        if($na < $kkm || $tp < $kkm || $sts < $kkm || $sas < $kkm) $jml_remedial++;
                                    }
                                }
                            }
                        }

                        if($jml_remedial > 0){
                            $status_badge = "<span class='badge bg-danger rounded-pill px-3'><i class='fas fa-circle-exclamation me-1'></i> Remedial ($jml_remedial)</span>";
                            $ket = "Ada nilai di bawah KKM";
                        } elseif($jml_mapel_wajib > 0 && $jml_lengkap >= $jml_mapel_wajib) {
                            $status_badge = "<span class='badge bg-success rounded-pill px-3'><i class='fas fa-check-circle me-1'></i> Lengkap</span>";
                            $ket = "Nilai $label_jenis sudah lengkap";
                        } elseif($jml_lengkap > 0) {
                            $status_badge = "<span class='badge bg-warning text-dark rounded-pill px-3'><i class='fas fa-clock me-1'></i> Progres ($jml_lengkap/$jml_mapel_wajib)</span>";
                            $ket = "Masih ada nilai $label_jenis yang kosong";
                        } else {
                            $status_badge = "<span class='badge bg-danger rounded-pill px-3'>Belum Input</span>";
                            $ket = "Belum ada nilai $label_jenis";
                        }
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-start fw-bold small"><?= strtoupper($s['nama_siswa']) ?></td>
                    <td class="small text-muted"><?= $s['nisn'] ?></td>
                    <td><?= $status_badge ?></td>
                    <td>
                        <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill" style="font-size: 10px;"><?= $ket ?></span>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr><td colspan="5" class="py-5 text-muted">Belum ada data siswa di kelas ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>