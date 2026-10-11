<?php
// --- 1. IDENTITAS GURU & CEK KELAS PERWALIAN ---
$nama_user_login = $_SESSION['guru']['nama'] ?? '';
$nip_guru        = $_SESSION['guru']['nip'] ?? '';

// Tarik ID guru dari database biar cocok sama fk id_wali_kelas
$qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip_guru' LIMIT 1");
$id_guru_login = ($qGuru && $qGuru->num_rows > 0) ? $qGuru->fetch_assoc()['id'] : 0;

// Kueri menggunakan id_wali_kelas
$qK = $conn->query("SELECT id, nama_kelas FROM kelas WHERE id_wali_kelas = '$id_guru_login'");
$list_kelas = $qK ? $qK->fetch_all(MYSQLI_ASSOC) : [];

if (count($list_kelas) == 0) {
    echo "<div class='alert alert-warning mt-4 fw-bold shadow-sm rounded-4'>
            <i class='fas fa-exclamation-triangle me-2'></i> Anda bukan Wali Kelas di kelas mana pun.
          </div>";
    exit;
}

$id_k_pilih = $_GET['id_kelas'] ?? $list_kelas[0]['id'];
$nama_k_pilih = "";

// 🩹 FITUR BARU: pilihan jenis ranking (UTS / UAS), biar rumusnya konsisten sesuai
// laporan rapor masing-masing, bukan otomatis "gonta-ganti rumus" per mapel kayak sebelumnya.
$jenis_ranking = $_GET['jenis_ranking'] ?? 'uas';
if(!in_array($jenis_ranking, ['uts', 'uas'])) $jenis_ranking = 'uas';

foreach($list_kelas as $k) { 
    if($k['id'] == $id_k_pilih) {
        $nama_k_pilih = $k['nama_kelas']; 
    }
}

// AMBIL SEMESTER & HARI EFEKTIF DARI TABEL TAHUN AJARAN YANG AKTIF
$qTahun = $conn->query("SELECT id, tahun, semester, hari_efektif FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");

if($qTahun && $qTahun->num_rows > 0){
    $dTahun = $qTahun->fetch_assoc();
    $tahun_pelajaran = $dTahun['tahun'] ?? '2025/2026';
    $sem_aktif = $dTahun['semester'] ?? 'GANJIL';
    $total_hari = $dTahun['hari_efektif'] ?? 100; 
    $id_tahun_aktif_rank_safe = mysqli_real_escape_string($conn, $dTahun['id'] ?? 0);
} else {
    $tahun_pelajaran = '2025/2026';
    $sem_aktif = 'GANJIL';
    $total_hari = 100;
    $id_tahun_aktif_rank_safe = 0;
}

