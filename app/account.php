<?php
declare(strict_types=1);

function login_destination(array $user): string
{
    $next = $_GET['next'] ?? '';
    if (is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
        && !preg_match('/[\\\\\x00-\x20\x7f]/', $next) && ($user['role'] === 'ADMIN' || !str_starts_with($next, '/admin'))) return $next;
    return $user['role'] === 'ADMIN' ? '/admin' : '/rentals';
}

function payment_input(int $balance): array
{
    $mode = post_string('payment_mode', 'full');
    if (!in_array($mode, ['full', 'partial'], true)) throw new InvalidArgumentException('Pilihan nominal tidak valid.');
    $amount = $mode === 'full' ? $balance : input_int('amount');
    if ($amount < 1 || $amount > $balance) throw new InvalidArgumentException('Nominal harus positif dan tidak melebihi sisa tagihan.');
    $method = post_string('payment_method', 'TRANSFER');
    $body = ['payment_method'=>$method, 'amount'=>$amount];
    if ($method === 'CASH') {
        $body['cash_received'] = input_int('cash_received', $amount);
        if ($body['cash_received'] < $amount) throw new InvalidArgumentException('Uang tunai yang diterima kurang.');
    } elseif ($method === 'TRANSFER') {
        $body['bank_name'] = post_string('bank_name', 'BCA');
        if ($body['bank_name'] === '') throw new InvalidArgumentException('Nama bank wajib diisi.');
    } elseif ($method === 'EWALLET') {
        $body['provider'] = post_string('provider', 'DANA');
        if ($body['provider'] === '') throw new InvalidArgumentException('Penyedia dompet digital wajib diisi.');
    } else throw new InvalidArgumentException('Metode pembayaran tidak dikenal.');
    return $body;
}

function account_action(string $action): bool
{
    if ($action === 'login') {
        $savedBag = bag();
        $password = $_POST['password'] ?? '';
        if (!is_string($password)) throw new InvalidArgumentException('Kata sandi tidak valid.');
        $user = api_call('POST', '/auth/login', ['email'=>post_string('email'), 'password'=>$password]);
        unset($GLOBALS['auth_user']);
        save_bag($savedBag);
        flash('Berhasil masuk.');
        redirect(login_destination($user));
    }
    if ($action === 'logout') {
        require_user();
        $savedBag = bag();
        api_call('POST', '/auth/logout');
        unset($GLOBALS['auth_user']);
        save_bag($savedBag);
        flash('Anda sudah keluar.');
        redirect('/');
    }
    if ($action === 'profile_save') {
        require_user();
        $body = [];
        foreach (['name','email','phone','address'] as $field) $body[$field] = post_string($field);
        api_call('PATCH', '/auth/profile', $body);
        unset($GLOBALS['auth_user']);
        flash('Profil tersimpan.');
        redirect('/profile');
    }
    if ($action === 'bag_remove') {
        $bag = bag(); $id = input_int('clothing_id');
        $bag['items'] = array_values(array_filter($bag['items'], static fn($item) => $item['clothing_id'] !== $id));
        $bag['key'] = request_key(); save_bag($bag);
        redirect('/cart');
    }
    if ($action === 'bag_update') {
        $bag = bag(); $bag['days'] = input_int('rental_days');
        foreach ($bag['items'] as &$item) $item['quantity'] = input_int('quantity_'.$item['clothing_id']);
        unset($item);
        api_call('POST', '/rentals/quote', ['rental_days'=>$bag['days'], 'items'=>$bag['items']]);
        $bag['key'] = request_key(); save_bag($bag);
        flash('Tas sewa diperbarui.'); redirect('/cart');
    }
    if ($action === 'checkout') {
        $user = require_user('CUSTOMER');
        $key = post_string('request_key');
        $receipt = $_SESSION['checkout_results'][$key] ?? null;
        if ($receipt && $receipt['user_id'] === $user['id']) redirect('/rentals/'.$receipt['rental_id'].'/invoice');
        $bag = bag();
        if (!$bag['items'] || !hash_equals($bag['key'], $key)) throw new DomainException('Tas berubah. Periksa kembali rincian checkout.', 409);
        $quote = api_call('POST', '/rentals/quote', bag_input());
        if (input_int('quoted_total', -1) !== (int)$quote['total_price']) throw new DomainException('Harga berubah. Periksa total terbaru sebelum konfirmasi.', 409);
        $payment = (int)$quote['total_price'] > 0 ? payment_input((int)$quote['total_price']) : null;
        $order = api_call('POST', '/rentals', bag_input(), [], $key);
        $_SESSION['checkout_results'][$key] = ['user_id'=>$user['id'], 'rental_id'=>$order['id']];
        save_bag(['items'=>[], 'days'=>1, 'key'=>request_key()]);
        if ((int)$order['total_price'] !== (int)$quote['total_price']) {
            flash('Sewaan tersimpan. Harga berubah saat konfirmasi; periksa total sebelum membayar.');
            redirect('/rentals/'.$order['id']);
        }
        try {
            if ($payment !== null) api_call('POST', '/rentals/'.$order['id'].'/payments', $payment, [], $key.'-payment');
        } catch (InvalidArgumentException|DomainException $error) {
            flash('Sewaan sudah tersimpan, tetapi pembayaran belum berhasil: '.$error->getMessage());
            redirect('/rentals/'.$order['id']);
        }
        flash('Sewaan tersimpan.'); redirect('/rentals/'.$order['id'].'/invoice');
    }
    if ($action === 'payment') {
        require_user();
        $id = input_int('rental_id');
        $data = api_call('GET', '/rentals/'.$id.'/invoice');
        $body = payment_input((int)$data['outstanding_amount']);
        api_call('POST', '/rentals/'.$id.'/payments', $body, [], post_string('request_key'));
        flash('Pembayaran tercatat.'); redirect('/rentals/'.$id);
    }
    return false;
}

