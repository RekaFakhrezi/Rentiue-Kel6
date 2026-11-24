<?php
declare(strict_types=1);
use Rentique\Storage\JsonStore;
use Rentique\Controllers\ApiController;
use Rentique\Middleware\Session;

require dirname(__DIR__).'/bootstrap.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, mixed $data, string $message): never
{
    http_response_code($status);
    if (isset($_SESSION['csrf'])) {
        header('X-CSRF-Token: '.$_SESSION['csrf']);
    }
    echo json_encode(['success' => $status < 400, 'data' => $data, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri === '/' || $uri === '/health') {
        respond(200, ['status' => 'ok', 'app' => 'Rentique API'], 'Rentique API is operational.');
    }
    if (!is_string($uri) || !str_starts_with($uri, '/api/')) {
        respond(404, null, 'Rute API tidak ditemukan.');
    }
    $path = substr($uri, 4);
    $method = $_SERVER['REQUEST_METHOD'];
    Session::start();
    $body = [];
    if (!in_array($method, ['GET','HEAD'], true)) {
        Session::verifyCsrf();
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if (strlen($raw) > 65536) {
            respond(413, null, 'Permintaan terlalu besar.');
        }
        if ($raw !== '') {
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                respond(415, null, 'Gunakan application/json.');
            }
            $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($body) || !str_starts_with(ltrim($raw), '{')) {
                respond(400, null, 'Kirim objek JSON.');
            }
        }
    }
    $result = (new ApiController(new JsonStore()))->dispatch($method, $path, $body, $_GET);
    $created = $method === 'POST' && ($path === '/rentals' || $path === '/clothings' || preg_match('~/([1-9][0-9]*)/(payments|return)$~', $path));
    respond($created ? 201 : 200, $result, '');
} catch (JsonException) {
    respond(400, null, 'JSON tidak valid.');
} catch (InvalidArgumentException $e) {
    respond(422, null, $e->getMessage());
} catch (DomainException $e) {
    $code = $e->getCode();
    respond($code >= 400 && $code <= 499 ? $code : 409, null, $e->getMessage());
} catch (RuntimeException $e) {
    error_log('Rentique local storage: '.$e->getMessage());
    respond(503, null, 'Penyimpanan lokal belum tersedia. Periksa izin atau pulihkan data dari cadangan.');
} catch (Throwable $e) {
    error_log('Rentique '.get_class($e).' in '.basename($e->getFile()).' line '.$e->getLine());
    respond(500, null, 'Permintaan belum dapat diproses.');
}
