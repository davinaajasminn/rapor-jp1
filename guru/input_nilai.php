<?php
// =======================================================
// 🩹 FIX: CP BISA IKUT NYESUAIN KALAU TEMPLATE DIUPDATE
// =======================================================
// Sebelumnya teks CP "dipanggang" jadi teks statis pas nilai disimpan, lepas total dari
// template. Sekarang kita simpan juga predikat (A/B/C/D) yang dipakai + catatan manual
// guru secara TERPISAH, supaya nanti kalau template di setting_cp.php diupdate, sistem
// bisa nulis ulang deskripsi_cp yang sudah ada tanpa perlu guru input ulang satu-satu.
$cekKolomPredikat = $conn->query("SHOW COLUMNS FROM nilai LIKE 'predikat_huruf'");
if($cekKolomPredikat && $cekKolomPredikat->num_rows == 0){
    $conn->query("ALTER TABLE nilai ADD predikat_huruf VARCHAR(1) NULL AFTER deskripsi_cp");
}
$cekKolomCatatanManual = $conn->query("SHOW COLUMNS FROM nilai LIKE 'catatan_guru_manual'");
if($cekKolomCatatanManual && $cekKolomCatatanManual->num_rows == 0){
    $conn->query("ALTER TABLE nilai ADD catatan_guru_manual TEXT NULL AFTER predikat_huruf");
}

// --- AMBIL TAHUN AJARAN AKTIF ---
// Semua baca/tulis ke tabel `nilai` wajib disaring/ditandai id_tahun_ajaran ini,
// supaya nilai antar semester/tahun ajaran tidak tercampur.
$qTahunAktif = $conn->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$dTahunAktif = ($qTahunAktif && $qTahunAktif->num_rows > 0) ? $qTahunAktif->fetch_assoc() : null;
$id_tahun_aktif = (int)($dTahunAktif['id'] ?? 0);

if($id_tahun_aktif <= 0){
    echo "<div class='alert alert-danger mt-4 text-center fw-bold shadow-sm rounded-4'>
            Tahun Ajaran Aktif belum diset oleh Admin. Input nilai tidak dapat dilakukan.
          </div>";
    exit;
}

// --- CEK STATUS KUNCI RAPOR DARI KEPSEK ---
// Kunci sekarang dipisah:
// - rapor_uts_dikunci: mengunci TP yang sudah terisi, STS, Sikap, dan Catatan/CP UTS
//   TP yang masih kosong tetap bisa diisi untuk kebutuhan UAS.
// - rapor_uas_dikunci: mengunci seluruh form input nilai reguler
//
// 🩹 FIX: sebelumnya status kunci dibaca dari tabel `identitas_sekolah`, padahal
// pengesahan.php (yang dipakai Kepsek buat kunci/buka kunci) nyimpen & update
// statusnya di tabel `tahun_ajaran` (per id tahun ajaran). Karena beda tabel,
// `identitas_sekolah` gak pernah ke-update sama sekali pas Kepsek ngesahin rapor,
// jadi guru selalu kebaca "belum terkunci" dan tetap bisa input/edit nilai bebas
// meskipun rapor udah disahkan. Sekarang dibaca dari `tahun_ajaran` sesuai
// tahun ajaran aktif ($id_tahun_aktif), biar nyambung sama pengesahan.php.
$qKunci = $conn->query("SELECT rapor_uts_dikunci, rapor_uas_dikunci FROM tahun_ajaran WHERE id = '$id_tahun_aktif' LIMIT 1");
$dKunci = ($qKunci && $qKunci->num_rows > 0) ? $qKunci->fetch_assoc() : [];

$is_dikunci = 0; // kolom lama rapor_dikunci sudah tidak dipakai, diganti rapor_uts_dikunci/rapor_uas_dikunci per tahun ajaran
$is_uts_dikunci = (int)($dKunci['rapor_uts_dikunci'] ?? 0);
$is_uas_dikunci = (int)($dKunci['rapor_uas_dikunci'] ?? 0);

// Kalau kolom lama rapor_dikunci masih aktif, anggap semua terkunci.
if($is_dikunci == 1){
    $is_uts_dikunci = 1;
    $is_uas_dikunci = 1;
}


// Helper tanpa ubah database:
// Menentukan TP mana yang termasuk nilai UTS yang sudah dikunci.
// Karena tidak ada kolom snapshot, sistem mengunci prefix TP yang rata-ratanya masih sesuai nilai_t UTS.
// TP tambahan setelah UTS dikunci tetap bisa diedit selama UAS belum dikunci.
function jumlah_tp_uts_terkunci($tp_list, $nilai_t_uts){
    if(!is_array($tp_list)) return 0;
    $nilai_t_uts = (int)$nilai_t_uts;
    if($nilai_t_uts <= 0) return 0;

    $sum = 0;
    $count = 0;
    $last_match = 0;

    for($i = 0; $i < 9; $i++){
        $v = $tp_list[$i] ?? '';

        // Asumsi input TP berjalan berurutan dari kiri ke kanan.
        // Kalau ketemu kosong, TP setelahnya dianggap nilai tambahan UAS/live.
        if($v === null || $v === '' || (float)$v <= 0){
            break;
        }

        $sum += (float)$v;
        $count++;

        if(round($sum / $count) == $nilai_t_uts){
            $last_match = $count;
        }
    }

    return $last_match;
}

