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

$jurusan_f = isset($_GET['jurusan']) ? $_GET['jurusan'] : null;
$tingkat_f = isset($_GET['tingkat']) ? $_GET['tingkat'] : null;
$search_mapel = $_GET['search'] ?? '';

function redirect_data_mapel($jurusan = '', $tingkat = '', $search = ''){
    $params = ['page' => 'data_mapel'];
    if($jurusan !== null && $jurusan !== '') $params['jurusan'] = $jurusan;
    if($tingkat !== null && $tingkat !== '') $params['tingkat'] = $tingkat;
    if($search !== null && $search !== '') $params['search'] = $search;

    return '?' . http_build_query($params);
}


// --- PROSES UPDATE URUTAN MAPEL VIA DRAG & DROP ---
if(isset($_POST['update_urutan_mapel'])){
    $urutan_data = json_decode($_POST['urutan_data'] ?? '[]', true);

    if(!is_array($urutan_data) || count($urutan_data) == 0){
        echo "<script>alert('Urutan gagal disimpan karena data urutan kosong. Coba drag ulang lalu klik Simpan Urutan.'); window.history.back();</script>";
        exit;
    }

    foreach($urutan_data as $item){
        $id_mapel = intval($item['id'] ?? 0);
        $urutan = intval($item['urutan'] ?? 0);

        if($id_mapel > 0 && $urutan > 0){
            $conn->query("UPDATE mapel SET urutan='$urutan' WHERE id='$id_mapel'");
        }
    }

    $jurusan_redirect = $_POST['jurusan_f'] ?? '';
    $tingkat_redirect = $_POST['tingkat_f'] ?? '';
    $search_redirect = $_POST['search_f'] ?? '';

    echo "<script>
        alert('Urutan mata pelajaran berhasil disimpan!');
        window.location='?page=data_mapel&jurusan=$jurusan_redirect&tingkat=$tingkat_redirect&search=$search_redirect';
    </script>";
    exit;
}

// --- 1. PROSES SIMPAN: ANTI-DOBEL & VALIDASI KKM ---
if(isset($_POST['simpan_mapel'])){
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_mapel'])); 
    $kel = $_POST['kelompok']; 
    $ting = $_POST['tingkat']; 
    $jur = $_POST['kode_jurusan']; 
    $kkm = (int)$_POST['kkm'];
    $fase = ($ting == 'X') ? 'E' : 'F';

    // Satpam KKM
    if($kkm < 0 || $kkm > 100){
        echo "<script>alert('GAGAL! Nilai KKM tidak masuk akal (Maksimal 100).'); window.history.back();</script>";
        exit;
    }

    // Aturan struktur mapel:
    // Muatan A = mapel umum, jadi jurusan wajib UMUM/NULL.
    // Muatan B = mapel kejuruan, jadi jurusan wajib dipilih.
    if($kel == 'A'){
        $jur = '';
    }

    if($kel == 'B' && empty($jur) && !empty($jurusan_f)){
        $jur = $jurusan_f;
    }

    if($kel == 'B' && empty($jur)){
        echo "<script>alert('Muatan B wajib memilih jurusan. Tidak boleh Umum.'); window.history.back();</script>";
        exit;
    }

    // Anti double nama mapel:
    // Nama mapel TIDAK BOLEH sama pada tingkat yang sama,
    // walaupun beda kelompok atau beda jurusan.
    // Contoh: Bahasa Inggris sudah ada di Muatan A, maka tidak boleh ditambah lagi di Muatan B.
    // Contoh: Dasar RPL sudah ada di Muatan B, maka tidak boleh ditambah lagi di Muatan A.
    $ting_safe = mysqli_real_escape_string($conn, $ting);
    $sql_cek = "SELECT id, kelompok, kode_jurusan FROM mapel 
                WHERE LOWER(TRIM(nama_mapel)) = LOWER(TRIM('$nama'))
                AND tingkat = '$ting_safe'
                LIMIT 1";

    $cek_ada = $conn->query($sql_cek);

    if($cek_ada && $cek_ada->num_rows > 0) {
        echo "<script>alert('GAGAL! Mapel $nama sudah ada di tingkat $ting. Nama mapel tidak boleh sama walaupun beda Muatan/Jurusan.'); window.history.back();</script>";
        exit;
    }

    $jur_val = empty($jur) ? "NULL" : "'" . mysqli_real_escape_string($conn, $jur) . "'";
    $conn->query("INSERT INTO mapel (nama_mapel, kkm, kelompok, tingkat, fase, kode_jurusan) VALUES ('$nama', '$kkm', '$kel', '$ting', '$fase', $jur_val)");

    $jur_redirect = !empty($jur) ? $jur : $jurusan_f;
    echo "<script>window.location='?page=data_mapel&jurusan=$jur_redirect&tingkat=$ting';</script>";
    exit;
}

