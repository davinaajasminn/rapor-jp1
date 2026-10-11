<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';

// --- FIXED PATH & CLASS CALL ---
$mesin_excel = __DIR__ . '/SimpleXLSX.php';
if (file_exists($mesin_excel)) {
    include $mesin_excel; 
} else {
    echo "<div class='alert alert-danger'>ERROR: File <b>SimpleXLSX.php</b> tidak ada di folder admin!</div>";
}

$db = new Database();
$conn = $db->getConnection();

// --- 🩹 FIX: TABEL PENGECUALIAN SISWA ---
// Mencatat siswa yg SENGAJA dikeluarkan (dihapus) dari suatu Tahun Pelajaran tertentu,
// supaya tombol "Kunci Siswa" tidak menghidupkan/memasukkan mereka lagi secara otomatis.
// Dibuat otomatis kalau belum ada, jadi tidak perlu migrasi manual di database.
$conn->query("CREATE TABLE IF NOT EXISTS pengecualian_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_siswa INT NOT NULL,
    id_tahun_ajaran INT NOT NULL,
    UNIQUE KEY uniq_siswa_ta (id_siswa, id_tahun_ajaran)
)");

// --- 🔒 LOCK DATA AKTIF BERDASARKAN MASTER IDENTITAS SEKOLAH ---
$qTAAktif = $conn->query("SELECT * FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$ta_aktif = $qTAAktif ? $qTAAktif->fetch_assoc() : null;
$tahun_aktif_default = $ta_aktif['tahun'] ?? '';

// Ambil filter tahun lewat GET (dropdown), kalau kosong otomatis pakai tahun aktif dari Identitas
$filter_tahun = $_GET['filter_tahun'] ?? $tahun_aktif_default;
$tahun_safe = mysqli_real_escape_string($conn, $filter_tahun);

$text_tahun_aktif = $ta_aktif['tahun'] ?? '';
$text_semester_aktif = $ta_aktif['semester'] ?? '';

// Kumpulkan semua ID unik (Ganjil & Genap) yang masuk dalam rumpun tahun terpilih
$qAmbilIDTA = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun = '$tahun_safe'");
$array_id_ta = [];
while($row_id = $qAmbilIDTA->fetch_assoc()){
    $array_id_ta[] = $row_id['id'];
}
$string_id_ta = count($array_id_ta) > 0 ? implode("','", $array_id_ta) : '0';


// --- 1. PROSES HAPUS ---
if(isset($_GET['hapus_siswa'])){
    $id_siswa_del = mysqli_real_escape_string($conn, $_GET['hapus_siswa']);
    $conn->query("DELETE FROM riwayat_kelas WHERE id_siswa = '$id_siswa_del' AND id_tahun_ajaran IN ('$string_id_ta')");
    // 🩹 FIX: catat siswa ini sebagai "sengaja dikeluarkan" dari TP $filter_tahun,
    // supaya tombol "Kunci Siswa" tidak menghidupkannya lagi di tahun yang sama.
    foreach($array_id_ta as $id_ta_isi){
        $conn->query("INSERT IGNORE INTO pengecualian_siswa (id_siswa, id_tahun_ajaran) VALUES ('$id_siswa_del', '$id_ta_isi')");
    }
    echo "<script>alert('✅ Siswa berhasil dikeluarkan dari Tahun Pelajaran $filter_tahun. Data master siswa tetap aman!'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
    exit;
}

// 🩹 FITUR BARU: HAPUS PERMANEN
// Beda sama "hapus_siswa" di atas (yang cuma ngeluarin dari 1 tahun ajaran, data master
// tetap aman) — ini BENERAN menghapus baris siswa & semua data terkait dari database.
// Dipakai buat bersihin data test/salah input tanpa perlu buka phpMyAdmin manual.
// TIDAK BISA DIBATALKAN.
if(isset($_GET['hapus_permanen_siswa'])){
    $id_siswa_hp = mysqli_real_escape_string($conn, $_GET['hapus_permanen_siswa']);

    $conn->query("DELETE FROM riwayat_kelas WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM pengecualian_siswa WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM nilai WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM absensi WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM data_pkl WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM nilai_ekskul WHERE id_siswa = '$id_siswa_hp'");
    $conn->query("DELETE FROM siswa WHERE id = '$id_siswa_hp'");

    echo "<script>alert('🗑️ Siswa & seluruh datanya (nilai, absensi, riwayat kelas, dll) berhasil DIHAPUS PERMANEN dari database.'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
    exit;
}

// --- 2. IMPORT EXCEL ---
if(isset($_POST['import_excel'])){
    $id_kelas_target = $_POST['id_kelas_import']; 

    // 🩹 FIX: validasi "Kelas Tujuan" wajib dipilih juga di sisi PHP, bukan cuma andalin
    // atribut required di HTML (yang bisa kebobol). Tanpa ini, siswa bisa keinsert dengan
    // id_kelas kosong/0 -> siswa jadi "ngambang", gak nyambung ke kelas manapun, jadi gak
    // muncul di manapun walau datanya tetap ada di tabel siswa.
    if(empty($id_kelas_target)){
        echo "<script>alert('Import dibatalkan: Kelas Tujuan wajib dipilih dulu sebelum upload file.'); window.location='?page=data_siswa';</script>";
        exit;
    }

    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] === 0) {
        
        if ( $xlsx = \Shuchkin\SimpleXLSX::parse( $_FILES['file_excel']['tmp_name'] ) ) {
            $sukses = 0; 
            $gagal = 0; 
            $pesan_error = "";
            
            foreach ( $xlsx->rows() as $index => $data ) {
                if($index === 0) continue; 
                
                $nis  = trim($data[0] ?? '');
                $nisn = trim($data[1] ?? '');
                $nama = trim($data[2] ?? '');
                $jk   = trim($data[3] ?? '');
                $tgl_raw = trim($data[4] ?? '');

                // Baris kosong total (misal sisa baris kosong di template) -> lewati diam-diam, tidak dihitung gagal
                if(empty($nis) && empty($nisn) && empty($nama) && empty($jk) && empty($tgl_raw)) continue;

                // 🩹 FIX: Sebelumnya cuma NISN & Nama yg wajib, NIS & Tanggal Lahir boleh kosong
                // dan otomatis jadi 01/01/1970 (dari strtotime('') yg return false).
                // Sekarang NIS, NISN, Nama, dan Tanggal Lahir SEMUA wajib diisi.
                $field_kosong = [];
                if(empty($nis))  $field_kosong[] = 'NIS';
                if(empty($nisn)) $field_kosong[] = 'NISN';
                if(empty($nama)) $field_kosong[] = 'NAMA_SISWA';
                if(empty($tgl_raw)) $field_kosong[] = 'TGL_LAHIR';

                if(!empty($field_kosong)){
                    $gagal++;
                    $pesan_error .= "[Baris " . ($index+1) . ": kolom " . implode(', ', $field_kosong) . " kosong] ";
                    continue;
                }

                $tgl_fix = "";
                if(strpos($tgl_raw, '/') !== false){
                    $p = explode('/', $tgl_raw);
                    if(count($p)==3) $tgl_fix = "$p[2]-$p[1]-$p[0]";
                } else {
                    $ts = strtotime($tgl_raw);
                    if($ts !== false) $tgl_fix = date('Y-m-d', $ts);
                }

                // Validasi hasil parsing tanggal, tolak kalau format tidak valid
                if(empty($tgl_fix) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_fix)){
                    $gagal++;
                    $pesan_error .= "[Baris " . ($index+1) . ": format TGL_LAHIR '$tgl_raw' tidak valid, gunakan DD/MM/YYYY] ";
                    continue;
                }

                $nis_safe = mysqli_real_escape_string($conn, $nis);
                $nisn_safe = mysqli_real_escape_string($conn, $nisn);

                $cek = $conn->query("SELECT id FROM siswa WHERE nis='$nis_safe' OR nisn='$nisn_safe' LIMIT 1");
                if($cek && $cek->num_rows > 0){
                    $gagal++;
                    $pesan_error .= "[NIS/NISN $nis / $nisn sudah ada] ";
                } else {
                    $sql = "INSERT INTO siswa (nis, nisn, nama_siswa, id_kelas, tgl_lahir, password) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $pw_excel_hash = password_hash($tgl_fix, PASSWORD_DEFAULT);
                    $stmt->bind_param("sssiss", $nis, $nisn, $nama, $id_kelas_target, $tgl_fix, $pw_excel_hash);
                    
                    if($stmt->execute()){
                        $new_siswa_id = $conn->insert_id;
                        foreach($array_id_ta as $id_ta_isi) {
                            $conn->query("INSERT INTO riwayat_kelas (id_siswa, id_kelas, id_tahun_ajaran) VALUES ('$new_siswa_id', '$id_kelas_target', '$id_ta_isi')");
                        }
                        $sukses++;
                    } else {
                        $gagal++;
                        $pesan_error .= "[DB Error: $conn->error] ";
                    }
                }
            }

            $msg = "Hasil Import - Berhasil: $sukses, Gagal: $gagal. $pesan_error";
            echo "<script>alert('$msg'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
        } else {
            echo "<script>alert('Gagal membaca file! Error: " . \Shuchkin\SimpleXLSX::parseError() . "');</script>";
        }
    }
}

