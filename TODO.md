# TODO: Revisi Project Sistem E-Rapor

## A. Perubahan Teks UI (7 occurrences in 4 files)
- [x] 1. siswa/index.php - Ubah title dan small tag
- [ ] 2. index.php - Ubah title dan sub-judul
- [ ] 3. guru/index.php - Ubah title, small tag, dan welcome text
- [ ] 4. admin/dashboard.php - Ubah welcome text

## B. Perbaikan Bug dan Logic
- [ ] 5. siswa/lihat_nilai.php - Fix query:
  - [ ] a. Add nilai_sikap to SELECT
  - [ ] b. Add active school year filter
  - [ ] c. Fix kode_jurusan filter for PABP (include all or handle PABP specially)
- [ ] 6. siswa/lihat_nilai.php - Add Nilai Sikap display column
- [ ] 7. siswa/lihat_nilai.php - Fix TP average handling (handle NULL properly)
- [ ] 8. Check undefined index issues in other files
