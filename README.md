# Sistem Informasi Pengolahan Nilai Berbasis Web — SMK Jakarta Pusat 1

Aplikasi web untuk mengolah nilai dan mencetak rapor di SMK Jakarta Pusat 1. Dibangun sebagai proyek skripsi S1 Sistem Informasi, Universitas Pamulang, dengan metode pengembangan **Agile**.

**Judul skripsi:** *Perancangan Sistem Informasi Pengolahan Nilai Berbasis Website dengan Metode Agile, Studi Kasus SMK Jakarta Pusat 1*

## Teknologi

| Bagian | Teknologi |
|---|---|
| Bahasa | PHP native (tanpa framework) |
| Database | MySQL (`db_pengolahan_nilai`) |
| Antarmuka | HTML, CSS, Bootstrap, jQuery |
| Grafik | Chart.js |
| Import Excel | SimpleXLSX |
| Server lokal | XAMPP (Apache + MySQL) |
| Pengujian | Black Box Testing dan Selenium + pytest (Python) |

## Peran dan Fitur

Sistem memiliki empat peran dengan hak akses berbeda.

**Admin**
- Kelola data siswa, guru, kelas, mata pelajaran, dan tahun ajaran
- Import data siswa dari file Excel (template tersedia di `admin/template_import_siswa.xlsx`)
- Pantau progres input nilai tiap kelas
- Kelola riwayat kelas siswa per tahun ajaran

**Guru**
- Input nilai (tujuan pembelajaran, STS, SAS, dan sikap) sesuai mata pelajaran yang diampu
- Input kehadiran
- Ubah profil dan kata sandi

**Kepala Sekolah**
- Pantau kelengkapan nilai dan lakukan validasi
- Lihat ranking dan rekap per kelas
- Cetak rapor

**Siswa**
- Lihat nilai dan rapor pribadi
- Ganti tahun ajaran untuk melihat riwayat
- Ubah profil dan kata sandi

## Cara Menjalankan di Komputer Lokal

1. Pasang [XAMPP](https://www.apachefriends.org/) lalu jalankan **Apache** dan **MySQL**.
2. Salin folder proyek ini ke `C:\xampp\htdocs\rapor_jp1`.
3. Buka `http://localhost/phpmyadmin`, buat database bernama `db_pengolahan_nilai`.
4. Impor file db_pengolahan_nilai.sql lewat tab Import di phpMyAdmin.
5. Buka `http://localhost/rapor_jp1/` di browser.

Konfigurasi koneksi database ada di `config/Database.php` (default XAMPP: host `localhost`, user `root`, tanpa kata sandi).

## Struktur Folder

```
rapor_jp1/
├── admin/      modul admin
├── guru/       modul guru
├── kepsek/     modul kepala sekolah
├── siswa/      modul siswa
├── config/     koneksi database
├── assets/     CSS, JavaScript, dan gambar
├── index.php   halaman login
├── cek_login.php
└── logout.php
```

## Pengujian

- **Black Box Testing** pada fungsi utama sistem.
- **Pengujian otomatis dengan Selenium** (Python + pytest): 130 test case mencakup login, hak akses antar peran, input nilai, nilai batas, pencarian, navigasi menu, dan cetak rapor.

## Catatan dan Rencana Pengembangan

- Kata sandi tersimpan dengan `password_hash` (bcrypt). Pengecekan login masih mendukung kata sandi lama untuk kompatibilitas data.
- Beberapa query masih disusun langsung dari input. Rencana berikutnya: beralih ke *prepared statement* di seluruh modul.
- Validasi nilai di sisi server akan diperketat untuk kasus nilai batas.

## Pengembang

**Davina Jasmin Azzahra**
S1 Sistem Informasi, Universitas Pamulang
[LinkedIn](https://www.linkedin.com/in/davina-jasmin-azzahra-657a76307)
