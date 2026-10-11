<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Pengolah Nilai - SMK Jakarta Pusat 1</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            background-image: url('assets/bg-login.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(1px);
            z-index: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.38);
            border-radius: 28px;
            padding: 42px 34px;
            box-shadow: 0 25px 60px rgba(45, 94, 150, 0.22);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            animation: fadeIn 0.7s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-img {
            width: 100px;
            height: auto;
            margin-bottom: 16px;
            filter: drop-shadow(0 8px 12px rgba(13, 56, 94, 0.18));
        }

        .text-donker {
            color: #0d385e;
        }

        .judul-sekolah {
            font-weight: 800;
            margin-bottom: 6px;
            color: #0d385e;
            letter-spacing: 0.2px;
        }

        .sub-judul {
            font-weight: 600;
            margin-bottom: 32px;
            color: #0d385e;
            opacity: 0.78;
            letter-spacing: 1.2px;
            font-size: 14px;
        }

        .form-label {
            color: #0d385e;
            margin-bottom: 8px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.72);
            border: 1.8px solid rgba(255, 255, 255, 0.85);
            border-radius: 14px;
            padding: 13px 15px;
            font-size: 14px;
            color: #0d385e;
            box-shadow: 0 8px 18px rgba(80, 130, 180, 0.08);
            transition: all 0.25s ease;
        }

        .form-control::placeholder {
            color: rgba(13, 56, 94, 0.45);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.92);
            border-color: rgba(13, 56, 94, 0.55);
            box-shadow: 0 0 0 4px rgba(176, 196, 222, 0.35);
            color: #0d385e;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.72);
            border: 1.8px solid rgba(255, 255, 255, 0.85);
            border-left: none;
            border-radius: 0 14px 14px 0;
            cursor: pointer;
            color: rgba(13, 56, 94, 0.65);
            box-shadow: 0 8px 18px rgba(80, 130, 180, 0.08);
            transition: all 0.25s ease;
        }

        .input-group-text:hover {
            color: #0d385e;
            background: rgba(255, 255, 255, 0.92);
        }

        .input-password {
            border-right: none;
            border-radius: 14px 0 0 14px;
        }

        .input-password:focus + .input-group-text {
            border-color: rgba(13, 56, 94, 0.55);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 0 0 4px rgba(176, 196, 222, 0.35);
        }

        .btn-login {
            background: linear-gradient(135deg, #0d385e, #2f6fa7);
            color: white;
            font-weight: 700;
            border-radius: 15px;
            padding: 13px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
            border: none;
            box-shadow: 0 12px 24px rgba(13, 56, 94, 0.28);
            letter-spacing: 0.6px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #082642, #245c8d);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(13, 56, 94, 0.34);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-custom {
            font-size: 13px;
            border-radius: 14px;
            border: none;
            padding: 12px 14px;
            backdrop-filter: blur(8px);
        }

        .alert-danger {
            background: rgba(255, 228, 231, 0.86);
            color: #8a1f2d;
        }

        .alert-success {
            background: rgba(223, 246, 231, 0.86);
            color: #1d6b3b;
        }

        .alert-warning {
            background: rgba(255, 244, 210, 0.88);
            color: #8a6414;
        }

        .forgot-link {
            color: rgba(13, 56, 94, 0.68);
            font-weight: 500;
            transition: all 0.25s ease;
        }

        .forgot-link:hover {
            color: #0d385e;
            text-decoration: underline !important;
        }

        .footer-text {
            color: rgba(13, 56, 94, 0.58);
            font-size: 11px;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 35px 24px;
                border-radius: 24px;
            }

            .logo-img {
                width: 88px;
            }

            .judul-sekolah {
                font-size: 20px;
            }

            .sub-judul {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <?php 
            if(isset($_GET['pesan'])){
                if($_GET['pesan'] == "gagal"){
                    echo "<div class='alert alert-danger alert-custom mb-4'>Login Gagal! Username atau Password salah.</div>";
                } else if($_GET['pesan'] == "logout"){
                    echo "<div class='alert alert-success alert-custom mb-4'>Anda berhasil Logout.</div>";
                } else if($_GET['pesan'] == "belum_login"){
                    echo "<div class='alert alert-warning alert-custom mb-4'>Silahkan Login terlebih dahulu.</div>";
                }
            }
            ?>

            <img src="assets/logo.png" alt="Logo SMK" class="logo-img">
            
            <h4 class="judul-sekolah">SMK Jakarta Pusat 1</h4>
            <p class="sub-judul">Sistem Pengolahan Nilai</p>

            <form action="cek_login.php" method="POST">
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-donker">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                </div>
                
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-donker">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passInput" class="form-control input-password" placeholder="Masukkan password" required>
                        
                        <span class="input-group-text text-muted" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> MASUK
                </button>

                <div class="text-center mt-3">
                    <a href="#" onclick="alert('Lupa password? Silakan hubungi staf Tata Usaha (TU) SMK Jakarta Pusat 1 untuk mengecek atau mereset password Anda.'); return false;" class="text-decoration-none small forgot-link">Lupa Password?</a>
                </div>
            </form>

            <div class="mt-4">
                <small class="footer-text">&copy; 2026 SMK Jakarta Pusat 1. All rights reserved.</small>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var input = document.getElementById("passInput");
            var icon = document.getElementById("eyeIcon");
            
            if (input.type === "password") {
                input.type = "text"; // Ubah jadi teks biar kelihatan
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash"); // Ganti ikon jadi mata dicoret
            } else {
                input.type = "password"; // Balikin jadi titik-titik
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye"); // Balikin ikon mata biasa
            }
        }
    </script>

</body>
</html>