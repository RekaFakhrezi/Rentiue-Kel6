<?php
declare(strict_types=1);

function admin_action(string $action): bool
{
    if (!in_array($action, ['admin_add', 'admin_edit', 'admin_deactivate', 'admin_stock', 'admin_return'], true)) return false;
    require_user('ADMIN');
    $id = input_int('id');
    if ($action === 'admin_return') {
        api_call('POST', '/rentals/' . $id . '/return', [
            'return_date' => post_string('return_date'),
            'condition_on_return' => post_string('condition_on_return'),
            'notes' => post_string('notes'),
        ]);
        flash('Pengembalian dicatat dan stok dipulihkan.');
        redirect('/admin/returns/' . $id);
    }
    if ($action === 'admin_deactivate') {
        api_call('DELETE', '/clothings/' . $id);
        flash('Pakaian dinonaktifkan.');
    } elseif ($action === 'admin_stock') {
        api_call('POST', '/clothings/' . $id . '/adjust-stock', [
            'quantity_changed' => input_int('quantity_changed'), 'notes' => post_string('notes'),
        ]);
        flash('Stok diperbarui.');
    } else {
        $body = [];
        foreach (['name','type','size','color','condition','description','image_url'] as $field) $body[$field] = post_string($field);
        foreach (['category_id','rental_price_per_day','stock'] as $field) $body[$field] = input_int($field);
        if ($body['type'] === 'DRESS') $body['dress_style'] = post_string('dress_style');
        if ($body['type'] === 'SUIT') $body['dry_cleaning_fee'] = input_int('dry_cleaning_fee');
        if ($body['type'] === 'COSTUME') {
            $body['theme'] = post_string('theme');
            $body['accessory_fee'] = input_int('accessory_fee');
        }
        if ($action === 'admin_edit') $body['expected_stock'] = input_int('expected_stock');
        api_call($action === 'admin_add' ? 'POST' : 'PATCH', '/clothings' . ($action === 'admin_edit' ? '/' . $id : ''), $body);
        flash($action === 'admin_add' ? 'Pakaian ditambahkan.' : 'Perubahan disimpan.');
    }
    redirect('/admin/inventory');
}

