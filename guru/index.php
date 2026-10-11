<?php


session_start();

if(!isset($_SESSION['guru']) || $_SESSION['guru']['status'] != 'login'){
    session_destroy();
    header("location:../index.php?pesan=belum_login");
    exit;
}

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

$nip_guru = $_SESSION['guru']['nip'];

// --- AMBIL TAHUN AJARAN & SEMESTER AKTIF ---
// Dipakai untuk memfilter tugas mengajar (guru_mengajar) dan ditampilkan
// sebagai info di dashboard, supaya guru tahu sedang bekerja di periode mana.
$qTahunAktifDash = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahunAktifDash = ($qTahunAktifDash && $qTahunAktifDash->num_rows > 0) ? $qTahunAktifDash->fetch_assoc() : null;
$id_tahun_aktif = (int)($dTahunAktifDash['id'] ?? 0);
$tahun_aktif_label = $dTahunAktifDash['tahun'] ?? '-';
$semester_aktif_label = $dTahunAktifDash['semester'] ?? '-';

// --- 1. SINKRONISASI DATA GURU (AMBIL ID GURU & FOTO) ---
$qGuru = $conn->query("SELECT id, nama_guru, foto_profil FROM guru WHERE nip = '$nip_guru' LIMIT 1");
$dGuru = $qGuru->fetch_assoc();
$id_guru_login = $dGuru['id'] ?? 0;
$nama_user_login = $dGuru['nama_guru'] ?? $_SESSION['guru']['nama'];
$foto_profil_login = $dGuru['foto_profil'] ?? '';

// --- PROSES TANDAI BACA SEMUA NOTIFIKASI ---
if(isset($_GET['read_all']) && $_GET['read_all'] == 1) {
    $conn->query("UPDATE notifikasi SET status_baca = 1 WHERE penerima_role = 'guru' AND penerima_id = '$id_guru_login'");
    $qs = preg_replace('/&?read_all=1/', '', $_SERVER['QUERY_STRING']);
    header("Location: ?$qs");
    exit;
}

// Siapkan URL Foto buat Navbar
$avatar_nav = (!empty($foto_profil_login) && file_exists("../assets/uploads/".$foto_profil_login)) 
              ? "../assets/uploads/".$foto_profil_login 
              : "https://ui-avatars.com/api/?name=".urlencode($nama_user_login)."&background=random&color=fff&rounded=true&bold=true";

