<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/stok.php';

// Ambil data barang langsung dari database supaya beranda sinkron dengan katalog.
// Stok tersedia dihitung dari tanggal, bukan dari stok yang dikurangi manual.
$stok_hari_ini = sql_stok_hari_ini('p');
$result = $koneksi->query(
    "SELECT p.id, p.nama, p.deskripsi, p.harga, p.gambar, p.stok,
            {$stok_hari_ini} AS stok_tersedia
     FROM pancingan p
     WHERE p.stok > 0
     ORDER BY p.id ASC
     LIMIT 3"
);
$barang = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$stat = ['jumlah' => 0, 'harga_min' => 0];
$stat_res = $koneksi->query("SELECT COUNT(*) AS jumlah, COALESCE(MIN(harga), 0) AS harga_min FROM pancingan WHERE stok > 0");
if ($stat_res && ($stat_row = $stat_res->fetch_assoc())) {
    $stat = $stat_row;
}

$page_title  = 'Sewa Pancingan';
$page_css    = ['kp.css', 'katalog.css'];
$active_menu = 'beranda';
$hide_footer = true;
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-text">
        <h1>Sewa Pancingan Impianmu</h1>
        <p>Cari joran favoritmu. Mudah, cepat, dan terpercaya!</p>
        <?php if (sudah_login()): ?>
            <p>
                Halo, <strong><?= htmlspecialchars(nama_user()); ?></strong>
                (<?= role_user() === 'mitra' ? 'Pemilik Barang' : 'Penyewa' ?>)
            </p>
            <a href="<?= url_dashboard_manusia(); ?>" class="btn">Buka Dashboard Saya</a>
        <?php else: ?>
            <a href="<?= base_url('pages/katalog.php'); ?>" class="btn">Mulai Sewa</a>
        <?php endif; ?>
    </div>
</section>

<section class="info-singkat">
    <div class="info-kartu">
        <h3><?= (int) $stat['jumlah']; ?> Jenis Pancingan</h3>
        <p>Dari joran laut profesional sampai set lengkap untuk pemula.</p>
    </div>
    <div class="info-kartu">
        <h3>Harga Murah</h3>
        <p>Mulai dari Rp<?= number_format((int) $stat['harga_min'], 0, ',', '.'); ?> per hari, tanpa biaya tersembunyi.</p>
    </div>
    <div class="info-kartu">
        <h3>Stok Terjaga</h3>
        <p>Ketersediaan dihitung otomatis sesuai tanggal sewa yang kamu pilih.</p>
    </div>
</section>

<section class="cards">
    <h2>Jenis Pancingan</h2>
    <?php if (empty($barang)): ?>
        <p class="kosong">Belum ada barang yang tersedia.</p>
    <?php else: ?>
        <div class="card-container">
            <?php foreach ($barang as $b): ?>
                <div class="card">
                    <?= tag_gambar($b['gambar'], $b['nama']) ?>
                    <h3><?= htmlspecialchars($b['nama']); ?></h3>
                    <p class="deskripsi"><?= htmlspecialchars($b['deskripsi']); ?></p>
                    <p class="harga">Rp<?= number_format($b['harga'], 0, ',', '.'); ?> /hari</p>
                    <p class="stok">Stok total: <?= (int) $b['stok']; ?> unit &middot; Tersedia hari ini: <?= (int) $b['stok_tersedia']; ?> unit</p>
                    <a href="<?= base_url('pages/sewa.php?id=' . (int) $b['id']); ?>" class="btn">Sewa</a>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:25px;">
            <a href="<?= base_url('pages/katalog.php'); ?>" class="btn btn-soft">Lihat Semua Katalog</a>
        </p>
    <?php endif; ?>
</section>

<section class="cta-akhir">
    <h2>Punya Pertanyaan?</h2>
    <p>Pilih joran yang paling cocok, lalu sewa langsung dari dashboard penyewa kamu.</p>
    <a href="<?= base_url('pages/katalog.php'); ?>" class="btn">Lihat Katalog</a>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
