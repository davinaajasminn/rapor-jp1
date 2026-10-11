<?php
/*
    Helper semester rapor.
    Bisa dipakai di file cetak rapor kalau diperlukan.
*/
function semester_aktif_sistem($conn){
    $q = $conn->query("SELECT tahun, semester FROM tahun_ajaran WHERE status='Aktif' LIMIT 1");
    if($q && $q->num_rows > 0){
        return $q->fetch_assoc();
    }
    return ['tahun' => date('Y').'/'.(date('Y') + 1), 'semester' => 'Ganjil'];
}

function format_semester_kelas($tingkat, $semester){
    $tingkat = strtoupper(trim((string)$tingkat));
    $semester = strtoupper(trim((string)$semester));
    $is_genap = ($semester == '2' || $semester == '4' || $semester == '6' || strpos($semester, 'GENAP') !== false);

    if($tingkat == 'XII') return $is_genap ? '6 (GENAP)' : '5 (GANJIL)';
    if($tingkat == 'XI') return $is_genap ? '4 (GENAP)' : '3 (GANJIL)';
    return $is_genap ? '2 (GENAP)' : '1 (GANJIL)';
}
?>
