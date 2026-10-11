<?php
session_start();
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != "login"){
    header("location:../index.php?pesan=belum_login"); exit;
}
$nama_user_login = $_SESSION['admin']['nama'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pengolahan Nilai - Admin</title>

    <link href="../assets/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS utama sistem -->
    <link rel="stylesheet" href="../assets/style.css">

    <script src="../assets/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>

<body>
    
<div class="d-flex">

    <!-- SIDEBAR -->
    <div class="sidebar p-0 pt-4 shadow" id="sidebarMenu">
        <div class="text-center mb-4 px-3">
            <img src="../assets/logo.png" alt="Logo" style="width: 70px;">
            <h5 class="fw-bold mb-0 mt-2">SMK Jakarta Pusat 1</h5>
            <small>Sistem Pengolahan Nilai</small>
        </div>

        <ul class="nav flex-column pe-3">
            <li class="nav-item">
                <a href="?page=dashboard" class="nav-link <?= (!isset($_GET['page']) || $_GET['page']=='dashboard')?'active':''; ?>">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=identitas" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='identitas')?'active':''; ?>">
                    <i class="fas fa-school me-2"></i> Identitas Sekolah
                </a>
            </li>

            <li class="nav-item mt-3 mb-1">
                <span class="text-secondary fw-bold ms-3" style="font-size: 11px; letter-spacing: 1px;">DATA USER</span>
            </li>

            <li class="nav-item">
                <a href="?page=data_guru" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='data_guru')?'active':''; ?>">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Data Guru
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=data_siswa" class="nav-link <?= (isset($_GET['page']) && ($_GET['page']=='data_siswa' || $_GET['page']=='edit_detail_siswa'))?'active':''; ?>">
                    <i class="fas fa-user-graduate me-2"></i> Data Siswa
                </a>
            </li>

            <li class="nav-item mt-3 mb-1">
                <span class="text-secondary fw-bold ms-3" style="font-size: 11px; letter-spacing: 1px;">DATA AKADEMIK</span>
            </li>

            <li class="nav-item">
                <a href="?page=data_jurusan" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='data_jurusan')?'active':''; ?>">
                    <i class="fas fa-layer-group me-2"></i> Data Jurusan
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=data_kelas" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='data_kelas')?'active':''; ?>">
                    <i class="fas fa-door-open me-2"></i> Data Kelas
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=data_mapel" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='data_mapel')?'active':''; ?>">
                    <i class="fas fa-book me-2"></i> Data Mapel
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=master_ekskul" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='master_ekskul')?'active':''; ?>">
                    <i class="fas fa-list-ul me-2"></i> Master Ekskul
                </a>
            </li>

            <li class="nav-item mt-3 mb-1">
                <span class="text-secondary fw-bold ms-3" style="font-size: 11px; letter-spacing: 1px;">DATA RAPOR</span>
            </li>

            <li class="nav-item">
                <a href="?page=input_absen" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='input_absen')?'active':''; ?>">
                    <i class="fas fa-user-check me-2"></i> Input Absensi
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=input_ekskul" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='input_ekskul')?'active':''; ?>">
                    <i class="fas fa-skating me-2"></i> Input Nilai Ekskul
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=atur_pembimbing_pkl" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='atur_pembimbing_pkl')?'active':''; ?>">
                    <i class="fas fa-user-tie me-2"></i> Atur Pembimbing PKL
                </a>
            </li>

            <li class="nav-item">
                <a href="?page=cetak_rapor" class="nav-link <?= (isset($_GET['page']) && $_GET['page']=='cetak_rapor')?'active':''; ?>">
                    <i class="fas fa-file-pdf me-2"></i> Cetak Rapor
                </a>
            </li>
        </ul>

        <div class="mt-auto pt-5 pb-4 px-3 text-center">
            <a href="../logout.php" class="btn btn-logout btn-sm w-100 rounded-pill text-decoration-none">
                <i class="fas fa-sign-out-alt me-2"></i> Logout Keluar
            </a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="flex-grow-1" style="height: 100vh; overflow-y: auto;">
        <nav class="navbar navbar-light shadow-sm mb-4 sticky-top px-4 d-flex justify-content-between main-navbar">
            <span class="navbar-brand h1 fw-bold text-secondary mb-0">Sistem Pengolahan Nilai</span>
            <span class="fw-bold text-dark"><?= $nama_user_login ?></span>
        </nav>

        <div class="px-4 pb-5">
            <?php
            $page = $_GET['page'] ?? 'dashboard';
            
            // Logika include file
            if($page == 'dashboard') {
                include 'dashboard.php';
            } else if($page == 'data_jurusan') {
                include 'data_jurusan.php';
            } else if($page == 'identitas') {
                include 'identitas.php';
            } else if($page == 'input_absen') {
                include 'input_absen.php'; 
            } else if($page == 'master_ekskul') {
                include 'master_ekskul.php'; 
            } else if($page == 'input_ekskul') {
                include 'input_ekskul.php'; 
            } else if($page == 'atur_pembimbing_pkl') {
                include 'atur_pembimbing_pkl.php';
            } else if($page == 'data_siswa') {
                include 'data_siswa.php';
            } else if($page == 'edit_detail_siswa') {
                include 'edit_detail_siswa.php';
            } else if(file_exists($page.'.php')) {
                include $page.'.php';
            } else {
                echo "<div class='p-5 text-center text-muted'><h3><i class='fas fa-tools mb-3'></i><br>Halaman Sedang Dibangun</h3></div>";
            }
            ?>
        </div>
    </div>

</div>

<script src="../assets/bootstrap.bundle.min.js"></script>
</body>
</html>