function render_login(): void
{
    if ($user = current_user()) redirect(login_destination($user));
    ?>
    <main class="ac-page ac-login" id="konten-utama"><div class="ac-login-photo"><img src="/images/hero.webp" alt="Gaun malam koleksi Rentique"></div><section class="ac-login-form"><h1>Selamat datang.</h1><p>Masuk ke akun Rentique untuk melanjutkan.</p><form method="post" data-login><?php form_fields('login'); ?><label>Email<input name="email" type="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>"></label><label>Kata sandi<input name="password" type="password" required autocomplete="current-password"></label><button class="ac-btn">Masuk <?= icon('arrow') ?></button></form><div class="ac-demo"><span>Isi akun demo</span><button type="button" data-demo="customer">Pelanggan</button><button type="button" data-demo="admin">Admin</button></div><details class="native-demo"><summary>Kredensial akun demo</summary><p>Pelanggan: customer@rentique.test<br>Customer123!</p><p>Admin: admin@rentique.test<br>Admin123!</p></details></section></main>
    <?php
}

function render_profile(): void
{
    $user = require_user();
    ?><main class="ac-page ac-narrow" id="konten-utama"><h1>Profil saya</h1><p class="ac-sub">Kelola informasi kontak untuk sewaanmu.</p><form class="ac-panel" method="post"><?php form_fields('profile_save'); ?><?php foreach (['name'=>['Nama','text'], 'email'=>['Email','email'], 'phone'=>['Nomor telepon','tel']] as $field=>[$label,$type]) { ?><label><?= h($label) ?><input name="<?= h($field) ?>" type="<?= h($type) ?>" value="<?= h($_POST[$field] ?? $user[$field]) ?>" <?= $field !== 'phone' ? 'required' : '' ?>></label><?php } ?><label>Alamat<textarea name="address" rows="3"><?= h($_POST['address'] ?? $user['address']) ?></textarea></label><button class="ac-btn">Simpan perubahan</button></form><div class="ac-actions"><a href="<?= $user['role'] === 'ADMIN' ? '/admin' : '/rentals' ?>"><?= $user['role'] === 'ADMIN' ? 'Buka dasbor' : 'Lihat sewaan saya' ?></a><form method="post"><?php form_fields('logout'); ?><button class="ac-link"><?= icon('logout',15) ?> Keluar</button></form></div></main><?php
}

