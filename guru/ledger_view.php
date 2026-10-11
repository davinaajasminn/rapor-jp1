<?php
// JENIS LEDGER (UTS atau UAS)
$jenis = $_GET['jenis'] ?? 'uas';
$judul_ledger = ($jenis == 'uts') ? "LEDGER NILAI TENGAH SEMESTER (UTS)" : "LEDGER NILAI AKHIR SEMESTER (UAS)";

// 1. AMBIL IDENTITAS SEKOLAH + TAHUN AJARAN AKTIF
$qIden = $conn->query("SELECT * FROM identitas_sekolah LIMIT 1");
$di = ($qIden && $qIden->num_rows > 0) ? $qIden->fetch_assoc() : [];

$nama_sekolah = $di['nama_sekolah'] ?? 'SMK Jakarta Pusat 1';

// Semester dan tahun pelajaran diambil dari tabel tahun_ajaran karena di identitas_sekolah tidak ada kolom semester_aktif/tahun_pelajaran
$qTahun = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahun = ($qTahun && $qTahun->num_rows > 0) ? $qTahun->fetch_assoc() : [];

$semester_db   = $dTahun['semester'] ?? 'Ganjil';
$thn_pelajaran = $dTahun['tahun'] ?? '2025/2026';
$id_tahun_aktif_ledger_safe = mysqli_real_escape_string($conn, $dTahun['id'] ?? 0);

// 2. DATA WALI & KELAS
$id_kelas = $_GET['id_kelas'] ?? ''; 
$id_kelas_safe = mysqli_real_escape_string($conn, $id_kelas);

if(empty($id_kelas)){
    echo "<div class='alert alert-danger mt-4 text-center'>Pilih kelas dari menu Validasi dulu, Dav!</div>";
    exit;
}

// --- Wali kelas sekarang diambil lewat riwayat_wali_kelas sesuai tahun ajaran aktif ($thn_pelajaran) ---
$thn_pelajaran_safe = mysqli_real_escape_string($conn, $thn_pelajaran);
$qK = $conn->query("SELECT k.nama_kelas, g.nama_guru 
                    FROM kelas k 
                    LEFT JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id AND rwk.tahun = '$thn_pelajaran_safe'
                    LEFT JOIN guru g ON rwk.id_guru = g.id
                    WHERE k.id = '$id_kelas_safe' LIMIT 1");
$dk = $qK->fetch_assoc();

$nama_kelas = $dk['nama_kelas'] ?? '-';
$nama_wali_display = $dk['nama_guru'] ?? '-'; 

// Variabel Sakti buat ganti nama file PDF! (Disesuaikan UTS/UAS)
$nama_file_pdf = "Ledger_".strtoupper($jenis)."_" . str_replace(" ", "_", $nama_kelas) . "_" . strtoupper($semester_db);

// --- LOGIKA PENOMORAN SEMESTER 1-6 ---
$tingkat_kelas = strtoupper(explode(' ', trim($nama_kelas))[0]);
$is_genap = (stripos($semester_db, 'Genap') !== false);

if ($tingkat_kelas == 'X') {
    $smt_angka = $is_genap ? "2 (DUA)" : "1 (SATU)";
} elseif ($tingkat_kelas == 'XI') {
    $smt_angka = $is_genap ? "4 (EMPAT)" : "3 (TIGA)";
} elseif ($tingkat_kelas == 'XII') {
    $smt_angka = $is_genap ? "6 (ENAM)" : "5 (LIMA)";
} else {
    $smt_angka = strtoupper($semester_db);
}

// 3. DAFTAR MAPEL SINKRON DENGAN KELAS
$tingkat_romawi = strtoupper(explode(' ', trim($nama_kelas))[0]);
$jurusan_key = '';

if (stripos($nama_kelas, 'RPL') !== false) {
    $jurusan_key = 'RPL';
} elseif (stripos($nama_kelas, 'AKL') !== false) {
    $jurusan_key = 'AKL';
} elseif (stripos($nama_kelas, 'AKT') !== false) {
    $jurusan_key = 'AKT';
} elseif (stripos($nama_kelas, 'BR') !== false) {
    $jurusan_key = 'BR';
} elseif (stripos($nama_kelas, 'MP') !== false) {
    $jurusan_key = 'MP';
}

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_romawi);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_key);

