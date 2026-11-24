<?php
declare(strict_types=1);

namespace Rentique\Models;

final class TransferPayment extends Payment
{
    public function __construct(Rental $rental, int $amount, private string $bankName)
    {
        parent::__construct($rental, $amount);
    }

    public function processPayment(): bool { return trim($this->bankName) !== '' && $this->finishPayment(); }
    public function getPaymentMethod(): string { return 'TRANSFER'; }
    public function toArray(): array { return ['bank_name' => $this->bankName] + parent::toArray(); }
}
