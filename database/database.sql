-- ============================================
-- KAILKILAT.COM - Database & Tabel (penyewaan_pancingan)
-- Import file ini di phpMyAdmin (tab Import)
-- atau lewat terminal: mysql -u root < database/database.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS penyewaan_pancingan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE penyewaan_pancingan;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('penyewa','mitra') DEFAULT 'penyewa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ============================================
-- Akun bawaan
-- ============================================
-- Kredensial tidak disimpan di repo. Setelah import, jalankan:
--   php tools/buat_akun.php
-- dengan environment variable OWNER_EMAIL dan OWNER_PASSWORD
-- (opsional: MITRA_EMAIL dan MITRA_PASSWORD untuk akun mitra kedua).
-- Penyewa mendaftar akun sendiri lewat pages/daftar.php (role = 'penyewa').

CREATE TABLE IF NOT EXISTS pancingan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  deskripsi TEXT,
  harga INT NOT NULL,
  gambar VARCHAR(255) DEFAULT 'joran-danau.jpg',
  -- stok = jumlah unit total milik mitra.
  -- Sisa yang bisa disewa dihitung otomatis dari tanggal sewa.
  stok INT DEFAULT 1,
  owner_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pancingan_owner (owner_id),
  INDEX idx_pancingan_nama (nama),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tiap barang punya gambar sendiri, file-nya ada di folder ini
INSERT INTO pancingan (nama, deskripsi, harga, gambar, stok) VALUES
('Pancingan Laut', 'Joran kuat untuk mancing di laut lepas. Karbon impor, tahan ikan besar seperti tuna dan kakap. Dapat: joran, reel, pelampung, senar, kail, keramba.', 1159375, 'profesional.jpg', 5),
('Pancing Sungai', 'Ringan dan praktis, cocok untuk mancing santai di sungai dengan arus sedang. Panjang 2,7 meter. Dapat: joran, reel, senar, pelampung, kail.', 56250, 'sungai.jpg', 8),
('Pancing Danau', 'Nyaman untuk mancing santai di danau. Sensitif dan pas untuk pemula yang baru belajar. Dapat: joran, reel, senar, pelampung, kail.', 62500, 'joran-danau.jpg', 6),
('Set Pancing Lengkap', 'Satu set praktis untuk pemula, langsung siap pakai. Dapat: joran, reel, senar, kail, kotak tackle, dan pelampung.', 150000, 'set-lengkap.jpg', 4),
('Joran Telescopic 4 Meter', 'Bisa dilipat jadi 40 cm, ringan, dan mudah dibawa piknik. Cocok untuk traveling mancing. Dapat: joran telescopic, reel, senar, kail, dan pelampung.', 1121875, 'telescopi.avif', 7),
('Reel Spinning 2000', 'Reel putaran cepat dengan drag halus. Cocok untuk ikan kecil sampai sedang di sungai.', 68750, 'real-spining.jpg', 10),
('Joran Lulco Katak', 'Joran lulco untuk teknik katak di sungai. Panjang 1,8 meter, fleksibel dan kuat. Dapat: joran lulco, reel, senar, kail, dan umpan katak.', 1131250, 'lulco-katak.jpg', 5),
('Pancingan Kolam Perlombaan', 'Paket sewa paling laris, lengkap dan siap pakai. Dapat: joran, reel, senar, kail, dan umpan siap pakai.', 43750, 'kolam-perlombaan.jpg', 12);

-- Barang di atas otomatis jadi milik akun mitra (Pemilik Barang)
UPDATE pancingan
SET owner_id = (SELECT id FROM users WHERE role = 'mitra' ORDER BY id LIMIT 1)
WHERE owner_id IS NULL;

CREATE TABLE IF NOT EXISTS sewa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  nama_penyewa VARCHAR(100) NOT NULL DEFAULT '',
  email_penyewa VARCHAR(100) NOT NULL DEFAULT '',
  pancingan_id INT NOT NULL,
  tanggal_mulai DATE NOT NULL,
  tanggal_selesai DATE NOT NULL,
  jumlah INT DEFAULT 1,
  total_harga INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sewa_user (user_id),
  INDEX idx_sewa_barang (pancingan_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pancingan_id) REFERENCES pancingan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pesan dari form kontak.
-- user_id = pengirim (boleh NULL kalau pengunjung belum masuk)
-- mitra_id = pemilik barang yang menerima pesan
-- dibaca   = 0 masih baru, 1 sudah dibuka pemilik
CREATE TABLE IF NOT EXISTS pesan_kontak (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  mitra_id INT NULL,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  subjek VARCHAR(150) NOT NULL,
  pesan TEXT NOT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pesan_mitra (mitra_id, dibaca),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (mitra_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
