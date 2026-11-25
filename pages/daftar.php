<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

cek_csrf();

if (sudah_login()) {
    header('Location: ' . url_dashboard_manusia());
    exit;
}

$role_diminta = valid_role($_POST['role'] ?? ($_GET['role'] ?? '')) ? (string) ($_POST['role'] ?? $_GET['role']) : 'penyewa';
$redirect_tujuan = redirect_aman($_POST['redirect'] ?? ($_GET['redirect'] ?? ''), '');
$error = '';
$flash = ambil_flash();
if ($flash && !empty($flash['pesan'])) {
    $error = (string) $flash['pesan'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role_diminta === 'penyewa') {
    $nama       = trim($_POST['nama'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = bersihkan_password($_POST['password'] ?? '');
    $konfirmasi = bersihkan_password($_POST['konfirmasi'] ?? '');

    if ($nama === '' || $email === '' || $password === '') {
        $error = 'Nama, email, dan kata sandi wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $cek = $koneksi->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $cek->bind_param('s', $email);
        $cek->execute();
        $cek->store_result();
        $sudah_ada = $cek->num_rows > 0;
        $cek->close();

        if ($sudah_ada) {
            $error = 'Email sudah terdaftar. Silakan masuk dengan akun tersebut.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $koneksi->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'penyewa')");
            $ins->bind_param('sss', $nama, $email, $hash);

            if ($ins->execute()) {
                $id_baru = (int) $ins->insert_id;
                $ins->close();

                masuk_dan_simpan([
                    'id'   => $id_baru,
                    'nama' => $nama,
                    'email' => $email,
                    'role' => 'penyewa',
                ]);

                set_flash('Pendaftaran berhasil. Silakan pilih pancingan di katalog.', 'success');
                header('Location: ' . ($redirect_tujuan !== '' ? $redirect_tujuan : base_url('pages/katalog.php')));
                exit;
            }

            $ins->close();
            $error = 'Pendaftaran gagal. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Penyewa - KAILKILAT.COM</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        media="print" onload="this.media='all';this.onload=null" />
  <noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  </noscript>

  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/login.css')); ?>?v=<?= @filemtime(ROOT_DIR . '/assets/css/login.css') ?: time(); ?>" />

  <style>
    .alert-error {
      background-color: #ffe6e6;
      color: #d9534f;
      padding: 10px 15px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 14px;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body class="auth-body">

  <div class="auth-wrapper">
    <div class="auth-hero">
      <a href="<?= base_url('index.php'); ?>" class="logo hero-logo"><img src="<?= htmlspecialchars(base_url('assets/img/img_logo.svg')); ?>?v=<?= @filemtime(ROOT_DIR . '/assets/img/img_logo.svg') ?: time(); ?>" alt="Logo KAILKILAT" class="brand-logo">KAILKILAT<span>.COM</span></a>
      <div class="hero-text" style="top:150px;">
        <h2>Sewa Pancingan/Joran Impian Tanpa Beban!</h2>
        <p>Cari Joran Favorite mu hanya di KAILKILAT.COM</p>
        <p style="margin-top:70px; font-weight:500;">Daftar sekali, lalu sewa kapan saja lewat akun kamu</p>
      </div>
    </div>

    <div class="auth-container">
      <div class="auth-header">
        <a href="<?= base_url('index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
        <h2>Daftar Penyewa</h2>
        <p>Buat akun dulu untuk bisa memilih dan menyewa pancingan</p>
      </div>

      <?php if ($role_diminta === 'mitra'): ?>
        <div class="alert-error" style="background-color:#fff4e5;color:#8a5a00;border-color:#ffd8a8;">
          <i class="fa-solid fa-circle-info"></i> Akun Pemilik Barang (mitra) dibuat oleh admin KAILKILAT.COM dan tidak bisa didaftarkan sendiri.
        </div>
        <div class="auth-footer">
          Sudah punya akun? <a href="<?= htmlspecialchars(base_url('pages/login.php?role=mitra')); ?>">Masuk sebagai Pemilik Barang</a>
        </div>
      <?php else: ?>
        <?php if (!empty($error)): ?>
          <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form class="auth-form" action="" method="POST">
          <?= csrf_field(); ?>
          <input type="hidden" name="role" value="penyewa">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_tujuan); ?>">

          <div class="role-indicator">
            Mendaftar sebagai: <strong>Penyewa Barang</strong>
          </div>

          <div class="form-group">
            <label for="nama">Nama Lengkap</label>
            <div class="input-wrapper">
              <i class="fa-solid fa-user"></i>
              <input type="text" name="nama" id="nama" placeholder="Nama kamu" value="<?= htmlspecialchars($_POST['nama'] ?? ''); ?>" required />
            </div>
          </div>

          <div class="form-group">
            <label for="email">Alamat Email</label>
            <div class="input-wrapper">
              <i class="fa-solid fa-envelope"></i>
              <input type="email" name="email" id="email" placeholder="nama@email.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required />
            </div>
          </div>

          <div class="form-group">
            <label for="password">Kata Sandi</label>
            <div class="input-wrapper">
              <i class="fa-solid fa-lock"></i>
              <input type="password" name="password" id="password" placeholder="Minimal 6 karakter" autocomplete="new-password" required />
              <i class="fa-solid fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
            </div>
          </div>

          <div class="form-group">
            <label for="konfirmasi">Ulangi Kata Sandi</label>
            <div class="input-wrapper">
              <i class="fa-solid fa-lock"></i>
              <input type="password" name="konfirmasi" id="konfirmasi" placeholder="Ulangi kata sandi" autocomplete="new-password" required />
            </div>
          </div>

          <button type="submit" class="btn btn-primary auth-btn">Daftar Sebagai Penyewa <i class="fa-solid fa-arrow-right-to-bracket"></i></button>

          <div class="auth-footer">
            Sudah punya akun? <a href="<?= htmlspecialchars(base_url('pages/login.php?role=penyewa' . ($redirect_tujuan !== '' ? '&redirect=' . rawurlencode($redirect_tujuan) : ''))); ?>">Masuk di sini</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function togglePasswordVisibility() {
      const passInput = document.getElementById('password');
      const icon = document.querySelector('.toggle-password');
      if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }
  </script>

</body>
</html>
