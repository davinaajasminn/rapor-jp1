<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

/*
    FIX SEMESTER IDENTITAS SEKOLAH
    Pasang file ini dengan include di bagian paling atas admin/identitas.php:
    include 'identitas_semester_fix_include.php';

    Fungsi:
    1. Saat admin klik Simpan Perubahan di halaman Identitas, semester dan tahun pelajaran ikut disimpan.
    2. Semester disimpan ke tabel tahun_ajaran sebagai tahun ajaran aktif.
    3. Jika kolom semester_aktif / tahun_pelajaran ada di identitas_sekolah, ikut disinkronkan.
    4. Setelah simpan, halaman balik ke ?page=identitas sehingga dropdown tidak balik ke nilai lama.
*/

if(!isset($conn)){
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

function column_exists_fix_semester($conn, $table, $column){
    $table_safe = mysqli_real_escape_string($conn, $table);
    $col_safe = mysqli_real_escape_string($conn, $column);
    $q = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$col_safe'");
    return ($q && $q->num_rows > 0);
}

function ambil_post_semester_fix(){
    foreach(['semester', 'semester_aktif', 'smt', 'semester_sekolah'] as $k){
        if(isset($_POST[$k]) && trim((string)$_POST[$k]) !== ''){
            $v = trim((string)$_POST[$k]);
            return (stripos($v, 'genap') !== false || $v == '2' || $v == '4' || $v == '6') ? 'Genap' : 'Ganjil';
        }
    }
    return '';
}

function ambil_post_tahun_fix(){
    foreach(['tahun_pelajaran', 'tahun_ajaran', 'tahun'] as $k){
        if(isset($_POST[$k]) && trim((string)$_POST[$k]) !== ''){
            return trim((string)$_POST[$k]);
        }
    }
    return '';
}

/*
    Tangkap semua kemungkinan tombol simpan.
    Jadi walaupun tombolnya namanya simpan_identitas / update_identitas / simpan, tetap masuk.
*/
$is_submit_identitas = ($_SERVER['REQUEST_METHOD'] === 'POST');
if($is_submit_identitas){
    $semester_fix = ambil_post_semester_fix();
    $tahun_fix = ambil_post_tahun_fix();

    if($semester_fix !== '' || $tahun_fix !== ''){
        // Ambil fallback dari data aktif yang sudah ada.
        $qAktif = $conn->query("SELECT * FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
        $dAktif = ($qAktif && $qAktif->num_rows > 0) ? $qAktif->fetch_assoc() : [];

        if($semester_fix === ''){
            $semester_fix = $dAktif['semester'] ?? 'Ganjil';
        }

        if($tahun_fix === ''){
            $tahun_fix = $dAktif['tahun'] ?? date('Y').'/'.(date('Y') + 1);
        }

        $semester_safe = mysqli_real_escape_string($conn, $semester_fix);
        $tahun_safe = mysqli_real_escape_string($conn, $tahun_fix);

        // Sinkron ke tabel tahun_ajaran karena banyak modul sistem mengambil semester dari sini.
        $conn->query("CREATE TABLE IF NOT EXISTS tahun_ajaran (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tahun VARCHAR(20) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Tidak Aktif',
            hari_efektif INT(11) DEFAULT 100
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $cekTA = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun='$tahun_safe' AND semester='$semester_safe' LIMIT 1");

        if($cekTA && $cekTA->num_rows > 0){
            $id_ta_fix = (int)$cekTA->fetch_assoc()['id'];
        } else {
            $hari_efektif = $dAktif['hari_efektif'] ?? 100;
            $hari_efektif = (int)$hari_efektif;
            $conn->query("INSERT INTO tahun_ajaran (tahun, semester, status, hari_efektif)
                          VALUES ('$tahun_safe', '$semester_safe', 'Tidak Aktif', '$hari_efektif')");
            $id_ta_fix = (int)$conn->insert_id;
        }

        if($id_ta_fix > 0){
            $conn->query("UPDATE tahun_ajaran SET status='Tidak Aktif'");
            $conn->query("UPDATE tahun_ajaran SET status='Aktif' WHERE id='$id_ta_fix'");
        }

        // Sinkron juga ke identitas_sekolah kalau kolomnya memang ada.
        $set_identitas = [];
        if(column_exists_fix_semester($conn, 'identitas_sekolah', 'id_tahun_ajaran') && $id_ta_fix > 0){
            $set_identitas[] = "id_tahun_ajaran='$id_ta_fix'";
        }
        if(column_exists_fix_semester($conn, 'identitas_sekolah', 'semester_aktif')){
            $set_identitas[] = "semester_aktif='$semester_safe'";
        }
        if(column_exists_fix_semester($conn, 'identitas_sekolah', 'semester')){
            $set_identitas[] = "semester='$semester_safe'";
        }
        if(column_exists_fix_semester($conn, 'identitas_sekolah', 'tahun_pelajaran')){
            $set_identitas[] = "tahun_pelajaran='$tahun_safe'";
        }
        if(column_exists_fix_semester($conn, 'identitas_sekolah', 'tahun_ajaran')){
            $set_identitas[] = "tahun_ajaran='$tahun_safe'";
        }

        if(count($set_identitas) > 0){
            $cekIdentitas = $conn->query("SELECT * FROM identitas_sekolah LIMIT 1");
            if($cekIdentitas && $cekIdentitas->num_rows > 0){
                $conn->query("UPDATE identitas_sekolah SET ".implode(', ', $set_identitas)." LIMIT 1");
            }
        }
    }
}
?>