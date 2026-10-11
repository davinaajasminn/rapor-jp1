<style>
    .btn-soft-blue {
        background: #6FA7DF !important;
        color: #fff !important;
        border: none !important;
        border-radius: 50px !important;
        font-weight: 700 !important;
        box-shadow: 0 5px 12px rgba(111, 167, 223, 0.25) !important;
        transition: none !important;
        transform: none !important;
    }

    .btn-soft-blue:hover,
    .btn-soft-blue:focus,
    .btn-soft-blue:active {
        background: #5E97D2 !important;
        color: #fff !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
    }

    .validasi-banner {
    background: #BFD3EA !important;
    border-radius: 22px;
    color: #0d385e;
    box-shadow: 0 8px 22px rgba(80, 115, 155, 0.12);
    border: 1px solid rgba(255,255,255,0.75);
    padding: 32px 26px;
    }

    .validasi-banner h4 {
        color: #173c76;
        font-weight: 800;
    }

    .validasi-banner p {
        color: #244b70;
        font-weight: 500;
    }

    .validasi-card {
        border: 2px solid transparent !important;
        transition: none !important;
        transform: none !important;
    }

    .validasi-card:hover {
        border-color: #AECDEE !important;
        box-shadow: 0 10px 25px rgba(80, 115, 155, 0.12) !important;
        transform: none !important;
    }

    .validasi-icon-box {
        width: 70px;
        height: 70px;
        background: #D8E9FA;
        color: #0d385e;
    }

    button,
    button:hover,
    button:focus,
    button:active,
    .btn,
    .btn:hover,
    .btn:focus,
    .btn:active {
        transition: none !important;
        transform: none !important;
        animation: none !important;
    }
</style>

<div class="validasi-banner mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-2">
                Pusat Validasi Kelas
            </h4>

            <p class="mb-0">
                Pilih kelas perwalian Anda untuk mengecek kelengkapan nilai dan mencetak rapor.
            </p>
        </div>

        <div class="text-end d-none d-md-block">
            <i class="fas fa-users fa-3x" style="opacity:0.22;"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php if(empty($list_kelas_wali)): ?>
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm rounded-4 text-center p-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold">Belum Ada Kelas Perwalian</h5>
                <p class="mb-0">Anda belum ditugaskan sebagai Wali Kelas untuk kelas manapun di sistem.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach($list_kelas_wali as $wali): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4 validasi-card bg-white">
                <div class="mx-auto rounded-circle d-flex justify-content-center align-items-center mb-3 validasi-icon-box">
                    <i class="fas fa-school fa-2x"></i>
                </div>

                <h5 class="fw-bold text-dark mb-1"><?= $wali['nama_kelas'] ?></h5>

                <p class="text-muted small mb-4">
                    Kelola Ledger Rapor & Cek Validasi Siswa
                </p>

                <a href="?page=wali_siswa&id_kelas=<?= $wali['id'] ?>" class="btn btn-soft-blue rounded-pill w-100 fw-bold shadow-sm">
                    Buka Kelas <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>