<?php
session_start();
include 'config/Database.php';
$db = new Database(); 
$conn = $db->getConnection();

// Ambil input dari form login
$username = mysqli_real_escape_string($conn, $_POST['username']);
$password = $_POST['password']; // Jangan di-escape untuk password_verify agar karakter khusus tidak rusak

// --- KUNCI KEAMANAN HYBRID (Bisa plaintext & password_hash) ---
function verifikasiPassword($passwordInput, $passwordDatabase) {
    // 1. Cek jika password di DB adalah hasil hash bcrypt (selalu diawali $2y$)
    if (strpos($passwordDatabase, '$2y$') === 0) {
        return password_verify($passwordInput, $passwordDatabase);
    }
    // 2. Jika bukan hash, cek secara plaintext (untuk data lama agar tidak rusak)
    return ($passwordInput === $passwordDatabase);
}

// 1. CEK PINTU ADMIN
$login_admin = $conn->query("SELECT * FROM admin WHERE username='$username'");
if($login_admin->num_rows > 0){
    $data = $login_admin->fetch_assoc();
    
    if (verifikasiPassword($password, $data['password'])) {
        session_regenerate_id(true);
        
        // FORMAT SESSION GLOBAL (Biar dibaca sama kodingan lama)
        $_SESSION['username'] = $data['username'];
        $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
        $_SESSION['role'] = 'admin';
        $_SESSION['status'] = 'login';
        
        // FORMAT ARRAY LOKAL
        $_SESSION['admin'] = ['id' => $data['id'], 'nama' => $data['nama_lengkap'], 'status' => 'login'];
        header("location:admin/index.php"); 
        exit;
    }
}

// 2. CEK PINTU KEPALA SEKOLAH
$login_kepsek = $conn->query("SELECT * FROM kepala_sekolah WHERE username='$username' OR nip='$username'");
if($login_kepsek->num_rows > 0){
    $data = $login_kepsek->fetch_assoc();
    
    if (verifikasiPassword($password, $data['password'])) {
        session_regenerate_id(true);

        $_SESSION['username'] = $data['username'];
        $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
        $_SESSION['role'] = 'kepsek';
        $_SESSION['status'] = 'login';

        $_SESSION['kepsek'] = [
            'id' => $data['id'], 
            'nama_lengkap' => $data['nama_lengkap'], 
            'nip' => $data['nip'],
            'status' => 'login'
        ];
        
        header("location:kepsek/index.php"); 
        exit;
    }
}

// 3. CEK PINTU GURU
$login_guru = $conn->query("SELECT * FROM guru WHERE nip='$username'");
if($login_guru->num_rows > 0){
    $data = $login_guru->fetch_assoc();
    
    if (verifikasiPassword($password, $data['password'])) {
        session_regenerate_id(true);

        $_SESSION['username'] = $data['nip'];
        $_SESSION['nama_lengkap'] = $data['nama_guru'];
        $_SESSION['role'] = 'guru';
        $_SESSION['status'] = 'login';

        $_SESSION['guru'] = [
            'id' => $data['id'], 
            'nama' => $data['nama_guru'], 
            'nip' => $data['nip'], 
            'status' => 'login'
        ];
        header("location:guru/index.php"); 
        exit;
    }
}

// 4. CEK PINTU SISWA
$login_siswa = $conn->query("SELECT * FROM siswa WHERE nisn='$username'");
if($login_siswa->num_rows > 0){
    $data = $login_siswa->fetch_assoc();
    
    if (verifikasiPassword($password, $data['password'])) {
        session_regenerate_id(true);

        $_SESSION['username'] = $data['nisn'];
        $_SESSION['nama_lengkap'] = $data['nama_siswa'];
        $_SESSION['role'] = 'siswa';
        $_SESSION['status'] = 'login';

        $_SESSION['siswa'] = [
            'id' => $data['id'], 
            'nama' => $data['nama_siswa'], 
            'username' => $data['nisn'], 
            'status' => 'login'
        ];
        header("location:siswa/index.php"); 
        exit;
    }
}

// JIKA SEMUA GAGAL
header("location:index.php?pesan=gagal"); 
exit;
?>