<?php
declare(strict_types=1);

namespace Rentique\Services;

use Rentique\Storage\JsonStore;
use Rentique\Repositories\CatalogRepository;
use Rentique\Repositories\RentalRepository;
use Rentique\Repositories\UserRepository;

final class DashboardService
{
    public function __construct(private JsonStore $db) {}

    public function dashboard(): array
    {
        $rentals = new RentalRepository($this->db);
        return $rentals->transaction(fn() => $rentals->statistics() + [
            'recent_rentals' => $rentals->listing(null, 8),
            'low_stock_items' => (new CatalogRepository($this->db))->lowStock(),
        ], true);
    }

    public function logs(): array { return (new CatalogRepository($this->db))->logs(); }
    public function customers(): array { return (new UserRepository($this->db))->customers(); }
}
