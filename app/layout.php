<?php
declare(strict_types=1);

function navigation_links(string $path, ?array $user): void
{
    foreach (['/'=>'Beranda', '/catalog'=>'Koleksi', '/#cara-sewa'=>'Cara sewa'] as $url=>$label) {
        $active = $url === $path || ($url === '/catalog' && str_starts_with($path, '/clothing/'));
        echo '<a href="'.h($url).'"'.($active ? ' class="rt-nav-active" aria-current="page"' : '').'>'.h($label).'</a>';
    }
    if ($user) echo '<a href="'.($user['role'] === 'ADMIN' ? '/admin' : '/rentals').'">'.($user['role'] === 'ADMIN' ? 'Dasbor' : 'Sewaan saya').'</a>';
}

function page_start(string $title = 'Rentique — Dress for the moment.'): void
{
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h($title) ?></title><meta name="description" content="Sewa gaun, jas, dan kostum pilihan untuk setiap momen istimewa."><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/base.css"><link rel="stylesheet" href="/assets/storefront.css"><link rel="stylesheet" href="/assets/account.css"><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/native.css"><link rel="stylesheet" href="/assets/storefront-native.css"><link rel="stylesheet" href="/assets/admin-native.css"><script src="/assets/native.js" defer></script></head><body><?php
}

function store_start(string $path): void
{
    $user = current_user();
    $count = array_sum(array_column(bag()['items'], 'quantity'));
    ?><div class="rentique-store"><a href="#konten-utama" class="rt-skip">Langsung ke konten</a><header class="rt-site-header"><div class="rt-frame rt-header-inner"><details class="native-mobile-menu"><summary aria-label="Buka navigasi"><?= icon('menu',22) ?></summary><nav aria-label="Menu seluler"><?php navigation_links($path,$user); ?></nav></details><a class="rt-wordmark" href="/" aria-label="Rentique, beranda">rentique<span>.</span></a><nav class="rt-nav" aria-label="Menu utama"><?php navigation_links($path,$user); ?></nav><div class="rt-nav-actions"><a href="<?= $user ? '/profile' : '/login' ?>" class="rt-account" aria-label="<?= $user ? 'Buka profil' : 'Masuk' ?>"><?= icon('user',18) ?><span><?= $user ? 'Profil' : 'Masuk' ?></span></a><a class="rt-nav-bag" href="/cart" aria-label="Tas sewa, <?= h($count) ?> item"><?= icon('bag',21) ?><?php if ($count > 0) { ?><span class="rt-count"><?= h($count) ?></span><?php } ?></a></div></div></header><?php
}

function store_end(): void
{
    ?><footer class="rt-site-footer"><div class="rt-frame rt-footer-inner"><a class="rt-wordmark" href="/">rentique<span>.</span></a><nav aria-label="Menu bawah"><a href="/catalog">Koleksi</a><a href="/#cara-sewa">Cara sewa</a><a href="/cart">Tas sewa</a></nav><small>© <?= date('Y') ?> Rentique</small></div></footer></div><?php
}

function page_notices(?string $error): void
{
    if (isset($_SESSION['notice'])) {
        echo '<div class="native-notice" role="status">'.h($_SESSION['notice']).'</div>';
        unset($_SESSION['notice']);
    }
    if ($error !== null) echo '<div class="native-notice native-error" role="alert">'.h($error).'</div>';
}
