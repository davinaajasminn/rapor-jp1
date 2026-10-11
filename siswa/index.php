<?php
session_start();

// --- 1. CEK KHUSUS SESI SISWA ---
if(!isset($_SESSION['siswa']) || $_SESSION['siswa']['status'] != "login"){
    session_destroy();
    header("location:../index.php?pesan=belum_login");
    exit;
}

// Ambil NISN dari session username
$username_login = $_SESSION['siswa']['username'];
$nama_siswa_login = $_SESSION['siswa']['nama'];

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

// --- AMBIL DATA SISWA BERDASARKAN NISN ---
$qSiswa = $conn->query("SELECT id, nis, nama_siswa, id_kelas, foto_profil FROM siswa WHERE nisn='$username_login'");
$dSiswa = $qSiswa->fetch_assoc();

if(!$dSiswa){ 
    echo "Data profil siswa tidak ditemukan untuk NISN: " . $username_login; 
    exit; 
}

$id_siswa_asli = $dSiswa['id'];
$id_kelas_siswa = $dSiswa['id_kelas'];
$foto_profil_siswa = $dSiswa['foto_profil'] ?? '';

// --- STATUS ALUMNI ---
// siswa.id_kelas = NULL adalah penanda alumni (permanen sekali lulus, gak balik lagi ke kelas manapun).
// Ini lebih dipercaya ketimbang ngecek riwayat_kelas per TA, karena riwayat_kelas alumni juga
// isinya NULL untuk SEMUA TA setelah lulus, jadi gampang ketuker sama "belum diproses kenaikan kelas".
$is_alumni_siswa = is_null($id_kelas_siswa);

// Kalau alumni, cari kelas TERAKHIR & TA TERAKHIR waktu masih aktif jadi siswa (buat ditampilkan
// di dashboard sebagai "kelas terakhir sebelum lulus", dan buat basis statistik histori nilai/absen).
$id_kelas_terakhir_alumni = 0;
$id_ta_terakhir_alumni = 0;
$tahun_lulus_siswa = '-';
$semester_lulus_siswa = '';