function empty_bag(): void
{
    ?><div class="ac-empty"><?= icon('bag',40) ?><h2>Tas sewa masih kosong</h2><p>Pilih pakaian untuk acaramu dari koleksi Rentique.</p><a href="/catalog" class="ac-btn">Jelajahi koleksi <?= icon('arrow',16) ?></a></div><?php
}

function rental_summary(array $quote): void
{
    $extras = array_sum(array_column($quote['items'], 'additional_fee'));
    ?><div class="ac-summary"><h2>Ringkasan sewa</h2><dl><div><dt>Tanggal sewa</dt><dd><?= h(date_label($quote['rental_date'])) ?></dd></div><div><dt>Rencana kembali</dt><dd><?= h(date_label($quote['planned_return_date'])) ?></dd></div><div><dt>Harga sewa (<?= h($quote['rental_days']) ?> hari)</dt><dd><?= h(money($quote['total_price']-$extras)) ?></dd></div><div><dt>Biaya cuci &amp; aksesori</dt><dd><?= h(money($extras)) ?></dd></div><div class="ac-total"><dt>Total sewa</dt><dd><?= h(money($quote['total_price'])) ?></dd></div></dl></div><?php
}

function render_bag(): void
{
    $bag = bag();
    ?><main class="ac-page" id="konten-utama"><h1>Tas sewa</h1><?php
    if (!$bag['items']) { empty_bag(); echo '</main>'; return; }
    $quote = null;
    try { $quote = api_call('POST', '/rentals/quote', bag_input()); }
    catch (InvalidArgumentException|DomainException $error) { echo '<p class="ac-error" role="alert">'.h($error->getMessage()).' Sesuaikan tas sebelum checkout.</p>'; }
    $products = array_column(api_call('GET', '/clothings'), null, 'id');
    ?><div class="ac-split"><section><form method="post" id="bag-update"><?php form_fields('bag_update'); ?><label class="ac-duration">Durasi semua pakaian (hari)<input name="rental_days" type="number" min="1" step="1" required value="<?= h($bag['days']) ?>"></label></form><?php foreach ($bag['items'] as $item) {
        $product = $products[$item['clothing_id']] ?? null;
        $row = null; foreach ($quote['items'] ?? [] as $line) if ((int)$line['clothing_id'] === $item['clothing_id']) $row = $line;
        ?><article class="ac-bag-item"><?php if ($product) { ?><div class="ac-thumb"><img src="<?= h(product_image($product)) ?>" alt=""></div><?php } ?><div><a href="/clothing/<?= h($item['clothing_id']) ?>"><h3><?= h($product['name'] ?? 'Pakaian #'.$item['clothing_id']) ?></h3></a><p><?= $product ? 'Ukuran '.h($product['size']) : 'Tidak tersedia' ?></p><label class="ac-qty">Jumlah<input form="bag-update" aria-label="<?= h('Jumlah '.($product['name'] ?? 'pakaian')) ?>" name="quantity_<?= h($item['clothing_id']) ?>" type="number" min="1" step="1" required value="<?= h($item['quantity']) ?>"></label></div><div class="ac-item-actions"><form method="post"><?php form_fields('bag_remove'); ?><input type="hidden" name="clothing_id" value="<?= h($item['clothing_id']) ?>"><button class="native-link" aria-label="<?= h('Hapus '.($product['name'] ?? 'pakaian')) ?>">Hapus</button></form><?php if ($row) { ?><b><?= h(money($row['subtotal'])) ?></b><?php } ?></div></article><?php
    } ?><button form="bag-update" class="ac-btn">Perbarui tas</button></section><aside class="ac-panel"><?php if ($quote) { rental_summary($quote); ?><a href="/checkout" class="ac-btn">Lanjut checkout <?= icon('arrow',16) ?></a><?php } ?><p class="ac-note">Stok diperiksa kembali saat sewaan dibuat.</p></aside></div></main><?php
}

