<?php
declare(strict_types=1);

namespace Rentique\Models;

final class RentalItem
{
    private int $id = 0;
    private int $pricePerDay;
    private int $additionalFee;

    private function __construct(private Clothing $clothing, private int $rentalDays, private int $quantity)
    {
        Fields::integer($rentalDays, 'Durasi sewa', 1);
        Fields::integer($quantity, 'Jumlah pakaian', 1);
    }

    public static function book(Clothing $clothing, int $days, int $quantity): self
    {
        $item = new self($clothing, $days, $quantity);
        $item->pricePerDay = $clothing->getRentalPricePerDay();
        $item->additionalFee = Fields::money($clothing->additionalFeePerUnit() * $quantity, 'Biaya tambahan');
        return $item;
    }

    public static function restore(Clothing $clothing, array $row): self
    {
        $item = new self($clothing, (int) $row['rental_days'], (int) $row['quantity']);
        $item->id = (int) $row['id'];
        $item->pricePerDay = (int) $row['price_per_day'];
        $item->additionalFee = (int) $row['additional_fee'];
        return $item;
    }

    public function calculateSubtotal(): int
    {
        return Fields::money($this->pricePerDay * $this->rentalDays * $this->quantity + $this->additionalFee, 'Subtotal');
    }

    public function getClothing(): Clothing { return $this->clothing; }
    public function getQuantity(): int { return $this->quantity; }
    public function getRentalDays(): int { return $this->rentalDays; }
    public function getPricePerDay(): int { return $this->pricePerDay; }
    public function getAdditionalFee(): int { return $this->additionalFee; }

    public function toArray(): array
    {
        $clothing = $this->clothing->toArray();
        return [
            'id' => $this->id, 'clothing_id' => $clothing['id'], 'name' => $clothing['name'],
            'image_url' => $clothing['image_url'], 'size' => $clothing['size'], 'type' => $clothing['type'],
            'quantity' => $this->quantity, 'rental_days' => $this->rentalDays,
            'price_per_day' => $this->pricePerDay, 'additional_fee' => $this->additionalFee,
            'subtotal' => $this->calculateSubtotal(),
        ];
    }
}
