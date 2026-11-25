<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/stok.php';

$id  = (int) ($_GET['id'] ?? 0);

if (!sudah_login()) {
    set_flash('Silakan masuk atau daftar terlebih dahulu untuk menyewa.');
    $balik = base_url('pages/sewa.php?id=' . $id);
    header('Location: ' . base_url('pages/login.php?role=penyewa&redirect=' . rawurlencode($balik)));
    exit;
}

if (role_user() !== 'penyewa') {
    set_flash('Halaman sewa khusus untuk akun Penyewa.', 'error');
    header('Location: ' . url_dashboard_manusia());
    exit;
}

cek_csrf();

$msg = '';
$msg_type = '';
$total = 0;
$harga = 0;

$stmt = $koneksi->prepare("SELECT * FROM pancingan WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

$nama_pemilik = '';
if ($item && !empty($item['owner_id'])) {
    $id_pemilik = (int) $item['owner_id'];

    $cek = $koneksi->prepare("SELECT nama FROM users WHERE id = ? LIMIT 1");
    $cek->bind_param('i', $id_pemilik);
    $cek->execute();
    $baris_pemilik = $cek->get_result()->fetch_assoc();
    $cek->close();
    $nama_pemilik = $baris_pemilik['nama'] ?? '';
}

$hari_ini = date('Y-m-d');
$stok_total = $item ? (int) $item['stok'] : 0;
$tersedia_hari_ini = $item ? stok_tersedia($koneksi, $id, $stok_total, $hari_ini, $hari_ini) : 0;

if ($item) {
    $harga = (int) $item['harga'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tgl_mulai   = $_POST['tgl_mulai'] ?? '';
        $tgl_selesai = $_POST['tgl_selesai'] ?? '';
        $jumlah      = (int) ($_POST['jumlah'] ?? 1);

        $d1 = DateTime::createFromFormat('Y-m-d', $tgl_mulai);
        $d2 = DateTime::createFromFormat('Y-m-d', $tgl_selesai);

        if (!$d1 || !$d2 || $d1->format('Y-m-d') !== $tgl_mulai || $d2->format('Y-m-d') !== $tgl_selesai) {
            $msg = 'Tanggal tidak valid.';
            $msg_type = 'error';
        } elseif ($d1->format('Y-m-d') < $hari_ini) {
            $msg = 'Tanggal mulai tidak boleh di masa lalu.';
            $msg_type = 'error';
        } elseif ($d2 < $d1) {
            $msg = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            $msg_type = 'error';
        } elseif ($jumlah < 1) {
            $msg = 'Jumlah unit minimal 1.';
            $msg_type = 'error';
        } elseif ($jumlah > $stok_total) {
            $msg = 'Stok total barang ini hanya ' . $stok_total . ' unit.';
            $msg_type = 'error';
        } else {
            $lama_hari = $d1->diff($d2)->days + 1;
            $total = $harga * $lama_hari * $jumlah;

            $koneksi->begin_transaction();
            try {
                // Kunci baris barang supaya dua orang tidak bisa memesan
                // sisa unit yang sama pada waktu bersamaan.
                $lock = $koneksi->prepare("SELECT stok FROM pancingan WHERE id = ? FOR UPDATE");
                $lock->bind_param('i', $id);
                $lock->execute();
                $row_stok = $lock->get_result()->fetch_assoc();
                $lock->close();

                if (!$row_stok) {
                    $koneksi->rollback();
                    $msg = 'Barang sudah tidak tersedia.';
                    $msg_type = 'error';
                } else {
                    $stok_sekarang = (int) $row_stok['stok'];
                    $terpesan      = jumlah_terpesan($koneksi, $id, $tgl_mulai, $tgl_selesai);
                    $tersedia      = max(0, $stok_sekarang - $terpesan);

                    if ($jumlah > $tersedia) {
                        $koneksi->rollback();
                        $msg = 'Stok tidak cukup untuk tanggal itu. Tersedia '
                             . $tersedia . ' unit dari total ' . $stok_sekarang . ' unit.';
                        $msg_type = 'error';
                    } else {
                        $uid = id_user();
                        $nama_penyewa = nama_user();
                        $email_penyewa = email_user();
                        $ins = $koneksi->prepare(
                            "INSERT INTO sewa (user_id, nama_penyewa, email_penyewa, pancingan_id, tanggal_mulai, tanggal_selesai, jumlah, total_harga)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $ins->bind_param('ississii', $uid, $nama_penyewa, $email_penyewa, $id, $tgl_mulai, $tgl_selesai, $jumlah, $total);

                        if ($ins->execute()) {
                            $ins->close();

                            $id_pemilik = (int) ($item['owner_id'] ?? 0);
                            if ($id_pemilik > 0) {
                                $subjek_notif = 'Penyewaan baru: ' . $item['nama'];
                                $isi_notif = $nama_penyewa . ' (' . $email_penyewa . ') menyewa '
                                           . $jumlah . ' unit ' . $item['nama'] . ' dari '
                                           . $tgl_mulai . ' sampai ' . $tgl_selesai
                                           . '. Total Rp' . number_format($total, 0, ',', '.') . '.';
                                $notif = $koneksi->prepare(
                                    "INSERT INTO pesan_kontak (user_id, mitra_id, nama, email, subjek, pesan)
                                     VALUES (?, ?, ?, ?, ?, ?)"
                                );
                                $notif->bind_param('iissss', $uid, $id_pemilik, $nama_penyewa, $email_penyewa, $subjek_notif, $isi_notif);
                                $notif->execute();
                                $notif->close();
                            }

                            $koneksi->commit();

                            $stok_total        = $stok_sekarang;
                            $tersedia_hari_ini = stok_tersedia($koneksi, $id, $stok_sekarang, $hari_ini, $hari_ini);

                            $msg = 'Penyewaan berhasil dicatat! Total biaya Rp'
                                 . number_format($total, 0, ',', '.') . '.';
                            $msg_type = 'success';
                        } else {
                            $ins->close();
                            $koneksi->rollback();
                            $msg = 'Gagal menyimpan penyewaan. Silakan coba lagi.';
                            $msg_type = 'error';
                        }
                    }
                }
            } catch (Throwable $e) {
                $koneksi->rollback();
                $msg = 'Terjadi kesalahan saat memproses. Silakan coba lagi.';
                $msg_type = 'error';
            }
        }
    }
}

$page_title  = ($item ? 'Sewa ' . $item['nama'] : 'Detail Sewa');
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'katalog';
include __DIR__ . '/../includes/header.php';
?>

    <div style="max-width:900px;margin:30px auto;padding:0 20px;">
        <a href="<?= base_url('pages/katalog.php'); ?>" style="color:#075985;text-decoration:none;font-weight:600;">&larr; Kembali ke Katalog</a>
    </div>

    <?php if (!$item): ?>
        <section class="katalog">
            <p style="text-align:center;color:#111827;padding:40px;">Barang tidak ditemukan.</p>
        </section>
    <?php else: ?>
        <section class="detail-wrapper">
            <div class="detail-img">
                <?= tag_gambar($item['gambar'], $item['nama']) ?>
            </div>
            <div class="detail-info">
                <h2><?= htmlspecialchars($item['nama']); ?></h2>
                <p class="detail-harga">Rp<?= number_format($harga, 0, ',', '.'); ?> / hari</p>
                <p class="detail-deskripsi"><?= htmlspecialchars($item['deskripsi']); ?></p>
                <?php if ($nama_pemilik !== ''): ?>
                    <p class="detail-pemilik">
                        Pemilik barang: <strong><?= htmlspecialchars($nama_pemilik); ?></strong>
                        <?php if (!empty($item['owner_id'])): ?>
                            &middot;
                            <a href="<?= base_url('pages/kontak.php?mitra=' . (int) $item['owner_id']); ?>">Hubungi pemilik</a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <p class="detail-stok">
                    Stok total: <strong><?= $stok_total; ?></strong> unit &middot;
                    Tersedia hari ini: <strong><?= $tersedia_hari_ini; ?></strong> unit
                </p>
                <?php if ($stok_total <= 0): ?>
                    <p class="detail-stok habis">Stok habis</p>
                <?php endif; ?>

                <?php if (!empty($msg)): ?>
                    <div class="alert-msg <?= $msg_type; ?>"><?= htmlspecialchars($msg); ?></div>
                <?php endif; ?>

                <?php if ($stok_total > 0): ?>
                    <form class="form-sewa" action="" method="POST">
                        <?= csrf_field(); ?>
                        <div class="form-group">
                            <label for="nama_penyewa">Nama Penyewa (dari akun kamu)</label>
                            <input type="text" id="nama_penyewa" value="<?= htmlspecialchars(nama_user()); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="email_penyewa">Email Penyewa (dari akun kamu)</label>
                            <input type="email" id="email_penyewa" value="<?= htmlspecialchars(email_user()); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="tgl_mulai">Tanggal Mulai Sewa</label>
                            <input type="date" name="tgl_mulai" id="tgl_mulai" value="<?= $hari_ini; ?>" min="<?= $hari_ini; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tgl_selesai">Tanggal Selesai Sewa</label>
                            <input type="date" name="tgl_selesai" id="tgl_selesai" value="<?= $hari_ini; ?>" min="<?= $hari_ini; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="jumlah">Jumlah Unit (maks. <?= $stok_total; ?>)</label>
                            <input type="number" name="jumlah" id="jumlah" value="1" min="1" max="<?= $stok_total; ?>" required>
                        </div>
                        <div class="total-box">
                            Estimasi total: <strong id="totalDisplay">Rp0</strong> &nbsp;(harga x hari x unit)
                        </div>
                        <button type="submit" class="btn">Sewa Sekarang</button>
                    </form>
                    <script>
                      (function () {
                        const harga = <?= $harga; ?>;
                        const tglMulai = document.getElementById('tgl_mulai');
                        const tglSelesai = document.getElementById('tgl_selesai');
                        const jumlah = document.getElementById('jumlah');
                        const totalDisplay = document.getElementById('totalDisplay');
                        function hitung() {
                          const d1 = new Date(tglMulai.value + 'T00:00:00');
                          const d2 = new Date(tglSelesai.value + 'T00:00:00');
                          let hari = 1;
                          if (!isNaN(d1) && !isNaN(d2) && d2 >= d1) {
                            hari = Math.round((d2 - d1) / 86400000) + 1;
                          } else if (!isNaN(d2) && d2 < d1) {
                            totalDisplay.textContent = 'tanggal tidak valid';
                            return;
                          }
                          const n = parseInt(jumlah.value, 10) || 0;
                          const total = harga * hari * n;
                          totalDisplay.textContent = 'Rp' + total.toLocaleString('id-ID');
                        }
                        tglMulai.addEventListener('change', hitung);
                        tglSelesai.addEventListener('change', hitung);
                        jumlah.addEventListener('input', hitung);
                        hitung();
                      })();
                    </script>
                <?php else: ?>
                    <div class="alert-msg error">Maaf, stok barang ini sedang habis.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
