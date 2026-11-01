<?php
/**
 * index.php - Portal Surat Suara Elektronik Pemilih (E-Voting Pilkades)
 * Didesain dengan tata letak resmi Surat Suara Pilkades Indonesia.
 * Bebas kesan template AI: Clean, Resmi, Responsif, dan Ringan.
 */
require_once 'koneksi.php';

$alert = get_alert();
$is_logged = isset($_SESSION['user_nik']);
$vote_success = isset($_SESSION['vote_success']) ? $_SESSION['vote_success'] : false;

// 1. PROSES VERIFIKASI & LOGIN NIK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login_nik') {
    $nik = clean($_POST['nik']);
    
    if (empty($nik)) {
        set_alert('error', 'Form Belum Lengkap', 'Silakan masukkan 16 digit NIK Anda sesuai KTP/KK.');
        header('Location: index.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nik = ?");
    $stmt->execute([$nik]);
    $user = $stmt->fetch();
    
    if (!$user) {
        set_alert('error', 'NIK Tidak Ditemukan', 'Nomor NIK "' . $nik . '" tidak tercatat dalam Daftar Pemilih Tetap (DPT). Silakan hubungi Panitia Pemilihan Desa.');
        header('Location: index.php');
        exit;
    }
    
    if ($user['status_vote'] == 1) {
        $waktu = $user['waktu_vote'] ? date('d/m/Y H:i', strtotime($user['waktu_vote'])) . ' WIB' : 'Sesi sebelumnya';
        set_alert('warning', 'Hak Suara Telah Digunakan', 'Pemilih atas nama ' . $user['nama'] . ' (NIK: ' . $user['nik'] . ') sudah tercatat menggunakan hak suara pada ' . $waktu . '. Sistem menolak pemilihan berulang.');
        header('Location: index.php');
        exit;
    }
    
    $_SESSION['user_nik'] = $user['nik'];
    $_SESSION['user_nama'] = $user['nama'];
    set_alert('success', 'Verifikasi Berhasil', 'Identitas terverifikasi. Selamat datang, ' . $user['nama'] . '. Silakan gunakan hak pilih Anda.');
    header('Location: index.php');
    exit;
}

// 2. PROSES PEMBERIAN SUARA (TRANSAKSI ATOMIK DATABASE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_vote') {
    if (!$is_logged) {
        set_alert('error', 'Sesi Kedaluwarsa', 'Waktu sesi pemilihan telah habis. Silakan verifikasi NIK kembali.');
        header('Location: index.php');
        exit;
    }
    
    $nik = $_SESSION['user_nik'];
    $calon_id = intval($_POST['calon_id'] ?? 0);
    
    if ($calon_id <= 0) {
        set_alert('error', 'Data Tidak Sah', 'Kandidat calon tidak valid.');
        header('Location: index.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Kunci baris pemilih untuk mencegah duplikasi (FOR UPDATE)
        $stmtUser = $pdo->prepare("SELECT status_vote, nama FROM users WHERE nik = ? FOR UPDATE");
        $stmtUser->execute([$nik]);
        $currentUser = $stmtUser->fetch();
        
        if (!$currentUser || $currentUser['status_vote'] == 1) {
            $pdo->rollBack();
            set_alert('warning', 'Peringatan Duplikasi', 'Hak suara pemilih ini telah tercatat sebelumnya.');
            unset($_SESSION['user_nik']);
            unset($_SESSION['user_nama']);
            header('Location: index.php');
            exit;
        }
        
        // 1. Update status pemilih
        $upUser = $pdo->prepare("UPDATE users SET status_vote = 1, waktu_vote = NOW() WHERE nik = ?");
        $upUser->execute([$nik]);
        
        // 2. Tambah suara ke calon terpilih
        $upCalon = $pdo->prepare("UPDATE calon SET jumlah_suara = jumlah_suara + 1 WHERE id = ?");
        $upCalon->execute([$calon_id]);
        
        // Ambil info calon terpilih untuk tanda terima
        $stmtCalon = $pdo->prepare("SELECT * FROM calon WHERE id = ?");
        $stmtCalon->execute([$calon_id]);
        $calonTerpilih = $stmtCalon->fetch();
        
        $pdo->commit();
        
        // Tanda terima pemungutan suara
        $_SESSION['vote_success'] = [
            'id_transaksi' => 'PLK-' . strtoupper(substr(md5(uniqid($nik, true)), 0, 8)),
            'nama_pemilih' => $currentUser['nama'],
            'nik'          => $nik,
            'nomor_calon'  => $calonTerpilih['nomor_urut'] ?? '-',
            'nama_calon'   => $calonTerpilih['nama'] ?? '-',
            'waktu'        => date('d F Y, H:i:s') . ' WIB'
        ];
        
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_alert('error', 'Gangguan Sistem', 'Gagal memproses data suara: ' . $e->getMessage());
        header('Location: index.php');
        exit;
    }
}

// 3. AMBIL DATA CALON UNTUK BILIK SUARA
$calon_list = [];
if ($is_logged && !$vote_success) {
    // Verifikasi ulang di DB apakah status vote masih 0
    $cekStatus = $pdo->prepare("SELECT status_vote FROM users WHERE nik = ?");
    $cekStatus->execute([$_SESSION['user_nik']]);
    if ($cekStatus->fetchColumn() == 1) {
        unset($_SESSION['user_nik']);
        set_alert('warning', 'Status Vote', 'Anda sudah tercatat melakukan pencoblosan.');
        header('Location: index.php');
        exit;
    }
    
    $stmt = $pdo->query("SELECT * FROM calon ORDER BY nomor_urut ASC LIMIT 3");
    $calon_list = $stmt->fetchAll();
}

// Data NIK DPT untuk bantuan pengujian praktis
$dptSamples = $pdo->query("SELECT nik, nama, status_vote FROM users ORDER BY id ASC LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Surat Suara Elektronik - Pilkades Desa Sukamaju</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Header Resmi Pemerintahan Desa -->
  <header class="gov-topbar">
    <div class="gov-topbar-inner">
      <a href="index.php" class="gov-brand">
        <div class="gov-emblem">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
          </svg>
        </div>
        <div>
          <div class="gov-brand-title">Panitia Pemilihan Kepala Desa (P2KD)</div>
          <div class="gov-brand-subtitle">Desa Sukamaju • Pemilihan Serentak Periode 2026–2032</div>
        </div>
      </a>

      <div class="gov-nav-links">
        <?php if ($is_logged && !$vote_success): ?>
          <div class="gov-user-tag">
            <span>Pemilih:</span>
            <strong><?= clean($_SESSION['user_nama']) ?></strong>
            <span style="opacity: 0.7;">(<?= clean($_SESSION['user_nik']) ?>)</span>
          </div>
          <a href="logout.php?type=user" class="btn btn-secondary btn-sm" onclick="return confirm('Batalkan sesi pemilihan?')">Batal</a>
        <?php else: ?>
          <a href="admin.php" class="btn btn-secondary btn-sm">
            Portal Rekapitulasi (Admin)
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Container Utama -->
  <main class="container" style="flex: 1; padding-bottom: 40px;">

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

    <!-- TAMPILAN 1: TANDA TERIMA HAK SUARA (SELESAI COBLOS) -->
    <?php if ($vote_success): ?>
      <div class="receipt-wrapper">
        <div class="receipt-header">
          <h3>Tanda Terima Pemberian Suara</h3>
          <p style="font-size: 0.8rem; color: #cbd5e1; margin-top: 4px;">Formulir Model C-Pemberitahuan Elektronik</p>
        </div>
        
        <div class="receipt-body">
          <div class="receipt-stamp">
            ✓ Suara Sah Telah Berhasil Tercatat
          </div>

          <table class="receipt-table">
            <tr>
              <td>No. Transaksi</td>
              <td><code><?= htmlspecialchars($vote_success['id_transaksi']) ?></code></td>
            </tr>
            <tr>
              <td>Nama Pemilih</td>
              <td><?= htmlspecialchars($vote_success['nama_pemilih']) ?></td>
            </tr>
            <tr>
              <td>Nomor NIK</td>
              <td><?= htmlspecialchars($vote_success['nik']) ?></td>
            </tr>
            <tr>
              <td>Pilihan Surat Suara</td>
              <td>Calon Nomor Urut <?= htmlspecialchars($vote_success['nomor_calon']) ?> — <?= htmlspecialchars($vote_success['nama_calon']) ?></td>
            </tr>
            <tr>
              <td>Waktu Pencatatan</td>
              <td><?= htmlspecialchars($vote_success['waktu']) ?></td>
            </tr>
            <tr>
              <td>Status Hak Suara</td>
              <td><span class="badge badge-success">Sudah Digunakan</span></td>
            </tr>
          </table>

          <div style="display: flex; gap: 10px; justify-content: center; margin-top: 24px;">
            <a href="logout.php?type=user" class="btn btn-primary btn-block">
              Selesai & Keluar dari Bilik Suara
            </a>
          </div>

          <div style="text-align: center; margin-top: 14px;">
            <a href="admin.php" style="font-size: 0.825rem; color: var(--gov-navy); text-decoration: underline;">
              Pantau Rekapitulasi Suara Sementara &rarr;
            </a>
          </div>
        </div>
      </div>

    <!-- TAMPILAN 2: BILIK SUARA (SURAT SUARA DENGAN 3 KOLOM RESMI) -->
    <?php elseif ($is_logged): ?>
      
      <!-- Kop Surat Suara Resmi -->
      <div class="ballot-header">
        <h2>Surat Suara Pemilihan Kepala Desa</h2>
        <p>Desa Sukamaju • Kecamatan Sukamaju • Kabupaten Bogor • Tahun 2026</p>
      </div>
      <div class="ballot-meta-bar">
        <span>Bilik Suara Elektronik — TPS 01</span>
        <span>Pilihan: Langsung, Umum, Bebas, Rahasia, Jujur dan Adil</span>
      </div>

      <!-- Kotak Surat Suara 3 Kolom -->
      <div class="ballot-wrapper">
        <div class="ballot-grid">
          <?php foreach ($calon_list as $c): ?>
            <div class="ballot-column">
              <!-- Nomor Urut -->
              <div class="ballot-col-number">
                <?= str_pad($c['nomor_urut'], 2, '0', STR_PAD_LEFT) ?>
              </div>

              <!-- Foto Kandidat -->
              <div class="ballot-photo-frame">
                <img 
                  src="<?= !empty($c['foto']) && file_exists($c['foto']) ? htmlspecialchars($c['foto']) : 'data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 240\' fill=\'%23cbd5e1\'><rect width=\'200\' height=\'240\' fill=\'%23f1f5f9\'/><circle cx=\'100\' cy=\'90\' r=\'45\'/><path d=\'M25 210 c0-45 45-65 75-65 s75 20 75 65\'/></svg>' ?>" 
                  alt="<?= htmlspecialchars($c['nama']) ?>" 
                  class="ballot-photo"
                >
              </div>

              <!-- Nama & Visi -->
              <div class="ballot-info">
                <div class="ballot-candidate-name">
                  <?= htmlspecialchars($c['nama']) ?>
                </div>

                <div class="ballot-visi-box">
                  <div class="ballot-visi-title">Visi & Program Kerja</div>
                  <div><?= nl2br(htmlspecialchars($c['visi_misi'])) ?></div>
                </div>

                <!-- Area Coblos -->
                <div class="ballot-coblos-area">
                  <button 
                    type="button" 
                    class="btn-coblos"
                    onclick="bukaKonfirmasi(<?= $c['id'] ?>, '<?= $c['nomor_urut'] ?>', '<?= addslashes(htmlspecialchars($c['nama'])) ?>', '<?= addslashes(htmlspecialchars($c['foto'])) ?>')"
                  >
                    Coblos Calon No. <?= $c['nomor_urut'] ?>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Modal Dialog Konfirmasi Coblos -->
      <div id="modalKonfirmasi" class="modal-overlay">
        <div class="modal-card">
          <div class="modal-title">Konfirmasi Pemberian Suara</div>
          <p class="modal-desc">
            Periksa kembali pilihan Anda. Setelah dikonfirmasi, suara akan tercatat sah dan tidak dapat diubah kembali.
          </p>

          <div class="modal-preview-box">
            <img id="konfirmasiImg" src="" alt="Foto" class="modal-preview-img">
            <div>
              <div id="konfirmasiNomor" style="font-size: 0.75rem; font-weight: 700; color: var(--gov-gold); text-transform: uppercase;">CALON NO. 01</div>
              <div id="konfirmasiNama" style="font-size: 1rem; font-weight: 800; color: var(--gov-navy);">Nama Calon</div>
            </div>
          </div>

          <form method="POST" action="index.php">
            <input type="hidden" name="action" value="submit_vote">
            <input type="hidden" name="calon_id" id="konfirmasiCalonId" value="">

            <div style="display: flex; gap: 10px;">
              <button type="button" class="btn btn-secondary btn-block" onclick="tutupKonfirmasi()">
                Batal
              </button>
              <button type="submit" class="btn btn-primary btn-block">
                Ya, Konfirmasi Suara Sah
              </button>
            </div>
          </form>
        </div>
      </div>

      <script>
        function bukaKonfirmasi(id, nomor, nama, foto) {
          document.getElementById('konfirmasiCalonId').value = id;
          document.getElementById('konfirmasiNomor').innerText = 'CALON NOMOR URUT ' + String(nomor).padStart(2, '0');
          document.getElementById('konfirmasiNama').innerText = nama;
          document.getElementById('konfirmasiImg').src = foto ? foto : 'data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\' fill=\'%23cbd5e1\'><rect width=\'100\' height=\'100\' fill=\'%23f1f5f9\'/></svg>';
          document.getElementById('modalKonfirmasi').classList.add('active');
        }

        function tutupKonfirmasi() {
          document.getElementById('modalKonfirmasi').classList.remove('active');
        }

        window.addEventListener('click', function(e) {
          const m = document.getElementById('modalKonfirmasi');
          if (e.target === m) tutupKonfirmasi();
        });
      </script>

    <!-- TAMPILAN 3: VERIFIKASI NIK (HALAMAN DEPAN) -->
    <?php else: ?>
      
      <div class="gov-auth-card">
        <div class="gov-auth-header">
          <h3>Verifikasi Pemilih Tetap (DPT)</h3>
          <p>Masukkan 16 Digit NIK yang terdaftar pada KTP-el / KK</p>
        </div>

        <div class="gov-auth-body">
          <form method="POST" action="index.php">
            <input type="hidden" name="action" value="login_nik">

            <div class="form-group">
              <label class="form-label" for="nik">Nomor Induk Kependudukan (NIK)</label>
              <input 
                type="text" 
                id="nik" 
                name="nik" 
                class="form-input form-input-nik" 
                placeholder="Contoh: 3201010101900001" 
                maxlength="20"
                required
                autofocus
                autocomplete="off"
              >
            </div>

            <button type="submit" class="btn btn-navy btn-block btn-lg">
              Buka Surat Suara
            </button>
          </form>

          <!-- Accordion Data NIK DPT untuk Pengujian -->
          <details class="test-nik-accordion">
            <summary class="test-nik-summary">
              Daftar NIK DPT untuk Simulasi Pengujian (Klik untuk Buka)
            </summary>
            <div class="test-nik-list">
              <?php foreach ($dptSamples as $u): ?>
                <div class="test-nik-item" onclick="isiNik('<?= $u['nik'] ?>')">
                  <div>
                    <code><?= $u['nik'] ?></code> — <span><?= htmlspecialchars($u['nama']) ?></span>
                  </div>
                  <div>
                    <?php if ($u['status_vote']): ?>
                      <span class="badge badge-neutral">Sudah Memilih</span>
                    <?php else: ?>
                      <span class="badge badge-success">Siap Memilih</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        </div>
      </div>

      <script>
        function isiNik(nik) {
          const input = document.getElementById('nik');
          if (input) {
            input.value = nik;
            input.focus();
          }
        }
      </script>

    <?php endif; ?>

  </main>

  <!-- Footer Resmi -->
  <footer class="gov-footer">
    <div class="container">
      Panitia Pemilihan Kepala Desa (P2KD) Desa Sukamaju &copy; 2026. Aplikasi Pemungutan Suara Elektronik Native PHP & MySQL.
    </div>
  </footer>

</body>
</html>
