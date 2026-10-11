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

// --- HELPER: CARI ID TAHUN_AJARAN BERDASARKAN TAHUN + SEMESTER ---
function getTahunAjaranId($conn, $tahun, $semester){
    $tahun_safe = mysqli_real_escape_string($conn, $tahun);
    $semester_safe = mysqli_real_escape_string($conn, $semester);
    if(empty($tahun_safe) || empty($semester_safe)) return 0;
    $q = $conn->query("SELECT id FROM tahun_ajaran WHERE tahun = '$tahun_safe' AND semester = '$semester_safe' LIMIT 1");
    return ($q && $q->num_rows > 0) ? (int)$q->fetch_assoc()['id'] : 0;
}

// --- AMBIL TAHUN AJARAN AKTIF (buat default filter & badge) ---
$qActiveTA = $conn->query("SELECT * FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$active_ta = ($qActiveTA && $qActiveTA->num_rows > 0) ? $qActiveTA->fetch_assoc() : null;
$active_ta_id = $active_ta ? (int)$active_ta['id'] : 0;

// --- FILTER TAMPILAN: TAHUN AJARAN & SEMESTER (dipakai buat lihat & edit tugas mengajar per periode) ---
$listTA = $conn->query("SELECT DISTINCT tahun FROM tahun_ajaran ORDER BY tahun DESC")->fetch_all(MYSQLI_ASSOC);

$filter_tahun = $_GET['filter_tahun'] ?? ($active_ta['tahun'] ?? '');
$filter_semester = $_GET['filter_semester'] ?? ($active_ta['semester'] ?? '');
$filter_ta_id = getTahunAjaranId($conn, $filter_tahun, $filter_semester);

// --- RADAR ANTI-BENTROKAN (Buat Satpam Javascript), discope ke periode yang lagi ditampilkan ---
$claimed_mapels = [];
$qSemuaGuru = $conn->query("SELECT g.id, g.nama_guru, m.nama_mapel, k.nama_kelas 
                            FROM guru_mengajar gm
                            JOIN guru g ON gm.id_guru = g.id
                            JOIN mapel m ON gm.id_mapel = m.id
                            JOIN kelas k ON gm.id_kelas = k.id
                            WHERE gm.id_tahun_ajaran = '$filter_ta_id'");
while($rg = $qSemuaGuru->fetch_assoc()){
    $gabungan = $rg['nama_mapel'] . " (" . $rg['nama_kelas'] . ")";
    $claimed_mapels[$gabungan] = ['id' => $rg['id'], 'nama' => $rg['nama_guru']];
}

// --- 1. PROSES SIMPAN / UPDATE GURU ---
if(isset($_POST['update_guru']) || isset($_POST['simpan_guru'])){
    $id = $_POST['id_guru'] ?? '';
    $nip = $_POST['nip'];
    $nama = $_POST['nama_guru'];
    $tgl = $_POST['tgl_lahir'];
    $hp = $_POST['no_hp'];
    $pw_baru = $tgl; 

    // --- TENTUKAN PERIODE TUJUAN (ikut filter yang lagi dibuka pas form disubmit) ---
    $target_tahun = $_POST['target_tahun'] ?? $filter_tahun;
    $target_semester = $_POST['target_semester'] ?? $filter_semester;
    $target_ta_id = getTahunAjaranId($conn, $target_tahun, $target_semester);

    // --- SATPAM VALIDASI DATA (Anti Input Ngawur) ---
    if (!preg_match("/^[a-zA-Z\s\.,]+$/", $nama)) {
        echo "<script>alert('GAGAL! Nama guru hanya boleh berisi huruf, spasi, titik, atau koma.'); history.back();</script>";
        exit;
    }
    if (!empty($hp) && !preg_match("/^[0-9]+$/", $hp)) {
        echo "<script>alert('GAGAL! Nomor HP hanya boleh berisi angka.'); history.back();</script>";
        exit;
    }
    if ($tgl > date('Y-m-d')) {
        echo "<script>alert('GAGAL! Tanggal lahir tidak masuk akal (Tahun dari masa depan).'); history.back();</script>";
        exit;
    }
    if ($target_ta_id === 0 && !empty($_POST['mapel_ajar'])) {
        echo "<script>alert('GAGAL! Periode Tahun Ajaran/Semester yang dipilih tidak ditemukan di database. Silakan pilih periode yang valid dulu di bagian filter sebelum atur tugas mengajar.'); history.back();</script>";
        exit;
    }
    // ------------------------------------------------

    // Amankan input sebelum dipakai ke query
    $nip = mysqli_real_escape_string($conn, $nip);
    $nama = mysqli_real_escape_string($conn, $nama);
    $tgl = mysqli_real_escape_string($conn, $tgl);
    $hp = mysqli_real_escape_string($conn, $hp);
    $pw_baru = mysqli_real_escape_string($conn, $pw_baru);

    // --- SATPAM BACKEND: CEK BENTROKAN (khusus di periode tujuan) ---
    $bentrok_list = [];
    if(isset($_POST['mapel_ajar'])){
        foreach($_POST['mapel_ajar'] as $m_gabung){
            if (preg_match('/^(.*)\s\(([^()]*)\)$/', $m_gabung, $matches)) {
                $nama_m = trim($matches[1]);
                $nama_k = trim($matches[2]);
                
                $nama_clean = mysqli_real_escape_string($conn, $nama_m);
                $sql_cek = "SELECT g.nama_guru 
                            FROM guru_mengajar gm 
                            JOIN mapel m ON gm.id_mapel = m.id 
                            JOIN kelas k ON gm.id_kelas = k.id 
                            JOIN guru g ON gm.id_guru = g.id 
                            WHERE m.nama_mapel LIKE '%$nama_clean%' 
                            AND k.nama_kelas = '$nama_k' 
                            AND gm.id_tahun_ajaran = '$target_ta_id'
                            AND gm.id_guru != '$id'"; 
                $qCek = $conn->query($sql_cek);
                
                if($qCek->num_rows > 0){
                    $guru_bentrok = $qCek->fetch_assoc()['nama_guru'];
                    $bentrok_list[] = "- $nama_m di $nama_k (Oleh: $guru_bentrok)";
                }
            }
        }
    }

    if(count($bentrok_list) > 0){
        $pesan_error = "GAGAL DISIMPAN!\\nMapel ini sudah diajarkan oleh guru lain di kelas tersebut (periode $target_tahun - $target_semester):\\n\\n" . implode("\\n", $bentrok_list);
        echo "<script>alert('$pesan_error'); history.back();</script>";
        exit;
    }
    // ----------------------------------------------------

    // --- VALIDASI NIP DUPLIKAT ---
    // Tambah guru: NIP tidak boleh sudah ada.
    // Edit guru: NIP boleh sama dengan data dirinya sendiri, tapi tidak boleh sama dengan guru lain.
    $id_safe = mysqli_real_escape_string($conn, $id);

    if(isset($_POST['simpan_guru'])){
        $cek_nip = $conn->query("SELECT id FROM guru WHERE nip = '$nip' LIMIT 1");
    } else {
        $cek_nip = $conn->query("SELECT id FROM guru WHERE nip = '$nip' AND id != '$id_safe' LIMIT 1");
    }

    if($cek_nip && $cek_nip->num_rows > 0){
        echo "<script>alert('GAGAL! NIP sudah digunakan oleh guru lain. Silakan gunakan NIP yang berbeda.'); history.back();</script>";
        exit;
    }
    // ---------------------------------

    if(isset($_POST['simpan_guru'])){
    $simpan = $conn->query("INSERT INTO guru (nip, nama_guru, tgl_lahir, password, no_hp) VALUES ('$nip', '$nama', '$tgl', '" . password_hash($pw_baru, PASSWORD_DEFAULT) . "', '$hp')");
        if(!$simpan){
            echo "<script>alert('GAGAL! Data guru tidak berhasil ditambahkan. Periksa kembali data yang diinput.'); history.back();</script>";
            exit;
        }

        $id = $conn->insert_id;
    } else {
        // PERBAIKAN: Kolom password dihapus dari query agar string HASH tidak tertimpa teks biasa saat edit data
        $update = $conn->query("UPDATE guru SET nip='$nip', nama_guru='$nama', tgl_lahir='$tgl', no_hp='$hp' WHERE id='$id_safe'");

        if(!$update){
            echo "<script>alert('GAGAL! Data guru tidak berhasil diupdate. Periksa kembali data yang diinput.'); history.back();</script>";
            exit;
        }

        // BERSIHKAN TUGAS LAMA — HANYA UNTUK PERIODE TUJUAN, biar histori periode lain gak ikut kehapus
        $conn->query("DELETE FROM guru_mengajar WHERE id_guru = '$id_safe' AND id_tahun_ajaran = '$target_ta_id'");
    }

    if(isset($_POST['mapel_ajar']) && $target_ta_id > 0){
        foreach($_POST['mapel_ajar'] as $m_gabung){
            if (preg_match('/^(.*)\s\(([^()]*)\)$/', $m_gabung, $matches)) {
                $nama_m = trim($matches[1]);
                $nama_k = trim($matches[2]);
                
                $qK = $conn->query("SELECT id, tingkat, kode_jurusan FROM kelas WHERE nama_kelas = '$nama_k' LIMIT 1");
                if($qK->num_rows > 0){
                    $dK = $qK->fetch_assoc();
                    $id_k = $dK['id'];
                    $tk_k = $dK['tingkat'];

                    $nama_clean = mysqli_real_escape_string($conn, $nama_m);
                    $sql_m = "SELECT id FROM mapel 
                              WHERE (nama_mapel LIKE '%$nama_clean%' OR '$nama_clean' LIKE CONCAT('%', nama_mapel, '%'))
                              AND tingkat = '$tk_k' 
                              LIMIT 1";
                    
                    $qM = $conn->query($sql_m);
                    $id_m = ($qM->num_rows > 0) ? $qM->fetch_assoc()['id'] : 0;

                    if($id_m > 0 && $id_k > 0){
                        $conn->query("INSERT INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES ('$id', '$id_m', '$id_k', '$target_ta_id')");
                    }
                }
            }
        }
    }
    $redirect_qs = "?page=data_guru&filter_tahun=" . urlencode($target_tahun) . "&filter_semester=" . urlencode($target_semester);
    echo "<script>alert('Data Berhasil Diupdate!'); window.location='$redirect_qs';</script>";
}

// --- 2. PROSES HAPUS ---
if(isset($_GET['hapus_guru'])){
    $id = $_GET['hapus_guru'];
    $conn->query("DELETE FROM guru_mengajar WHERE id_guru = '$id'");
    $conn->query("DELETE FROM guru WHERE id = '$id'");
    echo "<script>alert('Guru berhasil dihapus!'); window.location='?page=data_guru';</script>";
}

// --- 3. PROSES SALIN SEMUA PENUGASAN DARI PERIODE SEBELUMNYA (bulk, untuk semua guru sekaligus) ---
if(isset($_GET['salin_semua_periode'])){
    $redirect_balik = "?page=data_guru&filter_tahun=" . urlencode($filter_tahun) . "&filter_semester=" . urlencode($filter_semester);

    if($filter_ta_id === 0){
        echo "<script>alert('GAGAL! Periode tujuan (" . htmlspecialchars($filter_tahun) . " - " . htmlspecialchars($filter_semester) . ") tidak ditemukan di sistem.'); window.location='?page=data_guru';</script>";
        exit;
    }

    // Urutkan semua periode secara kronologis buat cari periode SEBELUM yang lagi difilter
    $qUrutBulk = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun ASC, FIELD(semester,'Ganjil','Genap') ASC");
    $urutanBulk = $qUrutBulk->fetch_all(MYSQLI_ASSOC);
    $prev_ta_bulk = null;
    foreach($urutanBulk as $idx => $ta){
        if((int)$ta['id'] === (int)$filter_ta_id){
            if($idx > 0) $prev_ta_bulk = $urutanBulk[$idx - 1];
            break;
        }
    }

    if(!$prev_ta_bulk){
        echo "<script>alert('GAGAL! Tidak ada periode sebelum " . htmlspecialchars($filter_tahun) . " - " . htmlspecialchars($filter_semester) . " yang tercatat di sistem.'); window.location='$redirect_balik';</script>";
        exit;
    }

    $prev_ta_id_bulk = (int)$prev_ta_bulk['id'];
    $qTotalSrc = $conn->query("SELECT COUNT(*) as total FROM guru_mengajar WHERE id_tahun_ajaran = '$prev_ta_id_bulk'");
    $total_src = (int)$qTotalSrc->fetch_assoc()['total'];

    if($total_src === 0){
        echo "<script>alert('Tidak ada data penugasan mengajar di periode {$prev_ta_bulk['tahun']} - {$prev_ta_bulk['semester']} buat disalin.'); window.location='$redirect_balik';</script>";
        exit;
    }

    // Salin, tapi lewati kombinasi mapel+kelas yang di periode tujuan udah ada guru pengampunya (jaga anti-bentrokan)
    $conn->query("INSERT INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran)
                  SELECT gm.id_guru, gm.id_mapel, gm.id_kelas, '$filter_ta_id'
                  FROM guru_mengajar gm
                  WHERE gm.id_tahun_ajaran = '$prev_ta_id_bulk'
                  AND NOT EXISTS (
                      SELECT 1 FROM guru_mengajar gm2
                      WHERE gm2.id_tahun_ajaran = '$filter_ta_id'
                      AND gm2.id_mapel = gm.id_mapel
                      AND gm2.id_kelas = gm.id_kelas
                  )");
    $inserted = $conn->affected_rows;
    $dilewati = $total_src - $inserted;

    $pesan = "Berhasil menyalin $inserted penugasan mengajar dari periode {$prev_ta_bulk['tahun']} - {$prev_ta_bulk['semester']} ke " . htmlspecialchars($filter_tahun) . " - " . htmlspecialchars($filter_semester) . ".";
    if($dilewati > 0){
        $pesan .= "\\n$dilewati item dilewati (mapel & kelas itu udah ada guru pengampunya di periode tujuan).";
    }

    echo "<script>alert('$pesan'); window.location='$redirect_balik';</script>";
    exit;
}

$allMapel = $conn->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetch_all(MYSQLI_ASSOC);
$allKelas = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

$search_guru = $_GET['search'] ?? '';
$search_guru_safe = mysqli_real_escape_string($conn, $search_guru);
$where_guru = "";

if(!empty($search_guru_safe)){
    // Search sekarang bisa nyari NIP, nama guru, mapel yang diajar, kelas, ATAU gabungan tingkat+mapel (cth: "XI Sejarah")
    $where_guru = "WHERE (
        g.nip LIKE '%$search_guru_safe%' 
        OR g.nama_guru LIKE '%$search_guru_safe%'
        OR EXISTS (
            SELECT 1 FROM guru_mengajar gm2
            JOIN mapel m2 ON gm2.id_mapel = m2.id
            JOIN kelas k2 ON gm2.id_kelas = k2.id
            WHERE gm2.id_guru = g.id
            AND gm2.id_tahun_ajaran = '$filter_ta_id'
            AND (
                m2.nama_mapel LIKE '%$search_guru_safe%'
                OR k2.nama_kelas LIKE '%$search_guru_safe%'
                OR CONCAT(k2.tingkat, ' ', m2.nama_mapel) LIKE '%$search_guru_safe%'
                OR CONCAT(m2.nama_mapel, ' ', k2.tingkat) LIKE '%$search_guru_safe%'
            )
        )
    )";
}
?>

<style>
    .list-mapel-item { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 8px 12px; margin: 4px 0; display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600; }
    .btn-del-item { color: #dc3545; cursor: pointer; font-size: 18px; }
    .badge-mapel { font-size: 11px; white-space: normal !important; text-align: left; }
    .modal-content, .modal-body { overflow: visible !important; }
    
    /* STYLE TEKS ABU MUDA UNTUK TOMBOL TOGGLE */
    .toggle-mapel { cursor: pointer; color: #adb5bd; font-weight: bold; font-size: 11px; transition: 0.2s; display: inline-block; margin-top: 4px; }
    .toggle-mapel:hover { color: #6c757d; text-decoration: underline; }

    .search-guru-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(80, 115, 155, 0.08);
        padding: 18px 20px;
        margin-bottom: 20px;
    }

    .search-guru-wrapper {
        position: relative;
    }

    .search-guru-wrapper i {
        position: absolute;
        left: 18px;
        top: 14px;
        color: #9aabbc;
        z-index: 2;
    }

    .input-search-guru {
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

    select.input-search-guru {
        padding-left: 18px;
    }

    .input-search-guru:focus {
        border-color: #9fc1e7;
        box-shadow: 0 0 0 3px rgba(111, 167, 223, 0.14);
        background-color: #ffffff;
    }

    .btn-search-guru {
        height: 46px;
        background: #6FA7DF !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        padding: 0 28px;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }

    .periode-info { font-size: 12.5px; font-weight: 700; margin-top: 10px; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 text-dark">
        <h4 class="fw-bold m-0">Data Guru</h4>
        <button class="btn btn-add-data" onclick="addMode()"><i class="fas fa-plus"></i> Tambah Guru</button>
    </div>

    <?php if(!$active_ta): ?>
    <div class="alert alert-warning fw-bold rounded-4 mb-3">
        <i class="fas fa-triangle-exclamation me-1"></i> Belum ada Tahun Ajaran yang berstatus AKTIF. Set salah satu Tahun Ajaran menjadi Aktif dulu di menu Tahun Ajaran supaya tugas mengajar guru bisa diatur.
    </div>
    <?php endif; ?>

    <div class="search-guru-card">
        <form method="GET" action="">
            <input type="hidden" name="page" value="data_guru">

            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="search-guru-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control input-search-guru" placeholder="Cari nama, NIP, mapel, atau tingkat (cth: X Sejarah)..." value="<?= htmlspecialchars($search_guru) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="filter_tahun" class="form-select input-search-guru">
                        <?php foreach($listTA as $ta): $sel = ($ta['tahun'] == $filter_tahun) ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($ta['tahun']) ?>" <?= $sel ?>><?= htmlspecialchars($ta['tahun']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="filter_semester" class="form-select input-search-guru">
                        <option value="Ganjil" <?= $filter_semester == 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $filter_semester == 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-search-guru w-100">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                </div>
            </div>
        </form>

        <div class="periode-info text-muted d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                Menampilkan tugas mengajar periode:
                <span class="text-primary"><?= htmlspecialchars($filter_tahun) ?> - <?= htmlspecialchars($filter_semester) ?></span>
                <?php if($filter_ta_id === 0): ?>
                    <span class="badge bg-danger ms-1">Periode tidak ditemukan</span>
                <?php elseif($filter_ta_id === $active_ta_id): ?>
                    <span class="badge bg-success ms-1">AKTIF</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-1">Riwayat</span>
                <?php endif; ?>
            </div>
            <?php if($filter_ta_id !== 0): ?>
            <a href="?page=data_guru&filter_tahun=<?= urlencode($filter_tahun) ?>&filter_semester=<?= urlencode($filter_semester) ?>&salin_semua_periode=1"
               class="btn btn-sm btn-outline-primary rounded-pill"
               onclick="return confirm('Salin SEMUA penugasan mengajar (semua guru) dari periode sebelumnya ke <?= htmlspecialchars($filter_tahun) ?> - <?= htmlspecialchars($filter_semester) ?>?\n\nMapel & kelas yang di periode ini udah ada guru pengampunya TIDAK akan ditimpa.')">
                <i class="fas fa-copy me-1"></i> Salin Semua Penugasan dari Periode Sebelumnya
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <table class="table align-middle mb-0 text-center">
            <thead class="bg-light fw-bold small text-muted text-uppercase text-nowrap">
                <tr><th>NO</th><th>NIP</th><th>NAMA GURU</th><th width="45%">TUGAS MENGAJAR</th><th>AKSI</th></tr>
            </thead>
            <tbody>
                <?php 
                $no=1; 
                $q=$conn->query("SELECT * FROM guru g $where_guru ORDER BY nama_guru ASC"); 
                if($q && $q->num_rows > 0):
                while($r=$q->fetch_assoc()): 
                    $id_g = $r['id'];
                    $qM = $conn->query("SELECT m.nama_mapel, k.nama_kelas FROM guru_mengajar gm 
                                       JOIN mapel m ON gm.id_mapel = m.id 
                                       JOIN kelas k ON gm.id_kelas = k.id 
                                       WHERE gm.id_guru = '$id_g' AND gm.id_tahun_ajaran = '$filter_ta_id'");
                    $mapel_list = [];
                    while($m = $qM->fetch_assoc()) $mapel_list[] = $m['nama_mapel'] . " (" . $m['nama_kelas'] . ")";
                    $r['mapel_raw'] = implode(", ", $mapel_list); 
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-primary fw-bold small"><?= $r['nip'] ?></td>
                    <td class="text-start fw-bold"><?= $r['nama_guru'] ?></td>
                    <td class="text-start">
                        
                        <div id="mapel_short_<?= $id_g ?>">
                            <?php 
                            // --- LOGIKA LIMITER TAMPILAN MAPEL ---
                            $limit = 3; 
                            $total_m = count($mapel_list);
                            
                            for($i = 0; $i < $total_m; $i++){
                                if($i < $limit){
                                    echo "<span class='badge bg-white text-dark border shadow-sm me-1 mb-1 badge-mapel'>".$mapel_list[$i]."</span>";
                                }
                            }
                            
                            if($total_m > $limit){
                                $sisa = $total_m - $limit;
                                echo "<span class='toggle-mapel' onclick='toggleMapel($id_g, true)'>+ $sisa lainnya...</span>";
                            }

                            if($total_m == 0){
                                echo "<span class='text-muted small'>Belum ada tugas mengajar di periode ini</span>";
                            }
                            ?>
                        </div>

                        <div id="mapel_full_<?= $id_g ?>" style="display:none;">
                            <?php 
                            foreach($mapel_list as $m) {
                                echo "<span class='badge bg-white text-dark border shadow-sm me-1 mb-1 badge-mapel'>".$m."</span>";
                            }
                            ?>
                            <span class='toggle-mapel' onclick='toggleMapel(<?= $id_g ?>, false)'>Sembunyikan</span>
                        </div>

                    </td>
                    <td>
                        <button type="button" class="btn-action btn-action-edit" onclick='editMode(<?= json_encode($r) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?page=data_guru&hapus_guru=<?= $r['id'] ?>" class="btn-action btn-action-delete" onclick="return confirm('Hapus guru ini?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted fw-bold">
                            Data guru tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalGuru" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <input type="hidden" name="id_guru" id="g_id">
                <input type="hidden" name="target_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                <input type="hidden" name="target_semester" value="<?= htmlspecialchars($filter_semester) ?>">
                <div class="modal-body p-4">
                    <h5 class="fw-bold mb-4" id="m_title">Tambah Guru</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="small fw-bold">NIP</label>
                            <input type="number" name="nip" id="g_nip" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Nama Lengkap</label>
                            <input type="text" name="nama_guru" id="g_nama" class="form-control" pattern="[a-zA-Z\s\.,]+" title="Hanya boleh huruf, spasi, titik, dan koma" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Tanggal Lahir</label>
                            <input type="date" name="tgl_lahir" id="g_tgl" class="form-control" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">No HP</label>
                            <input type="text" name="no_hp" id="g_hp" class="form-control" pattern="[0-9]*" title="Hanya boleh angka" maxlength="15">
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-4 border mb-3">
                        <label class="small fw-bold text-primary mb-2 d-block">
                            PENGATURAN TUGAS MENGAJAR
                            <span class="text-muted fw-normal">— untuk periode: <?= htmlspecialchars($filter_tahun) ?> - <?= htmlspecialchars($filter_semester) ?><?= ($filter_ta_id === $active_ta_id && $active_ta_id !== 0) ? ' (Aktif)' : '' ?></span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select id="s_jur" class="form-select small">
                                    <option value="">-- Jurusan --</option>
                                    <?php 
                                    $qJ = $conn->query("SELECT * FROM jurusan");
                                    while($j = $qJ->fetch_assoc()) echo "<option value='".$j['kode_jurusan']."'>".$j['kode_jurusan']."</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="s_ting" class="form-select small">
                                    <option value="">-- Tingkat --</option>
                                    <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                                </select>
                            </div>
                            <div class="col-md-4"><select id="s_map" class="form-select small"><option value="">-- Pilih Mapel --</option></select></div>
                            <div class="col-md-2"><select id="s_kel" class="form-select small"><option value="">-- Kelas --</option></select></div>
                            <div class="col-12"><button type="button" class="btn btn-save-data btn-sm mt-3 w-100 fw-bold rounded-pill" onclick="addItem()">+ TAMBAH KE DAFTAR</button></div>
                        </div>
                    </div>
                    <div id="g_container" class="border p-2 rounded-3 bg-white" style="min-height:100px; max-height:200px; overflow-y:auto;"></div>
                    <button type="submit" name="simpan_guru" id="g_btn" class="btn btn-save-data w-100 mt-4 py-2 rounded-pill shadow">SIMPAN DATA GURU</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function toggleMapel(id, show) {
        if(show) {
            $('#mapel_short_'+id).hide();
            $('#mapel_full_'+id).fadeIn(200);
        } else {
            $('#mapel_full_'+id).hide();
            $('#mapel_short_'+id).fadeIn(200);
        }
    }

    const allM = <?= json_encode($allMapel) ?>;
    const allK = <?= json_encode($allKelas) ?>;
    const claimedMapels = <?= json_encode($claimed_mapels) ?>;

    function loadDropdowns() {
        let j = $('#s_jur').val(), t = $('#s_ting').val();
        $('#s_map').html('<option value="">-- Pilih Mapel --</option>');
        allM.forEach(x => {
            if((!t || x.tingkat == t) && (!j || x.kode_jurusan == j || !x.kode_jurusan || x.kode_jurusan == "")) {
                $('#s_map').append(`<option value="${x.nama_mapel}">${x.nama_mapel}</option>`);
            }
        });
        $('#s_kel').html('<option value="">-- Kelas --</option>');
        allK.forEach(x => {
            if((!t || x.nama_kelas.startsWith(t + ' ')) && (!j || x.nama_kelas.includes(j))) {
                $('#s_kel').append(`<option value="${x.nama_kelas}">${x.nama_kelas}</option>`);
            }
        });
    }

    function addMode() {
        $('#g_id, #g_nip, #g_nama, #g_tgl, #g_hp').val('');
        $('#g_container').empty();
        $('#m_title').text('Tambah Guru Baru');
        $('#g_btn').attr('name', 'simpan_guru').text('SIMPAN DATA GURU');
        loadDropdowns();
        $('#modalGuru').modal('show');
    }

    function editMode(data) {
        $('#g_id').val(data.id);
        $('#g_nip').val(data.nip);
        $('#g_nama').val(data.nama_guru);
        $('#g_tgl').val(data.tgl_lahir);
        $('#g_hp').val(data.no_hp);
        $('#m_title').text('Edit Data Guru');
        $('#g_btn').attr('name', 'update_guru').text('UPDATE DATA GURU');
        $('#g_container').empty();
        if(data.mapel_raw) {
            data.mapel_raw.split(", ").forEach(m => { if(m.trim() !== "") renderRow(m.trim()); });
        }
        loadDropdowns();
        $('#modalGuru').modal('show');
    }

    $(document).ready(function(){
        $('#s_jur, #s_ting').change(loadDropdowns);
        loadDropdowns();
    });

    function addItem() {
        let m = $('#s_map').val(), k = $('#s_kel').val();
        if(m && k) {
            let gabung = m + ' (' + k + ')';
            
            let currentGuruId = $('#g_id').val();
            if(claimedMapels[gabung] && claimedMapels[gabung].id != currentGuruId) {
                alert('❌ DITOLAK!\\nMapel ' + m + ' di ' + k + ' sudah diajarkan oleh: ' + claimedMapels[gabung].nama);
                return;
            }

            let sudahAda = false;
            $('#g_container input[name="mapel_ajar[]"]').each(function(){ if($(this).val() === gabung) sudahAda = true; });
            if(sudahAda) { alert('Sudah ada di daftar mapel guru ini!'); return; }
            renderRow(gabung);
        }
    }

    function renderRow(v) {
        $('#g_container').append(`<div class="list-mapel-item"><span>${v}</span><input type="hidden" name="mapel_ajar[]" value="${v}"><i class="fas fa-times-circle btn-del-item" onclick="$(this).parent().remove()"></i></div>`);
    }
</script>