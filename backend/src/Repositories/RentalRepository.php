<?php
declare(strict_types=1);

namespace Rentique\Repositories;

use DomainException;
use Rentique\Models\Customer;
use Rentique\Models\Invoice;
use Rentique\Models\Payment;
use Rentique\Models\Rental;
use Rentique\Models\RentalItem;
use Rentique\Models\RentalReturn;

final class RentalRepository extends Repository
{
    private const PAYMENT_FIELDS = ['id', 'rental_id', 'payment_method', 'amount', 'payment_status', 'cash_received',
        'change_amount', 'bank_name', 'provider', 'payment_date', 'created_at'];

    private function summary(array $row): array
    {
        unset($row['request_key'], $row['request_hash']);
        $row['customer_name'] = $this->db->find('users', $row['customer_id'])['name'];
        $row['paid_amount'] = array_sum(array_column($this->payments($row['id']), 'amount'));
        return $row + Rental::paymentSummary($row['total_price'], $row['return_fee'], $row['paid_amount']);
    }

    public function listing(?int $customerId = null, ?int $limit = null): array
    {
        $rows = array_filter($this->db->rows('rentals'), static fn($r) => $customerId === null || $r['customer_id'] === $customerId);
        usort($rows, static fn($a, $b) => $b['id'] <=> $a['id']);
        if ($limit !== null) { $rows = array_slice($rows, 0, $limit); }
        return array_map(fn($row) => $this->summary($row), $rows);
    }

    public function authorize(int $id, array $user): void
    {
        $row = $this->db->find('rentals', $id);
        if (!$row || ($user['role'] !== 'ADMIN' && $row['customer_id'] !== (int)$user['id'])) {
            throw new DomainException('Penyewaan tidak ditemukan.', 404);
        }
    }

    public function hydrate(int $id, CatalogRepository $catalog, UserRepository $users): Rental
    {
        $row = $this->summary($this->db->find('rentals', $id) ?? throw new DomainException('Penyewaan tidak ditemukan.', 404));
        $customer = $users->model($row['customer_id']);
        if (!$customer instanceof Customer) { throw new DomainException('Pelanggan tidak ditemukan.', 404); }
        $itemRows = array_values(array_filter($this->db->rows('rental_items'), static fn($r) => $r['rental_id'] === $id));
        $clothings = $catalog->byIds(array_column($itemRows, 'clothing_id'), true);
        $items = array_map(static fn($item) => RentalItem::restore($clothings[$item['clothing_id']], $item), $itemRows);
        return Rental::restore($customer, $row, $items);
    }

    public function findRequest(int $customerId, string $key): ?array
    {
        foreach ($this->db->rows('rentals') as $row) {
            if ($row['customer_id'] === $customerId && $row['request_key'] === $key) { return $row; }
        }
        return null;
    }

    public function create(Rental $rental, string $key, string $hash): int
    {
        $data = $rental->toArray();
        $id = $this->insert('rentals', [
            'customer_id' => $data['customer_id'], 'rental_date' => $data['rental_date'],
            'planned_return_date' => $data['planned_return_date'], 'actual_return_date' => null,
            'status' => $data['status'], 'total_price' => $data['total_price'], 'return_fee' => 0,
            'request_key' => $key, 'request_hash' => $hash,
        ]);
        foreach ($rental->getItems() as $item) {
            $this->insert('rental_items', [
                'rental_id' => $id, 'clothing_id' => $item->getClothing()->getClothingId(),
                'quantity' => $item->getQuantity(), 'rental_days' => $item->getRentalDays(),
                'price_per_day' => $item->getPricePerDay(), 'additional_fee' => $item->getAdditionalFee(),
                'subtotal' => $item->calculateSubtotal(),
            ]);
        }
        return $id;
    }

