<?php
declare(strict_types=1);
namespace Rentique\Middleware;

use DomainException;

final class Session
{
    public static function start(): void
    {
        session_name('rentique_php_session');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => filter_var(getenv('SESSION_SECURE'), FILTER_VALIDATE_BOOLEAN) || !empty($_SERVER['HTTPS']),
        ]);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(): void
    {
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            throw new DomainException('Sesi formulir kedaluwarsa. Muat ulang lalu coba lagi.', 403);
        }
    }

    public static function changeIdentity(?int $id): void
    {
        session_regenerate_id(true);
        $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'user_id' => $id];
    }

    public static function requireRole(?array $user, ?string $role = null): array
    {
        if (!$user) {
            throw new DomainException('Masuk untuk melanjutkan.', 401);
        }
        if ($role !== null && $user['role'] !== $role) {
            throw new DomainException('Akun ini tidak memiliki akses.', 403);
        }
        return $user;
    }
}