function adm_query(string $name, string $default = ''): string
{
    return isset($_GET[$name]) && is_string($_GET[$name]) ? trim($_GET[$name]) : $default;
}
function adm_value(string $name, mixed $default = ''): string
{
    return isset($_POST[$name]) && is_scalar($_POST[$name]) ? (string) $_POST[$name] : (string) ($default ?? '');
}
function adm_matches(string $query, mixed ...$values): bool
{
    return $query === '' || stripos(implode(' ', array_map(static fn($v) => (string) ($v ?? ''), $values)), $query) !== false;
}
function adm_code(int|string $id): string { return '#' . str_pad((string) $id, 5, '0', STR_PAD_LEFT); }
function adm_badge(string $value, ?string $label = null): void
{
    echo '<span class="adm-badge adm-badge-' . h(strtolower($value)) . '">' . h($label ?? status_label($value)) . '</span>';
}
function adm_stock(array $c): void
{
    $state = !$c['is_active'] ? 'inactive' : ((int) $c['stock'] === 0 ? 'empty' : 'available');
    adm_badge($state, ['inactive'=>'Nonaktif','empty'=>'Habis','available'=>'Tersedia'][$state]);
}
function adm_photo(array $c): void { echo '<span class="adm-photo"><img src="' . h(product_image($c)) . '" alt="" loading="lazy"></span>'; }
function adm_heading(string $title, string $description, string $action = ''): void
{
    echo '<div class="adm-heading"><div><h1>' . h($title) . '</h1><p>' . h($description) . '</p></div>' . $action . '</div>';
}
function adm_empty(string $title, string $text): void { echo '<div class="adm-empty">' . icon('shirt',25) . '<strong>' . h($title) . '</strong><p>' . h($text) . '</p></div>'; }
function adm_table(array $headers, string $caption): void
{
    echo '<div class="adm-scroll" role="region" tabindex="0" aria-label="' . h($caption) . '"><table aria-label="' . h($caption) . '"><thead><tr>';
    foreach ($headers as $header) echo '<th scope="col">' . h($header) . '</th>';
    echo '</tr></thead><tbody>';
}
function adm_table_end(): void { echo '</tbody></table></div>'; }
function adm_search(string $placeholder): void
{
    echo '<label class="adm-search">' . icon('search',17) . '<input type="search" name="q" value="' . h(adm_query('q')) . '" aria-label="' . h($placeholder) . '" placeholder="' . h($placeholder) . '"></label>';
}
function adm_select(string $name, array $options, string $value, string $label): void
{
    echo '<select name="' . h($name) . '" aria-label="' . h($label) . '">';
    foreach ($options as $key=>$text) echo '<option value="' . h($key) . '"' . ((string)$key === $value ? ' selected' : '') . '>' . h($text) . '</option>';
    echo '</select>';
}
function adm_field(string $name, string $label, mixed $value = '', string $type = 'text', bool $required = false, bool $wide = false, string $extra = ''): void
{
    echo '<label class="adm-field' . ($wide ? ' adm-wide' : '') . '"><span>' . h($label) . '</span><input name="' . h($name) . '" type="' . h($type) . '" value="' . h(adm_value($name,$value)) . '"' . ($required ? ' required' : '') . ' ' . $extra . '></label>';
}
function adm_textarea(string $name, string $label, mixed $value = '', bool $required = false): void
{
    echo '<label class="adm-field adm-wide"><span>' . h($label) . '</span><textarea name="' . h($name) . '" rows="3"' . ($required ? ' required' : '') . '>' . h(adm_value($name,$value)) . '</textarea></label>';
}
function adm_dialog_start(string $title, string $close): void
{
    echo '<div class="adm-native-overlay"><section role="dialog" aria-modal="true" aria-labelledby="adm-dialog-title" class="adm-dialog"><div class="adm-dialog-head"><h2 id="adm-dialog-title">' . h($title) . '</h2><a class="adm-icon" href="' . h($close) . '" aria-label="Tutup dialog">' . icon('close') . '</a></div><div class="adm-dialog-body">';
}
function adm_dialog_end(): void { echo '</div></section></div>'; }
function adm_form_actions(string $close, string $text = 'Simpan', bool $danger = false): void
{
    echo '<div class="adm-form-actions"><a class="adm-btn" href="' . h($close) . '">Batal</a><button class="adm-btn ' . ($danger ? 'adm-danger' : 'adm-primary') . '" type="submit">' . h($text) . '</button></div>';
}

