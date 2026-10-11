<?php
// --- 1. AMBIL NIP DARI SESSION ---
$nip_guru_login = $_SESSION['guru']['nip']; 
$nama_guru_login = $_SESSION['guru']['nama'];

// Ambil ID Guru dari database
$qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip_guru_login' LIMIT 1");
$id_guru_login = ($qGuru && $qGuru->num_rows > 0) ? $qGuru->fetch_assoc()['id'] : 0;

// --- 2. AMBIL ID KELAS DARI URL ---
$id_kelas_pilihan = $_GET['id_kelas'] ?? '';

if(empty($id_kelas_pilihan)){
    echo "<div class='alert alert-warning mt-4'>Silakan pilih kelas perwalian terlebih dahulu di menu samping.</div>";
    exit;
}

// --- 3. VALIDASI AKSES WALI KELAS ---
// Sekarang dicek lewat riwayat_wali_kelas untuk tahun ajaran yang sedang aktif,
// bukan lagi kolom statis kelas.id_wali_kelas. Kalau guru ini wali kelas tahun
// lalu tapi bukan tahun ini, akses tetap ditolak.
$qTahunAktifWS = $conn->query("SELECT id, tahun FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahunAktifWS = ($qTahunAktifWS && $qTahunAktifWS->num_rows > 0) ? $qTahunAktifWS->fetch_assoc() : null;
$tahun_aktif_ws = $dTahunAktifWS['tahun'] ?? '';
$id_tahun_aktif_ws = $dTahunAktifWS['id'] ?? 0;
$tahun_aktif_ws_esc = mysqli_real_escape_string($conn, $tahun_aktif_ws);
$id_tahun_aktif_ws_safe = mysqli_real_escape_string($conn, $id_tahun_aktif_ws);

$qCek = $conn->query("SELECT k.* 
                      FROM kelas k
                      JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id
                      WHERE k.id = '$id_kelas_pilihan' 
                      AND rwk.id_guru = '$id_guru_login'
                      AND rwk.tahun = '$tahun_aktif_ws_esc'");
$dKelas = $qCek->fetch_assoc();

if (!$dKelas) {
    echo "<div class='card p-5 text-center border-0 shadow-sm rounded-4 mt-4'>
            <i class='fas fa-lock fa-4x mb-3 text-danger'></i>
            <h4 class='fw-bold'>Akses Ditolak</h4>
            <p class='text-muted'>Anda bukan Wali Kelas dari ID Kelas: <b>$id_kelas_pilihan</b></p>
            <hr>
            <small>Sistem mendeteksi akun Anda (ID Guru: $id_guru_login) tidak terdaftar sebagai wali kelas untuk kelas ini.</small>
          </div>";
    exit;
}

$nama_kelas_wali = $dKelas['nama_kelas']; 

// --- CEK AKSES TOMBOL DATA PKL ---
// Tombol Data PKL hanya muncul kalau guru login juga ditugaskan sebagai guru pembimbing PKL
// untuk siswa pada kelas ini. Kalau hanya wali kelas biasa, tombol disembunyikan.
$is_guru_pembimbing_pkl_kelas = false;

if(($dKelas['tingkat'] ?? '') == 'XII' || stripos($nama_kelas_wali, 'XII') === 0){
    $id_kelas_pkl_safe = mysqli_real_escape_string($conn, $id_kelas_pilihan);
    $id_guru_pkl_safe  = mysqli_real_escape_string($conn, $id_guru_login);
    $nama_guru_pkl_safe = mysqli_real_escape_string($conn, $nama_guru_login);

    $where_pembimbing_pkl = [];

    // Kalau tabel data_pkl punya kolom id_guru_pembimbing
    $cekKolIdPembimbing = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'id_guru_pembimbing'");
    if($cekKolIdPembimbing && $cekKolIdPembimbing->num_rows > 0){
        $where_pembimbing_pkl[] = "dp.id_guru_pembimbing = '$id_guru_pkl_safe'";
    }

    // Kalau tabel data_pkl punya kolom id_guru
    $cekKolIdGuru = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'id_guru'");
    if($cekKolIdGuru && $cekKolIdGuru->num_rows > 0){
        $where_pembimbing_pkl[] = "dp.id_guru = '$id_guru_pkl_safe'";
    }

    // Kalau tabel data_pkl menyimpan nama guru pembimbing dalam bentuk teks
    $cekKolGuruPembimbing = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'guru_pembimbing'");
    if($cekKolGuruPembimbing && $cekKolGuruPembimbing->num_rows > 0){
        $where_pembimbing_pkl[] = "dp.guru_pembimbing = '$nama_guru_pkl_safe'";
    }

    if(!empty($where_pembimbing_pkl)){
        $where_pembimbing_pkl_sql = implode(" OR ", $where_pembimbing_pkl);

        $qCekPembimbingPKL = $conn->query("SELECT COUNT(dp.id) AS total
                                           FROM data_pkl dp
                                           JOIN siswa s ON dp.id_siswa = s.id
                                           WHERE s.id_kelas = '$id_kelas_pkl_safe'
                                           AND dp.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                           AND ($where_pembimbing_pkl_sql)");

        $dCekPembimbingPKL = ($qCekPembimbingPKL && $qCekPembimbingPKL->num_rows > 0) ? $qCekPembimbingPKL->fetch_assoc() : ['total' => 0];
        $is_guru_pembimbing_pkl_kelas = ((int)$dCekPembimbingPKL['total'] > 0);
    }
}


// --- 4. HITUNG JUMLAH MAPEL WAJIB UNTUK KELAS INI ---
// FIX SINKRON: Wali kelas tidak lagi menghitung semua mapel kelompok B.
// Prioritas utama ambil mapel dari guru_mengajar sesuai id_kelas, karena itulah mapel yang benar-benar dipakai kelas tersebut.
// Kalau guru_mengajar untuk kelas ini masih kosong, baru fallback ke filter mapel: kelompok A + jurusan kelas.
$tingkat_romawi = $dKelas['tingkat'] ?? explode(' ', trim($nama_kelas_wali))[0];
$kode_jurusan_kelas = $dKelas['kode_jurusan'] ?? '';

if(empty($kode_jurusan_kelas)){
    if(stripos($nama_kelas_wali, 'RPL') !== false) $kode_jurusan_kelas = 'RPL';
    elseif(stripos($nama_kelas_wali, 'AKL') !== false) $kode_jurusan_kelas = 'AKL';
    elseif(stripos($nama_kelas_wali, 'AKT') !== false) $kode_jurusan_kelas = 'AKT';
    elseif(stripos($nama_kelas_wali, 'BR') !== false) $kode_jurusan_kelas = 'BR';
    elseif(stripos($nama_kelas_wali, 'MP') !== false) $kode_jurusan_kelas = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $kode_jurusan_kelas);
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas_pilihan);

// Kolom validasi PKL kalau kelas XII
$cekKolomValidPKL = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'status_validasi_pkl'");
if($cekKolomValidPKL && $cekKolomValidPKL->num_rows == 0){
    $conn->query("ALTER TABLE data_pkl ADD status_validasi_pkl TINYINT(1) NOT NULL DEFAULT 0");
}

// Cek mapel reguler untuk semua tingkat, termasuk XII.
// Kelas XII tetap punya mapel akademik + PKL.
$qCekGM = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                        FROM guru_mengajar gm
                        JOIN mapel m ON gm.id_mapel = m.id
                        WHERE gm.id_kelas = '$id_kelas_safe'
                        AND m.tingkat = '$tingkat_safe'
                        AND m.nama_mapel NOT LIKE '%PKL%'
                        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                        AND m.nama_mapel NOT LIKE '%Prakerin%'");
$jml_gm = ($qCekGM && $qCekGM->num_rows > 0) ? (int)$qCekGM->fetch_assoc()['total'] : 0;
$pakai_guru_mengajar = ($jml_gm > 0);
$kondisi_mapel_valid = $kondisi_mapel_valid ?? "1=1";

if($pakai_guru_mengajar){
    $qJmlMapel = $conn->query("SELECT COUNT(DISTINCT m.id) AS total
                               FROM guru_mengajar gm
                               JOIN mapel m ON gm.id_mapel = m.id
                               WHERE gm.id_kelas = '$id_kelas_safe'
                               AND m.tingkat = '$tingkat_safe'
                               AND m.nama_mapel NOT LIKE '%PKL%'
                               AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                               AND m.nama_mapel NOT LIKE '%Prakerin%'");
    $jml_mapel_wajib = $qJmlMapel ? ((int)($qJmlMapel->fetch_assoc()['total'] ?? 0)) : 0;
} else {
    $kondisi_mapel_wajib = "tingkat = '$tingkat_safe'
                            AND nama_mapel NOT LIKE '%PKL%'
                            AND nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                kelompok = 'A'
                                OR kode_jurusan = '$jurusan_safe'
                            )";

    $kondisi_mapel_valid = "m.tingkat = '$tingkat_safe'
                            AND m.nama_mapel NOT LIKE '%PKL%'
                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND m.nama_mapel NOT LIKE '%Prakerin%'
                            AND (
                                m.kelompok = 'A'
                                OR m.kode_jurusan = '$jurusan_safe'
                            )";

    $qJmlMapel = $conn->query("SELECT COUNT(DISTINCT id) AS total FROM mapel WHERE $kondisi_mapel_wajib");
    $jml_mapel_wajib = $qJmlMapel ? ((int)($qJmlMapel->fetch_assoc()['total'] ?? 0)) : 0;
}
// --- AKSI VALIDASI SEMUA SISWA YANG READY ---
if(isset($_POST['validasi_semua_ready'])){
    $id_kelas_valid_ready = mysqli_real_escape_string($conn, $id_kelas_pilihan);

    // FIX: riwayat_kelas pada tahun ajaran aktif jadi sumber utama.
    // Kolom statis siswa.id_kelas HANYA fallback untuk siswa yang belum
    // sama sekali punya baris riwayat_kelas di tahun ajaran ini.
    $qSiswaReady = $conn->query("SELECT * FROM siswa 
                                 WHERE id IN (
                                     SELECT rk.id_siswa FROM riwayat_kelas rk 
                                     WHERE rk.id_kelas = '$id_kelas_valid_ready' 
                                     AND rk.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                 )
                                 OR (
                                     id_kelas = '$id_kelas_valid_ready'
                                     AND id NOT IN (
                                         SELECT rk2.id_siswa FROM riwayat_kelas rk2 
                                         WHERE rk2.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                     )
                                 )
                                 ORDER BY nama_siswa ASC");

    $total_berhasil_valid = 0;
    $total_dilewati = 0;

    if($qSiswaReady && $qSiswaReady->num_rows > 0){
        while($sr = $qSiswaReady->fetch_assoc()){
            $id_s_ready = (int)$sr['id'];

            $where_mapel_ready = "";
            $join_mapel_ready = "";

            if($pakai_guru_mengajar){
                $join_mapel_ready = "JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'";
                $where_mapel_ready = "m.tingkat = '$tingkat_safe'
                                      AND m.nama_mapel NOT LIKE '%PKL%'
                                      AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                      AND m.nama_mapel NOT LIKE '%Prakerin%'";
            } else {
                $join_mapel_ready = "";
                $where_mapel_ready = "$kondisi_mapel_valid";
            }

            // Hitung nilai lengkap dan remedial.
            $qCekReady = $conn->query("SELECT 
                                        COUNT(DISTINCT n.id_mapel) AS total_terisi,
                                        SUM(CASE 
                                            WHEN n.id IS NULL THEN 0
                                            WHEN (
                                                n.nilai_t IS NULL OR n.nilai_t = '' OR n.nilai_t <= 0 OR
                                                COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) <= 0
                                            ) THEN 1
                                            ELSE 0
                                        END) AS total_kosong,
                                        SUM(CASE
                                            WHEN n.id IS NULL THEN 0
                                            WHEN (
                                                n.nilai_t > 0 AND n.nilai_t < COALESCE(m.kkm,75)
                                                OR n.nilai_p > 0 AND n.nilai_p < COALESCE(m.kkm,75)
                                                OR n.nilai_k > 0 AND n.nilai_k < COALESCE(m.kkm,75)
                                                OR ROUND(((4 * n.nilai_t) + (2 * n.nilai_p) + (4 * n.nilai_k)) / 10) < COALESCE(m.kkm,75)
                                            ) THEN 1
                                            ELSE 0
                                        END) AS total_remedial
                                      FROM nilai n
                                      JOIN mapel m ON n.id_mapel = m.id
                                      $join_mapel_ready
                                      WHERE n.id_siswa = '$id_s_ready'
                                      AND $where_mapel_ready");

            $dReady = ($qCekReady && $qCekReady->num_rows > 0) ? $qCekReady->fetch_assoc() : [];
            $total_terisi = (int)($dReady['total_terisi'] ?? 0);
            $total_kosong = (int)($dReady['total_kosong'] ?? 0);
            $total_remedial = (int)($dReady['total_remedial'] ?? 0);

            $siswa_ready_mapel = ($jml_mapel_wajib > 0 && $total_terisi >= $jml_mapel_wajib && $total_kosong == 0 && $total_remedial == 0);

            $siswa_ready_pkl = true;
            if($tingkat_romawi == 'XII'){
                $qPKLReady = $conn->query("SELECT tp1_skor, tp2_skor, tp3_skor, tp4_skor
                                           FROM data_pkl
                                           WHERE id_siswa = '$id_s_ready'
                                           AND id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                           LIMIT 1");
                $dPKLReady = ($qPKLReady && $qPKLReady->num_rows > 0) ? $qPKLReady->fetch_assoc() : null;

                $jml_skor_pkl_ready = 0;
                if($dPKLReady){
                    foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_pkl_ready){
                        if($dPKLReady[$field_pkl_ready] !== null && $dPKLReady[$field_pkl_ready] !== '' && (float)$dPKLReady[$field_pkl_ready] > 0){
                            $jml_skor_pkl_ready++;
                        }
                    }
                }

                $siswa_ready_pkl = ($dPKLReady && $jml_skor_pkl_ready == 4);
            }

            if($siswa_ready_mapel && $siswa_ready_pkl){
                if($pakai_guru_mengajar){
                    $conn->query("UPDATE nilai n
                                  JOIN mapel m ON n.id_mapel = m.id
                                  JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'
                                  SET n.status_validasi = 1
                                  WHERE n.id_siswa = '$id_s_ready'
                                  AND m.tingkat = '$tingkat_safe'
                                  AND m.nama_mapel NOT LIKE '%PKL%'
                                  AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                  AND m.nama_mapel NOT LIKE '%Prakerin%'
                                  AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                  AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0");
                } else {
                    $conn->query("UPDATE nilai n
                                  JOIN mapel m ON n.id_mapel = m.id
                                  SET n.status_validasi = 1
                                  WHERE n.id_siswa = '$id_s_ready'
                                  AND $kondisi_mapel_valid
                                  AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                  AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0");
                }

                if($tingkat_romawi == 'XII'){
                    $conn->query("UPDATE data_pkl SET status_validasi_pkl = 1
                                  WHERE id_siswa = '$id_s_ready'
                                  AND id_tahun_ajaran = '$id_tahun_aktif_ws_safe'");
                }

                $total_berhasil_valid++;
            } else {
                $total_dilewati++;
            }
        }
    }

    echo "<script>alert('Validasi selesai. Siswa ready tervalidasi: $total_berhasil_valid. Siswa dilewati: $total_dilewati.'); window.location='?page=wali_siswa&id_kelas=$id_kelas_pilihan';</script>";
    exit;
}

?>

<style>
    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #fff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
        transform: none !important;
    }

    .btn-soft-green {
        background: #5E9F86 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(94, 159, 134, 0.25) !important;
        transition: none !important;
        transform: none !important;
    }

    .btn-soft-yellow {
        background: #FFD98A !important;
        color: #7A5200 !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(255, 217, 138, 0.25) !important;
        transition: none !important;
        transform: none !important;
    }

    .btn-print-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(174, 205, 238, 0.18) !important;
        transition: none !important;
        transform: none !important;
    }

    .wali-banner {
        background: linear-gradient(135deg, #eaf3ff 0%, #c7ddf5 55%, #a9c5e6 100%);
        border-radius: 22px;
        min-height: 150px;
        padding: 28px 32px;
        color: #0d385e;
        box-shadow: 0 8px 22px rgba(80, 115, 155, 0.18);
        border: 1px solid rgba(255,255,255,0.75);
    }

    .wali-badge {
        background: rgba(255,255,255,0.86);
        color: #0d6efd;
        border-radius: 50px;
        padding: 7px 14px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 5px 12px rgba(80,115,155,0.10);
    }

    .btn-wali-sm {
        min-height: 38px;
        padding: 0 18px !important;
        font-size: 13px !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-wali-table {
        min-height: 31px;
        min-width: 58px;
        padding: 0 13px !important;
        font-size: 12px !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .wali-status-pending {
        background: #FFE3E8;
        color: #842029;
        border: 1px solid #F2A6B3;
        border-radius: 50px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
    }

    .wali-status-ready {
        background: #DDF5EA;
        color: #1f5f48;
        border: 1px solid #9DDCC4;
        border-radius: 50px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
    }

    .wali-status-remedial {
        background: #FFDDE5;
        color: #842029;
        border: 1px solid #F2A6B3;
        border-radius: 50px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
    }

    .wali-status-valid-remedial {
        background: #FFF1CC;
        color: #7A5200;
        border: 1px solid #E9C979;
        border-radius: 50px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
    }

    .wali-stat-box {
        background: #ffffff;
        border-radius: 18px;
        padding: 14px 18px;
        box-shadow: 0 6px 16px rgba(80,115,155,0.10);
        border: 1px solid #edf2f7;
        min-width: 145px;
    }

    .wali-stat-label {
        font-size: 11px;
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 4px;
    }

    .wali-stat-value {
        font-size: 24px;
        font-weight: 900;
        color: #0d385e;
        line-height: 1;
    }

</style>

<?php
// --- HITUNG STATISTIK VALIDASI KELAS ---
$total_siswa_kelas = 0;
$total_ready_kelas = 0;
$total_pending_kelas = 0;
$total_remedial_kelas = 0;

// FIX: riwayat_kelas pada tahun ajaran aktif jadi sumber utama.
// Kolom statis siswa.id_kelas HANYA dipakai sebagai fallback untuk siswa
// yang belum sama sekali punya baris riwayat_kelas di tahun ajaran ini,
// supaya siswa yang di tahun ini sudah tercatat pindah/naik kelas lain
// tidak ikut ketarik hanya karena kolom statisnya belum ter-update.
$qStatSiswa = $conn->query("SELECT * FROM siswa 
                            WHERE id IN (
                                SELECT rk.id_siswa FROM riwayat_kelas rk 
                                WHERE rk.id_kelas = '$id_kelas_pilihan' 
                                AND rk.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                            )
                            OR (
                                id_kelas = '$id_kelas_pilihan'
                                AND id NOT IN (
                                    SELECT rk2.id_siswa FROM riwayat_kelas rk2 
                                    WHERE rk2.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                )
                            )");
if($qStatSiswa && $qStatSiswa->num_rows > 0){
    while($ss = $qStatSiswa->fetch_assoc()){
        $total_siswa_kelas++;
        $id_stat_siswa = (int)$ss['id'];

        if($pakai_guru_mengajar){
            $join_mapel_stat = "JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'";
            $where_mapel_stat = "m.tingkat = '$tingkat_safe'
                                 AND m.nama_mapel NOT LIKE '%PKL%'
                                 AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                 AND m.nama_mapel NOT LIKE '%Prakerin%'";
        } else {
            $join_mapel_stat = "";
            $where_mapel_stat = $kondisi_mapel_valid;
        }

        $qStatNilai = $conn->query("SELECT 
                                    COUNT(DISTINCT n.id_mapel) AS total_terisi,
                                    SUM(CASE 
                                        WHEN n.id IS NULL THEN 0
                                        WHEN (
                                            n.nilai_t IS NULL OR n.nilai_t = '' OR n.nilai_t <= 0 OR
                                            COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) <= 0
                                        ) THEN 1
                                        ELSE 0
                                    END) AS total_kosong,
                                    SUM(CASE
                                        WHEN n.id IS NULL THEN 0
                                        WHEN (
                                            (
                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                    AND n.nilai_t < COALESCE(m.kkm,75)
                                                )
                                                OR (
                                                    COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) < COALESCE(m.kkm,75)
                                                )
                                                OR (
                                                    COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0
                                                    AND COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) < COALESCE(m.kkm,75)
                                                )
                                                OR (
                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                    AND (
                                                        CASE
                                                            WHEN COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0 THEN
                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0)) + (4 * COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0))) / 10)
                                                            ELSE
                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0))) / 6)
                                                        END
                                                    ) < COALESCE(m.kkm,75)
                                                )
                                        ) THEN 1
                                        ELSE 0
                                    END) AS total_remedial,
                                    SUM(CASE WHEN n.status_validasi = 1 THEN 1 ELSE 0 END) AS total_valid
                                  FROM nilai n
                                  JOIN mapel m ON n.id_mapel = m.id
                                  $join_mapel_stat
                                  WHERE n.id_siswa = '$id_stat_siswa'
                                  AND $where_mapel_stat");

        $dStatNilai = ($qStatNilai && $qStatNilai->num_rows > 0) ? $qStatNilai->fetch_assoc() : [];
        $stat_terisi = (int)($dStatNilai['total_terisi'] ?? 0);
        $stat_kosong = (int)($dStatNilai['total_kosong'] ?? 0);
        $stat_remedial = (int)($dStatNilai['total_remedial'] ?? 0);
        $stat_valid = (int)($dStatNilai['total_valid'] ?? 0);

        $stat_pkl_ready = true;
        if($tingkat_romawi == 'XII'){
            $qStatPKL = $conn->query("SELECT tp1_skor, tp2_skor, tp3_skor, tp4_skor, status_validasi_pkl
                                      FROM data_pkl
                                      WHERE id_siswa = '$id_stat_siswa'
                                      AND id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                      LIMIT 1");
            $dStatPKL = ($qStatPKL && $qStatPKL->num_rows > 0) ? $qStatPKL->fetch_assoc() : null;

            $stat_pkl_skor = 0;
            if($dStatPKL){
                foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_stat_pkl){
                    if($dStatPKL[$field_stat_pkl] !== null && $dStatPKL[$field_stat_pkl] !== '' && (float)$dStatPKL[$field_stat_pkl] > 0){
                        $stat_pkl_skor++;
                    }
                }
            }

            $stat_pkl_ready = ($dStatPKL && $stat_pkl_skor == 4 && (int)$dStatPKL['status_validasi_pkl'] == 1);
        }

        if($stat_remedial > 0){
            $total_remedial_kelas++;
        } elseif($jml_mapel_wajib > 0 && $stat_valid >= $jml_mapel_wajib && $stat_pkl_ready){
            $total_ready_kelas++;
        } else {
            $total_pending_kelas++;
        }
    }
}
?>

