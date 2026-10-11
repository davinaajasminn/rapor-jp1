<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// Pastikan koneksi database ada
if (!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// --- TAHUN AJARAN AKTIF (dipakai buat Total Siswa & 2 grafik di bawah) ---
$qTaAktifDash = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$ta_aktif_dash = ($qTaAktifDash && $qTaAktifDash->num_rows > 0) ? $qTaAktifDash->fetch_assoc() : null;

if(!$ta_aktif_dash){
    $qTaLastDash = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY id DESC LIMIT 1");
    $ta_aktif_dash = ($qTaLastDash && $qTaLastDash->num_rows > 0) ? $qTaLastDash->fetch_assoc() : ['id' => 0, 'tahun' => '-', 'semester' => '-'];
}

$id_ta_dash = (int)$ta_aktif_dash['id'];
$id_ta_dash_safe = mysqli_real_escape_string($conn, $id_ta_dash);

// Cek apakah riwayat_kelas sudah punya data untuk tahun ajaran aktif ini
// (kalau belum, misal Kenaikan Kelas belum diproses, fallback ke penempatan kelas siswa saat ini).
$qCekRK = $conn->query("SELECT COUNT(*) as total FROM riwayat_kelas WHERE id_tahun_ajaran = '$id_ta_dash_safe'");
$ada_riwayat_dash = $qCekRK && ((int)($qCekRK->fetch_assoc()['total'] ?? 0) > 0);

// --- QUERY HITUNG DATA AKADEMIK ---
if($ada_riwayat_dash){
    // Sumber kebenaran: riwayat_kelas, ambil baris TERBARU per siswa untuk tahun ajaran aktif ini.
    $qSiswa = $conn->query("SELECT COUNT(*) as total FROM (
                                SELECT id_siswa
                                FROM riwayat_kelas
                                WHERE id_tahun_ajaran = '$id_ta_dash_safe' AND id_siswa != 0 AND id_kelas != 0
                                GROUP BY id_siswa
                            ) rk");
} else {
    $qSiswa = $conn->query("SELECT COUNT(*) as total FROM siswa");
}
$siswa  = $qSiswa->fetch_assoc()['total'] ?? 0;

$qGuru  = $conn->query("SELECT COUNT(*) as total FROM guru");
$guru   = $qGuru->fetch_assoc()['total'] ?? 0;

$qKelas = $conn->query("SELECT COUNT(*) as total FROM kelas");
$kelas  = $qKelas->fetch_assoc()['total'] ?? 0;

$qJurusanCount = $conn->query("SELECT COUNT(*) as total FROM jurusan");
$jurusan_total = $qJurusanCount->fetch_assoc()['total'] ?? 0;

// --- QUERY UNTUK GRAFIK (CHART) ---
// 1. Data Siswa per Jurusan
if($ada_riwayat_dash){
    $qJurusan = $conn->query("SELECT k.kode_jurusan, COUNT(*) as total
                               FROM (
                                   SELECT id_siswa, MAX(id) as max_id
                                   FROM riwayat_kelas
                                   WHERE id_tahun_ajaran = '$id_ta_dash_safe' AND id_siswa != 0 AND id_kelas != 0
                                   GROUP BY id_siswa
                               ) rk
                               INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                               INNER JOIN kelas k ON r.id_kelas = k.id
                               GROUP BY k.kode_jurusan");
} else {
    $qJurusan = $conn->query("SELECT k.kode_jurusan, COUNT(s.id) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id GROUP BY k.kode_jurusan");
}
$label_jurusan = []; 
$data_jurusan = [];

if($qJurusan) {
    while($row = $qJurusan->fetch_assoc()){
        $label_jurusan[] = $row['kode_jurusan'];
        $data_jurusan[] = $row['total'];
    }
}

// 2. Data Siswa per Tingkat
if($ada_riwayat_dash){
    $qTingkat = $conn->query("SELECT k.tingkat, COUNT(*) as total
                               FROM (
                                   SELECT id_siswa, MAX(id) as max_id
                                   FROM riwayat_kelas
                                   WHERE id_tahun_ajaran = '$id_ta_dash_safe' AND id_siswa != 0 AND id_kelas != 0
                                   GROUP BY id_siswa
                               ) rk
                               INNER JOIN riwayat_kelas r ON r.id = rk.max_id
                               INNER JOIN kelas k ON r.id_kelas = k.id
                               GROUP BY k.tingkat ORDER BY k.tingkat ASC");
} else {
    $qTingkat = $conn->query("SELECT k.tingkat, COUNT(s.id) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id GROUP BY k.tingkat ORDER BY k.tingkat ASC");
}
$label_tingkat = []; 
$data_tingkat = [];

if($qTingkat) {
    while($row = $qTingkat->fetch_assoc()){
        $label_tingkat[] = $row['tingkat'];
        $data_tingkat[] = $row['total'];
    }
}
?>

<style>
    /* Hero Icon Styling */
    .hero-icon {
        color: #ffffff;
        opacity: 0.98;
        filter: drop-shadow(0px 14px 12px rgba(13, 56, 94, 0.34));
        position: relative;
        z-index: 5;
    }

    /* Efek Kartu Statistik yang Bisa Diklik */
    .stat-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
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
        transition: 0.3s;
    }

    .stat-card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }

    /* Banner Selamat Datang dengan Background Mosaic */
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

    /* Overlay kiri dibuat terang supaya tulisan tetap kebaca */
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

    /* Overlay kanan dibuat lebih biru gelap */
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

        /* Dashboard stat card hanya tampilan informasi, bukan tombol */
        .stat-card {
            cursor: default !important;
        }
        .stat-card:hover {
            transform: none !important;
        }

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-2">
    
    <div class="hero-banner p-5 mb-5 border-0">
        <div class="bg-circle-1"></div>
        <div class="bg-circle-2"></div>
        
        <div class="row align-items-center hero-content">
            <div class="col-md-8">
                <span class="badge hero-badge mb-3 px-3 py-2 rounded-pill fw-bold">
                    <i class="fas fa-check-circle me-1 text-success"></i> Kontrol Panel Administrator
                </span>

                <h2 class="fw-bold mb-2 hero-title" style="font-size: 2.2rem;">
                    Selamat Datang, Admin!
                </h2>

                <p class="mb-0 fw-medium hero-desc" style="font-size: 1.1rem; opacity: 0.92;">
                    Kelola data master SMK Jakarta Pusat 1 dengan aman, transparan, dan terorganisir.
                </p>
            </div>

            <div class="col-md-4 text-center d-none d-md-block">
                <div class="cap-wrapper">
                    <i class="fas fa-graduation-cap fa-7x hero-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-4 text-dark">Ringkasan Data Induk</h5>

    <div class="row g-4 mb-5">
        
        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Total Siswa</small>
                    <h2 class="fw-bold mb-0 text-dark mt-2"><?= $siswa ?></h2>
                </div>

                <div class="icon-box" style="background-color: #fce4ec; color: #d81b60;">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Total Guru</small>
                    <h2 class="fw-bold mb-0 text-dark mt-2"><?= $guru ?></h2>
                </div>

                <div class="icon-box" style="background-color: #e8f5e9; color: #2e7d32;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Total Kelas</small>
                    <h2 class="fw-bold mb-0 text-dark mt-2"><?= $kelas ?></h2>
                </div>

                <div class="icon-box" style="background-color: #e1f5fe; color: #0277bd;">
                    <i class="fas fa-school"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card p-4 h-100 d-flex justify-content-between align-items-center">
                <div>
                    <small class="fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Total Jurusan</small>
                    <h2 class="fw-bold mb-0 text-dark mt-2"><?= $jurusan_total ?></h2>
                </div>

                <div class="icon-box" style="background-color: #f3e5f5; color: #7b1fa2;">
                    <i class="fas fa-project-diagram"></i>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4 mb-4">

        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h6 class="fw-bold text-dark mb-4">
                    <i class="fas fa-chart-pie me-2 text-primary"></i>Distribusi Siswa per Jurusan
                </h6>

                <div style="height: 300px; display: flex; justify-content: center;">
                    <canvas id="chartJurusan"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h6 class="fw-bold text-dark mb-4">
                    <i class="fas fa-chart-bar me-2 text-success"></i>Distribusi Siswa per Tingkat
                </h6>

                <div style="height: 300px;">
                    <canvas id="chartTingkat"></canvas>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
// DAFTAR WARNA PASTEL (Kalem & Modern)
const pastelPalette = [
    '#FFB7B2', '#B5EAD7', '#C7CEEA', '#FFDAC1', '#E2F0CB', '#F1CBFF'
];

// GRAFIK JURUSAN (DOUGHNUT)
const ctxJurusan = document.getElementById('chartJurusan').getContext('2d');

new Chart(ctxJurusan, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($label_jurusan) ?>,
        datasets: [{
            data: <?= json_encode($data_jurusan) ?>,
            backgroundColor: pastelPalette,
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { 
                position: 'bottom',
                labels: { 
                    font: { 
                        family: 'Poppins', 
                        size: 12 
                    } 
                }
            } 
        },
        cutout: '65%'
    }
});

// GRAFIK TINGKAT (BAR)
const ctxTingkat = document.getElementById('chartTingkat').getContext('2d');

new Chart(ctxTingkat, {
    type: 'bar',
    data: {
        labels: <?= json_encode($label_tingkat) ?>,
        datasets: [{
            label: 'Jumlah Siswa',
            data: <?= json_encode($data_tingkat) ?>,
            backgroundColor: pastelPalette,
            borderRadius: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { 
            y: { 
                beginAtZero: true, 
                ticks: { 
                    stepSize: 5, 
                    font: { 
                        family: 'Poppins' 
                    } 
                },
                grid: { 
                    borderDash: [5, 5] 
                }
            },
            x: {
                ticks: { 
                    font: { 
                        family: 'Poppins', 
                        weight: 'bold' 
                    } 
                },
                grid: { 
                    display: false 
                }
            }
        },
        plugins: { 
            legend: { 
                display: false 
            } 
        }
    }
});
</script>