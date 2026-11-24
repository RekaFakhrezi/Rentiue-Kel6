<?php
declare(strict_types=1);

namespace Rentique\Repositories;

use Rentique\Storage\JsonStore;

class Repository
{
    public function __construct(protected JsonStore $db) {}

    public function transaction(callable $action, bool $readOnly = false): mixed
    {
        return $this->db->transaction($action, $readOnly);
    }

    protected function insert(string $table, array $fields): int
    {
        return $this->db->insert($table, $fields);
    }
}
