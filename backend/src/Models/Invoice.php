<?php
declare(strict_types=1);

namespace Rentique\Models;

final class Invoice
{
    public function __construct(private Rental $rental) {}

    public function generateInvoiceNumber(): string
    {
        return sprintf('INV-%s-%06d', $this->rental->getRentalDate()->format('Y'), $this->rental->getRentalId());
    }

    public function toArray(array $issued, array $payments): array
    {
        return $this->rental->toArray() + [
            'invoice_number' => $issued['invoice_number'], 'issue_date' => $issued['issue_date'],
            'total_amount' => (int) $issued['total_amount'], 'payments' => $payments,
        ];
    }
}
