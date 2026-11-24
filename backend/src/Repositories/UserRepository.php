<?php
declare(strict_types=1);

namespace Rentique\Repositories;

use DomainException;
use Rentique\Models\User;

final class UserRepository extends Repository
{
    public function model(int $id): User
    {
        $row = $this->db->find('users', $id) ?? throw new DomainException('Pengguna tidak ditemukan.', 404);
        return User::fromRow($row);
    }

    public function authenticate(string $email, string $password): ?array
    {
        foreach ($this->db->rows('users') as $row) {
            if ($row['email'] === strtolower(trim($email))) {
                $user = User::fromRow($row);
                return $user->login($email, $password) ? $user->toArray() : null;
            }
        }
        return null;
    }

    public function find(int $id): ?array
    {
        $row = $this->db->find('users', $id);
        return $row ? User::fromRow($row)->toArray() : null;
    }

    public function update(int $id, array $input): array
    {
        return $this->transaction(function () use ($id, $input) {
            $user = $this->model($id);
            $fields = $input + $user->toArray();
            $user->updateProfile($fields['name'], $fields['email'], $fields['phone'], $fields['address']);
            $row = $user->toArray();
            foreach ($this->db->rows('users') as $other) {
                if ($other['id'] !== $id && $other['email'] === $row['email']) {
                    throw new DomainException('Alamat email sudah digunakan.', 409);
                }
            }
            $this->db->update('users', $id, array_intersect_key($row, array_flip(['name', 'email', 'phone', 'address'])));
            return $row;
        });
    }

    public function customers(): array
    {
        $rows = [];
        $rentals = $this->db->rows('rentals');
        foreach ($this->db->rows('users') as $user) {
            if ($user['role'] !== 'CUSTOMER') { continue; }
            $row = array_intersect_key($user, array_flip(['id', 'name', 'email', 'phone', 'address', 'created_at']));
            $row['rental_count'] = count(array_filter($rentals, static fn($r) => $r['customer_id'] === $user['id']));
            $rows[] = $row;
        }
        usort($rows, static fn($a, $b) => [$a['name'], $a['id']] <=> [$b['name'], $b['id']]);
        return $rows;
    }
}
