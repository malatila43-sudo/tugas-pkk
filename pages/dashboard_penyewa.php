<?php
// ============================================
// KAILKILAT.COM - Dashboard Penyewa
// Halaman ini KHUSUS untuk akun penyewa.
// Pemilik barang (mitra) otomatis dialihkan ke dashboard miliknya.
// ============================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_role('penyewa');
cek_csrf();

$uid = id_user();

$msg = '';
$msg_type = 'info';

// ---------- Aksi: batalkan penyewaan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'batal') {
    $id_sewa = (int) ($_POST['id_sewa'] ?? 0);

    $koneksi->begin_transaction();
    try {
        $cek = $koneksi->prepare("SELECT * FROM sewa WHERE id = ? AND user_id = ? FOR UPDATE");
        $cek->bind_param('ii', $id_sewa, $uid);
        $cek->execute();
        $row = $cek->get_result()->fetch_assoc();
        $cek->close();

        if (!$row) {
            $koneksi->rollback();
            $msg = 'Penyewaan yang dimaksud tidak ditemukan.';
            $msg_type = 'error';
        } elseif (($row['status'] ?? '') === 'selesai' || $row['tanggal_selesai'] < date('Y-m-d')) {
            $koneksi->rollback();
            $msg = 'Penyewaan yang sudah selesai tidak bisa dibatalkan.';
            $msg_type = 'error';
        } else {
            // Stok tidak perlu diubah manual. Karena ketersediaan dihitung
            // dari sisa baris sewa yang aktif, menghapus baris ini otomatis
            // membuat unitnya bisa disewa lagi.
            $hapus = $koneksi->prepare("DELETE FROM sewa WHERE id = ? AND user_id = ?");
            $hapus->bind_param('ii', $id_sewa, $uid);
            $hapus->execute();
            $hapus->close();

            $koneksi->commit();
            $msg = 'Penyewaan dibatalkan. Unit barang otomatis tersedia lagi untuk tanggal tersebut.';
            $msg_type = 'success';
        }
    } catch (Throwable $e) {
        $koneksi->rollback();
        $msg = 'Terjadi kesalahan saat memproses. Silakan coba lagi.';
        $msg_type = 'error';
    }
}

// ---------- Data untuk halaman ----------
$stmt = $koneksi->prepare(
    "SELECT
        COUNT(*) AS total_pesanan,
        COALESCE(SUM(total_harga), 0) AS total_biaya,
        COALESCE(SUM(CASE WHEN COALESCE(status, CASE WHEN tanggal_mulai > CURDATE() THEN 'akan' WHEN tanggal_selesai >= CURDATE() THEN 'berjalan' ELSE 'selesai' END) = 'akan' THEN 1 ELSE 0 END), 0) AS akan,
        COALESCE(SUM(CASE WHEN COALESCE(status, CASE WHEN tanggal_mulai > CURDATE() THEN 'akan' WHEN tanggal_selesai >= CURDATE() THEN 'berjalan' ELSE 'selesai' END) = 'berjalan' THEN 1 ELSE 0 END), 0) AS berjalan,
        COALESCE(SUM(CASE WHEN COALESCE(status, CASE WHEN tanggal_mulai > CURDATE() THEN 'akan' WHEN tanggal_selesai >= CURDATE() THEN 'berjalan' ELSE 'selesai' END) = 'selesai' THEN 1 ELSE 0 END), 0) AS selesai
     FROM sewa
     WHERE user_id = ?"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$stat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $koneksi->prepare(
    "SELECT s.id, s.tanggal_mulai, s.tanggal_selesai, s.jumlah, s.total_harga,
            p.nama AS nama_barang,
            DATEDIFF(s.tanggal_selesai, s.tanggal_mulai) + 1 AS lama_hari,
            COALESCE(s.status, CASE
                WHEN s.tanggal_mulai > CURDATE() THEN 'akan'
                WHEN s.tanggal_selesai >= CURDATE() THEN 'berjalan'
                ELSE 'selesai'
            END) AS status
     FROM sewa s
     JOIN pancingan p ON p.id = s.pancingan_id
     WHERE s.user_id = ?
     ORDER BY s.id DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$riwayat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title  = 'Dashboard Penyewa';
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'dashboard_penyewa';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-banner">
    <h1>Halo, <?= htmlspecialchars(nama_user()); ?>!</h1>
    <p>Khusus akun <strong>Penyewa</strong>. Semua pemesanan pancingan kamu ada di sini.</p>
</section>

<div class="wrap">
    <?php if ($msg !== ''): ?>
        <div class="alert-msg <?= $msg_type; ?>"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="grid-stat">
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $stat['total_pesanan']; ?></span>
            <span class="stat-label">Total Penyewaan</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $stat['berjalan']; ?></span>
            <span class="stat-label">Sedang Berjalan</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $stat['akan']; ?></span>
            <span class="stat-label">Belum Mulai</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $stat['selesai']; ?></span>
            <span class="stat-label">Sudah Selesai</span>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title">Total Pengeluaran</h2>
        <p class="total-besar">Rp<?= number_format((int) $stat['total_biaya'], 0, ',', '.'); ?></p>
        <a href="<?= base_url('pages/katalog.php'); ?>" class="btn">Sewa Pancingan Lagi</a>
    </div>

    <div class="panel">
        <h2 class="panel-title">Riwayat Sewa Saya</h2>

        <?php if (empty($riwayat)): ?>
            <p class="kosong">Kamu belum pernah menyewa. Yuk pilih joran favoritmu di katalog.</p>
            <a href="<?= base_url('pages/katalog.php'); ?>" class="btn">Buka Katalog</a>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Durasi</th>
                            <th>Jumlah</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): ?>
                            <?php
                            $status = (string) ($r['status'] ?? 'akan');
                            $label  = $status === 'akan' ? 'Belum Mulai'
                                    : ($status === 'berjalan' ? 'Barang Sudah Diambil' : 'Barang Sudah Dikembalikan');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nama_barang']); ?></td>
                                <td><?= htmlspecialchars($r['tanggal_mulai']); ?></td>
                                <td><?= htmlspecialchars($r['tanggal_selesai']); ?></td>
                                <td><?= (int) $r['lama_hari']; ?> hari</td>
                                <td><?= (int) $r['jumlah']; ?></td>
                                <td>Rp<?= number_format((int) $r['total_harga'], 0, ',', '.'); ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($status); ?>"><?= $label; ?></span></td>
                                <td>
                                    <?php if ($status !== 'selesai'): ?>
                                        <form action="" method="POST" onsubmit="return confirm('Batalkan penyewaan ini? Unit barang akan otomatis tersedia lagi.');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="aksi" value="batal">
                                            <input type="hidden" name="id_sewa" value="<?= (int) $r['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-kecil">Batalkan</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="teks-kecil">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>