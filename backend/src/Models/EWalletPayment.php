<?php
declare(strict_types=1);

namespace Rentique\Models;

final class EWalletPayment extends Payment
{
    public function __construct(Rental $rental, int $amount, private string $provider)
    {
        parent::__construct($rental, $amount);
    }

    public function processPayment(): bool { return trim($this->provider) !== '' && $this->finishPayment(); }
    public function getPaymentMethod(): string { return 'EWALLET'; }
    public function toArray(): array { return ['provider' => $this->provider] + parent::toArray(); }
}