// --- 2. PROSES UPDATE & VALIDASI KKM ---
if(isset($_POST['update_mapel'])){
    $id = (int)$_POST['id_mapel']; 
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_mapel'])); 
    $kel = $_POST['kelompok'];
    $ting = $_POST['tingkat']; 
    $jur = $_POST['kode_jurusan']; 
    $kkm = (int)$_POST['kkm'];
    $fase = ($ting == 'X') ? 'E' : 'F';

    // Satpam KKM
    if($kkm < 0 || $kkm > 100){
        echo "<script>alert('GAGAL! Nilai KKM tidak masuk akal (Maksimal 100).'); window.history.back();</script>";
        exit;
    }

    // Muatan A harus umum. Muatan B harus punya jurusan.
    if($kel == 'A'){
        $jur = '';
    }

    if($kel == 'B' && empty($jur) && !empty($jurusan_f)){
        $jur = $jurusan_f;
    }

    if($kel == 'B' && empty($jur)){
        echo "<script>alert('Muatan B wajib memilih jurusan. Tidak boleh Umum.'); window.history.back();</script>";
        exit;
    }

    // Anti double saat update:
    // Nama mapel tidak boleh sama pada tingkat yang sama,
    // walaupun beda kelompok atau beda jurusan.
    $ting_safe = mysqli_real_escape_string($conn, $ting);
    $sql_cek_update = "SELECT id, kelompok, kode_jurusan FROM mapel 
                       WHERE id != '$id'
                       AND LOWER(TRIM(nama_mapel)) = LOWER(TRIM('$nama'))
                       AND tingkat = '$ting_safe'
                       LIMIT 1";

    $cek_update = $conn->query($sql_cek_update);

    if($cek_update && $cek_update->num_rows > 0){
        echo "<script>alert('GAGAL! Mapel $nama sudah ada di tingkat $ting. Nama mapel tidak boleh sama walaupun beda Muatan/Jurusan.'); window.history.back();</script>";
        exit;
    }

    $jur_val = empty($jur) ? "NULL" : "'" . mysqli_real_escape_string($conn, $jur) . "'";
    $conn->query("UPDATE mapel SET nama_mapel='$nama', kkm='$kkm', kelompok='$kel', tingkat='$ting', fase='$fase', kode_jurusan=$jur_val WHERE id='$id'");

    $jur_redirect = !empty($jur) ? $jur : $jurusan_f;
    echo "<script>window.location='?page=data_mapel&jurusan=$jur_redirect&tingkat=$ting';</script>";
    exit;
}