// --- 2. CEK STATUS WALI KELAS ---
// Sekarang dicek lewat riwayat_wali_kelas untuk tahun ajaran yang sedang aktif,
// bukan lagi dari kolom statis kelas.id_wali_kelas. Supaya guru yang tahun lalu
// jadi wali tapi tahun ini belum/tidak ditugaskan lagi, tidak ikut kehitung.
$list_kelas_wali = []; 
$tahun_aktif_esc = mysqli_real_escape_string($conn, $tahun_aktif_label);
$cekWali = $conn->query("SELECT k.* 
                         FROM kelas k
                         JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id
                         WHERE rwk.id_guru = '$id_guru_login'
                         AND rwk.tahun = '$tahun_aktif_esc'");
$is_wali = $cekWali->num_rows > 0;

if($is_wali) {
    while($rowWali = $cekWali->fetch_assoc()){
        $list_kelas_wali[] = $rowWali; 
    }
}

// --- 3. AMBIL DATA MENGAJAR ---
// Dipisah supaya dashboard tidak rancu:
// - Kelas Mengajar = kelas dari mapel reguler di guru_mengajar
// - Mapel Diampu = mapel reguler di guru_mengajar
// - Bimbingan PKL = siswa/kelas dari data_pkl.id_guru_pembimbing
$mapel_unik_nama = [];
$kelas_mengajar = []; 
$mapel_ids_diampu = [];
$has_mapel_pkl = false;

$qMengajar = $conn->query("SELECT gm.id_mapel, gm.id_kelas, m.nama_mapel, k.nama_kelas 
                            FROM guru_mengajar gm
                            JOIN mapel m ON gm.id_mapel = m.id
                            JOIN kelas k ON gm.id_kelas = k.id
                            WHERE gm.id_guru = '$id_guru_login'
                            AND gm.id_tahun_ajaran = '$id_tahun_aktif'");

while($dm = $qMengajar->fetch_assoc()){
    $is_pkl_dm = (
        stripos($dm['nama_mapel'], 'PKL') !== false ||
        stripos($dm['nama_mapel'], 'Praktik Kerja Lapangan') !== false ||
        stripos($dm['nama_mapel'], 'Prakerin') !== false
    );

    if($is_pkl_dm){
        $has_mapel_pkl = true;
        continue; // PKL tidak dihitung sebagai Mapel Diampu/Kelas Mengajar reguler
    }

    if(!in_array($dm['nama_mapel'], $mapel_unik_nama)) $mapel_unik_nama[] = $dm['nama_mapel'];
    if(!in_array($dm['nama_kelas'], $kelas_mengajar)) $kelas_mengajar[] = $dm['nama_kelas'];

    $mapel_ids_diampu[] = [
        'id_m' => $dm['id_mapel'], 
        'id_k' => $dm['id_kelas']
    ];
}


// --- 3B. CEK PENUGASAN GURU PEMBIMBING PKL DARI ADMIN ---
// Penting: guru pembimbing PKL dibaca dari data_pkl.id_guru_pembimbing,
// bukan dari wali kelas dan bukan harus ada di guru_mengajar.
$has_pkl_assignment = false;
$qCekTugasPKL = $conn->query("SELECT COUNT(*) AS total 
                              FROM data_pkl 
                              WHERE id_guru_pembimbing = '$id_guru_login'
                              AND id_tahun_ajaran = '$id_tahun_aktif'");
if($qCekTugasPKL && $qCekTugasPKL->num_rows > 0){
    $dCekTugasPKL = $qCekTugasPKL->fetch_assoc();
    $has_pkl_assignment = ((int)$dCekTugasPKL['total'] > 0);
}

$has_mapel_pkl = ($has_mapel_pkl || $has_pkl_assignment);

// --- 3C. HITUNG STATISTIK BIMBINGAN PKL ---
$total_kelas_bimbingan_pkl = 0;
$total_siswa_bimbingan_pkl = 0;
$total_pkl_valid = 0;
$progres_pkl = 0;

if($has_pkl_assignment){
    $qStatPKL = $conn->query("SELECT 
                                COUNT(DISTINCT s.id_kelas) AS total_kelas_pkl,
                                COUNT(DISTINCT s.id) AS total_siswa_pkl,
                                SUM(CASE 
                                    WHEN dp.status_validasi_pkl = 1
                                     AND dp.tp1_skor IS NOT NULL AND dp.tp1_skor != ''
                                     AND dp.tp2_skor IS NOT NULL AND dp.tp2_skor != ''
                                     AND dp.tp3_skor IS NOT NULL AND dp.tp3_skor != ''
                                     AND dp.tp4_skor IS NOT NULL AND dp.tp4_skor != ''
                                    THEN 1 ELSE 0 END
                                ) AS total_valid_pkl
                              FROM data_pkl dp
                              JOIN siswa s ON dp.id_siswa = s.id
                              WHERE dp.id_guru_pembimbing = '$id_guru_login'
                              AND dp.id_tahun_ajaran = '$id_tahun_aktif'");

    if($qStatPKL && $qStatPKL->num_rows > 0){
        $dStatPKL = $qStatPKL->fetch_assoc();
        $total_kelas_bimbingan_pkl = (int)($dStatPKL['total_kelas_pkl'] ?? 0);
        $total_siswa_bimbingan_pkl = (int)($dStatPKL['total_siswa_pkl'] ?? 0);
        $total_pkl_valid = (int)($dStatPKL['total_valid_pkl'] ?? 0);
        $progres_pkl = ($total_siswa_bimbingan_pkl > 0) ? round(($total_pkl_valid / $total_siswa_bimbingan_pkl) * 100) : 0;
    }
}


// --- 4. HITUNG PROGRES INPUT NILAI ---
$mapel_selesai = 0; 
$progres = 0;

if(count($mapel_ids_diampu) > 0){
    foreach($mapel_ids_diampu as $tugas){
        $m_id = $tugas['id_m']; 
        if(!empty($m_id)) {
            $k_id = $tugas['id_k'];
            // FIX: siswa kelas ini diambil dari riwayat_kelas sesuai tahun ajaran aktif,
            // BUKAN dari siswa.id_kelas (posisi kelas siswa saat ini) -- soalnya kalau
            // TA aktif bukan TA sekarang, siswa yang dulu di kelas ini udah pindah/naik
            // kelas di tabel siswa yang live. Nilai-nya juga wajib difilter per TA aktif,
            // sebelumnya query ini gak ngecek id_tahun_ajaran sama sekali.
            $resNilai = $conn->query("SELECT n.id 
                                      FROM riwayat_kelas rk
                                      JOIN siswa s ON s.id = rk.id_siswa
                                      JOIN nilai n ON n.id_siswa = s.id AND n.id_tahun_ajaran = rk.id_tahun_ajaran
                                      WHERE n.id_mapel = '$m_id'
                                      AND rk.id_kelas = '$k_id'
                                      AND rk.id_tahun_ajaran = '$id_tahun_aktif'
                                      LIMIT 1");
            if($resNilai && $resNilai->num_rows > 0) {
                $mapel_selesai++;
            }
        }
    }
    $progres = round(($mapel_selesai / count($mapel_ids_diampu)) * 100);
}

// --- 5. HITUNG STATISTIK ---
$nama_kelas_wali = array_column($list_kelas_wali, 'nama_kelas');

$total_kelas_mengajar = count($kelas_mengajar);
$total_mapel = count($mapel_unik_nama);
$total_kelas_wali = count($nama_kelas_wali);

// Variabel lama tetap disiapkan agar tidak mengganggu bagian lain kalau masih dipakai.
$total_kelas = $total_kelas_mengajar;

// Progress Input tetap untuk nilai mapel reguler.
// Progress PKL dibuat card terpisah agar tidak tercampur dengan input nilai mapel.
$progres_input_mapel = $progres;


// --- PROSES HAPUS NOTIFIKASI GURU ---
if(isset($_GET['hapus_notif'])){
    $id_notif = mysqli_real_escape_string($conn, $_GET['hapus_notif']);

    $id_guru_login_notif = $_SESSION['guru']['id'] ?? ($_SESSION['guru']['id_guru'] ?? '');
    $nip_guru_login_notif = $_SESSION['guru']['nip'] ?? '';

    if(empty($id_guru_login_notif) && !empty($nip_guru_login_notif)){
        $nip_safe_notif = mysqli_real_escape_string($conn, $nip_guru_login_notif);
        $qGuruNotif = $conn->query("SELECT id FROM guru WHERE nip='$nip_safe_notif' LIMIT 1");

        if($qGuruNotif && $qGuruNotif->num_rows > 0){
            $id_guru_login_notif = $qGuruNotif->fetch_assoc()['id'];
        }
    }

    $id_guru_login_notif = mysqli_real_escape_string($conn, $id_guru_login_notif);

    $conn->query("DELETE FROM notifikasi 
                  WHERE id='$id_notif' 
                  AND penerima_role='guru' 
                  AND penerima_id='$id_guru_login_notif'");

    $page_now = $_GET['page'] ?? 'dashboard';
    // 🩹 FIX: di-encode dulu biar gak bisa disuntik keluar dari tag <script> (reflected XSS)
    echo "<script>window.location='index.php?page=" . rawurlencode($page_now) . "';</script>";
    exit;
}

// --- PROSES HAPUS SEMUA NOTIFIKASI GURU ---
if(isset($_GET['hapus_semua_notif'])){
    $id_guru_login_notif = $_SESSION['guru']['id'] ?? ($_SESSION['guru']['id_guru'] ?? '');
    $nip_guru_login_notif = $_SESSION['guru']['nip'] ?? '';

    if(empty($id_guru_login_notif) && !empty($nip_guru_login_notif)){
        $nip_safe_notif = mysqli_real_escape_string($conn, $nip_guru_login_notif);
        $qGuruNotif = $conn->query("SELECT id FROM guru WHERE nip='$nip_safe_notif' LIMIT 1");

        if($qGuruNotif && $qGuruNotif->num_rows > 0){
            $id_guru_login_notif = $qGuruNotif->fetch_assoc()['id'];
        }
    }

    $id_guru_login_notif = mysqli_real_escape_string($conn, $id_guru_login_notif);

    $conn->query("DELETE FROM notifikasi 
                  WHERE penerima_role='guru' 
                  AND penerima_id='$id_guru_login_notif'");

    $page_now = $_GET['page'] ?? 'dashboard';
    // 🩹 FIX: di-encode dulu biar gak bisa disuntik keluar dari tag <script> (reflected XSS)
    echo "<script>window.location='index.php?page=" . rawurlencode($page_now) . "';</script>";
    exit;
}


// --- 6. LOGIKA NOTIFIKASI ---
$my_role = 'guru'; 
$my_id = $id_guru_login; 

$qUnread = $conn->query("SELECT COUNT(id) as total FROM notifikasi WHERE penerima_role = '$my_role' AND penerima_id = '$my_id' AND NOT (pengirim_role = '$my_role' AND pengirim_id = '$my_id') AND status_baca = 0");
$jml_notif = ($qUnread && $qUnread->num_rows > 0) ? $qUnread->fetch_assoc()['total'] : 0;

$qNotif = $conn->query("SELECT * FROM notifikasi WHERE penerima_role = '$my_role' AND penerima_id = '$my_id' AND NOT (pengirim_role = '$my_role' AND pengirim_id = '$my_id') ORDER BY waktu DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - SMK JP 1</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
        }

        .sidebar .nav-link.active { 
            background-color: rgba(255,255,255,0.90); 
            color: var(--text-dark-blue) !important; 
            box-shadow: 0 4px 10px rgba(80, 115, 155, 0.18); 
            font-weight: 700;
        }

        .sidebar small,
        .sidebar .text-muted {
            color: rgba(13, 56, 94, 0.62) !important;
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
        
        @media print {
            .sidebar, .navbar { display: none !important; }
            .flex-grow-1 { height: auto !important; overflow: visible !important; }
            .px-4 { padding: 0 !important; }
            body { background-color: white !important; }
        }

        /* Card dashboard */
        .stat-card {
            background: white; 
            border-radius: 20px; 
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            transition: none;
            overflow: hidden; 
            cursor: default;
        }

        .stat-card:hover {
            transform: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .icon-box {
            width: 60px; 
            height: 60px; 
            border-radius: 15px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 24px; 
            transition: none;
        }

        /* Banner mosaic seperti admin */
        .hero-banner {
            background-image: url('../assets/bg-dashboard.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            border-radius: 25px;
            color: #1e3c72;
            box-shadow: 0 14px 35px rgba(92, 130, 176, 0.30);
            position: relative;
            overflow: hidden;
            min-height: 245px;
            border: 1px solid rgba(255, 255, 255, 0.55);
        }

        .hero-banner::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0.50) 0%,
                rgba(255, 255, 255, 0.36) 34%,
                rgba(176, 196, 222, 0.12) 60%,
                rgba(176, 196, 222, 0.03) 100%
            );
            z-index: 1;
        }

        .hero-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(176, 196, 222, 0.08) 0%,
                rgba(176, 196, 222, 0.10) 45%,
                rgba(176, 196, 222, 0.30) 72%,
                rgba(120, 155, 200, 0.55) 100%
            );
            z-index: 2;
        }

        .hero-content {
            position: relative;
            z-index: 4;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.88) !important;
            color: #0d6efd !important;
            box-shadow: 0 8px 18px rgba(13, 56, 94, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.80);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .hero-title {
            color: #173c76;
            text-shadow: 0 2px 6px rgba(255, 255, 255, 0.35);
        }

        .hero-desc {
            color: #2b4e83;
            text-shadow: 0 2px 6px rgba(255, 255, 255, 0.30);
        }

        .hero-icon {
            color: #ffffff;
            opacity: 0.98;
            filter: drop-shadow(0px 14px 12px rgba(13, 56, 94, 0.34));
            position: relative;
            z-index: 5;
        }

        .bg-circle-1 {
            position: absolute;
            width: 260px;
            height: 260px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            top: -85px;
            right: -55px;
            z-index: 3;
        }

        .bg-circle-2 {
            position: absolute;
            width: 155px;
            height: 155px;
            background: rgba(255,255,255,0.10);
            border-radius: 50%;
            bottom: -35px;
            right: 180px;
            z-index: 3;
        }

        .cap-wrapper {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.13);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            position: relative;
            z-index: 4;
        }

        @media (max-width: 768px) {
            .hero-banner {
                min-height: auto;
                padding: 35px 25px !important;
            }

            .hero-title {
                font-size: 1.75rem !important;
            }

            .hero-desc {
                font-size: 1rem !important;
            }
        }
    
        .btn-hapus-notif-guru {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #FFE3E8 !important;
            color: #842029 !important;
            border: 1px solid #F2A6B3 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            text-decoration: none !important;
            flex-shrink: 0;
        }

        .btn-hapus-notif-guru:hover {
            background: #F8D7DA !important;
            color: #842029 !important;
        }

        .notif-link-guru {
            flex: 1;
            min-width: 0;
            color: inherit;
            text-decoration: none;
        }

        .notif-link-guru:hover {
            color: inherit;
            text-decoration: none;
        }

        .btn-hapus-semua-notif-guru {
            color: #842029 !important;
            font-weight: 700;
            text-decoration: none;
            font-size: 12px;
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
    <div class="sidebar p-0 pt-4 shadow d-print-none">
        <div class="text-center mb-4 px-3">
            <img src="../assets/logo.png" style="width: 70px; margin-bottom: 15px;">
            <h5 class="fw-bold mb-0">SMK Jakarta Pusat 1</h5>
            <small>Sistem Pengolahan Nilai</small>
        </div>

        <ul class="nav flex-column pe-3"> 
            <li class="nav-item">
                <a href="?page=dashboard" class="nav-link <?= (!isset($_GET['page']) || $_GET['page']=='dashboard')?'active':''; ?>">
                    <i class="fas fa-home me-2"></i> Beranda
                </a>
            </li>

            <?php if($total_mapel > 0 || $has_mapel_pkl): ?>
            <li class="nav-item mt-3"><small class="text-muted ms-4 fw-bold">MENU GURU MAPEL</small></li>

            <?php if($total_mapel > 0): ?>
            <li class="nav-item">
                <a href="?page=input_nilai" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='input_nilai')?'active':''; ?>">
                    <i class="fas fa-edit me-2"></i> Input Nilai Mapel
                </a>
            </li>
            <?php endif; ?>

            <?php if($has_mapel_pkl): ?>
            <li class="nav-item">
                <a href="?page=input_pkl" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='input_pkl')?'active':''; ?>">
                    <i class="fas fa-briefcase me-2"></i> Input PKL
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=rekap_pkl" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='rekap_pkl')?'active':''; ?>">
                    <i class="fas fa-table-list me-2"></i> Rekap Nilai PKL
                </a>
            </li>
            <?php endif; ?>

            <?php if($total_mapel > 0): ?>
            <li class="nav-item">
                <a href="?page=setting_cp" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='setting_cp')?'active':''; ?>">
                    <i class="fas fa-cog me-2"></i> Setting Kalimat CP
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if($is_wali): ?>
            <li class="nav-item mt-3"><small class="text-muted ms-4 fw-bold">MENU WALI KELAS</small></li>

            <li class="nav-item">
                <a href="?page=ranking" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='ranking')?'active':''; ?>">
                    <i class="fas fa-trophy me-2"></i> Ranking Kelas
                </a>
            </li>
            
            <li class="nav-item">
                <a href="?page=validasi_index" class="nav-link <?= (isset($_GET['page']) && in_array($_GET['page'], ['validasi_index', 'wali_siswa', 'validasi_detail', 'rekap_input_kelas', 'input_catatan', 'ledger_view']))?'active':''; ?>">
                    <i class="fas fa-user-check me-2"></i> Validasi Siswa
                </a>
            </li>
            
            <li class="nav-item">
                <a href="?page=cetak_rapor" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='cetak_rapor')?'active':''; ?>">
                    <i class="fas fa-print me-2"></i> Cetak Rapor
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item mt-3"><small class="text-muted ms-4 fw-bold">PENGATURAN</small></li>

            <li class="nav-item">
                <a href="?page=profil" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='profil')?'active':''; ?>">
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

    <div class="flex-grow-1 print-area" style="min-height: 100vh; overflow-y: auto;">
        
        <nav class="navbar navbar-light bg-white shadow-sm mb-4 sticky-top d-print-none">
             <div class="container-fluid px-4 d-flex justify-content-end align-items-center">
                
                <div class="dropdown me-3 d-inline-block">
                    <a class="nav-link dropdown-toggle position-relative text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="border:none; background:transparent;">
                        <i class="fas fa-bell fa-lg"></i>

                        <?php if($jml_notif > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?= $jml_notif ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="p-3 bg-light border-bottom rounded-top-4 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-bell text-warning me-2"></i>Notifikasi</h6>
                            <?php if($jml_notif > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $jml_notif ?> Baru</span>
                            <?php endif; ?>
                        </li>
                        
                        <?php if($qNotif && $qNotif->num_rows > 0): while($n = $qNotif->fetch_assoc()): 
                            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($n['nama_pengirim']) . "&background=random&color=fff&rounded=true&bold=true";
                            $bg_unread = ($n['status_baca'] == 0) ? 'background-color: #f0f7ff;' : '';
                        ?>
                        <li class="border-bottom" style="<?= $bg_unread ?>">
                            <div class="d-flex align-items-start p-3">
                                <a class="notif-link-guru d-flex align-items-start text-wrap" href="<?= $n['link_tujuan'] ?>">
                                    <img src="<?= $avatar_url ?>" alt="Avatar" class="rounded-circle shadow-sm me-3" width="40" height="40">
                                    <div>
                                        <span class="d-block fw-bold small text-dark mb-1">
                                            <?= $n['nama_pengirim'] ?> 
                                            <small class="text-muted fw-normal">(<?= ucfirst($n['pengirim_role']) ?>)</small>
                                        </span>

                                        <span class="d-block small text-secondary" style="font-size: 11px; line-height: 1.3;">
                                            <?= htmlspecialchars($n['pesan']) ?>
                                        </span>

                                        <small class="text-muted mt-1 d-block" style="font-size: 9px;">
                                            <i class="fas fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($n['waktu'])) ?>
                                        </small>
                                    </div>
                                </a>

                                <a href="?page=<?= $_GET['page'] ?? 'dashboard' ?>&hapus_notif=<?= $n['id'] ?>" 
                                   class="btn-hapus-notif-guru ms-2"
                                   onclick="return confirm('Hapus notifikasi ini?');"
                                   title="Hapus notifikasi">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </li>
                        <?php endwhile; else: ?>
                        <li class="p-4 text-center text-muted small">
                            <i class="fas fa-bell-slash fa-2x mb-2 opacity-25"></i><br>Belum ada notifikasi baru.
                        </li>
                        <?php endif; ?>
                        
                        <li class="p-2 text-center bg-light rounded-bottom-4">
                            <?php 
                            $qs = $_SERVER['QUERY_STRING']; 
                            $qs = preg_replace('/&?read_all=1/', '', $qs);
                            $link_read_all = "?" . $qs . ($qs ? "&" : "") . "read_all=1";
                            ?>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="<?= $link_read_all ?>" class="text-primary small fw-bold text-decoration-none">Tandai semua dibaca</a>

                                <?php if($qNotif && $qNotif->num_rows > 0): ?>
                                    <a href="?page=<?= $_GET['page'] ?? 'dashboard' ?>&hapus_semua_notif=1" 
                                       class="btn-hapus-semua-notif-guru"
                                       onclick="return confirm('Hapus semua notifikasi?');">
                                        Hapus semua
                                    </a>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="dropdown d-inline-block">
                    <a class="nav-link dropdown-toggle d-flex align-items-center bg-light rounded-pill pe-3 ps-1 py-1 shadow-sm border text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= $avatar_nav ?>" alt="Profile" class="rounded-circle me-2 bg-white" width="35" height="35" style="object-fit: cover;">
                        <span class="fw-bold text-dark small me-1"><?= $nama_user_login; ?></span>
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

            if($page == 'dashboard'): ?>
                
                <div class="hero-banner p-5 mb-5 border-0">
                    <div class="bg-circle-1"></div>
                    <div class="bg-circle-2"></div>
                    
                    <div class="row align-items-center hero-content">
                        <div class="col-md-8">
                            <span class="badge hero-badge mb-3 px-3 py-2 rounded-pill fw-bold">
                                <i class="fas fa-chalkboard-teacher me-1 text-success"></i> Panel Guru Aktif
                            </span>

                            <span class="badge mb-3 ms-2 px-3 py-2 rounded-pill fw-bold" style="background:#EAF3FF; color:#0d385e; border:1px solid #AECDEE;">
                                <i class="fas fa-calendar-check me-1"></i> TP <?= htmlspecialchars($tahun_aktif_label) ?> - Semester <?= htmlspecialchars(strtoupper($semester_aktif_label)) ?>
                            </span>

                            <h2 class="fw-bold mb-2 hero-title" style="font-size: 2.2rem;">
                                Selamat Datang, <?= $nama_user_login; ?>!
                            </h2>

                            <p class="mb-0 fw-medium hero-desc" style="font-size: 1.1rem; opacity: 0.92;">
                                Selamat bekerja kembali di Sistem Pengolahan Nilai SMK Jakarta Pusat 1. Semangat mendidik!
                            </p>
                        </div>

                        <div class="col-md-4 text-center d-none d-md-block">
                            <div class="cap-wrapper">
                                <i class="fas fa-book-reader fa-6x hero-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $has_any_tugas = ($total_mapel > 0 || $has_pkl_assignment || $is_wali); ?>

                <?php if($has_any_tugas): ?>
                <h5 class="fw-bold mb-4 text-dark">Ringkasan Tugas Mengajar</h5>

                <div class="row g-4 mb-5">

                    <?php if($total_mapel > 0): ?>
                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Kelas Mengajar</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $total_kelas_mengajar ?></h2>
                            </div>

                            <div class="icon-box" style="background-color: #e3f2fd; color: #1e40af;">
                                <i class="fas fa-school"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Mapel Diampu</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $total_mapel ?></h2>
                            </div>

                            <div class="icon-box" style="background-color: #f3e8ff; color: #6b21a8;">
                                <i class="fas fa-book-open"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Progres Input Mapel</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $progres_input_mapel ?>%</h2>
                            </div>

                            <div class="icon-box" style="background-color: #dcfce7; color: #166534;">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($has_pkl_assignment): ?>
                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Kelas Bimbingan PKL</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $total_kelas_bimbingan_pkl ?></h2>
                            </div>

                            <div class="icon-box" style="background-color: #fff7d6; color: #9a5b00;">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Siswa Bimbingan PKL</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $total_siswa_bimbingan_pkl ?></h2>
                            </div>

                            <div class="icon-box" style="background-color: #ffe4e6; color: #9f1239;">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Progres PKL</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $progres_pkl ?>%</h2>
                            </div>

                            <div class="icon-box" style="background-color: #dff7f8; color: #0f766e;">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($is_wali): ?>
                    <div class="col-md-3">
                        <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Kelas Wali</small>
                                <h2 class="fw-bold mb-0 text-dark mt-2"><?= $total_kelas_wali ?></h2>
                            </div>

                            <div class="icon-box" style="background-color: #eef2ff; color: #3730a3;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-5 text-center" style="background:#f8fbff;">
                    <i class="fas fa-mug-hot fa-2x mb-3" style="color:#AECDEE;"></i>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Tugas di TP <?= htmlspecialchars($tahun_aktif_label) ?> - Semester <?= htmlspecialchars(strtoupper($semester_aktif_label)) ?></h6>
                    <p class="text-muted small mb-0">Belum ada penugasan mengajar, bimbingan PKL, maupun wali kelas untuk periode ini. Hubungi admin kalau ini seharusnya sudah diatur.</p>
                </div>
                <?php endif; ?>

            <?php 
            else: 
                if(file_exists($page.'.php')) {
                    include $page.'.php';
                } else {
                    echo "<div class='text-center py-5'><h5>Halaman tidak ditemukan.</h5></div>";
                }
            endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>