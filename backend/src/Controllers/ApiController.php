<?php
declare(strict_types=1);
namespace Rentique\Controllers;

use DomainException;
use InvalidArgumentException;
use Rentique\Storage\JsonStore;
use Rentique\Middleware\Session;
use Rentique\Repositories\UserRepository;
use Rentique\Services\CatalogService;
use Rentique\Services\DashboardService;
use Rentique\Services\RentalService;

final class ApiController
{
    private readonly UserRepository $users;
    private readonly CatalogService $catalog;
    private readonly RentalService $rentals;
    private readonly DashboardService $reports;
    private ?array $user;

    public function __construct(private readonly JsonStore $store)
    {
        $this->users = new UserRepository($store);
        $this->catalog = new CatalogService($store);
        $this->rentals = new RentalService($store);
        $this->reports = new DashboardService($store);
        $this->user = null;
    }

    public function dispatch(string $method, string $path, array $body, array $query): mixed
    {
        return $this->store->transaction(function () use ($method, $path, $body, $query) {
            $this->user = isset($_SESSION['user_id']) ? $this->users->find((int)$_SESSION['user_id']) : null;
            return $this->route($method, $path, $body, $query);
        }, $method === 'GET' || $path === '/rentals/quote');
    }

    private function route(string $method, string $path, array $body, array $query): mixed
    {
        $isAdmin = ($this->user['role'] ?? null) === 'ADMIN';
        if ($path === '/auth/login' && $method === 'POST') {
            $email = $body['email'] ?? null;
            $password = $body['password'] ?? null;
            if (!is_string($email) || !is_string($password) || strlen($password) > 1024) {
                throw new InvalidArgumentException('Periksa email dan kata sandi Anda.');
            }
            $user = $this->users->authenticate($email, $password);
            if (!$user) {
                throw new DomainException('Email atau kata sandi salah.', 401);
            }
            Session::changeIdentity((int)$user['id']);
            return $this->user = $user;
        }
        if ($method === 'POST' && $path === '/auth/logout') {
            Session::changeIdentity(null);
            return $this->user = null;
        }
        if ($method === 'GET' && $path === '/auth/me') {
            return $this->user;
        }
        if ($method === 'PATCH' && $path === '/auth/profile') {
            return $this->user = $this->users->update(Session::requireRole($this->user)['id'], $body);
        }
        if ($method === 'GET' && $path === '/categories') {
            return $this->catalog->categories();
        }
        if ($path === '/clothings') {
            return match ($method) {
                'GET' => $this->catalog->list($query, $isAdmin),
                'POST' => $this->catalog->create($body, Session::requireRole($this->user, 'ADMIN')['id']),
                default => throw new DomainException('Metode tidak didukung.', 405),
            };
        }
        if (preg_match('~^/clothings/([1-9][0-9]*)(/adjust-stock)?$~D', $path, $match)) {
            $id = (int)$match[1];
            if ($method === 'GET' && !isset($match[2])) {
                return $this->catalog->get($id, $isAdmin);
            }
            $actor = Session::requireRole($this->user, 'ADMIN')['id'];
            return match ($method.($match[2] ?? '')) {
                'PATCH' => $this->catalog->update($id, $body, $actor),
                'DELETE' => $this->catalog->deactivate($id, $actor),
                'POST/adjust-stock' => $this->catalog->adjustStock($id, $body, $actor),
                default => throw new DomainException('Metode tidak didukung.', 405),
            };
        }
        if ($method === 'POST' && $path === '/rentals/quote') {
            return $this->rentals->quote($body);
        }
        if ($path === '/rentals') {
            return match ($method) {
                'GET' => $this->rentals->list(Session::requireRole($this->user)),
                'POST' => $this->rentals->create($body, Session::requireRole($this->user, 'CUSTOMER')['id'], $this->key()),
                default => throw new DomainException('Metode tidak didukung.', 405),
            };
        }
        if (preg_match('~^/rentals/([1-9][0-9]*)(?:/(payments|invoice|return))?$~D', $path, $match)) {
            $id = (int)$match[1];
            $user = Session::requireRole($this->user);
            return match ($method.' '.($match[2] ?? '')) {
                'GET ' => $this->rentals->get($id, $user),
                'GET payments' => $this->rentals->payments($id, $user),
                'POST payments' => $this->rentals->pay($id, $body, $user, $this->key()),
                'GET invoice' => $this->rentals->invoice($id, $user),
                'GET return' => $this->rentals->getReturn($id, $user),
                'POST return' => $this->rentals->processReturn($id, $body, Session::requireRole($user, 'ADMIN')),
                default => throw new DomainException('Metode tidak didukung.', 405),
            };
        }
        if (str_starts_with($path, '/admin/') && $method === 'GET') {
            Session::requireRole($this->user, 'ADMIN');
            return match ($path) {
                '/admin/dashboard' => $this->reports->dashboard(),
                '/admin/customers' => $this->reports->customers(),
                '/admin/inventory-logs' => $this->reports->logs(),
                default => throw new DomainException('Halaman tidak ditemukan.', 404),
            };
        }
        throw new DomainException('Rute API tidak ditemukan.', 404);
    }

    private function key(): string
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_.-]{8,128}$/D', $key)) {
            throw new InvalidArgumentException('Idempotency-Key diperlukan (8–128 karakter).');
        }
        return $key;
    }
}
