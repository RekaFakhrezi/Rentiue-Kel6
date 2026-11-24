<?php
declare(strict_types=1);

namespace Rentique\Models;

final class Costume extends Clothing
{
    private string $theme;
    private int $accessoryFee;

    public function __construct(array $row, ClothingCategory $category)
    {
        parent::__construct($row, $category);
        $this->theme = Fields::text($row['theme'] ?? null, 'Tema kostum', 150);
        $this->accessoryFee = Fields::money($row['accessory_fee'] ?? 0, 'Biaya aksesori');
    }

    public function getType(): string { return 'COSTUME'; }
    public function getTheme(): string { return $this->theme; }
    public function getAccessoryFee(): int { return $this->accessoryFee; }
    public function additionalFeePerUnit(): int { return $this->getAccessoryFee(); }
    protected function attributes(): array { return ['theme' => $this->theme, 'accessory_fee' => $this->accessoryFee]; }
}
