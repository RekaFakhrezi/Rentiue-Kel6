<?php
declare(strict_types=1);

namespace Rentique\Services;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Rentique\Storage\JsonStore;
use Rentique\Models\Admin;
use Rentique\Models\Customer;
use Rentique\Models\Fields;
use Rentique\Models\InventoryLog;
use Rentique\Models\Payment;
use Rentique\Models\Rental;
use Rentique\Models\RentalReturn;
use Rentique\Models\User;
use Rentique\Repositories\CatalogRepository;
use Rentique\Repositories\RentalRepository;
use Rentique\Repositories\UserRepository;

final class RentalService
{
    private RentalRepository $rentals;
    private CatalogRepository $catalog;
    private UserRepository $users;

    public function __construct(JsonStore $db)
    {
        $this->rentals = new RentalRepository($db);
        $this->catalog = new CatalogRepository($db);
        $this->users = new UserRepository($db);
    }

    private function cart(array $input): array
    {
        $days = Fields::integer($input['rental_days'] ?? null, 'Durasi sewa', 1);
        $items = $input['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !$items) {
            throw new InvalidArgumentException('Tas sewa harus berisi pakaian.');
        }
        $quantities = [];
        foreach ($items as $item) {
            if (!is_array($item)) { throw new InvalidArgumentException('Item sewa tidak valid.'); }
            $id = Fields::integer($item['clothing_id'] ?? null, 'ID pakaian', 1);
            $qty = Fields::integer($item['quantity'] ?? 1, 'Jumlah pakaian', 1);
            if (isset($item['rental_days']) && Fields::integer($item['rental_days'], 'Durasi', 1) !== $days) {
                throw new InvalidArgumentException('Durasi semua pakaian harus sama.');
            }
            if (isset($quantities[$id])) { throw new InvalidArgumentException('Pakaian tercantum dua kali dalam tas.'); }
            $quantities[$id] = $qty;
        }
        ksort($quantities, SORT_NUMERIC);
        $date = isset($input['rental_date']) ? Fields::date($input['rental_date'])->format('Y-m-d') : null;
        return ['days' => $days, 'quantities' => $quantities, 'date' => $date];
    }

    /** addItem() changes only these in-memory objects until create() commits their new stocks. */
    private function prepareRental(array $cart, ?Customer $customer): Rental
    {
        $date = $cart['date'] ? Fields::date($cart['date']) : new DateTimeImmutable('today');
        $rental = $customer ? $customer->createRental($date) : new Rental(null, $date);
        $clothes = $this->catalog->byIds(array_keys($cart['quantities']), false);
        foreach ($cart['quantities'] as $id => $qty) {
            if (!isset($clothes[$id])) { throw new DomainException('Salah satu pakaian sudah tidak tersedia.', 409); }
            $rental->addItem($clothes[$id], $cart['days'], $qty);
        }
        return $rental;
    }

    public function quote(array $input): array
    {
        $rental = $this->prepareRental($this->cart($input), null);
        return ['status' => 'DRAFT'] + $rental->toArray();
    }

    private function key(string $key): string
    {
        $key = Fields::text($key, 'Idempotency-Key', 128);
        if (!preg_match('/^[a-zA-Z0-9_.-]{8,128}$/D', $key)) { throw new InvalidArgumentException('Idempotency-Key tidak valid.'); }
        return $key;
    }

    private function checkReplay(array $existing, string $hash): void
    {
        if (!hash_equals($existing['request_hash'], $hash)) {
            throw new DomainException('Kunci permintaan telah digunakan dengan data berbeda.', 409);
        }
    }