function payment_form(int $balance, string $action, ?array $quote = null, ?int $rentalId = null): void
{
    $key = $action === 'checkout' ? bag()['key'] : post_string('request_key', request_key());
    ?><form method="post" class="ac-payment" data-payment data-balance="<?= h($balance) ?>"><?php form_fields($action, $key); ?><?php if ($quote) { ?><input type="hidden" name="quoted_total" value="<?= h($quote['total_price']) ?>"><?php } ?><?php if ($rentalId !== null) { ?><input type="hidden" name="rental_id" value="<?= h($rentalId) ?>"><?php } ?><h2>Pembayaran</h2><p class="ac-note">Simulasi untuk demonstrasi. Tidak ada uang yang ditransfer.</p><?php if ($balance > 0) { ?><fieldset><legend>Jumlah pembayaran</legend><div class="ac-radios"><?php foreach (['full'=>'Bayar penuh','partial'=>'Bayar sebagian'] as $value=>$label) { ?><label><input type="radio" name="payment_mode" value="<?= h($value) ?>" <?= ($_POST['payment_mode'] ?? 'full') === $value ? 'checked' : '' ?>><?= h($label) ?></label><?php } ?></div></fieldset><label data-payment-partial>Nominal pembayaran sebagian (Rp)<input type="number" name="amount" min="1" max="<?= h($balance) ?>" step="1" value="<?= h($_POST['amount'] ?? '') ?>"></label><label>Metode pembayaran<select name="payment_method"><?php foreach (['TRANSFER'=>'Transfer bank','CASH'=>'Tunai','EWALLET'=>'Dompet digital'] as $value=>$label) { ?><option value="<?= h($value) ?>" <?= ($_POST['payment_method'] ?? 'TRANSFER') === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php } ?></select></label><label data-method="CASH">Uang diterima (Rp)<input type="number" name="cash_received" min="1" step="1" placeholder="Kosong berarti uang pas" value="<?= h($_POST['cash_received'] ?? '') ?>"></label><p class="ac-line" data-method="CASH"><span>Kembalian</span><b data-change>—</b></p><label data-method="TRANSFER">Bank<select name="bank_name"><?php foreach (['BCA','BNI','BRI','Mandiri'] as $bank) { ?><option <?= ($_POST['bank_name'] ?? 'BCA') === $bank ? 'selected' : '' ?>><?= h($bank) ?></option><?php } ?></select></label><label data-method="EWALLET">Penyedia<select name="provider"><?php foreach (['DANA','GoPay','OVO','ShopeePay'] as $provider) { ?><option <?= ($_POST['provider'] ?? 'DANA') === $provider ? 'selected' : '' ?>><?= h($provider) ?></option><?php } ?></select></label><p class="ac-line ac-total"><span>Dibayar sekarang</span><b data-payment-total><?= h(money($balance)) ?></b></p><?php } ?><button class="ac-btn"><?= $action === 'checkout' ? 'Konfirmasi sewaan' : 'Konfirmasi pembayaran' ?></button></form><?php
}

function render_checkout(): void
{
    $user = require_user('CUSTOMER');
    ?><main class="ac-page" id="konten-utama"><a class="ac-back" href="/cart">← Kembali ke tas</a><h1>Checkout</h1><?php
    if (!bag()['items']) { empty_bag(); echo '</main>'; return; }
    $quote = api_call('POST', '/rentals/quote', bag_input());
    ?><div class="ac-split"><section class="ac-panel"><h2>Informasi penyewa</h2><p><?= h($user['name']) ?><br><?= h($user['email']) ?><br><?= h($user['phone']) ?><br><?= h($user['address']) ?></p><a class="ac-link" href="/profile">Edit profil</a><hr><?php payment_form((int)$quote['total_price'], 'checkout', $quote); ?></section><aside class="ac-panel"><?php rental_summary($quote); ?><hr><?php foreach ($quote['items'] as $item) { ?><p class="ac-line"><span><?= h($item['name']) ?> × <?= h($item['quantity']) ?></span><b><?= h(money($item['subtotal'])) ?></b></p><?php } ?></aside></div></main><?php
}

