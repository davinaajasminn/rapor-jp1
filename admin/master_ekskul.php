<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
// Ditambahkan supaya file ini tidak bisa dibuka langsung lewat URL tanpa login,
// (sebelumnya file ini bikin koneksi DB sendiri & jalan penuh walau belum login).
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// --- PENGAMAN KONEKSI (BIAR GAK ERROR NULL) ---
if (!isset($conn)) {
    include '../config/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// --- LOGIKA PROSES (SIMPAN, EDIT, HAPUS) ---
if (isset($_POST['tambah_ekskul'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_ekskul']);
    if (!empty($nama)) {
        $conn->query("INSERT INTO master_ekskul (nama_ekskul) VALUES ('$nama')");
        echo "<script>window.location='index.php?page=master_ekskul';</script>";
    }
}

if (isset($_POST['edit_ekskul'])) {
    $id = $_POST['id_ekskul'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama_ekskul']);
    $conn->query("UPDATE master_ekskul SET nama_ekskul = '$nama' WHERE id = '$id'");
    echo "<script>window.location='index.php?page=master_ekskul';</script>";
}

if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $conn->query("DELETE FROM master_ekskul WHERE id = '$id'");
    echo "<script>window.location='index.php?page=master_ekskul';</script>";
}

$query = $conn->query("SELECT * FROM master_ekskul ORDER BY nama_ekskul ASC");
?>

<style>
    .btn-pastel-primary {
        background: #8CB6F0;
        color: #ffffff;
        border: none;
        border-radius: 50px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(140, 182, 240, 0.30);
        transition: 0.25s ease;
    }

    .btn-pastel-primary:hover {
        background: #78A7EA;
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(140, 182, 240, 0.38);
    }

    .btn-action-edit,
    .btn-action-delete {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: 0.25s ease;
        margin: 0 2px;
        font-size: 14px;
    }

    .btn-action-edit {
        background: #FFF4D8;
        color: #D89A00;
    }

    .btn-action-edit:hover {
        background: #FFE8A8;
        color: #B77B00;
        transform: translateY(-2px);
    }

    .btn-action-delete {
        background: #FFE1E7;
        color: #D7264A;
    }

    .btn-action-delete:hover {
        background: #FFC9D4;
        color: #B81738;
        transform: translateY(-2px);
    }

    .table-soft thead {
        background: #F4F7FA;
        color: #6c757d;
        font-size: 12px;
        text-transform: uppercase;
    }

    .table-soft th {
        padding-top: 14px;
        padding-bottom: 14px;
        font-weight: 700;
    }

    .table-soft td {
        padding-top: 16px;
        padding-bottom: 16px;
        vertical-align: middle;
    }

    .modal-save-btn {
        background: #8CB6F0;
        color: #ffffff;
        border: none;
        font-weight: 700;
    }

    .modal-save-btn:hover {
        background: #78A7EA;
        color: #ffffff;
    }
</style>

<div class="container-fluid p-4">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Master Data Ekstrakurikuler</h4>
            </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-4 bg-white">
        <form method="POST" action="">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label fw-bold small text-muted">Nama Ekskul Baru</label>
                    <input 
                        type="text" 
                        name="nama_ekskul" 
                        class="form-control rounded-pill px-4" 
                        style="height: 45px;" 
                        placeholder="Ketik nama ekskul..." 
                        required
                    >
                </div>

                <div class="col-md-3">
                    <button type="submit" name="tambah_ekskul" class="btn btn-pastel-primary w-100 rounded-pill" style="height: 45px;">
                        <i class="fas fa-plus me-2"></i>Tambah
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <table class="table table-hover align-middle mb-0 table-soft">
            <thead>
                <tr>
                    <th class="px-4 text-center" width="10%">No</th>
                    <th>Nama Ekstrakurikuler</th>
                    <th class="text-center" width="18%">Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php $no = 1; while($d = $query->fetch_assoc()): ?>
                <tr>
                    <td class="px-4 text-center"><?= $no++ ?></td>

                    <td class="fw-bold">
                        <?= $d['nama_ekskul'] ?>
                    </td>

                    <td class="text-center">
                        <button 
                            type="button" 
                            class="btn-action-edit" 
                            title="Edit"
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal<?= $d['id'] ?>"
                        >
                            <i class="fas fa-pen"></i>
                        </button>

                        <a 
                            href="?page=master_ekskul&hapus=<?= $d['id'] ?>" 
                            class="btn-action-delete" 
                            title="Hapus"
                            onclick="return confirm('Yakin mau hapus?')"
                        >
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>

                <div class="modal fade" id="editModal<?= $d['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow rounded-4">
                            <div class="modal-header border-0">
                                <h5 class="modal-title fw-bold">Edit Ekskul</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="id_ekskul" value="<?= $d['id'] ?>">

                                    <label class="form-label fw-bold small text-muted">Nama Ekstrakurikuler</label>
                                    <input 
                                        type="text" 
                                        name="nama_ekskul" 
                                        class="form-control rounded-pill px-3" 
                                        value="<?= $d['nama_ekskul'] ?>" 
                                        required
                                    >
                                </div>

                                <div class="modal-footer border-0">
                                    <button type="submit" name="edit_ekskul" class="btn modal-save-btn rounded-pill px-4">
                                        Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>