// Prioritas ambil mapel dari guru_mengajar agar sama dengan halaman wali kelas/validasi.
$qCekGM = $conn->query("SELECT COUNT(DISTINCT gm.id_mapel) AS total
                        FROM guru_mengajar gm
                        JOIN mapel m ON gm.id_mapel = m.id
                        WHERE gm.id_kelas = '$id_kelas_safe'
                        AND m.tingkat = '$tingkat_safe'
                        AND m.nama_mapel NOT LIKE '%PKL%'
                        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                        AND m.nama_mapel NOT LIKE '%Prakerin%'");
$jml_gm = ($qCekGM && $qCekGM->num_rows > 0) ? (int)$qCekGM->fetch_assoc()['total'] : 0;

if($jml_gm > 0){
    $qMapel = $conn->query("SELECT DISTINCT m.id, m.nama_mapel, m.kkm, m.kelompok, m.urutan
                            FROM guru_mengajar gm
                            JOIN mapel m ON gm.id_mapel = m.id
                            WHERE gm.id_kelas = '$id_kelas_safe'
                            AND m.tingkat = '$tingkat_safe'
                            AND m.nama_mapel NOT LIKE '%PKL%'
                            AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                            AND m.nama_mapel NOT LIKE '%Prakerin%'
                            ORDER BY 
                                CASE 
                                    WHEN m.kelompok = 'A' THEN 1
                                    WHEN m.kelompok = 'B' THEN 2
                                    WHEN m.kelompok = 'C1' THEN 3
                                    WHEN m.kelompok = 'C2' THEN 4
                                    WHEN m.kelompok = 'C3' THEN 5
                                    ELSE 6
                                END ASC,
                                m.urutan ASC,
                                m.nama_mapel ASC");
} else {
    $qMapel = $conn->query("SELECT id, nama_mapel, kkm, kelompok, urutan
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
                                CASE 
                                    WHEN kelompok = 'A' THEN 1
                                    WHEN kelompok = 'B' THEN 2
                                    WHEN kelompok = 'C1' THEN 3
                                    WHEN kelompok = 'C2' THEN 4
                                    WHEN kelompok = 'C3' THEN 5
                                    ELSE 6
                                END ASC,
                                urutan ASC,
                                nama_mapel ASC");
}

$list_mapel = [];
if($qMapel && $qMapel->num_rows > 0) {
    while($m = $qMapel->fetch_assoc()) {
        $list_mapel[] = $m;
    }
}

// --- CEK KELENGKAPAN NILAI UAS SEBELUM LEDGER DIBUKA ---
if($jenis == 'uas'){
    $ada_data_uas_belum_lengkap = false;
    $total_mapel_wajib = count($list_mapel);

    // 🩹 FIX: tambah cek riwayat_kelas tahun aktif sbg cadangan kalau kolom master siswa.id_kelas nyasar.
    $qCekSiswaUAS = $conn->query("SELECT id FROM siswa 
                                  WHERE (id_kelas = '$id_kelas_safe' 
                                      OR id IN (
                                          SELECT rk.id_siswa FROM riwayat_kelas rk 
                                          WHERE rk.id_kelas = '$id_kelas_safe' 
                                          AND rk.id_tahun_ajaran = '$id_tahun_aktif_ledger_safe'
                                      ))
                                  ORDER BY nama_siswa ASC");

    if($qCekSiswaUAS && $qCekSiswaUAS->num_rows > 0){
        while($cekS = $qCekSiswaUAS->fetch_assoc()){
            $id_s_cek = $cekS['id'];
            $mapel_uas_lengkap = 0;

            foreach($list_mapel as $mpCek){
                $id_m_cek = $mpCek['id'];

                $qCekNilai = $conn->query("SELECT nilai_t, nilai_p, nilai_sts, nilai_k, nilai_sas, status_validasi
                                           FROM nilai 
                                           WHERE id_siswa = '$id_s_cek' 
                                           AND id_mapel = '$id_m_cek' 
                                           LIMIT 1");

                if($qCekNilai && $qCekNilai->num_rows > 0){
                    $dnCek = $qCekNilai->fetch_assoc();

                    $nilai_sts_cek = ($dnCek['nilai_p'] > 0) ? $dnCek['nilai_p'] : ($dnCek['nilai_sts'] ?? 0);
                    $nilai_sas_cek = ($dnCek['nilai_k'] > 0) ? $dnCek['nilai_k'] : ($dnCek['nilai_sas'] ?? 0);

                    $tp_ada  = ($dnCek['nilai_t'] !== null && $dnCek['nilai_t'] !== '' && $dnCek['nilai_t'] > 0);
                    $sts_ada = ($nilai_sts_cek !== null && $nilai_sts_cek !== '' && $nilai_sts_cek > 0);
                    $sas_ada = ($nilai_sas_cek !== null && $nilai_sas_cek !== '' && $nilai_sas_cek > 0);

                    if($tp_ada && $sts_ada && $sas_ada){
                        $mapel_uas_lengkap++;
                    }
                }
            }

            if($total_mapel_wajib > 0 && $mapel_uas_lengkap < $total_mapel_wajib){
                $ada_data_uas_belum_lengkap = true;
                break;
            }
        }
    }

    if($ada_data_uas_belum_lengkap){
        ?>
        <style>
            .ledger-warning-card {
                max-width: 720px;
                margin: 70px auto;
                border: 1px solid #F2A6B3;
                background: #FFF5F7;
                border-radius: 22px;
                padding: 28px;
                box-shadow: 0 8px 24px rgba(132, 32, 41, 0.08);
                font-family: Arial, sans-serif;
                color: #842029;
            }

            .ledger-warning-card h4 {
                font-weight: 800;
                margin-bottom: 10px;
            }

            .ledger-warning-card p {
                margin-bottom: 8px;
                color: #7A1F2B;
            }

            .btn-ledger-back-warning {
                background: #D8E9FA;
                color: #0d385e;
                border: 1px solid #AECDEE;
                border-radius: 50px;
                padding: 10px 22px;
                font-weight: 800;
                text-decoration: none;
                display: inline-block;
            }
        </style>

        <div class="ledger-warning-card">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Ledger UAS belum bisa dibuka</h4>
            <p>Nilai UAS/SAS kelas <b><?= $nama_kelas ?></b> belum diinput lengkap atau belum divalidasi.</p>
            <p>Ledger UAS baru bisa dibuka setelah semua nilai <b>TP, STS, dan SAS</b> sudah lengkap.</p>

            <a href="index.php?page=wali_siswa&id_kelas=<?= $id_kelas ?>" class="btn-ledger-back-warning">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
        <?php
        exit;
    }
}

// 4. LOGIKA DATA SISWA & NILAI
$data_ledger = [];
// 🩹 FIX: tambah cek riwayat_kelas tahun aktif sbg cadangan kalau kolom master siswa.id_kelas nyasar.
$qSiswa = $conn->query("SELECT id, nama_siswa, nis, nisn FROM siswa 
                        WHERE (id_kelas = '$id_kelas_safe' 
                            OR id IN (
                                SELECT rk.id_siswa FROM riwayat_kelas rk 
                                WHERE rk.id_kelas = '$id_kelas_safe' 
                                AND rk.id_tahun_ajaran = '$id_tahun_aktif_ledger_safe'
                            ))
                        ORDER BY nama_siswa ASC");

while ($s = $qSiswa->fetch_assoc()) {
    $id_s = $s['id'];
    
    // Tarik Absensi
    $qAbsen = $conn->query("SELECT sakit, izin, alpha FROM absensi WHERE id_siswa = '$id_s' AND semester = '$semester_db' LIMIT 1");
    $da = $qAbsen->fetch_assoc();

    // Logika Nilai per Mapel
    $nilai_per_mapel = []; 
    $total_na_siswa = 0; 
    $jml_mapel_ada = 0;

    foreach ($list_mapel as $mp) {
        $id_m = $mp['id'];
        
        $qN = $conn->query("SELECT nilai_t, nilai_p, nilai_sts, nilai_k, nilai_sas FROM nilai WHERE id_siswa = '$id_s' AND id_mapel = '$id_m' LIMIT 1");
        $dn = $qN->fetch_assoc();
        
        if ($dn) {
            $tp = $dn['nilai_t'] ?? 0; 
            $sts = (!empty($dn['nilai_p'])) ? $dn['nilai_p'] : ($dn['nilai_sts'] ?? 0); 
            $sas = (!empty($dn['nilai_k'])) ? $dn['nilai_k'] : ($dn['nilai_sas'] ?? 0);
            
            // --- LOGIKA CERDAS: RUMUS UTS ATAU UAS ---
            if ($jenis == 'uts') {
                $na = round(((4 * $tp) + (2 * $sts)) / 6);
            } else {
                $na = round(((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10);
            }
            
            $nilai_per_mapel[$id_m] = $na; 
            $total_na_siswa += $na; 
            $jml_mapel_ada++;
        } else { 
            $nilai_per_mapel[$id_m] = "-"; 
        }
    }
    
    $rata = ($jml_mapel_ada > 0) ? round($total_na_siswa / $jml_mapel_ada, 2) : 0;
    $data_ledger[] = [
        'id' => $id_s, 'nama' => $s['nama_siswa'], 'nis' => $s['nis'], 'nisn' => $s['nisn'], 
        'nilai' => $nilai_per_mapel, 
        'rata' => $rata,
        'absen' => ['s' => $da['sakit'] ?? 0, 'i' => $da['izin'] ?? 0, 'a' => $da['alpha'] ?? 0]
    ];
}

// Sorting Berdasarkan Rank Rata-rata
usort($data_ledger, function($a, $b) { return $b['rata'] <=> $a['rata']; });
$rank_map = []; foreach($data_ledger as $key => $val) { $rank_map[$val['id']] = $key + 1; }
// Balikin sorting abjad buat tampilan
usort($data_ledger, function($a, $b) { return $a['nama'] <=> $b['nama']; });
?>

<style>
    .table-ledger { font-size: 8px; border: 1px solid #000 !important; border-collapse: collapse !important; width: 100%; }
    .table-ledger th, .table-ledger td { border: 1px solid #000 !important; padding: 4px 3px !important; }
    .bg-absen { background-color: #FFF9C4 !important; }
    .bg-header-nilai { background-color: <?= ($jenis == 'uts') ? '#0dcaf0' : '#198754' ?> !important; color: white !important; }
    .th-mapel { width: 45px; white-space: normal !important; word-wrap: break-word; line-height: 1.1; vertical-align: middle !important; background: #f2f2f2 !important; font-size: 8px; }
    
    @media print {
        @page { size: A4 landscape; margin: 0.5cm; }
        body { zoom: 65%; -webkit-print-color-adjust: exact; background: white !important;}
        .d-print-none { display: none !important; }
        .table-ledger { table-layout: fixed !important; width: 100% !important; }
    }
</style>

<div class="card border-0 shadow-sm p-3 mb-3 bg-white d-print-none">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="index.php?page=wali_siswa&id_kelas=<?= $id_kelas ?>" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm d-print-none" style="background:#D8E9FA; color:#0d385e; border:1px solid #AECDEE;">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>

        <button onclick="document.title='<?= $nama_file_pdf ?>'; window.print();" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm d-print-none" style="background:#6FA7DF; color:#fff; border:none;">
            <i class="fas fa-print me-2"></i>Cetak Ledger <?= strtoupper($jenis) ?>
        </button>
    </div>
</div>

<div style="text-align: center; font-weight: bold; text-transform: uppercase; margin-bottom: 15px;">
    <h5 style="margin: 0; font-size: 14px;"><?= $judul_ledger ?></h5>
    <h5 style="margin: 0; font-size: 14px;">SEMESTER <?= strtoupper($semester_db) ?></h5>
    <h5 style="margin: 0; font-size: 14px;"><?= strtoupper($nama_sekolah) ?></h5>
    <p style="margin: 0; font-size: 11px;">Tahun Pelajaran: <?= $thn_pelajaran ?></p>
</div>

<div style="font-size: 11px; font-weight: bold; margin-bottom: 10px; display: flex; justify-content: space-between;">
    <div>Kelas: <?= $nama_kelas ?><br>Wali Kelas: <?= $nama_wali_display ?></div>
    <div style="text-align: right;">Semester: <?= $smt_angka ?></div>
</div>

<div class="table-responsive" style="overflow: visible !important;">
    <table class="table table-bordered align-middle text-center table-ledger">
        <thead class="fw-bold">
            <tr class="bg-light">
                <th rowspan="2" width="25">NO</th>
                <th rowspan="2" width="130">NAMA PESERTA DIDIK</th>
                <th colspan="<?= count($list_mapel) ?>" class="bg-header-nilai py-1">MATA PELAJARAN (NILAI AKHIR)</th>
                <th rowspan="2" width="35">RATA</th>
                <th rowspan="2" width="30">RANK</th>
                <th colspan="3" class="bg-absen">ABSENSI</th>
            </tr>
            <tr>
                <?php foreach($list_mapel as $mp): ?>
                    <th class="th-mapel"><?= str_replace([" ", "/"], "<br>", $mp['nama_mapel']) ?></th>
                <?php endforeach; ?>
                <th class="bg-absen" width="18">S</th><th class="bg-absen" width="18">I</th><th class="bg-absen" width="18">A</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach($data_ledger as $dl): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td class="text-start fw-bold px-2"><?= strtoupper($dl['nama']) ?></td>
                <?php foreach($list_mapel as $mp): ?>
                    <td class="fw-bold">
                        <?= $dl['nilai'][$mp['id']] ?>
                    </td>
                <?php endforeach; ?>
                <td class="fw-bold bg-light" style="font-size: 10px;"><?= $dl['rata'] ?></td>
                <td class="fw-bold" style="font-size: 10px;"><?= $rank_map[$dl['id']] ?></td>
                <td class="bg-absen"><?= $dl['absen']['s'] ?></td>
                <td class="bg-absen"><?= $dl['absen']['i'] ?></td>
                <td class="bg-absen"><?= $dl['absen']['a'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>