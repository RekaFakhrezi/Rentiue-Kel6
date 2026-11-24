<?php
declare(strict_types=1);

namespace Rentique\Models;

use InvalidArgumentException;

abstract class Payment
{
    private bool $processed = false;

    public function __construct(private Rental $rental, private int $amount)
    {
        Fields::integer($amount, 'Nominal pembayaran', 1, Fields::MAX_MONEY);
    }

    public static function fromInput(Rental $rental, array $input): self
    {
        $amount = Fields::money($input['amount'] ?? null);
        return match ($input['payment_method'] ?? null) {
            'CASH' => new CashPayment($rental, $amount, Fields::money($input['cash_received'] ?? null, 'Uang diterima')),
            'TRANSFER' => new TransferPayment($rental, $amount, Fields::text($input['bank_name'] ?? null, 'Nama bank', 100)),
            'EWALLET' => new EWalletPayment($rental, $amount, Fields::text($input['provider'] ?? null, 'Penyedia e-wallet', 100)),
            default => throw new InvalidArgumentException('Metode pembayaran tidak dikenal.'),
        };
    }

    abstract public function processPayment(): bool;
    abstract public function getPaymentMethod(): string;

    protected function finishPayment(): bool
    {
        if ($this->processed || $this->amount > $this->rental->getOutstandingAmount()) {
            return false;
        }
        $this->rental->recordPayment($this->amount);
        $this->processed = true;
        return true;
    }

    public function getAmount(): int { return $this->amount; }

    public function toArray(): array
    {
        return [
            'rental_id' => $this->rental->getRentalId(), 'amount' => $this->amount,
            'payment_method' => $this->getPaymentMethod(), 'payment_status' => $this->processed ? 'PAID' : 'PENDING',
            'cash_received' => null, 'change_amount' => null, 'bank_name' => null, 'provider' => null,
        ];
    }
}