function adm_dashboard(): void
{
    $d = api_call('GET','/admin/dashboard');
    adm_heading('Ringkasan','Pantau stok, transaksi, dan pendapatan rental.', '<span class="adm-date">' . icon('calendar',16) . h(date_label(date('Y-m-d'))) . '</span>');
    echo '<div class="adm-kpis">';
    foreach ([['Produk aktif',$d['total_products'],'shirt','Inventaris'],['Penyewaan aktif',$d['active_rentals'],'bag','Sedang dipinjam'],['Pendapatan',money($d['revenue']),'book','Pembayaran berhasil'],['Lewat jatuh tempo',$d['overdue_rentals'],'calendar','Belum dikembalikan']] as [$label,$value,$ico,$hint]) {
        echo '<div class="adm-kpi"><div><span>' . h($label) . '</span>' . icon($ico,17) . '</div><strong>' . h($value) . '</strong><small>' . h($hint) . '</small></div>';
    }
    echo '</div><section class="adm-panel"><div class="adm-panel-title"><h2>Penyewaan terbaru</h2><a class="adm-text-link" href="/admin/rentals">Lihat semua ' . icon('arrow-up',15) . '</a></div>';
    if (!$d['recent_rentals']) adm_empty('Belum ada transaksi','Transaksi akan muncul saat pelanggan membuat penyewaan.');
    else {
        adm_table(['ID sewa','Pelanggan','Total sewa','Pembayaran','Status'],'Penyewaan terbaru');
        foreach ($d['recent_rentals'] as $r) {
            echo '<tr><td><a class="adm-id" href="/admin/rentals/' . (int)$r['id'] . '">' . h(adm_code($r['id'])) . '</a></td><td>' . h($r['customer_name']) . '</td><td>' . h(money($r['total_price'])) . '</td><td>'; adm_badge($r['payment_status']); echo '</td><td>'; adm_badge($r['status']); echo '</td></tr>';
        }
        adm_table_end();
    }
    echo '</section><div class="adm-dashboard-bottom"><section class="adm-panel"><div class="adm-panel-title"><h2>Stok menipis</h2><a href="/admin/inventory" class="adm-text-link">Inventaris ' . icon('arrow-up',15) . '</a></div>';
    if (!$d['low_stock_items']) adm_empty('Stok mencukupi','Belum ada produk dengan stok menipis.');
    else {
        echo '<ul class="adm-low-stock">';
        foreach ($d['low_stock_items'] as $c) { echo '<li>'; adm_photo($c); echo '<a href="/admin/inventory?product=' . (int)$c['id'] . '">' . h($c['name']) . '<small>Ukuran ' . h($c['size']) . '</small></a><span class="adm-units">' . h($c['stock']) . ' unit</span></li>'; }
        echo '</ul>';
    }
    echo '</section><section class="adm-panel"><div class="adm-panel-title"><h2>Operasional rental</h2></div><dl class="adm-operations">';
    foreach (['Unit tersedia'=>$d['available_units'],'Penyewaan selesai'=>$d['returned_rentals'],'Sisa tagihan pelanggan'=>money($d['outstanding_payments'])] as $label=>$value) echo '<div><dt>' . h($label) . '</dt><dd>' . h($value) . '</dd></div>';
    echo '</dl></section></div>';
}

function adm_inventory(): void
{
    $products = api_call('GET','/clothings',[],['include_inactive'=>'1']);
    $type = adm_query('type'); $availability = adm_query('availability');
    $rows = array_filter($products, static function($c) use ($type,$availability) {
        return (!$type || $c['type'] === $type) && adm_matches(adm_query('q'),$c['name'],$c['color'],$c['category_name'],$c['size']) && (!$availability || ($availability === 'available' && $c['is_active'] && $c['stock'] > 0) || ($availability === 'empty' && $c['is_active'] && $c['stock'] == 0) || ($availability === 'inactive' && !$c['is_active']));
    });
    adm_heading('Inventaris','Kelola koleksi, tarif sewa, dan ketersediaan pakaian.','<a class="adm-btn adm-primary" href="/admin/inventory?new=1">' . icon('plus',17) . 'Tambah pakaian</a>');
    echo '<section class="adm-panel"><form class="adm-tools" method="get" action="/admin/inventory">';
    adm_search('Cari nama, warna, kategori…');
    adm_select('type',[''=>'Semua jenis','DRESS'=>'Gaun','SUIT'=>'Jas','COSTUME'=>'Kostum'],$type,'Filter jenis');
    adm_select('availability',[''=>'Semua status','available'=>'Tersedia','empty'=>'Habis','inactive'=>'Nonaktif'],$availability,'Filter ketersediaan');
    echo '<button class="adm-btn" type="submit">Terapkan</button></form>';
    if (!$rows) adm_empty('Pakaian tidak ditemukan','Ubah filter pencarian atau tambahkan pakaian baru.');
    else {
        adm_table(['Pakaian','Jenis / kategori','Tarif per hari','Stok','Status','Aksi'],'Daftar pakaian');
        foreach ($rows as $c) {
            $id = (int)$c['id'];
            echo '<tr><td><a class="adm-product" href="/admin/inventory?product=' . $id . '">'; adm_photo($c); echo '<span><strong>' . h($c['name']) . '</strong><small>' . h($c['size'] . ' · ' . $c['color']) . '</small></span></a></td><td>' . h(status_label($c['type'])) . '<small>' . h($c['category_name']) . '</small></td><td>' . h(money($c['rental_price_per_day'])) . '</td><td>' . h($c['stock']) . ' unit</td><td>'; adm_stock($c);
            echo '</td><td><details class="adm-native-actions"><summary aria-label="Aksi untuk ' . h($c['name']) . '">•••</summary><div><a href="/admin/inventory?product=' . $id . '">Lihat detail</a>';
            if ($c['is_active']) foreach (['edit'=>'Edit pakaian','stock'=>'Sesuaikan stok','deactivate'=>'Nonaktifkan'] as $mode=>$label) echo '<a href="/admin/inventory?' . $mode . '=' . $id . '">' . h($label) . '</a>';
            echo '</div></details></td></tr>';
        }
        adm_table_end();
    }
    echo '</section>';
    $mode = ''; $selected = null;
    if (adm_query('new') === '1') $mode = 'new';
    foreach (['product','edit','stock','deactivate'] as $candidate) {
        if (preg_match('/^[1-9][0-9]*$/D',adm_query($candidate))) {
            $mode = $candidate;
            foreach ($products as $c) if ((string)$c['id'] === adm_query($candidate)) { $selected = $c; break; }
            if (!$selected) throw new DomainException('Pakaian tidak ditemukan.',404);
            break;
        }
    }
    if ($mode !== '') adm_inventory_dialog($mode,$selected);
}