// --- 3. TAMBAH MANUAL ---
if(isset($_POST['simpan_siswa'])){
    $nis = $_POST['nis']; 
    $nisn = $_POST['nisn']; 
    $nama = $_POST['nama_siswa']; 
    $tgl = $_POST['tgl_lahir']; 
    $kelas = $_POST['id_kelas'];

    $nis_safe = mysqli_real_escape_string($conn, $nis);
    $nisn_safe = mysqli_real_escape_string($conn, $nisn);
    $nama_safe = mysqli_real_escape_string($conn, $nama);
    $tgl_safe = mysqli_real_escape_string($conn, $tgl);
    $kelas_safe = mysqli_real_escape_string($conn, $kelas);

    $cek_duplikat = $conn->query("SELECT id FROM siswa WHERE nis='$nis_safe' OR nisn='$nisn_safe' LIMIT 1");

    if($cek_duplikat && $cek_duplikat->num_rows > 0){
        echo "<script>alert('Gagal! NIS atau NISN sudah terdaftar.'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
    } else {
        if($conn->query("INSERT INTO siswa (nis, nisn, nama_siswa, id_kelas, tgl_lahir, password) VALUES ('$nis_safe', '$nisn_safe', '$nama_safe', '$kelas_safe', '$tgl_safe', '" . password_hash($tgl_safe, PASSWORD_DEFAULT) . "')")){
            $new_siswa_id = $conn->insert_id;
            foreach($array_id_ta as $id_ta_isi) {
                $conn->query("INSERT INTO riwayat_kelas (id_siswa, id_kelas, id_tahun_ajaran) VALUES ('$new_siswa_id', '$kelas_safe', '$id_ta_isi')");
            }
            echo "<script>alert('Siswa ditambah!'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
        } else {
            echo "<script>alert('Gagal menambah siswa: " . addslashes($conn->error) . "'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
        }
    }
}

