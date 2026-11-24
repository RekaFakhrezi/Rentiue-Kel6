<?php
declare(strict_types=1);

namespace Rentique\Models;

final class CashPayment extends Payment
{
    public function __construct(Rental $rental, int $amount, private int $cashReceived)
    {
        parent::__construct($rental, $amount);
    }

    public function processPayment(): bool
    {
        return $this->cashReceived >= $this->getAmount() && $this->finishPayment();
    }

    public function getPaymentMethod(): string { return 'CASH'; }

    public function toArray(): array
    {
        return ['cash_received' => $this->cashReceived, 'change_amount' => $this->cashReceived - $this->getAmount()]
            + parent::toArray();
    }
}
