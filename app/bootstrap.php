<?php
declare(strict_types=1);

use Rentique\Storage\JsonStore;
use Rentique\Controllers\ApiController;
use Rentique\Middleware\Session;

require dirname(__DIR__).'/backend/bootstrap.php';
$sessionDir = dirname(__DIR__).'/.local/sessions';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
    throw new RuntimeException('Folder sesi tidak dapat dibuat.');
}
ini_set('session.save_path', $sessionDir);
Session::start();

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int|string|float|null $value): string
{
    return 'Rp'.number_format((float)($value ?? 0), 0, ',', '.');
}

function date_label(?string $value): string
{
    if (!$value) return '—';
    $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return $date->format('j').' '.$months[(int)$date->format('n') - 1].' '.$date->format('Y');
}

function status_label(string $value): string
{
    return [
        'DRAFT'=>'Draf', 'RENTED'=>'Disewa', 'RETURNED'=>'Dikembalikan',
        'PAID'=>'Lunas', 'PARTIALLY_PAID'=>'Dibayar sebagian', 'UNPAID'=>'Belum dibayar',
        'DRESS'=>'Gaun', 'SUIT'=>'Jas', 'COSTUME'=>'Kostum',
        'INITIAL'=>'Stok awal', 'ADJUSTMENT'=>'Penyesuaian', 'RENTAL'=>'Penyewaan', 'RETURN'=>'Pengembalian',
        'CASH'=>'Tunai', 'TRANSFER'=>'Transfer bank', 'EWALLET'=>'Dompet digital',
    ][$value] ?? $value;
}

function icon(string $name, int $size = 18): string
{
    $paths = [
        'bag'=>'<path d="M6 7h12l2 14H4L6 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'arrow-up'=>'<path d="M6 18 18 6M6 6h12v12"/>',
        'menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
        'chevron'=>'<path d="m9 5 7 7-7 7"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'shirt'=>'<path d="m8 3-6 4 3 5 3-2v11h8V10l3 2 3-5-6-4a4 4 0 0 1-8 0Z"/>',
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'rentals'=>'<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v5H9zM9 12h6M9 16h6"/>',
        'return'=>'<path d="M3 10a9 9 0 1 1 1 8M3 4v6h6"/>',
        'users'=>'<circle cx="9" cy="8" r="3"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 5a3 3 0 0 1 0 6M22 21v-2a7 7 0 0 0-4-6"/>',
        'book'=>'<path d="M12 5c-3-2-6-2-10-1v16c4-1 7-1 10 1 3-2 6-2 10-1V4c-4-1-7-1-10 1Zm0 0v16"/>',
        'logout'=>'<path d="M9 4H3v16h6m5-13 5 5-5 5M8 12h11"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'minus'=>'<path d="M5 12h14"/>',
        'filter'=>'<path d="M4 7h16M7 12h10M10 17h4"/>',
    ];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['shirt']).'</svg>';
}

function api_call(string $method, string $path, array $body = [], array $query = [], ?string $key = null): mixed
{
    static $store;
    $store ??= new JsonStore();
    $previous = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
    if ($key !== null) $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    try {
        return (new ApiController($store))->dispatch($method, $path, $body, $query);
    } finally {
        if ($previous === null) unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
        else $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $previous;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    if (!array_key_exists('auth_user', $GLOBALS)) {
        $GLOBALS['auth_user'] = api_call('GET', '/auth/me');
    }
    return $GLOBALS['auth_user'];
}

function require_user(?string $role = null): array
{
    $user = current_user();
    if (!$user) redirect('/login?next='.rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    return Session::requireRole($user, $role);
}

function request_key(): string { return bin2hex(random_bytes(24)); }

function form_fields(string $action, ?string $key = null): void
{
    echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';
    echo '<input type="hidden" name="action" value="'.h($action).'">';
    if ($key !== null) echo '<input type="hidden" name="request_key" value="'.h($key).'">';
}

function post_string(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $default;
    if (!is_string($value)) throw new InvalidArgumentException('Isian '.$name.' tidak valid.');
    return trim($value);
}

function input_int(string $name, int $default = 0): int
{
    if (!isset($_POST[$name]) || $_POST[$name] === '') return $default;
    $value = $_POST[$name];
    if (!is_string($value) || !preg_match('/^-?(0|[1-9][0-9]*)$/D', $value)) {
        throw new InvalidArgumentException('Isian '.$name.' harus bilangan bulat.');
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) throw new InvalidArgumentException('Isian '.$name.' terlalu besar.');
    return $number;
}

function redirect(string $url): never
{
    if (!str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) {
        throw new InvalidArgumentException('Tujuan halaman tidak valid.');
    }
    header('Location: '.$url, true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302);
    exit;
}

function flash(string $message): void { $_SESSION['notice'] = $message; }

function bag(): array
{
    return $_SESSION['bag'] ??= ['items'=>[], 'days'=>1, 'key'=>request_key()];
}

function save_bag(array $bag): void { $_SESSION['bag'] = $bag; }
function bag_input(): array { $bag = bag(); return ['rental_days'=>$bag['days'], 'items'=>$bag['items']]; }

function product_image(array $product): string
{
    $url = $product['image_url'] ?? '';
    if (is_string($url) && (preg_match('~^/images/[A-Za-z0-9_.-]+$~D', $url) || preg_match('~^https?://~i', $url))) return $url;
    return '/images/'.(['DRESS'=>'dress', 'SUIT'=>'suit', 'COSTUME'=>'costume'][$product['type'] ?? ''] ?? 'dress').'.webp';
}
