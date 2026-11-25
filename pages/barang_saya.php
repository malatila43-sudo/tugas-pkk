<?php
// ============================================
// KAILKILAT.COM - Kelola Barang (khusus pemilik barang)
// ============================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_role('mitra');
cek_csrf();

$uid     = id_user();
$msg     = '';
$msg_type = 'info';

// Simpan gambar yang di-upload. Mengembalikan nama file baru,
// null kalau tidak ada file, atau pesan error kalau gagal.
function simpan_gambar_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'nama' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'pesan' => 'Upload gambar gagal (kode ' . (int) $file['error'] . ').'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'pesan' => 'Ukuran gambar maksimal 2 MB.'];
    }

    $izin = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!isset($izin[$mime])) {
        return ['ok' => false, 'pesan' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.'];
    }

    $folder = ROOT_DIR . '/assets/img/';
    if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
        return ['ok' => false, 'pesan' => 'Folder gambar tidak bisa dibuat.'];
    }

    $nama_file = 'barang_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $izin[$mime];

    if (!move_uploaded_file($file['tmp_name'], $folder . $nama_file)) {
        return ['ok' => false, 'pesan' => 'Gambar gagal disimpan di server.'];
    }

    return ['ok' => true, 'nama' => $nama_file];
}

$edit_id   = (int) ($_GET['edit'] ?? 0);
$edit_data = null;

// ---------- Simpan barang (tambah atau ubah) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $id_tujuan = (int) ($_POST['id_barang'] ?? 0);
    $nama      = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga     = (int) ($_POST['harga'] ?? 0);
    $stok      = (int) ($_POST['stok'] ?? 0);
    $gambar    = trim($_POST['gambar'] ?? '');

    if ($nama === '') {
        $msg = 'Nama barang wajib diisi.';
        $msg_type = 'error';
    } elseif ($harga < 0) {
        $msg = 'Harga tidak boleh negatif.';
        $msg_type = 'error';
    } elseif ($stok < 0) {
        $msg = 'Stok tidak boleh negatif.';
        $msg_type = 'error';
    } else {
        $upload = simpan_gambar_upload($_FILES['gambar_file'] ?? []);

        if (!$upload['ok']) {
            $msg = $upload['pesan'];
            $msg_type = 'error';
        } else {
            if ($upload['nama'] !== null) {
                $gambar = $upload['nama'];
            } elseif ($gambar === '') {
                $gambar = GAMBAR_DEFAULT;
            }

            if ($id_tujuan > 0) {
                $stmt = $koneksi->prepare(
                    "UPDATE pancingan SET nama = ?, deskripsi = ?, harga = ?, stok = ?, gambar = ?
                     WHERE id = ? AND owner_id = ?"
                );
                // nama, deskripsi, gambar = teks; harga, stok, id, owner = angka
                $stmt->bind_param('ssiisii', $nama, $deskripsi, $harga, $stok, $gambar, $id_tujuan, $uid);
            } else {
                $stmt = $koneksi->prepare(
                    "INSERT INTO pancingan (nama, deskripsi, harga, stok, gambar, owner_id)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('ssiisi', $nama, $deskripsi, $harga, $stok, $gambar, $uid);
            }

            if ($stmt->execute()) {
                $berubah = $stmt->affected_rows;
                $stmt->close();

                // affected_rows 0 saat data tidak berubah, atau barang bukan milik kamu
                if ($id_tujuan > 0 && $berubah === 0) {
                    $msg = 'Barang tidak bisa diubah. Bukan milik kamu, atau datanya tidak berubah.';
                    $msg_type = 'error';
                } else {
                    set_flash(
                        $id_tujuan > 0
                            ? 'Barang berhasil diperbarui.'
                            : 'Barang baru berhasil ditambahkan.',
                        'success'
                    );
                    header('Location: ' . base_url('pages/barang_saya.php'));
                    exit;
                }
            } else {
                $msg = 'Gagal menyimpan barang. Silakan coba lagi.';
                $msg_type = 'error';
            }
        }
    }
}

// ---------- Hapus barang ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $id_hapus = (int) ($_POST['id_barang'] ?? 0);

    // Barang yang masih dipakai penyewa tidak boleh dihapus supaya
    // riwayat sewa yang sedang berjalan tidak ikut hilang.
    $cek = $koneksi->prepare(
        "SELECT COUNT(*) AS jml FROM sewa
         WHERE pancingan_id = ? AND tanggal_selesai >= CURDATE()"
    );
    $cek->bind_param('i', $id_hapus);
    $cek->execute();
    $masih_disewa = (int) ($cek->get_result()->fetch_assoc()['jml'] ?? 0);
    $cek->close();

    if ($masih_disewa > 0) {
        $msg = 'Barang tidak bisa dihapus karena masih ada penyewaan aktif. Tunggu sampai tanggal sewanya selesai.';
        $msg_type = 'error';
    } else {
        $stmt = $koneksi->prepare("DELETE FROM pancingan WHERE id = ? AND owner_id = ?");
        $stmt->bind_param('ii', $id_hapus, $uid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = 'Barang berhasil dihapus.';
            $msg_type = 'success';
        } else {
            $msg = 'Barang tidak bisa dihapus (bukan milik kamu atau sudah terhapus).';
            $msg_type = 'error';
        }
        $stmt->close();
    }
}

