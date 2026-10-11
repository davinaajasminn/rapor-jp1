<?php
session_start();

// Cek apakah yang login beneran kepsek
if(!isset($_SESSION['kepsek']) || $_SESSION['kepsek']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login");
    exit;
}

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil data dari session login
$nama_kepsek = $_SESSION['kepsek']['nama_lengkap'] ?? 'Bapak Kepala Sekolah';
$nip_kepsek = $_SESSION['kepsek']['nip'] ?? '-';
$id_kepsek = $_SESSION['kepsek']['id'] ?? 0;

// --- AMBIL FOTO PROFIL KEPSEK ---
$qKepsek = $conn->query("SELECT foto_profil FROM kepala_sekolah WHERE id = '$id_kepsek' LIMIT 1");
$dKepsek = $qKepsek ? $qKepsek->fetch_assoc() : null;
$foto_profil_kepsek = $dKepsek['foto_profil'] ?? '';

// Siapkan URL Foto buat Navbar
$avatar_nav = (!empty($foto_profil_kepsek) && file_exists("../assets/uploads/".$foto_profil_kepsek)) 
              ? "../assets/uploads/".$foto_profil_kepsek 
              : "https://ui-avatars.com/api/?name=".urlencode($nama_kepsek)."&background=random&color=fff&rounded=true&bold=true";

// Menghitung progres nilai UTS dan UAS sekolah, disamakan dengan halaman Pengesahan
function kolom_ada($conn, $table, $column){
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && $q->num_rows > 0);
}

function tambah_kolom_jika_belum_ada($conn){
    if(!kolom_ada($conn, 'identitas_sekolah', 'rapor_uts_dikunci')){
        $conn->query("ALTER TABLE identitas_sekolah ADD COLUMN rapor_uts_dikunci TINYINT(1) DEFAULT 0 AFTER rapor_dikunci");
    }

    if(!kolom_ada($conn, 'identitas_sekolah', 'rapor_uas_dikunci')){
        $conn->query("ALTER TABLE identitas_sekolah ADD COLUMN rapor_uas_dikunci TINYINT(1) DEFAULT 0 AFTER rapor_uts_dikunci");
    }
}

