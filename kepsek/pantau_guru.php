<?php
$search_guru = $_GET['search_guru'] ?? '';
$where_guru = "WHERE 1=1";

// Tahun ajaran yang lagi dipilih (default: tahun ajaran aktif, dari index.php)
$ta_pilih_safe = (int)$id_ta_pilih;

if(!empty($search_guru)){
    $search_safe = mysqli_real_escape_string($conn, $search_guru);
    $where_guru .= " AND (nama_guru LIKE '%$search_safe%' OR nip LIKE '%$search_safe%')";
}

// --- HELPER CEK KOLOM ---
function kolomAdaPantauGuru($conn, $table, $column){
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $q = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
    return ($q && $q->num_rows > 0);
}

$kolom_nilai_sts = kolomAdaPantauGuru($conn, 'nilai', 'nilai_sts');
$kolom_nilai_sas = kolomAdaPantauGuru($conn, 'nilai', 'nilai_sas');
$kolom_pkl_valid = kolomAdaPantauGuru($conn, 'data_pkl', 'status_validasi_pkl');

// Ekspresi nilai dibuat fleksibel supaya aman kalau database masih pakai kolom nilai_p/nilai_k saja.
$expr_sts = $kolom_nilai_sts ? "COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0)" : "COALESCE(NULLIF(n.nilai_p,''), 0)";
$expr_sas = $kolom_nilai_sas ? "COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0)" : "COALESCE(NULLIF(n.nilai_k,''), 0)";
$expr_pkl_valid = $kolom_pkl_valid ? "dp.status_validasi_pkl = 1 AND" : "";

// --- AMBIL DATA GURU SESUAI FILTER ---
$qGuru = $conn->query("SELECT id, nip, nama_guru FROM guru $where_guru ORDER BY nama_guru ASC");
$total_guru_tampil = ($qGuru) ? $qGuru->num_rows : 0;
?>

