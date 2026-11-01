<?php
/**
 * admin.php - Panel Administrasi & Rekapitulasi Suara (Model C1 Pilkades)
 * Memungkinkan admin:
 * 1. Melihat rekapitulasi jumlah suara sementara secara real-time
 * 2. Mengetahui siapa yang menang (calon perolehan suara tertinggi)
 * 3. Mengubah full data calon (nomor, nama, visi, foto) dan mengubah jumlah suara / menentukan pemenang langsung
 * 4. Mengelola DPT (tambah NIK, hapus NIK, reset status vote pemilih)
 * 5. Reset sistem secara menyeluruh
 */
require_once 'koneksi.php';

if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
}
if (!defined('ADMIN_PASS')) {
    define('ADMIN_PASS', 'admin');
}

$alert = get_alert();
$is_admin = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;

// 1. PROSES LOGIN ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $username = clean($_POST['username']);
    $password = clean($_POST['password']);
    
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;
        set_alert('success', 'Autentikasi Berhasil', 'Selamat datang di Panel Administrasi Pilkades.');
        header('Location: admin.php');
        exit;
    } else {
        set_alert('error', 'Login Ditolak', 'Nama Pengguna atau Kata Sandi tidak sesuai. (Kredensial Default: admin / admin)');
        header('Location: admin.php');
        exit;
    }
}

// 2. PROSES AKSI PENGELOLAAN DATA (HANYA JIKA SUDAH LOGIN ADMIN)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // A. UPDATE FULL DATA CALON & JUMLAH SUARA
    if ($action === 'update_calon') {
        $id = intval($_POST['id']);
        $nomor_urut = intval($_POST['nomor_urut']);
        $nama = clean($_POST['nama']);
        $visi_misi = clean($_POST['visi_misi']);
        $foto = clean($_POST['foto']);
        $jumlah_suara = max(0, intval($_POST['jumlah_suara']));
        
        // Cek duplikat nomor_urut pada calon LAIN (bukan diri sendiri)
        $cekDuplikat = $pdo->prepare("SELECT COUNT(*) FROM calon WHERE nomor_urut = ? AND id != ?");
        $cekDuplikat->execute([$nomor_urut, $id]);
        
        if ($cekDuplikat->fetchColumn() > 0) {
            set_alert('error', 'Gagal Menyimpan', 'Nomor urut ' . $nomor_urut . ' sudah digunakan oleh calon lain. Silakan gunakan nomor urut yang berbeda.');
        } else {
            $stmt = $pdo->prepare("UPDATE calon SET nomor_urut = ?, nama = ?, visi_misi = ?, foto = ?, jumlah_suara = ? WHERE id = ?");
            $stmt->execute([$nomor_urut, $nama, $visi_misi, $foto, $jumlah_suara, $id]);
            set_alert('success', 'Data Disimpan', 'Data Calon No. ' . $nomor_urut . ' (' . $nama . ') serta perolehan suara berhasil diperbarui.');
        }
        header('Location: admin.php');
        exit;
    }
    
    // B. MANIPULASI CEPAT SUARA / ATUR PEMENANG
    if ($action === 'quick_suara') {
        $id = intval($_POST['id']);
        $type = clean($_POST['type']);
        
        if ($type === 'plus') {
            $pdo->prepare("UPDATE calon SET jumlah_suara = jumlah_suara + 1 WHERE id = ?")->execute([$id]);
            set_alert('success', 'Suara Ditambahkan', 'Perolehan suara bertambah +1.');
        } elseif ($type === 'minus') {
            $pdo->prepare("UPDATE calon SET jumlah_suara = GREATEST(0, jumlah_suara - 1) WHERE id = ?")->execute([$id]);
            set_alert('info', 'Suara Dikurangi', 'Perolehan suara dikurangi 1.');
        } elseif ($type === 'jadikan_pemenang') {
            // Atur agar calon ini memiliki suara tertinggi mutlak
            $maxCurrent = $pdo->query("SELECT MAX(jumlah_suara) FROM calon")->fetchColumn();
            $targetSuara = ($maxCurrent > 0 ? $maxCurrent : 10) + 5;
            $pdo->prepare("UPDATE calon SET jumlah_suara = ? WHERE id = ?")->execute([$targetSuara, $id]);
            set_alert('success', 'Pemenang Ditetapkan', 'Calon berhasil diatur memimpin dengan ' . $targetSuara . ' suara.');
        }
        header('Location: admin.php');
        exit;
    }
    
    // C. TAMBAH PEMILIH DPT BARU
    if ($action === 'tambah_dpt') {
        $nik = clean($_POST['nik']);
        $nama = clean($_POST['nama']);
        
        if (empty($nik) || empty($nama)) {
            set_alert('error', 'Form Belum Lengkap', 'Nomor NIK dan Nama Pemilih wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (nik, nama, status_vote) VALUES (?, ?, 0)");
                $stmt->execute([$nik, $nama]);
                set_alert('success', 'Pemilih Terdaftar', 'Warga atas nama ' . $nama . ' (' . $nik . ') berhasil ditambahkan ke DPT.');
            } catch (PDOException $e) {
                set_alert('error', 'NIK Sudah Terdaftar', 'Nomor NIK ini telah terdaftar dalam sistem.');
            }
        }
        header('Location: admin.php');
        exit;
    }
    
    // D. RESET HAK SUARA PER PEMILIH
    if ($action === 'reset_vote_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $pdo->prepare("UPDATE users SET status_vote = 0, waktu_vote = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        set_alert('success', 'Hak Suara Direset', 'Status pemilih berhasil dikembalikan menjadi "Belum Memilih".');
        header('Location: admin.php');
        exit;
    }
    
    // E. HAPUS PEMILIH DPT
    if ($action === 'hapus_user') {
        $user_id = intval($_POST['user_id']);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        set_alert('info', 'Data Dihapus', 'Pemilih berhasil dihapus dari daftar.');
        header('Location: admin.php');
        exit;
    }
    
    // F. RESET SEMUA PEROLEHAN SUARA KE 0
    if ($action === 'reset_semua_suara') {
        $pdo->exec("UPDATE calon SET jumlah_suara = 0");
        set_alert('warning', 'Rekap Suara Dibersihkan', 'Seluruh hitungan perolehan suara calon telah direset ke 0.');
        header('Location: admin.php');
        exit;
    }
    
    // G. RESET STATUS SEMUA PEMILIH KE 0
    if ($action === 'reset_semua_pemilih') {
        $pdo->exec("UPDATE users SET status_vote = 0, waktu_vote = NULL");
        set_alert('warning', 'DPT Direset', 'Seluruh pemilih telah dikembalikan ke status "Belum Memilih".');
        header('Location: admin.php');
        exit;
    }
}

