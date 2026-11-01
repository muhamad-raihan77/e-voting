<?php
/**
 * koneksi.php - Skrip Koneksi Database & Auto-Init Database
 * Sistem E-Voting Pilkades 3 Calon (PHP Native + MySQL)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat konfigurasi kustom jika file config.php ada (file ini dilindungi oleh .gitignore)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : '';
$db   = defined('DB_NAME') ? DB_NAME : 'evoting_pilkades';

try {
    // 1. Coba koneksi langsung ke database tujuan
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // 2. Jika database belum ada, buat otomatis secara transparan (Self-Setup)
    try {
        $rootPdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $rootPdo->exec($sql);
        } else {
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4;");
        }
        
        // Hubungkan kembali setelah pembuatan database
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $ex) {
        die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;max-width:600px;margin:40px auto;'>
            <h3>Gagal Terhubung ke Database MySQL!</h3>
            <p>Pastikan MySQL di XAMPP Control Panel sudah aktif (Running).</p>
            <small>Detail Error: " . htmlspecialchars($ex->getMessage()) . "</small>
        </div>");
    }
}

// Pastikan tabel siap digunakan
try {
    $check = $pdo->query("SHOW TABLES LIKE 'calon'")->rowCount();
    if ($check == 0) {
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
    }
} catch (Exception $e) {
    // Abaikan jika sudah ada
}

// Helper: Sanitasi input
function clean($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

// Helper: Flash message untuk notifikasi manis
function set_alert($type, $title, $message) {
    $_SESSION['flash_alert'] = [
        'type' => $type,     // success, error, warning, info
        'title' => $title,
        'message' => $message
    ];
}

function get_alert() {
    if (isset($_SESSION['flash_alert'])) {
        $alert = $_SESSION['flash_alert'];
        unset($_SESSION['flash_alert']);
        return $alert;
    }
    return null;
}