    public function issueInvoice(Rental $rental): void
    {
        foreach ($this->db->rows('invoices') as $issued) {
            if ($issued['rental_id'] === $rental->getRentalId()) { throw new DomainException('Invoice telah diterbitkan.', 409); }
        }
        $this->insert('invoices', [
            'rental_id' => $rental->getRentalId(), 'invoice_number' => (new Invoice($rental))->generateInvoiceNumber(),
            'total_amount' => $rental->calculateTotal(), 'issue_date' => date('c'),
        ]);
    }

    public function invoice(Rental $rental): array
    {
        foreach ($this->db->rows('invoices') as $issued) {
            if ($issued['rental_id'] === $rental->getRentalId()) {
                return (new Invoice($rental))->toArray($issued, $this->payments($rental->getRentalId()));
            }
        }
        throw new DomainException('Invoice tidak ditemukan.', 404);
    }

    private function paymentFields(array $row): array
    {
        return array_intersect_key($row, array_flip(self::PAYMENT_FIELDS));
    }

    public function payments(int $rentalId): array
    {
        $rows = array_filter($this->db->rows('payments'), static fn($r) => $r['rental_id'] === $rentalId);
        return array_values(array_map(fn($row) => $this->paymentFields($row), $rows));
    }

    public function paymentRequest(int $rentalId, string $key): ?array
    {
        foreach ($this->db->rows('payments') as $row) {
            if ($row['rental_id'] === $rentalId && $row['request_key'] === $key) {
                return $this->paymentFields($row) + ['request_hash' => $row['request_hash']];
            }
        }
        return null;
    }

    public function addPayment(Payment $payment, string $key, string $hash): array
    {
        $id = $this->insert('payments', $payment->toArray() + [
            'request_key' => $key, 'request_hash' => $hash, 'payment_date' => date('c'),
        ]);
        return $this->paymentFields($this->db->find('payments', $id));
    }

    public function addReturn(RentalReturn $return, int $adminId): void
    {
        $fields = $return->toArray();
        foreach ($this->db->rows('rental_returns') as $row) {
            if ($row['rental_id'] === $fields['rental_id']) { throw new DomainException('Pengembalian telah diproses.', 409); }
        }
        unset($fields['late_days']);
        $this->insert('rental_returns', $fields + ['processed_by' => $adminId]);
        $this->db->update('rentals', $fields['rental_id'], [
            'actual_return_date' => $fields['return_date'], 'return_fee' => $fields['total_fee'], 'status' => 'RETURNED',
        ]);
    }

    public function returnRecord(int $rentalId): array
    {
        foreach ($this->db->rows('rental_returns') as $row) {
            if ($row['rental_id'] !== $rentalId) { continue; }
            $rental = $this->db->find('rentals', $rentalId);
            $planned = \Rentique\Models\Fields::date($rental['planned_return_date']);
            $returned = \Rentique\Models\Fields::date($row['return_date']);
            return $row + [
                'processed_by_name' => $this->db->find('users', $row['processed_by'])['name'],
                'late_days' => max(0, (int)$planned->diff($returned)->format('%r%a')),
            ];
        }
        throw new DomainException('Pengembalian belum tercatat.', 404);
    }

    public function statistics(): array
    {
        $products = array_filter($this->db->rows('clothings'), static fn($r) => $r['is_active']);
        $rentals = $this->db->rows('rentals');
        $revenue = array_sum(array_column($this->db->rows('payments'), 'amount'));
        $today = date('Y-m-d');
        return [
            'total_products' => count($products), 'available_units' => array_sum(array_column($products, 'stock')),
            'active_rentals' => count(array_filter($rentals, static fn($r) => $r['status'] === 'RENTED')),
            'returned_rentals' => count(array_filter($rentals, static fn($r) => $r['status'] === 'RETURNED')),
            'overdue_rentals' => count(array_filter($rentals, static fn($r) => $r['status'] === 'RENTED' && $r['planned_return_date'] < $today)),
            'revenue' => $revenue,
            'outstanding_payments' => array_sum(array_column($rentals, 'total_price')) + array_sum(array_column($rentals, 'return_fee')) - $revenue,
        ];
    }
}