if($is_alumni_siswa){
    $qTerakhirAlumni = $conn->query("SELECT rk.id_kelas, rk.id_tahun_ajaran, ta.tahun, ta.semester
                                      FROM riwayat_kelas rk
                                      JOIN tahun_ajaran ta ON ta.id = rk.id_tahun_ajaran
                                      WHERE rk.id_siswa='$id_siswa_asli' AND rk.id_kelas IS NOT NULL
                                      ORDER BY ta.id DESC LIMIT 1");
    if($qTerakhirAlumni && $qTerakhirAlumni->num_rows > 0){
        $dTerakhirAlumni = $qTerakhirAlumni->fetch_assoc();
        $id_kelas_terakhir_alumni = (int)$dTerakhirAlumni['id_kelas'];
        $id_ta_terakhir_alumni = (int)$dTerakhirAlumni['id_tahun_ajaran'];
        $tahun_lulus_siswa = $dTerakhirAlumni['tahun'];
        $semester_lulus_siswa = $dTerakhirAlumni['semester'];
    }
}

// Siapkan URL Foto buat Navbar
$avatar_nav = (!empty($foto_profil_siswa) && file_exists("../assets/uploads/".$foto_profil_siswa)) 
              ? "../assets/uploads/".$foto_profil_siswa 
              : "https://ui-avatars.com/api/?name=".urlencode($nama_siswa_login)."&background=random&color=fff&rounded=true&bold=true";

// --- PERBAIKAN: AMBIL INFO DARI TABEL TAHUN AJARAN AKTIF ---
$qTahun = $conn->query("SELECT id, tahun, semester, hari_efektif FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahun = $qTahun ? $qTahun->fetch_assoc() : null;

if(!$dTahun){
    $qTahunLast = $conn->query("SELECT id, tahun, semester, hari_efektif FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    $dTahun = ($qTahunLast && $qTahunLast->num_rows > 0) ? $qTahunLast->fetch_assoc() : ['id' => 0, 'tahun' => '-', 'semester' => 'Ganjil', 'hari_efektif' => 110];
}

$id_ta_aktif_siswa = (int)($dTahun['id'] ?? 0);
$id_ta_aktif_siswa_safe = mysqli_real_escape_string($conn, $id_ta_aktif_siswa);
$total_hari = $dTahun['hari_efektif'] ?? 110;
$sem_aktif = $dTahun['semester'] ?? 'Ganjil';
$tahun_ajaran_aktif_siswa = $dTahun['tahun'] ?? '-';

// --- AMBIL NAMA KELAS, TINGKAT, DAN JURUSAN ---
// FIX: Kelas Saat Ini sekarang dicek dari riwayat_kelas untuk tahun ajaran AKTIF dulu
// (bukan langsung dari siswa.id_kelas, yang cuma nyimpen penempatan kelas terakhir).
// Ini penting biar gak ketuker: kalau Kenaikan Kelas belum diproses untuk TA aktif,
// siswa akan ditandai "Belum Ditempatkan" dan bukan diam-diam nampilin kelas TA lama.
$belum_naik_kelas_siswa = false;

if($is_alumni_siswa){
    // Alumni: jangan dicocokkan ke TA aktif sistem sama sekali, langsung pakai kelas terakhir sebelum lulus.
    $qKelas = ($id_kelas_terakhir_alumni > 0) ? $conn->query("SELECT nama_kelas, tingkat, kode_jurusan FROM kelas WHERE id='$id_kelas_terakhir_alumni'") : null;
} else {
    $qRkSiswa = $conn->query("SELECT id_kelas FROM riwayat_kelas
                               WHERE id_siswa='$id_siswa_asli' AND id_tahun_ajaran='$id_ta_aktif_siswa_safe'
                               ORDER BY id DESC LIMIT 1");
    $id_kelas_ta_aktif = ($qRkSiswa && $qRkSiswa->num_rows > 0) ? (int)$qRkSiswa->fetch_assoc()['id_kelas'] : 0;

    if($id_kelas_ta_aktif > 0){
        // Ada penempatan resmi untuk TA aktif ini, pakai itu.
        $qKelas = $conn->query("SELECT nama_kelas, tingkat, kode_jurusan FROM kelas WHERE id='$id_kelas_ta_aktif'");
    } else {
        // Cek dulu apakah Kenaikan Kelas SUDAH diproses untuk TA aktif ini (buat siswa lain).
        $qCekRkTa = $conn->query("SELECT COUNT(*) as total FROM riwayat_kelas WHERE id_tahun_ajaran='$id_ta_aktif_siswa_safe'");
        $sudah_ada_proses_ta_aktif = $qCekRkTa && ((int)($qCekRkTa->fetch_assoc()['total'] ?? 0) > 0);

        if($sudah_ada_proses_ta_aktif){
            // Kenaikan Kelas sudah diproses untuk TA ini, tapi siswa ini belum termasuk di dalamnya.
            $belum_naik_kelas_siswa = true;
            $qKelas = null;
        } else {
            // Kenaikan Kelas belum diproses sama sekali untuk TA aktif ini, fallback ke penempatan kelas siswa saat ini.
            $qKelas = $conn->query("SELECT nama_kelas, tingkat, kode_jurusan FROM kelas WHERE id='$id_kelas_siswa'");
        }
    }
}

$dKelasLogin = ($qKelas && $qKelas->num_rows > 0) ? $qKelas->fetch_assoc() : [];

$nama_kelas = $is_alumni_siswa
    ? ($dKelasLogin['nama_kelas'] ?? 'Alumni')
    : ($belum_naik_kelas_siswa ? 'Belum Ditempatkan' : ($dKelasLogin['nama_kelas'] ?? 'Tidak Diketahui'));
$tingkat_siswa = $dKelasLogin['tingkat'] ?? explode(' ', trim($nama_kelas))[0];
$tingkat_menu_siswa = strtoupper(trim($tingkat_siswa));
$jurusan_siswa = $dKelasLogin['kode_jurusan'] ?? '';

if(empty($jurusan_siswa)){
    if(stripos($nama_kelas, 'RPL') !== false) $jurusan_siswa = 'RPL';
    elseif(stripos($nama_kelas, 'AKL') !== false) $jurusan_siswa = 'AKL';
    elseif(stripos($nama_kelas, 'AKT') !== false) $jurusan_siswa = 'AKT';
    elseif(stripos($nama_kelas, 'BR') !== false) $jurusan_siswa = 'BR';
    elseif(stripos($nama_kelas, 'MP') !== false) $jurusan_siswa = 'MP';
}

$tingkat_menu_siswa = strtoupper(trim($tingkat_siswa));

$tingkat_safe = mysqli_real_escape_string($conn, $tingkat_siswa);
$jurusan_safe = mysqli_real_escape_string($conn, $jurusan_siswa);

// Deteksi kolom jurusan di tabel mapel supaya sinkron dengan admin/wali.
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

// Kondisi mapel yang benar-benar berlaku untuk siswa.
// PKL sengaja dikeluarkan dari dashboard akademik reguler, karena PKL punya rekap/cetak sendiri.
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

// --- LOGIKA NOTIFIKASI LONCENG SISWA ---
$my_role = 'siswa'; 
$my_id = $id_siswa_asli; 

// 🩹 FITUR BARU: tandai baca & hapus notifikasi.
// Keamanan: semua query di bawah SELALU ikut syarat penerima_role+penerima_id milik sendiri,
// supaya siswa gak bisa nandain/hapus notifikasi milik siswa lain cuma dengan ganti id di URL.
if(isset($_GET['tandai_baca_notif'])){
    $id_notif_baca = mysqli_real_escape_string($conn, $_GET['tandai_baca_notif']);
    $conn->query("UPDATE notifikasi SET status_baca = 1 WHERE id = '$id_notif_baca' AND penerima_role = '$my_role' AND penerima_id = '$my_id'");

    $tujuan_notif = $_GET['goto'] ?? '?page=lihat_nilai';
    // Cegah open-redirect: cuma izinin link internal yang mulai dari 'index.php' atau '?'.
    if(!preg_match('/^(index\.php|\?)/', $tujuan_notif)){
        $tujuan_notif = '?page=lihat_nilai';
    }
    header("location: $tujuan_notif");
    exit;
}

if(isset($_GET['hapus_notif'])){
    $id_notif_hapus = mysqli_real_escape_string($conn, $_GET['hapus_notif']);
    $conn->query("DELETE FROM notifikasi WHERE id = '$id_notif_hapus' AND penerima_role = '$my_role' AND penerima_id = '$my_id'");
    header("location: ?page=" . ($_GET['page'] ?? 'lihat_nilai'));
    exit;
}

if(isset($_GET['tandai_semua_baca'])){
    $conn->query("UPDATE notifikasi SET status_baca = 1 WHERE penerima_role = '$my_role' AND penerima_id = '$my_id' AND status_baca = 0");
    header("location: ?page=" . ($_GET['page'] ?? 'lihat_nilai'));
    exit;
}

$qUnread = $conn->query("SELECT COUNT(id) as total FROM notifikasi WHERE penerima_role = '$my_role' AND penerima_id = '$my_id' AND status_baca = 0");
$jml_notif = ($qUnread && $qUnread->num_rows > 0) ? $qUnread->fetch_assoc()['total'] : 0;
$qNotif = $conn->query("SELECT * FROM notifikasi WHERE penerima_role = '$my_role' AND penerima_id = '$my_id' ORDER BY waktu DESC LIMIT 5");

// --- AMBIL DATA NILAI UNTUK GRAFIK & RATA-RATA ---
$labels = [];
$data_p = []; $data_k = [];
$bg_p = []; 
$bg_k = []; 
$total_na = 0;
$count_mapel = 0;

// Untuk alumni, histori nilai/absensi diambil dari TA TERAKHIR waktu masih aktif sekolah,
// bukan TA aktif sistem sekarang (yang notabene bukan periode dia lagi).
$id_ta_utk_statistik_siswa = $is_alumni_siswa ? $id_ta_terakhir_alumni : $id_ta_aktif_siswa_safe;

$qChart = $conn->query("SELECT DISTINCT m.id, m.nama_mapel, m.kkm, n.nilai_t, n.nilai_p, n.nilai_k 
                        FROM nilai n 
                        JOIN mapel m ON n.id_mapel = m.id 
                        WHERE n.id_siswa = '$id_siswa_asli'
                        AND n.id_tahun_ajaran = '$id_ta_utk_statistik_siswa'
                        AND $kondisi_mapel_siswa
                        ORDER BY m.nama_mapel ASC");

while($row = $qChart->fetch_assoc()){
    $labels[] = $row['nama_mapel'];
    $kkm = $row['kkm'] ?? 75;

    $np = $row['nilai_p'] ?? 0; 
    $nk = $row['nilai_k'] ?? 0; 
    $nt = $row['nilai_t'] ?? 0;

    $data_p[] = $np;
    $data_k[] = $nk;

    // Warna Pastel (Biru Pastel vs Merah Peringatan)
    if($np > 0 && $np < $kkm) { $bg_p[] = '#FFB7B2'; } else { $bg_p[] = '#C7CEEA'; }
    if($nk > 0 && $nk < $kkm) { $bg_k[] = '#FFB7B2'; } else { $bg_k[] = '#B5EAD7'; }

    if ((empty($np) || $np == 0) && (empty($nk) || $nk == 0)) {
        $na = $nt;
    } elseif (empty($nk) || $nk == 0) {
        $na = (($nt * 4) + ($np * 2)) / 6;
    } else {
        $na = (($nt * 4) + ($np * 2) + ($nk * 4)) / 10;
    }

    $total_na += $na;
    $count_mapel++;
}

$rata_akhir = ($count_mapel > 0) ? $total_na / $count_mapel : 0;

// --- HITUNG PERSENTASE ABSENSI ---
$semester_utk_statistik_siswa = $is_alumni_siswa ? $semester_lulus_siswa : $sem_aktif;
$qAbsen = $conn->query("SELECT sakit, izin, alpha FROM absensi WHERE id_siswa = '$id_siswa_asli' AND UPPER(semester) = '".strtoupper($semester_utk_statistik_siswa)."' AND id_tahun_ajaran = '$id_ta_utk_statistik_siswa'");
$dAbsen = $qAbsen->fetch_assoc();
$sakit = $dAbsen['sakit'] ?? 0;
$izin  = $dAbsen['izin'] ?? 0;
$alpa  = $dAbsen['alpha'] ?? 0;

$tidak_masuk = $sakit + $izin + $alpa;
$persen_hadir = ($total_hari > 0) ? round((($total_hari - $tidak_masuk) / $total_hari) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siswa - Sistem Pengolah Nilai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafd; }
        :root { 
            --sidebar-bg: #BFD3EA; 
            --text-dark-blue: #0d385e; 
            --pastel-blue: #6FA7DF;
            --pastel-blue-soft: #D8E9FA;
            --pastel-green: #B9DDCC;
            --pastel-green-text: #2E5E4E;
            --pastel-cream: #F6E8C9;
            --pastel-cream-text: #87653B;
        }
        .sidebar { 
            background-color: var(--sidebar-bg) !important; 
            min-height: 100vh; 
            color: var(--text-dark-blue); 
            width: 280px; 
            min-width: 280px;
            max-width: 280px;
            flex: 0 0 280px;
            flex-shrink: 0;
            display: flex; 
            flex-direction: column; 
        }
        .sidebar h5 { color: #003B63 !important; }
        .sidebar small { color: #003B63 !important; }
        .sidebar .nav-link { 
            color: var(--text-dark-blue) !important; 
            font-weight: 600; 
            padding: 12px 20px; 
            border-radius: 0 50px 50px 0; 
            font-size: 14px; 
            margin-bottom: 5px; 
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        .sidebar .nav-link.active { background-color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-logout-custom { border: 2px solid var(--text-dark-blue); color: var(--text-dark-blue); font-weight: 700; background: rgba(255,255,255,0.22); padding: 8px 15px; font-size: 13px; transition: none; text-decoration: none; }
        .btn-logout-custom:hover { background-color: #D8E9FA; color: var(--text-dark-blue); }
        
        /* Dashboard Siswa */
        .hero-banner { 
            background-image:
                linear-gradient(90deg, rgba(255,255,255,0.52) 0%, rgba(255,255,255,0.34) 45%, rgba(191,211,234,0.14) 72%, rgba(111,167,223,0.26) 100%),
                url('../assets/bg-dashboard.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #0d385e;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.60);
            box-shadow: 0 10px 25px rgba(176, 196, 222, 0.28);
            position: relative;
            overflow: hidden;
        }
        .bg-circle-1,
        .bg-circle-2 { display: none; }
        .hero-banner h2 { color: #0d385e !important; }
        .hero-banner p { color: #35597c !important; opacity: 1 !important; }
        .hero-icon { 
            color: #ffffff !important; 
            filter: drop-shadow(0px 10px 8px rgba(30, 60, 114, 0.16)); 
            opacity: 1 !important;
        }

        .hero-icon-circle {
            width: 175px;
            height: 175px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            border: 1.8px solid rgba(255, 255, 255, 0.38);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 0 0 0 1px rgba(255, 255, 255, 0.10),
                0 10px 25px rgba(22, 63, 122, 0.12);
            backdrop-filter: blur(3px);
        }

        @media (max-width: 991px) {
            .hero-icon-circle {
                width: 140px;
                height: 140px;
                margin-top: 24px;
            }

            .hero-icon {
                font-size: 72px !important;
            }
        }

        .stat-card { 
            background: white; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border: none; transition: 0.3s; cursor: default;
        }
        .stat-card:hover {
            transform: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .icon-box { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .btn-pastel-blue { background:#6FA7DF !important; color:#ffffff !important; border:none !important; border-radius:50px !important; font-weight:700 !important; box-shadow:0 5px 12px rgba(111,167,223,0.22) !important; }
        .btn-pastel-blue:hover { background:#5E97D2 !important; color:#ffffff !important; }
        .btn-pastel-green { background:#B9DDCC !important; color:#2E5E4E !important; border:1px solid #9FCDB8 !important; border-radius:50px !important; font-weight:700 !important; box-shadow:0 5px 12px rgba(156,201,182,0.18) !important; }
        .btn-pastel-cream { background:#F6E8C9 !important; color:#87653B !important; border:1px solid #E8D5AB !important; border-radius:50px !important; font-weight:700 !important; box-shadow:0 5px 12px rgba(214,189,145,0.18) !important; }
        .badge-pastel-blue { background:#D8E9FA !important; color:#0d385e !important; border:1px solid #AECDEE !important; }
        .badge-pastel-red { background:#FFE3E8 !important; color:#842029 !important; border:1px solid #F2A6B3 !important; }
    
        .sidebar .nav-link i {
            width: 22px;
            min-width: 22px;
            text-align: center;
        }
        .sidebar h5 {
            font-size: 20px;
            line-height: 1.25;
            white-space: normal;
        }
        .main-content-siswa {
            min-width: 0;
        }

        .attendance-card {
            position: relative;
        }

        .attendance-card .attendance-hint {
            font-size: 10px;
            color: #7b8794;
            margin-top: 3px;
            display: block;
            line-height: 1.2;
            font-weight: 400;
        }

        .tooltip.attendance-tooltip-custom .tooltip-inner {
            background-color: #D8E9FA !important;
            color: #0d385e !important;
            border: 1px solid #AECDEE !important;
            border-radius: 12px !important;
            box-shadow: 0 6px 16px rgba(80,115,155,0.18) !important;
            font-weight: 600;
            padding: 10px 14px;
            line-height: 1.5;
            font-size: 12px;
        }

        .tooltip.attendance-tooltip-custom.bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #D8E9FA !important;
        }

        .tooltip.attendance-tooltip-custom.bs-tooltip-bottom .tooltip-arrow::before {
            border-bottom-color: #D8E9FA !important;
        }

        .tooltip.attendance-tooltip-custom.bs-tooltip-start .tooltip-arrow::before {
            border-left-color: #D8E9FA !important;
        }

        .tooltip.attendance-tooltip-custom.bs-tooltip-end .tooltip-arrow::before {
            border-right-color: #D8E9FA !important;
        }

    
        /* Dashboard stat card hanya tampilan informasi, bukan tombol */
        .stat-card {
            cursor: default !important;
        }
        .stat-card:hover {
            transform: none !important;
        }

</style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar pt-4 d-print-none text-center">
        <div class="mb-4 px-3">
            <img src="../assets/logo.png" style="width: 70px; margin-bottom: 15px;">
            <h5 class="fw-bold mb-0">SMK Jakarta Pusat 1</h5>
            <small>Sistem Pengolahan Nilai</small>
        </div>
        <ul class="nav flex-column pe-3 text-start">
            <li class="nav-item"><a href="?page=dashboard" class="nav-link <?= (!isset($_GET['page']) || $_GET['page']=='dashboard')?'active':''; ?>"><i class="fas fa-home me-2"></i> Beranda</a></li>
            <li class="nav-item"><a href="?page=lihat_nilai" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='lihat_nilai')?'active':''; ?>"><i class="fas fa-file-invoice me-2"></i> Lihat Nilai</a></li>
            <?php if($tingkat_menu_siswa == 'XII'): ?>
                <li class="nav-item"><a href="?page=nilai_pkl" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='nilai_pkl')?'active':''; ?>"><i class="fas fa-briefcase me-2"></i> Nilai PKL</a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="?page=profil" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='profil')?'active':''; ?>"><i class="fas fa-user-cog me-2"></i> Pengaturan Profil</a></li>
        </ul>
        <div class="mt-auto p-3">
             <a href="../logout.php" class="btn btn-logout-custom w-100 rounded-pill d-flex align-items-center justify-content-center">
                <i class="fas fa-sign-out-alt me-2"></i> Logout Keluar
             </a>
        </div>
    </div>

    <div class="flex-grow-1 main-content-siswa" style="height: 100vh; overflow-y: auto;">
        
        <nav class="navbar navbar-light bg-white shadow-sm mb-4 sticky-top d-print-none">
             <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
                 <span class="navbar-brand h1 fw-bold text-secondary mb-0">Panel Siswa</span>
                 
                 <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="border:none; background:transparent;">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if($jml_notif > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill badge-pastel-red" style="font-size: 0.6rem;">
                                    <?= $jml_notif ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                            <li class="p-3 bg-light border-bottom rounded-top-4 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-bell text-primary me-2"></i>Notifikasi</h6>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if($jml_notif > 0): ?>
                                        <span class="badge badge-pastel-red rounded-pill"><?= $jml_notif ?> Baru</span>
                                        <a href="?page=<?= htmlspecialchars($_GET['page'] ?? 'lihat_nilai') ?>&tandai_semua_baca=1" class="small text-decoration-none fw-bold" style="color:#6FA7DF;" title="Tandai semua sudah dibaca">
                                            <i class="fas fa-check-double"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                            
                            <?php if($qNotif && $qNotif->num_rows > 0): while($n = $qNotif->fetch_assoc()): 
                                $avatar_url_notif = "https://ui-avatars.com/api/?name=" . urlencode($n['nama_pengirim']) . "&background=random&color=fff&rounded=true&bold=true";
                                $bg_unread = ($n['status_baca'] == 0) ? 'background-color: #f0f7ff;' : '';
                                $link_baca_notif = "?tandai_baca_notif=" . $n['id'] . "&goto=" . urlencode($n['link_tujuan']);
                            ?>
                            <li class="position-relative">
                                <a class="dropdown-item d-flex align-items-start p-3 border-bottom text-wrap pe-5" href="<?= $link_baca_notif ?>" style="<?= $bg_unread ?> transition: 0.3s;">
                                    <img src="<?= $avatar_url_notif ?>" alt="Avatar" class="rounded-circle shadow-sm me-3" width="40" height="40">
                                    <div>
                                        <span class="d-block fw-bold small text-dark mb-1"><?= $n['nama_pengirim'] ?> <small class="text-muted fw-normal">(<?= ucfirst($n['pengirim_role']) ?>)</small></span>
                                        <span class="d-block small text-secondary" style="font-size: 11px; line-height: 1.3;"><?= htmlspecialchars($n['pesan']) ?></span>
                                        <small class="text-muted mt-1 d-block" style="font-size: 9px;"><i class="fas fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($n['waktu'])) ?></small>
                                    </div>
                                </a>
                                <a href="?page=<?= htmlspecialchars($_GET['page'] ?? 'lihat_nilai') ?>&hapus_notif=<?= $n['id'] ?>" 
                                   class="position-absolute text-muted" 
                                   style="top: 12px; right: 12px; font-size: 12px;" 
                                   title="Hapus notifikasi"
                                   onclick="return confirm('Hapus notifikasi ini?');">
                                    <i class="fas fa-times"></i>
                                </a>
                            </li>
                            <?php endwhile; else: ?>
                            <li class="p-4 text-center text-muted small">
                                <i class="fas fa-bell-slash fa-2x mb-2 opacity-25"></i><br>Belum ada notifikasi baru.
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="dropdown d-inline-block">
                        <a class="nav-link dropdown-toggle d-flex align-items-center bg-light rounded-pill pe-3 ps-1 py-1 shadow-sm border text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= $avatar_nav ?>" alt="Profile" class="rounded-circle me-2 bg-white" width="35" height="35" style="object-fit: cover;">
                            <span class="fw-bold text-dark small me-1"><?= $nama_siswa_login; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 mt-2">
                            <li class="px-3 py-2 bg-light border-bottom mb-1">
                                <?php if($is_alumni_siswa): ?>
                                    <small class="text-muted d-block fw-bold">Status</small>
                                    <span class="fw-bold text-primary"><i class="fas fa-graduation-cap me-1"></i> Alumni</span>
                                    <small class="d-block text-muted mt-1" style="font-size: 10px;">Lulus dari kelas <?= htmlspecialchars($nama_kelas) ?> (TA <?= htmlspecialchars($tahun_lulus_siswa) ?>)</small>
                                <?php else: ?>
                                    <small class="text-muted d-block fw-bold">Kelas Saat Ini</small>
                                    <span class="fw-bold text-primary"><?= $nama_kelas ?></span>
                                    <?php if($belum_naik_kelas_siswa): ?>
                                        <small class="d-block text-warning mt-1" style="font-size: 10px;">Belum masuk Kenaikan Kelas TA <?= htmlspecialchars($tahun_ajaran_aktif_siswa) ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>
                            <li><a class="dropdown-item py-2 fw-bold text-secondary" href="?page=profil"><i class="fas fa-user-cog me-2 text-primary"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 fw-bold text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                 </div>
             </div>
        </nav>

        <div class="px-4 pb-5">
            <?php if(!isset($_GET['page']) || $_GET['page'] == 'dashboard'): ?>
                
                <div class="hero-banner p-5 mb-5 border-0">
                    <div class="bg-circle-1"></div>
                    <div class="bg-circle-2"></div>
                    <div class="row align-items-center position-relative" style="z-index: 2;">
                        <div class="col-md-8">
                            <?php if($is_alumni_siswa): ?>
                                <span class="badge badge-pastel-blue mb-3 px-3 py-2 rounded-pill fw-bold shadow-sm"><i class="fas fa-graduation-cap me-1"></i> Alumni SMK Jakarta Pusat 1</span>
                                <h2 class="fw-bold mb-2" style="font-size: 2.2rem;">Selamat, <?= $nama_siswa_login; ?>! 🎓</h2>
                                <p class="mb-0 fw-medium" style="font-size: 1.1rem; opacity: 0.85;">NISN: <?= $username_login ?> | Kelas Terakhir: <?= htmlspecialchars($nama_kelas) ?> | Lulus TA <?= htmlspecialchars($tahun_lulus_siswa) ?></p>
                            <?php else: ?>
                                <span class="badge badge-pastel-blue mb-3 px-3 py-2 rounded-pill fw-bold shadow-sm"><i class="fas fa-graduation-cap me-1"></i> Portal Akademik Siswa</span>
                                <h2 class="fw-bold mb-2" style="font-size: 2.2rem;">Halo, <?= $nama_siswa_login; ?>!</h2>
                                <p class="mb-0 fw-medium" style="font-size: 1.1rem; opacity: 0.85;">NISN: <?= $username_login ?> | TA <?= htmlspecialchars($tahun_ajaran_aktif_siswa) ?> | Semester: <?= strtoupper($sem_aktif) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center d-none d-md-block">
                            <div class="hero-icon-circle">
                                <i class="fas fa-graduation-cap fa-7x hero-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-1 text-dark">Ringkasan Capaian Belajar</h5>
                <?php if($is_alumni_siswa): ?>
                    <p class="text-muted small mb-4">Data dari semester terakhir sebelum lulus (<?= htmlspecialchars($nama_kelas) ?>, TA <?= htmlspecialchars($tahun_lulus_siswa) ?> <?= htmlspecialchars($semester_lulus_siswa) ?>).</p>
                <?php else: ?>
                    <div class="mb-4"></div>
                <?php endif; ?>
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold">RATA-RATA NILAI</small>
                                <h2 class="fw-bold mb-0 text-primary mt-2"><?= number_format($rata_akhir, 1) ?></h2>
                            </div>
                            <div class="icon-box" style="background-color: #D8E9FA; color: #0d385e;"><i class="fas fa-chart-line"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card attendance-card p-4 h-100 d-flex justify-content-between align-items-center"
                             data-bs-toggle="tooltip"
                             data-bs-placement="top"
                             data-bs-html="true"
                             data-bs-custom-class="attendance-tooltip-custom"
                             title="Sakit: <?= $sakit ?> hari<br>Izin: <?= $izin ?> hari<br>Alpha: <?= $alpa ?> hari<br>Total tidak masuk: <?= $tidak_masuk ?> hari">
                            <div>
                                <small class="text-muted fw-bold">KEHADIRAN</small>
                                <h2 class="fw-bold mb-0 text-success mt-2"><?= $persen_hadir ?>%</h2>
                                <span class="attendance-hint">Arahkan kursor untuk detail absen</span>
                            </div>
                            <div class="icon-box" style="background-color: #DDF5EA; color: #2E5E4E;"><i class="fas fa-user-check"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold">MAPEL TERISI</small>
                                <h2 class="fw-bold mb-0 text-info mt-2"><?= $count_mapel ?></h2>
                            </div>
                            <div class="icon-box" style="background-color: #F6E8C9; color: #87653B;"><i class="fas fa-book-open"></i></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Grafik Perkembangan Nilai</h6>
                        <small class="text-muted"><span class="badge" style="background-color: #FFB7B2; width: 12px; height: 12px; padding: 0;"> </span> Merah = Remedial</small>
                    </div>
                    <div style="height: 350px;"><canvas id="nilaiChart"></canvas></div>
                </div>

                <script>
                    const ctx = document.getElementById('nilaiChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($labels) ?>,
                            datasets: [
                                { label: 'STS (UTS)', data: <?= json_encode($data_p) ?>, backgroundColor: <?= json_encode($bg_p) ?>, borderRadius: 8 },
                                { label: 'SAS (UAS)', data: <?= json_encode($data_k) ?>, backgroundColor: <?= json_encode($bg_k) ?>, borderRadius: 8 }
                            ]
                        },
                        options: { 
                            responsive: true, maintainAspectRatio: false, 
                            scales: { 
                                y: { beginAtZero: true, max: 100, ticks: { font: { family: 'Poppins' } } },
                                x: { ticks: { font: { family: 'Poppins', size: 10 } } }
                            },
                            plugins: { legend: { labels: { font: { family: 'Poppins' } } } }
                        }
                    });
                </script>
            <?php else: 
                $page = $_GET['page'];
                if(file_exists($page.'.php')) { include $page.'.php'; } else { echo "<div class='alert alert-danger'>Halaman tidak ditemukan.</div>"; }
            endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
</body>
</html>