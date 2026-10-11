<?php
$nip = $_SESSION['guru']['nip'];

// Ambil data guru saat ini
$qGuru = $conn->query("SELECT * FROM guru WHERE nip='$nip' LIMIT 1");
$dGuru = $qGuru->fetch_assoc();
$foto_sekarang = $dGuru['foto_profil'] ?? '';
$ttd_sekarang = $dGuru['ttd_digital'] ?? '';

// --- PROSES SIMPAN PROFIL & PASSWORD ---

// --- PROSES SIMPAN TTD DIGITAL ---
if(isset($_POST['simpan_ttd'])){
    $ttd_digital = $_POST['ttd_digital'] ?? '';

    if(!empty($ttd_digital)){
        $ttd_safe = mysqli_real_escape_string($conn, $ttd_digital);
        $conn->query("UPDATE guru SET ttd_digital='$ttd_safe' WHERE nip='$nip'");
        echo "<script>alert('Tanda tangan digital berhasil disimpan!'); window.location='?page=profil';</script>";
        exit;
    } else {
        echo "<script>alert('Silakan tanda tangan dulu di kotak yang tersedia.'); window.location='?page=profil';</script>";
        exit;
    }
}

// --- PROSES HAPUS TTD DIGITAL ---
if(isset($_POST['hapus_ttd'])){
    $conn->query("UPDATE guru SET ttd_digital=NULL WHERE nip='$nip'");
    echo "<script>alert('Tanda tangan digital berhasil dihapus!'); window.location='?page=profil';</script>";
    exit;
}

if(isset($_POST['simpan_profil'])){
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $pass_ulang = $_POST['pass_ulang'];

    $update_pass_sql = "";
    // Logika Password
    if(!empty($pass_lama) || !empty($pass_baru)){
        if($pass_lama != $dGuru['password']){
            echo "<script>alert('Gagal: Password lama salah!');</script>";
        } elseif($pass_baru != $pass_ulang){
            echo "<script>alert('Gagal: Konfirmasi password baru tidak cocok!');</script>";
        } else {
            $update_pass_sql = "password='$pass_baru'";
        }
    }

    // Logika Upload Foto
    $update_foto_sql = "";
    if(isset($_FILES['foto']['name']) && $_FILES['foto']['name'] != ''){
        $nama_file = time().'_'.str_replace(" ", "_", $_FILES['foto']['name']);
        $tmp_file = $_FILES['foto']['tmp_name'];
        
        // Bikin folder otomatis kalau belum ada
        $dir = '../assets/uploads/';
        if(!is_dir($dir)) mkdir($dir, 0777, true); 
        
        if(move_uploaded_file($tmp_file, $dir.$nama_file)){
            $update_foto_sql = "foto_profil='$nama_file'";
        }
    }

    // Eksekusi Update ke Database
    if($update_pass_sql != "" || $update_foto_sql != ""){
        $set_query = [];
        if($update_pass_sql) $set_query[] = $update_pass_sql;
        if($update_foto_sql) $set_query[] = $update_foto_sql;
        
        $set_string = implode(", ", $set_query);
        $conn->query("UPDATE guru SET $set_string WHERE nip='$nip'");
        
        echo "<script>alert('Profil berhasil diupdate!'); window.location='?page=profil';</script>";
    }
}

// Tampilan UI-Avatars kalo belum ada foto
$avatar_default = "https://ui-avatars.com/api/?name=".urlencode($dGuru['nama_guru'])."&background=random&color=fff&rounded=true&bold=true&size=150";
$foto_tampil = (!empty($foto_sekarang) && file_exists("../assets/uploads/".$foto_sekarang)) ? "../assets/uploads/".$foto_sekarang : $avatar_default;
?>

<style>

    .ttd-canvas-box {
        border: 2px dashed #AECDEE;
        border-radius: 16px;
        background: #ffffff;
        width: 100%;
        height: 150px;
        touch-action: none;
        cursor: crosshair;
    }

    .btn-ttd-soft {
        background: #D8E9FA !important;
        color: #0d385e !important;
        border: 1px solid #AECDEE !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        padding: 8px 18px !important;
    }

    .btn-ttd-clear {
        background: #F8D7DA !important;
        color: #842029 !important;
        border: 1px solid #F1AEB5 !important;
        border-radius: 50px !important;
        font-weight: 800 !important;
        padding: 8px 18px !important;
    }

    .ttd-preview-img {
        max-width: 180px;
        max-height: 80px;
        object-fit: contain;
        border: 1px solid #e5edf5;
        border-radius: 12px;
        background: #fff;
        padding: 6px;
    }

</style>

