<?php
// ============================================
// KAILKILAT.COM - Header bersama (semua halaman)
// ============================================
// Cara pakai:
//   $page_title = 'Judul Halaman';
//   $page_css   = ['katalog.css'];
//   $active_menu = 'katalog';
//   $hide_brand = true;  // sembunyikan logo di pojok kiri atas
//   include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/auth.php';

$root = ROOT_DIR;

if (!isset($page_title)) $page_title = 'KAILKILAT.COM';
if (!isset($page_css)) $page_css = ['katalog.css'];
if (!isset($active_menu)) $active_menu = '';
if (!isset($hide_brand)) $hide_brand = false;

// Menu berbeda untuk penyewa, pemilik barang, dan pengunjung
$menu = menu_navigasi();
$flash = ambil_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?> - KAILKILAT.COM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          media="print" onload="this.media='all';this.onload=null">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </noscript>
<?php foreach ((array) $page_css as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/' . $css) . '?v=' . (@filemtime($root . '/assets/css/' . $css) ?: time())); ?>">
<?php endforeach; ?>
</head>
<body>
    <header>
        <?php if (!$hide_brand): ?>
            <a href="<?= sudah_login() ? url_dashboard_manusia() : base_url('index.php'); ?>" class="logo"><img src="<?= htmlspecialchars(base_url('assets/img/img_logo.svg')); ?>?v=<?= @filemtime($root . '/assets/img/img_logo.svg') ?: time(); ?>" alt="Logo KAILKILAT" class="brand-logo">KAILKILAT.COM</a>
        <?php else: ?>
            <div style="flex: 1;"></div>
        <?php endif; ?>
        <nav>
            <?php foreach ($menu as $menu_kunci => $menu_item): ?>
                <a href="<?= htmlspecialchars($menu_item['href']); ?>"
                   class="<?= $active_menu === $menu_kunci ? 'nav-aktif' : ''; ?>"
                   <?= $active_menu === $menu_kunci ? 'aria-current="page"' : ''; ?>><?= htmlspecialchars($menu_item['label']); ?></a>
            <?php endforeach; ?>
            <?php if (sudah_login()): ?>
                <span class="nav-user">
                    <?= role_user() === 'mitra' ? 'Pemilik Barang' : 'Penyewa' ?>,
                    <?= htmlspecialchars(nama_user()); ?>
                </span>
                <form action="<?= base_url('pages/logout.php'); ?>" method="POST" class="logout-form">
                    <?= csrf_field(); ?>
                    <button type="submit" class="nav-btn">Keluar</button>
                </form>
            <?php else: ?>
                <a href="<?= base_url('pages/login.php'); ?>" class="nav-btn">Masuk</a>
                <a href="<?= base_url('pages/daftar.php'); ?>" class="nav-btn">Daftar</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="alert-msg <?= htmlspecialchars($flash['tipe']); ?> flash"><?= htmlspecialchars($flash['pesan']); ?></div>
    <?php endif; ?>