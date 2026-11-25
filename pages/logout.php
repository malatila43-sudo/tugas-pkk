<?php
require_once __DIR__ . '/../includes/auth.php';

// Keluar hanya boleh lewat tombol (POST) dan token form yang sah.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_valid()) {
    set_flash('Permintaan keluar tidak valid. Silakan coba lagi.', 'error');
    header('Location: ' . base_url('index.php'));
    exit;
}

hancurkan_sesi();
header('Location: ' . base_url('index.php'));
exit;
