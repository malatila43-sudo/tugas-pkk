<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/stok.php';

// Pemilik barang hanya melihat katalog, tidak bisa menyewa barang sendiri
$adalah_mitra = sudah_login() && role_user() === 'mitra';

$keyword = trim($_GET['q'] ?? '');

$stok_sql = sql_stok_hari_ini('p');

$sql = "SELECT p.*, {$stok_sql} AS stok_tersedia FROM pancingan p";
if ($keyword !== '') {
    $cari = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    $sql .= " WHERE p.nama LIKE ? OR p.deskripsi LIKE ?";
}
$sql .= " ORDER BY p.harga ASC, p.id ASC";

$stmt = $koneksi->prepare($sql);
if ($keyword !== '') {
    $stmt->bind_param('ss', $cari, $cari);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$total = count($items);

$page_title  = 'Katalog Sewa';
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'katalog';
include __DIR__ . '/../includes/header.php';
?>

    <section class="page-banner">
        <h1>Katalog Sewa Pancingan</h1>
        <p>Pilih joran impianmu, sewa dengan mudah dan cepat</p>
    </section>

    <?php if ($adalah_mitra): ?>
        <div class="alert-msg info flash">
            Kamu masuk sebagai <strong>Pemilik Barang</strong>, jadi tidak bisa menyewa barang di katalog ini.
            <a href="<?= base_url('pages/barang_saya.php'); ?>">Kelola barang kamu di sini.</a>
        </div>
    <?php elseif (!sudah_login()): ?>
        <div class="alert-msg info flash">
            Untuk menyewa, <a href="<?= base_url('pages/login.php?role=penyewa'); ?>">masuk</a> atau
            <a href="<?= base_url('pages/daftar.php?role=penyewa'); ?>">daftar akun penyewa</a> dulu ya.
        </div>
    <?php endif; ?>

    <section class="katalog">
        <form class="cari-bar" method="GET" action="<?= base_url('pages/katalog.php'); ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama atau deskripsi pancingan...">
            <button type="submit" class="btn">Cari</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= base_url('pages/katalog.php'); ?>" class="btn btn-soft">Reset</a>
            <?php endif; ?>
        </form>

        <?php if ($keyword !== ''): ?>
            <p class="info-hasil">Menampilkan hasil untuk &ldquo;<?= htmlspecialchars($keyword); ?>&rdquo; &mdash; <?= $total; ?> barang ditemukan.</p>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <p class="kosong">Barang tidak ditemukan. Coba kata kunci lain.</p>
        <?php else: ?>
            <div class="card-container">
                <?php foreach ($items as $barang): ?>
                    <?php
                    $stok_total    = (int) $barang['stok'];
                    $stok_tersedia = (int) $barang['stok_tersedia'];
                    ?>
                    <div class="card">
                        <?= tag_gambar($barang['gambar'], $barang['nama']) ?>
                        <h3><?= htmlspecialchars($barang['nama']); ?></h3>
                        <p class="deskripsi"><?= htmlspecialchars($barang['deskripsi']); ?></p>
                        <p class="harga">Rp<?= number_format($barang['harga'], 0, ',', '.'); ?> /hari</p>
                        <p class="stok">
                            Stok total: <?= $stok_total; ?> unit &middot;
                            Tersedia hari ini: <?= $stok_tersedia; ?> unit
                        </p>

                        <?php $bisa_kelola = $adalah_mitra && (int) ($barang['owner_id'] ?? 0) === id_user(); ?>
                        <?php if ($adalah_mitra): ?>
                            <?php if ($bisa_kelola): ?>
                                <a href="<?= base_url('pages/barang_saya.php?edit=' . (int) $barang['id']); ?>" class="btn btn-soft">
                                    <?= $stok_total > 0 ? 'Kelola Barang Saya' : 'Tambah Stok'; ?>
                                </a>
                            <?php else: ?>
                                <span class="btn disabled">Tidak Bisa Disewa</span>
                            <?php endif; ?>
                        <?php elseif ($stok_total > 0): ?>
                            <?php
                            $url_sewa = base_url('pages/sewa.php?id=' . (int) $barang['id']);
                            if (!sudah_login()) {
                                $url_sewa = base_url('pages/login.php?role=penyewa&redirect=' . rawurlencode($url_sewa));
                            }
                            ?>
                            <a href="<?= htmlspecialchars($url_sewa); ?>" class="btn">Sewa Sekarang</a>
                        <?php else: ?>
                            <p class="stok habis">Stok habis</p>
                            <span class="btn disabled">Stok Habis</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
