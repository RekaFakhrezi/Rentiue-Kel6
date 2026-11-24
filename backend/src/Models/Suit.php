<?php
declare(strict_types=1);

namespace Rentique\Models;

final class Suit extends Clothing
{
    private int $dryCleaningFee;

    public function __construct(array $row, ClothingCategory $category)
    {
        parent::__construct($row, $category);
        $this->dryCleaningFee = Fields::money($row['dry_cleaning_fee'] ?? 0, 'Biaya dry cleaning');
    }

    public function getType(): string { return 'SUIT'; }
    public function getDryCleaningFee(): int { return $this->dryCleaningFee; }
    public function additionalFeePerUnit(): int { return $this->getDryCleaningFee(); }
    protected function attributes(): array { return ['dry_cleaning_fee' => $this->dryCleaningFee]; }
}
