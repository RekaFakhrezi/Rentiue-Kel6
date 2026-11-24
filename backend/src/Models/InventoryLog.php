<?php
declare(strict_types=1);

namespace Rentique\Models;

final class InventoryLog
{
    public function __construct(
        private Clothing $clothing,
        private User $actor,
        private string $changeType,
        private int $quantityChanged,
        private string $notes
    ) {}

    /** Structured record for persistence and the admin logbook. */
    public function record(): array
    {
        return [
            'clothing_id' => $this->clothing->getClothingId(),
            'admin_id' => $this->actor instanceof Admin ? $this->actor->getId() : null,
            'actor_id' => $this->actor->getId(), 'change_type' => $this->changeType,
            'quantity_changed' => $this->quantityChanged, 'notes' => $this->notes,
        ];
    }
}
