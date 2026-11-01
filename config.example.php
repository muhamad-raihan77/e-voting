<?php
/**
 * Template Konfigurasi Aplikasi E-Voting Pilkades
 * 
 * CARA PENGGUNAAN:
 * 1. Salin (copy) file ini menjadi `config.php`
 * 2. Sesuaikan kredensial database dan admin di bawah sesuai server/hosting Anda
 * 3. File `config.php` sudah terdaftar di `.gitignore` sehingga TIDAK AKAN terunggah ke GitHub.
 */

// Konfigurasi Database MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'evoting_pilkades');

// Kredensial Login Administrator
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin');