function rental_badge(string $status): void { echo '<span class="ac-badge ac-'.h($status).'">'.h(status_label($status)).'</span>'; }

function receipt_lines(array $data): void
{
    ?><div class="ac-table-wrap"><table class="ac-table"><thead><tr><th>Pakaian</th><th>Jumlah</th><th>Hari</th><th>Tarif/hari</th><th>Biaya tambahan</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($data['items'] as $item) { ?><tr><td><?= h($item['name']) ?></td><td><?= h($item['quantity']) ?></td><td><?= h($item['rental_days']) ?></td><td><?= h(money($item['price_per_day'])) ?></td><td><?= h(money($item['additional_fee'])) ?></td><td><?= h(money($item['subtotal'])) ?></td></tr><?php } ?></tbody></table></div><?php
}

function receipt_totals(array $data): void
{
    ?><dl class="ac-totals"><?php foreach (['total_price'=>'Total sewa', 'return_fee'=>'Denda pengembalian', 'paid_amount'=>'Sudah dibayar', 'outstanding_amount'=>'Sisa tagihan'] as $field=>$label) { ?><div <?= $field === 'outstanding_amount' ? 'class="ac-total"' : '' ?>><dt><?= h($label) ?></dt><dd><?= h(money($data[$field])) ?></dd></div><?php } ?></dl><?php
}

function receipt_payments(array $payments): void
{
    ?><div><h2>Riwayat pembayaran</h2><?php if (!$payments) { ?><p>Belum ada pembayaran.</p><?php } else { ?><ul class="ac-payments"><?php foreach ($payments as $payment) { ?><li><div><b><?= h(status_label($payment['payment_method'])) ?> <?= h($payment['bank_name'] ?? $payment['provider'] ?? '') ?></b><small><?= h(date_label($payment['payment_date'])) ?></small><?php if ($payment['payment_method'] === 'CASH') { ?><small>Diterima <?= h(money($payment['cash_received'])) ?> · Kembalian <?= h(money($payment['change_amount'])) ?></small><?php } ?></div><strong><?= h(money($payment['amount'])) ?></strong></li><?php } ?></ul><?php } ?></div><?php
}

