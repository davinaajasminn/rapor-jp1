<?php
$jurusan = urlencode($_GET['jurusan'] ?? '');
$tingkat = urlencode($_GET['tingkat'] ?? 'XII');
$id_tahun_ajaran = urlencode($_GET['id_tahun_ajaran'] ?? '');

// Arahkan ke halaman pembuat ZIP berbasis browser.
// Outputnya: 1 ZIP -> folder per kelas -> PDF per siswa.
header("Location: cetak_rapor_pkl_multi_print.php?jurusan={$jurusan}&tingkat={$tingkat}&id_tahun_ajaran={$id_tahun_ajaran}");
exit;