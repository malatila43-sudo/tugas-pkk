<?php
// ============================================
// KAILKILAT.COM - Dashboard Pemilik Barang (Mitra)
// Halaman ini KHUSUS untuk akun pemilik barang.
// Penyewa yang membuka halaman ini dialihkan ke dashboard penyewa.
// ============================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_role('mitra');
cek_csrf();

$uid = id_user();

$msg     = '';
$msg_type = 'info';

// ---------- Aksi kotak masuk pesan ----------

// Tandai satu pesan sudah dibaca
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'baca') {
    $id_pesan = (int) ($_POST['id_pesan'] ?? 0);

    $stmt = $koneksi->prepare("UPDATE pesan_kontak SET dibaca = 1 WHERE id = ? AND mitra_id = ?");
    $stmt->bind_param('ii', $id_pesan, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . base_url('pages/dashboard_mitra.php') . '#kotak-masuk');
    exit;
}

// Tandai semua pesan sudah dibaca sekaligus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'baca_semua') {
    $stmt = $koneksi->prepare("UPDATE pesan_kontak SET dibaca = 1 WHERE mitra_id = ? AND dibaca = 0");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . base_url('pages/dashboard_mitra.php') . '#kotak-masuk');
    exit;
}

// Hapus satu pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_pesan') {
    $id_pesan = (int) ($_POST['id_pesan'] ?? 0);

    $stmt = $koneksi->prepare("DELETE FROM pesan_kontak WHERE id = ? AND mitra_id = ?");
    $stmt->bind_param('ii', $id_pesan, $uid);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = 'Pesan sudah dihapus.';
        $msg_type = 'success';
    } else {
        $msg = 'Pesan tidak bisa dihapus (bukan pesan kamu).';
        $msg_type = 'error';
    }
    $stmt->close();
}

// Ubah status pesanan masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ubah_status') {
    $id_sewa = (int) ($_POST['id_sewa'] ?? 0);
    $status_baru = (string) ($_POST['status'] ?? '');
    $status_boleh = ['akan', 'berjalan', 'selesai'];

    if (!in_array($status_baru, $status_boleh, true)) {
        $msg = 'Status tidak valid.';
        $msg_type = 'error';
    } else {
        $stmt = $koneksi->prepare(
            "UPDATE sewa s
             JOIN pancingan p ON p.id = s.pancingan_id
             SET s.status = ?
             WHERE s.id = ? AND p.owner_id = ?"
        );
        $stmt->bind_param('sii', $status_baru, $id_sewa, $uid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = 'Status pesanan diperbarui.';
            $msg_type = 'success';
        } elseif ($stmt->errno === 0) {
            $msg = 'Status pesanan sudah tersimpan.';
            $msg_type = 'info';
        } else {
            $msg = 'Status pesanan gagal diperbarui.';
            $msg_type = 'error';
        }
        $stmt->close();
    }
}