<div class="row justify-content-center mt-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold mb-4 text-center text-primary"><i class="fas fa-user-shield me-2"></i>Pengaturan Profil</h5>
            
            <form method="POST" enctype="multipart/form-data">
                
                <div class="text-center mb-4 pb-4 border-bottom">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= $foto_tampil ?>" class="rounded-circle shadow-sm" width="120" height="120" style="object-fit: cover; border: 3px solid #e9ecef;">
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-muted">Ganti Foto Profil</label>
                        <input type="file" name="foto" class="form-control form-control-sm w-75 mx-auto" accept="image/*">
                        <small class="text-muted" style="font-size: 10px;">Format: JPG/PNG. Biarkan kosong jika tidak ingin mengubah foto.</small>
                    </div>
                </div>


                <div class="mb-4 pb-4 border-bottom">
                    <h6 class="fw-bold text-muted mb-3">
                        <i class="fas fa-pen-nib me-2"></i>Tanda Tangan Digital
                    </h6>

                    <div class="text-center mb-3">
                        <?php if(!empty($ttd_sekarang)): ?>
                            <div class="mb-2 small fw-bold text-muted">TTD tersimpan saat ini:</div>
                            <img src="<?= $ttd_sekarang ?>" class="ttd-preview-img mb-2" alt="TTD Digital">
                        <?php else: ?>
                            <small class="text-muted">Belum ada tanda tangan digital.</small>
                        <?php endif; ?>
                    </div>

                    <canvas id="canvasTtd" class="ttd-canvas-box"></canvas>
                    <input type="hidden" name="ttd_digital" id="ttdDigital">

                    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                        <button type="button" class="btn btn-ttd-clear" onclick="clearTtd()">
                            <i class="fas fa-eraser me-1"></i>Bersihkan
                        </button>

                        <button type="submit" name="simpan_ttd" class="btn btn-ttd-soft" onclick="return simpanTtd()">
                            <i class="fas fa-save me-1"></i>Simpan TTD
                        </button>

                        <?php if(!empty($ttd_sekarang)): ?>
                            <button type="submit" name="hapus_ttd" class="btn btn-ttd-clear" onclick="return confirm('Hapus tanda tangan digital?')">
                                <i class="fas fa-trash-alt me-1"></i>Hapus TTD
                            </button>
                        <?php endif; ?>
                    </div>

                    <small class="text-muted d-block text-center mt-2">
                        Tanda tangan langsung di kotak putih menggunakan mouse/touchpad.
                    </small>
                </div>

                <h6 class="fw-bold text-muted mb-3"><i class="fas fa-lock me-2"></i>Ganti Password</h6>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password Lama</label>
                    <input type="password" name="pass_lama" class="form-control bg-light" placeholder="Masukkan password saat ini...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password Baru</label>
                    <input type="password" name="pass_baru" class="form-control" placeholder="Masukkan password baru...">
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Ulangi Password Baru</label>
                    <input type="password" name="pass_ulang" class="form-control" placeholder="Ketik ulang password baru...">
                </div>

                <button type="submit" name="simpan_profil" class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm" style="height: 45px;">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const canvasTtd = document.getElementById('canvasTtd');
const ctxTtd = canvasTtd ? canvasTtd.getContext('2d') : null;
let drawingTtd = false;
let hasDrawTtd = false;

function resizeCanvasTtd() {
    if (!canvasTtd || !ctxTtd) return;
    const rect = canvasTtd.getBoundingClientRect();
    canvasTtd.width = rect.width;
    canvasTtd.height = rect.height;
    ctxTtd.lineWidth = 2.2;
    ctxTtd.lineCap = 'round';
    ctxTtd.lineJoin = 'round';
    ctxTtd.strokeStyle = '#111111';
}

function getPosTtd(e) {
    const rect = canvasTtd.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
}

function startTtd(e) {
    if (!ctxTtd) return;
    e.preventDefault();
    drawingTtd = true;
    hasDrawTtd = true;
    const pos = getPosTtd(e);
    ctxTtd.beginPath();
    ctxTtd.moveTo(pos.x, pos.y);
}

function drawTtd(e) {
    if (!drawingTtd || !ctxTtd) return;
    e.preventDefault();
    const pos = getPosTtd(e);
    ctxTtd.lineTo(pos.x, pos.y);
    ctxTtd.stroke();
}

function endTtd(e) {
    if (!ctxTtd) return;
    e.preventDefault();
    drawingTtd = false;
}

function clearTtd() {
    if (!canvasTtd || !ctxTtd) return;
    ctxTtd.clearRect(0, 0, canvasTtd.width, canvasTtd.height);
    hasDrawTtd = false;
    document.getElementById('ttdDigital').value = '';
}

function simpanTtd() {
    if (!canvasTtd) return false;
    if (!hasDrawTtd) {
        alert('Silakan tanda tangan dulu di kotak putih.');
        return false;
    }
    document.getElementById('ttdDigital').value = canvasTtd.toDataURL('image/png');
    return true;
}

if (canvasTtd) {
    resizeCanvasTtd();
    window.addEventListener('resize', resizeCanvasTtd);

    canvasTtd.addEventListener('mousedown', startTtd);
    canvasTtd.addEventListener('mousemove', drawTtd);
    canvasTtd.addEventListener('mouseup', endTtd);
    canvasTtd.addEventListener('mouseleave', endTtd);

    canvasTtd.addEventListener('touchstart', startTtd, { passive: false });
    canvasTtd.addEventListener('touchmove', drawTtd, { passive: false });
    canvasTtd.addEventListener('touchend', endTtd, { passive: false });
}
</script>
