# Sistem E-Voting Pilkades (Pemilihan Kepala Desa)

Aplikasi pemungutan suara elektronik (E-Voting) berbasis web menggunakan **PHP Native** dan **MySQL/MariaDB**. Didesain dengan antarmuka surat suara resmi, responsif, aman, dan mudah digunakan.

---

## 🌟 Fitur Utama

- **Portal Pemilih (Surat Suara Elektronik)**
  - Otentikasi Pemilih berbasis NIK (Daftar Pemilih Tetap / DPT).
  - Validasi pencegahan pemilihan ganda (1 NIK hanya bisa memilih 1 kali).
  - Tanda terima pemilihan instan dengan ID transaksi unik.
  - Antarmuka kartu suara resmi dengan foto kandidat, nomor urut, serta visi & misi.

- **Panel Administrasi & Rekapitulasi (C1-KWK)**
  - Dashboard statistik real-time (perolehan suara, persentase masuk, pemenang sementara).
  - Manajemen kandidat (tambah/ubah calon, visi-misi, foto).
  - Manajemen DPT (tambah warga, cek status pemilih, reset status vote).
  - Cetak Laporan Berita Acara & Sertifikat Hasil Pemilihan (Format Resmi A4 Siap Cetak/PDF).
  - Fitur reset pemilihan.

---

## 🚀 Petunjuk Instalasi & Penggunaan

### 1. Kebutuhan Sistem
- Web Server (Apache via XAMPP, Laragon, atau sejenisnya)
- PHP versi 7.4 ke atas (Mendukung PHP 8.x)
- MySQL / MariaDB

### 2. Cara Pasang di Localhost (XAMPP)
1. Salin atau *clone* repositori ini ke folder root web server Anda:
   ```bash
   # Masuk ke direktori htdocs
   cd C:/xampp/htdocs/
   
   # Clone repositori
   git clone <URL_REPOSITORY_ANDA> e-voting
   ```
2. Nyalakan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Konfigurasi kredensial database (opsional jika menggunakan pengaturan default XAMPP):
   - Salin file `config.example.php` menjadi `config.php`:
     ```bash
     cp config.example.php config.php
     ```
   - Buka `config.php` dan sesuaikan username/password MySQL jika diperlukan.
4. Buka browser dan akses aplikasi:
   - **Halaman Pemilih:** [http://localhost/e-voting](http://localhost/e-voting)
   - **Panel Admin:** [http://localhost/e-voting/admin.php](http://localhost/e-voting/admin.php)

> **Catatan:** Sistem memiliki fitur *Auto-Setup*. Database `evoting_pilkades` dan tabel akan dibuat secara otomatis saat pertama kali halaman diakses. Anda juga dapat mengimpor file `database.sql` secara manual melalui phpMyAdmin jika diinginkan.

---

## 🔑 Kredensial Default (Testing)

### Administrator
- **URL:** `http://localhost/e-voting/admin.php`
- **Username:** `admin`
- **Password:** `admin`

### Contoh NIK DPT untuk Pengujian Vote:
- `3201010101900001` (Ahmad Fauzi)
- `3201010202910002` (Siti Rahmawati)
- `3201010303920003` (Hendra Setiawan)

---

## 🔒 Keamanan & Data Sensitif

- File `config.php` telah dimasukkan ke dalam `.gitignore` agar kredensial database dan password administrator di server Anda tidak terunggah ke repositori publik GitHub.
- Jangan pernah menyimpan data pemilih asli (NIK / KTP) ke repositori publik.

