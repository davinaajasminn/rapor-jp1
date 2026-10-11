-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 11 Okt 2026 pada 04.46
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_pengolahan_nilai`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id_absen` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `sakit` int(3) DEFAULT 0,
  `izin` int(3) DEFAULT 0,
  `alpha` int(11) DEFAULT 0,
  `semester` varchar(20) DEFAULT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dokumen_pendukung` text DEFAULT NULL,
  `status_validasi` int(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_pkl`
--

CREATE TABLE `data_pkl` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `id_guru_pembimbing` int(11) DEFAULT NULL,
  `mitra_dudi` varchar(255) DEFAULT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `nama_instruktur` varchar(150) DEFAULT NULL,
  `lamanya` varchar(50) DEFAULT NULL,
  `nilai_pkl` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tp1_skor` int(11) DEFAULT NULL,
  `tp1_deskripsi` text DEFAULT NULL,
  `tp2_skor` int(11) DEFAULT NULL,
  `tp2_deskripsi` text DEFAULT NULL,
  `tp3_skor` int(11) DEFAULT NULL,
  `tp3_deskripsi` text DEFAULT NULL,
  `tp4_skor` int(11) DEFAULT NULL,
  `tp4_deskripsi` text DEFAULT NULL,
  `catatan_pkl` text DEFAULT NULL,
  `sakit` int(11) DEFAULT 0,
  `izin` int(11) DEFAULT 0,
  `tanpa_keterangan` int(11) DEFAULT 0,
  `status_validasi_pkl` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_guru` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tgl_lahir` date NOT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `ttd_digital` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_mengajar`
--

