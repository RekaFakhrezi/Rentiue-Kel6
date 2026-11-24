<?php
declare(strict_types=1);

namespace Rentique\Models;

use DateTimeImmutable;

final class RentalReturn
{
    public function __construct(
        private Rental $rental,
        private DateTimeImmutable $returnDate,
        private string $conditionOnReturn,
        private string $notes
    ) {
        $this->returnDate = $returnDate->setTime(0, 0);
        $this->conditionOnReturn = Fields::text($conditionOnReturn, 'Kondisi pengembalian', 180);
        $this->notes = Fields::text($notes, 'Catatan pengembalian', 2000);
    }

    public function lateDays(): int
    {
        $interval = $this->rental->getPlannedReturnDate()->diff($this->returnDate);
        return max(0, (int) $interval->format('%r%a'));
    }

    public function calculateTotalFee(): int { return $this->lateDays() * 10000; }
    public function finalizeReturn(): void { $this->rental->completeReturn($this->returnDate, $this->calculateTotalFee()); }

    public function toArray(): array
    {
        return [
            'rental_id' => $this->rental->getRentalId(), 'return_date' => $this->returnDate->format('Y-m-d'),
            'condition_on_return' => $this->conditionOnReturn, 'notes' => $this->notes,
            'total_fee' => $this->calculateTotalFee(), 'late_days' => $this->lateDays(),
        ];
    }
}
