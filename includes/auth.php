<?php
// ============================================
// KAILKILAT.COM - Helper login & pemisahan role
// Dipakai semua halaman supaya penyewa dan
// pemilik barang (mitra) tidak ketuker.
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    // Cookie sesi tidak bisa dibaca JavaScript dan tidak ikut terkirim
    // dari situs lain, supaya sesi login lebih aman.
    $pakai_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $pakai_https,
    ]);

    session_start();
}

// Jangan biarkan browser menyimpan cache halaman dinamis, supaya
// perubahan data (stok, katalog, dsb) selalu tampil terbaru.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Folder utama proyek (satu level di atas folder includes/).
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

// URL lengkap dari root web, dipakai supaya link dan aset tetap benar
// walau halaman berada di subfolder (mis. pages/).
function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $base = '/';
        $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $proyek  = realpath(ROOT_DIR);

        if ($docroot && $proyek) {
            $docroot = str_replace('\\', '/', $docroot);
            $proyek  = str_replace('\\', '/', $proyek);

            if (strpos($proyek, $docroot) === 0) {
                $base = rtrim(substr($proyek, strlen($docroot)), '/') . '/';
            }
        }
    }

    return $base . ltrim($path, '/');
}

function daftar_role(): array
{
    return ['penyewa', 'mitra'];
}

function valid_role($role): bool
{
    return in_array($role, daftar_role(), true);
}

