<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path)) { http_response_code(400); exit('Alamat halaman tidak valid.'); }
if (str_starts_with($path, '/api/')) {
    $sessionDir = dirname(__DIR__).'/.local/sessions';
    if (!is_dir($sessionDir)) mkdir($sessionDir, 0700, true);
    ini_set('session.save_path', $sessionDir);
    require dirname(__DIR__).'/backend/public/index.php';
    exit;
}
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/layout.php';
require dirname(__DIR__).'/app/storefront.php';
require dirname(__DIR__).'/app/account.php';
require dirname(__DIR__).'/app/admin.php';

function error_status(Throwable $error): int
{
    if ($error instanceof InvalidArgumentException) return 422;
    if ($error instanceof DomainException) return $error->getCode() >= 400 && $error->getCode() <= 499 ? $error->getCode() : 409;
    return 500;
}

function error_message(Throwable $error): string
{
    if ($error instanceof InvalidArgumentException || $error instanceof DomainException) return $error->getMessage();
    error_log('Rentique PHP '.get_class($error).': '.$error->getMessage());
    return 'Data lokal belum dapat dibaca atau disimpan. Periksa izin folder storage dan jangan hapus data.json yang masih digunakan.';
}

$error = null;
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET','HEAD','POST'], true)) throw new DomainException('Metode tidak didukung.', 405);
    if ($method === 'POST') {
        $token = post_string('csrf');
        if (!hash_equals($_SESSION['csrf'], $token)) throw new DomainException('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.', 403);
        $action = post_string('action');
        if (!account_action($action) && !storefront_action($action) && !admin_action($action)) throw new DomainException('Tindakan tidak ditemukan.', 404);
    }
} catch (Throwable $caught) {
    http_response_code(error_status($caught));
    $error = error_message($caught);
}

$isAdmin = $path === '/admin' || str_starts_with($path, '/admin/');
ob_start();
try {
    $found = $isAdmin ? render_admin($path) : (render_storefront($path) || render_account($path));
    if (!$found) {
        http_response_code(404);
        echo '<main class="ac-page" id="konten-utama"><h1>Halaman tidak ditemukan</h1><a class="ac-btn" href="/catalog">Jelajahi koleksi</a></main>';
    }
    $content = ob_get_clean();
} catch (Throwable $caught) {
    ob_end_clean();
    http_response_code(error_status($caught));
    $error = error_message($caught);
    $content = '<main class="ac-page" id="konten-utama"><h1>Halaman belum dapat dibuka</h1><p>Periksa pesan di atas, lalu kembali ke halaman sebelumnya.</p><a class="ac-btn" href="/">Kembali ke beranda</a></main>';
    $isAdmin = false;
}
page_start($isAdmin ? 'Admin — Rentique' : 'Rentique — Dress for the moment.');
if (!$isAdmin) store_start($path);
page_notices($error);
echo $content;
if (!$isAdmin) store_end();
echo '</body></html>';