function hitung_progress_nilai($conn, $jenis = 'uts', $id_ta = 0){
    /*
        FIX SINKRON DENGAN PANTAU GURU:
        Progress pengesahan tidak lagi dihitung dari semua isi tabel nilai,
        karena cara itu bisa ikut menghitung data lama/dobel/mapel yang tidak dipakai kelas.

        Sekarang total dihitung dari tugas aktif:
        guru_mengajar -> mapel -> kelas -> siswa

        Yang dihitung hanya mapel reguler, PKL tidak ikut UTS/UAS.
        UTS lengkap jika TP + STS terisi.
        UAS lengkap jika TP + STS + SAS terisi.

        FIX TAHUN AJARAN:
        Progress sekarang dihitung KHUSUS untuk 1 tahun ajaran ($id_ta),
        supaya nilai tahun ajaran lama tidak ikut kehitung/numpuk begitu
        tahun ajaran baru mulai jalan.

        FIX ROSTER SISWA:
        Siswa per kelas diambil dari riwayat_kelas sesuai TA yang dipilih,
        BUKAN dari siswa.id_kelas (kondisi kelas siswa saat ini). Soalnya kalau
        TA yang dipilih itu TA lama, siswa yang dulu di kelas itu udah pindah/naik
        kelas sekarang, jadi join ke siswa.id_kelas bakal salah hitung siswanya.
    */

    $ta_safe = (int)$id_ta;

    $where_mapel_reguler = "
        m.nama_mapel NOT LIKE '%PKL%'
        AND m.nama_mapel NOT LIKE '%Praktik Kerja Lapangan%'
        AND m.nama_mapel NOT LIKE '%Prakerin%'
    ";

    if($jenis === 'uts'){
        $syarat_lengkap = "
            n.id IS NOT NULL
            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
        ";
    } else {
        $syarat_lengkap = "
            n.id IS NOT NULL
            AND n.nilai_t IS NOT NULL AND n.nilai_t != '' AND n.nilai_t > 0
            AND COALESCE(NULLIF(n.nilai_p,''), NULLIF(n.nilai_sts,''), 0) > 0
            AND COALESCE(NULLIF(n.nilai_k,''), NULLIF(n.nilai_sas,''), 0) > 0
        ";
    }

    $qProgress = $conn->query("SELECT 
                                    COUNT(*) AS total,
                                    SUM(CASE WHEN $syarat_lengkap THEN 1 ELSE 0 END) AS lengkap
                                FROM (
                                    SELECT DISTINCT
                                        gm.id_kelas,
                                        gm.id_mapel,
                                        s.id AS id_siswa
                                    FROM guru_mengajar gm
                                    JOIN mapel m ON gm.id_mapel = m.id
                                    JOIN kelas k ON gm.id_kelas = k.id
                                    JOIN riwayat_kelas rk ON rk.id_kelas = k.id AND rk.id_tahun_ajaran = '$ta_safe'
                                    JOIN siswa s ON s.id = rk.id_siswa
                                    WHERE gm.id_tahun_ajaran = '$ta_safe'
                                    AND $where_mapel_reguler
                                ) tugas
                                LEFT JOIN nilai n 
                                    ON n.id_siswa = tugas.id_siswa
                                    AND n.id_mapel = tugas.id_mapel
                                    AND n.id_tahun_ajaran = '$ta_safe'");

    if(!$qProgress || $qProgress->num_rows == 0){
        return [
            'progress' => 0,
            'total' => 0,
            'lengkap' => 0,
            'kurang' => 0
        ];
    }

    $dProgress = $qProgress->fetch_assoc();

    $total = (int)($dProgress['total'] ?? 0);
    $lengkap = (int)($dProgress['lengkap'] ?? 0);

    $progress = ($total > 0) ? round(($lengkap / $total) * 100) : 0;
    if($progress > 100) $progress = 100;

    return [
        'progress' => $progress,
        'total' => $total,
        'lengkap' => $lengkap,
        'kurang' => max(0, $total - $lengkap)
    ];
}



// Status kunci rapor (UTS/UAS) dipindah supaya per-tahun-ajaran, bukan 1 status global
// untuk semua tahun ajaran. Kolomnya ditambahkan otomatis ke tabel tahun_ajaran kalau belum ada,
// dan nilai lama dari identitas_sekolah dibawa sekali ke tahun ajaran yang sedang aktif.
function pastikan_kolom_lock_tahun_ajaran($conn){
    $baru_ditambah = false;

    if(!kolom_ada($conn, 'tahun_ajaran', 'rapor_uts_dikunci')){
        $conn->query("ALTER TABLE tahun_ajaran ADD COLUMN rapor_uts_dikunci TINYINT(1) DEFAULT 0");
        $baru_ditambah = true;
    }

    if(!kolom_ada($conn, 'tahun_ajaran', 'rapor_uas_dikunci')){
        $conn->query("ALTER TABLE tahun_ajaran ADD COLUMN rapor_uas_dikunci TINYINT(1) DEFAULT 0");
        $baru_ditambah = true;
    }

    if($baru_ditambah){
        $conn->query("UPDATE tahun_ajaran ta
                      JOIN identitas_sekolah i ON 1=1
                      SET ta.rapor_uts_dikunci = i.rapor_uts_dikunci,
                          ta.rapor_uas_dikunci = i.rapor_uas_dikunci
                      WHERE ta.status = 'Aktif'
                      LIMIT 1");
    }
}

tambah_kolom_jika_belum_ada($conn);
pastikan_kolom_lock_tahun_ajaran($conn);

// --- TAHUN AJARAN AKTIF (dipakai Dashboard: data yang tampil selalu ikut tahun ajaran ini) ---
$qTaAktif = $conn->query("SELECT id, tahun, semester, rapor_uts_dikunci, rapor_uas_dikunci FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTaAktif = ($qTaAktif && $qTaAktif->num_rows > 0) ? $qTaAktif->fetch_assoc() : [];

$id_ta_aktif = (int)($dTaAktif['id'] ?? 0);
$label_ta_aktif = trim(($dTaAktif['tahun'] ?? 'Belum diatur') . ' - Semester ' . ($dTaAktif['semester'] ?? '-'));

$status_uts_dikunci = (int)($dTaAktif['rapor_uts_dikunci'] ?? 0);
$status_uas_dikunci = (int)($dTaAktif['rapor_uas_dikunci'] ?? 0);

// --- KUMPULKAN DATA STATISTIK SEKOLAH (ikut tahun ajaran aktif, bukan total keseluruhan tabel) ---
// Siswa & kelas dihitung dari riwayat_kelas di TA aktif (id_kelas = 0 berarti alumni, jadi dikecualikan).
$jml_siswa = $conn->query("SELECT COUNT(DISTINCT id_siswa) as total FROM riwayat_kelas WHERE id_tahun_ajaran = '$id_ta_aktif' AND id_kelas != 0")->fetch_assoc()['total'] ?? 0;
$jml_guru = $conn->query("SELECT COUNT(id) as total FROM guru")->fetch_assoc()['total'] ?? 0;
$jml_kelas = $conn->query("SELECT COUNT(DISTINCT id_kelas) as total FROM riwayat_kelas WHERE id_tahun_ajaran = '$id_ta_aktif' AND id_kelas != 0")->fetch_assoc()['total'] ?? 0;

// --- DAFTAR SEMUA TAHUN AJARAN (buat dropdown filter di menu-menu monitoring) ---
$qDaftarTa = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, FIELD(semester,'Ganjil','Genap') ASC");
$daftar_tahun_ajaran = [];
if($qDaftarTa){
    while($ta = $qDaftarTa->fetch_assoc()){
        $daftar_tahun_ajaran[] = $ta;
    }
}

// Tahun ajaran yang lagi dipilih di menu monitoring (lewat dropdown ?ta=). Default: tahun ajaran aktif.
$id_ta_pilih = (isset($_GET['ta']) && $_GET['ta'] !== '') ? (int)$_GET['ta'] : $id_ta_aktif;

$data_uts_dashboard = hitung_progress_nilai($conn, 'uts', $id_ta_aktif);
$data_uas_dashboard = hitung_progress_nilai($conn, 'uas', $id_ta_aktif);

$progress_uts_dashboard = $data_uts_dashboard['progress'];
$progress_uas_dashboard = $data_uas_dashboard['progress'];

// Progress utama tetap satu box, tapi isinya menampilkan UTS dan UAS terpisah
$progres_sekolah = round(($progress_uts_dashboard + $progress_uas_dashboard) / 2);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Sekolah - SMK JP 1</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f8fafd; 
        }

        :root {
            --sidebar-bg: #AFC6E0;
            --sidebar-bg-soft: #BFD3EA;
            --sidebar-bg-light: #D0E0F2;
            --text-dark-blue: #0d385e;
            --btn-blue: #6FA7DF;
            --btn-blue-hover: #5E97D2;
            --soft-green: #B9DDCC;
            --soft-cream: #F6E8C9;
        }

        .sidebar { 
            background: linear-gradient(
                180deg,
                var(--sidebar-bg) 0%,
                var(--sidebar-bg-soft) 55%,
                var(--sidebar-bg-light) 100%
            ) !important;
            min-height: 100vh; 
            width: 280px; 
            flex-shrink: 0; 
            display: flex; 
            flex-direction: column;
            color: var(--text-dark-blue);
            box-shadow: 5px 0 20px rgba(80, 115, 155, 0.22);
        }

        .sidebar .nav-link { 
            color: var(--text-dark-blue) !important; 
            font-weight: 600; 
            padding: 12px 20px; 
            border-radius: 0 50px 50px 0; 
            transition: none; 
            font-size: 14px; 
            margin-bottom: 5px; 
        }

        .sidebar .nav-link:hover { 
            background-color: rgba(255,255,255, 0.45); 
            padding-left: 25px; 
            color: var(--text-dark-blue) !important; 
        }

        .sidebar .nav-link.active { 
            background-color: rgba(255,255,255,0.90); 
            color: var(--text-dark-blue) !important; 
            box-shadow: 0 4px 10px rgba(80, 115, 155, 0.18); 
            font-weight: 700;
        }

        .sidebar small,
        .sidebar .text-dark {
            color: rgba(13, 56, 94, 0.72) !important;
        }
        
        .btn-logout-custom { 
            border: 2px solid var(--text-dark-blue); 
            color: var(--text-dark-blue); 
            font-weight: 700; 
            background: rgba(255,255,255,0.28); 
            transition: none; 
        }

        .btn-logout-custom:hover { 
            background-color: var(--text-dark-blue); 
            color: white; 
        }

        .card-welcome { 
            background-image: url('../assets/bg-dashboard.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #0d385e; 
            border-radius: 24px; 
            border: 1px solid rgba(255,255,255,0.60);
            position: relative;
            overflow: hidden;
            box-shadow: 0 14px 35px rgba(92, 130, 176, 0.22) !important;
        }

        .card-welcome::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(255,255,255,0.52) 0%,
                rgba(255,255,255,0.34) 42%,
                rgba(191,211,234,0.12) 70%,
                rgba(111,167,223,0.28) 100%
            );
            z-index: 1;
        }

        .card-welcome .row {
            position: relative;
            z-index: 2;
        }

        .card-welcome h2 {
            color: #0d385e !important;
            text-shadow: 0 2px 6px rgba(255,255,255,0.35);
        }

        .card-welcome p {
            color: #35597c !important;
            opacity: 1 !important;
        }

        .card-welcome .fa-chart-line {
            color: rgba(13,56,94,0.45) !important;
            opacity: 1 !important;
        }

        .hero-icon-circle {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            border: 1.8px solid rgba(255, 255, 255, 0.38);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.08),
                0 10px 25px rgba(22, 63, 122, 0.10);
            backdrop-filter: blur(3px);
        }

        .hero-icon-circle .fa-chart-line {
            font-size: 82px !important;
            opacity: 1 !important;
        }

        .stat-icon { 
            width: 60px; 
            height: 60px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 15px; 
            font-size: 24px; 
            color: #ffffff; 
        }

        .stat-icon.bg-primary {
            background-color: #6FA7DF !important;
            color: #ffffff !important;
        }

        .stat-icon.bg-success {
            background-color: #B9DDCC !important;
            color: #2E5E4E !important;
        }

        .stat-icon.bg-warning {
            background-color: #F6E8C9 !important;
            color: #87653B !important;
        }

        .progress-bar.bg-primary {
            background-color: #6FA7DF !important;
        }

        .progress-bar.bg-success {
            background-color: #5E9F86 !important;
        }

        .text-primary {
            color: #6FA7DF !important;
        }
        


        .progress-mini-label {
            font-size: 13px;
            font-weight: 800;
            color: #0d385e;
            margin-bottom: 7px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-mini-label .badge-lock-dashboard {
            font-size: 10px;
            border-radius: 999px;
            padding: 5px 10px;
            font-weight: 800;
        }

        .badge-lock-dashboard.open {
            background: #DDF5EA;
            color: #1f5f48;
            border: 1px solid #9DDCC4;
        }

        .badge-lock-dashboard.lock {
            background: #FFE1E7;
            color: #9b1c31;
            border: 1px solid #F3A5B4;
        }

        .progress-row-dashboard {
            height: 24px;
            border-radius: 999px;
            background: #e9eef5;
            overflow: hidden;
        }

        .progress-row-dashboard .bar-uts,
        .progress-row-dashboard .bar-uas {
            height: 100%;
            border-radius: 999px;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: 1rem 1rem;
            animation: progress-bar-stripes 1s linear infinite;
        }

        .progress-row-dashboard .bar-uts {
            background-color: #6FA7DF;
            background-image: linear-gradient(45deg, rgba(255,255,255,.18) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.18) 50%, rgba(255,255,255,.18) 75%, transparent 75%, transparent);
        }

        .progress-row-dashboard .bar-uas {
            background-color: #5E9F86;
            background-image: linear-gradient(45deg, rgba(255,255,255,.18) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.18) 50%, rgba(255,255,255,.18) 75%, transparent 75%, transparent);
        }

        @keyframes progress-bar-stripes {
            0% { background-position-x: 1rem; }
        }


        @media (max-width: 991px) {
            .hero-icon-circle {
                width: 140px;
                height: 140px;
                margin-top: 24px;
            }

            .hero-icon-circle .fa-chart-line {
                font-size: 68px !important;
            }
        }

        @media print {
            .sidebar, .navbar { display: none !important; }
            .flex-grow-1 { height: auto !important; overflow: visible !important; }
            .px-4 { padding: 0 !important; }
            body { background-color: white !important; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-0 pt-4 shadow d-print-none">
        <div class="text-center mb-4 px-3 text-dark">
            <img src="../assets/logo.png" style="width: 70px; margin-bottom: 15px;">
            <h5 class="fw-bold mb-0">SMK Jakarta Pusat 1</h5>
            <small class="opacity-75">Panel Eksekutif Kepsek</small>
        </div>
        <ul class="nav flex-column pe-3"> 
            <li class="nav-item">
                <a href="?page=dashboard" class="nav-link <?= (!isset($_GET['page']) || $_GET['page']=='dashboard') ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item mt-3"><small class="text-dark ms-4 fw-bold" style="font-size: 11px;">MONITORING</small></li>
            <li class="nav-item">
                <a href="?page=pantau_kelas" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='pantau_kelas') ? 'active' : '' ?>">
                    <i class="fas fa-eye me-2"></i> Pantau Rapor Kelas
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=pantau_guru" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='pantau_guru') ? 'active' : '' ?>">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Pantau Kinerja Guru
                </a>
            </li>
            
            <li class="nav-item mt-3"><small class="text-dark ms-4 fw-bold" style="font-size: 11px;">PENGESAHAN</small></li>
            <li class="nav-item">
                <a href="?page=pengesahan" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='pengesahan') ? 'active' : '' ?>">
                    <i class="fas fa-file-signature me-2"></i> Pengesahan Rapor
                </a>
            </li>

            <li class="nav-item mt-3"><small class="text-dark ms-4 fw-bold" style="font-size: 11px;">PENGATURAN</small></li>
            <li class="nav-item">
                <a href="?page=profil" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='profil') ? 'active' : '' ?>">
                    <i class="fas fa-user-cog me-2"></i> Pengaturan Profil
                </a>
            </li>

        </ul>
        <div class="mt-auto p-3">
             <a href="../logout.php" class="btn btn-logout-custom w-100 rounded-pill d-flex align-items-center justify-content-center py-2">
                <i class="fas fa-sign-out-alt me-2"></i> Logout Keluar
             </a>
        </div>
    </div>

    <div class="flex-grow-1" style="min-height: 100vh; overflow-y: auto;">
        
        <nav class="navbar navbar-light bg-white shadow-sm mb-4 sticky-top d-print-none">
             <div class="container-fluid px-4 d-flex justify-content-end align-items-center">
                
                <div class="dropdown d-inline-block">
                    <a class="nav-link dropdown-toggle d-flex align-items-center bg-light rounded-pill pe-3 ps-1 py-1 shadow-sm border text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= $avatar_nav ?>" alt="Profile" class="rounded-circle me-2 bg-white" width="35" height="35" style="object-fit: cover;">
                        <span class="fw-bold text-dark small me-1"><?= $nama_kepsek; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-2">
                        <li>
                            <a class="dropdown-item py-2 fw-bold text-secondary" href="?page=profil">
                                <i class="fas fa-user-cog me-2 text-primary"></i> Pengaturan Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <li>
                            <a class="dropdown-item py-2 fw-bold text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout Keluar
                            </a>
                        </li>
                    </ul>
                </div>

             </div>
        </nav>

        <div class="px-4 pb-5">
            <?php 
            $page = $_GET['page'] ?? 'dashboard';
            
            // --- INI KUNCI ROUTING OTOMATISNYA DAV ---
            if($page == 'dashboard') { ?>
                <div class="card card-welcome p-5 mb-4 shadow-sm">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <h2 class="fw-bold mb-2">Selamat Datang, Bapak/Ibu Kepala Sekolah!</h2>
                            <p class="opacity-75 mb-2">Ini adalah pusat kendali monitoring akademik. Anda dapat memantau progres pengisian nilai dan kinerja seluruh komponen sekolah secara real-time.</p>
                            <span class="badge rounded-pill px-3 py-2" style="background: rgba(255,255,255,0.22); color:#0d385e; font-weight:700; font-size: 12.5px;">
                                <i class="fas fa-calendar-check me-1"></i> Tahun Ajaran Aktif: <?= htmlspecialchars($label_ta_aktif) ?>
                            </span>
                        </div>
                        <div class="col-md-3 text-end d-none d-md-block">
                            <div class="hero-icon-circle">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 15px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><small class="text-muted fw-bold">TOTAL SISWA</small><h1 class="fw-bold mb-0 text-dark"><?= $jml_siswa ?></h1></div>
                                <div class="stat-icon bg-primary shadow-sm"><i class="fas fa-user-graduate"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 15px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><small class="text-muted fw-bold">TOTAL GURU</small><h1 class="fw-bold mb-0 text-dark"><?= $jml_guru ?></h1></div>
                                <div class="stat-icon bg-success shadow-sm"><i class="fas fa-chalkboard-teacher"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 15px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><small class="text-muted fw-bold">TOTAL KELAS</small><h1 class="fw-bold mb-0 text-dark"><?= $jml_kelas ?></h1></div>
                                <div class="stat-icon bg-warning shadow-sm"><i class="fas fa-school"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fas fa-spinner fa-spin text-primary me-2"></i>Progres Pengolahan Nilai Sekolah
                    </h6>

                    <div class="mb-3">
                        <div class="progress-mini-label">
                            <span><i class="fas fa-file-alt text-primary me-1"></i> Progress UTS</span>
                        </div>
                        <div class="progress-row-dashboard">
                            <div class="bar-uts" style="width: <?= $progress_uts_dashboard ?>%;">
                                <?= $progress_uts_dashboard ?>%
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="progress-mini-label">
                            <span><i class="fas fa-file-signature text-success me-1"></i> Progress UAS</span>
                        </div>
                        <div class="progress-row-dashboard">
                            <div class="bar-uas" style="width: <?= $progress_uas_dashboard ?>%;">
                                <?= $progress_uas_dashboard ?>%
                            </div>
                        </div>
                    </div>


                </div>
            <?php 
            } 
            // Kalau file-nya ada di folder kepsek, langsung buka!
            elseif (file_exists($page.'.php')) {
                include $page.'.php';
            } 
            // Kalau nama file-nya typo / belum dibikin
            else {
                echo "<div class='text-center py-5'><h5>Halaman <b>{$page}</b> tidak ditemukan atau sedang dalam tahap pengembangan.</h5></div>";
            }
            ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>