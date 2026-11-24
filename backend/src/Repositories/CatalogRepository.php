<?php
declare(strict_types=1);

namespace Rentique\Repositories;

use DomainException;
use InvalidArgumentException;
use Rentique\Models\Clothing;
use Rentique\Models\Fields;
use Rentique\Models\InventoryLog;

final class CatalogRepository extends Repository
{
    public function categories(): array
    {
        $rows = $this->db->rows('clothing_categories');
        usort($rows, static fn($a, $b) => $a['name'] <=> $b['name']);
        return $rows;
    }

    public function category(int $id): array
    {
        return $this->db->find('clothing_categories', $id)
            ?? throw new InvalidArgumentException('Kategori tidak ditemukan.');
    }

    private function withCategory(array $row): array
    {
        $category = $this->category($row['category_id']);
        return $row + ['category_name' => $category['name'], 'category_description' => $category['description']];
    }

    public function clothing(int $id, bool $includeInactive = false): Clothing
    {
        $row = $this->db->find('clothings', $id);
        if (!$row || (!$includeInactive && !$row['is_active'])) {
            throw new DomainException('Pakaian tidak ditemukan.', 404);
        }
        return Clothing::fromRow($this->withCategory($row));
    }

    public function byIds(array $ids, bool $includeInactive): array
    {
        $clothes = [];
        foreach ($this->db->rows('clothings') as $row) {
            if (in_array($row['id'], $ids, true) && ($includeInactive || $row['is_active'])) {
                $clothes[$row['id']] = Clothing::fromRow($this->withCategory($row));
            }
        }
        ksort($clothes, SORT_NUMERIC);
        return $clothes;
    }

    public function list(array $filters, bool $admin): array
    {
        $includeInactive = $admin && filter_var($filters['include_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $search = ($filters['search'] ?? '') !== '' ? Fields::text($filters['search'], 'Pencarian', 180, false) : '';
        $equals = [];
        foreach (['category_id', 'type', 'size'] as $field) {
            if (($filters[$field] ?? '') === '') { continue; }
            $equals[$field] = $field === 'category_id' ? Fields::integer($filters[$field], 'Kategori', 1)
                : Fields::text($filters[$field], $field, 60);
        }
        $available = null;
        if (isset($filters['available']) && $filters['available'] !== '') {
            $available = filter_var($filters['available'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($available === null) { throw new InvalidArgumentException('Filter ketersediaan tidak valid.'); }
        }
        $rows = [];
        foreach ($this->db->rows('clothings') as $row) {
            if (!$includeInactive && !$row['is_active']) { continue; }
            if ($search !== '' && stripos($row['name'].' '.$row['color'].' '.$row['description'], $search) === false) { continue; }
            foreach ($equals as $field => $value) {
                if ($row[$field] !== $value) { continue 2; }
            }
            if ($available !== null && ($row['stock'] > 0 && $row['is_active']) !== $available) { continue; }
            $rows[] = $row;
        }
        $sort = $filters['sort'] ?? '';
        usort($rows, static fn($a, $b) => match ($sort) {
            'price_asc' => [$a['rental_price_per_day'], $a['id']] <=> [$b['rental_price_per_day'], $b['id']],
            'price_desc' => [$b['rental_price_per_day'], $a['id']] <=> [$a['rental_price_per_day'], $b['id']],
            'name' => [$a['name'], $a['id']] <=> [$b['name'], $b['id']],
            'newest' => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']],
            default => $a['id'] <=> $b['id'],
        });
        return array_map(fn($row) => Clothing::fromRow($this->withCategory($row))->toArray(), $rows);
    }

    public function save(Clothing $clothing): int
    {
        $row = $clothing->toArray();
        $id = $row['id'];
        unset($row['id'], $row['category_name']);
        if (!$id) { return $this->insert('clothings', $row); }
        $this->db->update('clothings', $id, $row);
        return $id;
    }

    public function saveStock(Clothing $clothing): void
    {
        $this->db->update('clothings', $clothing->getClothingId(), ['stock' => $clothing->getStock()]);
    }

    public function deactivate(int $id): void
    {
        $this->db->update('clothings', $id, ['is_active' => false]);
    }

    public function log(InventoryLog $log): void { $this->insert('inventory_logs', $log->record()); }

    public function logs(): array
    {
        $rows = $this->db->rows('inventory_logs');
        foreach ($rows as &$row) {
            $clothing = $this->db->find('clothings', $row['clothing_id']);
            $actor = $this->db->find('users', $row['actor_id'] ?? $row['admin_id']);
            $row += ['clothing_name' => $clothing['name'], 'actor_name' => $actor['name'] ?? 'Sistem'];
        }
        unset($row);
        usort($rows, static fn($a, $b) => $b['id'] <=> $a['id']);
        return $rows;
    }

    public function lowStock(): array
    {
        $rows = array_filter($this->db->rows('clothings'), static fn($r) => $r['is_active'] && $r['stock'] <= 2);
        usort($rows, static fn($a, $b) => [$a['stock'], $a['id']] <=> [$b['stock'], $b['id']]);
        return array_map(fn($row) => Clothing::fromRow($this->withCategory($row))->toArray(), $rows);
    }
}