// ---------- Data barang milik saya ----------
$stmt = $koneksi->prepare(
    "SELECT p.*,
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

// ---------- Ambil barang yang mau diubah ----------
if ($edit_id > 0) {
    foreach ($barang_saya as $b) {
        if ((int) $b['id'] === $edit_id) {
            $edit_data = $b;
            break;
        }
    }
}

$form = [
    'id'       => $edit_data['id'] ?? 0,
    'nama'     => $edit_data['nama'] ?? ($_POST['nama'] ?? ''),
    'deskripsi'=> $edit_data['deskripsi'] ?? ($_POST['deskripsi'] ?? ''),
    'harga'    => $edit_data['harga'] ?? ($_POST['harga'] ?? ''),
    'stok'     => $edit_data['stok'] ?? ($_POST['stok'] ?? ''),
    'gambar'   => $edit_data['gambar'] ?? ($_POST['gambar'] ?? GAMBAR_DEFAULT),
];

// Daftar gambar yang tersedia di folder ini, buat dipakai di form
$gambar_tersedia = array_map('basename', glob(ROOT_DIR . '/assets/img/*.{svg,png,jpg,jpeg,webp,avif,gif}', GLOB_BRACE) ?: []);

$page_title  = 'Barang Saya';
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'barang_saya';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-banner">
    <h1>Barang Saya</h1>
    <p>Halaman khusus pemilik barang. Hanya akun kamu yang bisa melihat dan mengubah daftar ini.</p>
</section>

<div class="wrap">
    <?php if ($msg !== ''): ?>
        <div class="alert-msg <?= $msg_type; ?>"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2 class="panel-title"><?= $form['id'] > 0 ? 'Ubah Barang' : 'Tambah Barang Baru'; ?></h2>

        <form class="form-grid" action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id_barang" value="<?= (int) $form['id']; ?>">

            <div class="form-group">
                <label for="nama">Nama Barang</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars((string) $form['nama']); ?>" required>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars((string) $form['deskripsi']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="harga">Harga per hari (Rp)</label>
                <input type="number" id="harga" name="harga" min="0" step="1000" value="<?= (int) $form['harga']; ?>" required>
            </div>

            <div class="form-group">
                <label for="stok">Stok (unit)</label>
                <input type="number" id="stok" name="stok" min="0" value="<?= (int) $form['stok']; ?>" required>
            </div>

            <div class="form-group">
                <label for="gambar">Gambar Barang</label>
                <input type="text" id="gambar" name="gambar" list="daftar-gambar"
                       value="<?= htmlspecialchars((string) $form['gambar']); ?>"
                       placeholder="<?= GAMBAR_DEFAULT; ?>">
                <datalist id="daftar-gambar">
                    <?php foreach ($gambar_tersedia as $g): ?>
                        <option value="<?= htmlspecialchars($g); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <small class="teks-kecil">Pilih salah satu gambar di atas, atau ketik nama file lain yang ada di folder ini.</small>
                <div class="preview-gambar">
                    <img id="previewGambar" src="<?= htmlspecialchars(url_gambar((string) $form['gambar'])); ?>"
                         alt="Pratinjau gambar" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="form-group">
                <label for="gambar_file">Upload Gambar Baru (opsional)</label>
                <input type="file" id="gambar_file" name="gambar_file"
                       accept="image/png,image/jpeg,image/webp,image/gif">
                <small class="teks-kecil">Maksimal 2 MB. Kalau upload, kolom nama gambar di atas otomatis memakai file baru.</small>
            </div>

            <div class="form-group form-tombol">
                <button type="submit" class="btn"><?= $form['id'] > 0 ? 'Simpan Perubahan' : 'Tambah Barang'; ?></button>
                <?php if ($form['id'] > 0): ?>
                    <a href="<?= base_url('pages/barang_saya.php'); ?>" class="btn btn-soft">Batal Ubah</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2 class="panel-title">Daftar Barang Saya</h2>

        <?php if (empty($barang_saya)): ?>
            <p class="kosong">Belum ada barang. Isi form di atas untuk menambahkan barang pertama kamu.</p>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Harga /hari</th>
                            <th>Stok</th>
                            <th>Sedang Disewa</th>
                            <th>Disewa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barang_saya as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['nama']); ?></td>
                                <td>Rp<?= number_format((int) $b['harga'], 0, ',', '.'); ?></td>
                                <td><?= (int) $b['stok']; ?> unit</td>
                                <td><?= (int) $b['unit_disewa']; ?> unit</td>
                                <td><?= (int) $b['jumlah_sewa']; ?> kali</td>
                                <td class="kolom-aksi">
                                    <a href="<?= base_url('pages/barang_saya.php?edit=' . (int) $b['id']); ?>" class="btn btn-soft btn-kecil">Ubah</a>
                                    <form action="" method="POST" onsubmit="return confirm('Hapus barang ini? Penyewaan yang masih aktif tidak akan terhapus.');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_barang" value="<?= (int) $b['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-kecil">Hapus</button>
                                    </form>
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

<script>
  (function () {
    const input = document.getElementById('gambar');
    const preview = document.getElementById('previewGambar');
    if (!input || !preview) return;

    // Daftar file yang boleh dipakai, sama sastranya dengan url_gambar() di PHP
    const folder = '<?= base_url('assets/img/'); ?>';
    const daftar = <?= json_encode($gambar_tersedia, JSON_UNESCAPED_SLASHES); ?>;
    const bawaan = <?= json_encode(GAMBAR_DEFAULT, JSON_UNESCAPED_SLASHES); ?>;

    input.addEventListener('input', function () {
      const nama = input.value.trim();
      preview.src = folder + (daftar.indexOf(nama) !== -1 ? nama : bawaan);
    });
  })();
</script>