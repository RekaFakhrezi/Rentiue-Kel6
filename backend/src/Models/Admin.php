<?php
declare(strict_types=1);

namespace Rentique\Models;

final class Admin extends User
{
    public function getRole(): string { return 'ADMIN'; }

    public function addClothing(Clothing $clothing): InventoryLog
    {
        return new InventoryLog($clothing, $this, 'INITIAL', $clothing->getStock(), 'Pendaftaran stok awal');
    }

    public function adjustStock(Clothing $clothing, int $quantity, string $note): InventoryLog
    {
        if ($quantity > 0) {
            $clothing->increaseStock($quantity);
        } else {
            $clothing->reduceStock(-$quantity);
        }
        return new InventoryLog($clothing, $this, 'ADJUSTMENT', $quantity, $note);
    }

    public function processReturn(RentalReturn $return): void
    {
        $return->finalizeReturn();
    }
}