// --- 1. AMBIL DATA SEMESTER & IDENTITAS (Aman dari error kolom) ---
$semester_tampil = "GANJIL"; 
try {
    $qIden = $conn->query("SELECT * FROM identitas_sekolah LIMIT 1");
    if($qIden && $qIden->num_rows > 0) {
        $dIden = $qIden->fetch_assoc();
        if(isset($dIden['semester_aktif'])) $semester_tampil = strtoupper($dIden['semester_aktif']);
        elseif(isset($dIden['semester'])) $semester_tampil = strtoupper($dIden['semester']);
    }
} catch(Exception $e) {
    // Abaikan kalau tabel/kolom identitas belum sempurna
}

// --- 2. AMBIL DATA GURU & MAPPING MAPEL (Pake Tabel Relasi Baru) ---
$nip = $_SESSION['guru']['nip'] ?? '';
$nama_guru_login = $_SESSION['guru']['nama'] ?? '';

// Ambil ID Guru
$qGuru = $conn->query("SELECT id FROM guru WHERE nip = '$nip' OR nama_guru = '$nama_guru_login' LIMIT 1");
$id_guru_login = ($qGuru && $qGuru->num_rows > 0) ? $qGuru->fetch_assoc()['id'] : 0;

$mapel_data = [];
$has_mapel_pkl = false;

