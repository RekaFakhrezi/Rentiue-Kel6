<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use Rentique\Controllers\ApiController;
use Rentique\Models\Fields;
use Rentique\Repositories\UserRepository;
use Rentique\Storage\JsonStore;

$directory = sys_get_temp_dir().'/rentique-json-'.bin2hex(random_bytes(12));
if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create isolated test directory.'); }
$path = $directory.'/data.json';
$checks = 0;
function verify(bool $value, string $message): void
{
    global $checks;
    if (!$value) { throw new RuntimeException($message); }
    $checks++;
}
function refused(callable $action, int $code = 0): void
{
    try { $action(); }
    catch (DomainException|InvalidArgumentException $error) {
        verify($code === 0 || $error->getCode() === $code, 'Unexpected rejection code: '.$error->getMessage());
        return;
    }
    throw new RuntimeException('Expected request rejection.');
}
function callApi(ApiController $api, ?int $actor, string $method, string $route, array $body = [], array $query = [], string $key = ''): mixed
{
    $_SESSION = $actor === null ? [] : ['user_id' => $actor];
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    return $api->dispatch($method, $route, $body, $query);
}

try {
    $store = new JsonStore($path);
    $api = new ApiController($store);
    $users = new UserRepository($store);
    verify($users->authenticate('admin@rentique.test', 'Admin123!')['role'] === 'ADMIN', 'Seed admin login.');
    verify($users->authenticate('customer@rentique.test', 'Customer123!')['role'] === 'CUSTOMER', 'Seed customer login.');
    verify(!isset($users->find(2)['password_hash']), 'Password hashes never exposed.');
    $products = callApi($api, null, 'GET', '/clothings');
    verify(count($products) === 12 && $products[0]['image_url'] === '/images/suit.webp', 'Exact seeded catalogue.');
    verify($products[1]['rental_price_per_day'] === 250000 && $products[11]['name'] === 'Kostum Balerina', 'Seed prices and names.');
    verify(count(callApi($api, null, 'GET', '/clothings', [], ['type' => 'DRESS', 'size' => 'M'])) === 2, 'Catalogue filters.');
    $cart = ['rental_date' => '2026-11-18', 'rental_days' => 2, 'items' => [
        ['clothing_id' => 1, 'quantity' => 1], ['clothing_id' => 2, 'quantity' => 1],
        ['clothing_id' => 3, 'quantity' => 2], ['clothing_id' => 4, 'quantity' => 1],
    ]];
    $before = file_get_contents($path);
    $quote = callApi($api, null, 'POST', '/rentals/quote', $cart);
    verify($quote['status'] === 'DRAFT' && $quote['total_price'] === 1525000, 'Domain quote.');
    verify(file_get_contents($path) === $before, 'Guest quote does not reserve or persist.');
    refused(fn() => callApi($api, null, 'POST', '/rentals', $cart, [], 'checkout-0001'), 401);
    refused(fn() => callApi($api, 1, 'POST', '/rentals', $cart, [], 'checkout-0001'), 403);
    $rental = callApi($api, 2, 'POST', '/rentals', $cart, [], 'checkout-0001');
    $id = $rental['id'];
    verify($rental['total_price'] === 1525000 && $rental['planned_return_date'] === '2026-11-20', 'Persisted domain rental.');
    verify(callApi($api, 2, 'POST', '/rentals', $cart, [], 'checkout-0001')['id'] === $id, 'Creation idempotent replay after stock depletion.');
    $changed = $cart;
    $changed['rental_days'] = 3;
    refused(fn() => callApi($api, 2, 'POST', '/rentals', $changed, [], 'checkout-0001'), 409);
    $afterCreate = file_get_contents($path);
    $invalid = ['rental_date' => '2026-11-18', 'rental_days' => 2, 'items' => [
        ['clothing_id' => 1, 'quantity' => 1], ['clothing_id' => 3, 'quantity' => 1],
    ]];
    refused(fn() => callApi($api, 2, 'POST', '/rentals', $invalid, [], 'checkout-0002'), 409);
    verify(file_get_contents($path) === $afterCreate, 'Failed multi-item reservation rolls back stocks, rows, invoice and logs.');
    refused(fn() => callApi($api, 2, 'PATCH', '/clothings/1', ['name' => 'Not allowed']), 403);
    refused(fn() => callApi($api, 1, 'PATCH', '/clothings/1', ['stock' => 3, 'expected_stock' => 3]), 409);
    callApi($api, 1, 'PATCH', '/clothings/1', ['rental_price_per_day' => 1]);
    verify(callApi($api, 2, 'GET', "/rentals/$id")['total_price'] === 1525000, 'Historical price and fees survive catalogue edits.');
    refused(fn() => callApi($api, 2, 'POST', "/rentals/$id/payments", ['payment_method' => 'CASH', 'amount' => 0, 'cash_received' => 0], [], 'payment-0000'));
    refused(fn() => callApi($api, 2, 'POST', "/rentals/$id/payments", ['payment_method' => 'TRANSFER', 'amount' => Fields::MAX_MONEY + 1, 'bank_name' => 'BCA'], [], 'payment-0000'));
    $cash = ['payment_method' => 'CASH', 'amount' => 500000, 'cash_received' => 550000];
    $payment = callApi($api, 2, 'POST', "/rentals/$id/payments", $cash, [], 'payment-0001');
    verify($payment['change_amount'] === 50000 && $payment['payment_status'] === 'PAID', 'Cash domain processing.');
    verify(callApi($api, 2, 'POST', "/rentals/$id/payments", $cash, [], 'payment-0001') === $payment, 'Payment replay.');
    refused(fn() => callApi($api, 2, 'POST', "/rentals/$id/payments", array_replace($cash, ['cash_received' => 600000]), [], 'payment-0001'), 409);
    callApi($api, 2, 'POST', "/rentals/$id/payments", ['payment_method' => 'TRANSFER', 'amount' => 700000, 'bank_name' => 'BCA'], [], 'payment-0002');
    callApi($api, 2, 'POST', "/rentals/$id/payments", ['payment_method' => 'EWALLET', 'amount' => 325000, 'provider' => 'DANA'], [], 'payment-0003');
    $returnInput = ['return_date' => '2026-11-21', 'condition_on_return' => 'Baik', 'notes' => 'Lengkap'];
    refused(fn() => callApi($api, 2, 'POST', "/rentals/$id/return", $returnInput), 403);
    $returned = callApi($api, 1, 'POST', "/rentals/$id/return", $returnInput);
    verify($returned['total_fee'] === 10000 && $returned['processed_by'] === 1 && $returned['late_days'] === 1, 'Domain return and actor.');
    $afterReturn = file_get_contents($path);
    refused(fn() => callApi($api, 1, 'POST', "/rentals/$id/return", $returnInput), 409);
    verify(file_get_contents($path) === $afterReturn, 'Duplicate return is all-or-nothing.');
    $reopened = new ApiController(new JsonStore($path));
    verify(callApi($reopened, 2, 'GET', "/rentals/$id")['outstanding_amount'] === 10000, 'Rental and payments survive reopening.');
    verify(callApi($reopened, null, 'GET', '/clothings/1')['stock'] === 3, 'Stock restored exactly once across reopening.');
    verify(count(callApi($reopened, 2, 'GET', "/rentals/$id/payments")) === 3, 'Payment history retained.');
    callApi($reopened, 2, 'POST', "/rentals/$id/payments", ['payment_method' => 'EWALLET', 'amount' => 10000, 'provider' => 'DANA'], [], 'payment-0004');
    $invoice = callApi($reopened, 2, 'GET', "/rentals/$id/invoice");
    verify($invoice['payment_status'] === 'PAID' && $invoice['total_amount'] === 1525000 && $invoice['status'] === 'RETURNED', 'Original invoice with complete current balance.');
    verify(count($store->rows('invoices')) === 1 && count($store->rows('rental_returns')) === 1, 'One invoice and return per rental.');
    $other = $store->insert('users', ['name' => 'Other', 'email' => 'other@rentique.test', 'password_hash' => password_hash('Other123!', PASSWORD_DEFAULT), 'role' => 'CUSTOMER', 'phone' => '', 'address' => '']);
    refused(fn() => callApi($reopened, $other, 'GET', "/rentals/$id"), 404);
    verify(callApi($reopened, $other, 'GET', '/rentals') === [], 'History ownership.');
    $logs = callApi($reopened, 1, 'GET', '/admin/inventory-logs');
    verify(count(array_filter($logs, static fn($l) => $l['change_type'] === 'RENTAL' && $l['actor_id'] === 2)) === 4, 'Rental log actors.');
    verify(count(array_filter($logs, static fn($l) => $l['change_type'] === 'RETURN' && $l['actor_id'] === 1)) === 4, 'Return log actors.');
    $dashboard = callApi($reopened, 1, 'GET', '/admin/dashboard');
    verify($dashboard['returned_rentals'] === 1 && $dashboard['revenue'] === 1535000 && $dashboard['outstanding_payments'] === 0, 'Dashboard persisted totals.');
    verify(callApi($reopened, 1, 'GET', '/admin/customers')[1]['rental_count'] === 1, 'Customer history totals.');
    $baseline = file_get_contents($path);
    try {
        $store->transaction(function () use ($store) {
            $store->update('clothings', 1, ['stock' => 100]);
            throw new RuntimeException('Intentional rollback');
        });
    } catch (RuntimeException $error) {
        verify($error->getMessage() === 'Intentional rollback', 'Transaction propagated exception.');
    }
    verify(file_get_contents($path) === $baseline, 'Thrown mutation never replaces persisted data.');
    foreach (['{broken', '{"version":1,"users":[]}'] as $corrupt) {
        file_put_contents($path, $corrupt);
        try { new JsonStore($path); throw new LogicException('Corrupt data accepted.'); }
        catch (RuntimeException) { verify(file_get_contents($path) === $corrupt, 'Corrupt data detected without reseeding or overwriting.'); }
    }
    echo "PASS: $checks isolated JSON persistence checks.\n";
} finally {
    foreach (glob($directory.'/*') ?: [] as $file) { unlink($file); }
    foreach (glob($directory.'/.rentique-*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}
