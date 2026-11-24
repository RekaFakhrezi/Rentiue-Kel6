<?php
declare(strict_types=1);

namespace Rentique\Models;

final class ClothingCategory
{
    public function __construct(
        private int $categoryId,
        private string $categoryName,
        private string $description = ''
    ) {}

    public function getCategoryId(): int { return $this->categoryId; }
    public function getCategoryName(): string { return $this->categoryName; }
    public function getDescription(): string { return $this->description; }
}
