<?php
/**
 * logout.php - Menghapus sesi pemilih atau admin
 */
require_once 'koneksi.php';

$type = isset($_GET['type']) ? clean($_GET['type']) : 'user';

if ($type === 'admin') {
    unset($_SESSION['admin_logged']);
    set_alert('info', 'Logout Berhasil', 'Anda telah keluar dari halaman Admin.');
    header('Location: admin.php');
    exit;
} else {
    unset($_SESSION['user_nik']);
    unset($_SESSION['user_nama']);
    unset($_SESSION['vote_success']);
    set_alert('info', 'Sesi Berakhir', 'Sesi pemilihan telah selesai. Terima kasih.');
    header('Location: index.php');
    exit;
}
