<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && (str_starts_with($path, '/assets/') || str_starts_with($path, '/images/') || $path === '/favicon.svg')) {
    $public = __DIR__.DIRECTORY_SEPARATOR.'public';
    $file = realpath($public.rawurldecode($path));
    if ($file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)
        && preg_match('/\.(css|js|woff2|svg|webp|png|jpe?g|txt)$/iD', $file)) return false;
}
require __DIR__.'/public/index.php';