function sudah_login(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function id_user(): int
{
    return sudah_login() ? (int) $_SESSION['user_id'] : 0;
}

function nama_user(): string
{
    return (string) ($_SESSION['nama'] ?? '');
}

function email_user(): string
{
    return (string) ($_SESSION['email'] ?? '');
}

function role_user(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function label_role(string $role): string
{
    return $role === 'mitra' ? 'Pemilik Barang' : 'Penyewa';
}

function halaman_dashboard(string $role): string
{
    return base_url($role === 'mitra' ? 'pages/dashboard_mitra.php' : 'pages/dashboard_penyewa.php');
}

// Alamat tujuan dari query string boleh dipakai asal masih lokal.
// Ini mencegah open redirect ke situs lain lewat parameter ?redirect=
function redirect_aman(?string $target, string $default): string
{
    $target = trim((string) $target);

    $decoded = rawurldecode($target);

    if ($target === '' || $target[0] !== '/' || strpos($target, '//') === 0
        || strpos($decoded, '//') === 0
        || strpos($decoded, '\\') !== false
        || strpos($target, "\r") !== false
        || strpos($target, "\n") !== false
    ) {
        return $default;
    }

    return $target;
}

function url_dashboard_manusia(): string
{
    return halaman_dashboard(role_user());
}

// ============================================
// Perbandingan password
// ============================================
// Password lama di database sempat tersimpan polos dan ada karakter
// "\n" nyasar di akhir (misal "admin yang punya\n"). Karena itu setiap
// password yang dibandingkan harus dibersihkan dulu: newline asli maupun
// teks "\n" dua karakter, lalu spasi di kedua ujung.
function bersihkan_password(string $password): string
{
    $password = str_replace(["\r\n", "\r", "\n"], '', $password);
    $password = str_replace('\\n', '', $password);
    $password = str_replace('\\r', '', $password);
    return trim($password);
}

// True kalau password user cocok dengan yang tersimpan di database.
// Menyangani dua bentuk penyimpanan: hash bcrypt atau password polos lama.
function password_cocok(string $masukan, string $tersimpan): bool
{
    $masukan = bersihkan_password($masukan);

    if ($masukan === '' || $tersimpan === '') {
        return false;
    }

    if (password_verify($masukan, $tersimpan)) {
        return true;
    }

    // Password polos lama, lengkap dengan sisa "\n" nyasar kalau ada.
    return bersihkan_password($tersimpan) === $masukan;
}

// True kalau kolom password masih storing polos (belum di-hash).
function password_masih_plos(string $tersimpan): bool
{
    return password_get_info($tersimpan)['algo'] === null;
}

function masuk_dan_simpan(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['nama']    = $user['nama'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
}

function set_flash(string $pesan, string $tipe = 'info'): void
{
    $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe];
}

function ambil_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ============================================
// Keamanan form (CSRF) & sesi
// ============================================

// Satu token per sesi. Ditaruh di setiap form POST supaya form yang
// dikirim dari situs lain tidak bisa mengubah data.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

// True kalau token pada POST cocok dengan token sesi.
function csrf_valid(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

// Hentikan permintaan POST kalau token tidak cocok.
// $kembali = alamat tujuan setelah gagal (default: halaman yang sama).
function cek_csrf(?string $kembali = null): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if (csrf_valid()) {
        return;
    }

    set_flash('Sesi formulir sudah kedaluwarsa. Silakan isi ulang form.', 'error');

    $tujuan = $kembali ?? (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($tujuan === '' || $tujuan[0] !== '/') {
        $tujuan = base_url('index.php');
    }

    header('Location: ' . $tujuan);
    exit;
}

// Centang "Ingat Saya": cookie sesi disimpan 30 hari.
function set_ingat_saya(bool $aktif): void
{
    $umur = 60 * 60 * 24 * 30;

    if ($aktif) {
        ini_set('session.gc_maxlifetime', (string) $umur);
        $_SESSION['ingat_saya'] = true;
    } else {
        unset($_SESSION['ingat_saya']);
    }

    setcookie(session_name(), session_id(), [
        'expires'  => $aktif ? time() + $umur : 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

// Bersihkan sesi dengan rapi saat keluar.
function hancurkan_sesi(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $param = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $param['path'],
            'domain'   => $param['domain'],
            'secure'   => (bool) $param['secure'],
            'httponly' => (bool) $param['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

// Wajib sudah login, kalau belum lempar ke halaman masuk
function wajib_login(): void
{
    if (!sudah_login()) {
        set_flash('Silakan masuk terlebih dahulu.');
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}

// Wajib login DAN role-nya cocok, supaya tidak ada yang salah masuk halaman
function wajib_role(string $role): void
{
    wajib_login();

    if (role_user() !== $role) {
        set_flash(
            'Halaman ini khusus untuk akun ' . label_role($role) .
            '. Kamu masuk sebagai ' . label_role(role_user()) . '.',
            'error'
        );
        header('Location: ' . url_dashboard_manusia());
        exit;
    }
}

// ============================================
// Helper gambar barang
// ============================================
// Dipakai semua halaman supaya gambar tidak pernah rusak ( broken image ).
// Kalau nama file di database salah, kosong, atau filenya hilang,
// otomatis pakai gambar bawaan.
define('GAMBAR_DEFAULT', 'img_sewa_aktif.svg');

function url_gambar(?string $gambar): string
{
    $gambar = trim((string) $gambar);
    $folder = ROOT_DIR . '/assets/img/';

    // Hanya nama file sederhana, tolak path atau URL mencurigakan
    $pola_boleh = '/^[A-Za-z0-9._-]+\.(svg|png|jpe?g|webp|avif|gif)$/i';

    if ($gambar !== ''
        && preg_match($pola_boleh, $gambar)
        && is_file($folder . $gambar)
    ) {
        return base_url('assets/img/' . $gambar);
    }

    return base_url('assets/img/' . GAMBAR_DEFAULT);
}

// Cetak tag img lengkap. Lazy loading + decoding async supaya halaman
// tampil lebih cepat: gambar baru dimuat saat sudah mendekati layar.
function tag_gambar(?string $gambar, string $alt, string $kelas = ''): string
{
    $src = htmlspecialchars(url_gambar($gambar), ENT_QUOTES);
    $alt = htmlspecialchars($alt, ENT_QUOTES);
    $cls = $kelas !== '' ? ' class="' . htmlspecialchars($kelas, ENT_QUOTES) . '"' : '';

    return '<img' . $cls . ' src="' . $src . '" alt="' . $alt . '"'
         . ' loading="lazy" decoding="async" width="800" height="600">';
}

// ============================================
// Kotak masuk pesan
// ============================================

// Tanggal Indonesia ringkas: "d-m-Y H:i"
function tanggal_indonesia(?string $waktu): string
{
    if (!$waktu || $waktu === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime($waktu);
    if ($ts === false) {
        return '-';
    }

    return date('d-m-Y H:i', $ts);
}

// Waktu relatif: "baru saja", "5 menit lalu", "3 jam lalu", atau tanggal biasa
function waktu_indonesia(?string $waktu, ?int $menit_lalu = null): string
{
    if (!$waktu || $waktu === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime($waktu);
    if ($ts === false) {
        return '-';
    }

    if ($menit_lalu === null) {
        $menit_lalu = (int) floor((time() - $ts) / 60);
    }

    if ($menit_lalu < 1) {
        return 'baru saja';
    }
    if ($menit_lalu < 60) {
        return $menit_lalu . ' menit lalu';
    }

    $jam = (int) floor($menit_lalu / 60);
    if ($jam < 24) {
        return $jam . ' jam lalu';
    }

    return tanggal_indonesia($waktu);
}

// Pesan yang belum dibuka pemilik barang (badge merah di menu).
function pesan_belum_dibaca(?int $mitra_id = null): int
{
    global $koneksi;

    $mitra_id = $mitra_id ?? id_user();
    if ($mitra_id <= 0) {
        return 0;
    }

    $stmt = $koneksi->prepare("SELECT COUNT(*) AS jml FROM pesan_kontak WHERE mitra_id = ? AND dibaca = 0");
    $stmt->bind_param('i', $mitra_id);
    $stmt->execute();
    $jml = (int) ($stmt->get_result()->fetch_assoc()['jml'] ?? 0);
    $stmt->close();

    return $jml;
}

// Menu navigasi berbeda untuk penyewa dan pemilik barang
function menu_navigasi(): array
{
    if (!sudah_login()) {
        return [
            'beranda' => ['label' => 'Beranda', 'href' => base_url('index.php')],
            'katalog' => ['label' => 'Katalog', 'href' => base_url('pages/katalog.php')],
            'kontak'  => ['label' => 'Kontak',  'href' => base_url('pages/kontak.php')],
        ];
    }

    if (role_user() === 'mitra') {
        $baru = pesan_belum_dibaca();

        return [
            'dashboard_mitra' => ['label' => 'Dashboard Mitra', 'href' => base_url('pages/dashboard_mitra.php')],
            'barang_saya'     => ['label' => 'Barang Saya',    'href' => base_url('pages/barang_saya.php')],
            'katalog'         => ['label' => 'Lihat Katalog',  'href' => base_url('pages/katalog.php')],
            'kontak'          => [
                'label' => 'Kotak Masuk' . ($baru > 0 ? ' (' . $baru . ')' : ''),
                'href'  => base_url('pages/dashboard_mitra.php') . '#kotak-masuk',
            ],
        ];
    }

    return [
        'dashboard_penyewa' => ['label' => 'Dashboard Penyewa', 'href' => base_url('pages/dashboard_penyewa.php')],
        'katalog'           => ['label' => 'Katalog',           'href' => base_url('pages/katalog.php')],
        'kontak'            => ['label' => 'Kontak',            'href' => base_url('pages/kontak.php')],
    ];
}