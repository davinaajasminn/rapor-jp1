<?php
// File ini di-include di siswa/index.php, jadi variabel $conn, $id_siswa_asli, $username_login, dan $dSiswa biasanya sudah ada.
// Fix: jangan pakai tabel users karena di database kamu tabel users tidak ada. Password siswa diupdate dari tabel siswa.

if(!function_exists('kolom_ada_profil_siswa')){
    function kolom_ada_profil_siswa($conn, $table, $column){
        $table_safe = mysqli_real_escape_string($conn, $table);
        $column_safe = mysqli_real_escape_string($conn, $column);
        $q = $conn->query("SHOW COLUMNS FROM `$table_safe` LIKE '$column_safe'");
        return ($q && $q->num_rows > 0);
    }
}

if(!function_exists('ambil_kolom_password_siswa')){
    function ambil_kolom_password_siswa($conn){
        $kandidat = ['password', 'pass', 'kata_sandi', 'password_siswa'];
        foreach($kandidat as $kolom){
            if(kolom_ada_profil_siswa($conn, 'siswa', $kolom)){
                return $kolom;
            }
        }
        return '';
    }
}

if(!function_exists('password_cocok_siswa')){
    function password_cocok_siswa($input, $password_db){
        $input = (string)$input;
        $password_db = (string)$password_db;

        // Sistem kamu sebelumnya pakai plaintext, jadi ini tetap didukung.
        if($input === $password_db){
            return true;
        }

        // Dukungan kalau suatu saat password sudah di-hash.
        if(strlen($password_db) > 20 && password_verify($input, $password_db)){
            return true;
        }

        return false;
    }
}

$id_siswa_asli_safe = mysqli_real_escape_string($conn, $id_siswa_asli ?? '');

// Ambil ulang data siswa supaya foto/password selalu data terbaru.
$qSiswaProfil = $conn->query("SELECT * FROM siswa WHERE id='$id_siswa_asli_safe' LIMIT 1");
$dSiswaProfil = ($qSiswaProfil && $qSiswaProfil->num_rows > 0) ? $qSiswaProfil->fetch_assoc() : ($dSiswa ?? []);

$foto_sekarang = $dSiswaProfil['foto_profil'] ?? ($dSiswa['foto_profil'] ?? '');

// --- PROSES HAPUS FOTO PROFIL ---
if(isset($_POST['hapus_foto'])){
    if(!empty($foto_sekarang)){
        $file_foto = "../assets/uploads/".$foto_sekarang;
        if(file_exists($file_foto)){
            unlink($file_foto);
        }
    }

    if(kolom_ada_profil_siswa($conn, 'siswa', 'foto_profil')){
        $conn->query("UPDATE siswa SET foto_profil=NULL WHERE id='$id_siswa_asli_safe'");
    }

    echo "<script>alert('Foto profil berhasil dihapus!'); window.location='?page=profil';</script>";
    exit;
}

