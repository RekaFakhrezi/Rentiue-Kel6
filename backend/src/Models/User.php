<?php
declare(strict_types=1);

namespace Rentique\Models;

use InvalidArgumentException;

abstract class User
{
    private int $userId;
    private string $name;
    private string $email;
    private string $phone;
    private string $address;
    private ?string $passwordHash;

    public function __construct(array $row)
    {
        $this->userId = (int) $row['id'];
        $this->passwordHash = $row['password_hash'] ?? null;
        $this->updateProfile($row['name'], $row['email'], $row['phone'] ?? '', $row['address'] ?? '');
    }

    abstract public function getRole(): string;

    public static function fromRow(array $row): self
    {
        return match ($row['role']) {
            'CUSTOMER' => new Customer($row),
            'ADMIN' => new Admin($row),
            default => throw new InvalidArgumentException('Peran pengguna tidak dikenal.'),
        };
    }

    public function updateProfile(mixed $name, mixed $email, mixed $phone, mixed $address): void
    {
        $email = strtolower(Fields::text($email, 'Email', 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Alamat email tidak valid.');
        }
        $this->name = Fields::text($name, 'Nama', 150);
        $this->phone = Fields::text($phone, 'Telepon', 50, false);
        $this->address = Fields::text($address, 'Alamat', 1500, false);
        $this->email = $email;
    }

    public function login(string $email, string $password): bool
    {
        return strtolower(trim($email)) === $this->email && $this->passwordHash !== null
            && password_verify($password, $this->passwordHash);
    }

    public function getId(): int { return $this->userId; }
    public function getName(): string { return $this->name; }

    public function toArray(): array
    {
        return [
            'id' => $this->userId, 'name' => $this->name, 'email' => $this->email,
            'role' => $this->getRole(), 'phone' => $this->phone, 'address' => $this->address,
        ];
    }
}
