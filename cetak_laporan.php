<?php
/**
 * cetak_laporan.php - Dokumen Resmi Berita Acara & Sertifikat Rekapitulasi Suara (Model C1-KWK Pilkades)
 * Didesain khusus untuk dicetak atau di-download langsung sebagai file PDF berkualitas A4.
 */
require_once 'koneksi.php';

// Pastikan hanya admin yang bisa mencetak / mengakses laporan resmi
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: admin.php');
    exit;
}

// Ambil Data Calon & Suara
$stmt = $pdo->query("SELECT * FROM calon ORDER BY nomor_urut ASC");
$calon_list = $stmt->fetchAll();

// Total Suara
$total_suara_masuk = 0;
foreach ($calon_list as $c) {
    $total_suara_masuk += $c['jumlah_suara'];
}

// Urutkan untuk menentukan Terbanyak & Terkecil
$calon_sorted = $calon_list;
usort($calon_sorted, function($a, $b) {
    return $b['jumlah_suara'] <=> $a['jumlah_suara'];
});

$calon_terbanyak = !empty($calon_sorted) ? $calon_sorted[0] : null;
$calon_terkecil  = !empty($calon_sorted) ? $calon_sorted[count($calon_sorted) - 1] : null;

// Cek apakah ada seri (draw) di posisi tertinggi
$is_draw = false;
if ($total_suara_masuk > 0 && count($calon_sorted) > 1) {
    if ($calon_sorted[0]['jumlah_suara'] === $calon_sorted[1]['jumlah_suara'] && $calon_sorted[0]['jumlah_suara'] > 0) {
        $is_draw = true;
    }
}

// Data Pemilih DPT
$total_dpt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$dpt_hadir = $pdo->query("SELECT COUNT(*) FROM users WHERE status_vote = 1")->fetchColumn();
$dpt_absen = $total_dpt - $dpt_hadir;
$persen_partisipasi = $total_dpt > 0 ? round(($dpt_hadir / $total_dpt) * 100, 2) : 0;

