<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

include '../config/Database.php';
$db = new Database(); $conn = $db->getConnection();

// --- A. PROSES 1: TAMBAH TAHUN PELAJARAN + SEMESTER BARU SECARA MANUAL ---
if(isset($_POST['tambah_tahun_baru'])){
    $tahun_baru = trim($_POST['tahun_baru_input'] ?? '');
    $semester_baru = $_POST['semester_baru_input'] ?? '';
    
    if(!empty($tahun_baru) && !empty($semester_baru)){
        $tahun_baru_esc = mysqli_real_escape_string($conn, $tahun_baru);
        $semester_baru_esc = mysqli_real_escape_string($conn, $semester_baru);
        
        $cek_ta = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun = '$tahun_baru_esc' AND semester = '$semester_baru_esc' LIMIT 1");
        if($cek_ta->num_rows == 0){
            $conn->query("INSERT INTO tahun_ajaran (tahun, semester, status, hari_efektif) VALUES ('$tahun_baru_esc', '$semester_baru_esc', 'Tidak Aktif', 100)");
            echo "<script>alert('✅ Tahun Pelajaran $tahun_baru Semester $semester_baru Berhasil Ditambahkan!'); window.location='?page=identitas';</script>";
            exit;
        } else {
            echo "<script>alert('❌ Gagal! Tahun Pelajaran dan Semester tersebut sudah terdaftar.');</script>";
        }
    }
}

// --- 🔥 SATPAM BACKEND: PROSES HAPUS TAHUN AJARAN DENGAN VALIDASI SUPER KETAT ---
if(isset($_GET['hapus_ta'])){
    $id_hapus_ta = mysqli_real_escape_string($conn, $_GET['hapus_ta']);
    
    // 1. Cek status aktif
    $cek_status = $conn->query("SELECT status FROM tahun_ajaran WHERE id = '$id_hapus_ta' LIMIT 1");
    $status_ta = $cek_status ? $cek_status->fetch_assoc()['status'] : '';
    
    if(strtolower($status_ta) == 'aktif'){
        echo "<script>alert('❌ GAGAL! Tahun pelajaran yang sedang AKTIF tidak boleh dihapus. Alihkan dulu status aktif ke tahun lain.'); window.location='?page=identitas';</script>";
        exit;
    } 
    
    // 2. 👮‍♂️ SATPAM UTAMA: Cek apakah tahun ajaran ini sudah terpakai di tabel riwayat_kelas (Ploting siswa)
    $cek_riwayat = $conn->query("SELECT id FROM riwayat_kelas WHERE id_tahun_ajaran = '$id_hapus_ta' LIMIT 1");
    if($cek_riwayat && $cek_riwayat->num_rows > 0) {
        echo "<script>alert('❌ BLOKIR SISTEM!\\nTahun pelajaran ini SUDAH MEMILIKI DATA SISWA aktif yang terdaftar. Fitur hapus dibatalkan demi keamanan data!'); window.location='?page=identitas';</script>";
        exit;
    }

    // 3. Cek apakah sudah ada data PKL di tahun ini (jika ada kolomnya)
    $qKolomPKL = $conn->query("SHOW COLUMNS FROM data_pkl LIKE 'id_tahun_ajaran'");
    if($qKolomPKL && $qKolomPKL->num_rows > 0) {
        $cek_pkl = $conn->query("SELECT id FROM data_pkl WHERE id_tahun_ajaran = '$id_hapus_ta' LIMIT 1");
        if($cek_pkl && $cek_pkl->num_rows > 0) {
            echo "<script>alert('❌ BLOKIR SISTEM!\\nTahun pelajaran ini sudah memiliki data bimbingan PKL siswa aktif.'); window.location='?page=identitas';</script>";
            exit;
        }
    }

    // Jika beneran bersih dan kosong tanpa data (murni dobel salah ketik), barulah eksekusi hapus diizinkan jalan!
    $conn->query("DELETE FROM tahun_ajaran WHERE id = '$id_hapus_ta'");
    echo "<script>alert('✅ Tahun Pelajaran berhasil dibersihkan!'); window.location='?page=identitas';</script>";
    exit;
}