function adm_inventory_dialog(string $mode, ?array $c): void
{
    $close = '/admin/inventory';
    adm_dialog_start(['new'=>'Tambah pakaian','edit'=>'Edit pakaian','stock'=>'Sesuaikan stok','deactivate'=>'Nonaktifkan pakaian','product'=>'Detail pakaian'][$mode],$close);
    if ($c) { echo '<div class="adm-product-summary">'; adm_photo($c); echo '<div><h3>' . h($c['name']) . '</h3><p>' . h(status_label($c['type']) . ' · Ukuran ' . $c['size'] . ' · ' . $c['color']) . '</p></div></div>'; }
    if ($mode === 'product') {
        echo '<dl class="adm-dl">';
        $fields = ['Kategori'=>$c['category_name'],'Stok'=>$c['stock'] . ' unit','Tarif per hari'=>money($c['rental_price_per_day']),'Kondisi'=>$c['condition'],'Status'=>$c['is_active'] ? 'Aktif' : 'Nonaktif'];
        if ($c['type'] === 'DRESS') $fields['Gaya gaun'] = $c['dress_style'];
        if ($c['type'] === 'SUIT') $fields['Biaya cuci per unit'] = money($c['dry_cleaning_fee']);
        if ($c['type'] === 'COSTUME') { $fields['Tema'] = $c['theme']; $fields['Aksesori per unit'] = money($c['accessory_fee']); }
        foreach ($fields as $label=>$value) echo '<div><dt>' . h($label) . '</dt><dd>' . h($value) . '</dd></div>';
        echo '</dl><p>' . nl2br(h($c['description'])) . '</p><div class="adm-form-actions"><a class="adm-btn" href="' . $close . '">Tutup</a></div>';
    } else {
        echo '<form method="post">';
        form_fields(['new'=>'admin_add','edit'=>'admin_edit','stock'=>'admin_stock','deactivate'=>'admin_deactivate'][$mode]);
        echo '<input type="hidden" name="id" value="' . (int)($c['id'] ?? 0) . '">';
        if ($mode === 'new' || $mode === 'edit') {
            $categories = api_call('GET','/categories');
            echo '<div class="adm-fields">';
            adm_field('name','Nama pakaian',$c['name'] ?? '', 'text',true,true,'maxlength="180"');
            echo '<label class="adm-field"><span>Jenis</span>'; adm_select('type',['DRESS'=>'Gaun','SUIT'=>'Jas','COSTUME'=>'Kostum'],adm_value('type',$c['type'] ?? 'DRESS'),'Jenis pakaian'); echo '</label>';
            $options = [''=>'Pilih kategori']; foreach ($categories as $category) $options[$category['id']] = $category['name'];
            echo '<label class="adm-field"><span>Kategori</span>'; adm_select('category_id',$options,adm_value('category_id',$c['category_id'] ?? ''),'Kategori'); echo '</label>';
            adm_field('size','Ukuran',$c['size'] ?? '', 'text',true,false,'maxlength="30"');
            adm_field('color','Warna',$c['color'] ?? '', 'text',true,false,'maxlength="80"');
            adm_field('rental_price_per_day','Tarif per hari (Rp)',$c['rental_price_per_day'] ?? 0,'number',true,false,'min="0" step="1"');
            adm_field('stock',$c ? 'Stok tersedia' : 'Stok awal',$c['stock'] ?? 0,'number',true,false,'min="0" step="1"');
            if ($c) echo '<input type="hidden" name="expected_stock" value="' . h(adm_value('expected_stock',$c['stock'])) . '">';
            echo '<p class="adm-wide adm-field-hint">Isi atribut sesuai jenis pakaian. Gaun memerlukan gaya; jas memerlukan biaya cuci; kostum memerlukan tema dan biaya aksesori.</p>';
            adm_field('dress_style','Gaya gaun',$c['dress_style'] ?? '');
            adm_field('dry_cleaning_fee','Biaya cuci jas per unit (Rp)',$c['dry_cleaning_fee'] ?? 0,'number',false,false,'min="0" step="1"');
            adm_field('theme','Tema kostum',$c['theme'] ?? '');
            adm_field('accessory_fee','Biaya aksesori kostum per unit (Rp)',$c['accessory_fee'] ?? 0,'number',false,false,'min="0" step="1"');
            adm_field('condition','Kondisi',$c['condition'] ?? 'Baik','text',true,true,'maxlength="180"');
            adm_textarea('description','Deskripsi',$c['description'] ?? '');
            adm_field('image_url','Alamat gambar',$c['image_url'] ?? '','text',false,true,'maxlength="2048" placeholder="https://… atau /images/…"');
            echo '</div>';
        } elseif ($mode === 'stock') {
            echo '<div class="adm-fields">';
            adm_field('quantity_changed','Perubahan unit (+ tambah / − kurangi)',1,'number',true,true,'step="1"');
            adm_textarea('notes','Alasan penyesuaian','',true);
            echo '</div><div class="adm-summaryline"><span>Stok saat ini</span><strong>' . h($c['stock']) . ' unit</strong></div>';
        } else echo '<p>Pakaian ini akan disembunyikan dari katalog. Riwayat sewa dan pembayaran tetap tersimpan.</p>';
        adm_form_actions($close,$mode === 'deactivate' ? 'Nonaktifkan' : 'Simpan',$mode === 'deactivate');
        echo '</form>';
    }
    adm_dialog_end();
}