// --- 2. LOGIKA HITUNG RANKING ---
$data_ranking = [];
$jumlah_siswa_ranking = 0;
$id_k_pilih_safe = mysqli_real_escape_string($conn, $id_k_pilih);
// 🩹 FIX: tambah cek riwayat_kelas tahun aktif sbg cadangan kalau kolom master siswa.id_kelas nyasar.
$qSiswa = $conn->query("SELECT id, nama_siswa, nisn FROM siswa 
                        WHERE (id_kelas = '$id_k_pilih_safe' 
                            OR id IN (
                                SELECT rk.id_siswa FROM riwayat_kelas rk 
                                WHERE rk.id_kelas = '$id_k_pilih_safe' 
                                AND rk.id_tahun_ajaran = '$id_tahun_aktif_rank_safe'
                            ))");

if($qSiswa) {
    while ($s = $qSiswa->fetch_assoc()) {
        $jumlah_siswa_ranking++;
        $id_s = $s['id'];

        // 🩹 FIX: sebelumnya query ini TIDAK difilter id_tahun_ajaran sama sekali, jadi
        // nilai dari tahun ajaran/semester lain (data lama, sisa test, dll) ikut kehitung
        // campur aduk ke rata-rata ranking siswa. Sekarang dikunci ke tahun ajaran aktif.
        $qNilai = $conn->query("SELECT n.nilai_t, n.nilai_p, n.nilai_k, m.kkm 
                                FROM nilai n 
                                JOIN mapel m ON n.id_mapel = m.id 
                                WHERE n.id_siswa = '$id_s'
                                AND n.id_tahun_ajaran = '$id_tahun_aktif_rank_safe'");
        
        $total_na = 0; 
        $total_kkm = 0; 
        $jml_mapel = $qNilai->num_rows; 
        $jml_remed = 0;
        $mapel_dihitung = 0;

        while ($n = $qNilai->fetch_assoc()) {
            $tp = $n['nilai_t']; 
            $sts = $n['nilai_p']; 
            $sas = $n['nilai_k'];

            // 🩹 FIX: rumus sekarang FIX sesuai jenis ranking yang dipilih (sama persis
            // dengan rapor_uts.php / rapor_uas.php), bukan otomatis gonta-ganti rumus per
            // mapel tergantung SAS udah keisi apa belum. Jadi angka "Rata-Rata" selalu
            // punya makna yang sama & konsisten sepanjang waktu.
            if($jenis_ranking == 'uts'){
                // Sama seperti rapor_uts.php: TP + STS saja, SAS tidak ikut dihitung sama sekali.
                $na = ((4 * $tp) + (2 * $sts)) / 6;
                $total_na += $na;
                $mapel_dihitung++;

                if ($na < $n['kkm']) $jml_remed++;
            } else {
                // Sama seperti rapor_uas.php: TP + STS + SAS. Mapel yang SAS-nya belum
                // diisi dilewati dari rata-rata (belum bisa dinilai UAS-nya), bukan dianggap 0.
                if(empty($sas) || $sas == 0) continue;

                $na = ((4 * $tp) + (2 * $sts) + (4 * $sas)) / 10;
                $total_na += $na;
                $mapel_dihitung++;

                if ($na < $n['kkm']) $jml_remed++;
            }

            $total_kkm += $n['kkm'];
        }
        
        $rata = ($mapel_dihitung > 0) ? ($total_na / $mapel_dihitung) : 0;
        $rata_kkm = ($jml_mapel > 0) ? ($total_kkm / $jml_mapel) : 75;

        // 🩹 FIX: sebelumnya query ini cuma difilter semester tanpa id_tahun_ajaran,
        // jadi kalau siswa punya >1 baris absensi (beda tahun ajaran) yang ke-ambil
        // bisa baris yang salah (misal baris lama yang masih 0) -> kehadiran ranking
        // nggak sinkron sama data yang diinput admin di halaman Input Absensi.
        $qAbsen = $conn->query("SELECT sakit, izin, alpha 
                                FROM absensi 
                                WHERE id_siswa = '$id_s' 
                                AND semester = '$sem_aktif' 
                                AND id_tahun_ajaran = '$id_tahun_aktif_rank_safe'
                                LIMIT 1");

        $a = ($qAbsen && $qAbsen->num_rows > 0) ? $qAbsen->fetch_assoc() : ['sakit'=>0, 'izin'=>0, 'alpha'=>0];
        
        $mangkir = ($a['sakit'] ?? 0) + ($a['izin'] ?? 0) + ($a['alpha'] ?? 0);
        $persen_h = round((($total_hari - $mangkir) / $total_hari) * 100, 1);

        $data_ranking[] = [
            'nama' => $s['nama_siswa'],
            'rata' => round($rata, 2),
            'rata_kkm' => $rata_kkm,
            'hadir' => ($persen_h < 0 ? 0 : $persen_h),
            'remed' => $jml_remed
        ];
    }

    usort($data_ranking, function($a, $b) { 
        return $b['rata'] <=> $a['rata']; 
    });
}
?>

<style>
    .ranking-filter-box {
        background: #ffffff;
        border-radius: 18px;
        padding: 18px 20px;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08);
        border-left: 5px solid #6FA7DF;
        margin-bottom: 20px;
    }

    .ranking-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 10px;
        display: block;
    }

    .btn-kelas-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        min-height: 36px;
        padding: 0 18px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 6px;
        margin-bottom: 6px;
        box-shadow: none !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
        text-decoration: none !important;
    }

    .btn-kelas-soft:hover,
    .btn-kelas-soft:focus,
    .btn-kelas-soft:active {
        background: #AECDEE !important;
        color: #0d385e !important;
        border-color: #9FC1E7 !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-kelas-soft.active {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border-color: #6FA7DF !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.28) !important;
    }

    .ranking-banner {
    background: #BFD3EA !important;
    border-radius: 22px;
    color: #0d385e;
    box-shadow: 0 8px 22px rgba(80, 115, 155, 0.12);
    position: relative;
    overflow: visible !important;
    border: 1px solid rgba(255,255,255,0.75);
    padding: 34px 28px 28px 28px;
    margin-bottom: 24px;
    }
    
    .ranking-banner::before {
        display: none !important;
        content: none !important;
    }

    .ranking-title {
        color: #0d385e;
        font-weight: 800;
        line-height: 1.35 !important;
        margin-top: 0 !important;
        margin-bottom: 8px !important;
        overflow: visible !important;
        padding-top: 2px;
    }

    .ranking-desc {
        color: #244b70 !important;
        font-weight: 600;
    }

    .btn-print-ranking {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        height: 38px;
        padding: 0 20px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.24) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-print-ranking:hover,
    .btn-print-ranking:focus,
    .btn-print-ranking:active {
        background: #5E97D2 !important;
        color: #ffffff !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .rank-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
    }

    .rank-top {
        background: #FFD98A;
        color: #7A5200;
        border: 1px solid #F4C96D;
    }

    .rank-normal {
        background: #D8E9FA;
        color: #0d385e;
        border: 1px solid #AECDEE;
    }

    .status-tuntas {
        background: #DDF5EA;
        color: #1f5f48;
        border: 1px solid #9DDCC4;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-remedial {
        background: #FFE3E8;
        color: #842029;
        border: 1px solid #F2A6B3;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .hadir-soft {
        color: #5E9F86 !important;
        font-weight: 800;
    }

    button,
    button:hover,
    button:focus,
    button:active,
    .btn,
    .btn:hover,
    .btn:focus,
    .btn:active {
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

        .ranking-banner {
            background: #ffffff !important;
            color: #000000 !important;
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
        }

        .ranking-banner::before {
            display: none !important;
        }
    }

    /* Tampilan cetak ranking */
    @page {
        size: A4 portrait;
        margin: 14mm 12mm;
    }

    @media print {
        html, body {
            width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: Arial, Helvetica, sans-serif !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body * {
            visibility: hidden !important;
        }

        .ranking-page,
        .ranking-page * {
            visibility: visible !important;
        }

        .ranking-page {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        .ranking-filter-box,
        .btn-print-ranking,
        .navbar,
        .sidebar,
        .d-print-none {
            display: none !important;
        }

        .ranking-banner {
            background: #ffffff !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 0 14px 0 !important;
            text-align: center !important;
            color: #000000 !important;
            overflow: visible !important;
        }

        .ranking-banner .d-flex {
            display: block !important;
        }

        .ranking-title {
            color: #000000 !important;
            font-size: 18px !important;
            line-height: 1.35 !important;
            font-weight: 800 !important;
            text-align: center !important;
            text-transform: uppercase !important;
            margin: 0 0 6px 0 !important;
            padding: 0 !important;
        }

        .ranking-title i {
            display: none !important;
        }

        .ranking-desc {
            display: block !important;
            color: #000000 !important;
            font-size: 12px !important;
            line-height: 1.4 !important;
            font-weight: 600 !important;
            text-align: center !important;
            margin: 0 0 12px 0 !important;
        }

        .card {
            width: 100% !important;
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        .table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
            font-size: 11px !important;
            color: #000000 !important;
        }

        .table thead {
            display: table-header-group !important;
        }

        .table tr {
            page-break-inside: avoid !important;
        }

        .table th,
        .table td {
            border: 1px solid #000000 !important;
            padding: 7px 6px !important;
            background: #ffffff !important;
            color: #000000 !important;
            vertical-align: middle !important;
        }

        .table th {
            font-size: 10px !important;
            font-weight: 800 !important;
            text-align: center !important;
            text-transform: uppercase !important;
        }

        .table td {
            font-size: 10.5px !important;
        }

        .table th:nth-child(1), .table td:nth-child(1) {
            width: 55px !important;
            text-align: center !important;
        }

        .table th:nth-child(2), .table td:nth-child(2) {
            text-align: left !important;
            font-weight: 700 !important;
        }

        .table th:nth-child(3), .table td:nth-child(3),
        .table th:nth-child(4), .table td:nth-child(4) {
            width: 85px !important;
            text-align: center !important;
        }

        .table th:nth-child(5), .table td:nth-child(5) {
            width: 150px !important;
            text-align: center !important;
        }

        .rank-badge,
        .rank-top,
        .rank-normal {
            display: inline !important;
            width: auto !important;
            height: auto !important;
            padding: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: #000000 !important;
            font-weight: 800 !important;
        }

        .status-tuntas,
        .status-remedial {
            display: inline !important;
            background: transparent !important;
            color: #000000 !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            font-size: 10px !important;
            font-weight: 700 !important;
        }

        .status-tuntas i,
        .status-remedial i {
            display: none !important;
        }

        .hadir-soft,
        .text-danger,
        .text-dark,
        .fw-bold,
        .fs-5 {
            color: #000000 !important;
            font-size: 10.5px !important;
            font-weight: 700 !important;
        }

        a[href]::after {
            content: "" !important;
        }
    }

</style>

<div class="ranking-page">
<div class="ranking-filter-box">
    <label class="ranking-label">
        <i class="fas fa-filter me-1"></i> Pilih Kelas Perwalian
    </label>

    <div class="d-flex flex-wrap">
        <?php foreach($list_kelas as $lk): ?>
            <a href="?page=ranking&id_kelas=<?= $lk['id'] ?>&jenis_ranking=<?= $jenis_ranking ?>" 
               class="btn-kelas-soft <?= ($id_k_pilih == $lk['id']) ? 'active' : '' ?>">
                <?= $lk['nama_kelas'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="ranking-filter-box mt-3">
    <label class="ranking-label">
        <i class="fas fa-list-ol me-1"></i> Jenis Ranking
    </label>

    <div class="d-flex flex-wrap">
        <a href="?page=ranking&id_kelas=<?= $id_k_pilih ?>&jenis_ranking=uts" 
           class="btn-kelas-soft <?= ($jenis_ranking == 'uts') ? 'active' : '' ?>">
            Ranking UTS
        </a>
        <a href="?page=ranking&id_kelas=<?= $id_k_pilih ?>&jenis_ranking=uas" 
           class="btn-kelas-soft <?= ($jenis_ranking == 'uas') ? 'active' : '' ?>">
            Ranking UAS
        </a>
    </div>
    <small class="text-muted d-block mt-2">
        <?php if($jenis_ranking == 'uts'): ?>
             <?php else: ?>
            <?php endif; ?>
    </small>
</div>

<div class="ranking-banner">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="ranking-title mb-1">
                <i class="fas fa-trophy me-2"></i> Peringkat <?= strtoupper($jenis_ranking) ?> Kelas <?= htmlspecialchars($nama_k_pilih) ?>
            </h4>

            <small class="ranking-desc">
                Semester <?= ucfirst(strtolower($sem_aktif)) ?> • Tahun Pelajaran <?= htmlspecialchars($tahun_pelajaran) ?> • Jumlah Siswa: <?= $jumlah_siswa_ranking ?> Orang
            </small>
        </div>

        <button onclick="window.print()" class="btn-print-ranking">
            <i class="fas fa-print"></i> Cetak Ranking
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light text-center small fw-bold text-muted text-uppercase">
            <tr>
                <th width="70" class="py-3">Rank</th>
                <th class="text-start">Nama Siswa</th>
                <th>Rata-Rata <?= strtoupper($jenis_ranking) ?></th>
                <th>Kehadiran</th>
                <th>Status Ketuntasan</th>
            </tr>
        </thead>

        <tbody>
            <?php 
            $rank = 1; 
            foreach($data_ranking as $r): 
                $is_danger = ($r['rata'] < $r['rata_kkm']) ? 'text-danger fw-bold' : 'text-dark fw-bold';
            ?>
            <tr class="text-center">
                <td>
                    <span class="rank-badge <?= ($rank <= 3) ? 'rank-top' : 'rank-normal' ?>">
                        <?= $rank++ ?>
                    </span>
                </td>

                <td class="text-start fw-bold">
                    <?= strtoupper($r['nama']) ?>
                </td>

                <td class="<?= $is_danger ?> fs-5">
                    <?= number_format($r['rata'], 2) ?>
                </td>

                <td class="hadir-soft">
                    <?= $r['hadir'] ?>%
                </td>

                <td>
                    <?php if($r['remed'] > 0): ?>
                        <span class="status-remedial">
                            <i class="fas fa-exclamation-circle"></i> <?= $r['remed'] ?> Mapel Remedial
                        </span>
                    <?php else: ?>
                        <span class="status-tuntas">
                            <i class="fas fa-check-circle"></i> Tuntas
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if(empty($data_ranking)): ?>
            <tr>
                <td colspan="5" class="text-center py-5 text-muted fw-bold">
                    Belum ada data siswa di kelas ini.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div><!-- end ranking-page -->