// 3. AMBIL DATA STATISTIK DAN PERHITUNGAN SUARA
if ($is_admin) {
    $calon_list = $pdo->query("SELECT * FROM calon ORDER BY nomor_urut ASC")->fetchAll();
    
    $total_suara_masuk = 0;
    foreach ($calon_list as $c) {
        $total_suara_masuk += $c['jumlah_suara'];
    }
    
    $total_dpt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $dpt_sudah_vote = $pdo->query("SELECT COUNT(*) FROM users WHERE status_vote = 1")->fetchColumn();
    $dpt_belum_vote = $total_dpt - $dpt_sudah_vote;
    $persen_partisipasi = $total_dpt > 0 ? round(($dpt_sudah_vote / $total_dpt) * 100, 2) : 0;
    
    // Deteksi Pemenang Sementara
    $pemenang = null;
    $is_draw = false;
    $max_suara = -1;
    
    if ($total_suara_masuk > 0) {
        $highest = [];
        foreach ($calon_list as $c) {
            if ($c['jumlah_suara'] > $max_suara) {
                $max_suara = $c['jumlah_suara'];
                $highest = [$c];
            } elseif ($c['jumlah_suara'] == $max_suara && $max_suara > 0) {
                $highest[] = $c;
            }
        }
        
        if (count($highest) === 1) {
            $pemenang = $highest[0];
            $pemenang_persen = $total_suara_masuk > 0 ? round(($pemenang['jumlah_suara'] / $total_suara_masuk) * 100, 2) : 0;
        } elseif (count($highest) > 1) {
            $is_draw = true;
        }
    }

    // Urutkan untuk mencari suara terbanyak & terkecil
    $calon_sorted = $calon_list;
    usort($calon_sorted, function($a, $b) {
        return $b['jumlah_suara'] <=> $a['jumlah_suara'];
    });
    $calon_terbanyak = !empty($calon_sorted) ? $calon_sorted[0] : null;
    $calon_terkecil  = !empty($calon_sorted) ? $calon_sorted[count($calon_sorted) - 1] : null;
    $selisih_suara   = ($calon_terbanyak && $calon_terkecil) ? ($calon_terbanyak['jumlah_suara'] - $calon_terkecil['jumlah_suara']) : 0;
    
    $dpt_list = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Rekapitulasi Suara Pilkades - Desa Sukamaju</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Header Panel Admin -->
  <header class="gov-topbar">
    <div class="gov-topbar-inner">
      <a href="admin.php" class="gov-brand">
        <div class="gov-emblem">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
          </svg>
        </div>
        <div>
          <div class="gov-brand-title">Panel Administrasi & Rekapitulasi Suara</div>
          <div class="gov-brand-subtitle">Sekretariat Panitia Pemilihan Kepala Desa Sukamaju</div>
        </div>
      </a>

      <div class="gov-nav-links">
        <a href="index.php" class="btn btn-secondary btn-sm">
          Bilik Suara Pemilih
        </a>
        <?php if ($is_admin): ?>
          <a href="cetak_laporan.php" target="_blank" class="btn btn-pdf btn-sm" title="Buka Dokumen Resmi Berita Acara PDF">
            📄 Cetak / Download PDF
          </a>
          <a href="logout.php?type=admin" class="btn btn-outline-danger btn-sm" onclick="return confirm('Keluar dari panel administrasi?')">
            Logout Admin
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container" style="flex: 1; padding-bottom: 50px;">

    <!-- Notifikasi Sistem -->
    <?php if ($alert): ?>
      <div style="margin-top: 20px;">
        <div class="gov-alert <?= htmlspecialchars($alert['type']) ?>">
          <div class="gov-alert-content">
            <strong><?= htmlspecialchars($alert['title']) ?></strong>
            <div><?= htmlspecialchars($alert['message']) ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAMPILAN JIKA BELUM LOGIN ADMIN -->
    <?php if (!$is_admin): ?>
      <div class="gov-auth-card">
        <div class="gov-auth-header">
          <h3>Login Pengurus Panitia</h3>
          <p>Masukkan Kredensial Akses Panel Rekapitulasi</p>
        </div>

        <div class="gov-auth-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="admin_login">

            <div class="form-group">
              <label class="form-label">Nama Pengguna (Username)</label>
              <input type="text" name="username" class="form-input" placeholder="admin" required autofocus autocomplete="off">
            </div>

            <div class="form-group">
              <label class="form-label">Kata Sandi (Password)</label>
              <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-navy btn-block btn-lg">
              Masuk ke Panel Kontrol
            </button>
          </form>

          <div style="margin-top: 20px; padding: 12px; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-size: 0.8rem; color: var(--slate-600); text-align: center;">
            Kredensial Default: Username <code>admin</code> | Password <code>admin</code>
          </div>
        </div>
      </div>

    <!-- TAMPILAN DASHBOARD LENGKAP JIKA SUDAH LOGIN ADMIN -->
    <?php else: ?>

      <!-- 1. KARTU STATISTIK RESMI PEMILU -->
      <div class="admin-summary-grid">
        <div class="admin-summary-card">
          <div class="admin-summary-label">Total Pemilih Tetap (DPT)</div>
          <div class="admin-summary-value"><?= number_format($total_dpt) ?></div>
          <div class="admin-summary-sub">Pemilih terverifikasi</div>
        </div>

        <div class="admin-summary-card">
          <div class="admin-summary-label">Total Suara Masuk</div>
          <div class="admin-summary-value" style="color: var(--gov-green-dark);"><?= number_format($total_suara_masuk) ?></div>
          <div class="admin-summary-sub">Surat suara terhitung</div>
        </div>

        <div class="admin-summary-card">
          <div class="admin-summary-label">Belum Menggunakan Hak</div>
          <div class="admin-summary-value" style="color: var(--gov-gold);"><?= number_format($dpt_belum_vote) ?></div>
          <div class="admin-summary-sub">DPT belum mencoblos</div>
        </div>

        <div class="admin-summary-card">
          <div class="admin-summary-label">Tingkat Partisipasi</div>
          <div class="admin-summary-value"><?= $persen_partisipasi ?>%</div>
          <div class="admin-summary-sub">Rasio kehadiran pemilih</div>
        </div>
      </div>

      <!-- 2. STATUS PENENTUAN SIAPA YANG MENANG (RESMI) -->
      <div class="winner-official-box">
        <div>
          <div class="winner-official-tag">Status Hasil Perhitungan Suara Sementara</div>
          <?php if ($pemenang): ?>
            <div class="winner-official-name">
              Calon Nomor Urut <?= $pemenang['nomor_urut'] ?> — <?= htmlspecialchars($pemenang['nama']) ?>
            </div>
            <div style="font-size: 0.825rem; color: var(--slate-600); margin-top: 4px;">
              Memimpin perolehan suara terbanyak dalam rekapitulasi sementara TPS 01 Desa Sukamaju.
            </div>
          <?php elseif ($is_draw): ?>
            <div class="winner-official-name" style="color: var(--gov-gold);">
              Posisi Perolehan Suara Seimbang (Imbang)
            </div>
            <div style="font-size: 0.825rem; color: var(--slate-600); margin-top: 4px;">
              Dua atau lebih kandidat memiliki jumlah perolehan suara yang sama persis (<?= $max_suara ?> suara).
            </div>
          <?php else: ?>
            <div class="winner-official-name" style="color: var(--slate-500);">
              Belum Ada Surat Suara yang Masuk
            </div>
            <div style="font-size: 0.825rem; color: var(--slate-500); margin-top: 4px;">
              Perhitungan suara akan otomatis diperbarui begitu pemilih melakukan pencoblosan di bilik suara.
            </div>
          <?php endif; ?>
        </div>

        <?php if ($pemenang): ?>
          <div class="winner-official-stats">
            <div class="winner-official-votes"><?= number_format($pemenang['jumlah_suara']) ?> Suara</div>
            <div style="font-size: 0.775rem; color: var(--slate-600); font-weight: 600;"><?= $pemenang_persen ?>% dari seluruh suara sah</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- 3. DATA PEMILIH TERBANYAK & TERKECIL -->
      <div class="stat-extremes-grid">
        <!-- Suara Terbanyak -->
        <div class="card-extreme terbanyak">
          <div class="card-extreme-tag">Perolehan Suara Terbanyak (Peringkat 1)</div>
          <?php if ($total_suara_masuk > 0 && $calon_terbanyak): ?>
            <div class="card-extreme-name">
              Calon No. <?= $calon_terbanyak['nomor_urut'] ?> — <?= htmlspecialchars($calon_terbanyak['nama']) ?>
            </div>
            <div class="card-extreme-val">
              <?= number_format($calon_terbanyak['jumlah_suara']) ?> <span style="font-size: 0.85rem; font-weight: normal; color: #475569;">Suara</span>
            </div>
            <div class="card-extreme-sub">
              Menguasai <?= round(($calon_terbanyak['jumlah_suara'] / $total_suara_masuk) * 100, 2) ?>% dari seluruh suara sah
            </div>
          <?php else: ?>
            <div class="card-extreme-name" style="color: var(--slate-500);">Belum Ada Data</div>
            <div class="card-extreme-val" style="color: var(--slate-400);">0 Suara</div>
            <div class="card-extreme-sub">Menunggu suara pemilih pertama masuk</div>
          <?php endif; ?>
        </div>

        <!-- Suara Terkecil -->
        <div class="card-extreme terkecil">
          <div class="card-extreme-tag">Perolehan Suara Terkecil (Peringkat Terakhir)</div>
          <?php if ($total_suara_masuk > 0 && $calon_terkecil): ?>
            <div class="card-extreme-name">
              Calon No. <?= $calon_terkecil['nomor_urut'] ?> — <?= htmlspecialchars($calon_terkecil['nama']) ?>
            </div>
            <div class="card-extreme-val">
              <?= number_format($calon_terkecil['jumlah_suara']) ?> <span style="font-size: 0.85rem; font-weight: normal; color: #475569;">Suara</span>
            </div>
            <div class="card-extreme-sub">
              Memperoleh <?= round(($calon_terkecil['jumlah_suara'] / $total_suara_masuk) * 100, 2) ?>% dari total suara
            </div>
          <?php else: ?>
            <div class="card-extreme-name" style="color: var(--slate-500);">Belum Ada Data</div>
            <div class="card-extreme-val" style="color: var(--slate-400);">0 Suara</div>
            <div class="card-extreme-sub">Belum ada suara terhitung</div>
          <?php endif; ?>
        </div>

        <!-- Selisih Suara (Margin) -->
        <div class="card-extreme selisih">
          <div class="card-extreme-tag">Selisih Suara (Margin Kemenangan)</div>
          <?php if ($total_suara_masuk > 0): ?>
            <div class="card-extreme-name">
              Gap Suara Tertinggi vs Terendah
            </div>
            <div class="card-extreme-val">
              <?= number_format($selisih_suara) ?> <span style="font-size: 0.85rem; font-weight: normal; color: #475569;">Suara</span>
            </div>
            <div class="card-extreme-sub">
              <?php if ($selisih_suara == 0): ?>
                Posisi suara calon berada pada perolehan yang sama persis (imbang)
              <?php else: ?>
                Keunggulan selisih suara sah antara peringkat puncak dan juru kunci
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="card-extreme-name" style="color: var(--slate-500);">0 Suara</div>
            <div class="card-extreme-val" style="color: var(--slate-400);">-</div>
            <div class="card-extreme-sub">Belum ada pemungutan suara berlangsung</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. DIAGRAM VISUALISASI PEROLEHAN SUARA (BATANG & DONAT SVG RINGAN) -->
      <?php
        // Kalkulasi busur donat SVG murni
        $c1_suara = $calon_list[0]['jumlah_suara'] ?? 0;
        $c2_suara = $calon_list[1]['jumlah_suara'] ?? 0;
        $c3_suara = $calon_list[2]['jumlah_suara'] ?? 0;

        $p1 = $total_suara_masuk > 0 ? ($c1_suara / $total_suara_masuk) : 0;
        $p2 = $total_suara_masuk > 0 ? ($c2_suara / $total_suara_masuk) : 0;
        $p3 = $total_suara_masuk > 0 ? ($c3_suara / $total_suara_masuk) : 0;

        $keliling = 376.99; // 2 * PI * 60
        $len1 = round($p1 * $keliling, 2);
        $len2 = round($p2 * $keliling, 2);
        $len3 = round($p3 * $keliling, 2);
        $offset2 = -$len1;
        $offset3 = -($len1 + $len2);
      ?>
      <div class="chart-section-grid">
        <!-- Diagram Batang Komparatif -->
        <div class="chart-box">
          <div class="chart-header">
            <div class="chart-title">Diagram Batang Perolehan Suara</div>
            <span style="font-size: 0.775rem; color: var(--slate-500); font-weight: 600;">Skala Perbandingan 3 Calon</span>
          </div>

          <div class="chart-bars-list">
            <?php 
            $colors = ['bar-1', 'bar-2', 'bar-3'];
            foreach ($calon_list as $idx => $c): 
              $persen = $total_suara_masuk > 0 ? round(($c['jumlah_suara'] / $total_suara_masuk) * 100, 2) : 0;
              $colorClass = $colors[$idx % 3];
            ?>
              <div class="chart-bar-item">
                <div class="chart-bar-info">
                  <strong>No. <?= $c['nomor_urut'] ?> — <?= htmlspecialchars($c['nama']) ?></strong>
                  <span style="font-weight: 800; color: var(--gov-navy);">
                    <?= number_format($c['jumlah_suara']) ?> Suara (<?= $persen ?>%)
                  </span>
                </div>
                <div class="chart-bar-track">
                  <div class="chart-bar-fill <?= $colorClass ?>" style="width: <?= max(4, $persen) ?>%;">
                    <?php if ($persen > 8): ?>
                      <?= $persen ?>%
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Diagram Lingkaran Proporsi Donat (SVG Murni) -->
        <div class="chart-box">
          <div class="chart-header">
            <div class="chart-title">Diagram Proporsi Suara</div>
            <span style="font-size: 0.775rem; color: var(--slate-500); font-weight: 600;">Persentase Sah</span>
          </div>

          <div class="donut-container">
            <div class="donut-svg-wrap">
              <svg width="170" height="170" viewBox="0 0 180 180">
                <!-- Background Ring -->
                <circle cx="90" cy="90" r="60" fill="none" stroke="#e2e8f0" stroke-width="26" />
                
                <?php if ($total_suara_masuk > 0): ?>
                  <!-- Slice Calon 1 (Navy) -->
                  <?php if ($len1 > 0): ?>
                    <circle cx="90" cy="90" r="60" fill="none" stroke="#0f2744" stroke-width="26" 
                      stroke-dasharray="<?= $len1 ?> <?= $keliling ?>" stroke-dashoffset="0"
                      transform="rotate(-90 90 90)" />
                  <?php endif; ?>

                  <!-- Slice Calon 2 (Green) -->
                  <?php if ($len2 > 0): ?>
                    <circle cx="90" cy="90" r="60" fill="none" stroke="#15803d" stroke-width="26" 
                      stroke-dasharray="<?= $len2 ?> <?= $keliling ?>" stroke-dashoffset="<?= $offset2 ?>"
                      transform="rotate(-90 90 90)" />
                  <?php endif; ?>

                  <!-- Slice Calon 3 (Gold) -->
                  <?php if ($len3 > 0): ?>
                    <circle cx="90" cy="90" r="60" fill="none" stroke="#b45309" stroke-width="26" 
                      stroke-dasharray="<?= $len3 ?> <?= $keliling ?>" stroke-dashoffset="<?= $offset3 ?>"
                      transform="rotate(-90 90 90)" />
                  <?php endif; ?>
                <?php endif; ?>
              </svg>

              <div class="donut-center-text">
                <span style="font-size: 1.35rem; font-weight: 800; color: var(--gov-navy); line-height: 1.1;">
                  <?= number_format($total_suara_masuk) ?>
                </span>
                <span style="font-size: 0.675rem; text-transform: uppercase; color: var(--slate-500); font-weight: 700; letter-spacing: 0.05em;">
                  Suara Sah
                </span>
              </div>
            </div>

            <!-- Legend Calon -->
            <div class="donut-legend">
              <div class="donut-legend-item">
                <div class="donut-legend-color" style="background: #0f2744;"></div>
                <div>
                  <div style="font-weight: 700; color: var(--gov-navy);">Calon 01 (<?= round($p1 * 100, 1) ?>%)</div>
                  <div style="font-size: 0.725rem; color: var(--slate-500);"><?= number_format($c1_suara) ?> Suara</div>
                </div>
              </div>

              <div class="donut-legend-item">
                <div class="donut-legend-color" style="background: #15803d;"></div>
                <div>
                  <div style="font-weight: 700; color: var(--gov-navy);">Calon 02 (<?= round($p2 * 100, 1) ?>%)</div>
                  <div style="font-size: 0.725rem; color: var(--slate-500);"><?= number_format($c2_suara) ?> Suara</div>
                </div>
              </div>

              <div class="donut-legend-item">
                <div class="donut-legend-color" style="background: #b45309;"></div>
                <div>
                  <div style="font-weight: 700; color: var(--gov-navy);">Calon 03 (<?= round($p3 * 100, 1) ?>%)</div>
                  <div style="font-size: 0.725rem; color: var(--slate-500);"><?= number_format($c3_suara) ?> Suara</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. TABEL REKAPITULASI & PENGELOLAAN LENGKAP CALON -->
      <div style="margin-bottom: 34px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <div>
            <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--gov-navy); text-transform: uppercase;">
              Rekapitulasi Suara 3 Kandidat Calon Kepala Desa
            </h2>
            <p style="font-size: 0.8rem; color: var(--slate-600);">
              Admin berhak mengubah identitas calon secara lengkap serta mengubah perolehan suara langsung (menentukan pemenang).
            </p>
          </div>
          <div style="display: flex; gap: 8px;">
            <a href="cetak_laporan.php" target="_blank" class="btn btn-pdf btn-sm">
              📄 Download Laporan PDF (Model C1)
            </a>
            <form method="POST" action="admin.php" onsubmit="return confirm('PERINGATAN: Kosongkan seluruh hitungan perolehan suara calon menjadi 0?')">
              <input type="hidden" name="action" value="reset_semua_suara">
              <button type="submit" class="btn btn-outline-danger btn-sm">
                Reset Semua Suara ke 0
              </button>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table class="gov-table">
            <thead>
              <tr>
                <th style="width: 60px; text-align: center;">No.</th>
                <th style="width: 70px;">Foto</th>
                <th>Nama Kandidat Calon</th>
                <th style="width: 260px;">Perolehan Suara Sah & Persentase</th>
                <th style="width: 150px; text-align: center;">Penyesuaian Cepat</th>
                <th style="width: 130px; text-align: center;">Kelola Lengkap</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calon_list as $c): 
                $persen = $total_suara_masuk > 0 ? round(($c['jumlah_suara'] / $total_suara_masuk) * 100, 2) : 0;
                $isLeading = ($pemenang && $pemenang['id'] == $c['id']);
              ?>
                <tr style="<?= $isLeading ? 'background: #f0fdf4;' : '' ?>">
                  <td style="text-align: center; font-weight: 800; font-size: 1.1rem; color: var(--gov-navy);">
                    <?= $c['nomor_urut'] ?>
                  </td>
                  <td>
                    <img 
                      src="<?= !empty($c['foto']) && file_exists($c['foto']) ? htmlspecialchars($c['foto']) : 'data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' fill=\'%23cbd5e1\'><rect width=\'100\' height=\'100\' fill=\'%23f1f5f9\'/><circle cx=\'50\' cy=\'40\' r=\'20\'/><path d=\'M20 90 c0-20 20-30 30-30 s30 10 30 30\'/></svg>' ?>" 
                      alt="<?= htmlspecialchars($c['nama']) ?>" 
                      style="width: 50px; height: 50px; border-radius: 6px; object-fit: cover; border: 1px solid var(--slate-300);"
                    >
                  </td>
                  <td>
                    <strong style="font-size: 0.95rem; color: var(--gov-navy); display: block;">
                      <?= htmlspecialchars($c['nama']) ?>
                      <?php if ($isLeading): ?>
                        <span class="badge badge-leader" style="margin-left: 6px;">Perolehan Tertinggi</span>
                      <?php endif; ?>
                    </strong>
                    <div style="font-size: 0.775rem; color: var(--slate-600); margin-top: 3px; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= htmlspecialchars($c['visi_misi']) ?>
                    </div>
                  </td>
                  <td>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 0.875rem;">
                      <strong style="color: var(--gov-navy); font-size: 1.05rem;"><?= number_format($c['jumlah_suara']) ?> Suara</strong>
                      <span style="font-weight: 700; color: var(--gov-green-dark); font-size: 0.85rem;"><?= $persen ?>%</span>
                    </div>
                    <div class="tally-bar">
                      <div class="tally-fill" style="width: <?= $persen ?>%;"></div>
                    </div>
                  </td>
                  <td style="text-align: center;">
                    <div style="display: flex; gap: 4px; justify-content: center;">
                      <!-- +1 Suara -->
                      <form method="POST" action="admin.php" style="display:inline;">
                        <input type="hidden" name="action" value="quick_suara">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="type" value="plus">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Tambah 1 Suara" style="padding: 4px 8px; font-weight: 700; color: var(--gov-green);">+1</button>
                      </form>
                      <!-- -1 Suara -->
                      <form method="POST" action="admin.php" style="display:inline;">
                        <input type="hidden" name="action" value="quick_suara">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="type" value="minus">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Kurangi 1 Suara" style="padding: 4px 8px; font-weight: 700; color: var(--gov-red);">-1</button>
                      </form>
                      <!-- Jadikan Pemenang -->
                      <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Atur Calon No. <?= $c['nomor_urut'] ?> menjadi pemenang suara terbanyak?')">
                        <input type="hidden" name="action" value="quick_suara">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="type" value="jadikan_pemenang">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Set Sebagai Pemenang Tertinggi" style="padding: 4px 8px; font-size: 0.75rem; font-weight: 700;">Set Menang</button>
                      </form>
                    </div>
                  </td>
                  <td style="text-align: center;">
                    <button 
                      type="button" 
                      class="btn btn-navy btn-sm" 
                      onclick='bukaModalEdit(<?= json_encode($c) ?>)'
                    >
                      Ubah Data
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. MANAJEMEN DATA PEMILIH TETAP (DPT) -->
      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <div>
            <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--gov-navy); text-transform: uppercase;">
              Daftar Pemilih Tetap (DPT) Desa Sukamaju
            </h2>
            <p style="font-size: 0.8rem; color: var(--slate-600);">
              Daftar identitas warga pemegang hak suara. Anda dapat menambah pemilih, mereset status vote agar bisa memilih ulang, atau menghapus NIK.
            </p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="bukaModalTambahDpt()">
              + Tambah Pemilih DPT
            </button>
            <form method="POST" action="admin.php" onsubmit="return confirm('Kembalikan status SELURUH pemilih menjadi Belum Memilih?')">
              <input type="hidden" name="action" value="reset_semua_pemilih">
              <button type="submit" class="btn btn-secondary btn-sm">
                Reset Semua Status Pemilih
              </button>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table class="gov-table">
            <thead>
              <tr>
                <th style="width: 50px;">ID</th>
                <th style="width: 190px;">Nomor Induk Kependudukan (NIK)</th>
                <th>Nama Lengkap Pemilih</th>
                <th style="width: 140px;">Status Hak Suara</th>
                <th style="width: 180px;">Waktu Pemberian Suara</th>
                <th style="width: 160px; text-align: center;">Aksi Pengurus</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dpt_list)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 24px; color: var(--slate-500);">
                    Belum ada data DPT. Silakan tambahkan pemilih baru.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($dpt_list as $u): ?>
                  <tr>
                    <td><?= $u['id'] ?></td>
                    <td><code style="font-size: 0.85rem; font-weight: 700; color: var(--gov-navy);"><?= htmlspecialchars($u['nik']) ?></code></td>
                    <td><strong><?= htmlspecialchars($u['nama']) ?></strong></td>
                    <td>
                      <?php if ($u['status_vote'] == 1): ?>
                        <span class="badge badge-success">Sudah Memilih</span>
                      <?php else: ?>
                        <span class="badge badge-neutral">Belum Memilih</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--slate-600);">
                      <?= $u['waktu_vote'] ? date('d/m/Y H:i:s', strtotime($u['waktu_vote'])) . ' WIB' : '-' ?>
                    </td>
                    <td style="text-align: center;">
                      <div style="display: flex; gap: 6px; justify-content: center;">
                        <?php if ($u['status_vote'] == 1): ?>
                          <form method="POST" action="admin.php" onsubmit="return confirm('Kembalikan status <?= htmlspecialchars($u['nama']) ?> menjadi Belum Memilih agar dapat mencoblos kembali?')">
                            <input type="hidden" name="action" value="reset_vote_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Reset Status Vote">
                              Reset Vote
                            </button>
                          </form>
                        <?php endif; ?>

                        <form method="POST" action="admin.php" onsubmit="return confirm('Hapus NIK <?= htmlspecialchars($u['nik']) ?> dari daftar DPT?')">
                          <input type="hidden" name="action" value="hapus_user">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus NIK">
                            Hapus
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL 1: UBAH DATA CALON & JUMLAH SUARA -->
      <div id="modalEditCalon" class="modal-overlay">
        <div class="modal-card" style="max-width: 520px;">
          <div class="modal-title">Ubah Full Data Calon & Suara</div>
          <p class="modal-desc">
            Perbarui data identitas calon, nomor urut, visi misi, foto, atau ubah jumlah perolehan suara langsung.
          </p>

          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="update_calon">
            <input type="hidden" name="id" id="editCalonId" value="">

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Nomor Urut</label>
                <input type="number" id="editCalonNomor" name="nomor_urut" class="form-input" required min="1">
              </div>

              <div class="form-group">
                <label class="form-label">Jumlah Suara Sah</label>
                <input type="number" id="editCalonSuara" name="jumlah_suara" class="form-input" required min="0" style="font-weight: 700; color: var(--gov-green-dark);">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Nama Lengkap & Gelar Kandidat</label>
              <input type="text" id="editCalonNama" name="nama" class="form-input" required>
            </div>

            <div class="form-group">
              <label class="form-label">File / URL Foto Calon</label>
              <input type="text" id="editCalonFoto" name="foto" class="form-input" placeholder="img/calon1.jpg">
            </div>

            <div class="form-group">
              <label class="form-label">Visi & Program Kerja Unggulan</label>
              <textarea id="editCalonVisi" name="visi_misi" class="form-input" rows="3" required></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
              <button type="button" class="btn btn-secondary btn-block" onclick="tutupModalEdit()">Batal</button>
              <button type="submit" class="btn btn-navy btn-block">Simpan Perubahan Data</button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL 2: PENDAFTARAN DPT BARU -->
      <div id="modalTambahDpt" class="modal-overlay">
        <div class="modal-card" style="max-width: 440px;">
          <div class="modal-title">Pendaftaran Pemilih DPT Baru</div>
          <p class="modal-desc">
            Daftarkan warga yang memenuhi syarat sebagai pemilih sah dalam Pilkades.
          </p>

          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="tambah_dpt">

            <div class="form-group">
              <label class="form-label">Nomor Induk Kependudukan (NIK)</label>
              <input type="text" name="nik" class="form-input form-input-nik" placeholder="3201..." maxlength="20" required autocomplete="off">
            </div>

            <div class="form-group">
              <label class="form-label">Nama Lengkap Sesuai KTP</label>
              <input type="text" name="nama" class="form-input" placeholder="Nama warga" required autocomplete="off">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
              <button type="button" class="btn btn-secondary btn-block" onclick="tutupModalTambahDpt()">Batal</button>
              <button type="submit" class="btn btn-navy btn-block">Daftarkan ke DPT</button>
            </div>
          </form>
        </div>
      </div>

      <script>
        function bukaModalEdit(c) {
          document.getElementById('editCalonId').value = c.id;
          document.getElementById('editCalonNomor').value = c.nomor_urut;
          document.getElementById('editCalonNama').value = c.nama;
          document.getElementById('editCalonFoto').value = c.foto;
          document.getElementById('editCalonSuara').value = c.jumlah_suara;
          document.getElementById('editCalonVisi').value = c.visi_misi;
          document.getElementById('modalEditCalon').classList.add('active');
        }

        function tutupModalEdit() {
          document.getElementById('modalEditCalon').classList.remove('active');
        }

        function bukaModalTambahDpt() {
          document.getElementById('modalTambahDpt').classList.add('active');
        }

        function tutupModalTambahDpt() {
          document.getElementById('modalTambahDpt').classList.remove('active');
        }

        window.addEventListener('click', function(e) {
          if (e.target === document.getElementById('modalEditCalon')) tutupModalEdit();
          if (e.target === document.getElementById('modalTambahDpt')) tutupModalTambahDpt();
        });
      </script>

    <?php endif; ?>

  </main>

  <!-- Footer Resmi -->
  <footer class="gov-footer">
    <div class="container">
      Panitia Pemilihan Kepala Desa (P2KD) Desa Sukamaju &copy; 2026. Panel Rekapitulasi Berbasis PHP Native & MySQL.
    </div>
  </footer>

</body>
</html>
