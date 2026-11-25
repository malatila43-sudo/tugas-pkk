<?php
// ============================================
// KAILKILAT.COM - Bantuan hitung stok
// ============================================
// Kolom pancingan.stok = jumlah unit yang dimiliki mitra.
// Stok yang benar-benar bisa disewa dihitung dari tanggal:
// total unit dikurangi unit yang sudah dipesan pada rentang itu.

// Jumlah unit yang sudah dipesan pada rentang tanggal (inklusif).
function jumlah_terpesan(mysqli $koneksi, int $pancingan_id, string $mulai, string $selesai): int
{
    $stmt = $koneksi->prepare(
        "SELECT COALESCE(SUM(jumlah), 0) AS jml
         FROM sewa
         WHERE pancingan_id = ?
           AND NOT (tanggal_selesai < ? OR tanggal_mulai > ?)"
    );
    $stmt->bind_param('iss', $pancingan_id, $mulai, $selesai);
    $stmt->execute();
    $jml = (int) ($stmt->get_result()->fetch_assoc()['jml'] ?? 0);
    $stmt->close();

    return $jml;
}

// Sisa unit yang bisa disewa pada rentang tanggal.
function stok_tersedia(mysqli $koneksi, int $pancingan_id, int $stok_total, string $mulai, string $selesai): int
{
    return max(0, $stok_total - jumlah_terpesan($koneksi, $pancingan_id, $mulai, $selesai));
}

// Potongan SQL untuk stok yang tersedia hari ini, dipakai di
// beranda dan katalog supaya angka stok selalu sinkron.
function sql_stok_hari_ini(string $alias = 'p'): string
{
    return "GREATEST({$alias}.stok - COALESCE((
                SELECT SUM(s.jumlah) FROM sewa s
                WHERE s.pancingan_id = {$alias}.id
                  AND CURDATE() BETWEEN s.tanggal_mulai AND s.tanggal_selesai
            ), 0), 0)";
}