function render_my_rentals(): void
{
    require_user('CUSTOMER');
    $filter = $_GET['status'] ?? '';
    if (!is_string($filter) || !in_array($filter, ['', 'RENTED', 'RETURNED'], true)) throw new InvalidArgumentException('Filter status tidak valid.');
    $rows = array_filter(api_call('GET', '/rentals'), static fn($row) => !$filter || $row['status'] === $filter);
    ?><main class="ac-page" id="konten-utama"><h1>Sewaan saya</h1><p class="ac-sub">Pantau tanggal kembali, pembayaran, dan invoice sewaanmu.</p><div class="ac-tabs"><?php foreach ([''=>'Semua', 'RENTED'=>'Aktif', 'RETURNED'=>'Dikembalikan'] as $value=>$label) { ?><a href="/rentals<?= $value ? '?status='.h($value) : '' ?>" <?= $filter === $value ? 'aria-current="page"' : '' ?>><?= h($label) ?></a><?php } ?></div><?php if (!$rows) { ?><div class="ac-empty"><h2>Belum ada sewaan dalam daftar ini</h2><a class="ac-btn" href="/catalog">Jelajahi koleksi</a></div><?php } foreach ($rows as $row) { ?><article class="ac-rental"><div><h2>Sewa #<?= h($row['id']) ?></h2><p><?= h(date_label($row['rental_date'])) ?> — <?= h(date_label($row['planned_return_date'])) ?></p><div><?php rental_badge($row['status']); rental_badge($row['payment_status']); ?></div></div><div><p>Total <?= h(money($row['total_price'])) ?></p><b>Sisa <?= h(money($row['outstanding_amount'])) ?></b></div><div class="ac-row-links"><a href="/rentals/<?= h($row['id']) ?>">Lihat detail</a><?php if ($row['outstanding_amount'] > 0) { ?><a href="/rentals/<?= h($row['id']) ?>#bayar">Bayar sisa tagihan</a><?php } ?><a href="/rentals/<?= h($row['id']) ?>/invoice">Lihat invoice</a></div></article><?php } ?></main><?php
}

function render_rental(int $id, bool $invoice): void
{
    $user = require_user();
    $data = api_call('GET', '/rentals/'.$id.'/invoice');
    if ($invoice) { ?>
        <main class="ac-page ac-invoice-page" id="konten-utama"><div class="ac-invoice-actions"><a href="/rentals/<?= h($id) ?>">← Detail sewa</a><button class="ac-btn" type="button" data-print>Cetak invoice</button></div><article class="ac-invoice"><header><span class="ac-wordmark">rentique.</span><div><h1>Invoice</h1><span><?= h($data['invoice_number']) ?></span></div></header><div class="ac-invoice-meta"><div><small>Penyewa</small><b><?= h($data['customer']['name'] ?? $data['customer_name']) ?></b><p><?= h($data['customer']['email'] ?? '') ?><br><?= h($data['customer']['phone'] ?? '') ?><br><?= h($data['customer']['address'] ?? '') ?></p></div><div><small>Tanggal terbit</small><b><?= h(date_label($data['issue_date'])) ?></b><small>Periode sewa</small><p><?= h(date_label($data['rental_date'])) ?> — <?= h(date_label($data['planned_return_date'])) ?></p></div></div><div class="ac-invoice-status"><?php rental_badge($data['status']); rental_badge($data['payment_status']); ?></div><?php receipt_lines($data); receipt_totals($data); ?><hr><?php receipt_payments($data['payments']); ?><footer>Rentique · Bukti transaksi simulasi #<?= h($id) ?></footer></article></main>
    <?php return; }
    ?><main class="ac-page" id="konten-utama"><a class="ac-back" href="<?= $user['role'] === 'ADMIN' ? '/admin/rentals' : '/rentals' ?>">← Daftar sewa</a><h1>Detail sewa #<?= h($id) ?></h1><div class="ac-split"><section class="ac-panel"><div><?php rental_badge($data['status']); rental_badge($data['payment_status']); ?></div><p>Disewa <?= h(date_label($data['rental_date'])) ?> · Kembali paling lambat <?= h(date_label($data['planned_return_date'])) ?></p><?php if ($data['actual_return_date']) { ?><p>Dikembalikan <?= h(date_label($data['actual_return_date'])) ?></p><?php } receipt_lines($data); receipt_totals($data); ?><a class="ac-link" href="/rentals/<?= h($id) ?>/invoice">Lihat invoice</a><hr><?php receipt_payments($data['payments']); ?></section><?php if ((int)$data['outstanding_amount'] > 0) { ?><aside id="bayar" class="ac-panel"><?php payment_form((int)$data['outstanding_amount'], 'payment', null, $id); ?></aside><?php } ?></div></main><?php
}

function render_account(string $path): bool
{
    if ($path === '/login') { render_login(); return true; }
    if ($path === '/profile') { render_profile(); return true; }
    if ($path === '/cart') { render_bag(); return true; }
    if ($path === '/checkout') { render_checkout(); return true; }
    if ($path === '/rentals') { render_my_rentals(); return true; }
    if (preg_match('~^/rentals/([1-9][0-9]*)(/invoice)?$~D', $path, $match)) { render_rental((int)$match[1], isset($match[2])); return true; }
    return false;
}
