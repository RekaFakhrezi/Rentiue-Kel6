<?php
declare(strict_types=1);

function storefront_action(string $action): bool
{
    if ($action !== 'bag_add') { return false; }
    $id = input_int('clothing_id');
    $quantity = input_int('quantity');
    $days = input_int('rental_days');
    if ($id < 1 || $quantity < 1 || $days < 1) {
        throw new InvalidArgumentException('Masukkan durasi dan jumlah yang valid.');
    }
    $next = bag();
    $found = false;
    foreach ($next['items'] as &$item) {
        if ((int) $item['clothing_id'] === $id) {
            if ($quantity > PHP_INT_MAX - (int) $item['quantity']) {
                throw new InvalidArgumentException('Jumlah pakaian terlalu besar.');
            }
            $item['quantity'] = (int) $item['quantity'] + $quantity;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) { $next['items'][] = ['clothing_id' => $id, 'quantity' => $quantity]; }
    $next['days'] = $days;
    // A quote validates the entire new bag without reserving stock.
    api_call('POST', '/rentals/quote', ['rental_days' => $days, 'items' => $next['items']]);
    $next['key'] = request_key();
    save_bag($next);
    flash($quantity . ' item masuk ke tas sewa. Durasi seluruh tas menjadi ' . $days . ' hari.');
    redirect('/clothing/' . $id . '?quantity=1&days=' . $days);
}

function storefront_param(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;
    if (!is_string($value)) { throw new InvalidArgumentException('Parameter koleksi tidak valid.'); }
    return trim($value);
}

function storefront_error(Throwable $error, string $retry): void
{
    echo '<div class="rt-feedback" role="alert"><p>' . h($error->getMessage()) . '</p><a class="rt-text-link" href="' . h($retry) . '">Coba lagi</a></div>';
}

function storefront_availability(array $product): void
{
    $available = (int) $product['stock'] > 0;
    echo '<span class="rt-availability' . ($available ? '' : ' rt-out') . '">' . ($available ? 'Tersedia' : 'Sedang disewa') . '</span>';
}

function storefront_grid(array $products): void
{
    echo '<div class="rt-products">';
    foreach ($products as $product) { ?>
        <a class="rt-product-card" href="/clothing/<?= h($product['id']) ?>" aria-label="<?= h('Lihat detail ' . $product['name']) ?>">
            <div class="rt-product-photo"><img src="<?= h(product_image($product)) ?>" alt="<?= h($product['name']) ?>" loading="lazy"><span class="rt-card-action">Lihat detail <?= icon('arrow-up', 15) ?></span></div>
            <div class="rt-product-meta"><span><?= h(status_label($product['type'])) ?></span><span>Ukuran <?= h($product['size']) ?></span></div>
            <h3><?= h($product['name']) ?></h3><div class="rt-price"><?= h(money($product['rental_price_per_day'])) ?> <small>/ hari</small></div>
            <?php storefront_availability($product); ?>
        </a>
    <?php }
    echo '</div>';
}

function storefront_home(): void
{
    $types = ['DRESS' => ['Gaun', 'dress'], 'SUIT' => ['Jas', 'suit'], 'COSTUME' => ['Kostum', 'costume']];
    ?>
    <main class="rt-home" id="konten-utama"><div class="rt-frame">
        <section class="rt-hero" aria-labelledby="rt-hero-heading"><div class="rt-hero-copy"><div class="rt-collection-label">The rental collection</div><h1 id="rt-hero-heading">Dress for<br><em>the moment.</em></h1><p>Sewa gaun, jas, dan kostum pilihan untuk setiap momen istimewamu.</p><a class="rt-primary" href="/catalog">Jelajahi koleksi <?= icon('arrow', 17) ?></a></div><div class="rt-hero-visual"><img src="/images/hero.webp" alt="Model mengenakan gaun panjang berlipit dengan bahu terbuka" fetchpriority="high"></div></section>
        <section class="rt-section" aria-labelledby="rt-categories-heading"><div class="rt-section-heading"><h2 id="rt-categories-heading">Jelajahi per kategori</h2><a class="rt-text-link" href="/catalog">Lihat semua <?= icon('arrow-up', 16) ?></a></div><div class="rt-categories">
            <?php foreach ($types as $type => [$label, $image]) { ?><a href="/catalog?type=<?= h($type) ?>" class="rt-category rt-category-<?= h($image) ?>"><img src="/images/<?= h($image) ?>.webp" alt="" loading="lazy"><div class="rt-category-caption"><h3><?= h($label) ?></h3><span><?= icon('arrow-up', 20) ?></span></div></a><?php } ?>
        </div></section>
        <section class="rt-section rt-featured" aria-labelledby="rt-featured-heading"><div class="rt-section-heading"><h2 id="rt-featured-heading">Pilihan Rentique</h2><a class="rt-text-link" href="/catalog">Lihat koleksi <?= icon('arrow-up', 16) ?></a></div>
        <?php try {
            $products = api_call('GET', '/clothings');
            $featured = [];
            foreach (array_keys($types) as $type) {
                foreach ($products as $product) {
                    if ($product['type'] === $type) { $featured[$product['id']] = $product; break; }
                }
            }
            foreach ($products as $product) { $featured[$product['id']] = $product; }
            $featured = array_slice(array_values($featured), 0, 4);
            if ($featured) { storefront_grid($featured); }
            else { echo '<div class="rt-feedback">Koleksi baru sedang disiapkan.</div>'; }
        } catch (Throwable $error) { storefront_error($error, '/'); } ?>
        </section></div>
        <section class="rt-how" id="cara-sewa" aria-labelledby="rt-how-heading"><div class="rt-frame"><h2 id="rt-how-heading">Cara sewa</h2><ol class="rt-steps">
        <?php foreach ([['Temukan pilihanmu', 'Jelajahi koleksi dan masukkan pakaian yang kamu suka ke tas sewa.'], ['Tentukan durasi', 'Pilih jumlah hari, periksa rincian harga, lalu konfirmasi sewaanmu.'], ['Kenakan dan kembalikan', 'Nikmati acaramu dan kembalikan pakaian sesuai tanggal di invoice.']] as $index => [$title, $body]) { ?><li><span class="rt-step-index" aria-hidden="true"><?= h(sprintf('%02d', $index + 1)) ?></span><h3><?= h($title) ?></h3><p><?= h($body) ?></p></li><?php } ?>
        </ol></div></section>
    </main>
    <?php
}

function storefront_catalog(): void
{
    $filters = [];
    foreach (['type', 'q', 'size', 'category', 'available', 'sort'] as $name) { $filters[$name] = storefront_param($name); }
    $query = ['type' => $filters['type'], 'search' => $filters['q'], 'size' => $filters['size'], 'category_id' => $filters['category'], 'available' => ['yes' => 'true', 'no' => 'false'][$filters['available']] ?? $filters['available'], 'sort' => ['low' => 'price_asc', 'high' => 'price_desc'][$filters['sort']] ?? $filters['sort']];
    $products = []; $categories = []; $sizes = []; $error = null; $categoryError = null;
    try {
        $all = api_call('GET', '/clothings');
        foreach ($all as $item) { if ($item['size'] !== '') { $sizes[$item['size']] = $item['size']; } }
        $products = api_call('GET', '/clothings', [], array_filter($query, static fn($value) => $value !== ''));
    } catch (Throwable $caught) { $error = $caught; }
    try { $categories = api_call('GET', '/categories'); } catch (Throwable $caught) { $categoryError = $caught; }
    ?>
    <main class="rt-frame rt-catalog" id="konten-utama"><nav class="rt-crumbs" aria-label="Jejak halaman"><a href="/">Beranda</a><?= icon('chevron') ?><span>Koleksi</span></nav><div class="rt-page-intro"><h1>Koleksi Rentique</h1><p>Temukan pakaian untuk agenda berikutnya.</p></div>
    <form method="get" action="/catalog"><div class="rt-catalog-toolbar"><div class="rt-type-tabs" role="group" aria-label="Jenis pakaian">
    <?php foreach (['' => 'Semua', 'DRESS' => 'Gaun', 'SUIT' => 'Jas', 'COSTUME' => 'Kostum'] as $type => $label) { ?><a href="<?= h('/catalog?' . http_build_query(array_replace($filters, ['type' => $type]))) ?>" <?= $filters['type'] === $type ? 'aria-current="true"' : '' ?>><?= h($label) ?></a><?php } ?>
    </div><div class="rt-catalog-tools"><label class="rt-search"><?= icon('search', 17) ?><input aria-label="Cari pakaian" name="q" type="search" value="<?= h($filters['q']) ?>" placeholder="Cari pakaian…"></label><button class="rt-filter-button" type="submit"><?= icon('filter', 16) ?> Terapkan filter</button></div></div>
    <input type="hidden" name="type" value="<?= h($filters['type']) ?>">
    <div class="rt-filters" id="rt-filters">
    <?php
    $options = ['category' => ['Kategori acara', ['' => 'Semua kategori'] + array_column($categories, 'name', 'id')], 'size' => ['Ukuran', ['' => 'Semua ukuran'] + $sizes], 'available' => ['Ketersediaan', ['' => 'Semua stok', 'yes' => 'Tersedia', 'no' => 'Sedang disewa']]];
    foreach ($options as $name => [$label, $choices]) { ?>
        <label class="rt-filter-select"><span><?= h($label) ?></span><span class="rt-select-wrap"><select name="<?= h($name) ?>" aria-label="<?= h($label) ?>"><?php foreach ($choices as $value => $text) { ?><option value="<?= h($value) ?>" <?= (string) $value === $filters[$name] ? 'selected' : '' ?>><?= h($text) ?></option><?php } ?></select><?= icon('chevron', 14) ?></span></label>
    <?php } ?>
    </div><?php if ($categoryError) { storefront_error($categoryError, '/catalog?' . http_build_query($filters)); } ?>
    <div class="rt-results-line"><span><?= count($products) ?> pakaian <?php if (array_filter($filters)) { ?><a class="rt-reset" href="/catalog">Hapus filter <?= icon('close', 12) ?></a><?php } ?></span><label class="rt-sort"><span>Urutkan</span><span class="rt-select-wrap"><select name="sort" aria-label="Urutkan koleksi"><?php foreach (['' => 'Pilihan Rentique', 'low' => 'Harga terendah', 'high' => 'Harga tertinggi', 'name' => 'Nama', 'newest' => 'Terbaru'] as $value => $label) { ?><option value="<?= h($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php } ?></select><?= icon('chevron', 14) ?></span></label></div>
    </form>
    <?php if ($error) { storefront_error($error, '/catalog?' . http_build_query($filters)); }
    elseif (!$products) { ?><div class="rt-empty"><?= icon('search', 28) ?><h2>Pakaian belum ditemukan</h2><p>Coba kata kunci lain atau sesuaikan filter koleksi.</p><a class="rt-primary" href="/catalog">Tampilkan semua</a></div><?php }
    else { storefront_grid($products); } ?>
    </main>
    <?php
}

function storefront_details(string $id): void
{
    ?><main class="rt-frame rt-details" id="konten-utama"><nav class="rt-crumbs" aria-label="Jejak halaman"><a href="/">Beranda</a><?= icon('chevron') ?><a href="/catalog">Koleksi</a><?= icon('chevron') ?><span>Detail pakaian</span></nav><?php
    try {
        if (!ctype_digit($id) || (int) $id < 1 || (string) (int) $id !== $id) { throw new InvalidArgumentException('Pakaian tidak ditemukan.'); }
        $product = api_call('GET', '/clothings/' . $id);
    } catch (Throwable $error) { storefront_error($error, '/catalog'); echo '</main>'; return; }
    $bag = bag(); $inBag = 0;
    foreach ($bag['items'] as $item) { if ((int) $item['clothing_id'] === (int) $id) { $inBag = (int) $item['quantity']; break; } }
    $available = max(0, (int) $product['stock'] - $inBag);
    $quantity = storefront_param('quantity', '1'); $days = storefront_param('days', (string) $bag['days']);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'bag_add') {
        $quantity = post_string('quantity', $quantity); $days = post_string('rental_days', $days);
    }
    $valid = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => max(1, $available)]]) !== false && filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false && $available > 0;
    $quote = null; $quoteError = null;
    if ($valid) { try { $quote = api_call('POST', '/rentals/quote', ['rental_days' => (int) $days, 'items' => [['clothing_id' => (int) $id, 'quantity' => (int) $quantity]]]); } catch (Throwable $error) { $quoteError = $error; } }
    ?>
    <div class="rt-detail-grid"><div class="rt-detail-photo"><img src="<?= h(product_image($product)) ?>" alt="<?= h($product['name']) ?>" loading="eager"></div><div class="rt-detail-info"><a class="rt-type-link" href="/catalog?type=<?= h($product['type']) ?>"><?= h(status_label($product['type'])) ?></a><h1><?= h($product['name']) ?></h1><div class="rt-detail-price"><?= h(money($product['rental_price_per_day'])) ?> <small>/ hari</small></div><p class="rt-description"><?= h($product['description']) ?></p><div class="rt-stock"><?php storefront_availability($product); ?><span><?= h($product['stock']) ?> unit</span></div><dl class="rt-attributes">
    <?php $attributes = ['Ukuran' => $product['size'], 'Warna' => $product['color'], 'Kondisi' => $product['condition']];
    if ($product['type'] === 'DRESS') { $attributes['Model'] = $product['dress_style'] ?: '—'; }
    if ($product['type'] === 'SUIT') { $attributes['Biaya cuci per unit'] = money($product['dry_cleaning_fee'] ?? 0); }
    if ($product['type'] === 'COSTUME') { $attributes['Tema'] = $product['theme'] ?: '—'; $attributes['Aksesori per unit'] = money($product['accessory_fee'] ?? 0); }
    foreach ($attributes as $label => $value) { ?><div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div><?php } ?></dl>
    <?php if ($available > 0) { ?>
    <form class="rt-rental-options" method="get" action="/clothing/<?= h($id) ?>"><div class="rt-stepper-row"><label class="rt-stepper-field">Jumlah<span class="rt-stepper"><input aria-label="Jumlah" name="quantity" type="number" min="1" max="<?= h($available) ?>" value="<?= h($quantity) ?>" required></span></label><label class="rt-stepper-field">Durasi (hari)<span class="rt-stepper"><input aria-label="Durasi (hari)" name="days" type="number" min="1" value="<?= h($days) ?>" required></span></label></div><button class="rt-text-link rt-quote-button" type="submit">Hitung estimasi</button></form>
    <?php if ($bag['items'] && (int) $days !== (int) $bag['days'] && $valid) { ?><p class="rt-bag-hint">Durasi seluruh tas akan menjadi <?= h($days) ?> hari.</p><?php } ?>
    <div class="rt-estimate"><span>Estimasi sewa</span><strong><?= $quote !== null ? h(money($quote['total_price'])) : '—' ?></strong></div>
    <?php if (!$valid) { ?><p class="rt-invalid" role="alert">Masukkan durasi dan jumlah yang valid, maksimal <?= h($available) ?> unit.</p><?php }
    if ($quoteError) { storefront_error($quoteError, '/clothing/' . $id . '?' . http_build_query(['quantity' => $quantity, 'days' => $days])); }
    } else { ?><p class="rt-bag-hint"><?= $inBag ? 'Semua stok yang tersedia sudah ada di tas sewa.' : 'Pakaian ini sedang disewa.' ?></p><?php } ?>
    <form method="post" action="/clothing/<?= h($id) ?>"><?php form_fields('bag_add'); ?><input type="hidden" name="clothing_id" value="<?= h($id) ?>"><input type="hidden" name="quantity" value="<?= h($quantity) ?>"><input type="hidden" name="rental_days" value="<?= h($days) ?>"><button class="rt-primary rt-add" type="submit" <?= !$valid || $quote === null ? 'disabled' : '' ?>><?= icon('bag', 17) ?> Tambah ke tas sewa</button></form>
    <p class="rt-bag-hint">Stok belum dipesan sampai sewa dikonfirmasi.</p>
    <?php if ($inBag > 0) { ?><div class="rt-added" role="status"><span><?= icon('check', 15) ?> <?= h($inBag) ?> item di tas sewa.</span><a href="/cart" class="rt-text-link">Lihat tas <?= icon('arrow', 14) ?></a></div><?php } ?>
    </div></div></main>
    <?php
}

function render_storefront(string $path): bool
{
    if ($path === '/') { storefront_home(); return true; }
    if ($path === '/catalog') { storefront_catalog(); return true; }
    if ($path === '/clothing' || $path === '/clothing/') { storefront_details(''); return true; }
    if (preg_match('~^/clothing/([^/]+)$~D', $path, $match)) { storefront_details($match[1]); return true; }
    return false;
}
