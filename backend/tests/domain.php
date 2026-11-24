<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Rentique\Models\Admin;
use Rentique\Models\CashPayment;
use Rentique\Models\Clothing;
use Rentique\Models\Customer;
use Rentique\Models\EWalletPayment;
use Rentique\Models\Fields;
use Rentique\Models\Rental;
use Rentique\Models\RentalItem;
use Rentique\Models\RentalReturn;
use Rentique\Models\TransferPayment;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}

function rejects(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException|InvalidArgumentException) {
        check(true, $message);
        return;
    }
    throw new RuntimeException("Expected rejection: $message");
}

$customer = new Customer(['id' => 1, 'name' => 'Reka', 'email' => 'reka@example.test']);
$admin = new Admin(['id' => 2, 'name' => 'Admin', 'email' => 'admin@example.test']);
$common = ['category_id' => 1, 'category_name' => 'Formal', 'size' => 'L', 'color' => 'Hitam', 'stock' => 2];
$standard = Clothing::fromRow(['id'=>1, 'name'=>'Jas Standar', 'type'=>'SUIT', 'stock'=>3,
    'rental_price_per_day'=>100000, 'dry_cleaning_fee'=>20000] + $common);
$premium = Clothing::fromRow(['id'=>2, 'name'=>'Jas Premium', 'type'=>'SUIT',
    'rental_price_per_day'=>250000, 'dry_cleaning_fee'=>30000] + $common);
$dress = Clothing::fromRow(['id'=>3, 'name'=>'Dress Pesta', 'type'=>'DRESS',
    'rental_price_per_day'=>150000, 'dress_style'=>'A-Line'] + $common);
$costume = Clothing::fromRow(['id'=>4, 'name'=>'Kostum Bajak Laut', 'type'=>'COSTUME',
    'rental_price_per_day'=>80000, 'theme'=>'Bajak Laut', 'accessory_fee'=>15000] + $common);

check($admin->adjustStock($standard, 1, 'Tambah jas')->record()['quantity_changed'] === 1, 'Stock adjustment');
$rental = $customer->createRental(Fields::date('2026-11-18'));
$rental->addItem($standard, 2);
rejects(fn() => $rental->addItem($dress, 3), 'Mixed duration');
rejects(fn() => $rental->addItem($dress, 2, -1), 'Negative quantity');
rejects(fn() => $rental->addItem($dress, 2, 3), 'Insufficient stock');
$rental->addItem($premium, 2);
$rental->addItem($dress, 2, 2);
$rental->addItem($costume, 2);
check($rental->calculateTotal() === 1525000, 'Original four-garment total');
$standard->setRentalPricePerDay(1);
check($rental->calculateTotal() === 1525000, 'Historical price snapshot');
check($rental->getPlannedReturnDate()->format('Y-m-d') === '2026-11-20', 'Planned return date');
check($dress->getStock() === 0, 'Stock reduction');
rejects(fn() => $dress->reduceStock(1), 'Cannot overdraw stock');
rejects(fn() => $dress->increaseStock(0), 'Cannot add zero units');
rejects(fn() => Fields::integer(1.5, 'Jumlah'), 'Fractional quantity');
rejects(fn() => Fields::date('2026-02-31'), 'Invalid date');

$cash = new CashPayment($rental, 500000, 550000);
check($cash->processPayment(), 'Cash succeeds');
check($cash->toArray()['change_amount'] === 50000, 'Cash change');
check(!$cash->processPayment(), 'Reject processing payment object twice');
check($rental->toArray()['payment_status'] === 'PARTIALLY_PAID', 'Partial payment');
rejects(fn() => $rental->addItem($standard, 2), 'Paid contents immutable');
check(!(new TransferPayment($rental, 2000000, 'BCA'))->processPayment(), 'Reject excessive payment');
check(!(new EWalletPayment($rental, 1, ' '))->processPayment(), 'Reject empty wallet');
check(!(new CashPayment($rental, 10, 9))->processPayment(), 'Reject insufficient cash');
rejects(fn() => new CashPayment($rental, 0, 0), 'Reject zero payment');
check((new TransferPayment($rental, 700000, 'BCA'))->processPayment(), 'Bank transfer succeeds');
check((new EWalletPayment($rental, 325000, 'DANA'))->processPayment(), 'E-wallet succeeds');
check($rental->getOutstandingAmount() === 0, 'Paid in full');
$onTime = new RentalReturn($rental, Fields::date('2026-11-20'), 'Baik', 'Lengkap');
check($onTime->calculateTotalFee() === 0, 'On-time return');
$return = new RentalReturn($rental, new DateTimeImmutable('2026-11-21 00:01'), 'Baik', 'Lengkap');
$admin->processReturn($return);
check($return->calculateTotalFee() === 10000, 'Calendar-day late fee');
check($rental->getStatus() === 'RETURNED', 'Final rental status');
check($rental->getOutstandingAmount() === 10000, 'New balance after late return');
check((new CashPayment($rental, 10000, 10000))->processPayment(), 'Late fee payment succeeds');
check($rental->getOutstandingAmount() === 0, 'Final zero balance');
rejects(fn() => $admin->processReturn($return), 'Duplicate return');
check([$standard->getStock(),$premium->getStock(),$dress->getStock(),$costume->getStock()] === [4,2,2,2],
    'Adjusted stocks restored exactly once');

$row = $rental->toArray();
$restoredItems = array_map(static fn($item) => RentalItem::restore($item->getClothing(), $item->toArray()), $rental->getItems());
$restored = Rental::restore($customer, $row, $restoredItems);
check($restored->toArray() === $row, 'Hydrate status, dates, stock-neutral line items, snapshots, fees and balance');
echo "PASS: $checks domain checks. Total Rp1.525.000, late fee Rp10.000, final balance Rp0 and status RETURNED.\n";