CREATE TABLE `guru_mengajar` (
  `id` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `identitas_sekolah`
--

CREATE TABLE `identitas_sekolah` (
  `id` int(1) NOT NULL,
  `nama_sekolah` varchar(100) DEFAULT NULL,
  `npsn` varchar(20) DEFAULT NULL,
  `alamat_sekolah` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `kelurahan` varchar(50) DEFAULT NULL,
  `kecamatan` varchar(50) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL,
  `provinsi` varchar(50) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nama_kepsek` varchar(100) DEFAULT NULL,
  `nip_kepsek` varchar(30) DEFAULT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `total_hari_efektif` int(3) DEFAULT 110,
  `rapor_dikunci` tinyint(1) DEFAULT 0,
  `rapor_uts_dikunci` tinyint(1) DEFAULT 0,
  `rapor_uas_dikunci` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jurusan`
--

CREATE TABLE `jurusan` (
  `kode_jurusan` varchar(10) NOT NULL,
  `nama_jurusan` varchar(100) DEFAULT NULL,
  `logo_icon` varchar(50) DEFAULT 'fa-laptop'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(50) DEFAULT NULL,
  `tingkat` enum('X','XI','XII') DEFAULT NULL,
  `fase` enum('E','F','G') DEFAULT NULL,
  `kode_jurusan` varchar(10) DEFAULT NULL,
  `id_wali_kelas` int(11) DEFAULT NULL,
  `jumlah_siswa` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kepala_sekolah`
--

CREATE TABLE `kepala_sekolah` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `ttd_digital` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `mapel`
--

CREATE TABLE `mapel` (
  `id` int(11) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `kkm` int(3) DEFAULT 75,
  `kelompok` varchar(20) DEFAULT NULL,
  `tingkat` enum('X','XI','XII') DEFAULT NULL,
  `kode_jurusan` varchar(50) DEFAULT NULL,
  `fase` enum('E','F','G') DEFAULT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_ekskul`
--

CREATE TABLE `master_ekskul` (
  `id` int(11) NOT NULL,
  `nama_ekskul` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai`
--

CREATE TABLE `nilai` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `nilai_t` int(3) DEFAULT 0,
  `nilai_p` int(11) DEFAULT 0,
  `nilai_sts` int(11) DEFAULT 0,
  `nilai_sas` int(11) DEFAULT 0,
  `daftar_nilai_cp` text DEFAULT NULL,
  `nilai_sikap` int(11) DEFAULT 0,
  `nilai_k` int(11) DEFAULT 0,
  `is_validated` tinyint(1) DEFAULT 0,
  `status_validasi` int(1) DEFAULT 0,
  `deskripsi_cp` text DEFAULT NULL,
  `predikat_huruf` varchar(1) DEFAULT NULL,
  `catatan_guru_manual` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai_backup_mismatch`
--

CREATE TABLE `nilai_backup_mismatch` (
  `id` int(11) NOT NULL DEFAULT 0,
  `id_siswa` int(11) NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) DEFAULT NULL,
  `nilai_t` int(3) DEFAULT 0,
  `nilai_p` int(11) DEFAULT 0,
  `nilai_sts` int(11) DEFAULT 0,
  `nilai_sas` int(11) DEFAULT 0,
  `daftar_nilai_cp` text DEFAULT NULL,
  `nilai_sikap` int(11) DEFAULT 0,
  `nilai_k` int(11) DEFAULT 0,
  `is_validated` tinyint(1) DEFAULT 0,
  `status_validasi` int(1) DEFAULT 0,
  `deskripsi_cp` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai_ekskul`
--

CREATE TABLE `nilai_ekskul` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) NOT NULL,
  `ekskul_1` int(11) DEFAULT NULL,
  `nilai_1` varchar(20) DEFAULT NULL,
  `ekskul_2` int(11) DEFAULT NULL,
  `nilai_2` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `pengirim_role` varchar(20) NOT NULL,
  `pengirim_id` int(11) NOT NULL,
  `nama_pengirim` varchar(100) NOT NULL,
  `penerima_role` varchar(20) NOT NULL,
  `penerima_id` int(11) NOT NULL,
  `pesan` text NOT NULL,
  `link_tujuan` varchar(255) DEFAULT '#',
  `status_baca` tinyint(1) DEFAULT 0,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengecualian_siswa`
--

CREATE TABLE `pengecualian_siswa` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pkl_pembimbing_aktif`
--

CREATE TABLE `pkl_pembimbing_aktif` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) NOT NULL,
  `id_guru_pembimbing` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_kelas`
--

CREATE TABLE `riwayat_kelas` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `id_tahun_ajaran` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_kelas_backup`
--

CREATE TABLE `riwayat_kelas_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `id_siswa` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `id_tahun_ajaran` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_wali_kelas`
--

CREATE TABLE `riwayat_wali_kelas` (
  `id` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `tahun` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `setting_pernyataan`
--

CREATE TABLE `setting_pernyataan` (
  `id` int(11) NOT NULL,
  `nama_mapel_dasar` varchar(150) DEFAULT NULL,
  `id_guru` int(11) DEFAULT NULL,
  `template_a` text DEFAULT NULL,
  `template_b` text DEFAULT NULL,
  `template_c` text DEFAULT NULL,
  `template_d` text DEFAULT NULL,
  `template_e` text DEFAULT NULL,
  `template_f` text DEFAULT NULL,
  `kode_jurusan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nisn` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `tgl_lahir` date NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tahun_ajaran`
--

CREATE TABLE `tahun_ajaran` (
  `id` int(11) NOT NULL,
  `tahun` varchar(20) DEFAULT NULL,
  `semester` enum('Ganjil','Genap') DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') DEFAULT 'Tidak Aktif',
  `id_admin` int(11) DEFAULT NULL,
  `hari_efektif` int(3) DEFAULT 110,
  `rapor_uts_dikunci` tinyint(1) DEFAULT 0,
  `rapor_uas_dikunci` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `template_catatan`
--

CREATE TABLE `template_catatan` (
  `id` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `isi_template` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id_absen`),
  ADD KEY `fk_absen_siswa` (`id_siswa`),
  ADD KEY `fk_absen_admin` (`id_admin`);

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `data_pkl`
--
ALTER TABLE `data_pkl`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pkl_siswa` (`id_siswa`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`);

--
-- Indeks untuk tabel `guru_mengajar`
--
ALTER TABLE `guru_mengajar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gm_mapel` (`id_mapel`),
  ADD KEY `fk_gm_kelas` (`id_kelas`),
  ADD KEY `idx_gm_guru_ta` (`id_guru`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `identitas_sekolah`
--
ALTER TABLE `identitas_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_identitas_tahun` (`id_tahun_ajaran`);

--
-- Indeks untuk tabel `jurusan`
--
ALTER TABLE `jurusan`
  ADD PRIMARY KEY (`kode_jurusan`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kelas_jurusan` (`kode_jurusan`),
  ADD KEY `fk_kelas_walikelas` (`id_wali_kelas`);

--
-- Indeks untuk tabel `kepala_sekolah`
--
ALTER TABLE `kepala_sekolah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kepsek_tahun` (`id_tahun_ajaran`);

--
-- Indeks untuk tabel `mapel`
--
ALTER TABLE `mapel`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `master_ekskul`
--
ALTER TABLE `master_ekskul`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_nilai` (`id_siswa`,`id_mapel`,`id_tahun_ajaran`),
  ADD KEY `fk_nilai_mapel` (`id_mapel`),
  ADD KEY `idx_nilai_siswa_mapel_ta` (`id_siswa`,`id_mapel`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `nilai_ekskul`
--
ALTER TABLE `nilai_ekskul`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_siswa` (`id_siswa`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengecualian_siswa`
--
ALTER TABLE `pengecualian_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_siswa_ta` (`id_siswa`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `pkl_pembimbing_aktif`
--
ALTER TABLE `pkl_pembimbing_aktif`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_siswa_ta` (`id_siswa`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `riwayat_kelas`
--
ALTER TABLE `riwayat_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_siswa_tahun_ajaran` (`id_siswa`,`id_tahun_ajaran`);

--
-- Indeks untuk tabel `riwayat_wali_kelas`
--
ALTER TABLE `riwayat_wali_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kelas_tahun` (`id_kelas`,`tahun`);

--
-- Indeks untuk tabel `setting_pernyataan`
--
ALTER TABLE `setting_pernyataan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_set_guru` (`id_guru`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD KEY `fk_siswa_kelas` (`id_kelas`);

--
-- Indeks untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tahun_admin` (`id_admin`);

--
-- Indeks untuk tabel `template_catatan`
--
ALTER TABLE `template_catatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_template_guru` (`id_guru`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id_absen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `data_pkl`
--
ALTER TABLE `data_pkl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `guru_mengajar`
--
ALTER TABLE `guru_mengajar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `identitas_sekolah`
--
ALTER TABLE `identitas_sekolah`
  MODIFY `id` int(1) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kepala_sekolah`
--
ALTER TABLE `kepala_sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `mapel`
--
ALTER TABLE `mapel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `master_ekskul`
--
ALTER TABLE `master_ekskul`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `nilai`
--
ALTER TABLE `nilai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `nilai_ekskul`
--
ALTER TABLE `nilai_ekskul`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengecualian_siswa`
--
ALTER TABLE `pengecualian_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pkl_pembimbing_aktif`
--
ALTER TABLE `pkl_pembimbing_aktif`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `riwayat_kelas`
--
ALTER TABLE `riwayat_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `riwayat_wali_kelas`
--
ALTER TABLE `riwayat_wali_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `setting_pernyataan`
--
ALTER TABLE `setting_pernyataan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `template_catatan`
--
ALTER TABLE `template_catatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absen_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absen_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `data_pkl`
--
ALTER TABLE `data_pkl`
  ADD CONSTRAINT `fk_pkl_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru_mengajar`
--
ALTER TABLE `guru_mengajar`
  ADD CONSTRAINT `fk_gm_guru` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gm_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gm_mapel` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `identitas_sekolah`
--
ALTER TABLE `identitas_sekolah`
  ADD CONSTRAINT `fk_identitas_tahun` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `fk_kelas_jurusan` FOREIGN KEY (`kode_jurusan`) REFERENCES `jurusan` (`kode_jurusan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kelas_walikelas` FOREIGN KEY (`id_wali_kelas`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kepala_sekolah`
--
ALTER TABLE `kepala_sekolah`
  ADD CONSTRAINT `fk_kepsek_tahun` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD CONSTRAINT `fk_nilai_mapel` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nilai_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_kelas`
--
ALTER TABLE `riwayat_kelas`
  ADD CONSTRAINT `riwayat_kelas_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `setting_pernyataan`
--
ALTER TABLE `setting_pernyataan`
  ADD CONSTRAINT `fk_set_guru` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  ADD CONSTRAINT `fk_tahun_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `template_catatan`
--
ALTER TABLE `template_catatan`
  ADD CONSTRAINT `fk_template_guru` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
