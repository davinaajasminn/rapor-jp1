<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

if(!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// --- 1. PROSES SIMPAN / UPDATE ---
if(isset($_POST['simpan_jurusan']) || isset($_POST['update_jurusan'])){
    $kode = mysqli_real_escape_string($conn, $_POST['kode_jurusan']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_jurusan']);
    
    if(isset($_POST['simpan_jurusan'])){
        $conn->query("INSERT INTO jurusan (kode_jurusan, nama_jurusan) VALUES ('$kode', '$nama')");
    } else {
        $old_kode = mysqli_real_escape_string($conn, $_POST['old_kode']);
        $conn->query("UPDATE jurusan SET kode_jurusan='$kode', nama_jurusan='$nama' WHERE kode_jurusan='$old_kode'");
    }
    echo "<script>alert('Data Jurusan Berhasil Disimpan!'); window.location='?page=data_jurusan';</script>";
}

// --- 2. PROSES HAPUS ---
if(isset($_GET['hapus'])){
    $kode = mysqli_real_escape_string($conn, $_GET['hapus']);
    $conn->query("DELETE FROM jurusan WHERE kode_jurusan = '$kode'");
    echo "<script>window.location='?page=data_jurusan';</script>";
}

$qJur = $conn->query("SELECT * FROM jurusan ORDER BY kode_jurusan ASC");
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0">Data Jurusan</h4>
        <button class="btn btn-add-data" data-bs-toggle="modal" data-bs-target="#modalTambah" onclick="resetForm()">
            <i class="fas fa-plus me-2"></i> Tambah Jurusan
        </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table align-middle mb-0 text-center">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th>No</th>
                    <th>Kode Jurusan</th>
                    <th>Nama Jurusan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; while($j = $qJur->fetch_assoc()): ?>
                <tr class="bg-white">
                    <td><?= $no++ ?></td>
                    <td class="fw-bold"><?= $j['kode_jurusan'] ?></td>
                    <td><?= $j['nama_jurusan'] ?></td>
                    <td>
                        <button class="btn-action btn-action-edit" onclick="editMode('<?= $j['kode_jurusan'] ?>', '<?= $j['nama_jurusan'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?page=data_jurusan&hapus=<?= $j['kode_jurusan'] ?>" class="btn-action btn-action-delete" onclick="return confirm('Hapus jurusan ini?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3" id="m_title">Tambah Jurusan Baru</h6>
                    <input type="hidden" name="old_kode" id="old_kode">
                    <div class="mb-3">
                        <label class="small fw-bold">Kode Jurusan (Contoh: RPL)</label>
                        <input type="text" name="kode_jurusan" id="kode_jurusan" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Nama Jurusan</label>
                        <input type="text" name="nama_jurusan" id="nama_jurusan" class="form-control" required>
                    </div>
                    <button type="submit" name="simpan_jurusan" id="btn_submit" class="btn btn-save-data w-100 rounded-pill fw-bold">SIMPAN DATA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    $('#m_title').text('Tambah Jurusan Baru');
    $('#old_kode').val('');
    $('#kode_jurusan').val('');
    $('#nama_jurusan').val('');
    $('#btn_submit').attr('name', 'simpan_jurusan');
}
function editMode(kode, nama) {
    $('#m_title').text('Edit Data Jurusan');
    $('#old_kode').val(kode);
    $('#kode_jurusan').val(kode);
    $('#nama_jurusan').val(nama);
    $('#btn_submit').attr('name', 'update_jurusan');
    $('#modalTambah').modal('show');
}
</script>