// --- 4. UPDATE DATA SISWA ---
if(isset($_POST['update_siswa'])){
    $id = $_POST['id_siswa']; 
    $nis = $_POST['nis']; 
    $nisn = $_POST['edit_nisn']; 
    $nama = $_POST['nama_siswa']; 
    $tgl = $_POST['tgl_lahir']; 
    $kelas = $_POST['id_kelas'];

    $id_safe = mysqli_real_escape_string($conn, $id);
    $nis_safe = mysqli_real_escape_string($conn, $nis);
    $nisn_safe = mysqli_real_escape_string($conn, $nisn);
    $nama_safe = mysqli_real_escape_string($conn, $nama);
    $tgl_safe = mysqli_real_escape_string($conn, $tgl);

    $cek_duplikat = $conn->query("SELECT id FROM siswa WHERE (nis='$nis_safe' OR nisn='$nisn_safe') AND id != '$id_safe' LIMIT 1");

    if($cek_duplikat && $cek_duplikat->num_rows > 0){
        echo "<script>alert('Gagal update! NIS atau NISN sudah dipakai siswa lain.'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
    } else {
        if($kelas === 'alumni'){
            // FIX: kalau siswa ini alumni, JANGAN ikut nge-plot dia ke kelas asli manapun.
            // Simpan NULL di kedua tabel, konsisten sama proses "Luluskan sebagai Alumni".
            if($conn->query("UPDATE siswa SET nis='$nis_safe', nisn='$nisn_safe', nama_siswa='$nama_safe', id_kelas=NULL, tgl_lahir='$tgl_safe' WHERE id='$id_safe'")){
                $conn->query("UPDATE riwayat_kelas SET id_kelas=NULL WHERE id_siswa='$id_safe' AND id_tahun_ajaran IN ('$string_id_ta')");
                echo "<script>alert('Berhasil diupdate!'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
            } else {
                echo "<script>alert('Gagal update siswa: " . addslashes($conn->error) . "'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
            }
        } else {
            $kelas_safe = mysqli_real_escape_string($conn, $kelas);
            if($conn->query("UPDATE siswa SET nis='$nis_safe', nisn='$nisn_safe', nama_siswa='$nama_safe', id_kelas='$kelas_safe', tgl_lahir='$tgl_safe' WHERE id='$id_safe'")){
                $conn->query("UPDATE riwayat_kelas SET id_kelas='$kelas_safe' WHERE id_siswa='$id_safe' AND id_tahun_ajaran IN ('$string_id_ta')");
                echo "<script>alert('Berhasil diupdate!'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
            } else {
                echo "<script>alert('Gagal update siswa: " . addslashes($conn->error) . "'); window.location='?page=data_siswa&filter_tahun=$filter_tahun';</script>";
            }
        }
    }
}