if(isset($_POST['simpan_profil'])){
    $pass_lama = $_POST['pass_lama'] ?? '';
    $pass_baru = $_POST['pass_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    $pass_berhasil = false;
    $foto_berhasil = false;

    // --- 1. LOGIKA PASSWORD ---
    // Tidak pakai tabel users. Password siswa dicek dan diupdate dari tabel siswa.
    if(!empty($pass_lama) || !empty($pass_baru) || !empty($konfirmasi)){
        if(empty($pass_lama) || empty($pass_baru) || empty($konfirmasi)){
            echo "<script>alert('Kalau ingin mengganti password, isi Password Lama, Password Baru, dan Konfirmasi Password.'); window.location='?page=profil';</script>";
            exit;
        }

        if($pass_baru != $konfirmasi){
            echo "<script>alert('Konfirmasi Password tidak cocok!'); window.location='?page=profil';</script>";
            exit;
        }

        $kolom_password = ambil_kolom_password_siswa($conn);

        if(empty($kolom_password)){
            echo "<script>alert('Kolom password di tabel siswa belum ditemukan. Pastikan tabel siswa punya kolom password/pass/kata_sandi.'); window.location='?page=profil';</script>";
            exit;
        }

        $qCekPass = $conn->query("SELECT `$kolom_password` AS password_siswa FROM siswa WHERE id='$id_siswa_asli_safe' LIMIT 1");
        $dCekPass = ($qCekPass && $qCekPass->num_rows > 0) ? $qCekPass->fetch_assoc() : null;

        if(!$dCekPass || !password_cocok_siswa($pass_lama, $dCekPass['password_siswa'] ?? '')){
            echo "<script>alert('Password Lama Salah!'); window.location='?page=profil';</script>";
            exit;
        }

        // Karena sistem awal kamu plaintext, default tetap plaintext agar login lama tidak rusak.
        // Kalau nanti login sudah pakai password_verify, bagian ini bisa diganti password_hash.
        $pass_baru_safe = mysqli_real_escape_string($conn, $pass_baru);
        $conn->query("UPDATE siswa SET `$kolom_password`='$pass_baru_safe' WHERE id='$id_siswa_asli_safe'");
        $pass_berhasil = true;
    }

    // --- 2. LOGIKA UPLOAD FOTO PROFIL ---
    if(isset($_FILES['foto']['name']) && $_FILES['foto']['name'] != ''){
        if(!kolom_ada_profil_siswa($conn, 'siswa', 'foto_profil')){
            echo "<script>alert('Kolom foto_profil belum ada di tabel siswa.'); window.location='?page=profil';</script>";
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $ext_diizinkan = ['jpg','jpeg','png','webp'];

        if(!in_array($ext, $ext_diizinkan)){
            echo "<script>alert('Format foto harus JPG, PNG, atau WEBP.'); window.location='?page=profil';</script>";
            exit;
        }

        $nama_file = "siswa_".time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['foto']['name']);
        $tmp_file = $_FILES['foto']['tmp_name'];

        $dir = '../assets/uploads/';
        if(!is_dir($dir)) mkdir($dir, 0777, true); 

        if(move_uploaded_file($tmp_file, $dir.$nama_file)){
            // Hapus foto lama kalau ada.
            if(!empty($foto_sekarang) && file_exists($dir.$foto_sekarang)){
                @unlink($dir.$foto_sekarang);
            }

            $nama_file_safe = mysqli_real_escape_string($conn, $nama_file);
            $conn->query("UPDATE siswa SET foto_profil='$nama_file_safe' WHERE id='$id_siswa_asli_safe'");
            $foto_berhasil = true;
        }
    }

    // --- 3. REDIRECT SETELAH SIMPAN ---
    if($pass_berhasil){
        echo "<script>alert('Password berhasil diganti! Silahkan login ulang.'); window.location='../logout.php';</script>";
        exit;
    } elseif($foto_berhasil){
        echo "<script>alert('Foto profil berhasil diperbarui!'); window.location='?page=profil';</script>";
        exit;
    } else {
        echo "<script>alert('Tidak ada perubahan yang disimpan.'); window.location='?page=profil';</script>";
        exit;
    }
}

// Tampilan Avatar
$nama_avatar = $nama_siswa_login ?? ($dSiswaProfil['nama_siswa'] ?? 'Siswa');
$avatar_default = "https://ui-avatars.com/api/?name=".urlencode($nama_avatar)."&background=random&color=fff&rounded=true&bold=true&size=150";
$foto_tampil = (!empty($foto_sekarang) && file_exists("../assets/uploads/".$foto_sekarang)) ? "../assets/uploads/".$foto_sekarang : $avatar_default;
?>


<style>
    .card-profil { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .label-pastel { font-weight: 700; color: #64748b; font-size: 13px; margin-bottom: 8px; display: block; }
    .form-control-pastel { background-color: #f1f5f9; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; }
    .form-control-pastel:focus { border-color: #8cb6f0; box-shadow: 0 0 0 3px rgba(140, 182, 240, 0.2); background-color: white; }
    .link-lupa { font-size: 12px; font-weight: 600; color: #8cb6f0; transition: 0.3s; }
    .link-lupa:hover { color: #0d385e; text-decoration: underline !important; }

    .btn-hapus-foto {
        background: #F8D7DA !important;
        color: #842029 !important;
        border: 1px solid #F1AEB5 !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 7px 18px !important;
        font-size: 12px !important;
        box-shadow: 0 4px 10px rgba(132, 32, 41, 0.08) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .btn-hapus-foto:hover,
    .btn-hapus-foto:focus,
    .btn-hapus-foto:active {
        background: #F5C2C7 !important;
        color: #842029 !important;
    }
</style>

<div class="row justify-content-center mt-3">
    <div class="col-md-7">
        <div class="card card-profil bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-user-cog me-2 text-primary"></i>Pengaturan Profil & Keamanan</h5>
            </div>
            <div class="card-body p-4">
                
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="text-center mb-4 pb-4 border-bottom">
                        <div class="position-relative d-inline-block mb-3">
                            <img src="<?= $foto_tampil ?>" class="rounded-circle shadow-sm" width="120" height="120" style="object-fit: cover; border: 3px solid #e2e8f0;">
                        </div>
                        <div>
                            <label class="label-pastel text-center">Ganti Foto Profil</label>
                            <input type="file" name="foto" class="form-control form-control-sm w-75 mx-auto" accept="image/*">
                            <small class="text-muted d-block mt-1" style="font-size: 10px;">Format: JPG/PNG. Biarkan kosong jika tidak ingin mengubah foto.</small>

                            <button type="submit" name="hapus_foto" class="btn btn-hapus-foto mt-3" onclick="return confirm('Hapus foto profil dan kembali ke avatar bawaan?')">
                                <i class="fas fa-trash-alt me-1"></i> Hapus Foto
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 mb-4 small">
                        <i class="fas fa-info-circle me-2"></i> 
                        Demi keamanan, disarankan untuk mengganti password default (Tanggal Lahir) Anda secara berkala. Kosongkan jika tidak ingin ganti password.
                    </div>

                    <div class="mb-3">
                        <label class="label-pastel">NISN / USERNAME</label>
                        <input type="text" class="form-control form-control-pastel bg-light" value="<?= $username_login ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label class="label-pastel mb-0">Password Lama</label>
                            <a href="#" onclick="alert('Lupa password lama? Silakan hubungi staf Tata Usaha (TU) SMK Jakarta Pusat 1 untuk mengecek atau mereset password Anda.'); return false;" class="text-decoration-none link-lupa">Lupa Password?</a>
                        </div>
                        <input type="password" name="pass_lama" class="form-control form-control-pastel" placeholder="Masukkan password saat ini...">
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="label-pastel">Password Baru</label>
                            <input type="password" name="pass_baru" class="form-control form-control-pastel" placeholder="Buat password baru...">
                        </div>
                        <div class="col-md-6">
                            <label class="label-pastel">Ulangi Password Baru</label>
                            <input type="password" name="konfirmasi" class="form-control form-control-pastel" placeholder="Ketik ulang password baru...">
                        </div>
                    </div>
                    
                    <button type="submit" name="simpan_profil" class="btn w-100 fw-bold rounded-pill mt-4 py-3 shadow-sm" style="background-color: #8cb6f0; color: white;">
                        <i class="fas fa-save me-2"></i> SIMPAN PERUBAHAN
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>