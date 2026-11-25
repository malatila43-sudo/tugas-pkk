<?php
// ============================================
// KAILKILAT.COM - Form Kontak / Kirim Pesan
// ============================================
// Pengunjung (belum login) boleh kirim pesan juga.
// Kalau sudah login, nama dan email-nya diisi otomatis.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

cek_csrf();

// Pesan bisa diarahkan ke pemilik barang tertentu lewat ?mitra=ID.
// Kalau tidak ada, pakai pemilik barang pertama sebagai tujuan bawaan.
$mitra_id   = 0;
$nama_mitra = '';
$diminta    = (int) ($_GET['mitra'] ?? 0);

if ($diminta > 0) {
    $stmt = $koneksi->prepare("SELECT id, nama FROM users WHERE id = ? AND role = 'mitra' LIMIT 1");
    $stmt->bind_param('i', $diminta);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($baris) {
        $mitra_id   = (int) $baris['id'];
        $nama_mitra = (string) $baris['nama'];
    }
}

if ($mitra_id <= 0) {
    $res = $koneksi->query("SELECT id, nama FROM users WHERE role = 'mitra' ORDER BY id LIMIT 1");
    if ($res && ($baris = $res->fetch_assoc())) {
        $mitra_id   = (int) $baris['id'];
        $nama_mitra = (string) $baris['nama'];
    }
}

$error  = '';
$sukses = false;

$flash = ambil_flash();
if ($flash && !empty($flash['pesan'])) {
    $error = (string) $flash['pesan'];
}

$nama    = sudah_login() ? nama_user() : '';
$email   = sudah_login() ? email_user() : '';
$subjek  = '';
$pesan   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim($_POST['nama'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $subjek = trim($_POST['subjek'] ?? '');
    $pesan  = trim($_POST['pesan'] ?? '');

    // Kalau sudah login, nama dan email diambil dari akun, bukan dari form.
    // Jadi tidak ada yang bisa menulis pesan seolah-olah datang dari email lain.
    if (sudah_login()) {
        $nama  = nama_user();
        $email = email_user();
    }

    // Kalau bot mengisi field jebakan "website", jangan disimpan.
    $aksara   = $_POST['website'] ?? '';
    $salahnya = [];

    if ($nama === '') {
        $salahnya[] = 'nama';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $salahnya[] = 'email';
    }
    if ($subjek === '') {
        $salahnya[] = 'subjek';
    }
    if (strlen($pesan) < 10) {
        $salahnya[] = 'pesan';
    }

    // Bot juga biasanya mengisi field jebakan itu.
    if ($aksara !== '') {
        $error = 'Pesan tidak bisa dikirim.';
    } elseif ($mitra_id <= 0) {
        $error = 'Belum ada akun pemilik barang, pesan tidak bisa diterima.';
    } elseif ($salahnya) {
        $error = 'Mohon lengkapi: ' . implode(', ', $salahnya) . '.';
    } else {
        $user_id = sudah_login() ? id_user() : null;

        if ($user_id === null) {
            $stmt = $koneksi->prepare(
                "INSERT INTO pesan_kontak (user_id, mitra_id, nama, email, subjek, pesan)
                 VALUES (NULL, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('issss', $mitra_id, $nama, $email, $subjek, $pesan);
        } else {
            $stmt = $koneksi->prepare(
                "INSERT INTO pesan_kontak (user_id, mitra_id, nama, email, subjek, pesan)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iissss', $user_id, $mitra_id, $nama, $email, $subjek, $pesan);
        }

        if ($stmt->execute()) {
            $sukses = true;
            // Kosongkan form supaya tidak terkirim dua kali saat refresh
            $subjek = '';
            $pesan  = '';
        } else {
            $error = 'Pesan gagal dikirim. Silakan coba lagi.';
        }
        $stmt->close();
    }
}

$page_title  = 'Kontak';
$page_css    = ['kp.css', 'katalog.css', 'dashboard.css'];
$active_menu = 'kontak';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-banner">
    <h1>Hubungi Pemilik Barang</h1>
    <p>Punya pertanyaan soal sewa? Kirim pesan, pemilik barang akan membalas lewat email kamu.</p>
</section>

<div class="wrap" style="max-width:820px;">
    <?php if ($sukses): ?>
        <div class="alert-msg success">
            <i class="fa-solid fa-circle-check"></i>
            Terima kasih! Pesan kamu sudah sampai ke kotak masuk <?= $nama_mitra !== '' ? 'pemilik barang (' . htmlspecialchars($nama_mitra) . ')' : 'pemilik barang'; ?>.
        </div>
    <?php elseif ($error !== ''): ?>
        <div class="alert-msg error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2 class="panel-title">Kirim Pesan</h2>

        <?php if ($nama_mitra !== ''): ?>
            <p>Pesan ini akan dikirim ke <strong><?= htmlspecialchars($nama_mitra); ?></strong>.</p>
        <?php endif; ?>

        <?php if (!sudah_login()): ?>
            <div class="alert-msg info">
                Kamu belum masuk, tapi tidak apa-apa. Pesan tetap bisa dikirim.
                Kalau sudah <a href="<?= base_url('pages/login.php'); ?>" style="font-weight:600;">masuk</a>, nama dan email
                terisi otomatis.
            </div>
        <?php endif; ?>

        <form class="form-grid" action="" method="POST">
            <?= csrf_field(); ?>
            <div class="form-group">
                <label for="nama">Nama Kamu</label>
                <input type="text" id="nama" name="nama" maxlength="100"
                       value="<?= htmlspecialchars($nama); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" maxlength="100"
                       value="<?= htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group" style="grid-column:1 / -1;">
                <label for="subjek">Subjek</label>
                <input type="text" id="subjek" name="subjek" maxlength="150"
                       placeholder="Contoh: Mau sewa 3 hari, bisa bayar di tempat?"
                       value="<?= htmlspecialchars($subjek); ?>" required>
            </div>

            <div class="form-group" style="grid-column:1 / -1;">
                <label for="pesan">Pesan</label>
                <textarea id="pesan" name="pesan" rows="6" minlength="10"
                          placeholder="Tulis pertanyaan kamu di sini (minimal 10 karakter)"
                          required><?= htmlspecialchars($pesan); ?></textarea>
            </div>

            <!-- Field jebakan bot, disembunyikan dari pengguna -->
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-tombol" style="grid-column:1 / -1;">
                <button type="submit" class="btn">Kirim Pesan</button>
                <a href="<?= base_url('pages/katalog.php'); ?>" class="btn btn-soft">Lihat Katalog Dulu</a>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2 class="panel-title">Pertanyaan yang Sering Muncul</h2>
        <ul class="faq-list">
            <li><strong>Berapa lama barang disiapkan sebelum dipakai?</strong><br>Barang diperiksa dan disiapkan sehari sebelum tanggal mulai sewa.</li>
            <li><strong>Cara bayarnya bagaimana?</strong><br>Transfer ke rekening pemilik, lalu kirim bukti lewat pesan di halaman ini.</li>
            <li><strong>Bisa perpanjangan di tengah jalan?</strong><br>Bisa. Kirim pesan lagi, stoknya akan dicek dulu sebelum diperpanjang.</li>
            <li><strong>Kalau barang rusak?</strong><br>Segera laporkan lewat pesan supaya bisa ditangani cepat.</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