<style>
    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.18) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-blue:hover,
    .btn-soft-blue:focus,
    .btn-soft-blue:active {
        background: #5E97D2 !important;
        color: #ffffff !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-reset {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #E8D5AB !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(214, 189, 145, 0.18) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-soft-reset:hover,
    .btn-soft-reset:focus,
    .btn-soft-reset:active {
        background: #EED9AF !important;
        color: #76542D !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .guru-search-input {
        height: 45px !important;
        border-radius: 50px !important;
        border: 1px solid #dbe5ef !important;
        padding-left: 44px !important;
        color: #0d385e !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
    }

    .guru-search-input:focus {
        border-color: #9fc1e7 !important;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14) !important;
    }

    .search-icon-wrapper { position: relative; }
    .search-icon-wrapper i {
        position: absolute;
        left: 18px;
        top: 15px;
        color: #9aabbc;
        z-index: 2;
    }

    .badge-total-soft {
        background: #B9DDCC !important;
        color: #2E5E4E !important;
        border: 1px solid #9FCDB8 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
    }

    .status-soft-success {
        background: #B9DDCC !important;
        color: #1F5F48 !important;
        border: 1px solid #7FC1A5 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 5px 12px !important;
        min-width: 104px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .status-soft-warning {
        background: #F6E8C9 !important;
        color: #87653B !important;
        border: 1px solid #D8BC82 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 5px 12px !important;
        min-width: 104px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .status-soft-danger {
        background: #FFD6DE !important;
        color: #8A1E2E !important;
        border: 1px solid #F09AAD !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 5px 12px !important;
        min-width: 104px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .status-soft-secondary {
        background: #E9EEF5 !important;
        color: #506070 !important;
        border: 1px solid #CBD6E2 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 5px 12px !important;
        min-width: 104px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 12px !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .btn-guru-search {
        height: 40px !important;
        min-width: 115px !important;
        padding: 0 22px !important;
        font-size: 14px !important;
        line-height: 1 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
    }

    .btn-guru-reset {
        height: 40px !important;
        min-width: 85px !important;
        padding: 0 18px !important;
        font-size: 14px !important;
        line-height: 1 !important;
    }

    .progress-bar-soft-green { background-color: #5E9F86 !important; }
    .progress-bar-soft-yellow { background-color: #E7B24D !important; }
    .progress-bar-soft-red { background-color: #E66F82 !important; }
    .progress-bar-soft-gray { background-color: #9AA8B5 !important; }
    .progress-mini-title { font-size: 11px; font-weight: 800; color:#64748b; }
    .progress-mini-percent { font-size: 11px; font-weight: 800; color:#111827; }
</style>

<div class="card border-0 shadow-sm p-4 mb-4 bg-white" style="border-radius:15px; border-left: 5px solid #6FA7DF;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="fw-bold mb-1" style="color:#0d385e;">
                <i class="fas fa-chalkboard-teacher me-2 text-success"></i>Monitoring Kinerja Guru
            </h5>
            <p class="text-muted small mb-0">Pantau progres nilai UTS, UAS, dan PKL oleh masing-masing guru.</p>
        </div>
        <div class="text-end">
            <span class="badge badge-total-soft px-3 py-2 shadow-sm">Total <?= $total_guru_tampil ?> Guru</span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
    <form method="GET" action="">
        <input type="hidden" name="page" value="pantau_guru">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="search-icon-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search_guru" class="form-control guru-search-input" placeholder="Cari nama guru atau NIP..." value="<?= htmlspecialchars($search_guru) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="ta" class="form-select" onchange="this.form.submit();">
                    <?php foreach($daftar_tahun_ajaran as $ta): ?>
                        <option value="<?= $ta['id'] ?>" <?= ($id_ta_pilih == $ta['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?><?= ($ta['status'] == 'Aktif') ? ' (Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-soft-blue btn-guru-search">
                    <i class="fas fa-search"></i> Cari
                </button>
                <?php if(!empty($search_guru)): ?>
                    <a href="?page=pantau_guru&ta=<?= (int)$id_ta_pilih ?>" class="btn btn-soft-reset btn-guru-reset d-flex align-items-center justify-content-center">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-center small text-muted text-uppercase fw-bold">
                <tr>
                    <th width="50" class="py-3">NO</th>
                    <th class="text-start">NAMA GURU</th>
                    <th>BEBAN REGULER</th>
                    <th width="210">PROGRES UTS</th>
                    <th width="210">PROGRES UAS</th>
                    <th width="210">PROGRES PKL</th>
                    <th width="150">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if($qGuru && $qGuru->num_rows > 0):
                    while($g = $qGuru->fetch_assoc()): 
                        $id_g = (int)$g['id'];

                        // --- 1. AMBIL TUGAS MAPEL REGULER SAJA ---
                        // PKL tidak ikut dihitung di beban reguler, karena PKL punya progress sendiri.
                        $tugas_reguler = [];
                        $qMengajar = $conn->query("SELECT DISTINCT gm.id_mapel, gm.id_kelas, m.nama_mapel, k.nama_kelas
                                                    FROM guru_mengajar gm
                                                    JOIN mapel m ON gm.id_mapel = m.id
                                                    JOIN kelas k ON gm.id_kelas = k.id
                                                    WHERE gm.id_guru = '$id_g'
                                                    AND gm.id_tahun_ajaran = '$ta_pilih_safe'
                                                    AND m.nama_mapel NOT LIKE '%PKL%'
                                                    AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
                                                    AND m.nama_mapel NOT LIKE '%Prakerin%'
                                                    ORDER BY k.nama_kelas ASC, m.nama_mapel ASC");
                        if($qMengajar){
                            while($dm = $qMengajar->fetch_assoc()){
                                $tugas_reguler[] = $dm;
                            }
                        }

                        $total_tugas_reguler = count($tugas_reguler);
                        $uts_selesai = 0;
                        $uas_selesai = 0;

                        // --- 2. HITUNG PROGRES UTS DAN UAS PER MAPEL/KELAS ---
                        // UTS dianggap selesai kalau semua siswa di kelas tersebut sudah punya TP dan STS.
                        // UAS dianggap selesai kalau semua siswa di kelas tersebut sudah punya TP, STS, dan SAS.
                        foreach($tugas_reguler as $tugas){
                            $id_mapel = (int)$tugas['id_mapel'];
                            $id_kelas = (int)$tugas['id_kelas'];

                            $qCekNilai = $conn->query("SELECT
                                                        COUNT(DISTINCT rk.id_siswa) AS total_siswa,
                                                        SUM(CASE
                                                            WHEN n.id IS NOT NULL
                                                            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                            AND $expr_sts > 0
                                                            THEN 1 ELSE 0 END
                                                        ) AS total_uts_lengkap,
                                                        SUM(CASE
                                                            WHEN n.id IS NOT NULL
                                                            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
                                                            AND $expr_sts > 0
                                                            AND $expr_sas > 0
                                                            THEN 1 ELSE 0 END
                                                        ) AS total_uas_lengkap
                                                      FROM riwayat_kelas rk
                                                      LEFT JOIN nilai n ON n.id_siswa = rk.id_siswa AND n.id_mapel = '$id_mapel' AND n.id_tahun_ajaran = '$ta_pilih_safe'
                                                      WHERE rk.id_kelas = '$id_kelas'
                                                      AND rk.id_tahun_ajaran = '$ta_pilih_safe'");

                            $dCekNilai = ($qCekNilai && $qCekNilai->num_rows > 0) ? $qCekNilai->fetch_assoc() : [];
                            $total_siswa = (int)($dCekNilai['total_siswa'] ?? 0);
                            $total_uts_lengkap = (int)($dCekNilai['total_uts_lengkap'] ?? 0);
                            $total_uas_lengkap = (int)($dCekNilai['total_uas_lengkap'] ?? 0);

                            if($total_siswa > 0 && $total_uts_lengkap >= $total_siswa){
                                $uts_selesai++;
                            }

                            if($total_siswa > 0 && $total_uas_lengkap >= $total_siswa){
                                $uas_selesai++;
                            }
                        }

                        $progres_uts = ($total_tugas_reguler > 0) ? round(($uts_selesai / $total_tugas_reguler) * 100) : 0;
                        $progres_uas = ($total_tugas_reguler > 0) ? round(($uas_selesai / $total_tugas_reguler) * 100) : 0;

                        // --- 3. HITUNG PROGRES PKL DARI DATA_PKL ---
                        // Guru pembimbing PKL dibaca dari data_pkl.id_guru_pembimbing, bukan guru_mengajar.
                        $qPKL = $conn->query("SELECT
                                                COUNT(DISTINCT dp.id_siswa) AS total_siswa_pkl,
                                                COUNT(DISTINCT CASE
                                                    WHEN $expr_pkl_valid
                                                         dp.tp1_skor IS NOT NULL AND dp.tp1_skor != '' AND dp.tp1_skor > 0
                                                     AND dp.tp2_skor IS NOT NULL AND dp.tp2_skor != '' AND dp.tp2_skor > 0
                                                     AND dp.tp3_skor IS NOT NULL AND dp.tp3_skor != '' AND dp.tp3_skor > 0
                                                     AND dp.tp4_skor IS NOT NULL AND dp.tp4_skor != '' AND dp.tp4_skor > 0
                                                    THEN dp.id_siswa END
                                                ) AS total_selesai_pkl
                                              FROM data_pkl dp
                                              WHERE dp.id_guru_pembimbing = '$id_g'
                                              AND dp.id_tahun_ajaran = '$ta_pilih_safe'");

                        $dPKL = ($qPKL && $qPKL->num_rows > 0) ? $qPKL->fetch_assoc() : [];
                        $total_siswa_pkl = (int)($dPKL['total_siswa_pkl'] ?? 0);
                        $total_selesai_pkl = (int)($dPKL['total_selesai_pkl'] ?? 0);
                        $progres_pkl = ($total_siswa_pkl > 0) ? round(($total_selesai_pkl / $total_siswa_pkl) * 100) : 0;

                        // --- 4. STATUS UTAMA ---
                        $punya_tugas = ($total_tugas_reguler > 0 || $total_siswa_pkl > 0);
                        $semua_reguler_selesai = ($total_tugas_reguler == 0 || ($progres_uts == 100 && $progres_uas == 100));
                        $semua_pkl_selesai = ($total_siswa_pkl == 0 || $progres_pkl == 100);

                        $total_unit = $total_tugas_reguler + ($total_siswa_pkl > 0 ? 1 : 0);
                        $unit_selesai = 0;
                        if($total_tugas_reguler > 0 && $progres_uts == 100 && $progres_uas == 100) $unit_selesai += $total_tugas_reguler;
                        if($total_siswa_pkl > 0 && $progres_pkl == 100) $unit_selesai += 1;

                        if(!$punya_tugas){
                            $status_badge = "<span class='status-soft-secondary'>Belum Ada Tugas</span>";
                        } elseif($semua_reguler_selesai && $semua_pkl_selesai){
                            $status_badge = "<span class='status-soft-success'><i class='fas fa-check-double'></i>Selesai</span>";
                        } elseif($unit_selesai > 0 || $progres_uts > 0 || $progres_uas > 0 || $progres_pkl > 0){
                            $status_badge = "<span class='status-soft-warning'><i class='fas fa-spinner'></i>Proses</span>";
                        } else {
                            $status_badge = "<span class='status-soft-danger'><i class='fas fa-times'></i>Belum Input</span>";
                        }

                        if(!function_exists('warnaBarPantauGuru')){
                            function warnaBarPantauGuru($progres, $punya_data){
                                if(!$punya_data) return 'progress-bar-soft-gray';
                                if($progres == 100) return 'progress-bar-soft-green';
                                if($progres > 0) return 'progress-bar-soft-yellow';
                                return 'progress-bar-soft-red';
                            }
                        }

                        $warna_uts = warnaBarPantauGuru($progres_uts, $total_tugas_reguler > 0);
                        $warna_uas = warnaBarPantauGuru($progres_uas, $total_tugas_reguler > 0);
                        $warna_pkl = warnaBarPantauGuru($progres_pkl, $total_siswa_pkl > 0);
                ?>
                <tr class="text-center">
                    <td class="text-muted fw-bold"><?= $no++ ?></td>
                    <td class="text-start">
                        <span class="fw-bold d-block text-dark"><?= htmlspecialchars($g['nama_guru']) ?></span>
                        <small class="text-muted">NIP: <?= htmlspecialchars($g['nip'] ?: '-') ?></small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border px-2 py-1 mb-1 d-inline-block">
                            <i class="fas fa-book-open me-1 text-primary"></i> <?= $total_tugas_reguler ?> Mapel/Kelas
                        </span>
                        <?php if($total_siswa_pkl > 0): ?>
                            <br>
                            <span class="badge bg-light text-dark border px-2 py-1 d-inline-block">
                                <i class="fas fa-briefcase me-1 text-warning"></i> <?= $total_siswa_pkl ?> Siswa PKL
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="progress-mini-title">UTS</span>
                            <span class="progress-mini-percent"><?= $progres_uts ?>% <small class="text-muted">(<?= $uts_selesai ?>/<?= $total_tugas_reguler ?>)</small></span>
                        </div>
                        <div class="progress shadow-sm" style="height: 8px; border-radius: 50px; background:#eef1f4;">
                            <div class="progress-bar <?= $warna_uts ?> progress-bar-striped <?= ($progres_uts < 100 && $progres_uts > 0) ? 'progress-bar-animated' : '' ?>" role="progressbar" style="width: <?= $progres_uts ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="progress-mini-title">UAS</span>
                            <span class="progress-mini-percent"><?= $progres_uas ?>% <small class="text-muted">(<?= $uas_selesai ?>/<?= $total_tugas_reguler ?>)</small></span>
                        </div>
                        <div class="progress shadow-sm" style="height: 8px; border-radius: 50px; background:#eef1f4;">
                            <div class="progress-bar <?= $warna_uas ?> progress-bar-striped <?= ($progres_uas < 100 && $progres_uas > 0) ? 'progress-bar-animated' : '' ?>" role="progressbar" style="width: <?= $progres_uas ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="progress-mini-title">PKL</span>
                            <span class="progress-mini-percent"><?= $progres_pkl ?>% <small class="text-muted">(<?= $total_selesai_pkl ?>/<?= $total_siswa_pkl ?>)</small></span>
                        </div>
                        <div class="progress shadow-sm" style="height: 8px; border-radius: 50px; background:#eef1f4;">
                            <div class="progress-bar <?= $warna_pkl ?> progress-bar-striped <?= ($progres_pkl < 100 && $progres_pkl > 0) ? 'progress-bar-animated' : '' ?>" role="progressbar" style="width: <?= $progres_pkl ?>%"></div>
                        </div>
                    </td>
                    <td><?= $status_badge ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted fw-bold">
                        Guru tidak ditemukan.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>