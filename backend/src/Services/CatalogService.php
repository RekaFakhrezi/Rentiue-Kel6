<?php
declare(strict_types=1);

namespace Rentique\Services;

use DomainException;
use Rentique\Storage\JsonStore;
use Rentique\Models\Admin;
use Rentique\Models\Clothing;
use Rentique\Models\Fields;
use Rentique\Repositories\CatalogRepository;
use Rentique\Repositories\UserRepository;

final class CatalogService
{
    private CatalogRepository $catalog;
    private UserRepository $users;

    public function __construct(JsonStore $db)
    {
        $this->catalog = new CatalogRepository($db);
        $this->users = new UserRepository($db);
    }

    public function categories(): array { return $this->catalog->categories(); }
    public function list(array $filters, bool $admin = false): array { return $this->catalog->list($filters, $admin); }
    public function get(int $id, bool $admin = false): array { return $this->catalog->clothing($id, $admin)->toArray(); }

    private function admin(int $id): Admin
    {
        $actor = $this->users->model($id);
        if (!$actor instanceof Admin) { throw new DomainException('Khusus administrator.', 403); }
        return $actor;
    }

    private function clothing(array $input, ?Clothing $existing = null): Clothing
    {
        $fields = array_intersect_key($input, array_flip([
            'name','category_id','type','size','color','rental_price_per_day','stock','condition',
            'description','image_url','dress_style','dry_cleaning_fee','theme','accessory_fee',
        ]));
        $fields += $existing?->toArray() ?? [];
        $category = $this->catalog->category(Fields::integer($fields['category_id'] ?? null, 'Kategori', 1));
        $fields['category_name'] = $category['name'];
        return Clothing::fromRow($fields);
    }

    public function create(array $input, int $adminId): array
    {
        return $this->catalog->transaction(function () use ($input, $adminId) {
            $admin = $this->admin($adminId);
            $id = $this->catalog->save($this->clothing($input));
            $clothing = $this->catalog->clothing($id);
            $this->catalog->log($admin->addClothing($clothing));
            return $clothing->toArray();
        });
    }

    public function update(int $id, array $input, int $adminId): array
    {
        return $this->catalog->transaction(function () use ($id, $input, $adminId) {
            if (array_key_exists('stock', $input) && !array_key_exists('expected_stock', $input)) {
                throw new \InvalidArgumentException('Stok sebelumnya diperlukan untuk mengganti stok absolut.');
            }
            $admin = $this->admin($adminId);
            $existing = $this->catalog->clothing($id, true);
            if (array_key_exists('expected_stock', $input) && Fields::integer($input['expected_stock'], 'Stok sebelumnya') !== $existing->getStock()) {
                throw new DomainException('Stok telah berubah. Muat ulang data pakaian.', 409);
            }
            $updated = $this->clothing($input, $existing);
            $delta = $updated->getStock() - $existing->getStock();
            if ($delta !== 0) {
                $this->catalog->log($admin->adjustStock($existing, $delta, 'Perubahan stok melalui formulir pakaian'));
            }
            $this->catalog->save($updated);
            return $updated->toArray();
        });
    }

    public function adjustStock(int $id, array $input, int $adminId): array
    {
        $delta = Fields::integer($input['quantity_changed'] ?? null, 'Perubahan stok', -2147483647);
        $notes = Fields::text($input['notes'] ?? null, 'Catatan penyesuaian', 2000);
        return $this->catalog->transaction(function () use ($id, $delta, $notes, $adminId) {
            $admin = $this->admin($adminId);
            $clothing = $this->catalog->clothing($id, true);
            $this->catalog->log($admin->adjustStock($clothing, $delta, $notes));
            $this->catalog->saveStock($clothing);
            return $clothing->toArray();
        });
    }

    public function deactivate(int $id, int $adminId): array
    {
        return $this->catalog->transaction(function () use ($id, $adminId) {
            $this->admin($adminId);
            $this->catalog->clothing($id, true);
            $this->catalog->deactivate($id);
            return $this->get($id, true);
        });
    }
}