// ---------- Ambil semua pesan masuk ----------
$stmt = $koneksi->prepare(
    "SELECT id, nama, email, subjek, pesan, dibaca, created_at,
            TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS menit_lalu
     FROM pesan_kontak
     WHERE mitra_id = ?
     ORDER BY dibaca ASC, created_at DESC
     LIMIT 50"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$pesan_masuk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pesan_baru = pesan_belum_dibaca($uid);

// ---------- Ringkasan angka ----------
$stmt = $koneksi->prepare(
    "SELECT
        COUNT(*) AS jumlah_barang,
        COALESCE(SUM(stok), 0) AS total_stok
     FROM pancingan
     WHERE owner_id = ?"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$barang_stat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $koneksi->prepare(
    "SELECT
        COUNT(*) AS jumlah_pesanan,
        COALESCE(SUM(s.total_harga), 0) AS total_pendapatan
     FROM sewa s
     JOIN pancingan p ON p.id = s.pancingan_id
     WHERE p.owner_id = ?"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$pesanan_stat = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------- Pesanan masuk untuk barang milik saya ----------
$stmt = $koneksi->prepare(
    "SELECT s.id, s.tanggal_mulai, s.tanggal_selesai, s.jumlah, s.total_harga,
            p.nama AS nama_barang,
            s.nama_penyewa,
            s.email_penyewa,
            COALESCE(s.status, CASE
                WHEN s.tanggal_mulai > CURDATE() THEN 'akan'
                WHEN s.tanggal_selesai >= CURDATE() THEN 'berjalan'
                ELSE 'selesai'
            END) AS status
     FROM sewa s
     JOIN pancingan p ON p.id = s.pancingan_id
     WHERE p.owner_id = ?
     ORDER BY s.id DESC
     LIMIT 15"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Unit yang sedang disewa hari ini ----------
$stmt = $koneksi->prepare(
    "SELECT COALESCE(SUM(s.jumlah), 0) AS unit_disewa
     FROM sewa s
     JOIN pancingan p ON p.id = s.pancingan_id
     WHERE p.owner_id = ?
       AND CURDATE() BETWEEN s.tanggal_mulai AND s.tanggal_selesai"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$unit_disewa = (int) ($stmt->get_result()->fetch_assoc()['unit_disewa'] ?? 0);
$stmt->close();

// ---------- Daftar barang milik saya ----------
$stmt = $koneksi->prepare(
    "SELECT p.id, p.nama, p.harga, p.stok, p.gambar,
            (SELECT COUNT(*) FROM sewa s WHERE s.pancingan_id = p.id) AS jumlah_sewa,
            (SELECT COALESCE(SUM(s.jumlah), 0) FROM sewa s
             WHERE s.pancingan_id = p.id
               AND CURDATE() BETWEEN s.tanggal_mulai AND s.tanggal_selesai) AS unit_disewa
     FROM pancingan p
     WHERE p.owner_id = ?
     ORDER BY p.id DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$barang_saya = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title  = 'Dashboard Pemilik Barang';
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'dashboard_mitra';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-banner">
    <h1>Dashboard Pemilik Barang</h1>
    <p>Halo, <strong><?= htmlspecialchars(nama_user()); ?></strong>. Halaman ini khusus untuk pemilik barang (mitra).</p>
</section>

<div class="wrap">
    <div class="grid-stat">
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $barang_stat['jumlah_barang']; ?></span>
            <span class="stat-label">Jenis Barang Saya</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $barang_stat['total_stok']; ?></span>
            <span class="stat-label">Total Unit Stok</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= $unit_disewa; ?></span>
            <span class="stat-label">Unit Sedang Disewa</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $pesanan_stat['jumlah_pesanan']; ?></span>
            <span class="stat-label">Pesanan Masuk</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka">Rp<?= number_format((int) $pesanan_stat['total_pendapatan'], 0, ',', '.'); ?></span>
            <span class="stat-label">Total Pendapatan</span>
        </div>
        <div class="stat-card">
            <span class="stat-angka"><?= (int) $pesan_baru; ?></span>
            <span class="stat-label">Pesan Belum Dibaca</span>
        </div>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert-msg <?= $msg_type; ?>"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="panel" id="kotak-masuk">
        <h2 class="panel-title">
            Kotak Masuk Pesan
            <?php if ($pesan_baru > 0): ?>
                <span class="badge badge-akan"><?= (int) $pesan_baru; ?> baru</span>
            <?php endif; ?>
        </h2>
        <p>Pesan dari halaman <a href="<?= base_url('pages/kontak.php'); ?>" style="font-weight:600;">Kontak</a> dan notifikasi otomatis saat ada penyewaan baru.</p>

        <?php if (empty($pesan_masuk)): ?>
            <p class="kosong">Belum ada pesan masuk.</p>
        <?php else: ?>
            <?php if ($pesan_baru > 0): ?>
                <form action="" method="POST" style="margin-bottom:15px;">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="aksi" value="baca_semua">
                    <button type="submit" class="btn btn-soft btn-kecil">Tandai Semua Sudah Dibaca</button>
                </form>
            <?php endif; ?>

            <?php foreach ($pesan_masuk as $m): ?>
                <?php $baru = (int) $m['dibaca'] === 0; ?>
                <article class="pesan <?= $baru ? 'pesan-baru' : ''; ?>">
                    <header class="pesan-kepala">
                        <div>
                            <strong><?= htmlspecialchars($m['nama']); ?></strong>
                            <?php if ($baru): ?>
                                <span class="badge badge-akan">Baru</span>
                            <?php endif; ?>
                            <p class="pesan-meta">
                                &lt;<a href="mailto:<?= htmlspecialchars($m['email']); ?>"><?= htmlspecialchars($m['email']); ?></a>&gt;
                                &middot; <?= htmlspecialchars(waktu_indonesia($m['created_at'], $m['menit_lalu'])); ?>
                            </p>
                        </div>
                        <div class="pesan-aksi">
                            <?php if ($baru): ?>
                                <form action="" method="POST">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="aksi" value="baca">
                                    <input type="hidden" name="id_pesan" value="<?= (int) $m['id']; ?>">
                                    <button type="submit" class="btn btn-soft btn-kecil">Tandai Dibaca</button>
                                </form>
                            <?php endif; ?>
                            <form action="" method="POST" onsubmit="return confirm('Hapus pesan dari <?= htmlspecialchars(addslashes($m['nama'])); ?> ?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="aksi" value="hapus_pesan">
                                <input type="hidden" name="id_pesan" value="<?= (int) $m['id']; ?>">
                                <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
                            </form>
                        </div>
                    </header>

                    <h3 class="pesan-subjek"><?= htmlspecialchars($m['subjek']); ?></h3>
                    <p class="pesan-isi"><?= nl2br(htmlspecialchars($m['pesan'])); ?></p>

                    <a class="btn btn-soft btn-kecil" href="mailto:<?= htmlspecialchars($m['email']); ?>?subject=<?= rawurlencode('Re: ' . $m['subjek']); ?>">
                        <i class="fa-solid fa-reply"></i> Balas via Email
                    </a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">Kelola Barang Saya</h2>
        <p>Atur nama, harga, dan stok barang yang kamu sewakan. Barang yang kamu buat hanya muncul di akun kamu.</p>
        <a href="<?= base_url('pages/barang_saya.php'); ?>" class="btn">Tambah / Ubah Barang</a>
    </div>

    <div class="panel">
        <h2 class="panel-title">Pesanan Masuk (15 terbaru)</h2>

        <?php if (empty($pesanan)): ?>
            <p class="kosong">Belum ada penyewa yang menyewa barang kamu.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Penyewa</th>
                            <th>Barang</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Jumlah</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan as $p): ?>
                            <?php
                            $status_opsi = [
                                'akan'     => 'Belum Mulai',
                                'berjalan' => 'Barang Sudah Diambil',
                                'selesai'  => 'Barang Sudah Dikembalikan',
                            ];
                            $status = (string) ($p['status'] ?? 'akan');
                            if (!isset($status_opsi[$status])) {
                                $status = 'akan';
                            }
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($p['nama_penyewa']); ?>
                                    <?php if (!empty($p['email_penyewa'])): ?>
                                        <br><small class="teks-kecil"><?= htmlspecialchars($p['email_penyewa']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['nama_barang']); ?></td>
                                <td><?= htmlspecialchars($p['tanggal_mulai']); ?></td>
                                <td><?= htmlspecialchars($p['tanggal_selesai']); ?></td>
                                <td><?= (int) $p['jumlah']; ?></td>
                                <td>Rp<?= number_format((int) $p['total_harga'], 0, ',', '.'); ?></td>
                                <td>
                                    <form action="" method="POST" class="form-status">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="aksi" value="ubah_status">
                                        <input type="hidden" name="id_sewa" value="<?= (int) $p['id']; ?>">
                                        <select name="status" class="status-select status-<?= htmlspecialchars($status); ?>" onchange="this.form.submit()" aria-label="Status pesanan">
                                            <?php foreach ($status_opsi as $nilai => $label): ?>
                                                <?php if ($nilai === 'akan' && $status !== 'akan') continue; ?>
                                                <option value="<?= $nilai; ?>" <?= $status === $nilai ? 'selected' : ''; ?>><?= $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">Barang Saya</h2>

        <?php if (empty($barang_saya)): ?>
            <p class="kosong">Kamu belum punya barang. Silakan tambahkan lewat halaman Barang Saya.</p>
        <?php else: ?>
            <div class="card-container">
                <?php foreach ($barang_saya as $b): ?>
                    <div class="card">
                        <?= tag_gambar($b['gambar'], $b['nama']) ?>
                        <h3><?= htmlspecialchars($b['nama']); ?></h3>
                        <p class="harga">Rp<?= number_format((int) $b['harga'], 0, ',', '.'); ?> /hari</p>
                        <p class="stok">Stok total: <?= (int) $b['stok']; ?> unit</p>
                        <p class="stok">Sedang disewa hari ini: <?= (int) $b['unit_disewa']; ?> unit</p>
                        <p class="stok">Pernah disewa: <?= (int) $b['jumlah_sewa']; ?> kali</p>
                        <a href="<?= base_url('pages/barang_saya.php?edit=' . (int) $b['id']); ?>" class="btn btn-soft">Kelola</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>