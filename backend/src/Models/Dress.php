<?php
declare(strict_types=1);

namespace Rentique\Models;

final class Dress extends Clothing
{
    private string $dressStyle;

    public function __construct(array $row, ClothingCategory $category)
    {
        parent::__construct($row, $category);
        $this->dressStyle = Fields::text($row['dress_style'] ?? null, 'Model gaun', 150);
    }

    public function getType(): string { return 'DRESS'; }
    public function getDressStyle(): string { return $this->dressStyle; }
    protected function attributes(): array { return ['dress_style' => $this->dressStyle]; }
}
