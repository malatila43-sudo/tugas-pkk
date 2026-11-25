<?php
// ============================================
// KAILKILAT.COM - Bikin akun resmi
// ============================================
// 1 akun PEMILIK BARANG khusus (gak dibagikan ke siapa-siapa)
// Akun penyewa dibuat sendiri lewat halaman daftar
//
// Jalankan: php tools/buat_akun.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya bisa dijalankan dari terminal.');
}

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/koneksi.php';

// Kredensial dibaca dari environment variable, jangan ditulis di repo.
// Contoh (PowerShell):
//   $env:OWNER_EMAIL = "email@kamu.com"; $env:OWNER_PASSWORD = "sandi-kamu"
//   php tools/buat_akun.php
$akun = [];
$ownerEmail = getenv('OWNER_EMAIL') ?: '';
$ownerSandi = getenv('OWNER_PASSWORD') ?: '';
if ($ownerEmail !== '' && $ownerSandi !== '') {
    $akun[] = ['nama' => 'Pemilik Barang', 'email' => $ownerEmail, 'sandi' => $ownerSandi, 'role' => 'mitra'];
}
$mitraEmail = getenv('MITRA_EMAIL') ?: '';
$mitraSandi = getenv('MITRA_PASSWORD') ?: '';
if ($mitraEmail !== '' && $mitraSandi !== '') {
    $akun[] = ['nama' => 'Mitra', 'email' => $mitraEmail, 'sandi' => $mitraSandi, 'role' => 'mitra'];
}
if ($akun === []) {
    exit("Set OWNER_EMAIL dan OWNER_PASSWORD dulu sebelum menjalankan tool ini.\n");
}

// Jangan hapus akun atau data lain. Akun bawaan hanya dibuat kalau belum
// ada, dan kalau sudah ada password-nya direset ke sandi bawaan.
foreach ($akun as $a) {
    $hash = password_hash($a['sandi'], PASSWORD_DEFAULT);
    $nama = $a['nama'];
    $role = $a['role'];

    $stmt = $koneksi->prepare(
        "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nama = VALUES(nama), password = VALUES(password), role = VALUES(role)"
    );
    $stmt->bind_param('ssss', $nama, $a['email'], $hash, $role);
    $stmt->execute();
    $stmt->close();

    printf("SIAP   %-26s role: %s\n", $a['email'], $role);
}

// Semua barang jadi milik akun pemilik barang
$koneksi->query("UPDATE pancingan
                 SET owner_id = (SELECT id FROM users WHERE role = 'mitra' ORDER BY id LIMIT 1)
                 WHERE owner_id IS NULL");

echo "\nData pengguna, barang, dan riwayat sewa yang sudah ada tidak dihapus.\n";
echo "Selesai. Daftar akun sekarang:\n";
$r = $koneksi->query("SELECT id, email, role FROM users ORDER BY id");
while ($u = $r->fetch_assoc()) {
    printf("  id=%d  %-26s %s\n", $u['id'], $u['email'], $u['role']);
}
