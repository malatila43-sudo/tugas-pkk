<?php
// ============================================
// KAILKILAT.COM - Koneksi Database
// ============================================

// Kalau sudah ada koneksi di halaman ini, jangan buka yang baru.
if (isset($koneksi) && $koneksi instanceof mysqli) {
    return;
}

// Bisa diatur lewat environment variable, kalau tidak ada pakai
// pengaturan bawaan Laragon (root tanpa password).
$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'penyewaan_pancingan';
$port   = (int) (getenv('DB_PORT') ?: 3306);

$koneksi = new mysqli($host, $user, $pass, $dbname, $port);

if ($koneksi->connect_error) {
    die('Gagal koneksi ke database: ' . $koneksi->connect_error);
}

$koneksi->set_charset('utf8mb4');