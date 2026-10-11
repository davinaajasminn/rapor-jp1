<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

// --- PROSES TAMBAH ---
if(isset($_POST['simpan_ta'])){
    $tahun = $_POST['tahun'];
    $semester = $_POST['semester'];
    
    $cek = $conn->query("SELECT * FROM tahun_ajaran WHERE tahun='$tahun' AND semester='$semester'");
    if($cek->num_rows > 0){
        echo "<script>alert('Data Tahun Ajaran tersebut sudah ada!');</script>";
    } else {
        $sql = "INSERT INTO tahun_ajaran (tahun, semester, status) VALUES ('$tahun', '$semester', 'Tidak Aktif')";
        if($conn->query($sql)){
            echo "<script>alert('Berhasil menambah Tahun Ajaran!'); window.location='?page=tahun_ajaran';</script>";
        }
    }
}

// --- PROSES AKTIVASI ---
// --- PROSES AKTIVASI + KENAIKAN KELAS OTOMATIS ---
if(isset($_GET['aktifkan_id'])){
    $id_aktif = mysqli_real_escape_string($conn, $_GET['aktifkan_id']);
    
    // 1. Ambil data tahun ajaran target yang mau diaktifkan
    $qTarget = $conn->query("SELECT * FROM tahun_ajaran WHERE id = '$id_aktif' LIMIT 1");
    if($qTarget && $qTarget->num_rows > 0){
        $dTarget = $qTarget->fetch_assoc();
        $target_tahun = $dTarget['tahun'];     // Contoh: 2026/2027
        $target_semester = $dTarget['semester']; // Ganjil / Genap

        // 2. Jika target semester adalah GANJIL, itu tandanya pergantian tahun ajaran baru (Proses Kenaikan Kelas)
        if(strtolower($target_semester) == 'ganjil'){
            
            // Ambil semua data siswa aktif saat ini
            $qSiswa = $conn->query("SELECT s.id, s.id_kelas, k.nama_kelas, k.kode_jurusan 
                                    FROM siswa s 
                                    JOIN kelas k ON s.id_kelas = k.id");
            
            while($siswa = $qSiswa->fetch_assoc()){
                $id_siswa = $siswa['id'];
                $nama_kelas_lama = $siswa['nama_kelas']; // Contoh: "X MP 1"
                $jurusan = $siswa['kode_jurusan'];       // Contoh: "MP"
                
                // Pecah teks nama kelas buat nyari tingkat rombelnya (X, XI, XII)
                $part = explode(' ', $nama_kelas_lama);
                $tingkat = $part[0]; // X / XI / XII
                
                // Logika Kenaikan Tingkat
                $tingkat_baru = '';
                if($tingkat == 'X') {
                    $tingkat_baru = 'XI';
                } elseif($tingkat == 'XI') {
                    $tingkat_baru = 'XII';
                } elseif($tingkat == 'XII') {
                    $tingkat_baru = 'LULUS';
                }

                if($tingkat_baru == 'LULUS'){
                    // Opsi A: Siswa kelas XII bisa lu set ke id_kelas = 0 atau buat status lulus
                    // Untuk amannya, biarkan kelasnya tetap XII tapi datanya ga diubah atau pindah ke kelas khusus alumni
                } elseif(!empty($tingkat_baru)) {
                    // Susun nama kelas target baru (Misal dari "X MP 1" menjadi "XI MP 1")
                    $nama_kelas_baru = str_replace($tingkat, $tingkat_baru, $nama_kelas_lama);
                    $nama_kelas_baru_safe = mysqli_real_escape_string($conn, $nama_kelas_baru);
                    
                    // Cari ID kelas barunya di tabel kelas
                    $qKelasBaru = $conn->query("SELECT id FROM kelas WHERE nama_kelas = '$nama_kelas_baru_safe' LIMIT 1");
                    if($qKelasBaru && $qKelasBaru->num_rows > 0){
                        $id_kelas_baru = $qKelasBaru->fetch_assoc()['id'];
                        
                        // Eksekusi perpindahan kelas siswa ke tingkat yang baru!
                        $conn->query("UPDATE siswa SET id_kelas = '$id_kelas_baru' WHERE id = '$id_siswa'");
                    }
                }
            }
        }
    }

    // 3. Ubah status aktifasi tahun ajaran di database seperti semula
    $conn->query("UPDATE tahun_ajaran SET status = 'Tidak Aktif'");
    $conn->query("UPDATE tahun_ajaran SET status = 'Aktif' WHERE id = '$id_aktif'");
    
    echo "<script>alert('Tahun Ajaran Berhasil Dialihkan! Data siswa otomatis disinkronkan ke kelas baru.'); window.location='?page=tahun_ajaran';</script>";
    exit;
}

// --- PROSES HAPUS ---
if(isset($_GET['hapus_id'])){
    $id_hapus = $_GET['hapus_id'];
    $conn->query("DELETE FROM tahun_ajaran WHERE id = '$id_hapus'");
    echo "<script>alert('Data berhasil dihapus!'); window.location='?page=tahun_ajaran';</script>";
}
?>

<style>
    /* Warna Pastel Palette */
    :root {
        --pastel-blue-main: #8cb6f0; /* Biru Utama (Sama kayak Sidebar/Tombol) */
        --pastel-green-bg: #d1fae5;
        --pastel-green-text: #065f46;
    }

    /* Tombol Utama (Tambah) - Warna disamakan navigasi */
    .btn-pastel-primary {
        background-color: var(--pastel-blue-main); 
        color: white;
        border: none;
        transition: 0.3s;
        box-shadow: 0 4px 6px rgba(140, 182, 240, 0.3);
    }
    .btn-pastel-primary:hover {
        background-color: #7aa9e8;
        color: white;
        transform: translateY(-2px);
    }

    /* Badge Status Aktif (DIPERKECIL) */
    .badge-pastel-green {
        background-color: var(--pastel-green-bg);
        color: var(--pastel-green-text);
        font-weight: 700;
        font-size: 10px; /* Font lebih kecil */
        padding: 5px 12px; /* Padding lebih tipis */
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        letter-spacing: 0.5px;
    }

    /* Badge Ganjil/Genap */
    .badge-semester {
        font-size: 11px;
        padding: 5px 10px;
        border-radius: 8px;
    }
    .badge-ganjil { background-color: #fef3c7; color: #92400e; }
    .badge-genap { background-color: #dbeafe; color: #1e40af; }

    /* Tombol Aktifkan (Outline) */
    .btn-outline-pastel {
        border: 1px solid #cbd5e1;
        color: #64748b;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 12px;
        background: white;
        transition: 0.2s;
    }
    .btn-outline-pastel:hover {
        border-color: var(--pastel-blue-main);
        color: var(--pastel-blue-main);
        background: #f8fafc;
    }
    
    /* Modal Header */
    .modal-header-pastel {
        background-color: #f0f9ff;
        border-bottom: 1px solid #e0f2fe;
    }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Data Tahun Ajaran</h4>
            <small class="text-muted">Atur tahun ajaran dan semester aktif</small>
        </div>
        <button class="btn btn-pastel-primary rounded-pill px-4 btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i> Tambah Data
        </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary small text-uppercase" style="font-size: 11px; letter-spacing: 1px;">
                            <th class="py-3 px-4">No</th>
                            <th class="py-3">Tahun Ajaran</th>
                            <th class="py-3">Semester</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $query = "SELECT * FROM tahun_ajaran ORDER BY tahun DESC, semester DESC";
                        $result = $conn->query($query);

                        if($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                $is_active = ($row['status'] == 'Aktif');
                        ?>
                        <tr style="<?php echo $is_active ? 'background-color: #f0fdf4;' : ''; ?>"> 
                            <td class="px-4 text-muted small"><?php echo $no++; ?></td>
                            <td class="fw-bold text-dark"><?php echo $row['tahun']; ?></td>
                            <td>
                                <?php if($row['semester'] == 'Ganjil'): ?>
                                    <span class="badge badge-semester badge-ganjil">Ganjil</span>
                                <?php else: ?>
                                    <span class="badge badge-semester badge-genap">Genap</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($is_active): ?>
                                    <div class="badge-pastel-green shadow-sm">
                                        <i class="fas fa-check"></i> AKTIF
                                    </div>
                                <?php else: ?>
                                    <a href="?page=tahun_ajaran&aktifkan_id=<?php echo $row['id']; ?>" class="btn btn-outline-pastel" onclick="return confirm('Aktifkan tahun ajaran ini?');">
                                        Aktifkan
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if(!$is_active): ?>
                                    <a href="?page=tahun_ajaran&hapus_id=<?php echo $row['id']; ?>" class="btn btn-link text-muted p-0" onclick="return confirm('Hapus data ini?');">
                                        <i class="fas fa-trash-alt fa-sm"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small opacity-50"><i class="fas fa-lock fa-sm"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5 small">Belum ada data tahun ajaran.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered"> 
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header modal-header-pastel py-3">
                <h6 class="modal-title fw-bold text-dark"><i class="fas fa-calendar-plus me-2 text-primary"></i>Tambah Data</h6>
                <button type="button" class="btn-close small" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tahun Ajaran</label>
                        <select name="tahun" class="form-select border-0 bg-light rounded-3 shadow-sm" style="font-size: 14px;">
                            <?php 
                            $tahun_skrg = date('Y');
                            for($i=$tahun_skrg-1; $i<=$tahun_skrg+2; $i++){
                                $next = $i+1;
                                echo "<option value='$i/$next'>$i/$next</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Semester</label>
                        <select name="semester" class="form-select border-0 bg-light rounded-3 shadow-sm" style="font-size: 14px;">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <button type="submit" name="simpan_ta" class="btn btn-pastel-primary w-100 rounded-3 py-2 fw-bold mt-2">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>