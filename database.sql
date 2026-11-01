-- Database SQL untuk Sistem E-Voting Pilkades
-- Dapat diimpor langsung via phpMyAdmin atau otomatis dibuat oleh koneksi.php

CREATE DATABASE IF NOT EXISTS `evoting_pilkades` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `evoting_pilkades`;

-- 1. Tabel users (Pemilih / DPT)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nik` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `status_vote` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: Belum Memilih, 1: Sudah Memilih',
  `waktu_vote` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel calon (Kandidat Kepala Desa)
CREATE TABLE IF NOT EXISTS `calon` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nomor_urut` INT NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `visi_misi` TEXT NOT NULL,
  `foto` VARCHAR(255) NOT NULL DEFAULT '',
  `jumlah_suara` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Data Awal 3 Calon Pilkades
INSERT INTO `calon` (`id`, `nomor_urut`, `nama`, `visi_misi`, `foto`, `jumlah_suara`) VALUES
(1, 1, 'H. Ahmad Sudrajat, S.Sos', 'Mewujudkan tata kelola desa yang transparan, religius, mandiri, dan memperkuat perekonomian petani lokal serta UMKM desa.', 'img/calon1.jpg', 0),
(2, 2, 'Dra. Hj. Siti Maryam', 'Desa ramah keluarga, optimalisasi layanan kesehatan Posyandu, beasiswa pemuda desa, dan pemberdayaan ekonomi perempuan mandiri.', 'img/calon2.jpg', 0),
(3, 3, 'Budi Santoso, S.T.', 'Digitalisasi administrasi desa cepat & bebas calo, modernisasi irigasi pertanian cerdas, dan penciptaan lapangan kerja generasi muda.', 'img/calon3.jpg', 0)
ON DUPLICATE KEY UPDATE `nomor_urut`=VALUES(`nomor_urut`);

-- 4. Data Awal DPT (Daftar Pemilih Tetap Contoh untuk Pengujian)
INSERT IGNORE INTO `users` (`nik`, `nama`, `status_vote`) VALUES
('3201010101900001', 'Ahmad Fauzi', 0),
('3201010202910002', 'Siti Rahmawati', 0),
('3201010303920003', 'Hendra Setiawan', 0),
('3201010404930004', 'Dewi Lestari', 0),
('3201010505940005', 'Rudi Hartono', 0),
('3201010606950006', 'Rina Anggraini', 0),
('3201010707960007', 'Bambang Supriyadi', 0),
('3201010808970008', 'Nur Aini', 0);
