<?php
declare(strict_types=1);

namespace Rentique\Models;

use DomainException;
use InvalidArgumentException;

abstract class Clothing
{
    private int $clothingId;
    private string $clothingName;
    private string $size;
    private string $color;
    private int $rentalPricePerDay;
    private int $stock;
    private string $condition;
    private string $description;
    private string $imageUrl;
    private bool $isActive;

    public function __construct(array $row, private ClothingCategory $category)
    {
        $this->clothingId = (int) ($row['id'] ?? 0);
        $this->clothingName = Fields::text($row['name'] ?? null, 'Nama pakaian', 180);
        $this->size = Fields::text($row['size'] ?? null, 'Ukuran', 30);
        $this->color = Fields::text($row['color'] ?? null, 'Warna', 80);
        $this->setRentalPricePerDay(Fields::money($row['rental_price_per_day'] ?? null, 'Harga harian'));
        $this->stock = Fields::integer($row['stock'] ?? null, 'Stok');
        $this->condition = Fields::text($row['condition'] ?? 'Baik', 'Kondisi', 180);
        $this->description = Fields::text($row['description'] ?? '', 'Deskripsi', 4000, false);
        $this->imageUrl = Fields::text($row['image_url'] ?? '', 'Foto', 2048, false);
        if ($this->imageUrl !== '' && !preg_match('~^(https?://[^/ ]+|/(?!/))~i', $this->imageUrl)) {
            throw new InvalidArgumentException('Foto harus berupa URL HTTP(S) atau path lokal.');
        }
        $this->isActive = !in_array($row['is_active'] ?? true, [false, 'f', 'false', 0, '0'], true);
    }

    public static function fromRow(array $row): self
    {
        $category = new ClothingCategory(
            Fields::integer($row['category_id'] ?? null, 'Kategori', 1),
            $row['category_name'] ?? '',
            $row['category_description'] ?? ''
        );
        return match ($row['type'] ?? null) {
            'DRESS' => new Dress($row, $category),
            'SUIT' => new Suit($row, $category),
            'COSTUME' => new Costume($row, $category),
            default => throw new InvalidArgumentException('Jenis pakaian tidak dikenal.'),
        };
    }

    abstract public function getType(): string;
    abstract protected function attributes(): array;
    public function additionalFeePerUnit(): int { return 0; }

    public function isAvailable(int $qty): bool
    {
        return $this->isActive && $qty > 0 && $qty <= $this->stock;
    }

    public function reduceStock(int $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Jumlah pengurangan stok harus positif.');
        }
        if ($qty > $this->stock) {
            throw new DomainException('Stok ' . $this->clothingName . ' tidak mencukupi.', 409);
        }
        $this->stock -= $qty;
    }

    public function increaseStock(int $qty): void
    {
        Fields::integer($qty, 'Penambahan stok', 1);
        $this->stock = Fields::integer($this->stock + $qty, 'Stok akhir');
    }

    public function setRentalPricePerDay(int $price): void { $this->rentalPricePerDay = Fields::money($price); }
    public function getRentalPricePerDay(): int { return $this->rentalPricePerDay; }
    public function getStock(): int { return $this->stock; }
    public function getClothingId(): int { return $this->clothingId; }
    public function getClothingName(): string { return $this->clothingName; }

    public function toArray(): array
    {
        return $this->attributes() + [
            'id' => $this->clothingId, 'category_id' => $this->category->getCategoryId(),
            'category_name' => $this->category->getCategoryName(), 'name' => $this->clothingName,
            'type' => $this->getType(), 'size' => $this->size, 'color' => $this->color,
            'rental_price_per_day' => $this->rentalPricePerDay, 'stock' => $this->stock,
            'condition' => $this->condition, 'description' => $this->description,
            'image_url' => $this->imageUrl, 'is_active' => $this->isActive,
            'dress_style' => null, 'dry_cleaning_fee' => null, 'theme' => null, 'accessory_fee' => null,
        ];
    }
}