function adm_rentals(bool $returns, ?int $id): void
{
    $base = $returns ? '/admin/returns' : '/admin/rentals';
    $status = adm_query('status',$returns ? 'RENTED' : '');
    $rentals = api_call('GET','/rentals');
    $rows = array_filter($rentals,static fn($r) => (!$status || $r['status'] === $status) && adm_matches(adm_query('q'),$r['customer_name'],adm_code($r['id'])));
    adm_heading($returns ? 'Pengembalian' : 'Penyewaan',$returns ? 'Periksa pakaian yang kembali dan catat denda keterlambatan.' : 'Telusuri transaksi, pembayaran, dan jadwal pengembalian pelanggan.');
    echo '<section class="adm-panel"><form class="adm-tools" method="get" action="' . $base . '">'; adm_search('Cari pelanggan atau ID sewa…');
    adm_select('status',[''=>'Semua status','RENTED'=>$returns ? 'Perlu dikembalikan' : 'Disewa','RETURNED'=>'Dikembalikan','DRAFT'=>'Draf'],$status,'Filter status sewa');
    echo '<button class="adm-btn" type="submit">Terapkan</button></form>';
    if (!$rows) adm_empty('Tidak ada transaksi yang cocok','Coba ubah pencarian atau status. Transaksi muncul setelah pelanggan menyewa pakaian.');
    else {
        adm_table(['ID / pelanggan','Tanggal sewa','Rencana kembali','Total / sisa','Pembayaran','Status','Aksi'],$returns ? 'Daftar pengembalian' : 'Daftar penyewaan');
        foreach ($rows as $r) {
            echo '<tr><td><a class="adm-id" href="/admin/rentals/' . (int)$r['id'] . '">' . h(adm_code($r['id'])) . '</a><small>' . h($r['customer_name']) . '</small></td><td>' . h(date_label($r['rental_date'])) . '</td><td>' . h(date_label($r['planned_return_date']));
            if ($r['status'] === 'RENTED' && $r['planned_return_date'] < date('Y-m-d')) echo '<small class="adm-overdue">Terlambat</small>';
            echo '</td><td>' . h(money($r['total_price'])) . '<small>Sisa ' . h(money($r['outstanding_amount'])) . '</small></td><td>'; adm_badge($r['payment_status']); echo '</td><td>'; adm_badge($r['status']);
            echo '</td><td><a class="adm-row-link" href="/admin/' . ($r['status'] === 'RENTED' ? 'returns/' : 'rentals/') . (int)$r['id'] . '">' . ($r['status'] === 'RENTED' ? 'Proses kembali' : 'Detail') . '</a></td></tr>';
        }
        adm_table_end();
    }
    echo '</section>';
    if ($id !== null) adm_rental_dialog($id,$returns,$base);
}