<div class="wali-banner mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="wali-badge mb-3">
                <i class="fas fa-user-shield me-2 text-success"></i> Panel Wali Kelas
            </span>

            <h3 class="fw-bold mb-2" style="color:#173c76;">
                Wali Kelas: <?= $nama_kelas_wali ?>
            </h3>

            <p class="mb-0 fw-semibold" style="color:#244b70;">
                Nama Guru: <?= $nama_guru_login ?> | NIP: <?= $nip_guru_login ?>
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <div class="wali-stat-box">
                    <div class="wali-stat-label">Total Siswa</div>
                    <div class="wali-stat-value"><?= $total_siswa_kelas ?></div>
                </div>
                <div class="wali-stat-box">
                    <div class="wali-stat-label">Ready</div>
                    <div class="wali-stat-value text-success"><?= $total_ready_kelas ?></div>
                </div>
                <div class="wali-stat-box">
                    <div class="wali-stat-label">Pending</div>
                    <div class="wali-stat-value text-warning"><?= $total_pending_kelas ?></div>
                </div>
                <div class="wali-stat-box">
                    <div class="wali-stat-label">Remedial</div>
                    <div class="wali-stat-value text-danger"><?= $total_remedial_kelas ?></div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=rekap_input_kelas&id_kelas=<?= $id_kelas_pilihan ?>" class="btn btn-print-soft btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-chart-bar"></i> Lihat Rekap Nilai
            </a>

            <a href="?page=validasi_index" class="btn btn-print-soft btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h6 class="fw-bold mb-1 text-dark">
                <i class="fas fa-tasks me-2 text-primary"></i>AKSI WALI KELAS
            </h6>
            <small class="text-muted">Kelola Ledger Nilai dan Input Catatan Akademik Siswa.</small>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="m-0" onsubmit="return confirm('Validasi semua siswa yang Ready? Siswa remedial atau belum lengkap akan dilewati.');">
                <button type="submit" name="validasi_semua_ready" class="btn btn-soft-green btn-wali-sm fw-bold shadow-sm">
                    <i class="fas fa-check-double"></i> Validasi Semua Ready
                </button>
            </form>

            <?php if($tingkat_romawi == 'XII' && $is_guru_pembimbing_pkl_kelas): ?>
            <a href="?page=input_pkl&id_kelas=<?= $id_kelas_pilihan ?>" class="btn btn-soft-yellow btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-industry"></i> Data PKL
            </a>
            <?php endif; ?>
            
            <a href="?page=ledger_view&id_kelas=<?= $id_kelas_pilihan ?>&jenis=uts" class="btn btn-print-soft btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-file-excel"></i> Ledger UTS
            </a>

            <a href="?page=ledger_view&id_kelas=<?= $id_kelas_pilihan ?>&jenis=uas" class="btn btn-soft-green btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-file-excel"></i> Ledger UAS
            </a>

            <a href="?page=input_catatan&id_kelas=<?= $id_kelas_pilihan ?>" class="btn btn-soft-blue btn-wali-sm fw-bold shadow-sm">
                <i class="fas fa-comment-dots"></i> Input Catatan
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-users me-2"></i>Daftar Siswa Kelas <?= $nama_kelas_wali ?>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="text-center">
                    <th class="py-3" width="50">NO</th>
                    <th class="text-start">NAMA SISWA</th>
                    <th>NISN</th>
                    <th>STATUS VALIDASI</th>
                    <th width="120">AKSI CEK</th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $no = 1;
                // FIX: riwayat_kelas pada tahun ajaran aktif jadi sumber utama.
                // Kolom statis siswa.id_kelas HANYA fallback untuk siswa yang
                // belum sama sekali punya baris riwayat_kelas di tahun ajaran ini.
                $qSiswa = $conn->query("SELECT * FROM siswa 
                                        WHERE id IN (
                                            SELECT rk.id_siswa FROM riwayat_kelas rk 
                                            WHERE rk.id_kelas = '$id_kelas_pilihan' 
                                            AND rk.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                        )
                                        OR (
                                            id_kelas = '$id_kelas_pilihan'
                                            AND id NOT IN (
                                                SELECT rk2.id_siswa FROM riwayat_kelas rk2 
                                                WHERE rk2.id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                            )
                                        )
                                        ORDER BY nama_siswa ASC");
                
                if($qSiswa->num_rows > 0):
                    while($s = $qSiswa->fetch_assoc()): 
                        $id_s = $s['id'];
                        
                        // Status mapel reguler berlaku untuk X, XI, dan XII.
                        // Untuk XII dibuat lebih ketat karena rapor akhir butuh TP + STS + SAS.
                        $syarat_nilai_lengkap_xii = "";
                        if($tingkat_romawi == 'XII'){
                            $syarat_nilai_lengkap_xii = "AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                         AND n.nilai_p IS NOT NULL AND n.nilai_p != '' AND n.nilai_p > 0
                                                         AND n.nilai_k IS NOT NULL AND n.nilai_k != '' AND n.nilai_k > 0";
                        }

                        if($pakai_guru_mengajar){
                            $qValid = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS jml_valid
                                                    FROM nilai n
                                                    JOIN mapel m ON n.id_mapel = m.id
                                                    JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'
                                                    WHERE n.id_siswa = '$id_s'
                                                    AND n.status_validasi = 1
                                                    $syarat_nilai_lengkap_xii
                                                    AND m.tingkat = '$tingkat_safe'
                                                    AND m.nama_mapel NOT LIKE '%PKL%'
                                                    AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                    AND m.nama_mapel NOT LIKE '%Prakerin%'");
                        } else {
                            $qValid = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS jml_valid
                                                    FROM nilai n 
                                                    JOIN mapel m ON n.id_mapel = m.id
                                                    WHERE n.id_siswa = '$id_s' 
                                                    AND n.status_validasi = 1
                                                    $syarat_nilai_lengkap_xii
                                                    AND $kondisi_mapel_valid");
                        }

                        $jml_valid_mapel = $qValid ? ((int)($qValid->fetch_assoc()['jml_valid'] ?? 0)) : 0;
                        $mapel_complete = ($jml_valid_mapel >= $jml_mapel_wajib && $jml_mapel_wajib > 0);

                        // Hitung nilai yang di bawah KKM untuk indikator merah/remedial.
                        if($pakai_guru_mengajar){
                            $qRemedialSiswa = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS total_remedial
                                                            FROM nilai n
                                                            JOIN mapel m ON n.id_mapel = m.id
                                                            JOIN guru_mengajar gm ON gm.id_mapel = m.id AND gm.id_kelas = '$id_kelas_safe'
                                                            WHERE n.id_siswa = '$id_s'
                                                            AND m.tingkat = '$tingkat_safe'
                                                            AND m.nama_mapel NOT LIKE '%PKL%'
                                                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                            AND m.nama_mapel NOT LIKE '%Prakerin%'
                                                            AND (
                                                                (
                                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                                    AND n.nilai_t < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0
                                                                    AND COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                                    AND (
                                                                        CASE
                                                                            WHEN COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0 THEN
                                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0)) + (4 * COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0))) / 10)
                                                                            ELSE
                                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0))) / 6)
                                                                        END
                                                                    ) < COALESCE(m.kkm,75)
                                                                )
                                                            )");
                        } else {
                            $qRemedialSiswa = $conn->query("SELECT COUNT(DISTINCT n.id_mapel) AS total_remedial
                                                            FROM nilai n
                                                            JOIN mapel m ON n.id_mapel = m.id
                                                            WHERE n.id_siswa = '$id_s'
                                                            AND $kondisi_mapel_valid
                                                            AND (
                                                                (
                                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                                    AND n.nilai_t < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0
                                                                    AND COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) < COALESCE(m.kkm,75)
                                                                )
                                                                OR (
                                                                    n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                                    AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
                                                                    AND (
                                                                        CASE
                                                                            WHEN COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0 THEN
                                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0)) + (4 * COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0))) / 10)
                                                                            ELSE
                                                                                ROUND(((4 * n.nilai_t) + (2 * COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0))) / 6)
                                                                        END
                                                                    ) < COALESCE(m.kkm,75)
                                                                )
                                                            )");
                        }
                        $jml_remedial_siswa = ($qRemedialSiswa && $qRemedialSiswa->num_rows > 0) ? (int)($qRemedialSiswa->fetch_assoc()['total_remedial'] ?? 0) : 0;

                        $jml_valid_siswa = $jml_valid_mapel;
                        $is_complete = $mapel_complete;

                        if($tingkat_romawi == 'XII'){
                            $qPKLStatus = $conn->query("SELECT tp1_skor, tp2_skor, tp3_skor, tp4_skor, status_validasi_pkl
                                                        FROM data_pkl
                                                        WHERE id_siswa = '$id_s'
                                                        AND id_tahun_ajaran = '$id_tahun_aktif_ws_safe'
                                                        LIMIT 1");
                            $dPKLStatus = ($qPKLStatus && $qPKLStatus->num_rows > 0) ? $qPKLStatus->fetch_assoc() : null;

                            $jml_valid_pkl = 0;
                            if($dPKLStatus){
                                foreach(['tp1_skor','tp2_skor','tp3_skor','tp4_skor'] as $field_pkl){
                                    if($dPKLStatus[$field_pkl] !== null && $dPKLStatus[$field_pkl] !== ''){
                                        $jml_valid_pkl++;
                                    }
                                }
                            }

                            $pkl_complete = ($dPKLStatus && $jml_valid_pkl == 4 && (int)$dPKLStatus['status_validasi_pkl'] == 1);
                            $is_complete = ($mapel_complete && $pkl_complete);
                        }
                ?>
                <tr class="text-center">
                    <td class="text-muted"><?= $no++ ?></td>

                    <td class="text-start fw-bold">
                        <?= strtoupper($s['nama_siswa']) ?>
                    </td>

                    <td><?= $s['nisn'] ?></td>

                    <td>
                        <?php if($jml_remedial_siswa > 0): ?>
                            <?php if($is_complete): ?>
                                <span class="wali-status-valid-remedial">
                                    <i class="fas fa-check-circle me-1"></i> Valid dengan Remedial (<?= $jml_remedial_siswa ?>)
                                </span>
                            <?php else: ?>
                                <span class="wali-status-remedial">
                                    <i class="fas fa-circle-exclamation me-1"></i> Remedial (<?= $jml_remedial_siswa ?>) - Belum Valid
                                </span>
                            <?php endif; ?>
                        <?php elseif($tingkat_romawi == 'XII'): ?>
                            <?php if(!$is_complete): ?>
                                <span class="wali-status-pending">
                                    <i class="fas fa-clock me-1"></i>
                                    Mapel <?= $jml_valid_mapel ?>/<?= $jml_mapel_wajib ?> | PKL <?= $jml_valid_pkl ?>/4
                                </span>
                            <?php else: ?>
                                <span class="wali-status-ready">
                                    <i class="fas fa-check-circle me-1"></i> Ready Mapel & PKL
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if(!$is_complete): ?>
                                <span class="wali-status-pending">
                                    <i class="fas fa-clock me-1"></i> Pending (<?= $jml_valid_siswa ?>/<?= $jml_mapel_wajib ?>)
                                </span>
                            <?php else: ?>
                                <span class="wali-status-ready">
                                    <i class="fas fa-check-circle me-1"></i> Ready
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a href="?page=validasi_detail&id_siswa=<?= $s['id'] ?>" class="btn btn-print-soft btn-wali-table fw-bold shadow-sm">
                                <i class="fas fa-search"></i> Cek
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <h6 class="text-muted">Gak ada siswa di kelas <?= $nama_kelas_wali ?>.</h6>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>