if($id_guru_login > 0) {
    // Tarik data langsung dari tabel relasi.
    // Mapel PKL dipisahkan dari input nilai reguler, karena PKL punya form sendiri.
    $qMengajar = $conn->query("SELECT gm.id_mapel, gm.id_kelas, m.nama_mapel, k.nama_kelas 
                               FROM guru_mengajar gm
                               JOIN mapel m ON gm.id_mapel = m.id
                               JOIN kelas k ON gm.id_kelas = k.id
                               WHERE gm.id_guru = '$id_guru_login'
                               AND gm.id_tahun_ajaran = '$id_tahun_aktif'
                               ORDER BY m.nama_mapel ASC, k.nama_kelas ASC");
    while($dm = $qMengajar->fetch_assoc()){
        $is_pkl_row = (
            stripos($dm['nama_mapel'], 'PKL') !== false ||
            stripos($dm['nama_mapel'], 'Praktik Kerja Lapangan') !== false ||
            stripos($dm['nama_mapel'], 'Prakerin') !== false
        );

        if($is_pkl_row){
            $has_mapel_pkl = true;
            continue;
        }

        $nama_mapel_key = trim($dm['nama_mapel']);
        $id_kelas_key = (int)$dm['id_kelas'];

        // Simpan relasi asli guru_mengajar supaya tidak salah ambil id_mapel
        // saat nama mapel sama / mapel sudah dihapus.
        $mapel_data[$nama_mapel_key][$id_kelas_key] = [
            'id_mapel' => (int)$dm['id_mapel'],
            'nama_kelas' => trim($dm['nama_kelas'])
        ];
    }
}

if(empty($mapel_data)){ 
    if($has_mapel_pkl){
        echo "<div class='alert alert-info mt-4 text-center fw-bold shadow-sm rounded-4'>
                Akun ini hanya memiliki tugas mengajar PKL. Input PKL tidak dilakukan di menu Input Nilai Mapel reguler.<br>
                <a href='?page=input_pkl' class='btn btn-warning rounded-pill fw-bold px-4 mt-3'>
                    <i class='fas fa-briefcase me-1'></i> Buka Input PKL
                </a>
              </div>";
    } else {
        echo "<div class='alert alert-danger mt-4 text-center fw-bold shadow-sm rounded-4'>Tugas mengajar belum diset oleh Admin!</div>"; 
    }
    exit; 
}

$kelas_db = [];
$qK = $conn->query("SELECT id, nama_kelas FROM kelas");
while($k = $qK->fetch_assoc()){ $kelas_db[trim($k['nama_kelas'])] = $k['id']; }

$id_kelas = $_GET['id_kelas'] ?? '';
$selected_mapel_nama = $_GET['mapel_nama'] ?? '';
$id_mapel = ''; 
$pilihan_tidak_valid = false;

// Ambil id_mapel dari relasi guru_mengajar yang sesuai mapel + kelas.
// Kalau URL lama membawa kelas yang sudah tidak cocok, tidak perlu munculkan alert.
// Cukup kosongkan pilihan kelas sehingga tabel/form tidak tampil.
if($selected_mapel_nama && $id_kelas){
    $id_kelas_int = (int)$id_kelas;

    if(isset($mapel_data[$selected_mapel_nama][$id_kelas_int])){
        $id_mapel = (int)$mapel_data[$selected_mapel_nama][$id_kelas_int]['id_mapel'];

        $nama_mapel_safe = mysqli_real_escape_string($conn, $selected_mapel_nama);
        $qCekAksesMapel = $conn->query("SELECT m.id
                                        FROM guru_mengajar gm
                                        JOIN mapel m ON gm.id_mapel = m.id
                                        WHERE gm.id_guru = '$id_guru_login'
                                        AND gm.id_kelas = '$id_kelas_int'
                                        AND gm.id_mapel = '$id_mapel'
                                        AND gm.id_tahun_ajaran = '$id_tahun_aktif'
                                        AND m.nama_mapel = '$nama_mapel_safe'
                                        LIMIT 1");

        if(!$qCekAksesMapel || $qCekAksesMapel->num_rows == 0){
            $id_mapel = '';
            $id_kelas = '';
            $pilihan_tidak_valid = true;
        }
    } else {
        // Ini kondisi yang kamu mau:
        // misal mapel dipilih tapi kelasnya beda/tidak tersedia,
        // halaman tetap kosong saja, bukan alert.
        $id_mapel = '';
        $id_kelas = '';
        $pilihan_tidak_valid = true;
    }
} elseif($selected_mapel_nama && !$id_kelas) {
    $id_mapel = '';
}

$is_mapel_pkl = (
    stripos($selected_mapel_nama, 'PKL') !== false || 
    stripos($selected_mapel_nama, 'Praktik Kerja Lapangan') !== false ||
    stripos($selected_mapel_nama, 'Prakerin') !== false
);

if($is_mapel_pkl){
    echo "<div class='alert alert-warning mt-4 text-center fw-bold shadow-sm rounded-4'>
            Mapel PKL tidak diinput lewat halaman Input Nilai Mapel reguler.<br>
            Gunakan menu khusus Input PKL agar data pembimbing, mitra, lokasi, lama PKL, dan nilai PKL tersimpan benar.
            <br>
            <a href='?page=input_pkl' class='btn btn-warning rounded-pill fw-bold px-4 mt-3'>
                <i class='fas fa-briefcase me-1'></i> Buka Input PKL
            </a>
          </div>";
    exit;
}

// --- 3. PROSES SIMPAN LEDGER ---
if(isset($_POST['simpan_ledger'])){

    // PENGAMAN EKSTRA: Kalau di-hack via Inspect Element.
    // Kalau UAS sudah dikunci, seluruh form nilai reguler ditutup.
    // Kalau hanya UTS yang dikunci, backend tetap menjaga TP/STS/Sikap/Catatan lama agar tidak berubah.
    if($is_uas_dikunci == 1) {
        echo "<script>alert('DITOLAK! Rapor UAS sudah dikunci Kepsek. Seluruh nilai tidak dapat diubah.'); window.location='?page=input_nilai';</script>";
        exit;
    }

    $post_id_kelas = (int)$_POST['id_kelas_post'];
    $post_id_mapel = (int)$_POST['id_mapel_post'];
    $nama_mapel_post = $_POST['nama_mapel_post'];
    $is_pkl_post = $_POST['is_pkl_post'] ?? 0;

    // Pengaman backend:
    // Kalau form lama masih kebuka setelah mapel/kelas dihapus, jangan boleh simpan.
    $nama_mapel_post_safe = mysqli_real_escape_string($conn, $nama_mapel_post);
    $qCekSimpanMapel = $conn->query("SELECT m.id
                                     FROM guru_mengajar gm
                                     JOIN mapel m ON gm.id_mapel = m.id
                                     WHERE gm.id_guru = '$id_guru_login'
                                     AND gm.id_kelas = '$post_id_kelas'
                                     AND gm.id_mapel = '$post_id_mapel'
                                     AND gm.id_tahun_ajaran = '$id_tahun_aktif'
                                     AND m.nama_mapel = '$nama_mapel_post_safe'
                                     LIMIT 1");
    if(!$qCekSimpanMapel || $qCekSimpanMapel->num_rows == 0){
        echo "<script>alert('GAGAL! Mapel sudah tidak tersedia atau sudah dihapus dari tugas mengajar.'); window.location='?page=input_nilai';</script>";
        exit;
    }
// AMBIL SETTING CP JIKA ADA (Integrasi dgn file setting_cp.php)
    // 🩹 FIX: sebelumnya query ini cuma filter WHERE id_guru=..., padahal setting_cp.php
    // nyimpen template berdasarkan kombinasi (nama_mapel_dasar + kode_jurusan), bukan per
    // guru. Efeknya kalau guru lain (bukan yang terakhir nyimpen template) ngajar mapel yang
    // sama, autofill CP-nya gak jalan sama sekali -- fitur "Mode Kompak" jadi gak nyambung.
    // Sekarang dicocokin ke mapel + jurusan kelas yang lagi diinput, siapapun gurunya.
    $qKelasJurusanPost = $conn->query("SELECT kode_jurusan FROM kelas WHERE id='$post_id_kelas' LIMIT 1");
    $kode_jurusan_post = ($qKelasJurusanPost && $qKelasJurusanPost->num_rows > 0) ? trim($qKelasJurusanPost->fetch_assoc()['kode_jurusan']) : '';
    if(empty($kode_jurusan_post)) $kode_jurusan_post = 'Umum';
    $kode_jurusan_post_safe = mysqli_real_escape_string($conn, $kode_jurusan_post);

    $qTmpl = $conn->query("SELECT * FROM setting_pernyataan WHERE nama_mapel_dasar='$nama_mapel_post_safe' AND kode_jurusan='$kode_jurusan_post_safe' LIMIT 1");
    $tmpl = ($qTmpl) ? $qTmpl->fetch_assoc() : null;
    
    foreach($_POST['nilai'] as $id_siswa => $n){
        $id_siswa = (int)$id_siswa;

        // Ambil nilai lama dulu. Ini wajib untuk menjaga data UTS yang sudah dikunci
        // supaya tidak berubah meskipun user mencoba edit lewat Inspect Element.
        $qNilaiLama = $conn->query("SELECT * FROM nilai WHERE id_siswa='$id_siswa' AND id_mapel='$post_id_mapel' AND id_tahun_ajaran='$id_tahun_aktif' LIMIT 1");
        $nilai_lama = ($qNilaiLama && $qNilaiLama->num_rows > 0) ? $qNilaiLama->fetch_assoc() : null;

        if($is_uts_dikunci == 1 && !$nilai_lama){
            echo "<script>alert('DITOLAK! UTS sudah dikunci, jadi sistem tidak boleh membuat data nilai UTS baru.'); window.history.back();</script>";
            exit;
        }

        $tp_all = $n['tp'] ?? array_fill(0, 9, 0);
        $tp_terisi = array_filter($tp_all, function($v) { return ($v !== null && $v !== '' && $v > 0); });
        
        // BACKEND VALIDATION: Mencegah nilai aneh lewat inspect element
        $is_valid = true;
        foreach($tp_terisi as $val) { if($val > 100 || $val < 0) $is_valid = false; }
        
        $rata_tp = (count($tp_terisi) > 0) ? round(array_sum($tp_terisi) / count($tp_terisi)) : 0;
        // rata_tp_live dipakai untuk kebutuhan UAS/live.
        // rata_tp_simpan adalah nilai_t yang disimpan ke database.
        // Saat UTS sudah dikunci, nilai_t harus tetap memakai nilai lama agar rapor UTS tidak berubah.
        $rata_tp_live = $rata_tp;
        $rata_tp_simpan = $rata_tp;
        $json_tp = json_encode($tp_all);
        
        // FIX BUG: Pemaksaan (casting) string kosong menjadi integer 0
        $sikap = (int)($n['sikap'] ?? 0);
        $sts = (int)($n['sts'] ?? 0);
        $sas = (int)($n['sas'] ?? 0);

        if($sikap > 100 || $sts > 100 || $sas > 100) $is_valid = false;

        if(!$is_valid) {
            echo "<script>alert('Ada nilai tidak wajar (Lebih dari 100). Proses dibatalkan!'); window.history.back();</script>";
            exit;
        }
        
        $catatan_guru = trim($n['deskripsi'] ?? '');
        $gunakan_auto = isset($n['use_auto']); 

        // Kalau UTS sudah dikunci:
        // - TP yang SUDAH TERISI sebelumnya tidak boleh berubah.
        // - TP yang MASIH KOSONG tetap boleh diisi untuk kebutuhan UAS.
        // - STS, Sikap, dan Catatan/CP tetap memakai data lama.
        // Ini juga jadi pengaman backend kalau ada yang mencoba edit lewat Inspect Element.
        if($is_uts_dikunci == 1 && $nilai_lama){
            $tp_lama = json_decode($nilai_lama['daftar_nilai_cp'] ?? '[]', true);
            if(!is_array($tp_lama)) $tp_lama = array_fill(0, 9, '');
            $tp_post = $n['tp'] ?? [];

            $jumlah_tp_kunci = jumlah_tp_uts_terkunci($tp_lama, (int)($nilai_lama['nilai_t'] ?? 0));

            $tp_merge = [];
            for($i = 0; $i < 9; $i++){
                $old_val = $tp_lama[$i] ?? '';
                $new_val = $tp_post[$i] ?? '';

                if($i < $jumlah_tp_kunci){
                    // Hanya TP yang dihitung sebagai nilai UTS final yang dikunci.
                    $tp_merge[$i] = $old_val;
                } else {
                    // TP tambahan setelah UTS dikunci tetap boleh diedit untuk kebutuhan UAS.
                    $tp_merge[$i] = $new_val;
                }
            }

            $tp_all = $tp_merge;
            $tp_terisi = array_filter($tp_all, function($v) { return ($v !== null && $v !== '' && $v > 0); });
            $rata_tp_live = (count($tp_terisi) > 0) ? round(array_sum($tp_terisi) / count($tp_terisi)) : 0;
            // KUNCI UTAMA: nilai_t tidak boleh ikut berubah setelah UTS dikunci.
            // TP tambahan tetap tersimpan di daftar_nilai_cp, tapi rapor UTS tetap memakai nilai_t lama.
            $rata_tp_simpan = (int)($nilai_lama['nilai_t'] ?? 0);
            $json_tp = json_encode($tp_all);

            $sikap = (int)($nilai_lama['nilai_sikap'] ?? 0);
            $sts = (int)($nilai_lama['nilai_p'] ?? 0);
            $catatan_guru = $nilai_lama['deskripsi_cp'] ?? '';
            $gunakan_auto = false;
        }

        // Logika Nilai Akhir
        if($is_pkl_post){
            $na = $rata_tp_live;
        } else {
            if(empty($sas) || $sas == 0){ $na = round(((4 * $rata_tp_simpan) + (2 * $sts)) / 6); } 
            else { $na = round(((4 * $rata_tp_live) + (2 * $sts) + $sas) / 7); }
        }

        // Logika Deskripsi Terintegrasi Master Setting CP
        $deskripsi_final = "";
        $predikat_huruf_simpan = null;
        $catatan_manual_simpan = $catatan_guru;

        if($is_pkl_post){
             $deskripsi_final = $catatan_guru;
        } else {
            if($gunakan_auto){
                if($na >= 90){ $predikat_huruf_simpan = 'A'; $auto_text = !empty($tmpl['template_a']) ? $tmpl['template_a'] : "Menunjukkan penguasaan yang sangat baik dalam " . $nama_mapel_post; }
                elseif($na >= 80){ $predikat_huruf_simpan = 'B'; $auto_text = !empty($tmpl['template_b']) ? $tmpl['template_b'] : "Menunjukkan penguasaan baik dalam " . $nama_mapel_post; }
                elseif($na >= 75){ $predikat_huruf_simpan = 'C'; $auto_text = !empty($tmpl['template_c']) ? $tmpl['template_c'] : "Menunjukkan penguasaan cukup baik dalam " . $nama_mapel_post; }
                else { $predikat_huruf_simpan = 'D'; $auto_text = !empty($tmpl['template_d']) ? $tmpl['template_d'] : "Menunjukkan penguasaan kurang baik dalam " . $nama_mapel_post; }
                
                if(!empty($catatan_guru)) {
                    $deskripsi_final = $auto_text . ". " . $catatan_guru;
                } else {
                    $deskripsi_final = $auto_text;
                }
            } else {
                // Guru nulis manual sepenuhnya (gak pake auto template) -> gak ada predikat
                // yang perlu di-resync kalau template berubah, karena teksnya murni tulisan guru.
                $deskripsi_final = $catatan_guru;
                $predikat_huruf_simpan = null;
            }
        }

        $cek = $conn->query("SELECT id FROM nilai WHERE id_siswa='$id_siswa' AND id_mapel='$post_id_mapel' AND id_tahun_ajaran='$id_tahun_aktif'");
        if($cek->num_rows > 0){
            $stmt_upd = $conn->prepare("UPDATE nilai SET nilai_t=?, nilai_p=?, nilai_k=?, daftar_nilai_cp=?, nilai_sikap=?, deskripsi_cp=?, predikat_huruf=?, catatan_guru_manual=? WHERE id_siswa=? AND id_mapel=? AND id_tahun_ajaran=?");
            $stmt_upd->bind_param("iiisssssiii", $rata_tp_simpan, $sts, $sas, $json_tp, $sikap, $deskripsi_final, $predikat_huruf_simpan, $catatan_manual_simpan, $id_siswa, $post_id_mapel, $id_tahun_aktif);
            $stmt_upd->execute();
        } else {
            try {
                $stmt_ins = $conn->prepare("INSERT INTO nilai (id_siswa, id_kelas, id_mapel, id_tahun_ajaran, nilai_t, nilai_p, nilai_k, daftar_nilai_cp, nilai_sikap, deskripsi_cp, predikat_huruf, catatan_guru_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("iiiiiissssss", $id_siswa, $post_id_kelas, $post_id_mapel, $id_tahun_aktif, $rata_tp_simpan, $sts, $sas, $json_tp, $sikap, $deskripsi_final, $predikat_huruf_simpan, $catatan_manual_simpan);
                $stmt_ins->execute();
            } catch (Exception $e) {
                // Fallback kalau tabel nilai gak punya kolom id_kelas
                $stmt_ins = $conn->prepare("INSERT INTO nilai (id_siswa, id_mapel, id_tahun_ajaran, nilai_t, nilai_p, nilai_k, daftar_nilai_cp, nilai_sikap, deskripsi_cp, predikat_huruf, catatan_guru_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("iiiiissssss", $id_siswa, $post_id_mapel, $id_tahun_aktif, $rata_tp_simpan, $sts, $sas, $json_tp, $sikap, $deskripsi_final, $predikat_huruf_simpan, $catatan_manual_simpan);
                $stmt_ins->execute();
            }
        }
    }
    echo "<script>alert('Berhasil Simpan!'); window.location='?page=input_nilai&id_kelas=$post_id_kelas&mapel_nama=".urlencode($nama_mapel_post)."';</script>";
}

// Ambil Template CP buat difilter di layar (biar ga double di textarea)
// 🩹 FIX: sama seperti di atas, cocokin ke nama_mapel_dasar + kode_jurusan (bukan id_guru)
// biar konsisten sama Mode Kompak di setting_cp.php.
$tmplDisp = null;
if($id_mapel && $id_kelas){
    $qKelasJurusanDisp = $conn->query("SELECT kode_jurusan FROM kelas WHERE id='$id_kelas' LIMIT 1");
    $kode_jurusan_disp = ($qKelasJurusanDisp && $qKelasJurusanDisp->num_rows > 0) ? trim($qKelasJurusanDisp->fetch_assoc()['kode_jurusan']) : '';
    if(empty($kode_jurusan_disp)) $kode_jurusan_disp = 'Umum';
    $kode_jurusan_disp_safe = mysqli_real_escape_string($conn, $kode_jurusan_disp);
    $nama_mapel_disp_safe = mysqli_real_escape_string($conn, $selected_mapel_nama);

    $qTmplDisp = $conn->query("SELECT * FROM setting_pernyataan WHERE nama_mapel_dasar='$nama_mapel_disp_safe' AND kode_jurusan='$kode_jurusan_disp_safe' LIMIT 1");
    $tmplDisp = ($qTmplDisp && $qTmplDisp->num_rows > 0) ? $qTmplDisp->fetch_assoc() : null;
}
?>

<style>
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .table-ledger { font-size: 10px; border: 1.5px solid #444 !important; }
    .table-ledger th { background-color: #e2efda !important; border: 1px solid #444 !important; vertical-align: middle; }
    .table-pkl th { background-color: #cce5ff !important; }
    .input-tp { width: 35px; border: 1px solid #999; text-align: center; font-size: 10px; border-radius: 3px; }
    .input-tp-pkl { width: 50px; padding: 5px; font-size: 11px; }
    .input-deskripsi { width: 100%; border: 1px solid #ccc; font-size: 11px; padding: 5px; min-height: 50px; }
    .auto-box { font-size: 8px; color: #107c41; font-weight: bold; display: block; margin-bottom: 2px; }
    input:disabled, textarea:disabled {
        background: #f1f5f9 !important;
        color: #64748b !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
    }
    .lock-info-soft {
        background: #fff7df;
        border: 1px solid #f2d48c;
        color: #7a5200;
        border-radius: 14px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 16px;
    }
    .lock-info-danger {
        background: #ffe1e7;
        border: 1px solid #f3a5b4;
        color: #9b1c31;
        border-radius: 14px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 16px;
    }
</style>

<div class="header-pastel" style="background:white; padding:20px; border-radius:12px; border-left:5px solid #107c41; margin-bottom:20px;">
    <h5 class="fw-bold text-success"><i class="fas fa-edit me-2"></i>Input Nilai & Pernyataan CP</h5>
    <form method="GET" class="row g-2">
        <input type="hidden" name="page" value="input_nilai">
        <div class="col-md-5">
            <select name="mapel_nama" class="form-select form-select-sm" required onchange="this.form.submit()">
                <option value="">-- Pilih Mapel --</option>
                <?php foreach(array_keys($mapel_data) as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= ($selected_mapel_nama == $m)?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select name="id_kelas" class="form-select form-select-sm" required onchange="this.form.submit()">
                <option value="">-- Pilih Kelas --</option>
                <?php if($selected_mapel_nama && isset($mapel_data[$selected_mapel_nama])): 
                    foreach($mapel_data[$selected_mapel_nama] as $ik => $infoKelas): 
                        $nk = $infoKelas['nama_kelas']; 
                        echo "<option value='$ik' ".($id_kelas == $ik ? 'selected' : '').">$nk</option>"; 
                    endforeach; 
                endif; ?>
            </select>
        </div>
    </form>
</div>

<?php if($id_kelas && $id_mapel): ?>

<?php if($is_uas_dikunci == 1): ?>
    <div class="lock-info-danger">
        <i class="fas fa-lock me-1"></i>
        Rapor UAS sudah dikunci. Seluruh nilai pada mapel ini hanya bisa dilihat dan tidak dapat diedit.
    </div>
<?php elseif($is_uts_dikunci == 1): ?>
    <div class="lock-info-soft">
        <i class="fas fa-lock me-1"></i>
        Rapor UTS sudah dikunci. TP yang sudah masuk UTS final, STS, Sikap, dan Catatan/CP dikunci. TP tambahan dan nilai SAS masih bisa diisi untuk UAS.
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="id_kelas_post" value="<?= $id_kelas ?>">
    <input type="hidden" name="id_mapel_post" value="<?= $id_mapel ?>">
    <input type="hidden" name="nama_mapel_post" value="<?= htmlspecialchars($selected_mapel_nama) ?>">
    <input type="hidden" name="is_pkl_post" value="<?= $is_mapel_pkl ? 1 : 0 ?>">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        
        <?php if($is_mapel_pkl): ?>
            <div class="bg-primary text-white p-2 text-center small fw-bold">
                <i class="fas fa-industry me-1"></i> MODE INPUT KHUSUS MATA PELAJARAN PKL
            </div>
            <div class="table-responsive p-3">
                <table class="table table-bordered table-ledger table-pkl mb-0 text-center">
                    <thead>
                        <tr>
                            <th width="3%" rowspan="2">NO</th>
                            <th width="15%" rowspan="2" class="text-start">NAMA SISWA</th>
                            <th colspan="4">SKOR TUJUAN PEMBELAJARAN (TP) PKL</th>
                            <th width="40%" rowspan="2">DESKRIPSI KETERCAPAIAN UMUM<br><small class="fw-normal">(Tampil di Cetak Rapor PKL)</small></th>
                        </tr>
                        <tr style="font-size: 8px;">
                            <th>TP 1 (Soft Skill)</th><th>TP 2 (K3LH & POS)</th><th>TP 3 (Teknis)</th><th>TP 4 (Alur Bisnis)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $qS = $conn->query("SELECT s.id, s.nama_siswa, n.daftar_nilai_cp, n.deskripsi_cp 
                                           FROM riwayat_kelas rk
                                           JOIN siswa s ON s.id = rk.id_siswa
                                           LEFT JOIN nilai n ON s.id = n.id_siswa AND n.id_mapel = '$id_mapel' AND n.id_tahun_ajaran = '$id_tahun_aktif'
                                           WHERE rk.id_kelas = '$id_kelas' AND rk.id_tahun_ajaran = '$id_tahun_aktif' ORDER BY s.nama_siswa ASC");
                        while($s = $qS->fetch_assoc()): 
                            $tp_db = json_decode($s['daftar_nilai_cp'] ?? '[]', true);
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="text-start fw-bold" style="font-size: 11px;"><?= strtoupper($s['nama_siswa']) ?></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][tp][0]" class="input-tp input-tp-pkl" value="<?= $tp_db[0] ?? '' ?>" min="0" max="100" placeholder="0-100"></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][tp][1]" class="input-tp input-tp-pkl" value="<?= $tp_db[1] ?? '' ?>" min="0" max="100" placeholder="0-100"></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][tp][2]" class="input-tp input-tp-pkl" value="<?= $tp_db[2] ?? '' ?>" min="0" max="100" placeholder="0-100"></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][tp][3]" class="input-tp input-tp-pkl" value="<?= $tp_db[3] ?? '' ?>" min="0" max="100" placeholder="0-100"></td>
                            <td class="p-2"><textarea name="nilai[<?= $s['id'] ?>][deskripsi]" class="input-deskripsi"><?= $s['deskripsi_cp'] ?></textarea></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-ledger mb-0 text-center">
                    <thead>
                        <tr>
                            <th rowspan="2">NO</th>
                            <th rowspan="2">NAMA SISWA</th>
                            <th colspan="9">TP</th>
                            <th rowspan="2">SIKAP</th>
                            <th colspan="2">SUMATIF</th>
                            <th rowspan="2" width="250">PERNYATAAN CP / CATATAN GURU</th>
                        </tr>
                        <tr>
                            <?php for($i=1; $i<=9; $i++) echo "<th>$i</th>"; ?>
                            <th>STS</th><th>SAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $qS = $conn->query("SELECT s.id, s.nama_siswa, n.nilai_t, n.nilai_p, n.nilai_k, n.nilai_sikap, n.daftar_nilai_cp, n.deskripsi_cp 
                                           FROM riwayat_kelas rk
                                           JOIN siswa s ON s.id = rk.id_siswa
                                           LEFT JOIN nilai n ON s.id = n.id_siswa AND n.id_mapel = '$id_mapel' AND n.id_tahun_ajaran = '$id_tahun_aktif'
                                           WHERE rk.id_kelas = '$id_kelas' AND rk.id_tahun_ajaran = '$id_tahun_aktif' ORDER BY s.nama_siswa ASC");
                        while($s = $qS->fetch_assoc()): 
                            $tp_db = json_decode($s['daftar_nilai_cp'] ?? '[]', true);
                            
                            // FILTER ANTI-DOUBLE TEXT TAMPILAN
                            $desk_bersih = $s['deskripsi_cp'] ?? '';
                            if($tmplDisp) {
                                $arr_tmpl = [$tmplDisp['template_a'], $tmplDisp['template_b'], $tmplDisp['template_c'], $tmplDisp['template_d']];
                                foreach($arr_tmpl as $t) {
                                    if(!empty($t)) {
                                        $desk_bersih = str_ireplace($t . ". ", "", $desk_bersih);
                                        $desk_bersih = str_ireplace($t . ".", "", $desk_bersih);
                                        $desk_bersih = str_ireplace($t, "", $desk_bersih);
                                    }
                                }
                            }
                            // Sapu bersih kalimat default bawaan & doubleannya
                            $desk_bersih = preg_replace('/Menunjukkan penguasaan( yang sangat| cukup| kurang)? baik dalam [^.]+\.\s*/i', '', $desk_bersih);
                            $desk_bersih = preg_replace('/Menunjukkan penguasaan( yang sangat| cukup| kurang)? baik dalam [^.]+/i', '', $desk_bersih);
                            $desk_bersih = trim($desk_bersih, " .");
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="text-start fw-bold small"><?= strtoupper($s['nama_siswa']) ?></td>
                            <?php for($i=0; $i<9; $i++): 
                                $v = $tp_db[$i] ?? ''; 
                                // Jika UTS sudah dikunci, hanya TP yang benar-benar masuk hitungan UTS final yang dikunci.
                                // TP tambahan setelah UTS dikunci tetap bisa diedit untuk UAS.
                                $jumlah_tp_kunci_tampilan = ($is_uts_dikunci == 1) ? jumlah_tp_uts_terkunci($tp_db, (int)($s['nilai_t'] ?? 0)) : 0;
                                $lock_tp_cell = ($is_uas_dikunci == 1 || ($is_uts_dikunci == 1 && $i < $jumlah_tp_kunci_tampilan));
                            ?>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][tp][<?= $i ?>]" class="input-tp" min="0" max="100" value="<?= $v ?>" <?= $lock_tp_cell ? 'readonly disabled' : '' ?>></td>
                            <?php endfor; ?>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][sikap]" class="input-tp" min="0" max="100" value="<?= $s['nilai_sikap'] ?>" <?= ($is_uts_dikunci == 1 || $is_uas_dikunci == 1) ? 'readonly disabled' : '' ?>></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][sts]" class="input-tp text-primary fw-bold" min="0" max="100" value="<?= $s['nilai_p'] ?>" <?= ($is_uts_dikunci == 1 || $is_uas_dikunci == 1) ? 'readonly disabled' : '' ?>></td>
                            <td><input type="number" name="nilai[<?= $s['id'] ?>][sas]" class="input-tp text-success fw-bold" min="0" max="100" value="<?= $s['nilai_k'] ?>" <?= ($is_uas_dikunci == 1) ? 'readonly disabled' : '' ?>></td>
                            <td class="text-start p-2">
                                <label class="auto-box">
                                    <input type="checkbox" name="nilai[<?= $s['id'] ?>][use_auto]" checked <?= ($is_uts_dikunci == 1 || $is_uas_dikunci == 1) ? 'disabled' : '' ?>> Aktifkan Autofill Sistem
                                </label>
                                <textarea name="nilai[<?= $s['id'] ?>][deskripsi]" class="input-deskripsi" placeholder="Tambahkan catatan khusus..." <?= ($is_uts_dikunci == 1 || $is_uas_dikunci == 1) ? 'readonly disabled' : '' ?>><?= htmlspecialchars($desk_bersih) ?></textarea>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <div class="p-3 bg-light text-end border-top">
            <?php if($is_uas_dikunci == 1): ?>
                <button type="button" class="btn btn-secondary px-5 fw-bold rounded-pill" disabled>
                    <i class="fas fa-lock me-1"></i> DATA SUDAH DIKUNCI
                </button>
            <?php elseif($is_uts_dikunci == 1): ?>
                <button type="submit" name="simpan_ledger" class="btn btn-success px-5 fw-bold rounded-pill">
                    SIMPAN DATA UAS / SAS
                </button>
            <?php else: ?>
                <button type="submit" name="simpan_ledger" class="btn btn-success px-5 fw-bold rounded-pill">
                    SIMPAN DATA
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php endif; ?>