// --- 5. 🔥 FIXED LOGIC: PROSES MASSAL KENAIKAN KELAS ANTI-DUPLIKAT LINTAS KELAS ---
if(isset($_POST['proses_naik_kelas_final'])){
    $id_kelas_asal = $_POST['id_kelas_asal'];
    $id_kelas_tujuan = $_POST['id_kelas_tujuan'];
    $tahun_target = $_POST['tahun_tujuan_target']; 

    if(empty($id_kelas_asal) || empty($id_kelas_tujuan) || empty($tahun_target)){
        echo "<script>alert('Gagal! Form tidak lengkap.'); window.location='?page=data_siswa';</script>";
        exit;
    }

    $tahun_target_safe = mysqli_real_escape_string($conn, $tahun_target);

    // Ambil Rumpun ID semester untuk tahun pelajaran target baru
    $qTargetTA = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun = '$tahun_target_safe'");
    $target_ids_ta = [];
    while($row_t = $qTargetTA->fetch_assoc()){
        $target_ids_ta[] = $row_t['id'];
    }
    $string_target_ids = count($target_ids_ta) > 0 ? implode("','", $target_ids_ta) : '0';

    if(count($target_ids_ta) == 0){
        echo "<script>alert('Gagal! Tahun Pelajaran target belum terdaftar.'); window.location='?page=data_siswa';</script>";
        exit;
    }

    // Ambil data siswa dari rombel asal jaman tahun ini
    $qSiswa = $conn->query("SELECT id_siswa FROM riwayat_kelas WHERE id_kelas = '$id_kelas_asal' AND id_tahun_ajaran IN ('$string_id_ta') AND id_siswa != 0 GROUP BY id_siswa");
    
    $sukses_baru = 0;
    $sudah_ada_di_sini = 0;
    $ditolak_lintas_kelas = 0;

    if($qSiswa && $qSiswa->num_rows > 0) {
        while($s = $qSiswa->fetch_assoc()) {
            $id_siswa = $s['id_siswa'];

            if($id_kelas_tujuan == 'alumni') {
                // FIX: id_kelas di kedua tabel (siswa & riwayat_kelas) bertipe INT.
                // Alumni ditandai dengan id_kelas = NULL di kedua tabel, BUKAN string 'alumni'
                // (string 'alumni' di kolom INT bakal ke-truncate diam-diam jadi 0 oleh MySQL/MariaDB).
                $conn->query("UPDATE siswa SET id_kelas = NULL WHERE id = '$id_siswa'");
                foreach($target_ids_ta as $id_ta_baru) {
                    $cek_ex = $conn->query("SELECT id FROM riwayat_kelas WHERE id_siswa='$id_siswa' AND id_tahun_ajaran='$id_ta_baru' LIMIT 1");
                    if($cek_ex->num_rows == 0) {
                        $conn->query("INSERT INTO riwayat_kelas (id_siswa, id_kelas, id_tahun_ajaran) VALUES ('$id_siswa', NULL, '$id_ta_baru')");
                    } else {
                        // Kalau baris riwayat udah ada duluan (misal dari 'Kunci Data Siswa Aktif'),
                        // pastikan tetap ke-update jadi NULL, bukan nyangkut di kelas lama.
                        $conn->query("UPDATE riwayat_kelas SET id_kelas = NULL WHERE id_siswa='$id_siswa' AND id_tahun_ajaran='$id_ta_baru'");
                    }
                }
                $sukses_baru++;
            } else {
                $id_kelas_tujuan_safe = mysqli_real_escape_string($conn, $id_kelas_tujuan);
                
                // 👮‍♂️ SATPAM 1: Cek apakah siswa ini sudah punya riwayat aktif di kelas LAIN pada tahun ajaran target tersebut
                $cekLintasKelas = $conn->query("SELECT id_kelas FROM riwayat_kelas WHERE id_siswa = '$id_siswa' AND id_tahun_ajaran IN ('$string_target_ids') AND id_kelas != '$id_kelas_tujuan_safe' AND id_kelas != 0 LIMIT 1");
                if($cekLintasKelas && $cekLintasKelas->num_rows > 0) {
                    $ditolak_lintas_kelas++;
                    continue; // 🚫 LEWATI SANKSI: Tolak ploting siswa ini karena dia sudah nangkring di kelas lain di tahun target!
                }

                // 👮‍♂️ SATPAM 2: Cek apakah siswa ini sebenarnya sudah terdaftar di kelas tujuan ini pada tahun ajaran target
                $cekSama = $conn->query("SELECT id FROM riwayat_kelas WHERE id_siswa = '$id_siswa' AND id_tahun_ajaran IN ('$string_target_ids') AND id_kelas = '$id_kelas_tujuan_safe' LIMIT 1");
                if($cekSama && $cekSama->num_rows > 0) {
                    $sudah_ada_di_sini++;
                    continue; // 🚫 LEWATI: Jangan hitung sebagai data baru karena dia memang sudah dimasukkan sebelumnya
                }

                // Jika lolos semua satpam, barulah eksekusi ploting data barunya dijalankan
                $conn->query("UPDATE siswa SET id_kelas = '$id_kelas_tujuan_safe' WHERE id = '$id_siswa'");
                
                foreach($target_ids_ta as $id_ta_baru) {
                    $conn->query("INSERT INTO riwayat_kelas (id_siswa, id_kelas, id_tahun_ajaran) VALUES ('$id_siswa', '$id_kelas_tujuan_safe', '$id_ta_baru')");
                }
                $sukses_baru++;
            }
        }
    }

    // Bangun teks pesan notifikasi yang cerdas sesuai kondisi real lapangan
    $notif_msg = "PROSES EVALUASI MASSAL SELESAI:\\n";
    $notif_msg .= "• Berhasil dimasukkan: $sukses_baru siswa baru.\\n";
    if($sudah_ada_di_sini > 0) $notif_msg .= "• Dilewati (Sudah ada di kelas ini): $sudah_ada_di_sini siswa.\\n";
    if($ditolak_lintas_kelas > 0) $notif_msg .= "• DIBLOKIR PENCEGAH DUPLIKAT: $ditolak_lintas_kelas siswa (Karena terdeteksi sudah naik di kelas lain pada tahun target!).";

    echo "<script>alert('$notif_msg'); window.location='?page=data_siswa&filter_tahun=$tahun_target';</script>";
    exit;
}