$nomor_berita_acara = "BA/PLK-SKM/" . date('Y') . "/" . str_pad(rand(10, 99), 3, '0', STR_PAD_LEFT);
$tanggal_cetak = date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Berita Acara Rekapitulasi Suara Pilkades - Desa Sukamaju</title>
  <style>
    /* Styling Cetak Standar Kertas A4 Resmi */
    @page {
      size: A4 portrait;
      margin: 15mm 15mm 15mm 15mm;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Times New Roman', Times, serif;
      color: #000000;
      background: #e2e8f0;
      font-size: 11pt;
      line-height: 1.4;
      padding: 20px;
    }

    .no-print-bar {
      max-width: 210mm;
      margin: 0 auto 15px auto;
      background: #0f2744;
      color: #ffffff;
      padding: 12px 20px;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: system-ui, -apple-system, sans-serif;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .btn-action {
      background: #15803d;
      color: #ffffff;
      padding: 8px 16px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.85rem;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-action:hover {
      background: #166534;
    }

    .btn-back {
      background: #475569;
      color: #ffffff;
      padding: 8px 14px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .btn-back:hover {
      background: #334155;
    }

    /* Kertas Dokumen */
    .paper {
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      background: #ffffff;
      padding: 20mm;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.15);
    }

    /* Kop Surat Resmi */
    .kop-surat {
      text-align: center;
      border-bottom: 3px double #000000;
      padding-bottom: 10px;
      margin-bottom: 16px;
    }

    .kop-surat h4 {
      font-size: 12pt;
      font-weight: normal;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .kop-surat h3 {
      font-size: 14pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .kop-surat h2 {
      font-size: 15pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      margin: 2px 0;
    }

    .kop-surat p {
      font-size: 9.5pt;
      font-style: italic;
    }

    /* Judul Berita Acara */
    .doc-title {
      text-align: center;
      margin-bottom: 16px;
    }

    .doc-title h3 {
      font-size: 13pt;
      font-weight: bold;
      text-decoration: underline;
      text-transform: uppercase;
      margin-bottom: 3px;
    }

    .doc-title p {
      font-size: 10pt;
    }

    /* Paragraf Pembuka */
    .doc-intro {
      text-align: justify;
      text-indent: 30px;
      margin-bottom: 14px;
      font-size: 10.5pt;
    }

    /* Tabel Dokumen */
    .table-doc {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
      font-size: 10pt;
    }

    .table-doc th, .table-doc td {
      border: 1px solid #000000;
      padding: 6px 8px;
      vertical-align: middle;
    }

    .table-doc th {
      background-color: #f1f5f9;
      font-weight: bold;
      text-align: center;
    }

    .table-heading {
      font-weight: bold;
      margin: 12px 0 6px 0;
      font-size: 10.5pt;
    }

    /* Kotak Pengesahan Calon Terpilih */
    .box-penetapan {
      border: 2px solid #000000;
      padding: 10px 14px;
      margin-bottom: 18px;
      background: #fafafa;
      font-size: 10.5pt;
    }

    .box-penetapan strong {
      font-size: 11pt;
    }

    /* Area Tanda Tangan */
    .ttd-container {
      margin-top: 24px;
      page-break-inside: avoid;
    }

    .ttd-header-date {
      text-align: right;
      margin-bottom: 16px;
      font-size: 10.5pt;
    }

    .ttd-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      text-align: center;
    }

    .ttd-box {
      margin-bottom: 24px;
    }

    .ttd-space {
      height: 60px;
    }

    .ttd-name {
      font-weight: bold;
      text-decoration: underline;
    }

    .saksi-title {
      font-weight: bold;
      text-align: center;
      margin: 10px 0 6px 0;
      font-size: 10.5pt;
    }

    .saksi-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      text-align: center;
      margin-top: 10px;
    }

    /* Print Media Queries */
    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .no-print-bar {
        display: none !important;
      }
      .paper {
        width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>

  <!-- Bar Kontrol Navigasi (Hanya tampil di layar, otomatis disembunyikan saat dicetak) -->
  <div class="no-print-bar">
    <div>
      <strong>Laporan Berita Acara Rekapitulasi Pilkades (Model C1-KWK)</strong>
      <div style="font-size: 0.775rem; color: #cbd5e1;">Siap diunduh sebagai PDF atau dicetak langsung</div>
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="admin.php" class="btn-back">&larr; Kembali ke Admin</a>
      <button onclick="window.print()" class="btn-action">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"></polyline>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
          <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        Download / Cetak PDF
      </button>
    </div>
  </div>

  <!-- Lembar Dokumen Resmi Kertas A4 -->
  <div class="paper">
    
    <!-- Kop Surat Resmi -->
    <div class="kop-surat">
      <h4>Pemerintah Kabupaten Bogor</h4>
      <h3>Kecamatan Sukamaju</h3>
      <h2>Panitia Pemilihan Kepala Desa (P2KD)</h2>
      <p>Sekretariat: Jl. Raya Sukamaju No. 01, Desa Sukamaju, Kec. Sukamaju, Kab. Bogor 16830</p>
    </div>

    <!-- Judul Berita Acara -->
    <div class="doc-title">
      <h3>Berita Acara Rekapitulasi Hasil Penghitungan Suara</h3>
      <p>Nomor: <?= $nomor_berita_acara ?></p>
      <p style="font-weight: bold; margin-top: 2px;">Pemilihan Kepala Desa Sukamaju Periode 2026 - 2032</p>
    </div>

    <!-- Paragraf Pendahuluan -->
    <p class="doc-intro">
      Pada hari ini, <strong><?= date('l') ?></strong> tanggal <strong><?= date('d') ?></strong> bulan <strong><?= date('F') ?></strong> tahun <strong><?= date('Y') ?></strong>, Panitia Pemilihan Kepala Desa (P2KD) Desa Sukamaju telah melaksanakan rapat pleno rekapitulasi hasil penghitungan suara Pemilihan Kepala Desa serentak secara elektronik (E-Voting) bertempat di TPS 01 Desa Sukamaju, dengan rincian data sebagai berikut:
    </p>

    <!-- 1. Data Pemilih & Penggunaan Hak Suara -->
    <div class="table-heading">I. DATA PEMILIH DAN PENGGUNAAN HAK SUARA</div>
    <table class="table-doc">
      <thead>
        <tr>
          <th style="width: 40px;">No.</th>
          <th>Uraian Rekapitulasi Pemilih</th>
          <th style="width: 140px;">Jumlah</th>
          <th style="width: 140px;">Persentase</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="text-align: center;">1</td>
          <td>Jumlah Pemilih Terdaftar dalam DPT</td>
          <td style="text-align: right; font-weight: bold;"><?= number_format($total_dpt) ?> Orang</td>
          <td style="text-align: right;">100.00%</td>
        </tr>
        <tr>
          <td style="text-align: center;">2</td>
          <td>Jumlah Pemilih yang Menggunakan Hak Suara (Hadir)</td>
          <td style="text-align: right; font-weight: bold;"><?= number_format($dpt_hadir) ?> Orang</td>
          <td style="text-align: right; font-weight: bold;"><?= $persen_partisipasi ?>%</td>
        </tr>
        <tr>
          <td style="text-align: center;">3</td>
          <td>Jumlah Pemilih yang Tidak Menggunakan Hak Suara (Tidak Hadir)</td>
          <td style="text-align: right;"><?= number_format($dpt_absen) ?> Orang</td>
          <td style="text-align: right;"><?= round(100 - $persen_partisipasi, 2) ?>%</td>
        </tr>
      </tbody>
    </table>

    <!-- 2. Rincian Perolehan Suara Calon (Terbanyak & Terkecil) -->
    <div class="table-heading">II. RINCIAN PEROLEHAN SUARA SAH MASING-MASING KANDIDAT</div>
    <table class="table-doc">
      <thead>
        <tr>
          <th style="width: 50px;">No. Urut</th>
          <th>Nama Lengkap Kandidat Calon</th>
          <th style="width: 130px;">Jumlah Perolehan Suara</th>
          <th style="width: 110px;">Persentase</th>
          <th style="width: 140px;">Keterangan / Peringkat</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($calon_sorted as $index => $c): 
          $persen = $total_suara_masuk > 0 ? round(($c['jumlah_suara'] / $total_suara_masuk) * 100, 2) : 0;
          $rank = $index + 1;
        ?>
          <tr>
            <td style="text-align: center; font-weight: bold;"><?= str_pad($c['nomor_urut'], 2, '0', STR_PAD_LEFT) ?></td>
            <td><strong><?= htmlspecialchars($c['nama']) ?></strong></td>
            <td style="text-align: right; font-weight: bold;"><?= number_format($c['jumlah_suara']) ?> Suara</td>
            <td style="text-align: right; font-weight: bold;"><?= $persen ?>%</td>
            <td style="text-align: center; font-size: 9pt;">
              <?php if ($rank === 1 && $c['jumlah_suara'] > 0 && !$is_draw): ?>
                <strong>Suara Terbanyak (Juara 1)</strong>
              <?php elseif ($rank === count($calon_sorted) && $c['jumlah_suara'] > 0): ?>
                Suara Terkecil (Peringkat <?= $rank ?>)
              <?php else: ?>
                Peringkat <?= $rank ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr style="background: #f8fafc; font-weight: bold;">
          <td colspan="2" style="text-align: center;">TOTAL SELURUH SUARA SAH MASUK</td>
          <td style="text-align: right;"><?= number_format($total_suara_masuk) ?> Suara</td>
          <td style="text-align: right;">100.00%</td>
          <td style="text-align: center;">SAH</td>
        </tr>
      </tbody>
    </table>

    <!-- 3. Penetapan Hasil / Pemenang -->
    <div class="box-penetapan">
      <strong>III. PENETAPAN HASIL PEMILIHAN KEPALA DESA:</strong>
      <div style="margin-top: 6px;">
        <?php if ($total_suara_masuk == 0): ?>
          Berdasarkan hasil rekapitulasi, saat ini kotak suara elektronik belum memiliki suara masuk.
        <?php elseif ($is_draw): ?>
          Berdasarkan rekapitulasi perolehan suara, terjadi <strong>posisi perolehan suara seimbang (draw)</strong> pada perolehan suara tertinggi. Keputusan lanjutan merujuk pada regulasi Peraturan Daerah tentang Pilkades.
        <?php elseif ($calon_terbanyak): ?>
          Berdasarkan perolehan suara sah terbanyak, Panitia Pemilihan menetapkan:
          <div style="margin: 6px 0; padding-left: 14px;">
            <div>Nama Calon Terpilih : <strong><?= htmlspecialchars($calon_terbanyak['nama']) ?></strong></div>
            <div>Nomor Urut : <strong><?= $calon_terbanyak['nomor_urut'] ?></strong></div>
            <div>Jumlah Suara Sah : <strong><?= number_format($calon_terbanyak['jumlah_suara']) ?> Suara (<?= round(($calon_terbanyak['jumlah_suara'] / $total_suara_masuk) * 100, 2) ?>%)</strong></div>
            <div>Status : <strong>CALON KEPALA DESA TERPILIH PERIODE 2026 - 2032</strong></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4. Area Pengesahan & Tanda Tangan -->
    <div class="ttd-container">
      <div class="ttd-header-date">
        Ditetapkan di: Sukamaju<br>
        Pada tanggal: <?= $tanggal_cetak ?>
      </div>

      <div class="ttd-grid">
        <div class="ttd-box">
          <div>Mengetahui,</div>
          <div><strong>Ketua BPD Desa Sukamaju</strong></div>
          <div class="ttd-space"></div>
          <div class="ttd-name">Drs. H. Mulyadi, M.Si</div>
          <div>NIP. 19740512 199903 1 002</div>
        </div>

        <div class="ttd-box">
          <div>Panitia Pemilihan Kepala Desa (P2KD)</div>
          <div><strong>Ketua Panitia</strong></div>
          <div class="ttd-space"></div>
          <div class="ttd-name">Ir. H. Supriyanto</div>
        </div>
      </div>

      <div class="saksi-title">SAKSI-SAKSI KANDIDAT CALON KEPALA DESA:</div>
      <div class="saksi-grid">
        <div>
          <div>Saksi Calon No. 01</div>
          <div style="height: 45px;"></div>
          <div style="text-decoration: underline; font-weight: bold;">( .................................... )</div>
        </div>
        <div>
          <div>Saksi Calon No. 02</div>
          <div style="height: 45px;"></div>
          <div style="text-decoration: underline; font-weight: bold;">( .................................... )</div>
        </div>
        <div>
          <div>Saksi Calon No. 03</div>
          <div style="height: 45px;"></div>
          <div style="text-decoration: underline; font-weight: bold;">( .................................... )</div>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
