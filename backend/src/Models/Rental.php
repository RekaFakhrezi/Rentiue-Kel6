<?php
declare(strict_types=1);

namespace Rentique\Models;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Rental
{
    private int $rentalId = 0;
    private string $status = 'DRAFT';
    private array $items = [];
    private DateTimeImmutable $plannedReturnDate;
    private ?DateTimeImmutable $actualReturnDate = null;
    private int $paidAmount = 0;
    private int $returnFee = 0;

    public function __construct(private ?Customer $customer, private DateTimeImmutable $rentalDate)
    {
        $this->rentalDate = $rentalDate->setTime(0, 0);
        $this->plannedReturnDate = $this->rentalDate;
    }

    /** Rebuild existing items without charging current prices or reducing stock again. */
    public static function restore(Customer $customer, array $row, array $items): self
    {
        $rental = new self($customer, Fields::date($row['rental_date']));
        $rental->rentalId = (int) $row['id'];
        $rental->items = $items;
        $rental->status = $row['status'];
        $rental->plannedReturnDate = Fields::date($row['planned_return_date']);
        $rental->actualReturnDate = $row['actual_return_date'] ? Fields::date($row['actual_return_date']) : null;
        $rental->paidAmount = (int) $row['paid_amount'];
        $rental->returnFee = (int) $row['return_fee'];
        return $rental;
    }

    public function addItem(Clothing $clothing, int $days, int $qty = 1): void
    {
        if ($this->paidAmount > 0 || $this->status === 'RETURNED') {
            throw new DomainException('Isi penyewaan tidak dapat diubah lagi.', 409);
        }
        Fields::integer($days, 'Durasi sewa', 1);
        Fields::integer($qty, 'Jumlah pakaian', 1);
        if (!$clothing->isAvailable($qty)) {
            throw new DomainException('Stok ' . $clothing->getClothingName() . ' tidak mencukupi.', 409);
        }
        if ($this->items && $this->items[0]->getRentalDays() !== $days) {
            throw new InvalidArgumentException('Durasi semua pakaian harus sama.');
        }
        $planned = $this->rentalDate->modify("+$days days");
        if (strlen($planned->format('Y')) > 4) {
            throw new InvalidArgumentException('Tanggal pengembalian di luar jangkauan.');
        }
        $item = RentalItem::book($clothing, $days, $qty);
        Fields::money($this->calculateTotal() + $item->calculateSubtotal(), 'Total sewa');
        $clothing->reduceStock($qty);
        $this->items[] = $item;
        $this->plannedReturnDate = $planned;
        $this->updateStatus('RENTED');
    }

    private function updateStatus(string $status): void
    {
        if (!$this->items || $this->status === 'RETURNED'
            || ($status === 'RETURNED' && !$this->actualReturnDate)) {
            throw new DomainException('Perubahan status sewa tidak valid.', 409);
        }
        $this->status = $status;
    }

    public function calculateTotal(): int
    {
        return array_sum(array_map(static fn(RentalItem $item) => $item->calculateSubtotal(), $this->items));
    }

    public function recordPayment(int $amount): void
    {
        if (!$this->items || $amount <= 0 || $amount > $this->getOutstandingAmount()) {
            throw new InvalidArgumentException('Nominal pembayaran harus positif dan tidak melebihi sisa tagihan.');
        }
        $this->paidAmount += $amount;
    }

    public function completeReturn(DateTimeImmutable $date, int $fee): void
    {
        if ($this->status !== 'RENTED') {
            throw new DomainException('Pengembalian telah diproses atau penyewaan belum aktif.', 409);
        }
        if ($date < $this->rentalDate || $fee < 0) {
            throw new InvalidArgumentException('Tanggal atau biaya pengembalian tidak valid.');
        }
        Fields::money($this->calculateTotal() + $fee, 'Total tagihan');
        foreach ($this->items as $item) {
            $item->getClothing()->increaseStock($item->getQuantity());
        }
        $this->actualReturnDate = $date;
        $this->returnFee = $fee;
        $this->updateStatus('RETURNED');
    }

    public function getRentalId(): int { return $this->rentalId; }
    public function getCustomer(): ?Customer { return $this->customer; }
    public function getRentalDate(): DateTimeImmutable { return $this->rentalDate; }
    public function getPlannedReturnDate(): DateTimeImmutable { return $this->plannedReturnDate; }
    public function getStatus(): string { return $this->status; }
    public function getItems(): array { return $this->items; }
    public function getOutstandingAmount(): int { return $this->calculateTotal() + $this->returnFee - $this->paidAmount; }

    public static function paymentSummary(int $total, int $fee, int $paid): array
    {
        $balance = $total + $fee - $paid;
        return [
            'outstanding_amount' => $balance,
            'payment_status' => $balance === 0 ? 'PAID' : ($paid > 0 ? 'PARTIALLY_PAID' : 'UNPAID'),
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->rentalId, 'customer_id' => $this->customer?->getId(),
            'customer_name' => $this->customer?->getName(), 'customer' => $this->customer?->toArray(),
            'rental_date' => $this->rentalDate->format('Y-m-d'),
            'planned_return_date' => $this->plannedReturnDate->format('Y-m-d'),
            'actual_return_date' => $this->actualReturnDate?->format('Y-m-d'), 'status' => $this->status,
            'rental_days' => $this->items ? $this->items[0]->getRentalDays() : 0,
            'total_price' => $this->calculateTotal(), 'return_fee' => $this->returnFee,
            'paid_amount' => $this->paidAmount,
            'items' => array_map(static fn(RentalItem $item) => $item->toArray(), $this->items),
        ] + self::paymentSummary($this->calculateTotal(), $this->returnFee, $this->paidAmount);
    }
}