$list_filter_tahun = [];
$qGroupTA = $conn->query("SELECT tahun, MAX(status) as status_aktif FROM tahun_ajaran GROUP BY tahun ORDER BY tahun DESC");
while($gta = $qGroupTA->fetch_assoc()){ $list_filter_tahun[] = $gta; }

$kelas_array = [];
$qk = $conn->query("SELECT id, nama_kelas, kode_jurusan FROM kelas ORDER BY nama_kelas");
while($k = $qk->fetch_assoc()){ $kelas_array[] = $k; }

$jurusans = [];
$qJur = $conn->query("SELECT kode_jurusan FROM jurusan ORDER BY kode_jurusan ASC");
while($j = $qJur->fetch_assoc()){ $jurusans[] = $j['kode_jurusan']; }

$filter_jurusan = $_GET['jurusan'] ?? '';
$search_siswa = $_GET['search'] ?? '';
?>

<style>
    .toolbar-siswa { background: #ffffff; border-radius: 18px; box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08); padding: 16px; margin-bottom: 20px; }
    .search-siswa-box { position: relative; }
    .search-siswa-box i { position: absolute; left: 18px; top: 14px; color: #9aabbc; z-index: 2; }
    .input-search-siswa { height: 46px; border-radius: 50px; border: 1px solid #dbe5ef; background-color: #f8fbff; color: #0d385e; font-size: 14px; font-weight: 600; padding-left: 45px; box-shadow: none; }
    .input-search-siswa:focus { border-color: #9fc1e7; box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14); background-color: #ffffff; }
    .btn-search-siswa { height: 46px; background: #6FA7DF !important; color: #ffffff !important; border: none !important; border-radius: 50px !important; font-weight: 700 !important; padding: 0 24px; box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important; }
    .filter-jurusan-box { height: 46px; border-radius: 50px; border: 1px solid #dbe5ef !important; background: #ffffff; box-shadow: 0 4px 12px rgba(80, 115, 155, 0.08); }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Data Siswa</h4>
            <small class="text-secondary fw-bold"><i class="fas fa-calendar-alt me-1"></i> TAMPILAN DATA TAHUN: <?= htmlspecialchars($filter_tahun) ?></small>
        </div>

        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-soft-green px-4" style="height: 42px;" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fas fa-file-excel me-2"></i> Import Excel
            </button>
            
            <button type="button" class="btn fw-bold px-4 text-white rounded-pill" style="height: 42px; background-color: #f43f5e; border: none; box-shadow: 0 4px 12px rgba(244, 63, 94, 0.2);" onclick="document.getElementById('customModalKenaikan').style.display='flex'">
                <i class="fas fa-graduation-cap me-2"></i> Kenaikan Kelas
            </button>

            <button class="btn btn-add-data" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-user-plus me-2"></i> Tambah Siswa
            </button>
        </div>
    </div>

    <div class="toolbar-siswa">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <form method="GET" action="" class="m-0">
                    <input type="hidden" name="page" value="data_siswa">
                    <input type="hidden" name="jurusan" value="<?= htmlspecialchars($filter_jurusan) ?>">
                    <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                    <div class="row g-2">
                        <div class="col-md-9">
                            <div class="search-siswa-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="form-control input-search-siswa" placeholder="Cari nama siswa atau NISN..." value="<?= htmlspecialchars($search_siswa) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-search-siswa w-100">Cari</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-3">
                <form method="GET" class="m-0">
                    <input type="hidden" name="page" value="data_siswa">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_siswa) ?>">
                    <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                    <div class="d-flex align-items-center filter-jurusan-box px-2 py-1 w-100">
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-1" style="width: 32px; height: 32px;">
                            <i class="fas fa-filter text-primary" style="font-size: 13px;"></i>
                        </div>
                        <select name="jurusan" class="form-select border-0 shadow-none fw-bold text-secondary" onchange="this.form.submit()" style="cursor: pointer; font-size: 14px;">
                            <option value="">Semua Jurusan</option>
                            <option value="alumni" <?= ($filter_jurusan == 'alumni') ? 'selected' : '' ?>>🎓 Data Alumni (Lulus)</option>
                            <?php foreach($jurusans as $jur): ?>
                                <option value="<?= $jur ?>" <?= ($filter_jurusan == $jur) ? 'selected' : '' ?>>Jurusan <?= $jur ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="col-md-4">
                <form method="GET" class="m-0">
                    <input type="hidden" name="page" value="data_siswa">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_siswa) ?>">
                    <input type="hidden" name="jurusan" value="<?= htmlspecialchars($filter_jurusan) ?>">
                    
                    <div class="d-flex align-items-center filter-jurusan-box px-2 py-1 w-100">
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-1" style="width: 32px; height: 32px;">
                            <i class="fas fa-calendar-alt text-success" style="font-size: 13px;"></i>
                        </div>
                        <select name="filter_tahun" class="form-select border-0 shadow-none fw-bold text-dark" onchange="this.form.submit()" style="cursor: pointer; font-size: 14px; padding-top: 6px; padding-bottom: 6px;">
                            <?php foreach($list_filter_tahun as $ta_item): 
                                $label_aktif = (strtolower($ta_item['status_aktif']) == 'aktif' || $ta_item['tahun'] == $tahun_aktif_default) ? ' - [AKTIF]' : '';
                            ?>
                                <option value="<?= $ta_item['tahun'] ?>" <?= ($filter_tahun == $ta_item['tahun']) ? 'selected' : '' ?>>
                                    TP <?= $ta_item['tahun'] ?><?= $label_aktif ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="text-secondary small text-uppercase">
                    <th class="py-3 px-4">No</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Status / Kelas</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $where_riwayat = " WHERE r.id_tahun_ajaran IN ('$string_id_ta')";

                if(!empty($filter_jurusan)){
                    $jur_escaped = $conn->real_escape_string($filter_jurusan);
                    if($jur_escaped == 'alumni') {
                        $where_riwayat .= " AND r.id_kelas IS NULL";
                    } else {
                        $where_riwayat .= " AND k.kode_jurusan = '$jur_escaped' AND r.id_kelas IS NOT NULL";
                    }
                } else {
                    $where_riwayat .= " AND r.id_kelas IS NOT NULL";
                }

                if(!empty($search_siswa)){
                    $search_escaped = $conn->real_escape_string($search_siswa);
                    $where_riwayat .= " AND (s.nama_siswa LIKE '%$search_escaped%' OR s.nisn LIKE '%$search_escaped%' OR s.nis LIKE '%$search_escaped%')";
                }

                $res = $conn->query("SELECT s.*, k.nama_kelas, k.kode_jurusan, r.id_kelas as kelas_riwayat 
                                     FROM riwayat_kelas r
                                     INNER JOIN siswa s ON r.id_siswa = s.id
                                     LEFT JOIN kelas k ON r.id_kelas = k.id
                                     $where_riwayat
                                     GROUP BY s.id
                                     ORDER BY k.nama_kelas, s.nama_siswa");
                
                if($res && $res->num_rows > 0):
                    while($row = $res->fetch_assoc()):
                        $badge_kelas = empty($row['kelas_riwayat']) ? '<span class="badge bg-secondary text-white">🎓 ALUMNI (LULUS)</span>' : '<span class="badge bg-light text-dark border">'.$row['nama_kelas'].'</span>';
                ?>
                <tr>
                    <td class="px-4"><?= $no++ ?></td>
                    <td><?= $row['nisn'] ?></td>
                    <td class="fw-bold"><?= ucwords(strtolower($row['nama_siswa'])) ?></td>
                    <td><?= $badge_kelas ?></td>
                    <td class="text-center">
                        <button class="btn-action btn-action-edit btn-edit" 
                            data-id="<?= $row['id'] ?>" data-nis="<?= $row['nis'] ?>" data-nisn="<?= $row['nisn'] ?>"
                            data-nama="<?= $row['nama_siswa'] ?>" data-tgl="<?= $row['tgl_lahir'] ?>" 
                            data-kelas="<?= is_null($row['id_kelas']) ? 'alumni' : $row['id_kelas'] ?>" data-jurusan="<?= $row['kode_jurusan'] ?>"
                            data-bs-toggle="modal" data-bs-target="#modalEdit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?page=data_siswa&hapus_siswa=<?= $row['id'] ?>&filter_tahun=<?= $filter_tahun ?>" class="btn-action btn-action-delete" title="Keluarkan dari Tahun Pelajaran ini (data master tetap aman)" onclick="return confirm('Keluarkan siswa ini dari Tahun Pelajaran <?= $filter_tahun ?>? Data riwayat tahun pelajaran lain tidak akan hilang.');">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                        <a href="?page=data_siswa&hapus_permanen_siswa=<?= $row['id'] ?>&filter_tahun=<?= $filter_tahun ?>" 
                           class="btn-action" style="background:#842029;color:#fff;" 
                           title="Hapus Permanen (tidak bisa dibatalkan)"
                           onclick="return confirm('⚠️ HAPUS PERMANEN siswa \'<?= addslashes($row['nama_siswa']) ?>\'?\n\nSemua data (nilai, absensi, riwayat kelas, dll) akan terhapus TOTAL dari database dan TIDAK BISA DIBATALKAN.\n\nLanjutkan?');">
                            <i class="fas fa-eraser"></i>
                        </a>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="5" class="py-5 text-center text-muted">
                        <i class="fas fa-users-slash fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold mb-0">Tidak Ada Data Siswa Ditemukan.</h6>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 🩹 FITUR BARU: cari siswa "ngambang" (ada di tabel siswa, tapi TIDAK nyambung ke
// riwayat_kelas manapun -- misal sisa data test, atau pernah di-"Keluarkan" dari semua
// tahun ajaran). List utama di atas gak akan pernah nampilin siswa kayak gini karena
// pakai INNER JOIN ke riwayat_kelas. Panel ini cuma muncul kalau lagi nyari sesuatu DAN
// hasil di list utama kosong, biar gampang nemuin & hapus data ngambang tanpa phpMyAdmin.
if(!empty($search_siswa) && (!$res || $res->num_rows == 0)){
    $search_orphan_safe = mysqli_real_escape_string($conn, $search_siswa);
    $qOrphan = $conn->query("SELECT s.* FROM siswa s
                             WHERE (s.nama_siswa LIKE '%$search_orphan_safe%' OR s.nisn LIKE '%$search_orphan_safe%' OR s.nis LIKE '%$search_orphan_safe%')
                             AND NOT EXISTS (SELECT 1 FROM riwayat_kelas rk WHERE rk.id_siswa = s.id)
                             ORDER BY s.nama_siswa ASC");
    if($qOrphan && $qOrphan->num_rows > 0){
        ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 mt-3" style="background:#fff7e6; border:1px solid #ffe2ad !important;">
            <h6 class="fw-bold mb-2" style="color:#7A5200;">
                <i class="fas fa-exclamation-triangle me-1"></i> Ditemukan siswa yang tidak tersambung ke kelas manapun
            </h6>
            <p class="small text-muted mb-3">
                Siswa berikut ada di database tapi tidak punya riwayat kelas di tahun ajaran manapun (biasanya sisa data test, salah input, atau sudah "dikeluarkan" dari semua tahun ajaran). Mereka tidak akan muncul di list utama di atas.
            </p>
            <table class="table table-sm mb-0">
                <thead class="small text-muted text-uppercase">
                    <tr><th>NIS</th><th>NISN</th><th>Nama</th><th class="text-center">Aksi</th></tr>
                </thead>
                <tbody>
                    <?php while($o = $qOrphan->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($o['nis']) ?></td>
                        <td><?= htmlspecialchars($o['nisn']) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($o['nama_siswa']) ?></td>
                        <td class="text-center">
                            <a href="?page=data_siswa&hapus_permanen_siswa=<?= $o['id'] ?>&filter_tahun=<?= $filter_tahun ?>" 
                               class="btn btn-sm" style="background:#842029;color:#fff;border-radius:50px;"
                               onclick="return confirm('⚠️ HAPUS PERMANEN siswa \'<?= addslashes($o['nama_siswa']) ?>\' (NIS <?= addslashes($o['nis']) ?>)?\n\nTIDAK BISA DIBATALKAN.\n\nLanjutkan?');">
                                <i class="fas fa-eraser me-1"></i> Hapus Permanen
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
?>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 p-4">
            <form method="POST" enctype="multipart/form-data">
                <h6 class="fw-bold mb-3 text-success">Import Excel (.xlsx)</h6>
                <a href="download_template.php" class="btn btn-sm btn-outline-success rounded-pill mb-3">
                    <i class="fas fa-download me-1"></i> Download Template
                </a>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">Jurusan</label>
                        <select id="import_jurusan" class="form-select" onchange="filterKelasImport()">
                            <option value="">Semua Jurusan</option>
                            <?php foreach($jurusans as $jur) echo "<option value='$jur'>$jur</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Kelas Tujuan</label>
                        <select name="id_kelas_import" id="import_kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">File Excel</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx" required>
                </div>
                <button type="submit" name="import_excel" class="btn btn-soft-green w-100 fw-bold rounded-pill">MULAI IMPORT</button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 p-4">
            <form method="POST">
                <h6 class="fw-bold mb-3 text-primary">Tambah Siswa Baru (Untuk TP <?= htmlspecialchars($filter_tahun) ?>)</h6>
                <div class="mb-3"><label class="small fw-bold">NIS</label><input type="text" name="nis" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">NISN</label><input type="text" name="nisn" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">Nama Lengkap</label><input type="text" name="nama_siswa" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">Tanggal Lahir</label><input type="date" name="tgl_lahir" class="form-control" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">Pilih Jurusan</label>
                        <select id="tambah_jurusan" class="form-select" onchange="filterKelasTambah()">
                            <option value="">Semua Jurusan</option>
                            <?php foreach($jurusans as $jur) echo "<option value='$jur'>$jur</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Pilih Kelas</label>
                        <select name="id_kelas" id="tambah_kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="simpan_siswa" class="btn btn-save-data w-100 fw-bold rounded-pill">SIMPAN SISWA</button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 p-4">
            <form method="POST">
                <input type="hidden" name="id_siswa" id="edit_id">
                <h6 class="fw-bold mb-3 text-warning">Edit Data Siswa</h6>
                <div class="mb-3"><label class="small fw-bold">NIS</label><input type="text" name="nis" id="edit_nis" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">NISN</label><input type="text" name="edit_nisn" id="edit_nisn" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">Nama Lengkap</label><input type="text" name="nama_siswa" id="edit_nama" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">Tanggal Lahir</label><input type="date" name="tgl_lahir" id="edit_tgl" class="form-control" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">Jurusan</label>
                        <select id="edit_jurusan" class="form-select" onchange="filterKelasEdit($('#edit_jurusan').val(), null)">
                            <option value="">Semua Jurusan</option>
                            <?php foreach($jurusans as $jur) echo "<option value='$jur'>$jur</option>"; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Kelas</label>
                        <select name="id_kelas" id="edit_kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_siswa" class="btn btn-update-data w-100 fw-bold rounded-pill">UPDATE DATA</button>
            </form>
        </div>
    </div>
</div>

<div id="customModalKenaikan" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; padding: 15px;">
    <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); padding: 25px; position: relative;">
        <button type="button" style="position: absolute; right: 20px; top: 20px; border: none; background: none; font-size: 20px; cursor: pointer; color: #9aabbc;" onclick="document.getElementById('customModalKenaikan').style.display='none'">×</button>
        
        <form method="POST" action="">
            <h5 class="fw-bold mb-3 text-danger"><i class="fas fa-graduation-cap me-2"></i> Form Evaluasi Kenaikan Kelas</h5>
            <div class="mb-3">
                <label class="small fw-bold text-secondary mb-1">PILIH TAHUN PELAJARAN TARGET TUJUAN:</label>
                <select name="tahun_tujuan_target" class="form-select fw-bold text-primary" required>
                    <option value="">-- Pilih Tahun Target --</option>
                    <?php 
                    $qGroupTahun = $conn->query("SELECT tahun FROM tahun_ajaran GROUP BY tahun ORDER BY tahun DESC");
                    while($gt = $qGroupTahun->fetch_assoc()):
                    ?>
                        <option value="<?= $gt['tahun'] ?>">Tahun Pelajaran <?= $gt['tahun'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="small fw-bold text-secondary mb-1">1. PILIH KELAS ASAL SEKARANG:</label>
                <select name="id_kelas_asal" class="form-select fw-bold text-dark" required>
                    <option value="">-- Pilih Kelas Asal --</option>
                    <?php foreach($kelas_array as $kls): ?>
                        <option value="<?= $kls['id'] ?>">Kelas <?= $kls['nama_kelas'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="small fw-bold text-secondary mb-1">2. PLOT/PINDAH MASSAL KE KELAS BARU:</label>
                <select name="id_kelas_tujuan" class="form-select fw-bold text-success" required>
                    <option value="">-- Pilih Aksi Tujuan --</option>
                    <option value="alumni" style="color: #9d174d; font-weight: bold;">🎓 LULUSKAN SEBAGAI ALUMNI</option>
                    <?php foreach($kelas_array as $kls): ?>
                        <option value="<?= $kls['id'] ?>">Pindahkan Ke Kelas: <?= $kls['nama_kelas'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary w-50 fw-bold rounded-pill" onclick="document.getElementById('customModalKenaikan').style.display='none'">BATAL</button>
                <button type="submit" name="proses_naik_kelas_final" class="btn btn-danger w-50 fw-bold rounded-pill shadow-sm">PROSES MASSAL</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const kelasData = <?= json_encode($kelas_array) ?>;

function filterKelasTambah() {
    let jur = $('#tambah_jurusan').val();
    let kelasSelect = $('#tambah_kelas');
    kelasSelect.empty().append('<option value="">-- Pilih Kelas --</option>');
    kelasData.forEach(k => {
        if(jur === "" || k.kode_jurusan === jur) {
            kelasSelect.append(`<option value="${k.id}">${k.nama_kelas}</option>`);
        }
    });
}

function filterKelasImport() {
    let jur = $('#import_jurusan').val();
    let kelasSelect = $('#import_kelas');
    kelasSelect.empty().append('<option value=\"\">-- Pilih Kelas --</option>');
    kelasData.forEach(k => {
        if(jur === "" || k.kode_jurusan === jur) {
            kelasSelect.append(`<option value="${k.id}">${k.nama_kelas}</option>`);
        }
    });
}

function filterKelasEdit(selectedJur, selectedKelas) {
    let kelasSelect = $('#edit_kelas');
    kelasSelect.empty().append('<option value="">-- Pilih Kelas --</option>');
    kelasSelect.append('<option value="alumni" style="color:#9d174d;font-weight:bold;">🎓 Alumni (Tidak Ditempatkan)</option>');
    kelasData.forEach(k => {
        if(selectedJur === "" || k.kode_jurusan === selectedJur) {
            let isSelected = (String(k.id) === String(selectedKelas)) ? 'selected' : '';
            kelasSelect.append(`<option value="${k.id}" ${isSelected}>${k.nama_kelas}</option>`);
        }
    });
    if(selectedKelas === 'alumni'){
        kelasSelect.val('alumni');
    }
}

$(document).ready(function(){
    filterKelasTambah();
    filterKelasImport();

    $('.btn-edit').click(function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_nis').val($(this).data('nis'));
        $('#edit_nisn').val($(this).data('nisn'));
        $('#edit_nama').val($(this).data('nama'));
        $('#edit_tgl').val($(this).data('tgl'));
        
        let jur = $(this).data('jurusan');
        let kel = $(this).data('kelas');
        
        $('#edit_jurusan').val(jur);
        filterKelasEdit(jur, kel);
        $('#edit_kelas').val(kel);
    });
});
</script>