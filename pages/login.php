<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/koneksi.php';

cek_csrf();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$redirect_tujuan = redirect_aman($_POST['redirect'] ?? ($_GET['redirect'] ?? ''), '');

// Sudah punya sesi? Langsung ke dashboard sesuai jenis akunnya.
if (sudah_login()) {
    header('Location: ' . ($redirect_tujuan !== '' ? $redirect_tujuan : url_dashboard_manusia()));
    exit;
}

$error = '';
$flash = ambil_flash();
if ($flash && !empty($flash['pesan'])) {
    $error = (string) $flash['pesan'];
}

// Tab aktif mengikuti pilihan di halaman daftar (?role=mitra) atau pilihan POST
$role_terpilih = valid_role($_GET['role'] ?? '') ? (string) $_GET['role'] : 'penyewa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    // Ketik sandi yang nyempet ada spasi atau enter di ujungnya tetap diterima
    $password = bersihkan_password($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'penyewa';
    $ingat    = !empty($_POST['remember']);

    if (!valid_role($role)) {
        $role = 'penyewa';
    }
    $role_terpilih = $role;

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Cek jenis akun dulu: penyewa tidak boleh masuk lewat tombol pemilik, begitu pula sebaliknya
            if ($user['role'] !== $role) {
                $tombol = $user['role'] === 'mitra' ? 'Pemilik Barang' : 'Penyewa';
                $error = 'Akun ini terdaftar sebagai ' . label_role($user['role']) .
                         '. Klik tombol "' . $tombol . '" di atas, lalu masukkan email dan password kamu.';
            } elseif (password_cocok($password, $user['password'])) {
                // Password lama masih storing polos, samakan jadi hash biar aman
                if (password_masih_plos($user['password'])) {
                    $id_akun  = (int) $user['id'];
                    $newHash = password_hash(bersihkan_password($password), PASSWORD_DEFAULT);

                    $up = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $up->bind_param('si', $newHash, $id_akun);
                    $up->execute();
                    $up->close();
                }

                masuk_dan_simpan($user);
                set_ingat_saya($ingat);
                // Penyewa masuk ke dashboard penyewa, pemilik barang ke dashboard pemilik barang
                header('Location: ' . ($redirect_tujuan !== '' ? $redirect_tujuan : halaman_dashboard($user['role'])));
                exit;
            } else {
                $error = 'Password yang kamu masukkan salah.';
            }
        } else {
            $error = 'Email tidak terdaftar di KAILKILAT.COM.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Masuk - KAILKILAT.COM</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Font Awesome dimuat tanpa memblokir tampilan, halaman langsung tampil duluan -->
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
    <!-- Panel Kiri: Hero Branding -->
    <div class="auth-hero">
      <a href="<?= base_url('index.php'); ?>" class="logo hero-logo"><img src="<?= htmlspecialchars(base_url('assets/img/img_logo.svg')); ?>?v=<?= @filemtime(ROOT_DIR . '/assets/img/img_logo.svg') ?: time(); ?>" alt="Logo KAILKILAT" class="brand-logo">KAILKILAT<span>.COM</span></a>
      <div class="hero-text" style="top:150px;">
        <h2>Sewa Pancingan/Joran Impian Tanpa Beban!</h2>
        <p>Cari Joran Favorite mu hanya di KAILKILAT.COM</p>
        <p style="margin-top:70px; font-weight:500;">Proses Cepat & Praktis – Pendaftaran singkat, langsung bisa sewa</p>
      </div>
    </div>

    <!-- Panel Kanan: Form Card -->
    <div class="auth-container">
      <div class="auth-header">
        <a href="<?= base_url('index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
        <h2>Selamat Datang!</h2>
        <p>Silakan pilih jenis akun untuk melanjutkan</p>
      </div>

      <!-- Role Switcher Toggle -->
      <div class="role-switcher">
        <button type="button" class="role-btn <?= $role_terpilih === 'penyewa' ? 'active' : ''; ?>" id="btn-penyewa" onclick="switchRole('penyewa')">
          <i class="fa-solid fa-user"></i> Penyewa
        </button>
        <button type="button" class="role-btn <?= $role_terpilih === 'mitra' ? 'active' : ''; ?>" id="btn-mitra" onclick="switchRole('mitra')">
          <i class="fa-solid fa-store"></i> Pemilik Barang
        </button>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- Form Login -->
      <form class="auth-form" id="loginForm" action="" method="POST">
        <?= csrf_field(); ?>
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($role_terpilih); ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_tujuan); ?>">

<div class="role-indicator" id="roleIndicator">
          Login sebagai: <strong><?= $role_terpilih === 'mitra' ? 'Pemilik Barang (Mitra)' : 'Penyewa Barang'; ?></strong>
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
            <input type="password" name="password" id="password" placeholder="••••••••" autocomplete="current-password" required />
            <i class="fa-solid fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
          </div>
        </div>

        <div class="form-actions">
          <label class="remember-me">
            <input type="checkbox" id="remember" name="remember" value="1" /> Ingat Saya (30 hari)
          </label>
        </div>

<button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
          <?= $role_terpilih === 'mitra' ? 'Masuk Sebagai Pemilik Barang' : 'Masuk Sebagai Penyewa'; ?> <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </button>

        <div class="auth-footer">
          Belum punya akun? <a href="<?= htmlspecialchars(base_url('pages/daftar.php?role=' . $role_terpilih . ($redirect_tujuan !== '' ? '&redirect=' . rawurlencode($redirect_tujuan) : ''))); ?>" id="registerLink"><?= $role_terpilih === 'mitra' ? 'Daftar Pemilik Barang' : 'Daftar Penyewa'; ?></a>
        </div>
      </form>
    </div>
  </div>

  <script>
    function switchRole(role) {
      const btnPenyewa = document.getElementById('btn-penyewa');
      const btnMitra = document.getElementById('btn-mitra');
      const roleIndicator = document.getElementById('roleIndicator');
      const submitBtn = document.getElementById('submitBtn');
      const registerLink = document.getElementById('registerLink');
      const roleInput = document.getElementById('roleInput');

      roleInput.value = role;

      if (role === 'penyewa') {
        btnPenyewa.classList.add('active');
        btnMitra.classList.remove('active');
        roleIndicator.innerHTML = 'Login sebagai: <strong>Penyewa Barang</strong>';
        submitBtn.innerHTML = 'Masuk Sebagai Penyewa <i class="fa-solid fa-arrow-right-to-bracket"></i>';
        registerLink.textContent = 'Daftar Penyewa';
        registerLink.href = '<?= base_url('pages/daftar.php?role=penyewa' . ($redirect_tujuan !== '' ? '&redirect=' . rawurlencode($redirect_tujuan) : '')); ?>';
      } else {
        btnMitra.classList.add('active');
        btnPenyewa.classList.remove('active');
        roleIndicator.innerHTML = 'Login sebagai: <strong>Pemilik Barang (Mitra)</strong>';
        submitBtn.innerHTML = 'Masuk Pemilik Barang <i class="fa-solid fa-arrow-right-to-bracket"></i>';
        registerLink.textContent = 'Daftar Pemilik Barang';
        registerLink.href = '<?= base_url('pages/daftar.php?role=mitra' . ($redirect_tujuan !== '' ? '&redirect=' . rawurlencode($redirect_tujuan) : '')); ?>';
      }
    }

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