function adm_rental_dialog(int $id, bool $processing, string $close): void
{
    $r = api_call('GET','/rentals/' . $id);
    adm_dialog_start(($processing ? 'Pengembalian ' : 'Detail penyewaan ') . adm_code($id),$close);
    echo '<div class="adm-rental-customer"><div><strong>' . h($r['customer_name']) . '</strong><small>' . h($r['customer']['email'] ?? '') . '</small></div>'; adm_badge($r['status']);
    echo '</div><dl class="adm-dl"><div><dt>Tanggal sewa</dt><dd>' . h(date_label($r['rental_date'])) . '</dd></div><div><dt>Rencana kembali</dt><dd>' . h(date_label($r['planned_return_date'])) . '</dd></div></dl><ul class="adm-items">';
    foreach ($r['items'] as $item) {
        echo '<li>'; adm_photo($item); echo '<div><strong>' . h($item['name']) . '</strong><small>' . h($item['quantity'] . ' unit · ' . $item['rental_days'] . ' hari · Ukuran ' . $item['size']) . '</small></div><b>' . h(money($item['subtotal'])) . '</b></li>';
    }
    echo '</ul><dl class="adm-totals">';
    foreach (['Total sewa'=>$r['total_price'],'Denda pengembalian'=>$r['return_fee'],'Dibayar'=>$r['paid_amount'],'Sisa tagihan'=>$r['outstanding_amount']] as $label=>$amount) echo '<div><dt>' . h($label) . '</dt><dd>' . h(money($amount)) . '</dd></div>';
    echo '</dl>';
    if ($processing && $r['status'] === 'RENTED') {
        $date = adm_value('return_date',adm_query('return_date',date('Y-m-d')));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Jakarta'));
        $valid = $parsed && $parsed->format('Y-m-d') === $date && $date >= $r['rental_date'];
        echo '<form method="get" class="adm-return-preview"><div class="adm-fields">';
        echo '<label class="adm-field"><span>Tanggal untuk pratinjau denda</span><input type="date" name="return_date" value="' . h($date) . '" min="' . h($r['rental_date']) . '" required></label><button type="submit" class="adm-btn">Hitung denda</button></div></form>';
        if ($valid) {
            $planned = new DateTimeImmutable($r['planned_return_date'],new DateTimeZone('Asia/Jakarta'));
            $days = max(0,(int)$planned->diff($parsed)->format('%r%a'));
            echo '<div class="adm-summaryline"><span>Keterlambatan <small>' . $days . ' hari × ' . h(money(10000)) . '</small></span><strong>' . h(money($days * 10000)) . '</strong></div>';
        } else echo '<p class="adm-error">Pilih tanggal kembali yang valid, tidak sebelum tanggal sewa.</p>';
        echo '<form method="post">'; form_fields('admin_return'); echo '<input type="hidden" name="id" value="' . $id . '"><div class="adm-fields">';
        adm_field('return_date','Tanggal kembali aktual',$date,'date',true,false,'min="' . h($r['rental_date']) . '"');
        adm_field('condition_on_return','Kondisi saat kembali','','text',true,false,'maxlength="180" placeholder="Contoh: Baik"');
        adm_textarea('notes','Catatan pengembalian','',true);
        echo '</div><p class="adm-field-hint">Denda final dihitung dari tanggal kembali aktual. Konfirmasi hanya sekali; stok dipulihkan oleh transaksi pengembalian.</p>';
        adm_form_actions($close,'Konfirmasi pengembalian'); echo '</form>';
    }
    if (!empty($r['payments'])) {
        echo '<div class="adm-return-record"><h3>Pembayaran</h3><ul class="adm-payments">';
        foreach ($r['payments'] as $p) echo '<li><span>' . h(status_label($p['payment_method'])) . '<small>' . h(date_label($p['payment_date'])) . '</small></span><strong>' . h(money($p['amount'])) . '</strong></li>';
        echo '</ul></div>';
    }
    if ($r['status'] === 'RETURNED') {
        $record = api_call('GET','/rentals/' . $id . '/return');
        echo '<div class="adm-return-record"><h3>Catatan pengembalian</h3><dl class="adm-dl">';
        foreach (['Tanggal kembali'=>date_label($record['return_date']),'Kondisi'=>$record['condition_on_return'],'Keterlambatan'=>$record['late_days'] . ' hari','Denda'=>money($record['total_fee']),'Diproses oleh'=>$record['processed_by_name']] as $label=>$value) echo '<div><dt>' . h($label) . '</dt><dd>' . h($value) . '</dd></div>';
        echo '</dl><p>' . nl2br(h($record['notes'])) . '</p></div>';
    }
    echo '<div class="adm-form-actions"><a class="adm-btn" href="/rentals/' . $id . '">Pembayaran / detail</a><a class="adm-btn" href="/rentals/' . $id . '/invoice">Lihat faktur ' . icon('arrow-up',14) . '</a>';
    if (!$processing && $r['status'] === 'RENTED') echo '<a class="adm-btn adm-primary" href="/admin/returns/' . $id . '">' . icon('return',15) . 'Proses kembali</a>';
    echo '</div>';
    adm_dialog_end();
}