    public function create(array $input, int $customerId, string $idempotencyKey): array
    {
        $cart = $this->cart($input);
        $key = $this->key($idempotencyKey);
        $hash = hash('sha256', json_encode($cart, JSON_THROW_ON_ERROR));
        return $this->rentals->transaction(function () use ($customerId, $cart, $key, $hash) {
            $customer = $this->users->model($customerId);
            if (!$customer instanceof Customer) { throw new DomainException('Khusus pelanggan.', 403); }
            $existing = $this->rentals->findRequest($customerId, $key);
            if ($existing) {
                $this->checkReplay($existing, $hash);
                return $this->hydrate((int) $existing['id'])->toArray();
            }

            $rental = $this->prepareRental($cart, $customer);
            $id = $this->rentals->create($rental, $key, $hash);
            foreach ($rental->getItems() as $item) {
                $clothing = $item->getClothing();
                $this->catalog->saveStock($clothing);
                $this->catalog->log(new InventoryLog($clothing, $customer, 'RENTAL', -$item->getQuantity(), "Penyewaan #$id"));
            }
            $saved = $this->hydrate($id);
            $this->rentals->issueInvoice($saved);
            return $saved->toArray();
        });
    }

    private function hydrate(int $id): Rental
    {
        return $this->rentals->hydrate($id, $this->catalog, $this->users);
    }

    public function list(array $user): array
    {
        return $this->rentals->listing($user['role'] === 'ADMIN' ? null : (int) $user['id']);
    }

    public function get(int $id, array $user): array
    {
        return $this->rentals->transaction(function () use ($id, $user) {
            $this->rentals->authorize($id, $user);
            return $this->hydrate($id)->toArray() + ['payments' => $this->rentals->payments($id)];
        }, true);
    }

    public function invoice(int $id, array $user): array
    {
        return $this->rentals->transaction(function () use ($id, $user) {
            $this->rentals->authorize($id, $user);
            return $this->rentals->invoice($this->hydrate($id));
        }, true);
    }

    public function payments(int $id, array $user): array
    {
        $this->rentals->authorize($id, $user);
        return $this->rentals->payments($id);
    }

    public function pay(int $id, array $input, array $user, string $key): array
    {
        $key = $this->key($key);
        return $this->rentals->transaction(function () use ($id, $input, $user, $key) {
            $this->rentals->authorize($id, $user);
            $rental = $this->hydrate($id);
            $payment = Payment::fromInput($rental, $input);
            $hash = hash('sha256', json_encode($payment->toArray(), JSON_THROW_ON_ERROR));
            $existing = $this->rentals->paymentRequest($id, $key);
            if ($existing) {
                $this->checkReplay($existing, $hash);
                unset($existing['request_hash']);
                return $existing;
            }
            if (!$payment->processPayment()) {
                throw new InvalidArgumentException('Uang diterima kurang atau nominal melebihi sisa tagihan.');
            }
            return $this->rentals->addPayment($payment, $key, $hash);
        });
    }

    public function processReturn(int $id, array $input, array $user): array
    {
        $actor = User::fromRow($user);
        if (!$actor instanceof Admin) { throw new DomainException('Khusus administrator.', 403); }
        return $this->rentals->transaction(function () use ($id, $input, $user, $actor) {
            $this->rentals->authorize($id, $user);
            $rental = $this->hydrate($id);
            $return = new RentalReturn($rental, Fields::date($input['return_date'] ?? null),
                Fields::text($input['condition_on_return'] ?? null, 'Kondisi', 180),
                Fields::text($input['notes'] ?? null, 'Catatan', 2000));
            $actor->processReturn($return);
            $this->rentals->addReturn($return, $actor->getId());
            foreach ($rental->getItems() as $item) {
                $clothing = $item->getClothing();
                $this->catalog->saveStock($clothing);
                $this->catalog->log(new InventoryLog($clothing, $actor, 'RETURN', $item->getQuantity(), "Pengembalian #$id"));
            }
            return $this->rentals->returnRecord($id);
        });
    }

    public function getReturn(int $id, array $user): array
    {
        $this->rentals->authorize($id, $user);
        return $this->rentals->returnRecord($id);
    }
}