// --- 3. PROSES HAPUS ---
if(isset($_GET['hapus'])){
    $id = (int)$_GET['hapus'];

    $jur_redirect = $_GET['jurusan'] ?? $jurusan_f ?? '';
    $ting_redirect = $_GET['tingkat'] ?? $tingkat_f ?? '';
    $search_redirect = $_GET['search'] ?? $search_mapel ?? '';
    $redirect_after_delete = redirect_data_mapel($jur_redirect, $ting_redirect, $search_redirect);

    $qMapelHapus = $conn->query("SELECT * FROM mapel WHERE id = '$id' LIMIT 1");
    if(!$qMapelHapus || $qMapelHapus->num_rows == 0){
        echo "<script>alert('Mapel tidak ditemukan.'); window.location='$redirect_after_delete';</script>";
        exit;
    }

    $dMapelHapus = $qMapelHapus->fetch_assoc();
    $nama_hapus = mysqli_real_escape_string($conn, trim($dMapelHapus['nama_mapel']));
    $tingkat_hapus = mysqli_real_escape_string($conn, $dMapelHapus['tingkat']);
    $kelompok_hapus = $dMapelHapus['kelompok'];
    $jurusan_hapus = mysqli_real_escape_string($conn, $dMapelHapus['kode_jurusan'] ?? '');

    // Kalau mapel umum/Muatan A dihapus, hapus semua data dengan nama + tingkat yang sama.
    // Ini untuk membersihkan mapel umum yang nyangkut di beberapa jurusan.
    // Kalau Muatan B dihapus, hapus mapel tersebut dan duplikatnya pada jurusan yang sama saja.
    if($kelompok_hapus == 'A'){
        $qTargetHapus = $conn->query("SELECT id FROM mapel
                                      WHERE LOWER(TRIM(nama_mapel)) = LOWER(TRIM('$nama_hapus'))
                                      AND tingkat = '$tingkat_hapus'");
    } else {
        $qTargetHapus = $conn->query("SELECT id FROM mapel
                                      WHERE LOWER(TRIM(nama_mapel)) = LOWER(TRIM('$nama_hapus'))
                                      AND tingkat = '$tingkat_hapus'
                                      AND kelompok = '$kelompok_hapus'
                                      AND (
                                            kode_jurusan = '$jurusan_hapus'
                                            OR (kode_jurusan IS NULL AND '$jurusan_hapus' = '')
                                            OR (kode_jurusan = '' AND '$jurusan_hapus' = '')
                                          )");
    }

    $ids_hapus = [];
    if($qTargetHapus){
        while($target = $qTargetHapus->fetch_assoc()){
            $ids_hapus[] = (int)$target['id'];
        }
    }

    if(count($ids_hapus) == 0){
        $ids_hapus[] = $id;
    }

    $ids_sql = implode(',', array_map('intval', $ids_hapus));

    try {
        // Bersihkan relasi dulu supaya tidak ketahan foreign key dan tidak nyangkut di tampilan lain.
        $conn->query("DELETE FROM guru_mengajar WHERE id_mapel IN ($ids_sql)");
        $conn->query("DELETE FROM nilai WHERE id_mapel IN ($ids_sql)");

        // Hapus mapel target.
        $conn->query("DELETE FROM mapel WHERE id IN ($ids_sql)");

        echo "<script>alert('Mapel berhasil dihapus.'); window.location='$redirect_after_delete';</script>";
        exit;
    } catch (mysqli_sql_exception $e) {
        echo "<script>alert('Mapel gagal dihapus karena masih terhubung dengan data lain. Detail: ".addslashes($e->getMessage())."'); window.location='$redirect_after_delete';</script>";
        exit;
    }
}

?>

<style>
    .toolbar-mapel {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08);
        padding: 16px;
        margin-bottom: 20px;
    }

    .search-mapel-box {
        position: relative;
    }

    .search-mapel-box i {
        position: absolute;
        left: 18px;
        top: 14px;
        color: #9aabbc;
        z-index: 2;
    }

    .input-search-mapel {
        height: 46px;
        border-radius: 50px;
        border: 1px solid #dbe5ef;
        background-color: #f8fbff;
        color: #0d385e;
        font-size: 14px;
        font-weight: 600;
        padding-left: 45px;
        box-shadow: none;
    }

    .input-search-mapel:focus {
        border-color: #9fc1e7;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14);
        background-color: #ffffff;
    }

    .btn-search-mapel {
        height: 46px;
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 0 24px;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .filter-mapel-box {
        height: 46px;
        border-radius: 50px;
        border: 1px solid #dbe5ef !important;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(80, 115, 155, 0.08);
    }

    .filter-mapel-box select {
        font-size: 14px;
    }

    .drag-handle-mapel {
        cursor: grab;
        color: #9B8ACB;
        font-size: 16px;
        margin-right: 9px;
    }

    .drag-handle-mapel:active {
        cursor: grabbing;
    }

    .row-mapel-draggable {
        cursor: default;
    }

    .row-mapel-dragging {
        opacity: 0.55;
        background: #F8FBFF !important;
    }

    .btn-save-urutan-mapel {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        padding: 9px 18px !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.18) !important;
    }

    .info-drag-mapel {
        background: #FFF5F7;
        border: 1px solid #F1B8C1;
        color: #8A3A45;
        border-radius: 16px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 600;
    }

    .badge-muatan-a {
        background: #E8DDFB !important;
        color: #5D3B8C !important;
        border: 1px solid #D7C4F2 !important;
        font-weight: 800 !important;
    }

    .badge-muatan-b {
        background: #FADADD !important;
        color: #8A3A45 !important;
        border: 1px solid #F1B8C1 !important;
        font-weight: 800 !important;
    }

</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between mb-4 align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Manajemen Mata Pelajaran</h4>
            </div>
        <button class="btn btn-add-data" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i>Tambah Mapel
        </button>
    </div>

    <div class="toolbar-mapel">
        <form method="GET" action="" class="m-0">
            <input type="hidden" name="page" value="data_mapel">

            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="search-mapel-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control input-search-mapel" placeholder="Cari nama mapel..." value="<?= htmlspecialchars($search_mapel) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="filter-mapel-box d-flex align-items-center px-2">
                        <select name="jurusan" class="form-select border-0 shadow-none fw-bold text-secondary">
                            <option value="">-- Pilih Jurusan --</option>
                            <?php $qJ = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC"); while($j = $qJ->fetch_assoc()): ?>
                                <option value="<?= $j['kode_jurusan'] ?>" <?= ($jurusan_f == $j['kode_jurusan'])?'selected':'' ?>><?= $j['nama_jurusan'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="filter-mapel-box d-flex align-items-center px-2">
                        <select name="tingkat" class="form-select border-0 shadow-none fw-bold text-secondary">
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="X" <?= ($tingkat_f == 'X')?'selected':'' ?>>Kelas X</option>
                            <option value="XI" <?= ($tingkat_f == 'XI')?'selected':'' ?>>Kelas XI</option>
                            <option value="XII" <?= ($tingkat_f == 'XII')?'selected':'' ?>>Kelas XII</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-search-mapel w-100">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if($jurusan_f || $tingkat_f || !empty($search_mapel)): ?>

    <?php if($jurusan_f && $tingkat_f): ?>
    <form method="POST" id="formUrutanMapel" class="d-none">
        <input type="hidden" name="update_urutan_mapel" value="1">
        <input type="hidden" name="urutan_data" id="urutanDataMapel">
        <input type="hidden" name="jurusan_f" value="<?= htmlspecialchars($jurusan_f) ?>">
        <input type="hidden" name="tingkat_f" value="<?= htmlspecialchars($tingkat_f) ?>">
        <input type="hidden" name="search_f" value="<?= htmlspecialchars($search_mapel) ?>">
    </form>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase text-center">
                <tr>
                    <th class="py-3">No</th>
                    <th class="text-start">Mata Pelajaran</th>
                    <th>KKM</th>
                    <th>Kelompok</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody class="text-center" id="tbodyMapelDrag">
                <?php 
                // FILTER: search bisa langsung tanpa wajib pilih jurusan dan tingkat
                $where_sql = "WHERE 1=1";

                if(!empty($tingkat_f)){
                    $tingkat_safe = $conn->real_escape_string($tingkat_f);
                    $where_sql .= " AND tingkat='$tingkat_safe'";
                }

                if(!empty($jurusan_f)){
                    $jurusan_safe = $conn->real_escape_string($jurusan_f);
                    $where_sql .= " AND (kode_jurusan='$jurusan_safe' OR kode_jurusan IS NULL)";
                }

                $search_escaped = $conn->real_escape_string($search_mapel);
                if(!empty($search_escaped)){
                    $where_sql .= " AND (nama_mapel LIKE '%$search_escaped%' OR kelompok LIKE '%$search_escaped%' OR kode_jurusan LIKE '%$search_escaped%')";
                }

                $sql = "SELECT * FROM mapel 
                        $where_sql
                        ORDER BY 
                            CASE 
                                WHEN kelompok = 'A' THEN 1
                                WHEN kelompok = 'B' THEN 2
                                WHEN kelompok = 'C1' THEN 3
                                WHEN kelompok = 'C2' THEN 4
                                WHEN kelompok = 'C3' THEN 5
                                ELSE 6
                            END ASC,
                            urutan ASC,
                            id ASC";
                $q = $conn->query($sql); 
                $no = 1;
                if($q->num_rows > 0):
                    while($r = $q->fetch_assoc()): ?>
                    <tr class="bg-white row-mapel-draggable" draggable="true" data-id="<?= $r['id'] ?>" data-kelompok="<?= $r['kelompok'] ?>">
                        <td><?= $no++ ?></td>
                        <td class="text-start">
                            <?php
                                // Kalau Muatan B tetapi kode_jurusan masih NULL/kosong,
                                // jangan tampilkan UMUM. Tampilkan jurusan yang sedang difilter.
                                $label_jurusan_mapel = $r['kode_jurusan'] ?? '';

                                if(empty($label_jurusan_mapel) && $r['kelompok'] == 'B' && !empty($jurusan_f)){
                                    $label_jurusan_mapel = $jurusan_f;
                                }

                                if(empty($label_jurusan_mapel)){
                                    $label_jurusan_mapel = 'UMUM';
                                }
                            ?>
                            <div class="fw-bold"><i class="fas fa-grip-vertical drag-handle-mapel"></i><?= $r['nama_mapel'] ?></div>
                            <span class="badge bg-light text-dark border small" style="font-size: 10px;"><?= $r['tingkat'] ?> | <?= $label_jurusan_mapel ?></span>
                        </td>
                        <td class="fw-bold text-primary"><?= $r['kkm'] ?></td>
                        <td>
                            <span class="badge rounded-pill <?= ($r['kelompok'] == 'A') ? 'badge-muatan-a' : (($r['kelompok'] == 'B') ? 'badge-muatan-b' : 'bg-info') ?>">
                                Muatan <?= $r['kelompok'] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-action btn-action-edit btn-edit-mapel" data-id="<?= $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama_mapel']) ?>" data-kkm="<?= $r['kkm'] ?>" data-kel="<?= $r['kelompok'] ?>" data-tingkat="<?= $r['tingkat'] ?>" data-jurusan="<?= $r['kode_jurusan'] ?>" data-bs-toggle="modal" data-bs-target="#modalEdit"><i class="fas fa-edit"></i></button>
                            <a href="?page=data_mapel&hapus=<?= $r['id'] ?>&jurusan=<?= urlencode($jurusan_f ?? '') ?>&tingkat=<?= urlencode($tingkat_f ?? '') ?>&search=<?= urlencode($search_mapel ?? '') ?>" class="btn-action btn-action-delete" onclick="return confirm('Hapus mapel ini? Jika mapel umum/Muatan A, data mapel dengan nama yang sama pada tingkat ini akan ikut dihapus di semua jurusan.')"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" class="py-4 text-muted">Data mapel tidak ditemukan untuk filter ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($jurusan_f && $tingkat_f): ?>
    <div class="d-flex justify-content-end mt-3 mb-2">
        <button type="button" class="btn btn-save-urutan-mapel" id="btnSimpanUrutanMapel">
            <i class="fas fa-save me-1"></i> Simpan Urutan
        </button>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3">Tambah Mapel</h6>
                    <div class="mb-3">
                        <label class="small fw-bold">Nama Mapel</label>
                        <input type="text" name="nama_mapel" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">KKM</label>
                            <input type="number" name="kkm" class="form-control" value="75" min="0" max="100" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Kelompok</label>
                            <select name="kelompok" id="tambah_kel_mapel" class="form-select select-kelompok-mapel" data-target-jurusan="#tambah_jur_mapel">
                                <option value="A">A - Umum</option>
                                <option value="B">B - Kejuruan</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Tingkat</label>
                            <select name="tingkat" class="form-select">
                                <option value="X">X</option>
                                <option value="XI">XI</option>
                                <option value="XII">XII</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Jurusan</label>
                            <select name="kode_jurusan" id="tambah_jur_mapel" class="form-select select-jurusan-mapel">
                                <option value="">-- Umum --</option>
                                <?php $qJ2 = $conn->query("SELECT * FROM jurusan"); while($j2 = $qJ2->fetch_assoc()) echo "<option value='".$j2['kode_jurusan']."'>".$j2['kode_jurusan']."</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="simpan_mapel" class="btn btn-save-data w-100 fw-bold py-2 rounded-pill mt-2">SIMPAN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <input type="hidden" name="id_mapel" id="edit_id_mapel">
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3">Edit Mapel</h6>
                    <div class="mb-3">
                        <label class="small fw-bold">Nama Mapel</label>
                        <input type="text" name="nama_mapel" id="edit_nama_mapel" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">KKM</label>
                            <input type="number" name="kkm" id="edit_kkm_mapel" class="form-control" min="0" max="100" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Kelompok</label>
                            <select name="kelompok" id="edit_kel_mapel" class="form-select select-kelompok-mapel" data-target-jurusan="#edit_jur_mapel">
                                <option value="A">A - Umum</option>
                                <option value="B">B - Kejuruan</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Tingkat</label>
                            <select name="tingkat" id="edit_ting_mapel" class="form-select">
                                <option value="X">X</option>
                                <option value="XI">XI</option>
                                <option value="XII">XII</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Jurusan</label>
                            <select name="kode_jurusan" id="edit_jur_mapel" class="form-select select-jurusan-mapel">
                                <option value="">-- Umum --</option>
                                <?php $qJ3 = $conn->query("SELECT * FROM jurusan"); while($j3 = $qJ3->fetch_assoc()) echo "<option value='".$j3['kode_jurusan']."'>".$j3['kode_jurusan']."</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="update_mapel" class="btn btn-update-data w-100 fw-bold py-2 rounded-pill mt-2">UPDATE</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function aturJurusanMapel(kelompokSelector, jurusanSelector){
    const kelompok = $(kelompokSelector).val();
    const jurusan = $(jurusanSelector);
    const optionUmum = jurusan.find('option[value=""]');

    if(kelompok === 'A'){
        // Muatan A wajib UMUM
        optionUmum.prop('disabled', false).show();
        jurusan.val('');
        jurusan.prop('disabled', true);
    } else if(kelompok === 'B'){
        // Muatan B tidak boleh UMUM
        jurusan.prop('disabled', false);
        optionUmum.prop('disabled', true).hide();

        if(jurusan.val() === '' || jurusan.val() === null){
            const jurusanFilterAktif = '<?= $jurusan_f ?? "" ?>';

            if(jurusanFilterAktif && jurusan.find('option[value="' + jurusanFilterAktif + '"]').length){
                jurusan.val(jurusanFilterAktif);
            } else {
                const pilihanPertama = jurusan.find('option[value!=""]:first').val();
                jurusan.val(pilihanPertama || '');
            }
        }
    } else {
        jurusan.prop('disabled', false);
        optionUmum.prop('disabled', false).show();
    }
}

function aktifkanRuleJurusanMapel(){
    $('.select-kelompok-mapel').each(function(){
        const targetJurusan = $(this).data('target-jurusan');
        if(targetJurusan){
            aturJurusanMapel(this, targetJurusan);
        }
    });

    $('.select-kelompok-mapel').off('change.ruleJurusanMapel').on('change.ruleJurusanMapel', function(){
        const targetJurusan = $(this).data('target-jurusan');
        if(targetJurusan){
            aturJurusanMapel(this, targetJurusan);
        }
    });

    // Supaya select jurusan yang disabled tetap ikut terkirim saat submit
    $('form').on('submit', function(){
        $(this).find('select:disabled').prop('disabled', false);
    });
}

function updateNomorMapel(){
    const rows = document.querySelectorAll('#tbodyMapelDrag .row-mapel-draggable');
    rows.forEach((row, index) => {
        const nomorCell = row.querySelector('td:first-child');
        if(nomorCell){
            nomorCell.textContent = index + 1;
        }
    });
}

function isiDataUrutanMapel(){
    const rows = document.querySelectorAll('#tbodyMapelDrag .row-mapel-draggable');

    if(rows.length === 0){
        return false;
    }

    const counterKelompok = {};
    const data = [];

    rows.forEach(row => {
        const id = row.dataset.id;
        const kelompok = row.dataset.kelompok;

        if(!counterKelompok[kelompok]){
            counterKelompok[kelompok] = 1;
        }

        data.push({
            id: id,
            urutan: counterKelompok[kelompok]
        });

        counterKelompok[kelompok]++;
    });

    $('#urutanDataMapel').val(JSON.stringify(data));
    return true;
}

$(document).ready(function(){
    aktifkanRuleJurusanMapel();

    $('.btn-edit-mapel').click(function(){
        $('#edit_id_mapel').val($(this).data('id'));
        $('#edit_nama_mapel').val($(this).data('nama'));
        $('#edit_kkm_mapel').val($(this).data('kkm'));
        $('#edit_kel_mapel').val($(this).data('kel'));
        $('#edit_ting_mapel').val($(this).data('tingkat'));
        $('#edit_jur_mapel').val($(this).data('jurusan'));

        aturJurusanMapel('#edit_kel_mapel', '#edit_jur_mapel');
    });

    let draggedMapelRow = null;

    $('#tbodyMapelDrag .row-mapel-draggable').on('dragstart', function(e){
        draggedMapelRow = this;
        $(this).addClass('row-mapel-dragging');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });

    $('#tbodyMapelDrag .row-mapel-draggable').on('dragend', function(){
        $(this).removeClass('row-mapel-dragging');
        draggedMapelRow = null;
        updateNomorMapel();
        isiDataUrutanMapel();
    });

    $('#tbodyMapelDrag .row-mapel-draggable').on('dragover', function(e){
        e.preventDefault();

        if(!draggedMapelRow || draggedMapelRow === this) return;

        const kelompokAsal = $(draggedMapelRow).data('kelompok');
        const kelompokTujuan = $(this).data('kelompok');

        if(kelompokAsal !== kelompokTujuan) return;

        const rows = $('#tbodyMapelDrag .row-mapel-draggable').toArray();
        const draggedIndex = rows.indexOf(draggedMapelRow);
        const targetIndex = rows.indexOf(this);

        if(draggedIndex < targetIndex){
            $(this).after(draggedMapelRow);
        } else {
            $(this).before(draggedMapelRow);
        }

        updateNomorMapel();
        isiDataUrutanMapel();
    });

    $('#btnSimpanUrutanMapel').on('click', function(){
        if(!isiDataUrutanMapel()){
            alert('Tidak ada mata pelajaran untuk disimpan.');
            return;
        }

        $('#formUrutanMapel').trigger('submit');
    });
});
</script>