function adm_directory(bool $logs): void
{
    $data = api_call('GET',$logs ? '/admin/inventory-logs' : '/admin/customers');
    $type = adm_query('type');
    $rows = array_filter($data, static fn($r) => $logs
        ? (!$type || $r['change_type'] === $type) && adm_matches(adm_query('q'),$r['clothing_name'],$r['actor_name'],$r['notes'])
        : adm_matches(adm_query('q'),$r['name'],$r['email'],$r['phone']));
    adm_heading($logs ? 'Riwayat stok' : 'Pelanggan',$logs ? 'Telusuri setiap perubahan jumlah pakaian dan penanggung jawabnya.' : 'Lihat kontak dan jumlah penyewaan setiap pelanggan.');
    echo '<section class="adm-panel"><form class="adm-tools" method="get">'; adm_search($logs ? 'Cari pakaian, pelaku, atau catatan…' : 'Cari nama, email, atau telepon…');
    if ($logs) adm_select('type',[''=>'Semua aktivitas','INITIAL'=>'Stok awal','ADJUSTMENT'=>'Penyesuaian','RENTAL'=>'Penyewaan','RETURN'=>'Pengembalian'],$type,'Filter aktivitas stok');
    echo '<button class="adm-btn" type="submit">Terapkan</button></form>';
    if (!$rows) adm_empty($logs ? 'Tidak ada perubahan stok yang cocok' : 'Pelanggan tidak ditemukan','Periksa pencarian dan filter yang dipilih.');
    else {
        adm_table($logs ? ['Waktu (WIB)','Pakaian','Aktivitas','Perubahan','Pelaku','Catatan'] : ['Pelanggan','Telepon','Alamat','Jumlah sewa'],$logs ? 'Catatan perubahan stok' : 'Daftar pelanggan');
        foreach ($rows as $r) {
            if ($logs) {
                $time = new DateTimeImmutable($r['created_at']);
                echo '<tr><td>' . h(date_label($r['created_at'])) . '<small>' . h($time->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('H:i')) . ' WIB</small></td><td><strong>' . h($r['clothing_name']) . '</strong></td><td>' . h(status_label($r['change_type'])) . '</td><td><span class="adm-quantity' . ($r['quantity_changed'] > 0 ? ' adm-positive' : '') . '">' . ($r['quantity_changed'] > 0 ? '+' : '') . h($r['quantity_changed']) . '</span></td><td>' . h($r['actor_name'] ?: 'Sistem') . '</td><td class="adm-wrap">' . h($r['notes']) . '</td></tr>';
            } else echo '<tr><td><strong>' . h($r['name']) . '</strong><small>' . h($r['email']) . '</small></td><td>' . h($r['phone'] ?: '—') . '</td><td class="adm-wrap">' . h($r['address'] ?: '—') . '</td><td>' . h($r['rental_count']) . '</td></tr>';
        }
        adm_table_end();
    }
    echo '</section>';
}

