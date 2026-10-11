<?php
$id_kelas = $_GET['id_kelas'] ?? '';

if(empty($id_kelas)){
    echo "<div class='alert alert-danger shadow-sm rounded-4 mt-4 text-center fw-bold'>Pilih kelas terlebih dahulu!</div>";
    exit;
}

// Ranking dihitung KHUSUS untuk tahun ajaran yang sedang dipilih (default: tahun ajaran aktif)
$id_ta_ranking = (int)$id_ta_pilih;

// --- AMBIL INFO KELAS ---
$qKelas = $conn->query("SELECT nama_kelas FROM kelas WHERE id = '$id_kelas'");
$dKelas = $qKelas->fetch_assoc();
$nama_kelas = $dKelas['nama_kelas'] ?? 'Tidak Diketahui';

// --- AMBIL DATA SISWA DAN HITUNG NILAI ---
$id_kelas_safe_rank = mysqli_real_escape_string($conn, $id_kelas);
// 🩹 FIX: tambah cek riwayat_kelas tahun yg dipilih sbg cadangan kalau kolom master siswa.id_kelas nyasar.
$qSiswa = $conn->query("SELECT id, nama_siswa, nisn FROM siswa 
                        WHERE (id_kelas = '$id_kelas_safe_rank' 
                            OR id IN (
                                SELECT rk.id_siswa FROM riwayat_kelas rk 
                                WHERE rk.id_kelas = '$id_kelas_safe_rank' 
                                AND rk.id_tahun_ajaran = '$id_ta_ranking'
                            ))");

$ranking_data = [];
while($s = $qSiswa->fetch_assoc()){
    $id_s = $s['id'];
    $total_na = 0;
    $jml_mapel = 0;
    
    $qNilai = $conn->query("SELECT nilai_t, nilai_p, nilai_k FROM nilai WHERE id_siswa = '$id_s' AND id_tahun_ajaran = '$id_ta_ranking'");
    while($n = $qNilai->fetch_assoc()){
        $tp = $n['nilai_t'] ?? 0;
        $sts = $n['nilai_p'] ?? 0;
        $sas = $n['nilai_k'] ?? 0;
        
        // LOGIKA NA (Berdasarkan bobot TP*4, STS*2, SAS*4 dibagi 10)
        if ((empty($sts) || $sts == 0) && (empty($sas) || $sas == 0)) {
            $na = $tp; 
        } elseif (empty($sas) || $sas == 0) {
            $na = round(((4 * $tp) + (2 * $sts)) / 6); 
        } else {
            $na = round(((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10); 
        }
        
        $total_na += $na;
        $jml_mapel++;
    }
    
    // Hitung Rata-rata Nilai Akhir siswa
    $rata_na = ($jml_mapel > 0) ? round($total_na / $jml_mapel, 1) : 0;
    
    $ranking_data[] = [
        'nama' => $s['nama_siswa'],
        'nisn' => $s['nisn'],
        'rata_na' => $rata_na
    ];
}

// URUTKAN BERDASARKAN RATA-RATA NILAI AKHIR (NA) TERTINGGI KE TERENDAH
usort($ranking_data, function($a, $b) {
    return $b['rata_na'] <=> $a['rata_na'];
});
?>

<div class="card border-0 shadow-sm p-4 mb-4" style="background: #BFD3EA !important; color: #0d385e !important; border-radius:22px; border: 1px solid rgba(255,255,255,0.75); box-shadow: 0 8px 22px rgba(80,115,155,0.14);">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#0d385e;"><i class="fas fa-trophy me-2" style="color:#87653B;"></i>Peringkat Kelas: <?= $nama_kelas ?></h4>
            <p class="mb-1 small" style="color:#35597c; font-weight:500;">Data peringkat ini dihitung otomatis berdasarkan rata-rata Nilai Akhir (NA) seluruh mata pelajaran.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" class="m-0">
                <input type="hidden" name="page" value="lihat_ranking">
                <input type="hidden" name="id_kelas" value="<?= $id_kelas ?>">
                <select name="ta" class="form-select form-select-sm rounded-pill fw-bold" style="border:1px solid #AECDEE;" onchange="this.form.submit();">
                    <?php foreach($daftar_tahun_ajaran as $ta): ?>
                        <option value="<?= $ta['id'] ?>" <?= ($id_ta_pilih == $ta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?><?= ($ta['status'] == 'Aktif') ? ' (Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="?page=pantau_kelas&ta=<?= (int)$id_ta_pilih ?>" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background: #D8E9FA !important; color: #0d385e !important; border: 1px solid #AECDEE !important;">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center">
            <thead class="bg-light small fw-bold text-muted">
                <tr>
                    <th width="10%">RANK</th>
                    <th width="20%">NISN</th>
                    <th width="45%" class="text-start">NAMA SISWA</th>
                    <th width="25%">RATA-RATA NILAI AKHIR</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                if(count($ranking_data) > 0):
                    foreach($ranking_data as $row): 
                        $badge_rank = $rank;
                        if($rank == 1) $badge_rank = "<i class='fas fa-medal fs-4 text-warning' title='Juara 1'></i>";
                        elseif($rank == 2) $badge_rank = "<i class='fas fa-medal fs-5' style='color: #C0C0C0;' title='Juara 2'></i>";
                        elseif($rank == 3) $badge_rank = "<i class='fas fa-medal fs-6' style='color: #CD7F32;' title='Juara 3'></i>";
                ?>
                <tr class="<?= ($rank <= 3) ? 'bg-light' : '' ?>">
                    <td class="fw-bold fs-5"><?= $badge_rank ?></td>
                    <td class="text-muted small"><?= $row['nisn'] ?></td>
                    <td class="text-start fw-bold"><?= strtoupper($row['nama']) ?></td>
                    <td class="text-primary fw-bold fs-5"><?= $row['rata_na'] ?></td>
                </tr>
                <?php 
                    $rank++;
                    endforeach; 
                else: 
                ?>
                <tr>
                    <td colspan="4" class="py-5 text-muted">Belum ada data nilai untuk kelas ini.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>