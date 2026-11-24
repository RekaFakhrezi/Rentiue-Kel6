<?php
declare(strict_types=1);

namespace Rentique\Models;

use DateTimeImmutable;

final class Customer extends User
{
    private array $rentals = [];

    public function getRole(): string { return 'CUSTOMER'; }

    public function createRental(?DateTimeImmutable $date = null): Rental
    {
        $rental = new Rental($this, $date ?? new DateTimeImmutable('today'));
        $this->rentals[] = $rental;
        return $rental;
    }

    public function viewRentalHistory(): array { return $this->rentals; }
}