function render_admin(string $path): bool
{
    if ($path !== '/admin' && !str_starts_with($path,'/admin/')) return false;
    $user = require_user('ADMIN');
    $pages = [''=>['Ringkasan','dashboard'],'inventory'=>['Inventaris','shirt'],'rentals'=>['Penyewaan','rentals'],'returns'=>['Pengembalian','return'],'customers'=>['Pelanggan','users'],'inventory-logs'=>['Riwayat stok','book']];
    $parts = explode('/',trim($path,'/')); $slug = $parts[1] ?? '';
    if (!isset($pages[$slug]) || (count($parts) > 2 && (!in_array($slug,['rentals','returns'],true) || count($parts) !== 3 || !preg_match('/^[1-9][0-9]*$/D',$parts[2])))) throw new DomainException('Halaman admin tidak ditemukan.',404);
    $initials = ''; foreach (array_slice(preg_split('/\s+/',trim($user['name'])),0,2) as $word) $initials .= function_exists('mb_substr') ? mb_substr($word,0,1) : substr($word,0,1);
    echo '<div class="adm-shell"><aside class="adm-sidebar"><a href="/admin" class="adm-wordmark" aria-label="Rentique Admin"><span>rentique.</span><b>r.</b></a><div class="adm-nav-label">Ruang kerja</div><nav id="adm-navigation" aria-label="Navigasi admin">';
    foreach ($pages as $key=>[$label,$ico]) echo '<a href="/admin' . ($key ? '/' . $key : '') . '" class="adm-nav-link' . ($key === $slug ? ' selected' : '') . '"' . ($key === $slug ? ' aria-current="page"' : '') . '>' . icon($ico,19) . '<span>' . h($label) . '</span></a>';
    echo '</nav><div class="adm-side-foot"><a href="/" class="adm-store-link">' . icon('arrow-up') . '<span>Lihat etalase</span></a><a href="/profile" class="adm-account"><span class="adm-avatar">' . h($initials) . '</span><span><strong>' . h($user['name']) . '</strong><small>Administrator</small></span></a></div></aside><div class="adm-main"><header class="adm-top"><details class="adm-native-mobile"><summary aria-label="Buka navigasi admin">' . icon('menu',20) . '</summary><nav aria-label="Navigasi admin seluler">';
    foreach ($pages as $key=>[$label,$ico]) echo '<a class="adm-nav-link' . ($key === $slug ? ' selected' : '') . '" href="/admin' . ($key ? '/' . $key : '') . '">' . icon($ico,19) . h($label) . '</a>';
    echo '<a class="adm-nav-link" href="/">Lihat etalase</a><a class="adm-nav-link" href="/profile">Profil administrator</a></nav></details><span class="adm-divider"></span><a href="/admin">Admin</a>' . icon('chevron',13) . '<span>' . h($pages[$slug][0]) . '</span></header><main class="adm-content">';
    switch ($slug) {
        case '': adm_dashboard(); break;
        case 'inventory': adm_inventory(); break;
        case 'rentals': case 'returns': adm_rentals($slug === 'returns',isset($parts[2]) ? (int)$parts[2] : null); break;
        case 'customers': adm_directory(false); break;
        case 'inventory-logs': adm_directory(true); break;
    }
    echo '</main></div></div>';
    return true;
}
