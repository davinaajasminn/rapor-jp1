<?php
// --- FILTER TAHUN AJARAN (dropdown, default ke yang Aktif) ---
$qDaftarTaSiswa = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");
$daftar_ta_siswa = [];
if($qDaftarTaSiswa){
    while($row_ta_s = $qDaftarTaSiswa->fetch_assoc()){ $daftar_ta_siswa[] = $row_ta_s; }
}

$qTaAktifSiswaLN = $conn->query("SELECT id FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$id_ta_aktif_default_ln = ($qTaAktifSiswaLN && $qTaAktifSiswaLN->num_rows > 0) ? (int)$qTaAktifSiswaLN->fetch_assoc()['id'] : 0;

if($id_ta_aktif_default_ln <= 0 && count($daftar_ta_siswa) > 0){
    $id_ta_aktif_default_ln = (int)$daftar_ta_siswa[0]['id'];
}

// Alumni: default dropdown ke TA TERAKHIR waktu masih aktif sekolah, bukan TA aktif sistem sekarang
// (yang notabene sudah bukan periode dia lagi, jadi kalau dipaksa default ke situ nilai bakal kosong).
$id_ta_default_utk_siswa_ln = ($is_alumni_siswa && $id_ta_terakhir_alumni > 0) ? $id_ta_terakhir_alumni : $id_ta_aktif_default_ln;

$id_ta_get_ln = trim((string)($_GET['id_tahun_ajaran'] ?? ''));
$id_ta_lihat_nilai = ($id_ta_get_ln !== '' && ctype_digit($id_ta_get_ln)) ? (int)$id_ta_get_ln : $id_ta_default_utk_siswa_ln;
$id_ta_lihat_nilai_safe = mysqli_real_escape_string($conn, $id_ta_lihat_nilai);
$lihat_ta_aktif = ($id_ta_lihat_nilai == $id_ta_aktif_default_ln);

// --- RESOLUSI KELAS SISWA UNTUK TAHUN AJARAN YANG DIPILIH ---
// Sumber kebenaran: riwayat_kelas (penempatan resmi per tahun ajaran).
// Fallback ke siswa.id_kelas kalau Kenaikan Kelas belum pernah diproses sama sekali untuk TA itu.
$belum_naik_kelas_ln = false;
$sedang_lihat_periode_alumni_ln = false; // TA yang dipilih ada di riwayat, tapi id_kelas NULL (siswa sudah lulus saat itu)

$qRkLihatNilai = $conn->query("SELECT id_kelas FROM riwayat_kelas
                                WHERE id_siswa='$id_siswa_asli' AND id_tahun_ajaran='$id_ta_lihat_nilai_safe'
                                ORDER BY id DESC LIMIT 1");

$id_kelas_utk_ta_ln = 0;

if($qRkLihatNilai && $qRkLihatNilai->num_rows > 0){
    $row_rk_ln = $qRkLihatNilai->fetch_assoc();
    if($row_rk_ln['id_kelas'] === null){
        // Baris riwayat ADA tapi id_kelas NULL -> ini periode alumni, bukan "belum diproses".
        $sedang_lihat_periode_alumni_ln = true;
    } else {
        $id_kelas_utk_ta_ln = (int)$row_rk_ln['id_kelas'];
    }
} else {
    $qCekRkTaLN = $conn->query("SELECT COUNT(*) as total FROM riwayat_kelas WHERE id_tahun_ajaran='$id_ta_lihat_nilai_safe'");
    $sudah_diproses_ta_ln = $qCekRkTaLN && ((int)($qCekRkTaLN->fetch_assoc()['total'] ?? 0) > 0);

    if($sudah_diproses_ta_ln){
        // Kenaikan Kelas sudah jalan untuk TA ini, tapi siswa ini belum termasuk di dalamnya.
        $belum_naik_kelas_ln = true;
    } else {
        // Kenaikan Kelas belum pernah diproses untuk TA ini, fallback ke kelas siswa saat ini.
        $id_kelas_utk_ta_ln = (int)$id_kelas_siswa;
    }
}

// Ambil info tingkat dan jurusan siswa untuk tahun ajaran yang dipilih
$qKlsInfo = ($belum_naik_kelas_ln || $sedang_lihat_periode_alumni_ln) ? null : $conn->query("SELECT tingkat, kode_jurusan, nama_kelas FROM kelas WHERE id = '$id_kelas_utk_ta_ln'");
$dKlsInfo = ($qKlsInfo && $qKlsInfo->num_rows > 0) ? $qKlsInfo->fetch_assoc() : [];

$tingkat_siswa = $dKlsInfo['tingkat'] ?? 'X';
$jurusan_siswa = $dKlsInfo['kode_jurusan'] ?? '';
$nama_kelas_siswa = $dKlsInfo['nama_kelas'] ?? '';

if(empty($jurusan_siswa)){
    if(stripos($nama_kelas_siswa, 'RPL') !== false) $jurusan_siswa = 'RPL';
    elseif(stripos($nama_kelas_siswa, 'AKL') !== false) $jurusan_siswa = 'AKL';
    elseif(stripos($nama_kelas_siswa, 'AKT') !== false) $jurusan_siswa = 'AKT';
    elseif(stripos($nama_kelas_siswa, 'BR') !== false) $jurusan_siswa = 'BR';
    elseif(stripos($nama_kelas_siswa, 'MP') !== false) $jurusan_siswa = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_siswa);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_siswa);

// Deteksi kolom jurusan mapel agar sama dengan dashboard/wali/admin
$mapel_cols_siswa = [];
$qMapelColsSiswa = $conn->query("SHOW COLUMNS FROM mapel");
if($qMapelColsSiswa){
    while($mc = $qMapelColsSiswa->fetch_assoc()){
        $mapel_cols_siswa[] = $mc['Field'];
    }
}

$mapel_jurusan_col_siswa = '';
if(in_array('kode_jurusan', $mapel_cols_siswa)){
    $mapel_jurusan_col_siswa = 'kode_jurusan';
} elseif(in_array('jurusan', $mapel_cols_siswa)){
    $mapel_jurusan_col_siswa = 'jurusan';
} elseif(in_array('id_jurusan', $mapel_cols_siswa)){
    $mapel_jurusan_col_siswa = 'id_jurusan';
}

$id_jurusan_for_mapel_siswa = 0;
if($mapel_jurusan_col_siswa == 'id_jurusan' && !empty($jurusan_siswa)){
    $qIdJurSiswa = $conn->query("SELECT id FROM jurusan WHERE kode_jurusan='$jurusan_safe' OR nama_jurusan LIKE '%$jurusan_safe%' LIMIT 1");
    if($qIdJurSiswa && $qIdJurSiswa->num_rows > 0){
        $id_jurusan_for_mapel_siswa = (int)$qIdJurSiswa->fetch_assoc()['id'];
    }
}

if(!empty($mapel_jurusan_col_siswa)){
    if($mapel_jurusan_col_siswa == 'id_jurusan'){
        $kondisi_mapel_siswa = "m.tingkat = '$tingkat_safe'
                                 AND m.nama_mapel NOT LIKE '%PKL%'
                                 AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                 AND m.nama_mapel NOT LIKE '%Prakerin%'
                                 AND (
                                    m.kelompok = 'A'
                                    OR m.id_jurusan = '$id_jurusan_for_mapel_siswa'
                                 )";
    } else {
        $kondisi_mapel_siswa = "m.tingkat = '$tingkat_safe'
                                 AND m.nama_mapel NOT LIKE '%PKL%'
                                 AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                 AND m.nama_mapel NOT LIKE '%Prakerin%'
                                 AND (
                                    m.kelompok = 'A'
                                    OR m.$mapel_jurusan_col_siswa = '$jurusan_safe'
                                    OR m.$mapel_jurusan_col_siswa = ''
                                    OR m.$mapel_jurusan_col_siswa IS NULL
                                 )";
    }
} else {
    $kondisi_mapel_siswa = "m.tingkat = '$tingkat_safe'
                             AND m.nama_mapel NOT LIKE '%PKL%'
                             AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                             AND m.nama_mapel NOT LIKE '%Prakerin%'
                             AND (
                                m.kelompok = 'A'
                                OR m.nama_mapel LIKE '%$jurusan_safe%'
                                OR m.nama_mapel LIKE '%($jurusan_safe)%'
                             )";
}

?>

<div class="header-pastel mb-4" style="background: white; border-left: 5px solid #4fb9af; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
    <h5 class="fw-bold mb-1" style="color: #2c3e50;">Detail Nilai Rapor</h5>
    <p class="text-muted small mb-0">Nilai otomatis berwarna <span class="text-danger fw-bold">MERAH</span> jika di bawah KKM mapel.</p>
    <p class="text-muted small mb-0">Status <span class="badge bg-warning text-dark" style="font-size: 10px;">Sementara</span> berarti nilai belum divalidasi oleh Wali Kelas.</p>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Pilih Tahun Ajaran</h6>
            <small class="text-muted">Lihat nilai rapor dari tahun ajaran/semester yang sudah lewat.</small>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="page" value="lihat_nilai">
            <select name="id_tahun_ajaran" class="form-select form-select-sm fw-bold" style="min-width: 220px;" onchange="this.form.submit()">
                <?php foreach($daftar_ta_siswa as $ta_opt): ?>
                    <option value="<?= $ta_opt['id'] ?>" <?= ($id_ta_lihat_nilai == $ta_opt['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ta_opt['tahun']) ?> - <?= htmlspecialchars($ta_opt['semester']) ?><?= (strtolower($ta_opt['status']) == 'aktif') ? ' (Aktif)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h6 class="fw-bold mb-1 text-dark">
                <i class="fas fa-file-pdf me-2 text-danger"></i>Cetak Rekap Nilai
            </h6>
            <small class="text-muted">Rekap nilai ini bukan rapor resmi, jadi tidak memakai tanda tangan digital.</small>
        </div>
        <a href="cetak_rekap_nilai.php?id=<?= urlencode($id_siswa_asli) ?>&id_tahun_ajaran=<?= urlencode($id_ta_lihat_nilai) ?>" target="_blank" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-print me-1"></i> Cetak PDF Rekap Nilai
        </a>
    </div>
</div>

<?php if($sedang_lihat_periode_alumni_ln): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body text-center py-5">
            <i class="fas fa-graduation-cap fa-2x text-secondary mb-3"></i>
            <h6 class="fw-bold text-dark mb-1">Kamu Sudah Lulus (Alumni)</h6>
            <p class="text-muted small mb-0">Tahun ajaran ini berada setelah kamu lulus, jadi nggak ada nilai rapor untuk periode ini. Pilih tahun ajaran di atas untuk melihat histori nilai semasa sekolah.</p>
        </div>
    </div>
<?php elseif($belum_naik_kelas_ln): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body text-center py-5">
            <i class="fas fa-info-circle fa-2x text-warning mb-3"></i>
            <h6 class="fw-bold text-dark mb-1">Belum Ada Penempatan Kelas</h6>
            <p class="text-muted small mb-0">Kamu belum masuk proses Kenaikan Kelas untuk tahun ajaran ini, jadi nilai belum bisa ditampilkan.</p>
        </div>
    </div>
<?php else: ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead style="background-color: #f8fafc; color: #475569; font-size: 12px;">
                    <tr>
                        <th class="py-3" width="50">NO</th>
                        <th class="text-start">MATA PELAJARAN</th>
                        <th>KKM</th>
                        <th>RATA TP</th>
                        <th>STS (UTS)</th>
                        <th>SAS (UAS)</th>
                        <th>SIKAP</th>
                        <th>NILAI AKHIR</th>
                        <th>STATUS</th>
                        <th>PREDIKAT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    $qNilai = $conn->query("
                        SELECT DISTINCT m.id as id_mapel, m.nama_mapel, m.kkm, n.nilai_t, n.nilai_p, n.nilai_k, n.nilai_sikap, n.daftar_nilai_cp, n.status_validasi
                        FROM mapel m
                        LEFT JOIN nilai n ON m.id = n.id_mapel AND n.id_siswa = '$id_siswa_asli' AND n.id_tahun_ajaran = '$id_ta_lihat_nilai_safe'
                        WHERE $kondisi_mapel_siswa
                        ORDER BY m.nama_mapel ASC
                    ");

                    if($qNilai && $qNilai->num_rows > 0):
                        while($r = $qNilai->fetch_assoc()):
                            $id_m = $r['id_mapel'];
                            $kkm = $r['kkm'] ?? 75;
                            // nilai_t diperlakukan sebagai nilai TP UTS yang sudah beku saat UTS dikunci.
                            // Untuk UAS/live, rata-rata TP dihitung dari daftar_nilai_cp saat SAS sudah terisi.
                            $tp_uts = (int)($r['nilai_t'] ?? 0);
                            $tp_live = $tp_uts;
                            $tp_list = json_decode($r['daftar_nilai_cp'] ?? '[]', true);
                            if(is_array($tp_list)){
                                $tp_terisi_live = array_filter($tp_list, function($v){
                                    return ($v !== null && $v !== '' && (float)$v > 0);
                                });
                                if(count($tp_terisi_live) > 0){
                                    $tp_live = round(array_sum($tp_terisi_live) / count($tp_terisi_live));
                                }
                            }

                            $tp = $tp_uts;
                            $sts = $r['nilai_p'] ?? 0;
                            $sas = $r['nilai_k'] ?? 0;
                            $sikap = $r['nilai_sikap'] ?? 0;
                            $valid = $r['status_validasi'] ?? 0;
                            
                            // Ambil Kontak Guru Mapel
                            $qGuru = $conn->query("SELECT g.nama_guru, g.no_hp FROM guru g JOIN guru_mengajar gm ON g.id = gm.id_guru WHERE gm.id_mapel = '$id_m' AND gm.id_kelas = '$id_kelas_utk_ta_ln' LIMIT 1");
                            $guru = $qGuru->fetch_assoc();
                            $nama_g = $guru['nama_guru'] ?? 'Belum diset';
                            $wa_g = $guru['no_hp'] ?? '';
                            
                            // LOGIKA RUMUS NA YANG SUDAH DIPERBAIKI
                            if ((empty($sts) || $sts == 0) && (empty($sas) || $sas == 0)) {
                                // Sebelum UAS/SAS ada, tampilkan nilai UTS yang sudah dikunci.
                                $na = $tp_uts; 
                            } elseif (empty($sas) || $sas == 0) {
                                // UTS: nilai akhir memakai TP UTS beku + STS.
                                $na = round(((4 * $tp_uts) + (2 * $sts)) / 6); 
                            } else {
                                // UAS/live: boleh memakai TP tambahan dari daftar_nilai_cp + STS + SAS.
                                $na = round(((4 * $tp_live) + (2 * $sts) + (4 * $sas)) / 10); 
                            }
                            
                            $checkColor = function($val, $kkm) { return ($val > 0 && $val < $kkm) ? 'text-danger fw-bold' : ''; };
                            $row_opacity = ($na > 0 && $valid == 0) ? 'style="background-color: #fffdf5;"' : '';
                            $class_na = ($na > 0 && $na < $kkm) ? 'bg-danger text-white' : (($na >= $kkm) ? 'bg-success text-white' : 'bg-light text-dark');
                            
                            if($na >= 90) $predikat = "A";
                            elseif($na >= 80) $predikat = "B";
                            elseif($na >= $kkm) $predikat = "C";
                            else $predikat = "D";
                    ?>
                    <tr <?= $row_opacity ?>>
                        <td class="text-muted"><?= $no++ ?></td>
                        <td class="text-start">
                            <div class="fw-bold text-dark"><?= $r['nama_mapel'] ?></div>
                            <div class="d-flex align-items-center mt-1">
                                <small class="text-muted me-2" style="font-size: 11px;">
                                    <i class="fas fa-user-tie me-1"></i> <?= $nama_g ?>
                                </small>
                                <button type="button" class="btn btn-outline-success btn-sm rounded-pill py-0 px-2 shadow-sm" style="font-size: 10px; border-width: 1px;" data-bs-toggle="modal" data-bs-target="#modalKontak<?= $id_m ?>">
                                    <i class="fab fa-whatsapp"></i> Hubungi
                                </button>
                            </div>

                            <div class="modal fade" id="modalKontak<?= $id_m ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-body text-center p-4">
                                            <div class="mb-3">
                                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                                                    <i class="fab fa-whatsapp fa-2x"></i>
                                                </div>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1"><?= $nama_g ?></h6>
                                            <p class="text-muted small mb-3">Guru Mapel <?= $r['nama_mapel'] ?></p>
                                            
                                            <div class="bg-light rounded-3 p-2 mb-3 border">
                                                <span class="fw-bold text-dark fs-6"><?= (!empty($wa_g)) ? $wa_g : 'Nomor belum diisi' ?></span>
                                            </div>
                                            
                                            <?php if(!empty($wa_g)): 
                                                $wa_link = 'https://wa.me/' . preg_replace('/^0/', '62', $wa_g);
                                            ?>
                                                <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm mb-2">
                                                    <i class="fas fa-paper-plane me-1"></i> Chat WhatsApp
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-light w-100 rounded-pill fw-bold" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </td>
                        <td class="fw-bold text-muted"><?= $kkm ?></td>
                        <td class="<?= $checkColor($tp, $kkm) ?>"><?= ($tp > 0) ? $tp : '-' ?></td>
                        <td class="<?= $checkColor($sts, $kkm) ?>"><?= ($sts > 0) ? $sts : '-' ?></td>
                        <td class="<?= $checkColor($sas, $kkm) ?>"><?= ($sas > 0) ? $sas : '-' ?></td>
                        <td class="fw-bold text-primary"><?= ($sikap > 0) ? $sikap : '-' ?></td>
                        <td class="<?= $class_na ?> fw-bold"><?= ($na > 0) ? $na : '-' ?></td>
                        <td>
                            <?php if($na == 0): ?>
                                <span class="text-muted small">-</span>
                            <?php elseif($valid == 1): ?>
                                <span class="badge bg-success rounded-pill" style="font-size: 10px;"><i class="fas fa-check-circle me-1"></i>Valid</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark rounded-pill" style="font-size: 10px;"><i class="fas fa-clock me-1"></i>Sementara</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold">
                            <?php if($na == 0): echo "-"; 
                                  elseif($valid == 0): echo "<small class='text-muted' style='font-size: 10px;'>Menunggu Wali</small>"; 
                                  else: echo $predikat; endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">Data mata pelajaran belum tersedia.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>