<?php
// --- 🔒 PENGAMAN AKSES LANGSUNG (WAJIB LOGIN ADMIN) ---
if(session_status() === PHP_SESSION_NONE){ session_start(); }
if(!isset($_SESSION['admin']) || $_SESSION['admin']['status'] != 'login'){
    header("location:../index.php?pesan=belum_login"); exit;
}

// Serve file template .xlsx yang sudah disiapkan langsung dari folder admin
// (template_import_siswa.xlsx), bukan generate on-the-fly.
$file_template = __DIR__ . '/template_import_siswa.xlsx';

if(!file_exists($file_template)){
    die("ERROR: File template_import_siswa.xlsx tidak ditemukan di folder admin!");
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_siswa.xlsx"');
header('Content-Length: ' . filesize($file_template));
header('Cache-Control: no-cache, must-revalidate');
readfile($file_template);
exit;