// --- B. PROSES 2: UPDATE IDENTITAS SEKOLAH & SET DROPDOWN TAHUN AKTIF ---
if(isset($_POST['update_sekolah'])){
    $ns = mysqli_real_escape_string($conn, $_POST['nama_sekolah'] ?? '');
    $npsn = mysqli_real_escape_string($conn, $_POST['npsn'] ?? '');
    $al = mysqli_real_escape_string($conn, $_POST['alamat_sekolah'] ?? '');
    $telp = mysqli_real_escape_string($conn, $_POST['telepon'] ?? '');
    $kel = mysqli_real_escape_string($conn, $_POST['kelurahan'] ?? '');
    $kec = mysqli_real_escape_string($conn, $_POST['kecamatan'] ?? '');
    $kota = mysqli_real_escape_string($conn, $_POST['kota'] ?? '');
    $prov = mysqli_real_escape_string($conn, $_POST['provinsi'] ?? '');
    $web = mysqli_real_escape_string($conn, $_POST['website'] ?? '');
    $mail = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $kepsek = mysqli_real_escape_string($conn, $_POST['nama_kepsek'] ?? '');
    $nip = mysqli_real_escape_string($conn, $_POST['nip_kepsek'] ?? '');

    $id_ta_terpilih = $_POST['id_tahun_ajaran_aktif'] ?? '';

    $conn->query("UPDATE identitas_sekolah SET 
        nama_sekolah='$ns', npsn='$npsn', alamat_sekolah='$al', telepon='$telp', 
        kelurahan='$kel', kecamatan='$kec', kota='$kota', provinsi='$prov', 
        website='$web', email='$mail', nama_kepsek='$kepsek', nip_kepsek='$nip' 
        WHERE id=1");

    if(!empty($id_ta_terpilih)){
        $id_ta_esc = mysqli_real_escape_string($conn, $id_ta_terpilih);
        $conn->query("UPDATE tahun_ajaran SET status='Tidak Aktif'");
        $conn->query("UPDATE tahun_ajaran SET status='Aktif' WHERE id = '$id_ta_esc'");
    }

    echo "<script>alert('✅ Identitas Sekolah & Tahun Pelajaran Aktif Berhasil Diperbarui!'); window.location='?page=identitas';</script>";
    exit;
}

// Ambil data identitas sekolah
$query = $conn->query("SELECT * FROM identitas_sekolah WHERE id=1 LIMIT 1");
$i = $query ? $query->fetch_assoc() : [];

// Ambil list semua Tahun Pelajaran
$list_dropdown_ta = [];
$ta_aktif_saat_ini = null;
$qListDropdown = $conn->query("SELECT id, tahun, semester, status FROM tahun_ajaran ORDER BY tahun DESC, semester ASC");
if($qListDropdown){
    while($row_d = $qListDropdown->fetch_assoc()){
        $list_dropdown_ta[] = $row_d;
        if(strtolower($row_d['status']) == 'aktif') {
            $ta_aktif_saat_ini = $row_d;
        }
    }
}
?>

<div class="container-fluid p-4">
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #f0f7ff; border-left: 5px solid #3b82f6 !important;">
        <form method="POST" action="" class="m-0">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-plus-circle me-1"></i> Tambah Kalender Tahun Pelajaran Baru</h6>
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="tahun_baru_input" class="form-control bg-white" placeholder="Contoh: 2027/2028" required>
                </div>
                <div class="col-md-3">
                    <select name="semester_baru_input" class="form-select bg-white" required>
                        <option value="">-- Pilih Semester --</option>
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="tambah_tahun_baru" class="btn btn-primary fw-bold w-100"><i class="fas fa-save me-1"></i> Daftarkan Kalender</button>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block italic">*Gunakan format 2027/2028.</small>
                </div>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <form method="POST" action="">
            <h5 class="fw-bold text-dark mb-4">Identitas Sekolah</h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="small fw-bold">Nama Sekolah</label>
                    <input type="text" name="nama_sekolah" class="form-control" value="<?= $i['nama_sekolah'] ?? '' ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold">NPSN</label>
                    <input type="text" name="npsn" class="form-control" value="<?= $i['npsn'] ?? '' ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="small fw-bold text-primary mb-1"><i class="fas fa-calendar-check me-1"></i> Set Tahun Pelajaran & Semester Aktif Saat Ini</label>
                    
                    <input type="hidden" name="id_tahun_ajaran_aktif" id="hidden_id_ta" value="<?= $ta_aktif_saat_ini['id'] ?? '' ?>">
                    
                    <div class="dropdown">
                        <button class="btn btn-white border border-primary w-100 d-flex justify-content-between align-items-center fw-bold text-dark text-start dropdown-toggle shadow-none" 
                                type="button" id="customDropdownTA" data-bs-toggle="dropdown" aria-expanded="false" style="height: 40px; background: #fbfdff;">
                            <span id="textDropdownTerpilih">
                                <?php if($ta_aktif_saat_ini): ?>
                                    Tahun Pelajaran <?= $ta_aktif_saat_ini['tahun'] ?> - Semester <?= $ta_aktif_saat_ini['semester'] ?> (Sedang Aktif)
                                <?php else: ?>
                                    -- Pilih Tahun Ajaran Aktif --
                                <?php endif; ?>
                            </span>
                        </button>
                        
                        <ul class="dropdown-menu w-100 shadow p-1" aria-labelledby="customDropdownTA" style="max-height: 250px; overflow-y: auto; border-color: #dbe5ef;">
                            <?php if(empty($list_dropdown_ta)): ?>
                                <li><span class="dropdown-item text-muted italic small">Belum ada tahun terdaftar</span></li>
                            <?php endif; ?>
                            
                            <?php foreach($list_dropdown_ta as $ta_row): ?>
                                <li class="d-flex align-items-center justify-content-between px-2 py-1 border-bottom border-light btn-item-custom-ta" style="cursor: pointer;" data-id="<?= $ta_row['id'] ?>" data-text="Tahun Pelajaran <?= $ta_row['tahun'] ?> - Semester <?= $ta_row['semester'] ?>">
                                    <span class="dropdown-item border-0 bg-transparent p-1 fw-bold text-dark small m-0 flex-grow-1">
                                        Tahun Pelajaran <?= $ta_row['tahun'] ?> - Semester <?= $ta_row['semester'] ?>
                                        <?= strtolower($ta_row['status']) == 'aktif' ? '<span class="badge bg-success ms-1 p-1 text-white" style="font-size: 9px;">AKTIF</span>' : '' ?>
                                    </span>
                                    
                                    <?php if(strtolower($ta_row['status']) != 'aktif'): ?>
                                        <a href="?page=identitas&hapus_ta=<?= $ta_row['id'] ?>" class="btn btn-sm btn-link text-danger p-1 m-0 text-decoration-none shadow-none btn-hapus-dalam-dropdown" onclick="event.stopPropagation(); return confirm('Peringatan: Hapus periode kalender sekolah ini?');">
                                            <i class="fas fa-times-circle fa-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="small fw-bold">Alamat Sekolah</label>
                    <textarea name="alamat_sekolah" class="form-control" rows="2" required><?= $i['alamat_sekolah'] ?? '' ?></textarea>
                </div>

                <div class="col-md-6"><label class="small fw-bold">Telepon</label><input type="text" name="telepon" class="form-control" value="<?= $i['telepon'] ?? '' ?>"></div>
                <div class="col-md-6"><label class="small fw-bold">Email</label><input type="text" name="email" class="form-control" value="<?= $i['email'] ?? '' ?>"></div>

                <div class="col-md-3"><label class="small fw-bold">Kelurahan</label><input type="text" name="kelurahan" class="form-control" value="<?= $i['kelurahan'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Kecamatan</label><input type="text" name="kecamatan" class="form-control" value="<?= $i['kecamatan'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Kota/Kabupaten</label><input type="text" name="kota" class="form-control" value="<?= $i['kota'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="small fw-bold">Provinsi</label><input type="text" name="provinsi" class="form-control" value="<?= $i['provinsi'] ?? '' ?>"></div>

                <div class="col-md-12"><label class="small fw-bold">Website</label><input type="text" name="website" class="form-control" value="<?= $i['website'] ?? '' ?>"></div>
                
                <div class="col-md-6"><label class="small fw-bold">Kepala Sekolah</label><input type="text" name="nama_kepsek" class="form-control" value="<?= $i['nama_kepsek'] ?? '' ?>"></div>
                <div class="col-md-6"><label class="small fw-bold">NIP Kepsek</label><input type="text" name="nip_kepsek" class="form-control" value="<?= $i['nip_kepsek'] ?? '' ?>"></div>
            </div>

            <button type="submit" name="update_sekolah" class="btn btn-primary mt-4 px-5 py-2 fw-bold shadow-sm rounded-pill"><i class="fas fa-save me-2"></i> Simpan Pengaturan</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    $('.btn-item-custom-ta').click(function(){
        let id_ta = $(this).data('id');
        let text_ta = $(this).data('text');
        
        $('#hidden_id_ta').val(id_ta);
        $('#textDropdownTerpilih').text(text_ta);
    });
});
</script>