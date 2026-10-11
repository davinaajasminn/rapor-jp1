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

// --- 🔒 LOCK DATA AKTIF BERDASARKAN MASTER IDENTITAS SEKOLAH UNTUK DEFAULT ---
$qTAAktif = $conn->query("SELECT * FROM tahun_ajaran WHERE LOWER(status)='aktif' LIMIT 1");
$ta_aktif = $qTAAktif ? $qTAAktif->fetch_assoc() : null;
$tahun_aktif_default = $ta_aktif['tahun'] ?? '';

// Ambil filter tahun lewat GET (dropdown), kalau kosong otomatis pakai tahun aktif dari Identitas
$filter_tahun = $_GET['filter_tahun'] ?? $tahun_aktif_default;
$tahun_safe = mysqli_real_escape_string($conn, $filter_tahun);

// Kumpulkan semua ID unik (Ganjil & Genap) yang masuk dalam rumpun tahun terpilih
$qAmbilIDTA = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun = '$tahun_safe'");
$array_id_ta = [];
while($row_id = $qAmbilIDTA->fetch_assoc()){
    $array_id_ta[] = $row_id['id'];
}
$string_id_ta = count($array_id_ta) > 0 ? implode("','", $array_id_ta) : '0';


// --- 1. PROSES SIMPAN KELAS BARU (ANTI-FATAL ERROR CONSTRAINT) ---
if(isset($_POST['simpan_kelas'])){
    $nama_kelas = strtoupper(trim($_POST['nama_kelas']));
    $tingkat = trim($_POST['tingkat']);
    $jurusan = trim($_POST['kode_jurusan']);
    $nip_wali = trim($_POST['id_wali_kelas']); 

    $nama_kelas_esc = $conn->real_escape_string($nama_kelas);
    $tingkat_esc = $conn->real_escape_string($tingkat);
    $jurusan_esc = $conn->real_escape_string($jurusan);
    $nip_wali_esc = $conn->real_escape_string($nip_wali);

    // Cek apakah master kelas sudah ada atau belum
    $cekMaster = $conn->query("SELECT id FROM kelas WHERE UPPER(TRIM(nama_kelas)) = '$nama_kelas_esc' AND tingkat = '$tingkat_esc' AND kode_jurusan = '$jurusan_esc' LIMIT 1");
    
    if($cekMaster && $cekMaster->num_rows > 0){
        echo "<script>alert('Pemberitahuan: Kelas $nama_kelas sudah terdaftar di master data sekolah dan siap digunakan untuk periode tahun manapun.'); window.location='?page=data_kelas&filter_tahun=$filter_tahun';</script>";
        exit;
    } else {
        // Ambil ID guru penugasan wali kelas
        $id_guru_fix = "NULL";
        if(!empty($nip_wali_esc)){
            $qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip_wali_esc' LIMIT 1");
            if($qGuru && $qGuru->num_rows > 0) $id_guru_fix = (int)$qGuru->fetch_assoc()['id'];
        }

        // Jika benar-benar rombel baru gres, masukkan langsung ke master kelas utama (Aman tanpa bypass id_siswa = 0)
        $conn->query("INSERT INTO kelas (nama_kelas, tingkat, kode_jurusan, id_wali_kelas) VALUES ('$nama_kelas_esc', '$tingkat_esc', '$jurusan_esc', $id_guru_fix)");
        $id_kelas_baru = $conn->insert_id;

        // Catat juga ke riwayat_wali_kelas untuk tahun ajaran yang sedang dilihat/diinput,
        // supaya histori wali kelas per tahun ajaran ikut tercatat sejak kelas dibuat.
        if($id_guru_fix !== "NULL"){
            $tahun_target_esc = $conn->real_escape_string($filter_tahun);
            $conn->query("INSERT INTO riwayat_wali_kelas (id_kelas, id_guru, tahun) 
                          VALUES ('$id_kelas_baru', $id_guru_fix, '$tahun_target_esc')
                          ON DUPLICATE KEY UPDATE id_guru = $id_guru_fix");
        }

        echo "<script>alert('✅ Kelas Baru Berhasil Didaftarkan ke Master Data Sekolah!'); window.location='?page=data_kelas&filter_tahun=$filter_tahun';</script>";
        exit;
    }
}

// --- 2. PROSES UPDATE DATA WALI KELAS ---
if(isset($_POST['update_kelas'])){
    $id_kelas = $_POST['id_kelas'] ?? '';
    $nip_wali = trim($_POST['id_wali_kelas']); 
    
    $id_kelas_esc = $conn->real_escape_string($id_kelas);
    $nip_wali_esc = $conn->real_escape_string($nip_wali);
    $tahun_target_esc = $conn->real_escape_string($filter_tahun);

    $id_guru_fix = null;
    if(!empty($nip_wali_esc)){
        $qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip_wali_esc' LIMIT 1");
        if($qGuru && $qGuru->num_rows > 0) $id_guru_fix = (int)$qGuru->fetch_assoc()['id'];
    }

    // Wali kelas sekarang disimpan PER TAHUN AJARAN yang sedang dilihat/diedit
    // (variabel $filter_tahun), bukan menimpa satu kolom global di tabel kelas.
    // Ini supaya mengganti wali kelas di tahun ajaran baru tidak mengubah
    // histori wali kelas tahun-tahun sebelumnya (termasuk yang sudah tercetak di rapor).
    if($id_guru_fix !== null){
        $conn->query("INSERT INTO riwayat_wali_kelas (id_kelas, id_guru, tahun) 
                      VALUES ('$id_kelas_esc', $id_guru_fix, '$tahun_target_esc')
                      ON DUPLICATE KEY UPDATE id_guru = $id_guru_fix");
    } else {
        // Guru dikosongkan khusus untuk tahun ini saja, tahun lain tidak terpengaruh.
        $conn->query("DELETE FROM riwayat_wali_kelas WHERE id_kelas = '$id_kelas_esc' AND tahun = '$tahun_target_esc'");
    }

    // Sinkron sementara ke kolom lama kelas.id_wali_kelas, HANYA kalau yang diedit
    // adalah tahun ajaran yang sedang AKTIF. Ini menjaga file-file lain yang belum
    // dipindah membaca riwayat_wali_kelas (tahap berikutnya) tetap dapat data yang
    // benar untuk tahun aktif. Kolom ini akan dihapus setelah semua file dipindah.
    if($filter_tahun == $tahun_aktif_default){
        $id_guru_legacy = ($id_guru_fix !== null) ? $id_guru_fix : "NULL";
        $conn->query("UPDATE kelas SET id_wali_kelas = $id_guru_legacy WHERE id = '$id_kelas_esc'");
    }

    echo "<script>alert('✅ Data Wali Kelas Berhasil Diperbarui!'); window.location='?page=data_kelas&filter_tahun=$filter_tahun';</script>";
    exit;
}

// --- 3. PROSES HAPUS MASTER KELAS ---
if(isset($_GET['hapus'])){
    $id_del = mysqli_real_escape_string($conn, $_GET['hapus']);
    
    // Putuskan hubungan siswa yang masih berada di kelas ini
    $conn->query("UPDATE siswa SET id_kelas = NULL WHERE id_kelas = '$id_del'");
    $conn->query("DELETE FROM riwayat_kelas WHERE id_kelas = '$id_del'");
    $conn->query("DELETE FROM kelas WHERE id = '$id_del'");
    
    echo "<script>alert('Kelas berhasil dihapus secara keseluruhan dari sistem.'); window.location='?page=data_kelas&filter_tahun=$filter_tahun';</script>";
    exit;
}

// MEMBUAT LIST GROUP TAHUN AJARAN UNTUK FILTER DROPDOWN
$list_filter_tahun = [];
$qGroupTA = $conn->query("SELECT tahun, MAX(status) as status_aktif FROM tahun_ajaran GROUP BY tahun ORDER BY tahun DESC");
while($gta = $qGroupTA->fetch_assoc()){ $list_filter_tahun[] = $gta; }

$filter_jurusan = $_GET['jurusan'] ?? '';
$search_kelas = $_GET['search'] ?? '';
?>

<style>
    .toolbar-kelas {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08);
        padding: 16px;
        margin-bottom: 20px;
    }
    .search-kelas-box { position: relative; }
    .search-kelas-box i { position: absolute; left: 18px; top: 14px; color: #9aabbc; z-index: 2; }
    .input-search-kelas {
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
    .input-search-kelas:focus {
        border-color: #9fc1e7;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14);
        background-color: #ffffff;
    }
    .btn-search-kelas {
        height: 46px;
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 0 24px;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
    }
    .filter-jurusan-kelas {
        height: 46px;
        border-radius: 50px;
        border: 1px solid #dbe5ef !important;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(80, 115, 155, 0.08);
    }
    .badge-jumlah-siswa-click {
        cursor: pointer;
        border: none !important;
        transition: 0.2s ease;
    }
    .badge-jumlah-siswa-click:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.18) !important;
    }
    .modal-siswa-kelas .modal-content {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(80, 115, 155, 0.18);
        overflow: hidden;
    }
    .modal-siswa-kelas .modal-header {
        background: #D8E9FA;
        color: #0d385e;
        border-bottom: 1px solid #C7DDF3;
    }
    .table-siswa-kelas th {
        font-size: 12px;
        text-transform: uppercase;
        color: #607488;
        background: #F8FBFF;
    }
    .table-siswa-kelas td {
        font-size: 14px;
        vertical-align: middle;
    }
    .badge-jumlah-siswa-pink {
        background: #FADADD !important;
        color: #8A3A45 !important;
        border: 1px solid #F1B8C1 !important;
        box-shadow: 0 4px 10px rgba(190, 90, 110, 0.12) !important;
    }
    .badge-jumlah-siswa-pink:hover {
        background: #F8CCD2 !important;
        color: #8A3A45 !important;
        box-shadow: 0 4px 10px rgba(190, 90, 110, 0.18) !important;
    }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold m-0">Data Kelas</h4>
            <small class="text-secondary fw-bold"><i class="fas fa-calendar-alt me-1"></i> TAMPILAN DATA TAHUN: <?= htmlspecialchars($filter_tahun) ?></small>
        </div>
        <button class="btn btn-add-data" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i> Tambah Kelas
        </button>
    </div>

    <div class="toolbar-kelas">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <form method="GET" action="" class="m-0">
                    <input type="hidden" name="page" value="data_kelas">
                    <input type="hidden" name="jurusan" value="<?= htmlspecialchars($filter_jurusan) ?>">
                    <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                    <div class="row g-2">
                        <div class="col-md-9">
                            <div class="search-kelas-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="form-control input-search-kelas" placeholder="Cari nama kelas, tingkat, wali kelas..." value="<?= htmlspecialchars($search_kelas) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-search-kelas w-100">Cari</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-3">
                <form method="GET" class="m-0">
                    <input type="hidden" name="page" value="data_kelas">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_kelas) ?>">
                    <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                    <div class="d-flex align-items-center filter-jurusan-kelas px-2 py-1 w-100">
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-1" style="width: 32px; height: 32px;">
                            <i class="fas fa-filter text-primary" style="font-size: 13px;"></i>
                        </div>
                        <select name="jurusan" class="form-select border-0 shadow-none fw-bold text-secondary" onchange="this.form.submit()">
                            <option value="">Semua Jurusan</option>
                            <?php 
                            $qJ_filter = $conn->query("SELECT * FROM jurusan");
                            while($jf = $qJ_filter->fetch_assoc()):
                            ?>
                                <option value="<?= $jf['kode_jurusan'] ?>" <?= ($filter_jurusan == $jf['kode_jurusan']) ? 'selected' : '' ?>>Jurusan <?= $jf['kode_jurusan'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="col-md-4">
                <form method="GET" class="m-0">
                    <input type="hidden" name="page" value="data_kelas">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_kelas) ?>">
                    <input type="hidden" name="jurusan" value="<?= htmlspecialchars($filter_jurusan) ?>">
                    <div class="d-flex align-items-center filter-jurusan-kelas px-2 py-1 w-100">
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-1" style="width: 32px; height: 32px;">
                            <i class="fas fa-calendar-alt text-success" style="font-size: 13px;"></i>
                        </div>
                        <select name="filter_tahun" class="form-select border-0 shadow-none fw-bold text-dark" onchange="this.form.submit()">
                            <?php foreach($list_filter_tahun as $ta_item): 
                                $label_aktif = (strtolower($ta_item['status_aktif']) == 'aktif' || $ta_item['tahun'] == $tahun_aktif_default) ? ' - [AKTIF]' : '';
                            ?>
                                <option value="<?= $ta_item['tahun'] ?>" <?= ($filter_tahun == $ta_item['tahun']) ? 'selected' : '' ?>>TP <?= $ta_item['tahun'] ?><?= $label_aktif ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table align-middle mb-0 text-center">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th>No</th>
                    <th>Nama Kelas</th>
                    <th>Tingkat</th>
                    <th>Jurusan</th>
                    <th>Wali Kelas</th>
                    <th>Jumlah Siswa</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $conditions = [];

                if(!empty($filter_jurusan)){
                    $conditions[] = "k.kode_jurusan = '".$conn->real_escape_string($filter_jurusan)."'";
                }
                if(!empty($search_kelas)){
                    $conditions[] = "(k.nama_kelas LIKE '%".$conn->real_escape_string($search_kelas)."%' OR g.nama_guru LIKE '%".$conn->real_escape_string($search_kelas)."%')";
                }

                $where_sql = "";
                if(count($conditions) > 0){
                    $where_sql = " WHERE " . implode(" AND ", $conditions);
                }

                // 🔥 LOGIC FIX: Menghitung jumlah murid aktif murni berdasarkan LEFT JOIN dari master kelas ke riwayat_kelas tahun terpilih
                // Wali kelas sekarang diambil dari riwayat_wali_kelas sesuai tahun ajaran yang sedang difilter,
                // bukan lagi dari kolom statis kelas.id_wali_kelas.
                $sql = "SELECT k.*, g.nama_guru, g.nip,
                        (SELECT COUNT(DISTINCT id_siswa) FROM riwayat_kelas WHERE id_kelas = k.id AND id_tahun_ajaran IN ('$string_id_ta') AND id_siswa != 0) as jml_siswa
                        FROM kelas k
                        LEFT JOIN riwayat_wali_kelas rwk ON rwk.id_kelas = k.id AND rwk.tahun = '$tahun_safe'
                        LEFT JOIN guru g ON rwk.id_guru = g.id
                        $where_sql
                        ORDER BY k.tingkat ASC, k.nama_kelas ASC";
                
                $q = $conn->query($sql);
                
                if($q && $q->num_rows > 0):
                    while($r = $q->fetch_assoc()):
                        // Wali kelas sekarang menampilkan data historis yang sebenarnya untuk tahun
                        // yang sedang dilihat, bukan disembunyikan paksa kalau bukan tahun aktif.
                        $wali_kelas_display = $r['nama_guru'] ?? '<span class="text-danger small">Belum Ditentukan</span>';
                ?>
                <tr class="bg-white">
                    <td><?= $no++ ?></td>
                    <td class="fw-bold"><?= $r['nama_kelas'] ?></td>
                    <td><?= $r['tingkat'] ?></td>
                    <td><?= $r['kode_jurusan'] ?></td>
                    <td><?= $wali_kelas_display ?></td>
                    
                    <td>
                        <?php if($r['jml_siswa'] > 0): ?>
                            <?php
                                $id_kelas_siswa = $r['id'];
                                $list_siswa_kelas = [];
                                $qSiswaKelas = $conn->query("SELECT s.nis, s.nisn, s.nama_siswa FROM riwayat_kelas r INNER JOIN siswa s ON r.id_siswa = s.id WHERE r.id_kelas = '$id_kelas_siswa' AND r.id_tahun_ajaran IN ('$string_id_ta') AND r.id_siswa != 0 GROUP BY s.id ORDER BY s.nama_siswa ASC");
                                while($sk = $qSiswaKelas->fetch_assoc()){
                                    $list_siswa_kelas[] = ['nis' => $sk['nis'],'nisn' => $sk['nisn'],'nama_siswa' => $sk['nama_siswa']];
                                }
                                $json_siswa_kelas = htmlspecialchars(json_encode($list_siswa_kelas), ENT_QUOTES, 'UTF-8');
                            ?>
                            <button type="button" class="badge rounded-pill px-3 shadow-sm btn-lihat-siswa-kelas badge-jumlah-siswa-pink" data-bs-toggle="modal" data-bs-target="#modalLihatSiswaKelas" data-kelas="<?= htmlspecialchars($r['nama_kelas']) ?>" data-jumlah="<?= $r['jml_siswa'] ?>" data-siswa="<?= $json_siswa_kelas ?>">
                                <?= $r['jml_siswa'] ?> Siswa
                            </button>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border rounded-pill px-3">Kosong</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <button class="btn-action btn-action-edit btn-edit-kelas" data-id="<?= $r['id'] ?>" data-nama="<?= $r['nama_kelas'] ?>" data-tingkat="<?= $r['tingkat'] ?>" data-jurusan="<?= $r['kode_jurusan'] ?>" data-nip="<?= $r['nip'] ?>" data-bs-toggle="modal" data-bs-target="#modalEdit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?page=data_kelas&hapus=<?= $r['id'] ?>&filter_tahun=<?= $filter_tahun ?>" class="btn-action btn-action-delete" onclick="return confirm('Hapus kelas ini dari master data sekolah?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="7" class="py-5 text-center text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                        <h6 class="fw-bold mb-0">Belum Ada Rombel Kelas Terdaftar.</h6>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade modal-siswa-kelas" id="modalLihatSiswaKelas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1"><i class="fas fa-users me-2"></i>Daftar Siswa <span id="modalNamaKelasSiswa"></span></h5>
                    <small id="modalJumlahSiswaKelas">Total 0 siswa</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-siswa-kelas">
                        <thead>
                            <tr>
                                <th class="text-center" width="8%">No</th>
                                <th width="22%">NIS</th>
                                <th width="22%">NISN</th>
                                <th>Nama Siswa</th>
                            </tr>
                        </thead>
                        <tbody id="tbodySiswaKelas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 p-4">
            <form method="POST">
                <h6 class="fw-bold mb-3">Tambah Kelas Baru</h6>
                <div class="mb-3"><label class="small fw-bold">Nama Kelas</label><input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: X RPL 1" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="small fw-bold">Tingkat</label><select name="tingkat" class="form-select"><option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option></select></div>
                    <div class="col-6"><label class="small fw-bold">Jurusan</label><select name="kode_jurusan" class="form-select"><?php $qJ = $conn->query("SELECT * FROM jurusan"); while($j = $qJ->fetch_assoc()) echo "<option value='".$j['kode_jurusan']."'>".$j['kode_jurusan']."</option>"; ?></select></div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Wali Kelas</label>
                    <select name="id_wali_kelas" class="form-select"><option value="">-- Pilih Guru --</option><?php $qG = $conn->query("SELECT nip, nama_guru FROM guru ORDER BY nama_guru ASC"); while($g = $qG->fetch_assoc()) echo "<option value='".$g['nip']."'>".$g['nama_guru']."</option>"; ?></select>
                </div>
                <button type="submit" name="simpan_kelas" class="btn btn-primary w-100 rounded-pill fw-bold">SIMPAN KELAS BARU</button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 p-4">
            <form method="POST">
                <input type="hidden" name="id_kelas" id="edit_id">
                <h6 class="fw-bold mb-3">Plotting Wali Kelas Pelajaran <?= htmlspecialchars($filter_tahun) ?></h6>
                <div class="mb-3"><label class="small fw-bold">Nama Kelas</label><input type="text" id="edit_nama" class="form-control" readonly></div>
                <div class="mb-3">
                    <label class="small fw-bold">Tentukan Wali Kelas</label>
                    <select name="id_wali_kelas" id="edit_wali" class="form-select"><option value="">-- Pilih Guru --</option><?php $qG = $conn->query("SELECT nip, nama_guru FROM guru ORDER BY nama_guru ASC"); while($g = $qG->fetch_assoc()) echo "<option value='".$g['nip']."'>".$g['nama_guru']."</option>"; ?></select>
                </div>
                <button type="submit" name="update_kelas" class="btn btn-update-data w-100 rounded-pill fw-bold">UPDATE WALI KELAS</button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    $('.btn-edit-kelas').click(function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_nama').val($(this).data('nama'));
        $('#edit_wali').val($(this).data('nip'));
    });

    $('.btn-lihat-siswa-kelas').click(function(){
        const namaKelas = $(this).data('kelas');
        const jumlah = $(this).data('jumlah');
        let siswa = $(this).attr('data-siswa');
        try { siswa = JSON.parse(siswa); } catch(e) { siswa = []; }
        $('#modalNamaKelasSiswa').text(namaKelas);
        $('#modalJumlahSiswaKelas').text('Total ' + jumlah + ' siswa');
        let html = '';
        if(siswa.length > 0){
            siswa.forEach(function(item, index){
                html += `<tr><td class="text-center">${index + 1}</td><td>${item.nis || '-'}</td><td>${item.nisn || '-'}</td><td class="fw-bold">${item.nama_siswa || '-'}</td></tr>`;
            });
        } else { html = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada data siswa.</td></tr>'; }
        $('#tbodySiswaKelas').html(html);
    });
});
</script>